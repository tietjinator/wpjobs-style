<?php
/**
 * Creates one sample listing for every US state, DC and Canadian province,
 * the way the front-end form would: the raw typed values are saved first, then
 * the plugin's `job_manager_update_job_data` action runs, which is where this
 * repo's location fields normalise the state and build the combined location.
 *
 * Run inside WordPress: wp eval-file tests/seed-states.php
 * Or: WP_LOAD=/path/to/wp-load.php php tests/seed-states.php
 * (Also inlined into blueprint-states.json for WordPress Playground.)
 *
 * Mixed on purpose: some states typed as full names, some as lower or upper
 * case codes, some with no city (state and ZIP only), to exercise the lot.
 */

if ( ! defined( 'ABSPATH' ) ) {
	require getenv( 'WP_LOAD' ) ? getenv( 'WP_LOAD' ) : '/wordpress/wp-load.php';
}

// code => [ full name, city, zip ]
$wpjm_states = [
	'AL' => [ 'Alabama', 'Birmingham', '35203' ], 'AK' => [ 'Alaska', 'Anchorage', '99501' ], 'AZ' => [ 'Arizona', 'Phoenix', '85004' ],
	'AR' => [ 'Arkansas', 'Little Rock', '72201' ], 'CA' => [ 'California', 'Los Angeles', '90012' ], 'CO' => [ 'Colorado', 'Denver', '80202' ],
	'CT' => [ 'Connecticut', 'Hartford', '06103' ], 'DE' => [ 'Delaware', 'Wilmington', '19801' ], 'FL' => [ 'Florida', 'Miami', '33131' ],
	'GA' => [ 'Georgia', 'Atlanta', '30303' ], 'HI' => [ 'Hawaii', 'Honolulu', '96813' ], 'ID' => [ 'Idaho', 'Boise', '83702' ],
	'IL' => [ 'Illinois', 'Chicago', '60601' ], 'IN' => [ 'Indiana', 'Indianapolis', '46204' ], 'IA' => [ 'Iowa', 'Des Moines', '50309' ],
	'KS' => [ 'Kansas', 'Wichita', '67202' ], 'KY' => [ 'Kentucky', 'Louisville', '40202' ], 'LA' => [ 'Louisiana', 'New Orleans', '70112' ],
	'ME' => [ 'Maine', 'Portland', '04101' ], 'MD' => [ 'Maryland', 'Baltimore', '21201' ], 'MA' => [ 'Massachusetts', 'Boston', '02108' ],
	'MI' => [ 'Michigan', 'Detroit', '48226' ], 'MN' => [ 'Minnesota', 'Minneapolis', '55401' ], 'MS' => [ 'Mississippi', 'Jackson', '39201' ],
	'MO' => [ 'Missouri', 'Kansas City', '64106' ], 'MT' => [ 'Montana', 'Billings', '59101' ], 'NE' => [ 'Nebraska', 'Omaha', '68102' ],
	'NV' => [ 'Nevada', 'Las Vegas', '89101' ], 'NH' => [ 'New Hampshire', 'Manchester', '03101' ], 'NJ' => [ 'New Jersey', 'Newark', '07102' ],
	'NM' => [ 'New Mexico', 'Albuquerque', '87102' ], 'NY' => [ 'New York', 'Brooklyn', '11201' ], 'NC' => [ 'North Carolina', 'Charlotte', '28202' ],
	'ND' => [ 'North Dakota', 'Fargo', '58102' ], 'OH' => [ 'Ohio', 'Columbus', '43215' ], 'OK' => [ 'Oklahoma', 'Oklahoma City', '73102' ],
	'OR' => [ 'Oregon', 'Portland', '97201' ], 'PA' => [ 'Pennsylvania', 'Philadelphia', '19103' ], 'RI' => [ 'Rhode Island', 'Providence', '02903' ],
	'SC' => [ 'South Carolina', 'Charleston', '29401' ], 'SD' => [ 'South Dakota', 'Sioux Falls', '57104' ], 'TN' => [ 'Tennessee', 'Nashville', '37201' ],
	'TX' => [ 'Texas', 'Austin', '78701' ], 'UT' => [ 'Utah', 'Salt Lake City', '84101' ], 'VT' => [ 'Vermont', 'Burlington', '05401' ],
	'VA' => [ 'Virginia', 'Richmond', '23219' ], 'WA' => [ 'Washington', 'Seattle', '98101' ], 'WV' => [ 'West Virginia', 'Charleston', '25301' ],
	'WI' => [ 'Wisconsin', 'Milwaukee', '53202' ], 'WY' => [ 'Wyoming', 'Cheyenne', '82001' ], 'DC' => [ 'District of Columbia', 'Washington', '20001' ],
	'AB' => [ 'Alberta', 'Calgary', 'T2P 1J9' ], 'BC' => [ 'British Columbia', 'Vancouver', 'V6B 1A1' ], 'MB' => [ 'Manitoba', 'Winnipeg', 'R3C 0A5' ],
	'NB' => [ 'New Brunswick', 'Fredericton', 'E3B 1B5' ], 'NL' => [ 'Newfoundland and Labrador', "St. John's", 'A1C 5S7' ], 'NS' => [ 'Nova Scotia', 'Halifax', 'B3J 1S9' ],
	'NT' => [ 'Northwest Territories', 'Yellowknife', 'X1A 2P2' ], 'NU' => [ 'Nunavut', 'Iqaluit', 'X0A 0H0' ], 'ON' => [ 'Ontario', 'Toronto', 'M5H 2N2' ],
	'PE' => [ 'Prince Edward Island', 'Charlottetown', 'C1A 1M5' ], 'QC' => [ 'Quebec', 'Montreal', 'H2Y 1C6' ], 'SK' => [ 'Saskatchewan', 'Regina', 'S4P 3Y2' ],
	'YT' => [ 'Yukon', 'Whitehorse', 'Y1A 1A1' ],
];

