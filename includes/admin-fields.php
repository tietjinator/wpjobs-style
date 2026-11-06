<?php
/**
 * Fewer fields in the admin "Job Listing Data" box.
 *
 * The public form no longer asks for a company tagline, video or X / Twitter
 * name, so the box does not need them either. "Position Filled" is hidden too.
 * Hiding a field only stops it being shown and saved here: anything already
 * stored on a listing stays as it is.
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
