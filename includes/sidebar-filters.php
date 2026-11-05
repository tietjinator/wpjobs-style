<?php
/**
 * Sidebar filter extras for WP Job Manager.
 *
 * Adds a "Sort by" control to the [jobs] filter form and applies it to the
 * AJAX listing query. The layout itself is pure CSS (css/wpjm-sidebar.css).
 *
 * The plugin's JS re-runs the search whenever an element with the class
 * `job-manager-filter` changes, and it sends the whole form as `form_data`,
 * which is where we read the chosen sort from.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sort options: key => [ label, orderby, order ].
 * The empty key is the default and leaves the shortcode's own ordering alone.
 */
function wpjm_cs_sort_options() {
	return apply_filters(
		'wpjm_cs_sort_options',
		[
			''           => [ __( 'Featured first', 'wpjm-custom-styles' ), null, null ],
			'newest'     => [ __( 'Newest', 'wpjm-custom-styles' ), 'date', 'DESC' ],
			'oldest'     => [ __( 'Oldest', 'wpjm-custom-styles' ), 'date', 'ASC' ],
			'title_asc'  => [ __( 'Title A to Z', 'wpjm-custom-styles' ), 'title', 'ASC' ],
			'title_desc' => [ __( 'Title Z to A', 'wpjm-custom-styles' ), 'title', 'DESC' ],
		]
	);
}

// Render the sort control inside the filter form.
add_action(
	'job_manager_job_filters_search_jobs_end',
	function () {
		echo '<div class="search_sort">';
		echo '<label for="wpjm_sort">' . esc_html__( 'Sort by', 'wpjm-custom-styles' ) . '</label>';
		echo '<select name="sort" id="wpjm_sort" class="job-manager-filter">';
		foreach ( wpjm_cs_sort_options() as $key => $option ) {
			echo '<option value="' . esc_attr( $key ) . '">' . esc_html( $option[0] ) . '</option>';
		}
		echo '</select></div>';
	},
	20
);

// Apply the chosen sort to the AJAX listing query.
add_filter(
	'job_manager_get_listings_args',
	function ( $args ) {
		// Nonce is not applicable: this is a public, read-only listing query.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$form_data = isset( $_REQUEST['form_data'] ) ? wp_unslash( $_REQUEST['form_data'] ) : '';
		if ( ! is_string( $form_data ) || '' === $form_data ) {
			return $args;
		}

		parse_str( $form_data, $fields );
		$sort    = isset( $fields['sort'] ) && is_string( $fields['sort'] ) ? sanitize_key( $fields['sort'] ) : '';
		$options = wpjm_cs_sort_options();

		if ( '' !== $sort && isset( $options[ $sort ] ) && $options[ $sort ][1] ) {
			$args['orderby'] = $options[ $sort ][1];
			$args['order']   = $options[ $sort ][2];
			// Honour the explicit sort instead of pinning featured listings on top.
			$args['featured_first'] = false;
		}

		return $args;
	}
);

// Drop the "Showing N jobs" message and its Reset and RSS links. Reset is
// replaced by the "Reset Filters" button rendered inside the form below.
add_filter(
	'job_manager_job_filters_showing_jobs_links',
	function () {
		return [];
	}
);
add_filter( 'job_manager_get_listings_custom_filter_text', '__return_empty_string' );

// "Reset Filters" button, under the Search Jobs button. The plugin's JS handles
// clicks on any `.reset` element inside the filter form: it clears the keyword,
// location, category, job type, remote and sort fields and reloads the results.
add_action(
	'job_manager_job_filters_search_jobs_end',
	function () {
		echo '<div class="search_reset"><button type="button" class="reset">' . esc_html__( 'Reset Filters', 'wpjm-custom-styles' ) . '</button></div>';
	},
	30
);
