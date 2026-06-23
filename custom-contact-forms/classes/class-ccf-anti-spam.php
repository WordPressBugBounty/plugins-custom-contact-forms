<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enhanced anti-spam protection for CCF.
 *
 * Provides multiple layers beyond the existing basic honeypot:
 * - Improved honeypot with dynamic field names and CSS hiding
 * - Time-based spam trap (rejects submissions faster than threshold)
 * - IP-based rate limiting via transients
 * - Disposable email domain blocking
 * - Keyword blacklist
 *
 * All checks run before field validation to fail fast on spam.
 *
 * @since 7.9.0
 */
class CCF_Anti_Spam {

	public function __construct() {}

	/**
	 * Setup hooks
	 *
	 * @since 7.9.0
	 */
	public function setup() {
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/**
	 * Get anti-spam settings
	 *
	 * @since 7.9.0
	 * @return array
	 */
	public function get_settings() {
		$defaults = array(
			'honeypot_enabled'     => true,
			'time_trap_enabled'    => true,
			'time_trap_seconds'    => 3,
			'rate_limit_enabled'   => true,
			'rate_limit_max'       => 5,
			'rate_limit_window'    => 5,
			'disposable_enabled'   => false,
			'keyword_blacklist'    => '',
		);

		$settings = get_option( 'ccf_antispam_settings', array() );

		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		return wp_parse_args( $settings, $defaults );
	}

	/**
	 * Register settings
	 *
	 * @since 7.9.0
	 */
	public function register_settings() {
		register_setting(
			'ccf-settings',
			'ccf_antispam_settings',
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
			)
		);

		add_settings_section(
			'ccf-anti-spam',
			esc_html__( 'Spam Protection', 'custom-contact-forms' ),
			array( $this, 'section_description' ),
			'custom-contact-forms'
		);

		add_settings_field( 'ccf-honeypot', esc_html__( 'Honeypot', 'custom-contact-forms' ), array( $this, 'field_honeypot' ), 'custom-contact-forms', 'ccf-anti-spam' );
		add_settings_field( 'ccf-time-trap', esc_html__( 'Time Trap', 'custom-contact-forms' ), array( $this, 'field_time_trap' ), 'custom-contact-forms', 'ccf-anti-spam' );
		add_settings_field( 'ccf-rate-limit', esc_html__( 'Rate Limiting', 'custom-contact-forms' ), array( $this, 'field_rate_limit' ), 'custom-contact-forms', 'ccf-anti-spam' );
		add_settings_field( 'ccf-disposable', esc_html__( 'Disposable Emails', 'custom-contact-forms' ), array( $this, 'field_disposable' ), 'custom-contact-forms', 'ccf-anti-spam' );
		add_settings_field( 'ccf-keyword-blacklist', esc_html__( 'Keyword Blacklist', 'custom-contact-forms' ), array( $this, 'field_keyword_blacklist' ), 'custom-contact-forms', 'ccf-anti-spam' );
	}

	/**
	 * Sanitize settings
	 *
	 * @param array $input
	 * @since 7.9.0
	 * @return array
	 */
	public function sanitize_settings( $input ) {
		if ( ! is_array( $input ) ) {
			return array();
		}

		return array(
			'honeypot_enabled'     => ! empty( $input['honeypot_enabled'] ),
			'time_trap_enabled'    => ! empty( $input['time_trap_enabled'] ),
			'time_trap_seconds'    => max( 1, min( 30, (int) ( isset( $input['time_trap_seconds'] ) ? $input['time_trap_seconds'] : 3 ) ) ),
			'rate_limit_enabled'   => ! empty( $input['rate_limit_enabled'] ),
			'rate_limit_max'       => max( 1, min( 100, (int) ( isset( $input['rate_limit_max'] ) ? $input['rate_limit_max'] : 5 ) ) ),
			'rate_limit_window'    => max( 1, min( 60, (int) ( isset( $input['rate_limit_window'] ) ? $input['rate_limit_window'] : 5 ) ) ),
			'disposable_enabled'   => ! empty( $input['disposable_enabled'] ),
			'keyword_blacklist'    => sanitize_textarea_field( isset( $input['keyword_blacklist'] ) ? $input['keyword_blacklist'] : '' ),
		);
	}

	// ─── Settings field renderers ────────────────────────────────

	public function section_description() {
		?>
		<p><?php esc_html_e( 'These protections work automatically alongside Turnstile or reCAPTCHA. No user interaction required.', 'custom-contact-forms' ); ?></p>
		<?php
	}

	public function field_honeypot() {
		$s = $this->get_settings();
		?>
		<label>
			<input type="checkbox" name="ccf_antispam_settings[honeypot_enabled]" value="1" <?php checked( $s['honeypot_enabled'] ); ?>>
			<?php esc_html_e( 'Enable enhanced honeypot (invisible to humans, traps bots)', 'custom-contact-forms' ); ?>
		</label>
		<?php
	}

