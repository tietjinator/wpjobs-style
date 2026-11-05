<?php
/**
 * Sidebar filter extras for WP Job Manager.
 *
 * Adds a "Sort by" control above the results and applies it to the AJAX
 * listing query. The layout itself is pure CSS (css/wpjm-sidebar.css).
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

// Render the sort control as a bar above the results (styled to sit at the top
// right of them). It is output after the filter form, inside the listings
// wrapper, and linked back to the form by js/sidebar.js so it is still sent
// with the other filters. The class `job-manager-filter` makes the plugin's JS
// re-run the search when it changes.
add_action(
	'job_manager_job_filters_after',
	function () {
		wp_enqueue_script(
			'wpjm-custom-sidebar',
			plugins_url( 'js/sidebar.js', dirname( __FILE__ ) ),
			[],
			filemtime( dirname( __DIR__ ) . '/js/sidebar.js' ),
			true
		);

		echo '<div class="wpjm-sort-bar">';
		echo '<label for="wpjm_sort">' . esc_html__( 'Sort by', 'wpjm-custom-styles' ) . '</label>';
		echo '<select name="sort" id="wpjm_sort" class="job-manager-filter">';
		foreach ( wpjm_cs_sort_options() as $key => $option ) {
			echo '<option value="' . esc_attr( $key ) . '">' . esc_html( $option[0] ) . '</option>';
		}
		echo '</select></div>';
	}
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

/**
 * Job type "drawer": the job type checkboxes and the "Remote positions only"
 * checkbox inside one collapsible <details> block.
 *
 * The plugin prints the job types at `job_manager_job_filters_end` priority 20
 * and the remote checkbox earlier, inside the search block. We hide its copy of
 * the remote checkbox, capture the job types output, and print both inside the
 * drawer. Field names and ids are unchanged, so the plugin's JS (search on
 * change, Reset) keeps working. Drawer is open by default; return false from
 * the `wpjm_cs_types_drawer_open` filter to start it closed.
 */
function wpjm_cs_remote_enabled( $set = null ) {
	static $enabled = false;
	if ( null !== $set ) {
		$enabled = (bool) $set;
	}
	return $enabled;
}

// Remember whether the remote checkbox is enabled, then stop the template printing it.
add_filter(
	'job_manager_job_filters_show_remote_position',
	function ( $show ) {
		wpjm_cs_remote_enabled( $show );
		return false;
	},
	999
);

add_action(
	'job_manager_job_filters_end',
	function () {
		ob_start();
	},
	19
);

add_action(
	'job_manager_job_filters_end',
	function ( $atts ) {
		$types_html = ob_get_clean();
		$has_list   = false !== strpos( (string) $types_html, 'class="job_types"' );
		$remote     = '';

		if ( wpjm_cs_remote_enabled() ) {
			$checked = ! empty( $atts['remote_position'] ) ? ' checked="checked"' : '';
			$remote  = '<div class="search_remote_position">'
				. '<input type="checkbox" class="input-checkbox" name="remote_position" id="remote_position" value="1"' . $checked . ' />'
				. '<label for="remote_position" id="remote_position_label">' . esc_html__( 'Remote positions only', 'wp-job-manager' ) . '</label>'
				. '</div>';
		}

		// No job type list (for example on a job type archive): no drawer to build.
		if ( ! $has_list ) {
			echo $types_html . $remote; // phpcs:ignore WordPress.Security.EscapeOutput -- Plugin template output and static markup above.
			return;
		}

		$open = apply_filters( 'wpjm_cs_types_drawer_open', true ) ? ' open' : '';

		echo '<details class="wpjm-drawer"' . $open . '>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<summary>' . esc_html__( 'Job type', 'wpjm-custom-styles' ) . '</summary>';
		echo '<div class="wpjm-drawer__body">' . $types_html . $remote . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '</details>';
	},
	21
);
