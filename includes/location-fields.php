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
 * Full name for a code, for example "TX" gives "Texas". Returns the input
 * unchanged if it is not in the list.
 */
function wpjm_cs_state_name( $code ) {
	static $names = null;

	if ( null === $names ) {
		$names = [];
		foreach ( wpjm_cs_state_map() as $name => $abbr ) {
			// The first name listed for a code is the canonical one.
			if ( ! isset( $names[ $abbr ] ) ) {
				$pretty          = ucwords( $name );
				$pretty          = str_replace( [ ' And ', ' Of ', 'U.s.' ], [ ' and ', ' of ', 'U.S.' ], $pretty );
				$names[ $abbr ] = $pretty;
			}
		}
	}

	$code = strtoupper( trim( (string) $code ) );

	return isset( $names[ $code ] ) ? $names[ $code ] : $code;
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

		// WP Job Manager's Location search also looks in `geolocation_state_long`.
		// Geolocation is off, so fill it ourselves. Searching "Texas" or "TX"
		// then finds the listing even when it has no city.
		if ( '' !== $state ) {
			// "Texas (TX)": the full name for partial searches, and the bracketed
			// code as a whole-word token that only an exact state search matches.
			$name = wpjm_cs_state_name( $state );
			update_post_meta( $job_id, 'geolocation_state_long', $name === $state ? $name : $name . ' (' . $state . ')' );
			update_post_meta( $job_id, 'geolocation_state_short', $state );
		} else {
			delete_post_meta( $job_id, 'geolocation_state_long' );
			delete_post_meta( $job_id, 'geolocation_state_short' );
		}
		update_post_meta( $job_id, '_job_location', wpjm_cs_format_location( $city, $state, $postcode ) );
		update_post_meta( $job_id, '_wpjm_cs_location_split', 1 );
	} elseif ( get_post_meta( $job_id, '_wpjm_cs_location_split', true ) ) {
		update_post_meta( $job_id, '_job_location', '' );
		delete_post_meta( $job_id, 'geolocation_state_long' );
		delete_post_meta( $job_id, 'geolocation_state_short' );
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
// Search
// ---------------------------------------------------------------------------

/**
 * The state code for a search term that is exactly a state name or code, such
 * as "Texas", "texas", "TX" or "tx". Empty string for anything else.
 */
function wpjm_cs_state_code_from_term( $term ) {
	$clean = rtrim( trim( preg_replace( '/\s+/', ' ', (string) $term ) ), '.' );
	if ( '' === $clean ) {
		return '';
	}

	$map = wpjm_cs_state_map();
	$key = strtolower( str_replace( '&', 'and', $clean ) );

	if ( isset( $map[ $key ] ) ) {
		return $map[ $key ];
	}

	return in_array( strtoupper( $clean ), array_values( $map ), true ) ? strtoupper( $clean ) : '';
}

/**
 * Turns "Austin, Texas" or "Austin Texas" into "Austin, TX", the way locations
 * are stored. Returns an empty string when the term does not end in a state.
 */
function wpjm_cs_city_state_term( $term ) {
	$term = trim( (string) $term );
	$pos  = strrpos( $term, ',' );

	if ( false !== $pos ) {
		$city = trim( substr( $term, 0, $pos ) );
		$code = wpjm_cs_state_code_from_term( substr( $term, $pos + 1 ) );

		return ( '' !== $city && '' !== $code ) ? $city . ', ' . $code : '';
	}

	// No comma: try the last three, two, then one word as the state.
	$words = preg_split( '/\s+/', $term );
	for ( $n = min( 3, count( $words ) - 1 ); $n >= 1; $n-- ) {
		$code = wpjm_cs_state_code_from_term( implode( ' ', array_slice( $words, -$n ) ) );
		if ( '' !== $code ) {
			return implode( ' ', array_slice( $words, 0, -$n ) ) . ', ' . $code;
		}
	}

	return '';
}

/**
 * Rewrites the Location box text before the plugin searches with it.
 *
 * The plugin matches the text anywhere in the saved location, so a short code
 * such as "IN" or "OR" would match every listing with those letters inside a
 * word, and "Virginia" would match "West Virginia". For a term that is exactly
 * a state we search the whole-word token "(TX)" saved with each listing (see
 * wpjm_cs_save_combined_location), plus ", TX" for older listings saved as
 * "Austin, TX". "Austin, Texas" is searched as "Austin, TX". Other terms are
 * left as typed. Terms are separated with ";" as in the plugin.
 */
function wpjm_cs_expand_location_search( $search ) {
	$terms = [];

	foreach ( explode( ';', (string) $search ) as $term ) {
		$term = trim( $term );
		if ( '' === $term ) {
			continue;
		}

		$code = wpjm_cs_state_code_from_term( $term );
		if ( '' !== $code ) {
			$terms[] = '(' . $code . ')';
			$terms[] = ', ' . $code;

			// Names that are also a city people search for: "Washington" is the
			// state and the city in DC, so keep the plain text search too.
			$also_text = apply_filters( 'wpjm_cs_state_terms_also_searched_as_text', [ 'washington' ] );
			if ( in_array( strtolower( $term ), $also_text, true ) ) {
				$terms[] = $term;
			}
			continue;
		}

		$terms[]    = $term;
		$normalised = wpjm_cs_city_state_term( $term );
		if ( '' !== $normalised && 0 !== strcasecmp( $normalised, $term ) ) {
			$terms[] = $normalised;
		}
	}

	return implode( '; ', $terms );
}

add_filter(
	'job_manager_get_listings_args',
	function ( $args ) {
		if ( ! empty( $args['search_location'] ) && apply_filters( 'wpjm_cs_abbreviate_state', true ) ) {
			$args['search_location'] = wpjm_cs_expand_location_search( $args['search_location'] );
		}

		return $args;
	}
);

// ---------------------------------------------------------------------------
// State / Province filter in the sidebar
// ---------------------------------------------------------------------------

/**
 * States and provinces that have published listings, as code => count, read
 * from the `_job_state` meta this file saves. Cached; cleared when a listing's
 * state or status changes.
 */
function wpjm_cs_state_counts() {
	$counts = get_transient( 'wpjm_cs_state_counts' );

	if ( false === $counts ) {
		global $wpdb;

		$rows = $wpdb->get_results(
			"SELECT pm.meta_value AS state, COUNT(*) AS total
			FROM {$wpdb->postmeta} pm
			INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
			WHERE pm.meta_key = '_job_state' AND pm.meta_value <> ''
				AND p.post_type = 'job_listing' AND p.post_status = 'publish'
			GROUP BY pm.meta_value" // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Cached below, no user input.
		);

		$counts = [];
		foreach ( (array) $rows as $row ) {
			$counts[ $row->state ] = (int) $row->total;
		}

		set_transient( 'wpjm_cs_state_counts', $counts, HOUR_IN_SECONDS );
	}

	return $counts;
}

function wpjm_cs_clear_state_counts() {
	delete_transient( 'wpjm_cs_state_counts' );
}
add_action( 'transition_post_status', 'wpjm_cs_clear_state_counts' );
add_action( 'deleted_post', 'wpjm_cs_clear_state_counts' );
add_action(
	'updated_post_meta',
	function ( $meta_id, $post_id, $meta_key ) {
		if ( '_job_state' === $meta_key ) {
			wpjm_cs_clear_state_counts();
		}
	},
	10,
	3
);
add_action(
	'added_post_meta',
	function ( $meta_id, $post_id, $meta_key ) {
		if ( '_job_state' === $meta_key ) {
			wpjm_cs_clear_state_counts();
		}
	},
	10,
	3
);

// Print the dropdown inside the filter form, after the Location box.
add_action(
	'job_manager_job_filters_search_jobs_end',
	function () {
		$counts = wpjm_cs_state_counts();
		if ( ! $counts ) {
			return;
		}

		$canada = [ 'AB', 'BC', 'MB', 'NB', 'NL', 'NS', 'NT', 'NU', 'ON', 'PE', 'QC', 'SK', 'YT' ];
		$us     = array_values( array_unique( array_values( wpjm_cs_state_map() ) ) );
		$groups = [
			__( 'United States', 'wpjm-custom-styles' ) => [],
			__( 'Canada', 'wpjm-custom-styles' )        => [],
			__( 'Other', 'wpjm-custom-styles' )         => [],
		];
		$keys   = array_keys( $groups );

		foreach ( $counts as $state => $total ) {
			$group                              = in_array( $state, $canada, true ) ? $keys[1] : ( in_array( $state, $us, true ) ? $keys[0] : $keys[2] );
			$groups[ $group ][ (string) $state ] = $total;
		}

		echo '<div class="search_state">';
		echo '<label for="search_state">' . esc_html__( 'State / Province', 'wpjm-custom-styles' ) . '</label>';
		// `job-manager-filter` makes the plugin's JS search again on change and clear it on Reset.
		echo '<select name="filter_state" id="search_state" class="job-manager-filter">';
		echo '<option value="">' . esc_html__( 'Any state / province', 'wpjm-custom-styles' ) . '</option>';

		foreach ( $groups as $label => $states ) {
			if ( ! $states ) {
				continue;
			}

			// Sort by the name shown, not the code.
			uksort(
				$states,
				function ( $a, $b ) {
					return strcasecmp( wpjm_cs_state_name( $a ), wpjm_cs_state_name( $b ) );
				}
			);

			echo '<optgroup label="' . esc_attr( $label ) . '">';
			foreach ( $states as $state => $total ) {
				printf(
					'<option value="%s">%s (%d)</option>',
					esc_attr( $state ),
					esc_html( wpjm_cs_state_name( $state ) ),
					(int) $total
				);
			}
			echo '</optgroup>';
		}

		echo '</select></div>';
	},
	15
);

/**
 * The value of one field in the filter form's submitted data. The plugin's JS
 * sends the whole form as one `form_data` string.
 */
function wpjm_cs_form_value( $name ) {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public, read-only listing query.
	$form_data = isset( $_REQUEST['form_data'] ) ? wp_unslash( $_REQUEST['form_data'] ) : '';
	if ( ! is_string( $form_data ) || '' === $form_data ) {
		return '';
	}

	parse_str( $form_data, $fields );

	return isset( $fields[ $name ] ) && is_string( $fields[ $name ] ) ? sanitize_text_field( $fields[ $name ] ) : '';
}

// Pass the chosen state to the listing query.
add_filter(
	'job_manager_get_listings_args',
	function ( $args ) {
		$state = wpjm_cs_form_value( 'filter_state' );
		if ( '' !== $state ) {
			$args['wpjm_cs_state'] = $state;
		}

		return $args;
	}
);

add_filter(
	'get_job_listings_query_args',
	function ( $query_args, $args ) {
		if ( empty( $args['wpjm_cs_state'] ) ) {
			return $query_args;
		}

		$state  = $args['wpjm_cs_state'];
		$clause = [
			'relation' => 'OR',
			[
				'key'     => '_job_state',
				'value'   => $state,
				'compare' => '=',
			],
		];

		// Older listings saved as "Austin, TX" have no `_job_state` yet.
		if ( 2 === strlen( $state ) ) {
			$clause[] = [
				'key'     => '_job_location',
				'value'   => ', ' . $state,
				'compare' => 'LIKE',
			];
		}

		if ( ! isset( $query_args['meta_query'] ) || ! is_array( $query_args['meta_query'] ) ) {
			$query_args['meta_query'] = [];
		}
		$query_args['meta_query'][] = $clause;

		return $query_args;
	},
	10,
	2
);
