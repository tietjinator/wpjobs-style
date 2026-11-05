<?php
/**
 * The "Post a Job" form: wording and which fields are shown.
 *
 * - "Company" becomes "Church / Company" on the heading, label and placeholder.
 * - The X / Twitter username, tagline, video and logo upload are removed.
 * - An optional "Job description (PDF)" upload is added after the description,
 *   and shown as a download link under the description on the job page.
 * - The Remote Position checkbox is on unless it is switched off in the settings,
 *   and sits just below Job type.
 * - The "Your account / signed in as ..." panel is hidden for logged-in people.
 * - Salary takes whole dollars only (no cents), and the currency is a dropdown
 *   of US and Canadian dollars, US by default.
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

// ---------------------------------------------------------------------------
// Field order and the account panel
// ---------------------------------------------------------------------------

// Remote Position directly under Job type (the plugin puts it after Location).
add_filter(
	'submit_job_form_fields',
	function ( $fields ) {
		if ( isset( $fields['job']['remote_position'] ) ) {
			$type_priority                                  = isset( $fields['job']['job_type']['priority'] ) ? (float) $fields['job']['job_type']['priority'] : 4;
			$fields['job']['remote_position']['priority'] = $type_priority + 0.5;
		}

		return $fields;
	},
	30
);

// "Your account: You are currently signed in as ... Sign out" is not needed.
// Visitors who are not signed in still see "Have an account? Sign in", which is
// also where creating an account happens.
add_filter(
	'submit_job_form_show_signin',
	function ( $show ) {
		return is_user_logged_in() ? false : $show;
	}
);

// ---------------------------------------------------------------------------
// Salary: whole dollars, and a US / Canadian dollar choice
// ---------------------------------------------------------------------------

/**
 * The currencies offered, code => label. US dollars first, as the default.
 */
function wpjm_cs_currencies() {
	return apply_filters(
		'wpjm_cs_currencies',
		[
			'USD' => __( 'USD (US dollars)', 'wpjm-custom-styles' ),
			'CAD' => __( 'CAD (Canadian dollars)', 'wpjm-custom-styles' ),
		]
	);
}

// The currency is a dropdown on the form, USD unless changed, and a clearer salary hint.
add_filter(
	'submit_job_form_fields',
	function ( $fields ) {
		if ( isset( $fields['job']['job_salary'] ) ) {
			$fields['job']['job_salary']['placeholder'] = __( 'e.g. 52,000 or 52,000 - 61,000', 'wpjm-custom-styles' );
			$fields['job']['job_salary']['description'] = __( 'Whole dollars only, with no cents. A range is fine.', 'wpjm-custom-styles' );
		}

		if ( isset( $fields['job']['job_salary_currency'] ) ) {
			$fields['job']['job_salary_currency']['type']        = 'select';
			$fields['job']['job_salary_currency']['options']     = wpjm_cs_currencies();
			$fields['job']['job_salary_currency']['default']     = 'USD';
			$fields['job']['job_salary_currency']['placeholder'] = '';
			$fields['job']['job_salary_currency']['description'] = '';
		}

		return $fields;
	},
	40
);

// The same dropdown in the admin "Job Listing Data" box.
add_filter(
	'job_manager_job_listing_data_fields',
	function ( $fields ) {
		if ( isset( $fields['_job_salary_currency'] ) ) {
			$fields['_job_salary_currency']['type']        = 'select';
			$fields['_job_salary_currency']['options']     = wpjm_cs_currencies();
			$fields['_job_salary_currency']['default']     = 'USD';
			$fields['_job_salary_currency']['placeholder'] = '';
			$fields['_job_salary_currency']['description'] = __( 'US or Canadian dollars. Defaults to US dollars.', 'wpjm-custom-styles' );
		}

		return $fields;
	},
	20
);

/**
 * Whether a salary has cents: any number with a decimal part, such as 100.02,
 * 100.5 or even 100.00. Thousands separators (52,000) are fine.
 */
function wpjm_cs_salary_has_cents( $salary ) {
	return (bool) preg_match( '/\d?\.\d/', (string) $salary );
}

add_filter(
	'submit_job_form_validate_fields',
	function ( $valid, $fields, $values ) {
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		$salary = isset( $values['job']['job_salary'] ) ? trim( (string) $values['job']['job_salary'] ) : '';
		if ( '' !== $salary && wpjm_cs_salary_has_cents( $salary ) ) {
			return new WP_Error( 'validation-error', __( 'Enter the salary in whole dollars, with no cents, such as 52,000 or 18 - 22.', 'wpjm-custom-styles' ) );
		}

		return $valid;
	},
	10,
	3
);

/**
 * "$52,000 to $61,000" becomes "52,000 - 61,000". The dollar sign goes, because
 * the currency is chosen in its own field and shown after the number.
 */
function wpjm_cs_tidy_salary( $salary ) {
	$salary = str_replace( '$', '', (string) $salary );
	$salary = preg_replace( '/\s*(?:–|—|-|\bto\b)\s*/iu', ' - ', $salary );

	return trim( preg_replace( '/\s+/', ' ', $salary ) );
}

add_action(
	'job_manager_update_job_data',
	function ( $job_id, $values ) {
		if ( isset( $values['job']['job_salary'] ) ) {
			update_post_meta( $job_id, '_job_salary', wpjm_cs_tidy_salary( $values['job']['job_salary'] ) );
		}
	},
	20,
	2
);
