<?php
/**
 * Replaces WP Job Manager's Google Maps geocoding.
 *
 * WP Job Manager geocodes a listing's location text when the listing is saved,
 * and stores the result as `geolocation_*` post meta (coordinates, formatted
 * address, state, country, and so on). This file swaps the provider.
 *
 * Choose a provider in wp-config.php:
 *
 *   define( 'WPJM_CS_GEOCODER', 'nominatim' ); // default: OpenStreetMap Nominatim
 *   define( 'WPJM_CS_GEOCODER', 'geoapify' );  // needs WPJM_CS_GEOAPIFY_KEY
 *   define( 'WPJM_CS_GEOCODER', 'off' );       // no geocoding at all
 *
 *   define( 'WPJM_CS_GEOAPIFY_KEY', 'your-key' );
 *   define( 'WPJM_CS_GEOCODER_EMAIL', 'you@example.com' ); // optional, Nominatim asks for a contact
 *
 * Google is always disabled, whichever provider you pick. The Google Maps API
 * key setting in Job Listings > Settings is no longer used.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Which provider is active: 'nominatim', 'geoapify' or 'off'.
 */
function wpjm_cs_geocoder() {
	$provider = defined( 'WPJM_CS_GEOCODER' ) ? WPJM_CS_GEOCODER : 'nominatim';
	$provider = apply_filters( 'wpjm_cs_geocoder', $provider );
	return in_array( $provider, [ 'nominatim', 'geoapify', 'off' ], true ) ? $provider : 'nominatim';
}

// Never call Google. Our own handlers below do the work instead.
add_filter( 'job_manager_geolocation_enabled', '__return_false' );

// Same save hooks the plugin's own geocoder uses.
add_action(
	'job_manager_update_job_data',
	function ( $job_id, $values ) {
		if ( isset( $values['job']['job_location'] ) ) {
			wpjm_cs_geocode_listing( $job_id, $values['job']['job_location'], false );
		}
	},
	20,
	2
);

add_action(
	'job_manager_job_location_edited',
	function ( $job_id, $new_location ) {
		wpjm_cs_geocode_listing( $job_id, $new_location, true );
	},
	20,
	2
);

/**
 * Geocode a listing and save the result with the plugin's own meta keys.
 */
function wpjm_cs_geocode_listing( $job_id, $location, $clear_first ) {
	if ( 'off' === wpjm_cs_geocoder() || ! class_exists( 'WP_Job_Manager_Geocode' ) ) {
		return;
	}

	$address = wpjm_cs_get_location_data( $location );

	if ( $clear_first ) {
		WP_Job_Manager_Geocode::clear_location_data( $job_id );
	}

	if ( $address ) {
		WP_Job_Manager_Geocode::save_location_data( $job_id, $address );
	}
}

/**
 * Look up an address. Returns the array shape WP Job Manager stores
 * (lat, long, formatted_address, city, state_long, ...) or false.
 * Results, including misses, are cached so repeat lookups are free.
 */
function wpjm_cs_get_location_data( $raw ) {
	$raw = trim( (string) $raw );
	if ( '' === $raw ) {
		return false;
	}

	$provider  = wpjm_cs_geocoder();
	$cache_key = 'wpjm_cs_geo_' . md5( $provider . '|' . strtolower( $raw ) );
	$cached    = get_transient( $cache_key );

	if ( false !== $cached ) {
		return 'miss' === $cached ? false : $cached;
	}

	$address = 'geoapify' === $provider ? wpjm_cs_lookup_geoapify( $raw ) : wpjm_cs_lookup_nominatim( $raw );

	if ( null === $address ) {
		// Request failed (network, quota, bad key). Do not cache, try again next save.
		return false;
	}

	set_transient( $cache_key, $address ? $address : 'miss', $address ? 7 * DAY_IN_SECONDS : DAY_IN_SECONDS );
	return $address;
}

function wpjm_cs_http_get( $url ) {
	$response = wp_safe_remote_get(
		$url,
		[
			'timeout'    => 5,
			'user-agent' => 'WPJM-Custom-Styles; ' . get_bloginfo( 'url' ),
		]
	);

	if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
		return null;
	}

	return json_decode( wp_remote_retrieve_body( $response ), true );
}

/**
 * Nominatim (OpenStreetMap). Public server policy: max 1 request per second,
 * identifying User-Agent, no bulk use. https://operations.osmfoundation.org/policies/nominatim/
 *
 * @return array|false|null Address, false for no match, null on request failure.
 */
