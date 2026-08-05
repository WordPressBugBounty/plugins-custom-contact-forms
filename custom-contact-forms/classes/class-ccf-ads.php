<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CCF_Ads
 *
 * Legacy promotional class. External Mailchimp subscription has been removed
 * as the original list is no longer maintained. Structure preserved for
 * backward compatibility with hooks and filters.
 *
 * @since 6.9.4
 * @since 7.9.0 — Neutralized dead Mailchimp integration, added nonce checks
 */
class CCF_Ads {

	public function __construct() {}

	/**
	 * @since 6.9.4
	 */
	public function setup() {
		// Disabled: original developer's subscribe banner is dead code
		// add_action( 'admin_notices', array( $this, 'show_ad' ) );
		// Admin-only handler: this reads $_POST for a screen that exists solely
		// in wp-admin, so there is no reason to run it on front-end requests.
		add_action( 'admin_init', array( $this, 'process_submission' ) );
		add_action( 'in_admin_footer', array( $this, 'please_rate' ) );
	}

	/**
	 * Process dismiss — no longer sends to external Mailchimp
	 *
	 * @since 7.9.0
	 */
	public function process_submission() {
		if ( apply_filters( 'ccf_hide_ads', false ) ) {
			return;
		}

		// Only process dismissal, not external subscription
		if ( ! empty( $_POST['ccf_unsubscribe'] ) ) {
			if ( ! isset( $_POST['ccf_ads_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ccf_ads_nonce'] ) ), 'ccf_ads_action' ) ) {
				return;
			}
			update_option( 'ccf_subscribed', 1 );
		}
	}

	/**
	 * Output rate request
	 *
	 * @since 6.9.4
	 */
	public function please_rate() {
		global $pagenow;

		if ( apply_filters( 'ccf_hide_please_rate', false ) ) {
			return;
		}

		if ( 'edit.php' === $pagenow || 'post-new.php' === $pagenow ) {
			if ( empty( $_GET['post_type'] ) || 'ccf_form' !== $_GET['post_type'] ) {
				return;
			}
		}

		if ( 'post.php' === $pagenow ) {
			if ( 'ccf_form' !== get_post_type() ) {
				return;
			}
		}

		if ( 'post.php' !== $pagenow && 'edit.php' !== $pagenow && 'post-new.php' !== $pagenow ) {
			return;
		}

		?>
		<p class="ccf-please-rate">
			<a href="https://wordpress.org/support/view/plugin-reviews/custom-contact-forms#postform">
				<?php
				printf(
					/* translators: %s: star dashicon */
					esc_html__( 'We need your support. Please rate Custom Contact Forms five %s\'s', 'custom-contact-forms' ),
					'<span class="dashicons dashicons-star-filled"></span>'
				);
				?>
			</a>
		</p>
		<?php
	}

	/**
	 * @since 6.9.4
	 * @return CCF_Ads
	 */
	public static function factory() {
		static $instance;

		if ( ! $instance ) {
			$instance = new self();
			$instance->setup();
		}

		return $instance;
	}
}
