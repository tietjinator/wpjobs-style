<?php
/**
 * Compatibility notice.
 *
 * This plugin was built against WP Job Manager 2.x. The location fields and
 * sort use the plugin's documented hooks and should survive updates. The
 * sidebar layout depends on the plugin's current markup, so after a new major
 * version it is worth a look. This shows an admin warning when WP Job Manager
 * is missing or is a newer major version than the one this was built for.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WPJM_CS_BUILT_FOR_MAJOR', 2 );

add_action(
	'admin_notices',
	function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || ! in_array( $screen->id, [ 'dashboard', 'plugins', 'edit-job_listing' ], true ) ) {
			return;
		}

		if ( ! defined( 'JOB_MANAGER_VERSION' ) ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'WPJM Custom Styles needs the WP Job Manager plugin, which is not active.', 'wpjm-custom-styles' ) . '</p></div>';
			return;
		}

		if ( (int) JOB_MANAGER_VERSION > WPJM_CS_BUILT_FOR_MAJOR ) {
			printf(
				'<div class="notice notice-warning"><p>%s</p></div>',
				esc_html(
					sprintf(
						/* translators: 1: installed version, 2: major version this plugin was built for. */
						__( 'WPJM Custom Styles was built for WP Job Manager %2$d.x and you have %1$s. Check the Jobs page sidebar and the location fields still work, and update the plugin if they do not.', 'wpjm-custom-styles' ),
						JOB_MANAGER_VERSION,
						WPJM_CS_BUILT_FOR_MAJOR
					)
				)
			);
		}
	}
);
