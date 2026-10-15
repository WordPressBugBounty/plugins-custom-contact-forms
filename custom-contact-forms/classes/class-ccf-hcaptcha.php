<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * hCaptcha integration for CCF.
 *
 * Works like the Turnstile option: when switched on, the hCaptcha widget is
 * added to every form before the submit button and checked on the server.
 *
 * @since 7.18.0
 */
class CCF_Hcaptcha {

	public function __construct() {}

	/**
	 * Setup hooks
	 *
	 * @since 7.18.0
	 */
	public function setup() {
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_filter( 'ccf_form_after_fields', array( $this, 'render_widget' ), 10, 2 );
		add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue_script' ) );
	}

	/**
	 * Get hCaptcha settings
	 *
	 * @since 7.18.0
	 * @return array
	 */
	public function get_settings() {
		$defaults = array(
			'enabled'    => false,
			'site_key'   => '',
			'secret_key' => '',
			'theme'      => 'auto',
			'size'       => 'normal',
		);

		$settings = get_option( 'ccf_hcaptcha_settings', array() );

		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		return wp_parse_args( $settings, $defaults );
	}

	/**
	 * Check if hCaptcha is properly configured
	 *
	 * @since 7.18.0
	 * @return bool
	 */
	public function is_active() {
		$settings = $this->get_settings();
		return ! empty( $settings['enabled'] ) && ! empty( $settings['site_key'] ) && ! empty( $settings['secret_key'] );
	}

