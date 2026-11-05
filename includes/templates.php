<?php
/**
 * Swaps individual WP Job Manager templates for this plugin's own.
 *
 * WP Job Manager looks every template up through `job_manager_locate_template`.
 * Hooking it here replaces just the templates listed below, without editing the
 * plugin or the theme. A copy of the same template in the active theme still
 * wins, as it does for any plugin template.
 *
 *   job-application.php                    Apply button: a direct link
 *   account-signin.php                     Post a Job: no "Have an account?" prompt
 *   form-fields/term-multiselect-field.php Job category on the form: checkboxes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter(
	'job_manager_locate_template',
	function ( $template, $template_name ) {
		$ours = [
			'job-application.php'                    => 'templates/job-application.php',
			'account-signin.php'                     => 'templates/account-signin.php',
			'form-fields/term-multiselect-field.php' => 'templates/form-fields/term-multiselect-field.php',
		];

		if ( ! isset( $ours[ $template_name ] ) ) {
			return $template;
		}

		$file = dirname( __DIR__ ) . '/' . $ours[ $template_name ];
		if ( ! file_exists( $file ) ) {
			return $template;
		}

		// Keep a copy in the theme; replace only the plugin's own.
		$from_plugin = ! $template || 0 === strpos( wp_normalize_path( $template ), wp_normalize_path( JOB_MANAGER_PLUGIN_DIR ) );

		return $from_plugin ? $file : $template;
	},
	10,
	2
);
