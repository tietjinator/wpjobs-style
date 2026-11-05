<?php
/**
 * Plugin Name: WPJM Custom Styles
 * Description: Church job board customisations for WP Job Manager: a sidebar filter layout with sorting and category and state filters, City / State / ZIP location fields, a two column job page, a simpler Post a Job form and the Wesleyan brand colours. Geolocation is turned off.
 * Version:     1.0.0
 * Requires Plugins: wp-job-manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Only one copy can run. If a second copy is installed under another folder name
// (for example a fresh download next to an older install), stop here instead of
// crashing the site with "cannot redeclare function", and say so in the admin.
if ( defined( 'WPJM_CS_VERSION' ) ) {
	add_action(
		'admin_notices',
		function () {
			if ( current_user_can( 'activate_plugins' ) ) {
				echo '<div class="notice notice-warning"><p><strong>WPJM Custom Styles</strong> is installed twice. Deactivate and delete one copy on the Plugins screen.</p></div>';
			}
		}
	);

	return;
}

define( 'WPJM_CS_VERSION', '1.0.0' );

require_once __DIR__ . '/includes/sidebar-filters.php';
require_once __DIR__ . '/includes/no-geolocation.php';
require_once __DIR__ . '/includes/location-fields.php';
require_once __DIR__ . '/includes/submit-form.php';
require_once __DIR__ . '/includes/job-page.php';
require_once __DIR__ . '/includes/templates.php';
require_once __DIR__ . '/includes/apply-button.php';
require_once __DIR__ . '/includes/no-preview.php';
require_once __DIR__ . '/includes/category-filter.php';
require_once __DIR__ . '/includes/compat.php';

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