function wpjm_cs_lookup_nominatim( $raw ) {
	// Keep to one request per second.
	$last = (float) get_transient( 'wpjm_cs_nominatim_last' );
	$wait = 1.0 - ( microtime( true ) - $last );
	if ( $wait > 0 && $wait <= 1.0 ) {
		usleep( (int) ( $wait * 1000000 ) );
	}
	set_transient( 'wpjm_cs_nominatim_last', microtime( true ), 10 );

	$args = [
		'q'              => $raw,
		'format'         => 'jsonv2',
		'addressdetails' => 1,
		'limit'          => 1,
		'accept-language' => substr( get_locale(), 0, 2 ),
	];
	if ( defined( 'WPJM_CS_GEOCODER_EMAIL' ) && WPJM_CS_GEOCODER_EMAIL ) {
		$args['email'] = WPJM_CS_GEOCODER_EMAIL;
	}

	$data = wpjm_cs_http_get( add_query_arg( array_map( 'rawurlencode', $args ), 'https://nominatim.openstreetmap.org/search' ) );

	if ( null === $data ) {
		return null;
	}

	return empty( $data[0] ) ? false : wpjm_cs_parse_nominatim( $data[0] );
}

function wpjm_cs_parse_nominatim( $r ) {
	$a = isset( $r['address'] ) ? $r['address'] : [];

	$state_short = false;
	if ( ! empty( $a['ISO3166-2-lvl4'] ) && false !== strpos( $a['ISO3166-2-lvl4'], '-' ) ) {
		$state_short = substr( $a['ISO3166-2-lvl4'], strpos( $a['ISO3166-2-lvl4'], '-' ) + 1 );
	}

	$city = false;
	foreach ( [ 'city', 'town', 'village', 'municipality', 'hamlet', 'suburb' ] as $k ) {
		if ( ! empty( $a[ $k ] ) ) {
			$city = $a[ $k ];
			break;
		}
	}

	return wpjm_cs_clean_address(
		[
			'lat'               => isset( $r['lat'] ) ? $r['lat'] : false,
			'long'              => isset( $r['lon'] ) ? $r['lon'] : false,
			'formatted_address' => isset( $r['display_name'] ) ? $r['display_name'] : false,
			'street_number'     => isset( $a['house_number'] ) ? $a['house_number'] : false,
			'street'            => isset( $a['road'] ) ? $a['road'] : false,
			'city'              => $city,
			'state_short'       => $state_short,
			'state_long'        => isset( $a['state'] ) ? $a['state'] : ( isset( $a['region'] ) ? $a['region'] : false ),
			'postcode'          => isset( $a['postcode'] ) ? $a['postcode'] : false,
			'country_short'     => isset( $a['country_code'] ) ? strtoupper( $a['country_code'] ) : false,
			'country_long'      => isset( $a['country'] ) ? $a['country'] : false,
		]
	);
}

/**
 * Geoapify (OpenStreetMap based, hosted, free tier). Needs WPJM_CS_GEOAPIFY_KEY.
 *
 * @return array|false|null Address, false for no match, null on request failure.
 */
function wpjm_cs_lookup_geoapify( $raw ) {
	if ( ! defined( 'WPJM_CS_GEOAPIFY_KEY' ) || ! WPJM_CS_GEOAPIFY_KEY ) {
		return null;
	}

	$url  = add_query_arg(
		array_map(
			'rawurlencode',
			[
				'text'   => $raw,
				'format' => 'json',
				'limit'  => 1,
				'lang'   => substr( get_locale(), 0, 2 ),
				'apiKey' => WPJM_CS_GEOAPIFY_KEY,
			]
		),
		'https://api.geoapify.com/v1/geocode/search'
	);
	$data = wpjm_cs_http_get( $url );

	if ( null === $data ) {
		return null;
	}

	return empty( $data['results'][0] ) ? false : wpjm_cs_parse_geoapify( $data['results'][0] );
}

function wpjm_cs_parse_geoapify( $r ) {
	$state_short = false;
	if ( ! empty( $r['state_code'] ) ) {
		$state_short = $r['state_code'];
	}

	return wpjm_cs_clean_address(
		[
			'lat'               => isset( $r['lat'] ) ? $r['lat'] : false,
			'long'              => isset( $r['lon'] ) ? $r['lon'] : false,
			'formatted_address' => isset( $r['formatted'] ) ? $r['formatted'] : false,
			'street_number'     => isset( $r['housenumber'] ) ? $r['housenumber'] : false,
			'street'            => isset( $r['street'] ) ? $r['street'] : false,
			'city'              => isset( $r['city'] ) ? $r['city'] : false,
			'state_short'       => $state_short,
			'state_long'        => isset( $r['state'] ) ? $r['state'] : false,
			'postcode'          => isset( $r['postcode'] ) ? $r['postcode'] : false,
			'country_short'     => isset( $r['country_code'] ) ? strtoupper( $r['country_code'] ) : false,
			'country_long'      => isset( $r['country'] ) ? $r['country'] : false,
		]
	);
}

/**
 * Sanitise, and return false if there are no coordinates.
 */
function wpjm_cs_clean_address( $address ) {
	if ( empty( $address['lat'] ) || empty( $address['long'] ) ) {
		return false;
	}

	foreach ( $address as $key => $value ) {
		$address[ $key ] = $value ? sanitize_text_field( (string) $value ) : false;
	}

	return apply_filters( 'wpjm_cs_location_data', $address );
}