	public function field_time_trap() {
		$s = $this->get_settings();
		?>
		<label>
			<input type="checkbox" name="ccf_antispam_settings[time_trap_enabled]" value="1" <?php checked( $s['time_trap_enabled'] ); ?>>
			<?php esc_html_e( 'Reject submissions faster than', 'custom-contact-forms' ); ?>
		</label>
		<input type="number" name="ccf_antispam_settings[time_trap_seconds]" value="<?php echo (int) $s['time_trap_seconds']; ?>" min="1" max="30" style="width:4em;">
		<?php esc_html_e( 'seconds (bots submit instantly, humans don\'t)', 'custom-contact-forms' ); ?>
		<?php
	}

	public function field_rate_limit() {
		$s = $this->get_settings();
		?>
		<label>
			<input type="checkbox" name="ccf_antispam_settings[rate_limit_enabled]" value="1" <?php checked( $s['rate_limit_enabled'] ); ?>>
			<?php esc_html_e( 'Limit to', 'custom-contact-forms' ); ?>
		</label>
		<input type="number" name="ccf_antispam_settings[rate_limit_max]" value="<?php echo (int) $s['rate_limit_max']; ?>" min="1" max="100" style="width:4em;">
		<?php esc_html_e( 'submissions per', 'custom-contact-forms' ); ?>
		<input type="number" name="ccf_antispam_settings[rate_limit_window]" value="<?php echo (int) $s['rate_limit_window']; ?>" min="1" max="60" style="width:4em;">
		<?php esc_html_e( 'minutes per IP address', 'custom-contact-forms' ); ?>
		<?php
	}

	public function field_disposable() {
		$s = $this->get_settings();
		?>
		<label>
			<input type="checkbox" name="ccf_antispam_settings[disposable_enabled]" value="1" <?php checked( $s['disposable_enabled'] ); ?>>
			<?php esc_html_e( 'Block submissions from disposable email services (mailinator, guerrillamail, etc.)', 'custom-contact-forms' ); ?>
		</label>
		<?php
	}

	public function field_keyword_blacklist() {
		$s = $this->get_settings();
		?>
		<textarea name="ccf_antispam_settings[keyword_blacklist]" rows="4" class="large-text" placeholder="<?php esc_attr_e( 'One word or phrase per line', 'custom-contact-forms' ); ?>"><?php echo esc_textarea( $s['keyword_blacklist'] ); ?></textarea>
		<p class="description"><?php esc_html_e( 'Submissions containing these words will be blocked. One word or phrase per line.', 'custom-contact-forms' ); ?></p>
		<?php
	}

	// ─── Form rendering ──────────────────────────────────────────

	/**
	 * Render hidden anti-spam fields in the form.
	 * Replaces the old single honeypot.
	 *
	 * @param int $form_id
	 * @since 7.9.0
	 * @return string
	 */
	public function render_fields( $form_id ) {
		$s = $this->get_settings();
		$html = '';

		// Enhanced honeypot — two fields with misleading names + CSS hiding
		if ( ! empty( $s['honeypot_enabled'] ) ) {
			$hp_name_1 = 'ccf_website_url';
			$hp_name_2 = 'ccf_phone_verify';

			$html .= '<div aria-hidden="true" style="position:absolute;left:-9999px;top:-9999px;height:0;width:0;overflow:hidden;">';
			$html .= '<label for="' . esc_attr( $hp_name_1 ) . '">' . esc_html__( 'Website', 'custom-contact-forms' ) . '</label>';
			$html .= '<input type="text" name="' . esc_attr( $hp_name_1 ) . '" id="' . esc_attr( $hp_name_1 ) . '" value="" tabindex="-1" autocomplete="off">';
			$html .= '<input type="text" name="' . esc_attr( $hp_name_2 ) . '" value="" tabindex="-1" autocomplete="off">';
			$html .= '</div>';
		}

		// Time trap — hidden timestamp
		if ( ! empty( $s['time_trap_enabled'] ) ) {
			$html .= '<input type="hidden" name="ccf_ts" value="' . esc_attr( (string) time() ) . '">';
		}

		return $html;
	}

	// ─── Validation ──────────────────────────────────────────────

