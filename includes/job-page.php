<?php
/**
 * The single job page as two columns, like the Jobs board.
 *
 * WP Job Manager prints the page in order: details (job type, location, date,
 * company), description, then the Apply button, inside .single_job_listing.
 * The theme prints the title outside, above it. To put the title in the wide
 * column with the description, this wraps the output in the plugin's own hooks:
 *
 *   .single_job_listing
 *     h1.wpjm-job-title      the title (the theme's own title is hidden in CSS)
 *     div.wpjm-job-side      details card: the plugin's meta list and company box
 *     div.wpjm-job-main      description, PDF link and Apply button
 *
 * Only the main job page is wrapped. A [job] shortcode, the preview step and an
 * expired listing keep the plugin's default markup.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Whether the two column wrapper is open for this page.
 */
function wpjm_cs_job_layout_open( $set = null ) {
	static $open = false;
	if ( null !== $set ) {
		$open = (bool) $set;
	}

	return $open;
}

// Title, and open the details card. Runs before the plugin's meta (20) and company (30).
add_action(
	'single_job_listing_start',
	function () {
		if ( ! is_singular( 'job_listing' ) || ! in_the_loop() || ! is_main_query() ) {
			return;
		}

		wpjm_cs_job_layout_open( true );
		echo '<h1 class="wpjm-job-title">' . esc_html( get_the_title() ) . '</h1>';
		echo '<div class="wpjm-job-side">';
	},
	5
);

// Close the details card and open the main column. Everything the plugin prints
// after the start hook (description, Apply) lands in the main column.
add_action(
	'single_job_listing_start',
	function () {
		if ( wpjm_cs_job_layout_open() ) {
			echo '</div><div class="wpjm-job-main">';
		}
	},
	40
);

add_action(
	'single_job_listing_end',
	function () {
		if ( wpjm_cs_job_layout_open() ) {
			echo '</div>';
			wpjm_cs_job_layout_open( false );
		}
	},
	999
);
