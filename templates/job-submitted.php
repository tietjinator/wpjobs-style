<?php
/**
 * Confirmation shown after a listing is submitted.
 *
 * Replaces WP Job Manager's one-line message. A listing waiting for approval
 * is told it is in review, will be live within two business days, and who to
 * contact. The address comes from the `wpjm_cs_review_contact` filter.
 *
 * @package wpjm-custom-styles
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$job = get_post( $job->ID );

/** Who to contact with questions about a submitted listing. */
$contact = apply_filters( 'wpjm_cs_review_contact', 'zuberm@wesleyan.org' );
/** Review time promised to submitters. */
$window  = apply_filters( 'wpjm_cs_review_window', 'two business days' );

do_action( 'job_manager_job_submitted_content_before', $job );

if ( 'publish' === $job->post_status ) :
	?>
	<div class="job-manager-message wpjm-submitted wpjm-submitted--live">
		<h2 class="wpjm-submitted__title"><?php esc_html_e( 'Your listing is live', 'wpjm-custom-styles' ); ?></h2>
		<p><?php esc_html_e( 'Thank you for posting. People can find it on the job board now.', 'wpjm-custom-styles' ); ?></p>
		<p class="wpjm-submitted__actions"><a class="button" href="<?php echo esc_url( get_permalink( $job->ID ) ); ?>"><?php esc_html_e( 'View your listing', 'wpjm-custom-styles' ); ?></a></p>
	</div>
	<?php
elseif ( 'pending' === $job->post_status ) :
	?>
	<div class="job-manager-message wpjm-submitted wpjm-submitted--review">
		<h2 class="wpjm-submitted__title"><?php esc_html_e( 'Thank you, your listing has been submitted', 'wpjm-custom-styles' ); ?></h2>
		<p>
			<?php
			printf(
				/* translators: %s: review time, e.g. "two business days". */
				esc_html__( 'Your listing is now in review. Once it is approved it will be visible on the job board, within %s.', 'wpjm-custom-styles' ),
				'<strong>' . esc_html( $window ) . '</strong>'
			);
			?>
		</p>
		<?php if ( $contact ) : ?>
			<p>
				<?php esc_html_e( 'Questions? Contact', 'wpjm-custom-styles' ); ?>
				<a href="mailto:<?php echo esc_attr( antispambot( $contact ) ); ?>"><?php echo esc_html( antispambot( $contact ) ); ?></a>.
			</p>
		<?php endif; ?>
		<p class="wpjm-submitted__actions"><a class="button" href="<?php echo esc_url( function_exists( 'wpjm_cs_all_listings_url' ) ? wpjm_cs_all_listings_url() : home_url( '/' ) ); ?>"><?php esc_html_e( 'Back to the job board', 'wpjm-custom-styles' ); ?></a></p>
	</div>
	<?php
else :
	// Any other status: keep the behaviour other plugins hook into.
	do_action( 'job_manager_job_submitted_content_' . str_replace( '-', '_', sanitize_title( $job->post_status ) ), $job );
endif;

do_action( 'job_manager_job_submitted_content_after', sanitize_title( $job->post_status ), $job );
