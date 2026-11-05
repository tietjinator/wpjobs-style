<?php
/**
 * Job category field on the Post a Job form, as a checklist.
 *
 * Replaces WP Job Manager's search-as-you-type box, which is awkward for a short
 * list of categories (and shifts the page when it loses focus). The boxes are
 * named like the original field (`job_category[]`, term IDs), so the plugin
 * validates and saves them exactly as before: at least one is required, and the
 * plugin checks that on the server.
 *
 * Loaded through the `job_manager_locate_template` filter (includes/templates.php).
 *
 * @package wpjm-custom-styles
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Which terms are ticked: the saved or re-posted value, else the field's default.
if ( isset( $field['value'] ) ) {
	$selected = $field['value'];
} elseif ( ! empty( $field['default'] ) && is_int( $field['default'] ) ) {
	$selected = $field['default'];
} elseif ( ! empty( $field['default'] ) && ( $term = get_term_by( 'slug', $field['default'], $field['taxonomy'] ) ) ) { // phpcs:ignore WordPress.CodeAnalysis.AssignmentInCondition
	$selected = $term->term_id;
} else {
	$selected = '';
}
$selected = array_map( 'intval', array_filter( (array) $selected, 'is_scalar' ) );

$name  = isset( $field['name'] ) ? $field['name'] : $key;
$terms = get_terms(
	[
		'taxonomy'   => $field['taxonomy'],
		'hide_empty' => false,
		'orderby'    => 'name',
	]
);

if ( is_wp_error( $terms ) || ! $terms ) {
	return;
}

// "Other" last, not sorted into the middle.
if ( function_exists( 'wpjm_cs_other_last' ) ) {
	$terms = wpjm_cs_other_last( $terms );
}
?>
<div class="wpjm-checks" id="<?php echo esc_attr( $key ); ?>">
	<?php foreach ( $terms as $term ) : ?>
		<label class="wpjm-check">
			<input type="checkbox" name="<?php echo esc_attr( $name ); ?>[]" value="<?php echo esc_attr( $term->term_id ); ?>" <?php checked( in_array( (int) $term->term_id, $selected, true ) ); ?> />
			<?php echo esc_html( $term->name ); ?>
		</label>
	<?php endforeach; ?>
</div>
<?php if ( ! empty( $field['description'] ) ) : ?>
	<small class="description"><?php echo wp_kses_post( $field['description'] ); ?></small>
<?php endif; ?>
