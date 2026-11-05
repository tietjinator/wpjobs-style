<?php
/**
 * The Category filter in the sidebar, as a drawer with a checklist like Job type.
 *
 * WP Job Manager's category filter is a search-as-you-type dropdown, which does
 * not look or work like the rest of the sidebar. This prints a drawer of
 * checkboxes instead and hides the plugin's dropdown with CSS. The plugin's own
 * (hidden) dropdown is still what the plugin's JavaScript reads, so js/sidebar.js
 * keeps the two in step: ticking a box selects the matching option in it and
 * triggers the search.
 *
 * Nothing ticked means every category. Ticking several shows listings in any of
 * them. The two category settings in Job Listings > Settings (multiple choice and
 * "any or all") are overridden for this reason.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// The checklist only works if the plugin's own category dropdown is a multiple
// choice list, and a listing should show if it is in ANY of the ticked
// categories, not only if it is in all of them. The plugin saves both of these
// settings (as "off" and "all" unless changed), so override the saved values
// rather than supplying defaults.
add_filter( 'option_job_manager_enable_default_category_multiselect', '__return_true' );
add_filter(
	'option_job_manager_category_filter_type',
	function () {
		return 'any';
	}
);

add_action(
	'job_manager_job_filters_end',
	function ( $atts ) {
		if ( empty( $atts['show_categories'] ) || ! get_option( 'job_manager_enable_categories' ) ) {
			return;
		}

		// Same conditions as the plugin's own category filter: not on a category
		// archive, and not when the shortcode fixes the categories.
		if ( ! empty( $atts['categories'] ) || is_tax( WP_Job_Manager_Post_Types::TAX_LISTING_CATEGORY ) ) {
			return;
		}

		$terms = get_terms(
			[
				'taxonomy'   => WP_Job_Manager_Post_Types::TAX_LISTING_CATEGORY,
				'hide_empty' => true,
				'orderby'    => 'name',
			]
		);
		if ( is_wp_error( $terms ) || ! $terms ) {
			return;
		}

		echo '<details class="wpjm-drawer wpjm-drawer--categories">';
		echo '<summary>' . esc_html__( 'Category', 'wpjm-custom-styles' ) . '<span class="wpjm-drawer__hint"></span></summary>';
		echo '<div class="wpjm-drawer__body"><ul class="wpjm-checklist">';
		foreach ( $terms as $term ) {
			printf(
				'<li><label><input type="checkbox" value="%1$d" /> %2$s <span class="wpjm-checklist__count">%3$d</span></label></li>',
				(int) $term->term_id,
				esc_html( $term->name ),
				(int) $term->count
			);
		}
		echo '</ul></div></details>';
	},
	22
);
