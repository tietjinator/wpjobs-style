<?php
/**
 * The Apply button goes straight to the application, instead of revealing it.
 *
 * - Our own template (templates/job-application.php, swapped in by
 *   includes/templates.php) renders the button as a link.
 * - The subject of the email link names the job and the church or company.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Subject: "Application for Lead Pastor at Grace Community Church".
// Change it with this filter: wpjm_cs_application_subject( $subject, $post ).
add_filter(
	'job_manager_application_email_subject',
	function ( $subject, $post ) {
		$company = get_post_meta( $post->ID, '_company_name', true );
		$new     = $company
			/* translators: 1: job title, 2: church or company name. */
			? sprintf( __( 'Application for %1$s at %2$s', 'wpjm-custom-styles' ), $post->post_title, $company )
			/* translators: %s: job title. */
			: sprintf( __( 'Application for %s', 'wpjm-custom-styles' ), $post->post_title );

		return apply_filters( 'wpjm_cs_application_subject', $new, $post );
	},
	10,
	2
);
