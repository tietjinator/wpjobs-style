<?php
/**
 * Plugin Name: WPJM Custom Styles
 * Description: Loads css/wpjm-custom.css after WP Job Manager's own styles.
 * Version:     1.0.0
 * Requires Plugins: wp-job-manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'wp_enqueue_scripts',
	function () {
		$file = plugin_dir_path( __FILE__ ) . 'css/wpjm-custom.css';

		wp_enqueue_style(
			'wpjm-custom-styles',
			plugins_url( 'css/wpjm-custom.css', __FILE__ ),
			[],
			file_exists( $file ) ? filemtime( $file ) : '1.0.0'
		);
	},
	100 // After WP Job Manager (priority 10) so this prints last and wins ties.
);
