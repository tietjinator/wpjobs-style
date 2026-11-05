<?php
/**
 * Runs the Location search for every state and province seeded by
 * tests/seed-states.php, the same way the sidebar does (POST to the plugin's
 * get_listings AJAX endpoint with the filter form's data), and reports whether
 * the right listing comes back and what else matches with it.
 *
 * Usage: php tests/search-matrix.php http://127.0.0.1:8099
 */

$base = isset( $argv[1] ) ? rtrim( $argv[1], '/' ) : 'http://127.0.0.1:8099';

// Pull the seed table out of the seed file without running it.
$src = file_get_contents( __DIR__ . '/seed-states.php' );
preg_match( '/\$wpjm_states = \[(.*?)\n\];/s', $src, $m );
eval( '$states = [' . $m[1] . '];' ); // phpcs:ignore Squiz.PHP.Eval -- test script reading its own data table.

function search( $base, $term ) {
	$body    = http_build_query(
		[
			'search_location' => $term,
			'per_page'        => 200,
			'page'            => 1,
			'orderby'         => 'featured',
			'order'           => 'DESC',
			'form_data'       => http_build_query( [ 'search_location' => $term, 'sort' => '' ] ),
		]
	);
	$context = stream_context_create( [ 'http' => [ 'method' => 'POST', 'header' => "Content-Type: application/x-www-form-urlencoded\r\n", 'content' => $body, 'timeout' => 30 ] ] );
	$json    = json_decode( file_get_contents( $base . '/?jm-ajax=get_listings', false, $context ), true );
	preg_match_all( '#<h3>(.*?)</h3>#s', isset( $json['html'] ) ? $json['html'] : '', $titles );

	return array_map( 'html_entity_decode', $titles[1] );
}

$types   = [
	'CODE'         => fn( $c, $n, $city, $zip ) => $c,
	'code'         => fn( $c, $n, $city, $zip ) => strtolower( $c ),
	'Full Name'    => fn( $c, $n, $city, $zip ) => $n,
	'full name'    => fn( $c, $n, $city, $zip ) => strtolower( $n ),
	'FULL NAME'    => fn( $c, $n, $city, $zip ) => strtoupper( $n ),
	'City'         => fn( $c, $n, $city, $zip ) => $city,
	'ZIP'          => fn( $c, $n, $city, $zip ) => $zip,
	'City, CODE'   => fn( $c, $n, $city, $zip ) => "$city, $c",
	'City, Full'   => fn( $c, $n, $city, $zip ) => "$city, $n",
	'City CODE'    => fn( $c, $n, $city, $zip ) => "$city $c",
	'City Full'    => fn( $c, $n, $city, $zip ) => "$city $n",
	'Name; junk'   => fn( $c, $n, $city, $zip ) => "$n; Nowhereville",
];
// Searches that include the city only make sense for listings that have one.
$needs_city = [ 'City', 'City, CODE', 'City, Full', 'City CODE', 'City Full' ];
$stats   = [];
$details = [];

$index = -1;
foreach ( $states as $code => $row ) {
	list( $name, $city, $zip ) = $row;
	$expected                  = "$name test job";
	++$index;
	// tests/seed-states.php leaves the city off every 7th listing.
	$has_city = 0 !== $index % 7;
	foreach ( $types as $label => $make ) {
		if ( ! $has_city && in_array( $label, $needs_city, true ) ) {
			continue;
		}
		$term   = $make( $code, $name, $city, $zip );
		$titles = search( $base, $term );
		$hit    = in_array( $expected, $titles, true );
		$extras = array_values( array_diff( $titles, [ $expected ] ) );

		$stats[ $label ]['n']      = ( isset( $stats[ $label ]['n'] ) ? $stats[ $label ]['n'] : 0 ) + 1;
		$stats[ $label ]['hit']    = ( isset( $stats[ $label ]['hit'] ) ? $stats[ $label ]['hit'] : 0 ) + ( $hit ? 1 : 0 );
		$stats[ $label ]['extras'] = ( isset( $stats[ $label ]['extras'] ) ? $stats[ $label ]['extras'] : 0 ) + count( $extras );
		$stats[ $label ]['clean']  = ( isset( $stats[ $label ]['clean'] ) ? $stats[ $label ]['clean'] : 0 ) + ( $hit && ! $extras ? 1 : 0 );
		if ( ! $hit ) {
			$details[ $label ]['miss'][] = $term;
		}
		if ( $extras ) {
			$details[ $label ]['extra'][ $term ] = count( $extras );
		}
	}
}

printf( "%-12s %6s %8s %12s %14s\n", 'Search', 'Tried', 'Found', 'Exact only', 'Extra matches' );
foreach ( $stats as $label => $s ) {
	printf( "%-12s %6d %8d %12d %14d\n", $label, $s['n'], $s['hit'], $s['clean'], $s['extras'] );
}

echo "\nMISSES (the right listing did not come back):\n";
foreach ( $details as $label => $d ) {
	if ( ! empty( $d['miss'] ) ) {
		echo "  $label: " . count( $d['miss'] ) . ' misses, e.g. ' . implode( ' | ', array_slice( $d['miss'], 0, 6 ) ) . "\n";
	}
}
echo "\nMOST EXTRA MATCHES per search type (term => other listings returned):\n";
foreach ( $details as $label => $d ) {
	if ( ! empty( $d['extra'] ) ) {
		arsort( $d['extra'] );
		$top = array_slice( $d['extra'], 0, 6, true );
		echo "  $label: ";
		foreach ( $top as $t => $c ) {
			echo "$t(+$c) ";
		}
		echo "\n";
	}
}
