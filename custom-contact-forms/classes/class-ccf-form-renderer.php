<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CCF_Form_Renderer {

	/**
	 * Placeholder method
	 *
	 * @since 6.0
	 */
	public function __construct() {}

	/**
	 * Remember if we arent showing assets
	 *
	 * @since 7.2
	 */
	public $no_assets = false;

	/**
	 * Return allowed HTML tags for form field output.
	 * Used with wp_kses() to escape field HTML while preserving form elements.
	 *
	 * @since 7.9.0
	 * @return array
	 */
	public static function get_allowed_form_html() {
		$allowed = wp_kses_allowed_html( 'post' );
		$form_tags = array(
			'form'     => array( 'method' => true, 'action' => true, 'class' => true, 'id' => true, 'enctype' => true, 'data-form-id' => true ),
			'input'    => array( 'type' => true, 'name' => true, 'value' => true, 'id' => true, 'class' => true, 'placeholder' => true, 'required' => true, 'aria-required' => true, 'aria-hidden' => true, 'checked' => true, 'selected' => true, 'maxlength' => true, 'style' => true, 'readonly' => true, 'disabled' => true, 'min' => true, 'max' => true, 'step' => true, 'multiple' => true, 'accept' => true, 'tabindex' => true, 'autocomplete' => true ),
			'select'   => array( 'name' => true, 'id' => true, 'class' => true, 'required' => true, 'aria-required' => true, 'multiple' => true, 'style' => true ),
			'option'   => array( 'value' => true, 'selected' => true ),
			'optgroup' => array( 'label' => true ),
			'textarea' => array( 'name' => true, 'id' => true, 'class' => true, 'placeholder' => true, 'required' => true, 'aria-required' => true, 'rows' => true, 'cols' => true, 'style' => true ),
			'label'    => array( 'for' => true, 'class' => true ),
			'fieldset' => array( 'class' => true ),
			'legend'   => array( 'class' => true ),
			'button'   => array( 'type' => true, 'name' => true, 'value' => true, 'class' => true, 'id' => true ),
			'iframe'   => array( 'id' => true, 'name' => true, 'class' => true, 'style' => true, 'src' => true ),
			'img'      => array( 'src' => true, 'alt' => true, 'class' => true, 'style' => true, 'width' => true, 'height' => true ),
			'div'      => array( 'class' => true, 'id' => true, 'style' => true, 'aria-hidden' => true, 'data-form-id' => true, 'data-conditionals' => true, 'data-field-type' => true, 'data-field-slug' => true, 'data-sitekey' => true, 'data-theme' => true ),
		);
		return array_merge( $allowed, $form_tags );
	}

	/**
	 * Setup shortcode
	 *
	 * @since 6.0
	 */
	public function setup() {
		add_shortcode( 'ccf_form', array( $this, 'shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'action_wp_enqueue_scripts' ) );
	}

	/**
	 * Enqueue scripts for form
	 *
	 * @since 6.0
	 */
	public function action_wp_enqueue_scripts() {
		$option = get_option( 'ccf_settings' );

		// Conditionally load assets
		if ( ! empty( $option ) && is_array( $option ) && ! empty( $option['asset_loading_restriction_enabled'] ) ) {
			if ( empty( $option['asset_loading_restrictions'] ) || ! is_array( $option['asset_loading_restrictions'] ) ) {
				return;
			}

			$post_id = null;
			$current_path = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';

			$queried_object = get_queried_object();
			if ( ! empty( $queried_object ) && isset( $queried_object->ID ) ) {
				$post_id = $queried_object->ID;
			}

			$match = false;

			foreach ( $option['asset_loading_restrictions'] as $asset ) {
				if ( ! empty( $asset['location'] ) ) {
					if ( 'post_id' === ( isset( $asset['type'] ) ? $asset['type'] : '' ) ) {
						if ( (int) $asset['location'] === $post_id ) {
							$match = true;
							break;
						}
					} else {
						$asset_url_parts = wp_parse_url( $asset['location'] );
						if ( ! empty( $asset_url_parts['path'] ) ) {
							$asset_path = trailingslashit( $asset_url_parts['path'] );
							if ( ! preg_match( '#^/#', $asset_path ) ) {
								$asset_path = '/' . $asset_path;
							}

							if ( $asset_path === $current_path ) {
								$match = true;
								break;
							}
						}
					}
				}
			}

			if ( ! $match ) {
				$this->no_assets = true;
				return;
			}
		}

		if ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) {
			$css_form_path = '/assets/build/css/form.css';
			$js_path = '/assets/js/form.js';
		} else {
			$css_form_path = '/assets/build/css/form.min.css';
			$js_path = '/assets/build/js/form.min.js';
		}

		wp_enqueue_style( 'ccf-jquery-ui', plugins_url( '/build/css/jquery-ui-datepicker.css', dirname( __FILE__ ) ), array(), CCF_VERSION );

		// Register (do not enqueue) the Google reCAPTCHA API. It is only enqueued
		// when a form on the current page actually renders a reCAPTCHA field, so
		// no third-party request is made to Google unless reCAPTCHA is in use.
		wp_register_script( 'ccf-google-recaptcha', 'https://www.google.com/recaptcha/api.js?ver=2&onload=ccfRecaptchaOnload&render=explicit', array(), CCF_VERSION, true );

		wp_enqueue_style( 'ccf-form', plugins_url( $css_form_path, dirname( __FILE__ ) ), array(), CCF_VERSION );
		wp_enqueue_style( 'ccf-modern', plugins_url( '/build/css/ccf-modern.css', dirname( __FILE__ ) ), array( 'ccf-form' ), CCF_VERSION );

		wp_enqueue_script( 'ccf-form', plugins_url( $js_path, dirname( __FILE__ ) ), array( 'jquery-ui-datepicker', 'underscore' ), CCF_VERSION, false );

		$localized = array(
			'ajaxurl' => esc_url_raw( admin_url( 'admin-ajax.php' ) ),
			'required' => esc_html__( 'This field is required.', 'custom-contact-forms' ),
			'date_required' => esc_html__( 'Date is required.', 'custom-contact-forms' ),
			'hour_required' => esc_html__( 'Hour is required.', 'custom-contact-forms' ),
			'minute_required' => esc_html__( 'Minute is required.', 'custom-contact-forms' ),
			'am-pm_required' => esc_html__( 'AM/PM is required.', 'custom-contact-forms' ),
			'match' => esc_html__( 'Emails do not match.', 'custom-contact-forms' ),
			'email' => esc_html__( 'This is not a valid email address.', 'custom-contact-forms' ),
			'recaptcha' => esc_html__( 'Your reCAPTCHA response was incorrect.', 'custom-contact-forms' ),
			'recaptcha_theme' => apply_filters( 'ccf_recaptcha_theme', 'light' ),
			'phone' => esc_html__( 'This is not a valid phone number.', 'custom-contact-forms' ),
			'digits' => esc_html__( 'This phone number is not 10 digits', 'custom-contact-forms' ),
			'hour' => esc_html__( 'This is not a valid hour.', 'custom-contact-forms' ),
			'date' => esc_html__( 'This date is not valid.', 'custom-contact-forms' ),
			'minute' => esc_html__( 'This is not a valid minute.', 'custom-contact-forms' ),
			'fileExtension' => esc_html__( 'This is not an allowed file extension', 'custom-contact-forms' ),
			'fileSize' => esc_html__( 'This file is bigger than', 'custom-contact-forms' ),
			'unknown' => esc_html__( 'An unknown error occured.', 'custom-contact-forms' ),
			'website' => esc_html__( "This is not a valid URL. URL's must start with http(s)://", 'custom-contact-forms' ),
		);
		wp_localize_script( 'ccf-form', 'ccfSettings', apply_filters( 'ccf_localized_form_messages', $localized ) );
	}

	/**
	 * Output form shortcode
	 *
	 * @param array $atts
	 * @since 6.0
	 * @return string
	 */
	public function shortcode( $atts ) {
		$atts = shortcode_atts( array(
			'id' => null,
		), $atts );

		if ( empty( $atts['id'] ) || 'null' === $atts['id'] ) {
			return '';
		}

		if ( ! $this->no_assets ) {
			return $this->get_rendered_form( (int) $atts['id'] );
		}

		return '';
	}

	/**
	 * Return form HTML for a given form ID
	 *
	 * @param int $form_id
	 * @since 6.0
	 * @return string
	 */
	public function get_rendered_form( $form_id ) {
		$form_id = (int) $form_id;

		if ( is_customize_preview() ) {
			$form_query = new WP_Query( array(
				'post__in' => array( $form_id ),
				'post_type' => 'ccf_form',
				'ignore_sticky_posts' => true,
				'posts_per_page' => 1,
			) );
			$form = ! empty( $form_query->posts ) ? array_shift( $form_query->posts ) : null;
		} else {
			$form = get_post( $form_id );
		}
		if ( ! $form || 'ccf_form' !== $form->post_type ) {
			return '';
		}

		$fields = get_post_meta( $form_id, 'ccf_attached_fields', true );

		$pause = get_post_meta( $form_id, 'ccf_form_pause', true );

		$require_logged_in = get_post_meta( $form_id, 'ccf_form_require_logged_in', true );

		if ( empty( $fields ) || ! is_array( $fields ) ) {
			return '';
		}

		ob_start();

		if ( ! empty( $require_logged_in ) && ! is_user_logged_in() ) {
			?>
			<div class="ccf-form-require-logged-in form-id-<?php echo (int) $form_id; ?>">
				<?php esc_html_e( 'Log in to view this form.', 'custom-contact-forms' ); ?>
			</div>
			<?php
		} elseif ( ! empty( $pause ) ) {
			$pause_message = get_post_meta( $form_id, 'ccf_form_pause_message', true );
			?>

			<div class="ccf-form-pause form-id-<?php echo (int) $form_id; ?>">
				<?php if ( empty( $pause_message ) ) : ?>
					<?php esc_html_e( 'This form is paused right now. Check back later!', 'custom-contact-forms' ); ?>
				<?php else : ?>
					<?php echo esc_html( $pause_message ); ?>
				<?php endif; ?>
			</div>

			<?php
		} elseif ( ! empty( $_POST['ccf_form'] ) && ! empty( $_POST['form_id'] ) && (int) $_POST['form_id'] === $form_id && empty( CCF_Form_Handler::factory()->errors_by_form[ $form_id ] ) ) {

			$completion_message = get_post_meta( $form_id, 'ccf_form_completion_message', true );
			?>

			<div class="ccf-form-complete form-id-<?php echo (int) $form_id; ?>">
				<?php if ( empty( $completion_message ) ) : ?>
					<?php esc_html_e( 'Thank you for your submission.', 'custom-contact-forms' ); ?>
				<?php else : ?>
					<?php echo esc_html( $completion_message ); ?>
				<?php endif; ?>
			</div>

			<?php
		} else {
			$contains_file = false;

			$fields_html = '';

			$conditionals = array();

			foreach ( $fields as $field_id ) {
				$field_id = (int) $field_id;

				$type = esc_attr( get_post_meta( $field_id, 'ccf_field_type', true ) );
				$slug = get_post_meta( $field_id, 'ccf_field_slug', true );
				$conditionals_enabled = get_post_meta( $field_id, 'ccf_field_conditionalsEnabled', true );

				if ( ! empty( $conditionals_enabled ) ) {
					$field_conditionals = get_post_meta( $field_id, 'ccf_attached_conditionals', true );

					if ( ! empty( $field_conditionals ) && is_array( $field_conditionals ) ) {
						$new_conditionals = array(
							'conditions' => $field_conditionals,
							'conditionalType' => get_post_meta( $field_id, 'ccf_field_conditionalType', true ),
							'conditionalFieldsRequired' => get_post_meta( $field_id, 'ccf_field_conditionalFieldsRequired', true ),
						);

						$conditionals[$slug] = $new_conditionals;
					}
				}

				if ( 'file' === $type ) {
					$contains_file = true;
				}

				$fields_html .= apply_filters( 'ccf_field_html', CCF_Field_Renderer::factory()->render_router( $type, $field_id, $form_id ), $type, $field_id );
			}

			if ( CCF_Field_Renderer::factory()->section_open ) {
				$fields_html .= '</div>';
			}

			$theme = get_post_meta( $form_id, 'ccf_form_theme', true );
			if ( empty( $theme ) ) {
				$theme = 'default';
			}

			$hide_title = get_post_meta( $form_id, 'ccf_form_hide_title', true );

			// SECURITY FIX: sanitize REQUEST_URI for use in form
			$form_page_url = esc_url( untrailingslashit( site_url() ) . ( isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '' ) );
			?>

			<div class="ccf-form-wrapper form-id-<?php echo (int) $form_id; ?>" data-form-id="<?php echo (int) $form_id; ?>" data-conditionals="<?php echo esc_attr( wp_json_encode( $conditionals ) ); ?>">
				<form <?php if ( $contains_file ) : ?>enctype="multipart/form-data"<?php endif; ?> class="ccf-form ccf-theme-<?php echo esc_attr( $theme ); ?>" method="post" action="?v=<?php echo (int) time(); ?>" data-form-id="<?php echo (int) $form_id; ?>">

					<?php if ( empty( $hide_title ) ) : ?>
						<?php $title = get_the_title( $form_id ); if ( ! empty( $title ) && apply_filters( 'ccf_show_form_title', true, $form_id ) ) : ?>
							<div class="form-title">
								<?php echo esc_html( $title ); ?>
							</div>
						<?php endif; ?>
					<?php endif; ?>

					<?php $description = get_post_meta( $form_id, 'ccf_form_description', true ); if ( ! empty( $description ) && apply_filters( 'ccf_show_form_description', true, $form_id ) ) : ?>
						<div class="form-description">
							<?php echo esc_html( $description ); ?>
						</div>
					<?php endif; ?>

					<?php echo wp_kses( $fields_html, self::get_allowed_form_html() ); ?>

					<?php
					// Turnstile widget (before submit button)
					$turnstile_html = apply_filters( 'ccf_form_after_fields', '', $form_id );
					if ( ! empty( $turnstile_html ) ) {
						echo wp_kses_post( $turnstile_html );
					}
					?>

					<div class="form-submit <?php echo esc_attr( get_post_meta( $form_id, 'ccf_form_buttonClass', true ) ); ?>">
						<input type="submit" class="btn btn-primary ccf-submit-button" value="<?php echo esc_attr( get_post_meta( $form_id, 'ccf_form_buttonText', true ) ); ?>">
						<img class="loading-img" src="<?php echo esc_url( site_url( '/wp-admin/images/wpspin_light.gif' ) ); ?>">
					</div>

					<input type="hidden" name="form_id" value="<?php echo (int) $form_id; ?>">
					<input type="hidden" name="form_page" value="<?php echo esc_attr( $form_page_url ); ?>">
					<?php
					// Anti-spam fields (replaces old single honeypot)
					echo wp_kses( CCF_Anti_Spam::factory()->render_fields( $form_id ), self::get_allowed_form_html() );
					?>
					<input type="text" name="my_information" style="display: none;">
					<input type="hidden"  name="ccf_form" value="1">
					<input type="hidden" name="form_nonce" value="<?php echo esc_attr( wp_create_nonce( 'ccf_form' ) ); ?>">
				</form>

				<iframe class="ccf-form-frame" id="ccf_form_frame_<?php echo (int) $form_id; ?>" name="ccf_form_frame_<?php echo (int) $form_id; ?>"></iframe>
			</div>

			<?php
		}

		$form_html = ob_get_clean();

		CCF_Field_Renderer::factory()->reset();

		return $form_html;
	}

	/**
	 * Return singleton instance of class
	 *
	 * @since 6.0
	 * @return object
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

/**
 * Output a custom contact form.
 *
 * @param int $form_id
 * @since 6.3.4
 */
function ccf_output_form( $form_id ) {
	echo wp_kses( CCF_Form_Renderer::factory()->get_rendered_form( (int) $form_id ), CCF_Form_Renderer::get_allowed_form_html() );
}

/**
 * Output a custom contact form. This function is here for backwards compat.
 *
 * @param int $form_id
 * @since 1.0
 */
function serveCustomContactForm( $form_id ) {
	ccf_output_form( $form_id );
}
