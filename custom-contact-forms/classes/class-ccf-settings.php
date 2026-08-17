<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CCF_Settings {

	public function __construct() {}

	/**
	 * @since 7.2
	 */
	public function setup() {
		add_action( 'admin_menu', array( $this, 'register_menu_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'action_admin_enqueue_scripts' ) );
		add_action( 'wp_head', array( $this, 'output_custom_css' ), 999 );
	}

	/**
	 * @since 7.2
	 */
	public function action_admin_enqueue_scripts() {
		if ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) {
			$css_path = '/assets/build/css/settings.css';
			$js_path = '/assets/js/settings.js';
		} else {
			$css_path = '/assets/build/css/settings.min.css';
			$js_path = '/assets/build/js/settings.min.js';
		}
		wp_enqueue_style( 'ccf-settings', plugins_url( $css_path, dirname( __FILE__ ) ), array(), CCF_VERSION );
		wp_enqueue_script( 'ccf-settings', plugins_url( $js_path, dirname( __FILE__ ) ), array( 'jquery' ), CCF_VERSION, true );
	}

	/**
	 * Sanitize settings
	 *
	 * @since 7.2
	 * @param array $option
	 * @return array
	 */
	public function sanitize( $option ) {
		$clean_option = array();

		if ( ! is_array( $option ) ) {
			return $clean_option;
		}

		$clean_option['asset_loading_restriction_enabled'] = ( isset( $option['asset_loading_restriction_enabled'] ) && '1' === $option['asset_loading_restriction_enabled'] ) ? true : false;

		// Custom CSS: strip tags to prevent </style> breakout or script injection,
		// then run through wp_strip_all_tags. CSS itself contains no HTML, so this
		// is safe and removes any attempt to close the style tag or inject markup.
		$clean_option['custom_css'] = isset( $option['custom_css'] ) ? wp_strip_all_tags( $option['custom_css'] ) : '';

		$clean_option['asset_loading_restrictions'] = array();
		if ( ! empty( $option['asset_loading_restrictions'] ) && is_array( $option['asset_loading_restrictions'] ) ) {
			foreach ( $option['asset_loading_restrictions'] as $asset ) {
				if ( is_array( $asset ) ) {
					$clean_option['asset_loading_restrictions'][] = array(
						'type' => sanitize_text_field( isset( $asset['type'] ) ? $asset['type'] : 'url' ),
						'location' => sanitize_text_field( isset( $asset['location'] ) ? $asset['location'] : '' ),
					);
				}
			}
		}

		return $clean_option;
	}

	/**
	 * @since 7.2
	 */
	public function register_settings() {
		register_setting(
			'ccf-settings',
			'ccf_settings',
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
			)
		);

		$option = get_option( 'ccf_settings' );
		if ( ! is_array( $option ) ) {
			$option = array();
		}

		$restriction_classes = 'ccf-asset-loading-restrictions-wrap ccf-hide-field';
		if ( ! empty( $option['asset_loading_restriction_enabled'] ) ) {
			$restriction_classes = 'ccf-asset-loading-restrictions-wrap';
		}

		// First, because it is the thing most people have never heard of and
		// cannot otherwise discover.
		add_settings_section( 'ccf-ai-forms', esc_html__( 'Build forms with AI', 'custom-contact-forms' ), array( $this, 'ai_forms_summary' ), 'custom-contact-forms' );

		add_settings_section( 'asset-loading-restriction', esc_html__( 'Asset Loading Restriction', 'custom-contact-forms' ), array( $this, 'asset_loading_restriction_summary' ), 'custom-contact-forms' );
		add_settings_field( 'asset-loading-restriction-enable', esc_html__( 'Enable Asset Loading Restrictions', 'custom-contact-forms' ), array( $this, 'asset_loading_restriction_enable' ), 'custom-contact-forms', 'asset-loading-restriction' );
		add_settings_field( 'asset-loading-restriction-choose', esc_html__( 'Restrict Asset Loading To', 'custom-contact-forms' ), array( $this, 'asset_loading_restriction_choose' ), 'custom-contact-forms', 'asset-loading-restriction', array( 'class' => $restriction_classes ) );

		add_settings_section( 'ccf-custom-css', esc_html__( 'Custom CSS', 'custom-contact-forms' ), array( $this, 'custom_css_summary' ), 'custom-contact-forms' );
		add_settings_field( 'ccf-custom-css-field', esc_html__( 'Custom CSS', 'custom-contact-forms' ), array( $this, 'custom_css_field' ), 'custom-contact-forms', 'ccf-custom-css' );
	}

	/**
	 * Custom CSS section summary.
	 *
	 * @since 7.10
	 */
	public function custom_css_summary() {
		?>
		<p><?php esc_html_e( 'Add your own CSS to style your forms. This CSS is output on any page that displays a form.', 'custom-contact-forms' ); ?></p>
		<?php
	}

	/**
	 * Custom CSS textarea field.
	 *
	 * @since 7.10
	 */
	public function custom_css_field() {
		$option = get_option( 'ccf_settings' );
		$css    = ( is_array( $option ) && isset( $option['custom_css'] ) ) ? $option['custom_css'] : '';
		?>
		<textarea name="ccf_settings[custom_css]" id="ccf-custom-css" class="large-text code" rows="12" spellcheck="false" placeholder="<?php esc_attr_e( '.ccf-form { /* your styles */ }', 'custom-contact-forms' ); ?>"><?php echo esc_textarea( $css ); ?></textarea>
		<p class="description"><?php esc_html_e( 'Target form elements with the .ccf-form class. Example: .ccf-form input { border-radius: 6px; } — if your theme overrides a style, add !important (e.g. border-radius: 6px !important;).', 'custom-contact-forms' ); ?></p>
		<?php
	}

	/**
	 * Output custom CSS on the frontend.
	 *
	 * @since 7.10
	 */
	public function output_custom_css() {
		$option     = get_option( 'ccf_settings' );
		$custom_css = ( is_array( $option ) && isset( $option['custom_css'] ) ) ? $option['custom_css'] : '';

		if ( empty( trim( $custom_css ) ) ) {
			return;
		}

		echo "\n<style id=\"ccf-custom-css\">\n" . wp_strip_all_tags( $custom_css ) . "\n</style>\n";
	}

	public function asset_loading_restriction_summary() {
		?>
			<p>
				<?php esc_html_e( "By default, Custom Contact Forms loads all it's assets (JavaScript, CSS, etc.) on every page of your site. The reason for this is that there is no where to determine where you use forms. Asset Page Control allows you to specify the URLs or post IDs where your CCF forms will exist. By specifying where your forms will live, assets will not be unnecessarily loaded on every page of your site.", 'custom-contact-forms' ); ?>
			</p>
		<?php
	}

	public function asset_loading_restriction_enable() {
		$option = get_option( 'ccf_settings' );
		if ( ! is_array( $option ) ) {
			$option = array();
		}
		$enabled = ! empty( $option['asset_loading_restriction_enabled'] );

		?>
		<select class="ccf-asset-loading-restriction-enabled" name="ccf_settings[asset_loading_restriction_enabled]">
			<option value="0"><?php esc_html_e( 'No', 'custom-contact-forms' ); ?></option>
			<option <?php selected( $enabled, true ); ?> value="1"><?php esc_html_e( 'Yes', 'custom-contact-forms' ); ?></option>
		</select>
		<?php
	}

	public function asset_loading_restriction_choose() {
		$option = get_option( 'ccf_settings' );
		if ( ! is_array( $option ) ) {
			$option = array();
		}

		?>
			<div class="ccf-asset-restrictions">
				<?php if ( ! empty( $option['asset_loading_restrictions'] ) && is_array( $option['asset_loading_restrictions'] ) ) : $i = 0; foreach ( $option['asset_loading_restrictions'] as $asset ) : ?>
					<div class="asset">
						<input value="<?php echo esc_attr( isset( $asset['location'] ) ? $asset['location'] : '' ); ?>" name="ccf_settings[asset_loading_restrictions][<?php echo (int) $i; ?>][location]" class="asset-location" type="text" placeholder="<?php esc_attr_e( 'URL or post ID', 'custom-contact-forms' ); ?>">
						<?php esc_html_e( 'Restriction type:', 'custom-contact-forms' ); ?>
						<select class="restriction-type" name="ccf_settings[asset_loading_restrictions][<?php echo (int) $i; ?>][type]">
							<option value="url"><?php esc_html_e( 'URL', 'custom-contact-forms' ); ?></option>
							<option <?php selected( isset( $asset['type'] ) ? $asset['type'] : '', 'post_id' ); ?> value="post_id"><?php esc_html_e( 'Post ID', 'custom-contact-forms' ); ?></option>
						</select>

						<span class="add">+</span>
						<span class="delete">&times;</span>
					</div>
				<?php $i++;
endforeach; else : ?>
					<div class="asset">
						<input name="ccf_settings[asset_loading_restrictions][0][location]" class="asset-location" type="text" placeholder="<?php esc_attr_e( 'URL or post ID', 'custom-contact-forms' ); ?>">
						<?php esc_html_e( 'Restriction type:', 'custom-contact-forms' ); ?>
						<select class="restriction-type" name="ccf_settings[asset_loading_restrictions][0][type]">
							<option value="url"><?php esc_html_e( 'URL', 'custom-contact-forms' ); ?></option>
							<option value="post_id"><?php esc_html_e( 'Post ID', 'custom-contact-forms' ); ?></option>
						</select>

						<span class="add">+</span>
						<span class="delete">&times;</span>
					</div>
				<?php endif; ?>
			</div>
		<?php
	}

	public function register_menu_page() {
		add_submenu_page( 'edit.php?post_type=ccf_form', esc_html__( 'Custom Contact Forms Settings', 'custom-contact-forms' ), esc_html__( 'Settings', 'custom-contact-forms' ), 'manage_options', 'ccf-settings', array( $this, 'screen_options' ) );
	}

	public function screen_options() {
		?>
			<div class="wrap">
				<h1><?php esc_html_e( 'Custom Contact Forms Settings', 'custom-contact-forms' ); ?></h1>

				<form action="options.php" method="post">
					<?php settings_fields( 'ccf-settings' ); ?>
					<?php do_settings_sections( 'custom-contact-forms' ); ?>
					<?php submit_button(); ?>
				</form>
			</div>
		<?php
	}

	/**
	 * @since 7.2
	 * @return CCF_Settings
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
	 * Explain AI form building, and why it is or is not usable here.
	 *
	 * The feature hides itself when it cannot work, which is correct but
	 * leaves nobody any way to find out it exists or what is missing. This
	 * says both.
	 */
	public function ai_forms_summary() {
		$has_client = function_exists( 'wp_ai_client_prompt' );
		$available  = class_exists( 'CCF_AI_Form_Builder' ) && CCF_AI_Form_Builder::is_available();

		echo '<p>' . esc_html__( 'Describe the form you need in plain language and Custom Contact Forms will build it for you — fields, labels and all. You can change everything afterwards.', 'custom-contact-forms' ) . '</p>';

		if ( $available ) {
			echo '<div class="ccf-ai-callout">';
			echo '<p><strong>' . esc_html__( 'Ready to use.', 'custom-contact-forms' ) . '</strong> ' . esc_html__( 'Start a new form and describe what you need.', 'custom-contact-forms' ) . '</p>';
			echo '<a class="button button-primary" href="' . esc_url( admin_url( 'post-new.php?post_type=ccf_form' ) ) . '">&#10024; ' . esc_html__( 'Build a form with AI', 'custom-contact-forms' ) . '</a>';
			echo '</div>';

			return;
		}

		if ( ! $has_client ) {
			echo '<p>' . esc_html__( 'This needs WordPress 7.0 or later, which introduced a built-in way for plugins to use AI. Update WordPress to turn it on.', 'custom-contact-forms' ) . '</p>';

			return;
		}

		if ( ! current_user_can( 'prompt_ai' ) && ! current_user_can( 'manage_options' ) ) {
			echo '<p>' . esc_html__( 'Your account does not have permission to use AI features on this site. An administrator can grant it.', 'custom-contact-forms' ) . '</p>';

			return;
		}

		$check = CCF_AI_Form_Builder::diagnose();

		if ( 'ai_off' === $check['status'] ) {
			echo '<p>' . esc_html__( 'AI is switched off for this site. Turn it back on in the AI settings and this becomes available.', 'custom-contact-forms' ) . '</p>';

			return;
		}

		// Client present, permission held, but no provider answering — the
		// usual cause is a connector installed with no key saved.
		echo '<p>';
		printf(
			/* translators: %s: link to the Connectors settings screen. */
			esc_html__( 'Almost there. Connect an AI provider under %s and add its API key, then this turns on by itself. WordPress shares that one key with every plugin that needs it, so there is nothing to enter here.', 'custom-contact-forms' ),
			'<a href="' . esc_url( admin_url( 'options-connectors.php' ) ) . '"><strong>' . esc_html__( 'Settings → Connectors', 'custom-contact-forms' ) . '</strong></a>'
		);
		echo '</p>';

		// Shown only to administrators, and only while it is not working: four
		// different causes otherwise produce the same message, which makes
		// support conversations guesswork.
		if ( current_user_can( 'manage_options' ) && ! empty( $check['detail'] ) ) {
			echo '<p class="description"><em>' . esc_html( sprintf( 'Diagnostic: %s (%s)', $check['detail'], $check['status'] ) ) . '</em></p>';
		}
	}
}
