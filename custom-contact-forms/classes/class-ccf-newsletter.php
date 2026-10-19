<?php
/**
 * Opt-in signup for Custom Contact Forms tips and news.
 *
 * Nothing is sent unless a site owner clicks Subscribe. "No thanks" is
 * remembered on this site only and contacts no one. The signup asks once on
 * the Forms dashboard and, for anyone who has not answered there, once in the
 * form builder. A box on the Settings page lets people change their mind.
 *
 * @package Custom_Contact_Forms
 * @since   7.19.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CCF_Newsletter {

	/**
	 * Where signups are sent. Filterable for testing.
	 */
	const ENDPOINT = 'https://api.oiopublisher.com/ccf/subscribe.php';

	/**
	 * Site option: array( 'status' => 'subscribed'|'declined', 'time' => int, 'source' => string ).
	 */
	const OPTION = 'ccf_newsletter';

	/**
	 * Site option set once the builder popup has been shown.
	 */
	const POPUP_OPTION = 'ccf_newsletter_popup_seen';

	/**
	 * Privacy policy covering the signup.
	 */
	const PRIVACY_URL = 'https://customformspro.com/privacy-policy/';

	/**
	 * Singleton.
	 *
	 * @return CCF_Newsletter
	 */
	public static function factory() {
		static $instance;

		if ( ! $instance ) {
			$instance = new self();
			$instance->setup();
		}

		return $instance;
	}

	/**
	 * Register hooks.
	 */
	public function setup() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'ccf_dashboard_top', array( $this, 'render_card' ), 5 );
		add_action( 'ccf_settings_after', array( $this, 'render_settings_box' ) );
		add_action( 'admin_footer', array( $this, 'render_popup' ) );
		add_action( 'wp_ajax_ccf_newsletter_subscribe', array( $this, 'ajax_subscribe' ) );
		add_action( 'wp_ajax_ccf_newsletter_decline', array( $this, 'ajax_decline' ) );
	}

	/**
	 * This site's answer: '' (not asked yet), 'subscribed' or 'declined'.
	 *
	 * @return string
	 */
	public function status() {
		$saved = get_option( self::OPTION );

		return is_array( $saved ) && ! empty( $saved['status'] ) ? (string) $saved['status'] : '';
	}

	/**
	 * Only people who can change settings are asked.
	 *
	 * @return bool
	 */
	private function can_ask() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * The exact wording shown next to the Subscribe button. It is also sent
	 * with the signup so there is a record of what the person agreed to.
	 *
	 * @return string
	 */
	public function consent_text() {
		return __( 'We will email you occasional form tips and news about Custom Contact Forms. Clicking Subscribe sends the email address and name above, your site address and your plugin and WordPress versions to us at oiopublisher.com. Every email has an unsubscribe link.', 'custom-contact-forms' );
	}

	/**
	 * Load the script and styles where the signup can appear.
	 *
	 * @param string $hook Admin page hook.
	 */
	public function enqueue( $hook ) {
		if ( ! $this->can_ask() || 'subscribed' === $this->status() ) {
			return;
		}

		$screen  = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$builder = $screen && isset( $screen->post_type ) && 'ccf_form' === $screen->post_type && in_array( $screen->base, array( 'post', 'post-new' ), true );
		$pages   = false !== strpos( $hook, 'ccf-dashboard' ) || false !== strpos( $hook, 'ccf-settings' );

		if ( ! $builder && ! $pages ) {
			return;
		}

		wp_enqueue_style( 'ccf-newsletter', plugins_url( '/build/css/ccf-newsletter.css', dirname( __FILE__ ) ), array(), CCF_VERSION );
		wp_enqueue_script( 'ccf-newsletter', plugins_url( '/assets/js/ccf-newsletter.js', dirname( __FILE__ ) ), array(), CCF_VERSION, true );
		wp_localize_script(
			'ccf-newsletter',
			'ccfNewsletter',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'ccf_newsletter' ),
				'i18n'    => array(
					'sending' => __( 'Subscribing…', 'custom-contact-forms' ),
					'email'   => __( 'Please enter a valid email address.', 'custom-contact-forms' ),
					'failed'  => __( 'We could not reach our server just now. Please try again in a little while.', 'custom-contact-forms' ),
				),
			)
		);
	}

	/**
	 * The form itself, shared by the card, the popup and the Settings box.
	 *
	 * @param string $source Where it is shown: dashboard, builder or settings.
	 */
	private function form( $source ) {
		$user  = wp_get_current_user();
		$email = $user && $user->user_email ? $user->user_email : get_option( 'admin_email' );
		$name  = $user ? trim( (string) $user->first_name ) : '';
		$id    = 'ccf-nl-' . sanitize_key( $source );
		?>
		<form class="ccf-nl-form" data-source="<?php echo esc_attr( $source ); ?>" novalidate>
			<div class="ccf-nl-fields">
				<label for="<?php echo esc_attr( $id ); ?>-email">
					<span><?php esc_html_e( 'Email', 'custom-contact-forms' ); ?></span>
					<input type="email" id="<?php echo esc_attr( $id ); ?>-email" name="email" value="<?php echo esc_attr( $email ); ?>" required autocomplete="email">
				</label>
				<label for="<?php echo esc_attr( $id ); ?>-name">
					<span><?php esc_html_e( 'First name (optional)', 'custom-contact-forms' ); ?></span>
					<input type="text" id="<?php echo esc_attr( $id ); ?>-name" name="name" value="<?php echo esc_attr( $name ); ?>" autocomplete="given-name">
				</label>
			</div>
			<p class="ccf-nl-consent"><?php echo esc_html( $this->consent_text() ); ?> <a href="<?php echo esc_url( self::PRIVACY_URL ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Privacy policy', 'custom-contact-forms' ); ?></a></p>
			<div class="ccf-nl-actions">
				<button type="submit" class="button button-primary ccf-nl-subscribe"><?php esc_html_e( 'Subscribe', 'custom-contact-forms' ); ?></button>
				<?php if ( 'settings' !== $source ) : ?>
					<button type="button" class="button-link ccf-nl-decline"><?php esc_html_e( 'No thanks', 'custom-contact-forms' ); ?></button>
				<?php endif; ?>
				<span class="ccf-nl-message" role="status" aria-live="polite"></span>
			</div>
		</form>
		<?php
	}

	/**
	 * Card at the top of the Forms dashboard, until answered.
	 */
	public function render_card() {
		if ( ! $this->can_ask() || '' !== $this->status() ) {
			return;
		}
		?>
		<div class="ccf-nl-card" id="ccf-nl-card">
			<div class="ccf-nl-intro">
				<h2><?php esc_html_e( 'Get form tips and release news', 'custom-contact-forms' ); ?></h2>
				<p><?php esc_html_e( 'Practical ideas for getting more out of your forms, and a heads-up when new features ship. No spam, unsubscribe anytime.', 'custom-contact-forms' ); ?></p>
			</div>
			<?php $this->form( 'dashboard' ); ?>
			<div class="ccf-nl-thanks" hidden>
				<h2><?php esc_html_e( 'You are subscribed. Thank you!', 'custom-contact-forms' ); ?></h2>
				<p><?php esc_html_e( 'Look out for our next email.', 'custom-contact-forms' ); ?></p>
			</div>
		</div>
		<?php
	}

	/**
	 * One-time popup in the form builder for anyone who has not answered yet.
	 * It replaces the one-time Pro introduction that used to appear here.
	 */
	public function render_popup() {
		if ( ! $this->can_ask() || '' !== $this->status() || get_option( self::POPUP_OPTION ) ) {
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( ! $screen || ! isset( $screen->post_type ) || 'ccf_form' !== $screen->post_type || ! in_array( $screen->base, array( 'post', 'post-new' ), true ) ) {
			return;
		}

		// Shown once, whatever the person does next.
		update_option( self::POPUP_OPTION, time(), false );
		?>
		<div class="ccf-nl-overlay" id="ccf-nl-popup">
			<div class="ccf-nl-dialog" role="dialog" aria-modal="true" aria-labelledby="ccf-nl-popup-title">
				<button type="button" class="ccf-nl-x" aria-label="<?php esc_attr_e( 'Close', 'custom-contact-forms' ); ?>">&times;</button>
				<div class="ccf-nl-head">
					<span class="ccf-nl-eyebrow"><?php esc_html_e( 'Custom Contact Forms', 'custom-contact-forms' ); ?></span>
					<h2 id="ccf-nl-popup-title"><?php esc_html_e( 'Get form tips and release news', 'custom-contact-forms' ); ?></h2>
					<p><?php esc_html_e( 'Practical ideas for getting more out of your forms, and a heads-up when new features ship. No spam, unsubscribe anytime.', 'custom-contact-forms' ); ?></p>
				</div>
				<div class="ccf-nl-body">
					<?php $this->form( 'builder' ); ?>
					<div class="ccf-nl-thanks" hidden>
						<h2><?php esc_html_e( 'You are subscribed. Thank you!', 'custom-contact-forms' ); ?></h2>
						<p><?php esc_html_e( 'Look out for our next email.', 'custom-contact-forms' ); ?></p>
					</div>
					<?php if ( ! defined( 'CCFP_VERSION' ) ) : ?>
						<p class="ccf-nl-pro">
							<?php esc_html_e( 'Need payments, signed PDF agreements or multi-step forms?', 'custom-contact-forms' ); ?>
							<a href="<?php echo esc_url( ccf_pro_url( 'builder-newsletter' ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'See Custom Contact Forms Pro', 'custom-contact-forms' ); ?></a>
						</p>
					<?php endif; ?>
					<p class="ccf-nl-once"><?php esc_html_e( 'You will only see this once.', 'custom-contact-forms' ); ?></p>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Box on the Settings page.
	 */
	public function render_settings_box() {
		if ( ! $this->can_ask() ) {
			return;
		}
		?>
		<div class="ccf-nl-settings" id="ccf-newsletter">
			<h2><?php esc_html_e( 'Tips and release news', 'custom-contact-forms' ); ?></h2>
			<?php if ( 'subscribed' === $this->status() ) : ?>
				<p><?php esc_html_e( 'This site has subscribed to Custom Contact Forms tips and news. To unsubscribe, use the link at the bottom of any of our emails.', 'custom-contact-forms' ); ?></p>
			<?php else : ?>
				<p><?php esc_html_e( 'Get practical ideas for your forms and a heads-up when new features ship.', 'custom-contact-forms' ); ?></p>
				<?php $this->form( 'settings' ); ?>
				<div class="ccf-nl-thanks" hidden>
					<p><strong><?php esc_html_e( 'You are subscribed. Thank you!', 'custom-contact-forms' ); ?></strong></p>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Save this site's answer.
	 *
	 * @param string $status subscribed or declined.
	 * @param string $source Where it was answered.
	 */
	private function remember( $status, $source ) {
		update_option(
			self::OPTION,
			array(
				'status' => $status,
				'time'   => time(),
				'source' => $source,
			),
			false
		);
	}

	/**
	 * Clean the source sent from the browser.
	 *
	 * @return string
	 */
	private function posted_source() {
		$source = isset( $_POST['source'] ) ? sanitize_key( wp_unslash( $_POST['source'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked by the callers.

		return in_array( $source, array( 'dashboard', 'builder', 'settings' ), true ) ? $source : 'dashboard';
	}

	/**
	 * Subscribe: the only place anything is sent.
	 */
	public function ajax_subscribe() {
		check_ajax_referer( 'ccf_newsletter', 'nonce' );

		if ( ! $this->can_ask() ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to do that.', 'custom-contact-forms' ) ), 403 );
		}

		$email  = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$name   = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		$source = $this->posted_source();

		if ( ! is_email( $email ) ) {
			wp_send_json_error( array( 'message' => __( 'Please enter a valid email address.', 'custom-contact-forms' ) ), 400 );
		}

		$response = wp_safe_remote_post(
			apply_filters( 'ccf_newsletter_endpoint', self::ENDPOINT ),
			array(
				'timeout' => 10,
				'body'    => array(
					'email'          => $email,
					'name'           => mb_substr( $name, 0, 100 ),
					'site'           => home_url( '/' ),
					'plugin_version' => CCF_VERSION,
					'wp_version'     => get_bloginfo( 'version' ),
					'source'         => $source,
					'consent'        => '1',
					'consent_text'   => $this->consent_text(),
				),
			)
		);

		$code = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );
		$body = is_wp_error( $response ) ? array() : json_decode( (string) wp_remote_retrieve_body( $response ), true );

		if ( 200 !== $code || empty( $body['ok'] ) ) {
			wp_send_json_error( array( 'message' => __( 'We could not reach our server just now. Please try again in a little while.', 'custom-contact-forms' ) ), 502 );
		}

		$this->remember( 'subscribed', $source );

		wp_send_json_success();
	}

	/**
	 * No thanks: remembered on this site only. Nothing is sent.
	 */
	public function ajax_decline() {
		check_ajax_referer( 'ccf_newsletter', 'nonce' );

		if ( ! $this->can_ask() ) {
			wp_send_json_error( null, 403 );
		}

		if ( '' === $this->status() ) {
			$this->remember( 'declined', $this->posted_source() );
		}

		wp_send_json_success();
	}
}
