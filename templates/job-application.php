<?php
/**
 * Apply button on the job page.
 *
 * Replaces WP Job Manager's button, which reveals the contact details when
 * clicked, with a direct link:
 *   - a website: the button goes straight to that address (new tab)
 *   - an email: the button opens the person's mail app addressed to that
 *     address, with the job in the subject line (see includes/apply-button.php).
 *     A browser has no way to tell whether a mail app opened, so the address
 *     and subject appear under the button once it is clicked (js/apply.js),
 *     for anyone it did not work for. Without JavaScript they are always shown.
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

		<?php if ( 'email' === $apply->type ) : ?>
			<?php
			wp_enqueue_script(
				'wpjm-custom-apply',
				plugins_url( 'js/apply.js', dirname( __DIR__ ) . '/wpjm-custom-styles.php' ),
				[],
				filemtime( dirname( __DIR__ ) . '/js/apply.js' ),
				true
			);
			?>
			<noscript><style>.wpjm-apply-fallback[hidden] { display: block; }</style></noscript>
			<p class="wpjm-apply-fallback" hidden>
				<?php esc_html_e( 'Button not opening your email? Send your application to', 'wpjm-custom-styles' ); ?>
				<a href="mailto:<?php echo esc_attr( $apply->email ); ?>"><?php echo esc_html( $apply->email ); ?></a><?php if ( ! empty( $apply->subject ) ) : ?>
				<?php esc_html_e( 'with the subject', 'wpjm-custom-styles' ); ?>
				<em><?php echo esc_html( wp_specialchars_decode( $apply->subject, ENT_QUOTES ) ); ?></em><?php endif; ?>.
			</p>
		<?php endif; ?>

		<?php do_action( 'job_application_end', $apply ); ?>
	</div>
<?php endif; ?>
