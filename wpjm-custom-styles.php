<?php
/**
 * Plugin Name: WPJM Custom Styles
 * Description: Custom styles and a sidebar filter layout with sorting for WP Job Manager, with geolocation turned off.
 * Version:     1.0.0
 * Requires Plugins: wp-job-manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/includes/sidebar-filters.php';
require_once __DIR__ . '/includes/no-geolocation.php';

add_action(
	'wp_enqueue_scripts',
	function () {
		foreach ( [ 'wpjm-custom', 'wpjm-sidebar' ] as $name ) {
			$file = plugin_dir_path( __FILE__ ) . "css/{$name}.css";

			wp_enqueue_style(
				"{$name}-styles",
				plugins_url( "css/{$name}.css", __FILE__ ),
				[],
				file_exists( $file ) ? filemtime( $file ) : '1.0.0'
			);
		}
	},
	100 // After WP Job Manager (priority 10) so this prints last and wins ties.
);