update_option( 'job_manager_enable_types', 1 );
foreach ( [ 'Full Time', 'Part Time' ] as $t ) {
	if ( ! term_exists( $t, 'job_listing_type' ) ) {
		wp_insert_term( $t, 'job_listing_type' );
	}
}

$n = 0;
foreach ( $wpjm_states as $code => $row ) {
	list( $name, $city, $zip ) = $row;

	// Rotate how the state is typed: full name, lower-case code, upper-case code.
	$typed = [ $name, strtolower( $code ), $code ][ $n % 3 ];
	// Every 7th listing has no city (state and ZIP only).
	$typed_city = ( 0 === $n % 7 ) ? '' : $city;

	$id = wp_insert_post(
		[
			'post_title'   => "$name test job",
			'post_content' => "Sample listing for $name.",
			'post_type'    => 'job_listing',
			'post_status'  => 'publish',
			'post_date'    => gmdate( 'Y-m-d H:i:s', time() - ( $n * 3600 ) ),
		]
	);
	wp_set_object_terms( $id, 0 === $n % 2 ? 'Full Time' : 'Part Time', 'job_listing_type' );
	update_post_meta( $id, '_company_name', 'Sample Co' );
	update_post_meta( $id, '_filled', 0 );
	update_post_meta( $id, '_featured', 0 );
	update_post_meta( $id, '_remote_position', 0 );
	update_post_meta( $id, '_application', 'jobs@example.com' );

	// What the front-end form saves for each field, then the plugin's action.
	update_post_meta( $id, '_job_city', $typed_city );
	update_post_meta( $id, '_job_state', $typed );
	update_post_meta( $id, '_job_postcode', $zip );
	do_action(
		'job_manager_update_job_data',
		$id,
		[
			'job' => [
				'job_city'     => $typed_city,
				'job_state'    => $typed,
				'job_postcode' => $zip,
			],
		]
	);

	++$n;
}

echo "Created $n listings.\n";
