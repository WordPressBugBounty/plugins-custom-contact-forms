<?php
/**
 * Form builder panel for per-form integrations: email marketing and
 * webhooks. Added under Form Settings by assets/js/ccf-integrations-builder.js.
 *
 * @package Custom_Contact_Forms
 * @since   7.18.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CCF_Form_Integrations {

	public function setup() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ), 20 );
		add_action( 'customize_controls_enqueue_scripts', array( $this, 'enqueue' ), 20 );
		add_action( 'ccf_underscore_templates', array( $this, 'template' ) );
	}

	/**
	 * Load the builder script wherever the form builder loads.
	 */
	public function enqueue() {
		if ( ! wp_script_is( 'ccf-form-manager', 'enqueued' ) ) {
			return;
		}

		wp_enqueue_script(
			'ccf-integrations-builder',
			plugins_url( '/assets/js/ccf-integrations-builder.js', dirname( __FILE__ ) ),
			array( 'ccf-form-manager', 'jquery', 'underscore' ),
			CCF_VERSION,
			true
		);

		wp_localize_script(
			'ccf-integrations-builder',
			'ccfIntegrations',
			array(
				'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
				'nonce'       => wp_create_nonce( 'ccf_nonce' ),
				'settingsUrl' => ccf_integrations_url( 'mailchimp' ),
				'providers'   => CCF_Email_Marketing::factory()->builder_data(),
				'i18n'        => array(
					'loading'     => __( 'Loading…', 'custom-contact-forms' ),
					'choose'      => __( 'Choose…', 'custom-contact-forms' ),
					'noLists'     => __( 'None found. Create one in your account first.', 'custom-contact-forms' ),
					'notSaved'    => __( '(saved choice, not found in your account)', 'custom-contact-forms' ),
					'sending'     => __( 'Sending…', 'custom-contact-forms' ),
					'testFailed'  => __( 'Could not send the test.', 'custom-contact-forms' ),
				),
			)
		);
	}

	/**
	 * Underscore template for the panel.
	 */
	public function template() {
		?>
		<script type="text/html" id="ccf-form-integrations-template">
			<h3><?php esc_html_e( 'Email Marketing', 'custom-contact-forms' ); ?></h3>

			<# if ( ! data.connected.length ) { #>
				<p class="ccf-integrations-empty">
					<?php
					printf(
						/* translators: %s: settings link */
						wp_kses_post( __( 'Add people who fill in this form to your Mailchimp audience. <a href="%s" target="_blank">Connect Mailchimp under Forms > Integrations</a>, then come back here.', 'custom-contact-forms' ) ),
						esc_url( ccf_integrations_url( 'mailchimp' ) )
					);
					?>
				</p>
			<# } else { #>
				<p>
					<label for="ccf_form_email_provider"><?php esc_html_e( 'Add subscribers to:', 'custom-contact-forms' ); ?></label>
					<select class="ccf-em-provider" id="ccf_form_email_provider">
						<option value=""><?php esc_html_e( "Don't add to a list", 'custom-contact-forms' ); ?></option>
						<# _.each( data.connected, function( provider ) { #>
							<option value="{{ provider.id }}" <# if ( provider.id === data.settings.provider ) { #>selected<# } #>>{{ provider.label }}</option>
						<# } ); #>
					</select>
				</p>

				<div class="ccf-em-details">
					<p>
						<label for="ccf_form_email_list" class="ccf-em-list-label"><?php esc_html_e( 'List:', 'custom-contact-forms' ); ?></label>
						<select class="ccf-em-list" id="ccf_form_email_list"></select>
						<a href="#" class="ccf-em-refresh"><?php esc_html_e( 'Refresh', 'custom-contact-forms' ); ?></a>
						<span class="explain ccf-em-list-status"></span>
					</p>

					<p class="ccf-em-double-row">
						<label for="ccf_form_email_double"><?php esc_html_e( 'Double opt-in:', 'custom-contact-forms' ); ?></label>
						<select class="ccf-em-double" id="ccf_form_email_double">
							<option value="1"><?php esc_html_e( 'Send a confirmation email first (recommended)', 'custom-contact-forms' ); ?></option>
							<option value="0" <# if ( ! data.settings.doubleOptin ) { #>selected<# } #>><?php esc_html_e( 'Subscribe right away', 'custom-contact-forms' ); ?></option>
						</select>
					</p>

					<p>
						<label for="ccf_form_email_optin"><?php esc_html_e( 'Only add people who tick:', 'custom-contact-forms' ); ?></label>
						<select class="ccf-em-optin" id="ccf_form_email_optin">
							<option value=""><?php esc_html_e( 'No checkbox needed (everyone who submits)', 'custom-contact-forms' ); ?></option>
							<# _.each( data.checkboxes, function( field ) { #>
								<option value="{{ field.slug }}" <# if ( field.slug === data.settings.optinField ) { #>selected<# } #>>{{ field.label }}</option>
							<# } ); #>
						</select>
						<span class="explain"><?php esc_html_e( 'To ask for permission, add a Checkboxes field such as "Send me updates" and choose it here. The form\'s first Email field and Name field are used.', 'custom-contact-forms' ); ?></span>
					</p>
				</div>
			<# } #>

			<h3><?php esc_html_e( 'Webhooks', 'custom-contact-forms' ); ?></h3>
			<p>
				<label for="ccf_form_webhook_urls"><?php esc_html_e( 'Send each entry to:', 'custom-contact-forms' ); ?></label>
				<textarea class="widefat ccf-webhook-urls" id="ccf_form_webhook_urls" rows="2" placeholder="https://hooks.zapier.com/hooks/catch/…">{{ data.webhookUrls }}</textarea>
				<span class="explain"><?php esc_html_e( 'One URL per line, up to 5. Each new entry is sent as JSON. Works with Zapier, Make, n8n and any service that accepts webhooks.', 'custom-contact-forms' ); ?></span>
			</p>
			<p>
				<a href="#" class="button ccf-webhook-test"><?php esc_html_e( 'Send a test', 'custom-contact-forms' ); ?></a>
				<span class="explain ccf-webhook-status"></span>
			</p>
		</script>
		<?php
	}

	/**
	 * @return CCF_Form_Integrations
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
