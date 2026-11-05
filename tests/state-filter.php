<?php
/**
 * Checks the State / Province dropdown on a site seeded with
 * tests/seed-states.php: the dropdown is on the Jobs page, and choosing each
 * state returns exactly that state's listing, alone and combined with other
 * filters.
 *
 * Usage: php tests/state-filter.php http://127.0.0.1:8099
 */

$base = isset( $argv[1] ) ? rtrim( $argv[1], '/' ) : 'http://127.0.0.1:8099';

$src = file_get_contents( __DIR__ . '/seed-states.php' );
preg_match( '/\$wpjm_states = \[(.*?)\n\];/s', $src, $m );
eval( '$states = [' . $m[1] . '];' ); // phpcs:ignore Squiz.PHP.Eval -- test script reading its own data table.

function wpjm_post( $base, $fields ) {
	$form                = array_intersect_key( $fields, array_flip( [ 'search_keywords', 'search_location', 'filter_state', 'sort' ] ) );
	$fields['per_page']  = 200;
	$fields['page']      = 1;
	$fields['orderby']   = 'featured';
	$fields['order']     = 'DESC';
	$fields['form_data'] = http_build_query( $form );
	$ctx                 = stream_context_create( [ 'http' => [ 'method' => 'POST', 'header' => "Content-Type: application/x-www-form-urlencoded\r\n", 'content' => http_build_query( $fields ), 'timeout' => 30 ] ] );
	$json                = json_decode( file_get_contents( $base . '/?jm-ajax=get_listings', false, $ctx ), true );
	preg_match_all( '#<h3>(.*?)</h3>#s', isset( $json['html'] ) ? $json['html'] : '', $titles );

	return array_map( 'html_entity_decode', $titles[1] );
}

$fail = 0;
$check = function ( $label, $ok, $got = '' ) use ( &$fail ) {
	if ( ! $ok ) {
		++$fail;
		echo "FAIL $label $got\n";
	}
};

// 1. Each state, chosen on its own, returns exactly its listing.
// Listings are titled "Listing 01", "Listing 02"... in the order of the seed table.
$title = [];
$i     = 0;
foreach ( array_keys( $states ) as $code ) {
	$title[ $code ] = sprintf( 'Listing %02d', ++$i );
}
foreach ( $states as $code => $row ) {
	$got = wpjm_post( $base, [ 'filter_state' => $code ] );
	$check( "filter $code", $got === [ $title[ $code ] ], '=> ' . implode( ', ', $got ) );
}
echo 'Each of ' . count( $states ) . " states and provinces alone: done\n";

// 2. Combined with the other filters.
$got = wpjm_post( $base, [ 'filter_state' => 'TX', 'search_location' => 'Texas' ] );
$check( 'TX + location Texas', $got === [ $title['TX'] ], implode( ',', $got ) );
$got = wpjm_post( $base, [ 'filter_state' => 'TX', 'search_location' => 'Ohio' ] );
$check( 'TX + location Ohio gives nothing', [] === $got, implode( ',', $got ) );
$got = wpjm_post( $base, [ 'filter_state' => 'OH', 'search_keywords' => 'Ohio' ] );
$check( 'OH + keyword Cleveland-free text', [ $title['OH'] ] === wpjm_post( $base, [ 'filter_state' => 'OH', 'search_keywords' => 'Columbus' ] ), '' );
$got = wpjm_post( $base, [ 'filter_state' => 'OH', 'search_keywords' => 'Austin' ] );
$check( 'OH + keyword Austin gives nothing', [] === $got, implode( ',', $got ) );
$got = wpjm_post( $base, [ 'filter_state' => 'OH', 'remote_position' => 'true' ] );
$check( 'OH + remote only gives nothing (Ohio is not remote)', [] === $got, implode( ',', $got ) );

// 3. Edge cases.
$got = wpjm_post( $base, [ 'filter_state' => '' ] );
$check( 'empty filter returns every listing', count( $got ) === count( $states ), count( $got ) );
$got = wpjm_post( $base, [ 'filter_state' => 'ZZ' ] );
$check( 'unknown state gives nothing', [] === $got, implode( ',', $got ) );
$got = wpjm_post( $base, [ 'filter_state' => "TX' OR 1=1 -- " ] );
$check( 'injection attempt gives nothing', [] === $got, implode( ',', $got ) );
$got = wpjm_post( $base, [ 'filter_state' => 'IN' ] );
$check( 'IN is only Indiana, not every listing with "in"', $got === [ $title['IN'] ], implode( ',', $got ) );
$got = wpjm_post( $base, [ 'filter_state' => 'VA' ] );
$check( 'VA is not West Virginia', $got === [ $title['VA'] ], implode( ',', $got ) );
$got = wpjm_post( $base, [ 'filter_state' => 'WA' ] );
$check( 'WA is not DC', $got === [ $title['WA'] ], implode( ',', $got ) );

echo $fail ? "\n$fail FAILED\n" : "\nAll state filter checks passed.\n";
exit( $fail ? 1 : 0 );