	/**
	 * Register settings section and fields
	 *
	 * @since 7.18.0
	 */
	public function register_settings() {
		// Saved from its card on Forms > Integrations (since 7.18.0).
		register_setting(
			'ccf-int-hcaptcha',
			'ccf_hcaptcha_settings',
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
			)
		);
	}

	/**
	 * Settings shown inside the card on Forms > Integrations.
	 *
	 * @since 7.18.0
	 */
	public function render_settings() {
		$this->section_description();
		?>
		<table class="form-table" role="presentation"><tbody>
			<tr><th scope="row"><?php esc_html_e( 'Turn on', 'custom-contact-forms' ); ?></th><td><?php $this->field_enabled(); ?></td></tr>
			<tr><th scope="row"><?php esc_html_e( 'Site Key', 'custom-contact-forms' ); ?></th><td><?php $this->field_site_key(); ?></td></tr>
			<tr><th scope="row"><?php esc_html_e( 'Secret Key', 'custom-contact-forms' ); ?></th><td><?php $this->field_secret_key(); ?></td></tr>
			<tr><th scope="row"><?php esc_html_e( 'Widget Theme', 'custom-contact-forms' ); ?></th><td><?php $this->field_theme(); ?></td></tr>
		</tbody></table>
		<?php
	}

	/**
	 * Sanitize hCaptcha settings
	 *
	 * @param array $input
	 * @since 7.18.0
	 * @return array
	 */
	public function sanitize_settings( $input ) {
		if ( ! is_array( $input ) ) {
			return array();
		}

		return array(
			'enabled'    => ! empty( $input['enabled'] ),
			'site_key'   => sanitize_text_field( isset( $input['site_key'] ) ? $input['site_key'] : '' ),
			'secret_key' => sanitize_text_field( isset( $input['secret_key'] ) ? $input['secret_key'] : '' ),
			'theme'      => in_array( isset( $input['theme'] ) ? $input['theme'] : '', array( 'auto', 'light', 'dark' ), true ) ? $input['theme'] : 'auto',
			'size'       => in_array( isset( $input['size'] ) ? $input['size'] : '', array( 'normal', 'compact' ), true ) ? $input['size'] : 'normal',
		);
	}

	/**
	 * Section description
	 *
	 * @since 7.18.0
	 */
	public function section_description() {
		?>
		<p>
			<?php
			printf(
				wp_kses(
					/* translators: %s: URL to the hCaptcha dashboard */
					__( 'hCaptcha asks visitors to prove they are human, and many visitors pass without a challenge. Get free keys from the <a href="%s" target="_blank" rel="noopener">hCaptcha dashboard</a>. Use either hCaptcha or Turnstile, not both.', 'custom-contact-forms' ),
					array( 'a' => array( 'href' => array(), 'target' => array(), 'rel' => array() ) )
				),
				'https://dashboard.hcaptcha.com/'
			);
			?>
		</p>
		<?php
	}

	/**
	 * Enabled field
	 *
	 * @since 7.18.0
	 */
	public function field_enabled() {
		$settings = $this->get_settings();
		?>
		<label>
			<input type="checkbox" name="ccf_hcaptcha_settings[enabled]" value="1" <?php checked( $settings['enabled'] ); ?>>
			<?php esc_html_e( 'Add hCaptcha to all forms', 'custom-contact-forms' ); ?>
		</label>
		<?php
	}

	/**
	 * Site key field
	 *
	 * @since 7.18.0
	 */
	public function field_site_key() {
		$settings = $this->get_settings();
		?>
		<input type="text" name="ccf_hcaptcha_settings[site_key]" value="<?php echo esc_attr( $settings['site_key'] ); ?>" class="regular-text" autocomplete="off">
		<?php
	}

	/**
	 * Secret key field
	 *
	 * @since 7.18.0
	 */
	public function field_secret_key() {
		$settings = $this->get_settings();
		?>
		<input type="password" name="ccf_hcaptcha_settings[secret_key]" value="<?php echo esc_attr( $settings['secret_key'] ); ?>" class="regular-text" autocomplete="off">
		<?php
	}

	/**
	 * Theme field
	 *
	 * @since 7.18.0
	 */
	public function field_theme() {
		$settings = $this->get_settings();
		?>
		<select name="ccf_hcaptcha_settings[theme]">
			<option value="auto" <?php selected( $settings['theme'], 'auto' ); ?>><?php esc_html_e( 'Default', 'custom-contact-forms' ); ?></option>
			<option value="light" <?php selected( $settings['theme'], 'light' ); ?>><?php esc_html_e( 'Light', 'custom-contact-forms' ); ?></option>
			<option value="dark" <?php selected( $settings['theme'], 'dark' ); ?>><?php esc_html_e( 'Dark', 'custom-contact-forms' ); ?></option>
		</select>
		<?php
	}

	/**
	 * Enqueue hCaptcha JS on pages with forms
	 *
	 * @since 7.18.0
	 */
	public function maybe_enqueue_script() {
		if ( $this->is_active() ) {
			wp_enqueue_script( 'ccf-hcaptcha', 'https://js.hcaptcha.com/1/api.js', array(), null, true );
			wp_enqueue_script( 'ccf-captcha', plugins_url( '/assets/js/ccf-captcha.js', dirname( __FILE__ ) ), array(), CCF_VERSION, true );
			wp_localize_script( 'ccf-captcha', 'ccfCaptcha', array( 'failed' => __( 'Please complete the security check and try again.', 'custom-contact-forms' ) ) );
		}
	}

	/**
	 * Render hCaptcha widget HTML (called via filter)
	 *
	 * @param string $html    Existing HTML
	 * @param int    $form_id Form ID
	 * @since 7.18.0
	 * @return string
	 */
	public function render_widget( $html, $form_id ) {
		if ( ! $this->is_active() ) {
			return $html;
		}

		$settings = $this->get_settings();

		$widget = '<div class="ccf-hcaptcha-wrapper" style="margin-bottom:1em;">';
		// hCaptcha has no "auto" theme; leaving the attribute out gives light.
		$theme   = 'auto' === $settings['theme'] ? '' : ' data-theme="' . esc_attr( $settings['theme'] ) . '"';
		$widget .= '<div class="h-captcha" data-sitekey="' . esc_attr( $settings['site_key'] ) . '"' . $theme . '></div>';
		$widget .= '</div>';

		return $html . $widget;
	}

	/**
	 * Verify hCaptcha response server-side
	 *
	 * @param string $token The h-captcha-response token
	 * @since 7.18.0
	 * @return bool
	 */
	public function verify( $token ) {
		if ( ! $this->is_active() ) {
			return true;
		}

		if ( empty( $token ) ) {
			return false;
		}

		$settings = $this->get_settings();

		$response = wp_remote_post( 'https://api.hcaptcha.com/siteverify', array(
			'body' => array(
				'secret'   => $settings['secret_key'],
				'sitekey'  => $settings['site_key'],
				'response' => $token,
				'remoteip' => isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '',
			),
			'timeout' => 10,
		) );

		if ( is_wp_error( $response ) ) {
			// If hCaptcha is unreachable, fail open to not block legitimate submissions
			return apply_filters( 'ccf_hcaptcha_fail_open', true );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		return ! empty( $body['success'] );
	}

	/**
	 * @since 7.18.0
	 * @return CCF_Hcaptcha
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
