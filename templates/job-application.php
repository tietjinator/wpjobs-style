<?php
/**
 * Apply button on the job page.
 *
 * Replaces WP Job Manager's button, which reveals the contact details when
 * clicked, with a direct link:
 *   - a website: the button goes straight to that address (new tab)
 *   - an email: the button opens the person's mail app addressed to that
 *     address, with the job in the subject line (see includes/apply-button.php)
 *
 * Loaded through the `job_manager_locate_template` filter, so nothing in the
 * theme or in WP Job Manager is edited. The plugin's own hooks are kept so
 * add-ons that add to the apply area keep working.
 *
 * @package wpjm-custom-styles
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

$apply = get_the_job_application_method();

if ( $apply ) :
	if ( 'email' === $apply->type ) {
		// $apply->email is already obfuscated against spam harvesters by the plugin.
		$href   = 'mailto:' . $apply->email . '?subject=' . rawurlencode( wp_specialchars_decode( $apply->subject, ENT_QUOTES ) );
		$target = '';
	} else {
		$href   = $apply->url;
		$target = ' target="_blank" rel="noopener nofollow"';
	}
	?>
	<div class="job_application application">
		<?php do_action( 'job_application_start', $apply ); ?>

		<a class="wpjm-apply" href="<?php echo 'email' === $apply->type ? esc_attr( $href ) : esc_url( $href ); ?>"<?php echo $target; // phpcs:ignore WordPress.Security.EscapeOutput -- Fixed strings above. ?>><?php esc_html_e( 'Apply for job', 'wp-job-manager' ); ?></a>

		<?php do_action( 'job_application_end', $apply ); ?>
	</div>
<?php endif; ?>
