<?php
/**
 * The "Post a Job" form: wording and which fields are shown.
 *
 * - "Company" becomes "Church / Company" on the heading, label and placeholder.
 * - The X / Twitter username, tagline, video and logo upload are removed.
 * - An optional "Job description (PDF)" upload is added after the description,
 *   and shown as a download link under the description on the job page.
 * - The Remote Position checkbox is on unless it is switched off in the settings.
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

// ---------------------------------------------------------------------------
// Remote Position checkbox
// ---------------------------------------------------------------------------

// The form only offers the checkbox when "Remote Position" is switched on in
// Job Listings > Settings, and the plugin treats a setting that was never saved
// as off. Treat "never saved" as on instead. Switching it off in the settings
// still hides it.
add_filter( 'default_option_job_manager_enable_remote_position', '__return_true' );

// ---------------------------------------------------------------------------
// Job description PDF
// ---------------------------------------------------------------------------

/**
 * Largest PDF allowed, in bytes: 5 MB, or the server's upload limit if lower.
 */
function wpjm_cs_pdf_max_size() {
	return min( (int) apply_filters( 'wpjm_cs_pdf_max_size', 5 * MB_IN_BYTES ), wp_max_upload_size() );
}

// The upload field on the Post a Job and edit forms, just after Description.
add_filter(
	'submit_job_form_fields',
	function ( $fields ) {
		if ( ! isset( $fields['job'] ) ) {
			return $fields;
		}

		$after = isset( $fields['job']['job_description']['priority'] ) ? (float) $fields['job']['job_description']['priority'] : 6;

		$fields['job']['job_description_pdf'] = [
			'label'              => __( 'Job description (PDF)', 'wpjm-custom-styles' ),
			'description'        => __( 'Optional. Attach a PDF of the full job description.', 'wpjm-custom-styles' ),
			'type'               => 'file',
			'required'           => false,
			'placeholder'        => '',
			'priority'           => $after + 0.5,
			'ajax'               => true,
			'multiple'           => false,
			'max_size'           => wpjm_cs_pdf_max_size(),
			'allowed_mime_types' => [ 'pdf' => 'application/pdf' ],
		];

		return $fields;
	},
	30
);

// And in the admin "Job Listing Data" box.
add_filter(
	'job_manager_job_listing_data_fields',
	function ( $fields ) {
		$after = isset( $fields['_application']['priority'] ) ? (float) $fields['_application']['priority'] : 4;

		$fields['_job_description_pdf'] = [
			'label'              => __( 'Job description (PDF)', 'wpjm-custom-styles' ),
			'description'        => __( 'A PDF of the full job description. Shown as a download link under the description.', 'wpjm-custom-styles' ),
			'type'               => 'file',
			'priority'           => $after + 0.1,
			'multiple'           => false,
			'ajax'               => true,
			'allowed_mime_types' => [ 'pdf' => 'application/pdf' ],
		];

		return $fields;
	}
);

/**
 * The link markup for a listing, or an empty string if it has no PDF.
 * Only files in this site's uploads folder are linked.
 */
function wpjm_cs_pdf_link_html( $post_id ) {
	$url = get_post_meta( $post_id, '_job_description_pdf', true );
	if ( is_array( $url ) ) {
		$url = reset( $url );
	}

	$uploads = wp_get_upload_dir();
	if ( ! is_string( $url ) || '' === $url || 0 !== strpos( $url, $uploads['baseurl'] ) || '.pdf' !== strtolower( substr( $url, -4 ) ) ) {
		return '';
	}

	return '<p class="wpjm-pdf"><a href="' . esc_url( $url ) . '" target="_blank" rel="noopener">' . esc_html__( 'Download the full job description (PDF)', 'wpjm-custom-styles' ) . '</a></p>';
}

// Under the description text on the job page and the preview.
add_filter(
	'wpjm_the_job_description',
	function ( $description, $post ) {
		$post = get_post( $post );

		return $post ? $description . wpjm_cs_pdf_link_html( $post->ID ) : $description;
	},
	10,
	2
);
