<?php
/**
 * Turns off WP Job Manager's geolocation.
 *
 * By default the plugin sends each listing's location text to Google's
 * Geocoding API when the listing is saved. This stops that. Location search
 * still works, matching the text people typed in the location field.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter( 'job_manager_geolocation_enabled', '__return_false' );
