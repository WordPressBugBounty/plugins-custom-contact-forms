<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Cloudflare Turnstile integration for CCF.
 *
 * Adds Turnstile as a form-level option (enable per form or globally).
 * Renders the widget before the submit button and validates server-side.
 *
 * @since 7.9.0
 */
class CCF_Turnstile {

	public function __construct() {}

	/**
	 * Setup hooks
	 *
	 * @since 7.9.0
	 */
	public function setup() {
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_filter( 'ccf_form_after_fields', array( $this, 'render_widget' ), 10, 2 );
		add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue_script' ) );
	}

	/**
	 * Get Turnstile settings
	 *
	 * @since 7.9.0
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

		$settings = get_option( 'ccf_turnstile_settings', array() );

		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		return wp_parse_args( $settings, $defaults );
	}

	/**
	 * Check if Turnstile is properly configured
	 *
	 * @since 7.9.0
	 * @return bool
	 */
	public function is_active() {
		$settings = $this->get_settings();
		return ! empty( $settings['enabled'] ) && ! empty( $settings['site_key'] ) && ! empty( $settings['secret_key'] );
	}

	/**
	 * Register settings section and fields
	 *
	 * @since 7.9.0
	 */
	public function register_settings() {
		register_setting(
			'ccf-settings',
			'ccf_turnstile_settings',
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
			)
		);

		add_settings_section(
			'ccf-turnstile',
			esc_html__( 'Cloudflare Turnstile', 'custom-contact-forms' ),
			array( $this, 'section_description' ),
			'custom-contact-forms'
		);

		add_settings_field(
			'ccf-turnstile-enabled',
			esc_html__( 'Enable Turnstile', 'custom-contact-forms' ),
			array( $this, 'field_enabled' ),
			'custom-contact-forms',
			'ccf-turnstile'
		);

		add_settings_field(
			'ccf-turnstile-site-key',
			esc_html__( 'Site Key', 'custom-contact-forms' ),
			array( $this, 'field_site_key' ),
			'custom-contact-forms',
			'ccf-turnstile'
		);

		add_settings_field(
			'ccf-turnstile-secret-key',
			esc_html__( 'Secret Key', 'custom-contact-forms' ),
			array( $this, 'field_secret_key' ),
			'custom-contact-forms',
			'ccf-turnstile'
		);

		add_settings_field(
			'ccf-turnstile-theme',
			esc_html__( 'Widget Theme', 'custom-contact-forms' ),
			array( $this, 'field_theme' ),
			'custom-contact-forms',
			'ccf-turnstile'
		);
	}

	/**
	 * Sanitize Turnstile settings
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
	 * @since 7.9.0
	 */
	public function section_description() {
		?>
		<p>
			<?php
			printf(
				wp_kses(
					/* translators: %s: URL to Cloudflare Turnstile dashboard */
					__( 'Cloudflare Turnstile provides invisible, privacy-friendly spam protection. Get your free keys at <a href="%s" target="_blank" rel="noopener">Cloudflare Dashboard</a>.', 'custom-contact-forms' ),
					array( 'a' => array( 'href' => array(), 'target' => array(), 'rel' => array() ) )
				),
				'https://dash.cloudflare.com/?to=/:account/turnstile'
			);
			?>
		</p>
		<?php
	}

	/**
	 * Enabled field
	 *
	 * @since 7.9.0
	 */
	public function field_enabled() {
		$settings = $this->get_settings();
		?>
		<label>
			<input type="checkbox" name="ccf_turnstile_settings[enabled]" value="1" <?php checked( $settings['enabled'] ); ?>>
			<?php esc_html_e( 'Add Turnstile challenge to all forms', 'custom-contact-forms' ); ?>
		</label>
		<?php
	}

	/**
	 * Site key field
	 *
	 * @since 7.9.0
	 */
	public function field_site_key() {
		$settings = $this->get_settings();
		?>
		<input type="text" name="ccf_turnstile_settings[site_key]" value="<?php echo esc_attr( $settings['site_key'] ); ?>" class="regular-text" autocomplete="off">
		<?php
	}

	/**
	 * Secret key field
	 *
	 * @since 7.9.0
	 */
	public function field_secret_key() {
		$settings = $this->get_settings();
		?>
		<input type="password" name="ccf_turnstile_settings[secret_key]" value="<?php echo esc_attr( $settings['secret_key'] ); ?>" class="regular-text" autocomplete="off">
		<?php
	}

	/**
	 * Theme field
	 *
	 * @since 7.9.0
	 */
	public function field_theme() {
		$settings = $this->get_settings();
		?>
		<select name="ccf_turnstile_settings[theme]">
			<option value="auto" <?php selected( $settings['theme'], 'auto' ); ?>><?php esc_html_e( 'Auto', 'custom-contact-forms' ); ?></option>
			<option value="light" <?php selected( $settings['theme'], 'light' ); ?>><?php esc_html_e( 'Light', 'custom-contact-forms' ); ?></option>
			<option value="dark" <?php selected( $settings['theme'], 'dark' ); ?>><?php esc_html_e( 'Dark', 'custom-contact-forms' ); ?></option>
		</select>
		<?php
	}

	/**
	 * Enqueue Turnstile JS on pages with forms
	 *
	 * @since 7.9.0
	 */
	public function maybe_enqueue_script() {
		if ( $this->is_active() ) {
			wp_enqueue_script( 'ccf-turnstile', 'https://challenges.cloudflare.com/turnstile/v0/api.js', array(), null, true );
		}
	}

	/**
	 * Render Turnstile widget HTML (called via filter)
	 *
	 * @param string $html    Existing HTML
	 * @param int    $form_id Form ID
	 * @since 7.9.0
	 * @return string
	 */
	public function render_widget( $html, $form_id ) {
		if ( ! $this->is_active() ) {
			return $html;
		}

		$settings = $this->get_settings();

		$widget = '<div class="ccf-turnstile-wrapper" style="margin-bottom:1em;">';
		$widget .= '<div class="cf-turnstile" data-sitekey="' . esc_attr( $settings['site_key'] ) . '" data-theme="' . esc_attr( $settings['theme'] ) . '"></div>';
		$widget .= '</div>';

		return $html . $widget;
	}

	/**
	 * Verify Turnstile response server-side
	 *
	 * @param string $token The cf-turnstile-response token
	 * @since 7.9.0
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

		$response = wp_remote_post( 'https://challenges.cloudflare.com/turnstile/v0/siteverify', array(
			'body' => array(
				'secret'   => $settings['secret_key'],
				'response' => $token,
				'remoteip' => isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '',
			),
			'timeout' => 10,
		) );

		if ( is_wp_error( $response ) ) {
			// If Cloudflare is unreachable, fail open to not block legitimate submissions
			return apply_filters( 'ccf_turnstile_fail_open', true );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		return ! empty( $body['success'] );
	}

	/**
	 * @since 7.9.0
	 * @return CCF_Turnstile
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
