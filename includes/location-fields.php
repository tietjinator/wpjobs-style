<?php
/**
 * Splits the single Location field into City, State / Province and ZIP / Postal code.
 *
 * Everything WP Job Manager does with location (listing cards, the single job
 * page, the Location search box) reads one text value, `_job_location`. So the
 * three parts are saved on their own (`_job_city`, `_job_state`,
 * `_job_postcode`) and also combined into `_job_location`, for example
 * "Austin, TX 78701". Nothing in WP Job Manager is edited: this only uses its
 * documented filters and actions.
 *
 * Listings that predate this keep their old `_job_location` text until someone
 * fills in the new fields. Remote listings with no city, state or ZIP end up
 * with an empty location, which the plugin shows as "Remote".
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * State and province names mapped to their two-letter codes (US states, DC,
 * US territories and Canadian provinces). Lower-case keys. Add or remove
 * entries with the `wpjm_cs_state_map` filter.
 */
function wpjm_cs_state_map() {
	static $map = null;

	if ( null === $map ) {
		$map = [
		'alabama' => 'AL',
		'alaska' => 'AK',
		'arizona' => 'AZ',
		'arkansas' => 'AR',
		'california' => 'CA',
		'colorado' => 'CO',
		'connecticut' => 'CT',
		'delaware' => 'DE',
		'florida' => 'FL',
		'georgia' => 'GA',
		'hawaii' => 'HI',
		'idaho' => 'ID',
		'illinois' => 'IL',
		'indiana' => 'IN',
		'iowa' => 'IA',
		'kansas' => 'KS',
		'kentucky' => 'KY',
		'louisiana' => 'LA',
		'maine' => 'ME',
		'maryland' => 'MD',
		'massachusetts' => 'MA',
		'michigan' => 'MI',
		'minnesota' => 'MN',
		'mississippi' => 'MS',
		'missouri' => 'MO',
		'montana' => 'MT',
		'nebraska' => 'NE',
		'nevada' => 'NV',
		'new hampshire' => 'NH',
		'new jersey' => 'NJ',
		'new mexico' => 'NM',
		'new york' => 'NY',
		'north carolina' => 'NC',
		'north dakota' => 'ND',
		'ohio' => 'OH',
		'oklahoma' => 'OK',
		'oregon' => 'OR',
		'pennsylvania' => 'PA',
		'rhode island' => 'RI',
		'south carolina' => 'SC',
		'south dakota' => 'SD',
		'tennessee' => 'TN',
		'texas' => 'TX',
		'utah' => 'UT',
		'vermont' => 'VT',
		'virginia' => 'VA',
		'washington' => 'WA',
		'west virginia' => 'WV',
		'wisconsin' => 'WI',
		'wyoming' => 'WY',
		'district of columbia' => 'DC',
		'washington dc' => 'DC',
		'washington d.c' => 'DC',
		'washington, dc' => 'DC',
		'puerto rico' => 'PR',
		'guam' => 'GU',
		'u.s. virgin islands' => 'VI',
		'us virgin islands' => 'VI',
		'virgin islands' => 'VI',
		'american samoa' => 'AS',
		'northern mariana islands' => 'MP',
		'alberta' => 'AB',
		'british columbia' => 'BC',
		'manitoba' => 'MB',
		'new brunswick' => 'NB',
		'newfoundland and labrador' => 'NL',
		'newfoundland' => 'NL',
		'nova scotia' => 'NS',
		'northwest territories' => 'NT',
		'nunavut' => 'NU',
		'ontario' => 'ON',
		'prince edward island' => 'PE',
		'quebec' => 'QC',
		'québec' => 'QC',
		'saskatchewan' => 'SK',
		'yukon' => 'YT',
		];
	}

	return apply_filters( 'wpjm_cs_state_map', $map );
}

/**
 * "Texas" or "texas" becomes "TX". A value that is already a valid code is
 * upper-cased ("tx" becomes "TX"). Anything else, such as a region outside the
 * list, is left as typed. Return false from `wpjm_cs_abbreviate_state` to turn
 * this off.
 */
function wpjm_cs_abbreviate_state( $state ) {
	$state = trim( preg_replace( '/\s+/', ' ', (string) $state ) );

	if ( '' === $state || ! apply_filters( 'wpjm_cs_abbreviate_state', true ) ) {
		return $state;
	}

	$map   = wpjm_cs_state_map();
	$clean = rtrim( $state, '.' );
	$key   = strtolower( str_replace( '&', 'and', $clean ) );

	if ( isset( $map[ $key ] ) ) {
		return $map[ $key ];
	}

	if ( in_array( strtoupper( $clean ), array_values( $map ), true ) ) {
		return strtoupper( $clean );
	}

	return $state;
}

/**
 * Combine the parts, for example "Austin, TX 78701". Filterable.
 */
function wpjm_cs_format_location( $city, $state, $postcode ) {
	$city     = trim( (string) $city );
	$region   = trim( trim( (string) $state ) . ' ' . trim( (string) $postcode ) );
	$location = implode( ', ', array_filter( [ $city, $region ] ) );

	return apply_filters( 'wpjm_cs_format_location', $location, $city, $state, $postcode );
}

/**
 * Save the combined location for a listing from the three parts.
 *
 * If any part is filled in, `_job_location` is rebuilt from them. If all are
 * empty and this listing was split before, the location is cleared. If all are
 * empty on a listing that was never split (an older listing), the old text is
 * left alone so editing it does not wipe its location.
 */