	/**
	 * Run all anti-spam checks. Returns array with error key on failure, true on pass.
	 *
	 * @param int $form_id
	 * @since 7.9.0
	 * @return true|array
	 */
	public function check( $form_id ) {
		$s = $this->get_settings();

		// 1. Enhanced honeypot
		if ( ! empty( $s['honeypot_enabled'] ) ) {
			if ( ! empty( $_POST['ccf_website_url'] ) || ! empty( $_POST['ccf_phone_verify'] ) ) {
				return array( 'error' => 'spam_honeypot', 'success' => false );
			}
		}

		// Legacy honeypot (keep for backward compat)
		if ( ! empty( $_POST['my_information'] ) ) {
			return array( 'error' => 'honeypot', 'success' => false );
		}

		// 2. Time trap
		if ( ! empty( $s['time_trap_enabled'] ) && isset( $_POST['ccf_ts'] ) ) {
			$submitted_at = (int) $_POST['ccf_ts'];
			$elapsed = time() - $submitted_at;
			$min_seconds = max( 1, (int) $s['time_trap_seconds'] );

			if ( $elapsed < $min_seconds ) {
				return array( 'error' => 'spam_too_fast', 'success' => false );
			}
		}

		// 3. Rate limiting
		if ( ! empty( $s['rate_limit_enabled'] ) ) {
			$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '0.0.0.0';
			$transient_key = 'ccf_rl_' . md5( $ip );
			$window = max( 1, (int) $s['rate_limit_window'] ) * 60;
			$max = max( 1, (int) $s['rate_limit_max'] );

			$count = (int) get_transient( $transient_key );

			if ( $count >= $max ) {
				return array( 'error' => 'spam_rate_limit', 'success' => false );
			}

			set_transient( $transient_key, $count + 1, $window );
		}

		return true;
	}

	/**
	 * Check if a value contains blacklisted keywords
	 *
	 * @param string $text
	 * @since 7.9.0
	 * @return bool True if spam detected
	 */
	public function has_blacklisted_keywords( $text ) {
		$s = $this->get_settings();

		if ( empty( $s['keyword_blacklist'] ) ) {
			return false;
		}

		$keywords = array_filter( array_map( 'trim', explode( "\n", $s['keyword_blacklist'] ) ) );
		$text_lower = strtolower( $text );

		foreach ( $keywords as $keyword ) {
			if ( ! empty( $keyword ) && false !== strpos( $text_lower, strtolower( $keyword ) ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if an email domain is disposable
	 *
	 * @param string $email
	 * @since 7.9.0
	 * @return bool True if disposable
	 */
	public function is_disposable_email( $email ) {
		$s = $this->get_settings();

		if ( empty( $s['disposable_enabled'] ) || empty( $email ) ) {
			return false;
		}

		$domain = strtolower( substr( strrchr( $email, '@' ), 1 ) );

		if ( empty( $domain ) ) {
			return false;
		}

		$disposable_domains = apply_filters( 'ccf_disposable_email_domains', array(
			'mailinator.com', 'guerrillamail.com', 'guerrillamail.de', 'grr.la',
			'guerrillamailblock.com', 'tempmail.com', 'temp-mail.org', 'throwaway.email',
			'yopmail.com', 'yopmail.fr', 'sharklasers.com', 'guerrillamail.info',
			'maildrop.cc', 'dispostable.com', 'trashmail.com', 'trashmail.me',
			'trashmail.net', 'mailnesia.com', 'mailcatch.com', 'tempr.email',
			'discard.email', 'fakeinbox.com', 'mailforspam.com', 'trash-mail.com',
			'mytemp.email', 'tempail.com', 'tempmailaddress.com', 'jetable.org',
			'throwam.com', '10minutemail.com', 'minutemail.com', 'tmail.ws',
			'emailondeck.com', 'getairmail.com', 'filzmail.com', 'inboxbear.com',
			'spamgourmet.com', 'nospam.ze.tc', 'mailnull.com', 'spamcero.com',
			'trashymail.com', 'boun.cr', 'MailScrap.com', 'instant-mail.de',
			'harakirimail.com', 'bugmenot.com', 'devnullmail.com', 'mailzilla.com',
		) );

		return in_array( $domain, $disposable_domains, true );
	}

	/**
	 * Check all submission field values for keyword blacklist + disposable email
	 *
	 * @param array $submission Field slug => value pairs
	 * @param array $field_slug_to_id Field slug => array(id, type)
	 * @since 7.9.0
	 * @return true|array
	 */
	public function check_content( $submission, $field_slug_to_id ) {
		$s = $this->get_settings();

		foreach ( $submission as $slug => $value ) {
			$type = isset( $field_slug_to_id[ $slug ]['type'] ) ? $field_slug_to_id[ $slug ]['type'] : '';

			// Check disposable email domains
			if ( 'email' === $type && ! empty( $s['disposable_enabled'] ) ) {
				$email = is_array( $value ) ? ( isset( $value['email'] ) ? $value['email'] : '' ) : $value;
				if ( $this->is_disposable_email( $email ) ) {
					return array( 'error' => 'spam_disposable_email', 'success' => false );
				}
			}

			// Check keyword blacklist on text-like fields
			if ( in_array( $type, array( 'single-line-text', 'paragraph-text', 'email', 'website' ), true ) ) {
				$text = is_array( $value ) ? implode( ' ', $value ) : $value;
				if ( $this->has_blacklisted_keywords( $text ) ) {
					return array( 'error' => 'spam_blacklisted', 'success' => false );
				}
			}
		}

		return true;
	}

	/**
	 * @since 7.9.0
	 * @return CCF_Anti_Spam
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
