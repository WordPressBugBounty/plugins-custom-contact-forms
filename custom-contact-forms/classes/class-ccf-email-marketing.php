<?php
/**
 * Email marketing: add people who fill in a form to a mailing list.
 *
 * Each form chooses a service, a list and double opt-in in the form builder.
 * Mailchimp is included. Custom Contact Forms Pro registers more services
 * through the ccf_email_providers filter.
 *
 * Contact details are read from the saved entry, not the request, so the
 * same code works when an entry is moved out of spam later.
 *
 * @package Custom_Contact_Forms
 * @since   7.18.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CCF_Email_Marketing {

	const FORM_META = 'ccf_form_email_marketing';

	/**
	 * @var CCF_Email_Provider[]|null
	 */
	private $providers = null;

	public function setup() {
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_init', array( $this, 'maybe_migrate_pro_settings' ) );
		add_action( 'ccf_save_form', array( $this, 'save_form' ), 10, 2 );
		add_filter( 'ccf_form_response', array( $this, 'form_response' ), 10, 2 );
		add_action( 'ccf_successful_submission', array( $this, 'sync' ), 20, 2 );
		add_action( 'wp_ajax_ccf_email_lists', array( $this, 'ajax_lists' ) );
	}

	/**
	 * Registered services, keyed by ID.
	 *
	 * @return CCF_Email_Provider[]
	 */
	public function providers() {
		if ( null === $this->providers ) {
			$providers = apply_filters( 'ccf_email_providers', array( 'mailchimp' => new CCF_Provider_Mailchimp() ) );
			$clean     = array();

			foreach ( (array) $providers as $provider ) {
				if ( $provider instanceof CCF_Email_Provider ) {
					$clean[ $provider->id() ] = $provider;
				}
			}

			$this->providers = $clean;
		}

		return $this->providers;
	}

	/**
	 * One service by ID.
	 *
	 * @param string $id Provider ID.
	 * @return CCF_Email_Provider|null
	 */
	public function provider( $id ) {
		$providers = $this->providers();

		return isset( $providers[ $id ] ) ? $providers[ $id ] : null;
	}

	/**
	 * A form's email marketing settings with defaults.
	 *
	 * @param int $form_id Form ID.
	 * @return array
	 */
	public function form_settings( $form_id ) {
		$saved = get_post_meta( $form_id, self::FORM_META, true );

		return wp_parse_args(
			is_array( $saved ) ? $saved : array(),
			array(
				'provider'    => '',
				'list'        => '',
				'doubleOptin' => true,
				'optinField'  => '',
			)
		);
	}

	/* --------------------------------------------------------------------- */
	/* Form builder                                                           */
	/* --------------------------------------------------------------------- */

	/**
	 * Save the form's settings sent by the builder.
	 *
	 * @param int   $form_id Form ID.
	 * @param array $data    Request data.
	 */
	public function save_form( $form_id, $data ) {
		if ( ! isset( $data['emailMarketing'] ) || ! is_array( $data['emailMarketing'] ) ) {
			return;
		}

		$in       = $data['emailMarketing'];
		$provider = isset( $in['provider'] ) ? sanitize_key( $in['provider'] ) : '';

		if ( '' !== $provider && ! $this->provider( $provider ) ) {
			$provider = '';
		}

		update_post_meta(
			$form_id,
			self::FORM_META,
			array(
				'provider'    => $provider,
				'list'        => isset( $in['list'] ) ? sanitize_text_field( $in['list'] ) : '',
				'doubleOptin' => ! isset( $in['doubleOptin'] ) || ! empty( $in['doubleOptin'] ),
				'optinField'  => isset( $in['optinField'] ) ? sanitize_text_field( $in['optinField'] ) : '',
			)
		);
	}

	/**
	 * Add the settings to the form data the builder loads.
	 *
	 * @param array   $data Form data.
	 * @param WP_Post $item Form post.
	 * @return array
	 */
	public function form_response( $data, $item ) {
		$data['emailMarketing'] = $this->form_settings( $item->ID );

		return $data;
	}

	/**
	 * Services and labels for the builder script.
	 *
	 * @return array
	 */
	public function builder_data() {
		$out = array();

		foreach ( $this->providers() as $provider ) {
			$out[] = array(
				'id'          => $provider->id(),
				'label'       => $provider->label(),
				'listLabel'   => $provider->list_label(),
				'connected'   => $provider->is_configured(),
				'doubleOptin' => $provider->supports_double_optin(),
			);
		}

		return $out;
	}

	/**
	 * Lists for one service, for the builder's dropdown.
	 */
	public function ajax_lists() {
		check_ajax_referer( 'ccf_nonce', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to do that.', 'custom-contact-forms' ) ), 403 );
		}

		$provider = $this->provider( isset( $_POST['provider'] ) ? sanitize_key( wp_unslash( $_POST['provider'] ) ) : '' );

		if ( ! $provider ) {
			wp_send_json_error( array( 'message' => __( 'Unknown service.', 'custom-contact-forms' ) ) );
		}

		$lists = $provider->get_lists( ! empty( $_POST['refresh'] ) );

		if ( is_wp_error( $lists ) ) {
			wp_send_json_error( array( 'message' => $lists->get_error_message() ) );
		}

		$out = array();

		foreach ( $lists as $id => $name ) {
			$out[] = array(
				'id'   => (string) $id,
				'name' => (string) $name,
			);
		}

		wp_send_json_success( array( 'lists' => $out ) );
	}

	/* --------------------------------------------------------------------- */
	/* Sending                                                                */
	/* --------------------------------------------------------------------- */

	/**
	 * Add the person who filled in the form to the form's list.
	 *
	 * @param int $submission_id Entry ID.
	 * @param int $form_id       Form ID.
	 */
	public function sync( $submission_id, $form_id ) {
		$settings = $this->form_settings( $form_id );

		if ( '' === $settings['provider'] ) {
			return;
		}

		// Custom Contact Forms Pro before 1.2.0 sends Mailchimp subscribers
		// itself. Leave it to Pro until Pro is updated, so nobody gets two
		// confirmation emails.
		if ( $this->old_pro_handles_email() ) {
			return;
		}

		$provider = $this->provider( $settings['provider'] );

		if ( ! $provider ) {
			return;
		}

		if ( ! $provider->is_configured() ) {
			CCF_Integration_Log::add( $provider->label(), $form_id, __( 'Not connected. Add your API key under Forms > Integrations.', 'custom-contact-forms' ) );
			return;
		}

		if ( '' === $settings['list'] ) {
			CCF_Integration_Log::add( $provider->label(), $form_id, __( 'No list is chosen for this form.', 'custom-contact-forms' ) );
			return;
		}

		$contact = $this->contact_from_entry( $submission_id, $settings['optinField'] );

		if ( '' === $contact['email'] ) {
			return;
		}

		if ( ! $contact['consent'] ) {
			update_post_meta( $submission_id, 'ccf_email_marketing', 'no_consent' );
			return;
		}

		$result = $provider->subscribe(
			$contact,
			$settings['list'],
			array( 'double_optin' => ! empty( $settings['doubleOptin'] ) )
		);

		if ( is_wp_error( $result ) ) {
			update_post_meta( $submission_id, 'ccf_email_marketing', 'error' );
			/* translators: 1: email address, 2: error message */
			CCF_Integration_Log::add( $provider->label(), $form_id, sprintf( __( 'Could not add %1$s: %2$s', 'custom-contact-forms' ), $contact['email'], $result->get_error_message() ) );
			return;
		}

		update_post_meta( $submission_id, 'ccf_email_marketing', $provider->id() );

		/**
		 * Fires after a contact is sent to an email marketing service.
		 *
		 * @since 7.18.0
		 * @param string $provider      Provider ID.
		 * @param array  $contact       Contact details.
		 * @param int    $submission_id Entry ID.
		 * @param int    $form_id       Form ID.
		 */
		do_action( 'ccf_email_marketing_subscribed', $provider->id(), $contact, $submission_id, $form_id );
	}

	/**
	 * Read the email, name and consent from a saved entry.
	 *
	 * The first Email field and first Name field are used. Consent is given
	 * when the chosen checkbox field is ticked, or when there is no opt-in
	 * field on the form at all.
	 *
	 * @param int    $submission_id Entry ID.
	 * @param string $optin_field   Slug of the checkbox field, or empty.
	 * @return array { email, first_name, last_name, consent }
	 */
	public function contact_from_entry( $submission_id, $optin_field = '' ) {
		$data = get_post_meta( $submission_id, 'ccf_submission_data', true );
		$map  = get_post_meta( $submission_id, 'ccf_submission_data_map', true );
		$data = is_array( $data ) ? $data : array();
		$map  = is_array( $map ) ? $map : array();

		$contact = array(
			'email'      => '',
			'first_name' => '',
			'last_name'  => '',
			'consent'    => true,
		);

		$pro_optin_present = false;
		$pro_optin_ticked  = false;

		foreach ( $map as $slug => $info ) {
			$type  = isset( $info['type'] ) ? $info['type'] : '';
			$value = isset( $data[ $slug ] ) ? $data[ $slug ] : '';

			if ( 'email' === $type && '' === $contact['email'] ) {
				$email = is_array( $value ) ? ( isset( $value['email'] ) ? $value['email'] : '' ) : $value;
				$email = sanitize_email( $email );

				if ( is_email( $email ) ) {
					$contact['email'] = $email;
				}
			} elseif ( 'name' === $type && '' === $contact['first_name'] && '' === $contact['last_name'] ) {
				if ( is_array( $value ) ) {
					$contact['first_name'] = isset( $value['first'] ) ? sanitize_text_field( $value['first'] ) : '';
					$contact['last_name']  = isset( $value['last'] ) ? sanitize_text_field( $value['last'] ) : '';
				} else {
					$contact['first_name'] = sanitize_text_field( $value );
				}
			} elseif ( 'email-optin' === $type ) {
				// The consent field from Custom Contact Forms Pro.
				$pro_optin_present = true;
				$pro_optin_ticked  = $pro_optin_ticked || $this->is_ticked( $value );
			}
		}

		if ( '' !== $optin_field ) {
			$contact['consent'] = isset( $data[ $optin_field ] ) && $this->is_ticked( $data[ $optin_field ] );
		} elseif ( $pro_optin_present ) {
			$contact['consent'] = $pro_optin_ticked;
		}

		return $contact;
	}

	/**
	 * Whether a checkbox value counts as ticked.
	 *
	 * @param mixed $value Field value.
	 * @return bool
	 */
	private function is_ticked( $value ) {
		if ( is_array( $value ) ) {
			return count( array_filter( array_map( 'strval', $value ), 'strlen' ) ) > 0;
		}

		return '' !== trim( (string) $value ) && '0' !== (string) $value;
	}

	/* --------------------------------------------------------------------- */
	/* Settings page                                                          */
	/* --------------------------------------------------------------------- */

	public function register_settings() {
		// Each service is saved from its own card on Forms > Integrations.
		foreach ( $this->providers() as $provider ) {
			register_setting(
				'ccf-int-' . $provider->id(),
				$provider->option_name(),
				array(
					'type'              => 'array',
					'sanitize_callback' => array( $provider, 'sanitize_settings' ),
				)
			);
		}
	}

	/**
	 * Connection status for a service's card.
	 *
	 * @param CCF_Email_Provider $provider Service.
	 * @return array { state: ok|warn|off, label }
	 */
	public function status( $provider ) {
		if ( ! $provider->is_configured() ) {
			return array(
				'state' => 'off',
				'label' => __( 'Not connected', 'custom-contact-forms' ),
			);
		}

		$lists = $provider->get_lists();

		if ( is_wp_error( $lists ) ) {
			return array(
				'state' => 'warn',
				'label' => __( 'Problem connecting', 'custom-contact-forms' ),
			);
		}

		return array(
			'state' => 'ok',
			'label' => __( 'Connected', 'custom-contact-forms' ),
		);
	}

	/**
	 * Card settings for one service.
	 *
	 * @param CCF_Email_Provider $provider Service.
	 */
	public function render_settings( $provider ) {
		$this->provider_fields( array( 'provider' => $provider ) );
		echo '<p class="description">' . esc_html__( 'After connecting, open a form, go to Form Settings and choose a list under Email Marketing.', 'custom-contact-forms' ) . '</p>';
	}

	/**
	 * Fields for one service, with its connection status.
	 *
	 * @param array $args { provider: CCF_Email_Provider }.
	 */
	public function provider_fields( $args ) {
		$provider = $args['provider'];
		$settings = $provider->get_settings();
		$option   = $provider->option_name();

		foreach ( $provider->settings_fields() as $key => $field ) {
			$id   = 'ccf-' . $provider->id() . '-' . $key;
			$type = isset( $field['type'] ) && 'password' === $field['type'] ? 'password' : 'text';
			?>
			<p>
				<label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $field['label'] ); ?></label><br>
				<input type="<?php echo esc_attr( $type ); ?>" id="<?php echo esc_attr( $id ); ?>" class="regular-text" autocomplete="off"
					name="<?php echo esc_attr( $option . '[' . $key . ']' ); ?>"
					value="<?php echo esc_attr( $settings[ $key ] ); ?>"
					<?php if ( ! empty( $field['placeholder'] ) ) : ?>placeholder="<?php echo esc_attr( $field['placeholder'] ); ?>"<?php endif; ?>>
			</p>
			<?php
		}

		if ( $provider->key_help() ) {
			echo '<p class="description">' . esc_html( $provider->key_help() ) . '</p>';
		}

		if ( $provider->is_configured() ) {
			$lists = $provider->get_lists();

			if ( is_wp_error( $lists ) ) {
				echo '<p style="color:#b32d2e;"><span class="dashicons dashicons-warning" aria-hidden="true"></span> ' . esc_html( $lists->get_error_message() ) . '</p>';
			} else {
				/* translators: 1: list word such as "audiences", 2: number of lists */
				echo '<p style="color:#008a20;"><span class="dashicons dashicons-yes-alt" aria-hidden="true"></span> ' . esc_html( sprintf( _n( 'Connected. %d list found.', 'Connected. %d lists found.', count( $lists ), 'custom-contact-forms' ), count( $lists ) ) ) . '</p>';
			}
		}
	}

	/* --------------------------------------------------------------------- */
	/* Moving Mailchimp from Pro                                              */
	/* --------------------------------------------------------------------- */

	/**
	 * Pro settings, when Pro's email module is turned on.
	 *
	 * @return array|null
	 */
	private function pro_email_settings() {
		$enabled = get_option( 'ccfp_enabled_modules', array() );

		if ( ! is_array( $enabled ) || ! in_array( 'email-marketing', $enabled, true ) ) {
			return null;
		}

		$settings = get_option( 'ccfp_module_email-marketing_settings', array() );

		return is_array( $settings ) ? $settings : null;
	}

	/**
	 * Whether an older Pro is still sending subscribers itself.
	 *
	 * @return bool
	 */
	private function old_pro_handles_email() {
		if ( ! defined( 'CCFP_VERSION' ) || version_compare( CCFP_VERSION, '1.2.0', '>=' ) ) {
			return false;
		}

		$pro = $this->pro_email_settings();

		return ! empty( $pro['api_key'] ) && ! empty( $pro['list_id'] );
	}

	/**
	 * Copy a Mailchimp connection made in Pro 1.x into the free plugin once,
	 * and point every form that had none at the same audience, so nothing
	 * changes for the site when Pro stops handling Mailchimp.
	 */
	public function maybe_migrate_pro_settings() {
		if ( get_option( 'ccf_email_marketing_migrated' ) ) {
			return;
		}

		update_option( 'ccf_email_marketing_migrated', 1, false );

		$pro = $this->pro_email_settings();

		if ( empty( $pro['api_key'] ) || empty( $pro['list_id'] ) ) {
			return;
		}

		$provider = isset( $pro['provider'] ) ? $pro['provider'] : 'mailchimp';

		if ( 'mailchimp' !== $provider ) {
			// MailerLite stays in Pro, which moves its own settings.
			return;
		}

		$mailchimp = $this->provider( 'mailchimp' );

		if ( $mailchimp && '' === $mailchimp->setting( 'api_key' ) ) {
			update_option( $mailchimp->option_name(), array( 'api_key' => sanitize_text_field( $pro['api_key'] ) ) );
		}

		$forms = get_posts(
			array(
				'post_type'      => 'ccf_form',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);

		foreach ( $forms as $form_id ) {
			if ( '' !== get_post_meta( $form_id, self::FORM_META, true ) ) {
				continue;
			}

			update_post_meta(
				$form_id,
				self::FORM_META,
				array(
					'provider'    => 'mailchimp',
					'list'        => sanitize_text_field( $pro['list_id'] ),
					'doubleOptin' => ! isset( $pro['opt_in'] ) || 'single' !== $pro['opt_in'],
					'optinField'  => '',
				)
			);
		}
	}

	/**
	 * @return CCF_Email_Marketing
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
