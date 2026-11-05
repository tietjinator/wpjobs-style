<?php
/**
 * Post a Job without the Preview step.
 *
 * WP Job Manager's form runs Submit details, then Preview (with "Submit Listing"
 * and "Edit listing" buttons), then Done. This drops the middle screen: the
 * form's button is relabelled "Submit Listing" and pressing it publishes (or
 * queues for approval) straight away and shows the confirmation.
 *
 * It uses the plugin's own step machinery rather than editing it. A step with
 * no view makes WP Job Manager run the next step's handler at once, so the
 * Preview step's handler is wrapped to behave as if "Submit Listing" had been
 * pressed on it. Return false from the `wpjm_cs_skip_preview` filter to bring
 * the Preview screen back.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter(
	'submit_job_steps',
	function ( $steps ) {
		if ( ! apply_filters( 'wpjm_cs_skip_preview', true ) || empty( $steps['preview']['handler'] ) || ! is_array( $steps['preview']['handler'] ) ) {
			return $steps;
		}

		$form    = $steps['preview']['handler'][0];
		$handler = $steps['preview']['handler'];

		$steps['preview']['view']    = null;
		$steps['preview']['handler'] = function () use ( $form, $handler ) {
			// Only straight after the form was submitted. Reaching this step any
			// other way (a link, a refresh) shows the form again instead of
			// publishing anything.
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- The submit handler already checked the form's nonce.
			if ( empty( $_POST['submit_job'] ) ) {
				$form->set_step( 0 );
				return;
			}

			$_POST['continue'] = '1';
			if ( is_user_logged_in() ) {
				$_REQUEST['_wpjm_nonce'] = wp_create_nonce( 'preview-job-' . absint( $form->get_job_id() ) );
			}

			call_user_func( $handler );

			// Publishing was refused (for example a listing limit): show the form
			// again with the error rather than a blank page.
			if ( 'preview' === $form->get_step_key( $form->get_step() ) ) {
				$form->set_step( 0 );
			}
		};

		return $steps;
	}
);

add_filter(
	'submit_job_form_submit_button_text',
	function ( $text ) {
		return apply_filters( 'wpjm_cs_skip_preview', true ) ? __( 'Submit Listing', 'wpjm-custom-styles' ) : $text;
	}
);
