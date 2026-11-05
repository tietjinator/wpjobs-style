<?php
/**
 * Account block of the Post a Job form, without the "Have an account? Sign in"
 * prompt or the "You are signed in as" panel.
 *
 * Visitors who must sign in (accounts required, registration off) still get the
 * sign-in button, because they could not submit otherwise. Any registration
 * fields WP Job Manager adds are kept.
 *
 * @package wpjm-custom-styles
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( is_user_logged_in() ) {
	return;
}

if ( job_manager_user_requires_account() && ! job_manager_enable_registration() ) :
	?>
	<fieldset class="fieldset-login_required">
		<label><?php esc_html_e( 'Have an account?', 'wp-job-manager' ); ?></label>
		<div class="field account-sign-in">
			<a class="button" href="<?php echo esc_url( apply_filters( 'submit_job_form_login_url', wp_login_url( get_permalink() ) ) ); ?>"><?php esc_html_e( 'Sign in', 'wp-job-manager' ); ?></a>
			<?php echo wp_kses_post( apply_filters( 'submit_job_form_login_required_message', __( 'You must sign in to create a new listing.', 'wp-job-manager' ) ) ); ?>
		</div>
	</fieldset>
	<?php
endif;

$registration_fields = wpjm_get_registration_fields();
if ( ! empty( $registration_fields ) ) {
	foreach ( $registration_fields as $key => $field ) {
		?>
		<fieldset class="fieldset-<?php echo esc_attr( $key ); ?>">
			<label for="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $field['label'] ) . wp_kses_post( apply_filters( 'submit_job_form_required_label', $field['required'] ? '' : ' <small>' . __( '(optional)', 'wp-job-manager' ) . '</small>', $field ) ); ?></label>
			<div class="field <?php echo $field['required'] ? 'required-field draft-required' : ''; ?>">
				<?php get_job_manager_template( 'form-fields/' . $field['type'] . '-field.php', [ 'key' => $key, 'field' => $field ] ); ?>
			</div>
		</fieldset>
		<?php
	}
	do_action( 'job_manager_register_form' );
}