function wpjm_cs_save_combined_location( $job_id, $city, $state, $postcode ) {
	$city     = sanitize_text_field( $city );
	$state    = wpjm_cs_abbreviate_state( sanitize_text_field( $state ) );
	$postcode = sanitize_text_field( $postcode );

	if ( '' !== $city || '' !== $state || '' !== $postcode ) {
		// Store the short code, so the form and admin box show "TX" next time.
		update_post_meta( $job_id, '_job_state', $state );
		update_post_meta( $job_id, '_job_location', wpjm_cs_format_location( $city, $state, $postcode ) );
		update_post_meta( $job_id, '_wpjm_cs_location_split', 1 );
	} elseif ( get_post_meta( $job_id, '_wpjm_cs_location_split', true ) ) {
		update_post_meta( $job_id, '_job_location', '' );
	}
}

// ---------------------------------------------------------------------------
// Front end: the "Post a Job" and edit forms.
// ---------------------------------------------------------------------------

add_filter(
	'submit_job_form_fields',
	function ( $fields ) {
		if ( ! isset( $fields['job'] ) ) {
			return $fields;
		}

		// Take the old single Location field's place in the form.
		$priority = isset( $fields['job']['job_location']['priority'] ) ? (float) $fields['job']['job_location']['priority'] : 2;
		unset( $fields['job']['job_location'] );

		$fields['job']['job_city']     = [
			'label'       => __( 'City', 'wpjm-custom-styles' ),
			'type'        => 'text',
			'required'    => false,
			'placeholder' => __( 'e.g. "Austin"', 'wpjm-custom-styles' ),
			'priority'    => $priority,
		];
		$fields['job']['job_state']    = [
			'label'       => __( 'State / Province', 'wpjm-custom-styles' ),
			'type'        => 'text',
			'required'    => false,
			'description' => __( 'Full names are shortened automatically, for example Texas becomes TX.', 'wpjm-custom-styles' ),
			'placeholder' => __( 'e.g. "TX"', 'wpjm-custom-styles' ),
			'priority'    => $priority + 0.1,
		];
		$fields['job']['job_postcode'] = [
			'label'       => __( 'ZIP / Postal code', 'wpjm-custom-styles' ),
			'description' => __( 'Leave city, state and ZIP blank if the location is not important.', 'wpjm-custom-styles' ),
			'type'        => 'text',
			'required'    => false,
			'placeholder' => __( 'e.g. "78701"', 'wpjm-custom-styles' ),
			'priority'    => $priority + 0.2,
		];

		return $fields;
	}
);

add_action(
	'job_manager_update_job_data',
	function ( $job_id, $values ) {
		if ( ! isset( $values['job'] ) ) {
			return;
		}

		wpjm_cs_save_combined_location(
			$job_id,
			isset( $values['job']['job_city'] ) ? $values['job']['job_city'] : '',
			isset( $values['job']['job_state'] ) ? $values['job']['job_state'] : '',
			isset( $values['job']['job_postcode'] ) ? $values['job']['job_postcode'] : ''
		);
	},
	10,
	2
);

// ---------------------------------------------------------------------------
// Admin: the "Job Listing Data" box on the edit screen.
// ---------------------------------------------------------------------------

add_filter(
	'job_manager_job_listing_data_fields',
	function ( $fields ) {
		$priority = isset( $fields['_job_location']['priority'] ) ? (float) $fields['_job_location']['priority'] : 2;

		$fields['_job_city']     = [
			'label'    => __( 'City', 'wpjm-custom-styles' ),
			'type'     => 'text',
			'priority' => $priority + 0.1,
		];
		$fields['_job_state']    = [
			'label'    => __( 'State / Province', 'wpjm-custom-styles' ),
			'type'     => 'text',
			'priority' => $priority + 0.2,
		];
		$fields['_job_postcode'] = [
			'label'    => __( 'ZIP / Postal code', 'wpjm-custom-styles' ),
			'type'     => 'text',
			'priority' => $priority + 0.3,
		];

		// The combined text is still what the plugin displays and searches.
		if ( isset( $fields['_job_location'] ) ) {
			$fields['_job_location']['label']       = __( 'Location (combined)', 'wpjm-custom-styles' );
			$fields['_job_location']['description'] = __( 'Filled in automatically from City, State / Province and ZIP / Postal code. Only edit this directly for listings that have none of those.', 'wpjm-custom-styles' );
		}

		return $fields;
	}
);

// After the plugin has saved the admin fields (it does so at priority 20).
add_action(
	'job_manager_save_job_listing',
	function ( $post_id ) {
		wpjm_cs_save_combined_location(
			$post_id,
			(string) get_post_meta( $post_id, '_job_city', true ),
			(string) get_post_meta( $post_id, '_job_state', true ),
			(string) get_post_meta( $post_id, '_job_postcode', true )
		);
	},
	30
);

// ---------------------------------------------------------------------------
// Search: keep "Texas" working now that listings store "TX".
// ---------------------------------------------------------------------------

// The plugin matches the Location box against the saved text. If someone types
// a full state name, also search for its code, written ", TX" (the way it
// appears after the city) so a short code does not match inside other words.
add_filter(
	'job_manager_get_listings_args',
	function ( $args ) {
		if ( empty( $args['search_location'] ) || ! apply_filters( 'wpjm_cs_abbreviate_state', true ) ) {
			return $args;
		}

		$map   = wpjm_cs_state_map();
		$terms = [];

		foreach ( explode( ';', $args['search_location'] ) as $term ) {
			$term = trim( $term );
			if ( '' === $term ) {
				continue;
			}

			$terms[] = $term;

			$key = strtolower( $term );
			if ( isset( $map[ $key ] ) ) {
				$terms[] = ', ' . $map[ $key ];
			}
		}

		$args['search_location'] = implode( '; ', $terms );

		return $args;
	}
);
