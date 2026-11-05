<?php
/**
 * Splits the single Location field into City, State / Province and ZIP / Postal code.
 *
 * Everything WP Job Manager does with location (listing cards, the single job
 * page, the Location search box) reads one text value, `_job_location`. So the
 * three parts are saved on their own (`_job_city`, `_job_state`,
 * `_job_postcode`) and also combined into `_job_location`, for example
 * "Austin, TX 78701". Nothing in WP Job Manager is edited: this only uses its
 * documented filters and actions.
 *
 * Listings that predate this keep their old `_job_location` text until someone
 * fills in the new fields. Remote listings with no city, state or ZIP end up
 * with an empty location, which the plugin shows as "Remote".
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Combine the parts, for example "Austin, TX 78701". Filterable.
 */
function wpjm_cs_format_location( $city, $state, $postcode ) {
	$city     = trim( (string) $city );
	$region   = trim( trim( (string) $state ) . ' ' . trim( (string) $postcode ) );
	$location = implode( ', ', array_filter( [ $city, $region ] ) );

	return apply_filters( 'wpjm_cs_format_location', $location, $city, $state, $postcode );
}

/**
 * Save the combined location for a listing from the three parts.
 *
 * If any part is filled in, `_job_location` is rebuilt from them. If all are
 * empty and this listing was split before, the location is cleared. If all are
 * empty on a listing that was never split (an older listing), the old text is
 * left alone so editing it does not wipe its location.
 */
function wpjm_cs_save_combined_location( $job_id, $city, $state, $postcode ) {
	$city     = sanitize_text_field( $city );
	$state    = sanitize_text_field( $state );
	$postcode = sanitize_text_field( $postcode );

	if ( '' !== $city || '' !== $state || '' !== $postcode ) {
		update_post_meta( $job_id, '_job_location', wpjm_cs_format_location( $city, $state, $postcode ) );
		update_post_meta( $job_id, '_wpjm_cs_location_split', 1 );
	} elseif ( get_post_meta( $job_id, '_wpjm_cs_location_split', true ) ) {
		update_post_meta( $job_id, '_job_location', '' );
	}
}

// ---------------------------------------------------------------------------
// Front end: the "Post a Job" and edit forms.
// ---------------------------------------------------------------------------

add_filter(
	'submit_job_form_fields',
	function ( $fields ) {
		if ( ! isset( $fields['job'] ) ) {
			return $fields;
		}

		// Take the old single Location field's place in the form.
		$priority = isset( $fields['job']['job_location']['priority'] ) ? (float) $fields['job']['job_location']['priority'] : 2;
		unset( $fields['job']['job_location'] );

		$fields['job']['job_city']     = [
			'label'       => __( 'City', 'wpjm-custom-styles' ),
			'type'        => 'text',
			'required'    => false,
			'placeholder' => __( 'e.g. "Austin"', 'wpjm-custom-styles' ),
			'priority'    => $priority,
		];
		$fields['job']['job_state']    = [
			'label'       => __( 'State / Province', 'wpjm-custom-styles' ),
			'type'        => 'text',
			'required'    => false,
			'placeholder' => __( 'e.g. "TX"', 'wpjm-custom-styles' ),
			'priority'    => $priority + 0.1,
		];
		$fields['job']['job_postcode'] = [
			'label'       => __( 'ZIP / Postal code', 'wpjm-custom-styles' ),
			'description' => __( 'Leave city, state and ZIP blank if the location is not important.', 'wpjm-custom-styles' ),
			'type'        => 'text',
			'required'    => false,
			'placeholder' => __( 'e.g. "78701"', 'wpjm-custom-styles' ),
			'priority'    => $priority + 0.2,
		];

		return $fields;
	}
);

add_action(
	'job_manager_update_job_data',
	function ( $job_id, $values ) {
		if ( ! isset( $values['job'] ) ) {
			return;
		}

		wpjm_cs_save_combined_location(
			$job_id,
			isset( $values['job']['job_city'] ) ? $values['job']['job_city'] : '',
			isset( $values['job']['job_state'] ) ? $values['job']['job_state'] : '',
			isset( $values['job']['job_postcode'] ) ? $values['job']['job_postcode'] : ''
		);
	},
	10,
	2
);

// ---------------------------------------------------------------------------
// Admin: the "Job Listing Data" box on the edit screen.
// ---------------------------------------------------------------------------

add_filter(
	'job_manager_job_listing_data_fields',
	function ( $fields ) {
		$priority = isset( $fields['_job_location']['priority'] ) ? (float) $fields['_job_location']['priority'] : 2;

		$fields['_job_city']     = [
			'label'    => __( 'City', 'wpjm-custom-styles' ),
			'type'     => 'text',
			'priority' => $priority + 0.1,
		];
		$fields['_job_state']    = [
			'label'    => __( 'State / Province', 'wpjm-custom-styles' ),
			'type'     => 'text',
			'priority' => $priority + 0.2,
		];
		$fields['_job_postcode'] = [
			'label'    => __( 'ZIP / Postal code', 'wpjm-custom-styles' ),
			'type'     => 'text',
			'priority' => $priority + 0.3,
		];

		// The combined text is still what the plugin displays and searches.
		if ( isset( $fields['_job_location'] ) ) {
			$fields['_job_location']['label']       = __( 'Location (combined)', 'wpjm-custom-styles' );
			$fields['_job_location']['description'] = __( 'Filled in automatically from City, State / Province and ZIP / Postal code. Only edit this directly for listings that have none of those.', 'wpjm-custom-styles' );
		}

		return $fields;
	}
);

// After the plugin has saved the admin fields (it does so at priority 20).
add_action(
	'job_manager_save_job_listing',
	function ( $post_id ) {
		wpjm_cs_save_combined_location(
			$post_id,
			(string) get_post_meta( $post_id, '_job_city', true ),
			(string) get_post_meta( $post_id, '_job_state', true ),
			(string) get_post_meta( $post_id, '_job_postcode', true )
		);
	},
	30
);
