<?php
/**
 * Admin integration for AI form building.
 *
 * @package Custom_Contact_Forms
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CCF_AI_Admin {

	/**
	 * Singleton.
	 *
	 * @return CCF_AI_Admin
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
		add_action( 'admin_footer', array( $this, 'render_dialog' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'wp_ajax_ccf_ai_build_form', array( $this, 'ajax_build' ) );
	}

	/**
	 * Screens where the button belongs: the forms list and the builder.
	 *
	 * @return bool
	 */
	private function is_ccf_screen() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		return $screen && isset( $screen->post_type ) && 'ccf_form' === $screen->post_type;
	}

	/**
	 * Enqueue the button and dialog script.
	 */
	public function enqueue() {
		// Not gated on availability: the buttons are shown even when the
		// feature needs setting up, and they need this script to open the
		// dialog that explains what is missing.
		if ( ! $this->is_ccf_screen() ) {
			return;
		}

		wp_enqueue_script(
			'ccf-ai',
			CCF_PLUGIN_URL . 'assets/js/ccf-ai.js',
			array(),
			CCF_VERSION,
			true
		);

		// Registered against a core handle so nothing has to be added to the
		// SCSS build for a dialog this small.
		wp_register_style( 'ccf-ai', false, array(), CCF_VERSION );
		wp_enqueue_style( 'ccf-ai' );
		wp_add_inline_style( 'ccf-ai', self::styles() );

		wp_localize_script(
			'ccf-ai',
			'CCF_AI',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'ccf_ai_build_form' ),
				'i18n'    => array(
					'button'      => __( 'Build with AI', 'custom-contact-forms' ),
					'title'       => __( 'Describe your form', 'custom-contact-forms' ),
					'intro'       => __( 'Say what the form is for and roughly what you need to collect. You can change everything afterwards.', 'custom-contact-forms' ),
					'placeholder' => __( 'A booking form for a dog grooming appointment: name, email, phone, which dog, preferred date, and any special requirements.', 'custom-contact-forms' ),
					'submit'      => __( 'Build it', 'custom-contact-forms' ),
					'cancel'      => __( 'Cancel', 'custom-contact-forms' ),
					'working'     => __( 'Building your form…', 'custom-contact-forms' ),
					'steps'       => array(
						__( 'Reading your description…', 'custom-contact-forms' ),
						__( 'Working out what to ask…', 'custom-contact-forms' ),
						__( 'Choosing the right fields…', 'custom-contact-forms' ),
						__( 'Writing the labels…', 'custom-contact-forms' ),
						__( 'Putting it together…', 'custom-contact-forms' ),
					),
					'built'       => __( 'Here is your form', 'custom-contact-forms' ),
					'empty'       => __( 'Describe the form first.', 'custom-contact-forms' ),
					'failed'      => __( 'Something went wrong. Try again.', 'custom-contact-forms' ),
					'proIntro'    => __( 'This form would also use:', 'custom-contact-forms' ),
					'proLink'     => __( 'See Custom Contact Forms Pro', 'custom-contact-forms' ),
					'opening'     => __( 'Opening your form…', 'custom-contact-forms' ),
				),
				'proUrl'  => function_exists( 'ccf_pro_url' ) ? ccf_pro_url( 'ai-builder' ) : '',
				// The feature is advertised whether or not it can run yet, so
				// the dialog needs to know which state to open in.
				'ready'   => CCF_AI_Form_Builder::is_available(),
				'setup'   => $this->setup_notice(),
			)
		);
	}

	/**
	 * Markup for the dialog, hidden until opened.
	 */
	public function render_dialog() {
		if ( ! $this->is_ccf_screen() ) {
			return;
		}
		?>
		<div id="ccf-ai-dialog" class="ccf-ai-dialog" hidden>
			<div class="ccf-ai-dialog-inner" role="dialog" aria-modal="true" aria-labelledby="ccf-ai-heading"></div>
		</div>
		<?php
	}

	/**
	 * Generate a form and create it.
	 */
	public function ajax_build() {
		check_ajax_referer( 'ccf_ai_build_form', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) || ! CCF_AI_Form_Builder::is_available() ) {
			wp_send_json_error( array( 'message' => __( 'Not allowed.', 'custom-contact-forms' ) ) );
		}

		$description = isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '';

		if ( '' === trim( $description ) ) {
			wp_send_json_error( array( 'message' => __( 'Describe the form first.', 'custom-contact-forms' ) ) );
		}

		$spec = CCF_AI_Form_Builder::generate( $description );

		if ( is_wp_error( $spec ) ) {
			wp_send_json_error( array( 'message' => $spec->get_error_message() ) );
		}

		$form_id = wp_insert_post(
			array(
				'post_title'  => $spec['title'],
				'post_status' => 'publish',
				'post_type'   => 'ccf_form',
			)
		);

		if ( is_wp_error( $form_id ) ) {
			wp_send_json_error( array( 'message' => $form_id->get_error_message() ) );
		}

		// Reuse the REST controller's own field creation so the result is
		// identical to a form built by hand in the builder. The class is only
		// loaded on rest_api_init, which does not run during admin-ajax, so
		// require it here rather than fataling on a missing class.
		if ( ! class_exists( 'CCF_API_Form_Controller' ) ) {
			require_once CCF_PLUGIN_DIR . 'classes/class-ccf-api-form-controller.php';
		}

		$controller = new CCF_API_Form_Controller();
		$controller->_create_and_map_fields( $spec['fields'], $form_id );

		// The same form-level defaults a template sets. Without these the form
		// renders with an unlabelled submit button and, worse, no notification
		// emails — the fields are only half of what makes a working form.
		update_post_meta( $form_id, 'ccf_form_title', $spec['title'] );
		update_post_meta( $form_id, 'ccf_form_description', '' );
		update_post_meta( $form_id, 'ccf_form_buttonText', esc_attr( $spec['button'] ) );
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
		update_post_meta( $form_id, 'ccf_form_created_by_ai', 1 );

		wp_send_json_success(
			array(
				'formId'    => $form_id,
				'editUrl'   => admin_url( 'post.php?post=' . $form_id . '&action=edit' ),
				'title'     => $spec['title'],
				'fieldCount'=> count( $spec['fields'] ),
				// Labels and types so the result can show what was built
				// rather than a bare count.
				'fields'    => array_map(
					function ( $field ) {
						return array(
							'label' => $field['label'],
							'type'  => $field['type'],
						);
					},
					$spec['fields']
				),
				'pro'       => array_values( $spec['pro'] ),
			)
		);
	}

	/**
	 * Dialog styles. Small enough to inline; keeps the SCSS build untouched.
	 *
	 * @return string
	 */
	public static function styles() {
		return '
		.ccf-ai-dialog { position: fixed; inset: 0; background: rgba(0,0,0,.5); z-index: 100000; display: flex; align-items: center; justify-content: center; }
		.ccf-ai-dialog[hidden] { display: none; }
		.ccf-ai-dialog-inner { background: #fff; border-radius: 6px; padding: 24px; width: min(560px, 92vw); max-height: 88vh; overflow: auto; box-shadow: 0 10px 40px rgba(0,0,0,.25); }
		.ccf-ai-dialog-inner h2 { margin-top: 0; }
		.ccf-ai-intro { color: #50575e; }
		.ccf-ai-input { width: 100%; margin: 8px 0; }
		.ccf-ai-status { min-height: 1.4em; margin: 8px 0; }
		.ccf-ai-actions { display: flex; gap: 8px; margin-top: 12px; }
		.ccf-ai-pro { margin-top: 16px; padding: 14px 16px; background: #f6f7f7; border-left: 4px solid #2271b1; border-radius: 3px; }
		.ccf-ai-pro-intro { margin-top: 0; font-weight: 600; }
		.ccf-ai-pro ul { margin: 0 0 12px 18px; list-style: disc; }
		.ccf-ai-builder-cta { display: flex; align-items: center; gap: 12px; margin: 0 0 14px; padding: 12px 16px; background: linear-gradient(135deg,#f3f0ff,#eef4ff); border: 1px solid #d8d3f5; border-radius: 6px; }
		.ccf-ai-builder-cta-text { font-weight: 600; color: #3c366b; }
		.ccf-ai-callout { display: flex; align-items: center; gap: 14px; flex-wrap: wrap; padding: 16px 18px; background: linear-gradient(135deg,#f3f0ff,#eef4ff); border: 1px solid #d8d3f5; border-radius: 6px; margin: 12px 0; }
		.ccf-ai-callout p { margin: 0; }
		/* Sits inside the page <h1>, which would otherwise give it heading
		   sizing and weight. */
		.ccf-ai-beside-manage { font-size: 13px !important; font-weight: 400 !important; line-height: 2.15384615 !important; vertical-align: middle; margin-left: 6px !important; }

		/* The wait is the weakest moment in this flow — a few seconds of
		   nothing. These give it some shape without pretending to show real
		   progress, which we cannot know. */
		.ccf-ai-dialog-inner { animation: ccfAiRise .22s ease-out; }
		@keyframes ccfAiRise { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: none; } }

		.ccf-ai-working { display: flex; align-items: center; gap: 10px; margin: 14px 0; }
		.ccf-ai-orb { width: 22px; height: 22px; border-radius: 50%; flex: none;
			background: conic-gradient(from 0deg, #8b5cf6, #6366f1, #ec4899, #8b5cf6);
			animation: ccfAiSpin 1.1s linear infinite; position: relative; }
		.ccf-ai-orb::after { content: ""; position: absolute; inset: 4px; border-radius: 50%; background: #fff; }
		@keyframes ccfAiSpin { to { transform: rotate(360deg); } }

		.ccf-ai-step { font-weight: 600; color: #4c1d95; animation: ccfAiFade .4s ease; }
		@keyframes ccfAiFade { from { opacity: 0; transform: translateY(4px); } to { opacity: 1; transform: none; } }

		.ccf-ai-skeleton { margin: 4px 0 0; padding: 0; list-style: none; }
		.ccf-ai-skeleton li { height: 34px; margin-bottom: 8px; border-radius: 5px;
			background: linear-gradient(90deg,#f1f0fa 25%,#e6e3f7 37%,#f1f0fa 63%);
			background-size: 400% 100%; animation: ccfAiShimmer 1.3s ease infinite; }
		@keyframes ccfAiShimmer { 0% { background-position: 100% 50%; } 100% { background-position: 0 50%; } }

		.ccf-ai-built { margin: 10px 0 0; padding: 0; list-style: none; }
		.ccf-ai-built li { display: flex; align-items: center; gap: 8px; padding: 7px 10px; margin-bottom: 6px;
			background: #f6f7f7; border-left: 3px solid #8b5cf6; border-radius: 3px; font-size: 13px;
			opacity: 0; animation: ccfAiPop .32s ease forwards; }
		.ccf-ai-built li .ccf-ai-type { margin-left: auto; color: #757575; font-size: 11px; text-transform: uppercase; letter-spacing: .04em; }
		@keyframes ccfAiPop { from { opacity: 0; transform: translateX(-8px); } to { opacity: 1; transform: none; } }
		';
	}

	/**
	 * What is standing between this site and AI form building.
	 *
	 * The feature advertises itself whether or not it can run, so this has to
	 * answer "why not, and what do I do about it" rather than just refusing.
	 *
	 * @return array{heading:string,body:string,url:string,label:string}|null Null when it works.
	 */
	private function setup_notice() {
		if ( CCF_AI_Form_Builder::is_available() ) {
			return null;
		}

		if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
			return array(
				'heading' => __( 'Needs WordPress 7.0', 'custom-contact-forms' ),
				'body'    => __( 'WordPress 7.0 added a built-in way for plugins to use AI, with one shared connection for the whole site. Once you update, this turns on here with no extra setup.', 'custom-contact-forms' ),
				'url'     => admin_url( 'update-core.php' ),
				'label'   => __( 'Check for updates', 'custom-contact-forms' ),
			);
		}

		if ( ! current_user_can( 'prompt_ai' ) && ! current_user_can( 'manage_options' ) ) {
			return array(
				'heading' => __( 'Ask an administrator', 'custom-contact-forms' ),
				'body'    => __( 'Your account does not have permission to use AI on this site. An administrator can grant it.', 'custom-contact-forms' ),
				'url'     => '',
				'label'   => '',
			);
		}

		return array(
			'heading' => __( 'Connect an AI provider first', 'custom-contact-forms' ),
			'body'    => __( 'Open Settings → Connectors, install the connector for Anthropic, OpenAI or Google, and add its API key. WordPress stores that key once and shares it with every plugin that needs it, so there is nothing to enter here and no key for us to keep.', 'custom-contact-forms' ),
			// Core's own admin file, not a page= slug. Only reachable once
			// wp_ai_client_prompt() exists, so WordPress 7.0 is already
			// established and the file is guaranteed to be there.
			'url'     => admin_url( 'options-connectors.php' ),
			'label'   => __( 'Open Connectors', 'custom-contact-forms' ),
		);
	}
}
