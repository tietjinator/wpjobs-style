<?php
/**
 * Puts sample-data/seed.php into blueprint.json, so the Playground preview
 * builds its sample content from it.
 *
 * Run after editing seed.php:   php sample-data/build-blueprint.php
 *
 * Only the "runPHP" step's code is replaced. The rest of blueprint.json (site
 * options, which plugins are installed) is left as it is.
 */

$root      = dirname( __DIR__ );
$blueprint = $root . '/blueprint.json';
$seed      = file_get_contents( __DIR__ . '/seed.php' );

$data = json_decode( file_get_contents( $blueprint ), true );
if ( ! is_array( $data ) ) {
	fwrite( STDERR, "Could not read blueprint.json\n" );
	exit( 1 );
}

// Settings the sample content needs. Set before the plugin starts, because the
// category taxonomy is only registered when categories are switched on.
$data['steps'][0]['options'] = array_merge(
	$data['steps'][0]['options'],
	[
		'job_manager_enable_types'           => 1,
		'job_manager_enable_categories'      => 1,
		'job_manager_enable_remote_position' => 1,
		'job_manager_enable_salary'          => 1,
		'job_manager_enable_salary_currency' => 1,
		'job_manager_enable_salary_unit'     => 1,
	]
);

$replaced = false;
foreach ( $data['steps'] as $i => $step ) {
	if ( isset( $step['step'] ) && 'runPHP' === $step['step'] ) {
		$data['steps'][ $i ]['code'] = $seed;
		$replaced                    = true;
	}
}

if ( ! $replaced ) {
	fwrite( STDERR, "No runPHP step in blueprint.json\n" );
	exit( 1 );
}

// Tabs, like the rest of the repository.
$json = json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
$json = preg_replace_callback( '/^( +)/m', function ( $m ) {
	return str_repeat( "\t", strlen( $m[1] ) / 4 );
}, $json );

file_put_contents( $blueprint, $json . "\n" );
echo 'blueprint.json updated (' . round( strlen( $json ) / 1024 ) . " KB).\n";
