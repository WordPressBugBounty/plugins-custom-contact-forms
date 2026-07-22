<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CCF_Form_Templates {

	/**
	 * Setup hooks.
	 *
	 * @since 7.10.0
	 */
	public function setup() {
		add_action( 'admin_menu', array( $this, 'register_menu_page' ) );
		add_action( 'admin_init', array( $this, 'handle_template_creation' ) );
	}

	/**
	 * Register the Templates submenu page.
	 *
	 * @since 7.10.0
	 */
	public function register_menu_page() {
		add_submenu_page(
			'edit.php?post_type=ccf_form',
			esc_html__( 'Form Templates', 'custom-contact-forms' ),
			esc_html__( 'Templates', 'custom-contact-forms' ),
			'manage_options',
			'ccf-templates',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Handle template creation when a template is selected.
	 *
	 * @since 7.10.0
	 */
	public function handle_template_creation() {
		if ( empty( $_GET['ccf_create_template'] ) || empty( $_GET['_wpnonce'] ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'ccf_create_template' ) ) {
			return;
		}

		$template_key = sanitize_text_field( wp_unslash( $_GET['ccf_create_template'] ) );
		$templates = $this->get_templates();

		if ( ! isset( $templates[ $template_key ] ) ) {
			return;
		}

		$template = $templates[ $template_key ];
		$form_id = $this->create_form_from_template( $template );

		if ( $form_id ) {
			wp_safe_redirect( admin_url( 'post.php?post=' . $form_id . '&action=edit' ) );
			exit;
		}
	}

	/**
	 * Create a form with fields from a template definition.
	 *
	 * @since 7.10.0
	 * @param array $template Template definition.
	 * @return int|false Form post ID or false on failure.
	 */
	private function create_form_from_template( $template ) {
		$form_id = wp_insert_post( array(
			'post_type'   => 'ccf_form',
			'post_status' => 'publish',
			'post_title'  => $template['title'],
		) );

		if ( is_wp_error( $form_id ) ) {
			return false;
		}

		// Save form meta
		if ( ! empty( $template['description'] ) ) {
			update_post_meta( $form_id, 'ccf_form_description', sanitize_text_field( $template['description'] ) );
		} else {
			update_post_meta( $form_id, 'ccf_form_description', '' );
		}
		update_post_meta( $form_id, 'ccf_form_buttonText', esc_attr( $template['button_text'] ?? 'Submit Form' ) );
		update_post_meta( $form_id, 'ccf_form_buttonClass', '' );
		update_post_meta( $form_id, 'ccf_form_theme', '' );
		update_post_meta( $form_id, 'ccf_form_completion_action_type', 'text' );
		update_post_meta( $form_id, 'ccf_form_completion_message', '' );
		update_post_meta( $form_id, 'ccf_form_completion_redirect_url', '' );
		update_post_meta( $form_id, 'ccf_form_send_email_notifications', true );
		update_post_meta( $form_id, 'ccf_form_pause', false );
		update_post_meta( $form_id, 'ccf_form_hide_title', false );
		update_post_meta( $form_id, 'ccf_form_require_logged_in', false );
		update_post_meta( $form_id, 'ccf_form_post_creation', false );
		update_post_meta( $form_id, 'ccf_form_notifications', array() );
		update_post_meta( $form_id, 'ccf_form_post_field_mappings', array() );

		// Create fields as child posts
		$order = 0;
		$field_ids = array();
		foreach ( $template['fields'] as $field_def ) {
			$field_id = wp_insert_post( array(
				'post_type'   => 'ccf_field',
				'post_status' => 'publish',
				'post_parent' => $form_id,
				'post_title'  => $field_def['slug'],
				'menu_order'  => $order,
			) );

			if ( is_wp_error( $field_id ) ) {
				continue;
			}

			$field_ids[] = $field_id;

			// Save field meta
			update_post_meta( $field_id, 'ccf_field_type', sanitize_text_field( $field_def['type'] ) );
			update_post_meta( $field_id, 'ccf_field_slug', sanitize_text_field( $field_def['slug'] ) );
			update_post_meta( $field_id, 'ccf_field_label', sanitize_text_field( $field_def['label'] ) );
			update_post_meta( $field_id, 'ccf_field_required', ! empty( $field_def['required'] ) );
			update_post_meta( $field_id, 'ccf_field_value', '' );
			update_post_meta( $field_id, 'ccf_field_className', '' );
			update_post_meta( $field_id, 'ccf_field_description', isset( $field_def['description'] ) ? sanitize_textarea_field( $field_def['description'] ) : '' );

			// Arbitrary additional attributes (add-on field settings such as
			// pricing modes, terms text or survey rows). Multiline strings are
			// preserved; keys are restricted to safe meta-name characters.
			if ( ! empty( $field_def['attributes'] ) && is_array( $field_def['attributes'] ) ) {
				foreach ( $field_def['attributes'] as $attr_key => $attr_value ) {
					$attr_key = preg_replace( '/[^a-zA-Z0-9_]/', '', (string) $attr_key );
					if ( '' === $attr_key ) {
						continue;
					}
					$attr_value = is_array( $attr_value ) ? map_deep( $attr_value, 'sanitize_text_field' ) : sanitize_textarea_field( (string) $attr_value );
					update_post_meta( $field_id, 'ccf_field_' . $attr_key, $attr_value );
				}
			}

			// Conditional logic rules.
			if ( ! empty( $field_def['conditionals'] ) && is_array( $field_def['conditionals'] ) ) {
				$clean_conditions = array();
				foreach ( $field_def['conditionals'] as $condition ) {
					if ( empty( $condition['field'] ) ) {
						continue;
					}
					$clean_conditions[] = array(
						'field'   => sanitize_text_field( $condition['field'] ),
						'compare' => sanitize_text_field( $condition['compare'] ?? 'is' ),
						'value'   => sanitize_text_field( $condition['value'] ?? '' ),
					);
				}
				if ( $clean_conditions ) {
					update_post_meta( $field_id, 'ccf_attached_conditionals', $clean_conditions );
					update_post_meta( $field_id, 'ccf_field_conditionalsEnabled', true );
					update_post_meta( $field_id, 'ccf_field_conditionalType', sanitize_text_field( $field_def['conditional_type'] ?? 'show' ) );
					update_post_meta( $field_id, 'ccf_field_conditionalFieldsRequired', sanitize_text_field( $field_def['conditional_fields_required'] ?? 'all' ) );
				}
			}

			if ( ! empty( $field_def['placeholder'] ) ) {
				update_post_meta( $field_id, 'ccf_field_placeholder', sanitize_text_field( $field_def['placeholder'] ) );
			} else {
				update_post_meta( $field_id, 'ccf_field_placeholder', '' );
			}

			if ( ! empty( $field_def['description'] ) ) {
				update_post_meta( $field_id, 'ccf_field_description', sanitize_text_field( $field_def['description'] ) );
			}

			if ( ! empty( $field_def['width'] ) ) {
				update_post_meta( $field_id, 'ccf_field_fieldWidth', sanitize_text_field( $field_def['width'] ) );
			} else {
				update_post_meta( $field_id, 'ccf_field_fieldWidth', '' );
			}

			// Handle choices for dropdown, radio, and checkboxes
			if ( ! empty( $field_def['choices'] ) ) {
				$choice_order = 0;
				$choice_ids = array();
				foreach ( $field_def['choices'] as $choice_def ) {
					$choice_id = wp_insert_post( array(
						'post_type'   => 'ccf_choice',
						'post_status' => 'publish',
						'post_parent' => $field_id,
						'post_title'  => $choice_def['label'],
						'menu_order'  => $choice_order,
					) );

					if ( ! is_wp_error( $choice_id ) ) {
						update_post_meta( $choice_id, 'ccf_choice_label', sanitize_text_field( $choice_def['label'] ) );
						update_post_meta( $choice_id, 'ccf_choice_value', sanitize_text_field( $choice_def['value'] ?? $choice_def['label'] ) );
						update_post_meta( $choice_id, 'ccf_choice_selected', ! empty( $choice_def['selected'] ) );
						$choice_ids[] = $choice_id;
					}

					$choice_order++;
				}

				// Save attached choices array on the field
				update_post_meta( $field_id, 'ccf_attached_choices', $choice_ids );
			}

			$order++;
		}

		// Save attached fields array on the form
		update_post_meta( $form_id, 'ccf_attached_fields', $field_ids );

		return $form_id;
	}

	/**
	 * Get all available templates.
	 *
	 * @since 7.10.0
	 * @return array
	 */
	public function get_templates() {
		$templates = array(

			'contact' => array(
				'title'       => __( 'Contact Form', 'custom-contact-forms' ),
				'description' => __( 'Get in touch with us.', 'custom-contact-forms' ),
				'icon'        => 'email',
				'button_text' => __( 'Send Message', 'custom-contact-forms' ),
				'fields'      => array(
					array(
						'type'     => 'name',
						'slug'     => 'name-1',
						'label'    => __( 'Name', 'custom-contact-forms' ),
						'required' => true,
					),
					array(
						'type'        => 'email',
						'slug'        => 'email-2',
						'label'       => __( 'Email', 'custom-contact-forms' ),
						'required'    => true,
						'placeholder' => 'you@example.com',
					),
					array(
						'type'  => 'dropdown',
						'slug'  => 'subject-3',
						'label' => __( 'Subject', 'custom-contact-forms' ),
						'choices' => array(
							array( 'label' => __( 'General Inquiry', 'custom-contact-forms' ) ),
							array( 'label' => __( 'Support', 'custom-contact-forms' ) ),
							array( 'label' => __( 'Feedback', 'custom-contact-forms' ) ),
							array( 'label' => __( 'Other', 'custom-contact-forms' ) ),
						),
					),
					array(
						'type'        => 'paragraph-text',
						'slug'        => 'message-4',
						'label'       => __( 'Message', 'custom-contact-forms' ),
						'required'    => true,
						'placeholder' => __( 'How can we help you?', 'custom-contact-forms' ),
					),
				),
			),

			'quote' => array(
				'title'       => __( 'Quote Request', 'custom-contact-forms' ),
				'description' => __( 'Request a free quote.', 'custom-contact-forms' ),
				'icon'        => 'media-document',
				'button_text' => __( 'Request Quote', 'custom-contact-forms' ),
				'fields'      => array(
					array(
						'type'     => 'name',
						'slug'     => 'name-1',
						'label'    => __( 'Name', 'custom-contact-forms' ),
						'required' => true,
						'width'    => 'half',
					),
					array(
						'type'     => 'email',
						'slug'     => 'email-2',
						'label'    => __( 'Email', 'custom-contact-forms' ),
						'required' => true,
						'width'    => 'half',
					),
					array(
						'type'     => 'phone',
						'slug'     => 'phone-3',
						'label'    => __( 'Phone Number', 'custom-contact-forms' ),
						'width'    => 'half',
					),
					array(
						'type'  => 'dropdown',
						'slug'  => 'service-4',
						'label' => __( 'Service Type', 'custom-contact-forms' ),
						'width' => 'half',
						'choices' => array(
							array( 'label' => __( 'Web Design', 'custom-contact-forms' ) ),
							array( 'label' => __( 'Development', 'custom-contact-forms' ) ),
							array( 'label' => __( 'Consulting', 'custom-contact-forms' ) ),
							array( 'label' => __( 'Other', 'custom-contact-forms' ) ),
						),
					),
					array(
						'type'  => 'radio',
						'slug'  => 'budget-5',
						'label' => __( 'Budget Range', 'custom-contact-forms' ),
						'choices' => array(
							array( 'label' => __( 'Under $1,000', 'custom-contact-forms' ) ),
							array( 'label' => __( '$1,000 - $5,000', 'custom-contact-forms' ) ),
							array( 'label' => __( '$5,000 - $10,000', 'custom-contact-forms' ) ),
							array( 'label' => __( '$10,000+', 'custom-contact-forms' ) ),
						),
					),
					array(
						'type'        => 'paragraph-text',
						'slug'        => 'details-6',
						'label'       => __( 'Project Details', 'custom-contact-forms' ),
						'required'    => true,
						'placeholder' => __( 'Tell us about your project...', 'custom-contact-forms' ),
					),
					array(
						'type'  => 'file',
						'slug'  => 'attachment-7',
						'label' => __( 'Attach Files', 'custom-contact-forms' ),
					),
				),
			),

			'newsletter' => array(
				'title'       => __( 'Newsletter Signup', 'custom-contact-forms' ),
				'description' => __( 'Subscribe to our newsletter.', 'custom-contact-forms' ),
				'icon'        => 'megaphone',
				'button_text' => __( 'Subscribe', 'custom-contact-forms' ),
				'fields'      => array(
					array(
						'type'     => 'name',
						'slug'     => 'name-1',
						'label'    => __( 'Name', 'custom-contact-forms' ),
						'required' => true,
					),
					array(
						'type'        => 'email',
						'slug'        => 'email-2',
						'label'       => __( 'Email Address', 'custom-contact-forms' ),
						'required'    => true,
						'placeholder' => 'you@example.com',
					),
					array(
						'type'  => 'checkboxes',
						'slug'  => 'interests-3',
						'label' => __( 'I am interested in:', 'custom-contact-forms' ),
						'choices' => array(
							array( 'label' => __( 'Product Updates', 'custom-contact-forms' ) ),
							array( 'label' => __( 'Industry News', 'custom-contact-forms' ) ),
							array( 'label' => __( 'Tips & Tutorials', 'custom-contact-forms' ) ),
							array( 'label' => __( 'Promotions & Deals', 'custom-contact-forms' ) ),
						),
					),
				),
			),

			'event' => array(
				'title'       => __( 'Event Registration', 'custom-contact-forms' ),
				'description' => __( 'Register for our upcoming event.', 'custom-contact-forms' ),
				'icon'        => 'calendar-alt',
				'button_text' => __( 'Register', 'custom-contact-forms' ),
				'fields'      => array(
					array(
						'type'     => 'name',
						'slug'     => 'name-1',
						'label'    => __( 'Full Name', 'custom-contact-forms' ),
						'required' => true,
						'width'    => 'half',
					),
					array(
						'type'     => 'email',
						'slug'     => 'email-2',
						'label'    => __( 'Email', 'custom-contact-forms' ),
						'required' => true,
						'width'    => 'half',
					),
					array(
						'type'     => 'phone',
						'slug'     => 'phone-3',
						'label'    => __( 'Phone Number', 'custom-contact-forms' ),
						'width'    => 'half',
					),
					array(
						'type'  => 'dropdown',
						'slug'  => 'attendees-4',
						'label' => __( 'Number of Attendees', 'custom-contact-forms' ),
						'width' => 'half',
						'choices' => array(
							array( 'label' => '1' ),
							array( 'label' => '2' ),
							array( 'label' => '3' ),
							array( 'label' => '4' ),
							array( 'label' => '5+' ),
						),
					),
					array(
						'type'  => 'checkboxes',
						'slug'  => 'dietary-5',
						'label' => __( 'Dietary Requirements', 'custom-contact-forms' ),
						'choices' => array(
							array( 'label' => __( 'None', 'custom-contact-forms' ) ),
							array( 'label' => __( 'Vegetarian', 'custom-contact-forms' ) ),
							array( 'label' => __( 'Vegan', 'custom-contact-forms' ) ),
							array( 'label' => __( 'Gluten Free', 'custom-contact-forms' ) ),
							array( 'label' => __( 'Other', 'custom-contact-forms' ) ),
						),
					),
					array(
						'type'        => 'paragraph-text',
						'slug'        => 'requests-6',
						'label'       => __( 'Special Requests', 'custom-contact-forms' ),
						'placeholder' => __( 'Any accessibility needs or other requests...', 'custom-contact-forms' ),
					),
				),
			),

			'feedback' => array(
				'title'       => __( 'Feedback Survey', 'custom-contact-forms' ),
				'description' => __( 'We value your feedback.', 'custom-contact-forms' ),
				'icon'        => 'thumbs-up',
				'button_text' => __( 'Submit Feedback', 'custom-contact-forms' ),
				'fields'      => array(
					array(
						'type'     => 'name',
						'slug'     => 'name-1',
						'label'    => __( 'Name', 'custom-contact-forms' ),
						'width'    => 'half',
					),
					array(
						'type'     => 'email',
						'slug'     => 'email-2',
						'label'    => __( 'Email', 'custom-contact-forms' ),
						'width'    => 'half',
					),
					array(
						'type'  => 'radio',
						'slug'  => 'rating-3',
						'label' => __( 'How would you rate your experience?', 'custom-contact-forms' ),
						'required' => true,
						'choices' => array(
							array( 'label' => __( 'Excellent', 'custom-contact-forms' ) ),
							array( 'label' => __( 'Good', 'custom-contact-forms' ) ),
							array( 'label' => __( 'Average', 'custom-contact-forms' ) ),
							array( 'label' => __( 'Poor', 'custom-contact-forms' ) ),
						),
					),
					array(
						'type'  => 'dropdown',
						'slug'  => 'source-4',
						'label' => __( 'How did you hear about us?', 'custom-contact-forms' ),
						'choices' => array(
							array( 'label' => __( 'Google Search', 'custom-contact-forms' ) ),
							array( 'label' => __( 'Social Media', 'custom-contact-forms' ) ),
							array( 'label' => __( 'Friend / Referral', 'custom-contact-forms' ) ),
							array( 'label' => __( 'Advertisement', 'custom-contact-forms' ) ),
							array( 'label' => __( 'Other', 'custom-contact-forms' ) ),
						),
					),
					array(
						'type'        => 'paragraph-text',
						'slug'        => 'improve-5',
						'label'       => __( 'What could we improve?', 'custom-contact-forms' ),
						'placeholder' => __( 'Your suggestions help us get better...', 'custom-contact-forms' ),
					),
					array(
						'type'  => 'radio',
						'slug'  => 'recommend-6',
						'label' => __( 'Would you recommend us to others?', 'custom-contact-forms' ),
						'choices' => array(
							array( 'label' => __( 'Definitely', 'custom-contact-forms' ) ),
							array( 'label' => __( 'Probably', 'custom-contact-forms' ) ),
							array( 'label' => __( 'Not sure', 'custom-contact-forms' ) ),
							array( 'label' => __( 'Probably not', 'custom-contact-forms' ) ),
						),
					),
				),
			),

		);

		/**
		 * Filter the available form templates.
		 *
		 * Add-ons can register additional templates. Each template supports
		 * 'title', 'description', 'button_text', 'badge' (small label shown
		 * in the picker), 'icon'/'color', and 'fields' — where each field
		 * supports 'type', 'slug', 'label', 'required', 'placeholder',
		 * 'description', 'choices', 'attributes' (arbitrary ccf_field_ meta)
		 * and 'conditionals'.
		 *
		 * @since 7.12.6
		 * @param array $templates Registered templates.
		 */
		return apply_filters( 'ccf_form_templates', $templates );
	}

	/**
	 * Render the templates page.
	 *
	 * @since 7.10.0
	 */
	/**
	 * Custom icon (white SVG glyph + accent colour) for a template card.
	 *
	 * @param string $key Template key.
	 * @return array { color, svg }
	 */
	private function get_template_icon( $key ) {
		$icons = array(
			'contact'    => array(
				'color' => '#2271b1',
				'svg'   => '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><rect x="3" y="5.5" width="18" height="13" rx="2" stroke="#fff" stroke-width="1.8"/><path d="M4.5 7.5l7.5 5.5 7.5-5.5" stroke="#fff" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>',
			),
			'quote'      => array(
				'color' => '#1f8a4c',
				'svg'   => '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M6.5 3.5h6.5l4.5 4.5V20a.5.5 0 0 1-.5.5H6.5a.5.5 0 0 1-.5-.5V4a.5.5 0 0 1 .5-.5z" stroke="#fff" stroke-width="1.8" stroke-linejoin="round"/><path d="M13 3.5V8.5h4.5" stroke="#fff" stroke-width="1.8" stroke-linejoin="round"/><path d="M9 13h6M9 16h6" stroke="#fff" stroke-width="1.6" stroke-linecap="round"/></svg>',
			),
			'newsletter' => array(
				'color' => '#5b5bd6',
				'svg'   => '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M20.5 4L3.5 11.2l6.4 2.4L20.5 4z" fill="#fff"/><path d="M20.5 4l-3 14.5-4.6-5.2L20.5 4z" fill="#fff" fill-opacity="0.7"/></svg>',
			),
			'event'      => array(
				'color' => '#d8602f',
				'svg'   => '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><rect x="3.5" y="5" width="17" height="15" rx="2" stroke="#fff" stroke-width="1.8"/><path d="M3.5 9.5h17M8 3.5v3M16 3.5v3" stroke="#fff" stroke-width="1.8" stroke-linecap="round"/><rect x="6.8" y="12.2" width="3.6" height="3.4" rx="0.6" fill="#fff"/></svg>',
			),
			'feedback'   => array(
				'color' => '#c2820e',
				'svg'   => '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M12 3.8l2.5 5.1 5.6.8-4.05 3.95.95 5.55L12 16.6l-5.05 2.6.95-5.55L3.85 9.7l5.6-.8L12 3.8z" stroke="#fff" stroke-width="1.6" stroke-linejoin="round"/></svg>',
			),
		);

		return isset( $icons[ $key ] ) ? $icons[ $key ] : array( 'color' => '#2271b1', 'svg' => '' );
	}

	public function render_page() {
		if ( ! defined( 'CCFP_VERSION' ) ) {
			wp_enqueue_script( 'ccf-pro-teaser', plugins_url( '/assets/js/pro-teaser.js', dirname( __FILE__ ) ), array(), CCF_VERSION, true );
			wp_localize_script( 'ccf-pro-teaser', 'ccfProTeaser', array(
				'url'     => apply_filters( 'ccf_pro_upgrade_url', 'https://customformspro.com/' ),
				'badge'   => esc_html__( 'Pro', 'custom-contact-forms' ),
				'cta'     => esc_html__( 'Get Custom Contact Forms Pro', 'custom-contact-forms' ),
				'dismiss' => esc_html__( 'Maybe later', 'custom-contact-forms' ),
			) );
		}

		$templates = $this->get_templates();
		?>
		<div class="wrap ccf-templates-wrap">
			<h1><?php esc_html_e( 'Form Templates', 'custom-contact-forms' ); ?></h1>
			<p class="ccf-templates-intro"><?php esc_html_e( 'Select a template to create a new form with pre-configured fields. You can customize everything after creation.', 'custom-contact-forms' ); ?></p>

			<style>
				.ccf-tpl-badge { display:inline-block; margin-left:6px; padding:1px 7px; border-radius:10px; font-size:10px; font-weight:600; letter-spacing:.4px; vertical-align:middle; background:#8659d6; color:#fff; }
				.ccf-templates-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:18px;margin-top:20px;max-width:1200px;}
				.ccf-tpl{position:relative;display:flex;flex-direction:column;background:#fff;border:1px solid #e2e4e7;border-radius:12px;padding:22px;box-shadow:0 1px 2px rgba(0,0,0,.04);transition:box-shadow .18s ease,border-color .18s ease,transform .18s ease;}
				.ccf-tpl:hover{border-color:#2271b1;box-shadow:0 6px 20px rgba(0,0,0,.08);transform:translateY(-1px);}
				.ccf-tpl-chip{display:flex;align-items:center;justify-content:center;width:48px;height:48px;border-radius:12px;box-shadow:0 2px 6px rgba(0,0,0,.15);margin-bottom:14px;}
				.ccf-tpl-chip svg{width:27px;height:27px;}
				.ccf-tpl h3{margin:0 0 6px;font-size:16px;color:#1d2327;}
				.ccf-tpl-desc{color:#646970;margin:0 0 10px;font-size:13px;line-height:1.45;flex:1 1 auto;}
				.ccf-tpl-meta{color:#8c8f94;margin:0 0 16px;font-size:12px;font-weight:500;text-transform:uppercase;letter-spacing:.03em;}
				.ccf-tpl-blank{align-items:center;text-align:center;border-style:dashed;border-color:#c3c4c7;background:#fbfbfc;box-shadow:none;}
				.ccf-tpl-blank:hover{border-color:#2271b1;}
				.ccf-tpl-locked{cursor:pointer;}
				.ccf-tpl-locked .ccf-tpl-chip,.ccf-tpl-locked h3,.ccf-tpl-locked .ccf-tpl-desc{opacity:.72;}
				.ccf-tpl-locked:hover .ccf-tpl-chip,.ccf-tpl-locked:hover h3,.ccf-tpl-locked:hover .ccf-tpl-desc{opacity:1;}
				.ccf-pro-modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:1700000;display:flex;align-items:center;justify-content:center;}
				.ccf-pro-modal{background:#fff;border-radius:8px;padding:26px 28px;max-width:380px;width:90%;position:relative;box-shadow:0 8px 30px rgba(0,0,0,.3);}
				.ccf-pro-modal h2{margin:0 0 10px;font-size:18px;}
				.ccf-pro-modal-badge{display:inline-block;margin-left:8px;padding:1px 8px;border-radius:10px;font-size:10px;font-weight:600;background:#8659d6;color:#fff;vertical-align:middle;}
				.ccf-pro-modal p{margin:0 0 18px;font-size:14px;color:#3c434a;}
				.ccf-pro-modal-actions{display:flex;gap:10px;}
				.ccf-pro-modal-close{position:absolute;top:8px;right:10px;background:none;border:none;font-size:22px;line-height:1;cursor:pointer;color:#787c82;}
			</style>

			<div class="ccf-templates-grid">
				<?php
				foreach ( $templates as $key => $template ) :
					$url  = wp_nonce_url(
						admin_url( 'edit.php?post_type=ccf_form&page=ccf-templates&ccf_create_template=' . $key ),
						'ccf_create_template'
					);
					$icon = $this->get_template_icon( $key );
					?>
				<div class="ccf-tpl">
					<span class="ccf-tpl-chip" style="background:<?php echo esc_attr( $icon['color'] ); ?>;">
						<?php
						if ( ! empty( $icon['svg'] ) ) {
							echo $icon['svg']; // Static, plugin-authored SVG.
						} else {
							echo '<span class="dashicons dashicons-' . esc_attr( $template['icon'] ) . '" style="color:#fff;font-size:27px;width:27px;height:27px;"></span>';
						}
						?>
					</span>
					<h3><?php echo esc_html( $template['title'] ); ?><?php if ( ! empty( $template['badge'] ) ) : ?> <span class="ccf-tpl-badge"><?php echo esc_html( $template['badge'] ); ?></span><?php endif; ?></h3>
					<p class="ccf-tpl-desc"><?php echo esc_html( $template['description'] ); ?></p>
					<p class="ccf-tpl-meta">
						<?php
						/* translators: %d: number of fields in the template */
						printf( esc_html__( '%d fields', 'custom-contact-forms' ), count( $template['fields'] ) );
						?>
					</p>
					<a href="<?php echo esc_url( $url ); ?>" class="button button-primary"><?php esc_html_e( 'Use Template', 'custom-contact-forms' ); ?></a>
				</div>
				<?php endforeach; ?>

				<?php if ( ! defined( 'CCFP_VERSION' ) ) :
					$ccf_pro_url = apply_filters( 'ccf_pro_upgrade_url', 'https://customformspro.com/' );
					$ccf_pro_templates = array(
						array( 'title' => __( 'Donation Form', 'custom-contact-forms' ), 'desc' => __( 'Giving tiers with an "Other" custom amount and enforced minimum.', 'custom-contact-forms' ), 'icon' => 'heart', 'color' => '#d63384' ),
						array( 'title' => __( 'Product Order Form', 'custom-contact-forms' ), 'desc' => __( 'Sell products with quantities, coupons and card payment.', 'custom-contact-forms' ), 'icon' => 'cart', 'color' => '#2271b1' ),
						array( 'title' => __( 'Paid Event Registration', 'custom-contact-forms' ), 'desc' => __( 'Multi-step registration with ticket tiers and payment.', 'custom-contact-forms' ), 'icon' => 'tickets-alt', 'color' => '#996800' ),
						array( 'title' => __( 'Liability Waiver', 'custom-contact-forms' ), 'desc' => __( 'Scrollable waiver, versioned consent and ink signature.', 'custom-contact-forms' ), 'icon' => 'shield', 'color' => '#b32d2e' ),
						array( 'title' => __( 'Service Agreement + Deposit', 'custom-contact-forms' ), 'desc' => __( 'Contract, signature and deposit payment in one flow.', 'custom-contact-forms' ), 'icon' => 'edit-page', 'color' => '#2271b1' ),
						array( 'title' => __( 'Satisfaction Survey Pro', 'custom-contact-forms' ), 'desc' => __( 'Star rating, Likert survey grid and conditional follow-up.', 'custom-contact-forms' ), 'icon' => 'chart-bar', 'color' => '#3858e9' ),
						array( 'title' => __( 'Invoice Payment', 'custom-contact-forms' ), 'desc' => __( 'Clients enter their invoice amount and pay online.', 'custom-contact-forms' ), 'icon' => 'media-spreadsheet', 'color' => '#50575e' ),
						array( 'title' => __( 'Tip Jar', 'custom-contact-forms' ), 'desc' => __( 'Preset tips with Apple Pay and Google Pay support.', 'custom-contact-forms' ), 'icon' => 'coffee', 'color' => '#b45309' ),
					);
					foreach ( $ccf_pro_templates as $ccf_pro_tpl ) : ?>
				<div class="ccf-tpl ccf-tpl-locked ccf-pro-teaser" data-pro-name="<?php echo esc_attr( $ccf_pro_tpl['title'] ); ?>" data-pro-desc="<?php echo esc_attr( $ccf_pro_tpl['desc'] ); ?>">
					<span class="ccf-tpl-chip" style="background:<?php echo esc_attr( $ccf_pro_tpl['color'] ); ?>;">
						<span class="dashicons dashicons-<?php echo esc_attr( $ccf_pro_tpl['icon'] ); ?>" style="color:#fff;font-size:27px;width:27px;height:27px;"></span>
					</span>
					<h3><?php echo esc_html( $ccf_pro_tpl['title'] ); ?> <span class="ccf-tpl-badge"><?php esc_html_e( 'Pro', 'custom-contact-forms' ); ?></span></h3>
					<p class="ccf-tpl-desc"><?php echo esc_html( $ccf_pro_tpl['desc'] ); ?></p>
					<p class="ccf-tpl-meta"><span class="dashicons dashicons-lock" style="font-size:12px;width:12px;height:12px;"></span> <?php esc_html_e( 'Pro template', 'custom-contact-forms' ); ?></p>
					<span class="button"><?php esc_html_e( 'Unlock', 'custom-contact-forms' ); ?></span>
				</div>
				<?php endforeach; endif; ?>

				<div class="ccf-tpl ccf-tpl-blank">
					<span class="ccf-tpl-chip" style="background:#8c8f94;">
						<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M12 5v14M5 12h14" stroke="#fff" stroke-width="2" stroke-linecap="round"/></svg>
					</span>
					<h3 style="color:#646970;"><?php esc_html_e( 'Blank Form', 'custom-contact-forms' ); ?></h3>
					<p class="ccf-tpl-desc" style="flex:0 0 auto;"><?php esc_html_e( 'Start from scratch.', 'custom-contact-forms' ); ?></p>
					<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=ccf_form' ) ); ?>" class="button"><?php esc_html_e( 'Create Blank Form', 'custom-contact-forms' ); ?></a>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Factory method.
	 *
	 * @since 7.10.0
	 * @return CCF_Form_Templates
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
