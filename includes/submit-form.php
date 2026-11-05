<?php
/**
 * The "Post a Job" form: wording and which fields are shown.
 *
 * - "Company" becomes "Church / Company" on the heading, label and placeholder.
 * - The X / Twitter username, tagline, video and logo upload are removed.
 *
 * Removing a field from the form only stops it being asked for. Listings that
 * already have a tagline, logo or video keep them, and the admin edit screen
 * still shows those fields.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter(
	'submit_job_form_fields',
	function ( $fields ) {
		if ( ! isset( $fields['company'] ) ) {
			return $fields;
		}

		$removed = apply_filters( 'wpjm_cs_removed_company_fields', [ 'company_twitter', 'company_tagline', 'company_video', 'company_logo' ] );
		foreach ( $removed as $key ) {
			unset( $fields['company'][ $key ] );
		}

		return $fields;
	},
	20
);

// Reword the plugin's own strings on the public site. Admin screens and the
// translation files are not touched.
add_filter(
	'gettext',
	function ( $translation, $text, $domain ) {
		if ( 'wp-job-manager' !== $domain || is_admin() ) {
			return $translation;
		}

		$map = [
			'Company Details'                 => __( 'Church / Company Details', 'wpjm-custom-styles' ),
			'Company name'                    => __( 'Church / Company name', 'wpjm-custom-styles' ),
			'Enter the name of the company'   => __( 'Enter the name of the church or company', 'wpjm-custom-styles' ),
		];

		return isset( $map[ $text ] ) ? $map[ $text ] : $translation;
	},
	10,
	3
);
