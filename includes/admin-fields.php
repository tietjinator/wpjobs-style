<?php
/**
 * Fewer fields in the admin "Job Listing Data" box.
 *
 * The public form no longer asks for a company tagline, video or X / Twitter
 * name, so the box does not need them either. "Position Filled" is hidden too.
 * Hiding a field only stops it being shown and saved here: anything already
 * stored on a listing stays as it is.
 *

 * The Promote column on the Job Listings screen is hidden here too.
 *
 * To change the list, use the `wpjm_cs_hidden_admin_fields` filter. The names
 * are WP Job Manager meta keys: _company_tagline, _company_video,
 * _company_twitter, _filled, _featured, _job_expires, _remote_position, and so on.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter(
	'job_manager_job_listing_data_fields',
	function ( $fields ) {
		if ( ! is_admin() ) {
			return $fields;
		}

		$hidden = apply_filters(
			'wpjm_cs_hidden_admin_fields',
			[ '_company_tagline', '_company_video', '_company_twitter', '_filled' ]
		);

		foreach ( $hidden as $key ) {
			unset( $fields[ $key ] );
		}

		return $fields;
	},
	30
);

// The "Promote" column on the Job Listings screen (WP Job Manager's paid
// promotion upsell). Return false from `wpjm_cs_hide_promote_column` to show it.
add_filter(
	'manage_edit-job_listing_columns',
	function ( $columns ) {
		if ( apply_filters( 'wpjm_cs_hide_promote_column', true ) ) {
			unset( $columns['promoted_jobs'] );
		}

		return $columns;
	},
	99
);
