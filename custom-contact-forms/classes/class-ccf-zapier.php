<?php
/**
 * Zapier connection.
 *
 * Lets the Custom Contact Forms app on Zapier connect to this site with a
 * key, list the site's forms, and turn on a "New Form Entry" trigger by
 * itself (REST hooks), so nobody has to paste webhook URLs.
 *
 * Routes (namespace ccf/v1/zapier), all requiring the site's Zapier key in
 * the X-CCF-Zapier-Key header:
 *   GET    /me                  Connection test and account label.
 *   GET    /forms               Forms for the form dropdown.
 *   GET    /entries?form_id=N   Recent entries, or sample data, for setup.
 *   POST   /hooks               Subscribe a Zapier hook URL to a form.
 *   DELETE /hooks/<id>          Unsubscribe.
 *
 * @since 7.21.0
 * @package Custom_Contact_Forms
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CCF_Zapier {

	const KEY_OPTION   = 'ccf_zapier_key';
	const HOOKS_OPTION = 'ccf_zapier_hooks';
	const NAMESPACE_V1 = 'ccf/v1/zapier';

	/**
	 * Setup hooks.
	 */
	public function setup() {
		add_action( 'rest_api_init', array( $this, 'routes' ) );
		add_action( 'ccf_successful_submission', array( $this, 'send' ), 35, 2 );
		add_filter( 'ccf_integration_cards', array( $this, 'card' ) );
		add_action( 'admin_post_ccf_zapier_new_key', array( $this, 'new_key' ) );
	}

	/* --------------------------------------------------------------------- */
	/* Key                                                                    */
	/* --------------------------------------------------------------------- */

	/**
	 * The site's Zapier key, created the first time it is needed.
	 *
	 * @return string
	 */
	public function key() {
		$key = get_option( self::KEY_OPTION, '' );

		if ( ! is_string( $key ) || strlen( $key ) < 32 ) {
			$key = wp_generate_password( 40, false, false );
			update_option( self::KEY_OPTION, $key, false );
		}

		return $key;
	}

	/**
	 * Replace the key. Existing Zapier connections stop working and their
	 * hooks are removed, so an old key can never be used again.
	 */
	public function new_key() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'custom-contact-forms' ) );
		}

		check_admin_referer( 'ccf_zapier_new_key' );

		update_option( self::KEY_OPTION, wp_generate_password( 40, false, false ), false );
		update_option( self::HOOKS_OPTION, array(), false );

		wp_safe_redirect( admin_url( 'edit.php?post_type=ccf_form&page=' . CCF_Integrations_Page::SLUG . '&open=zapier&ccf-zapier=new-key' ) );
		exit;
	}

	/**
	 * Check the key sent by Zapier.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public function permission( $request ) {
		$sent = (string) $request->get_header( 'x_ccf_zapier_key' );
		$key  = get_option( self::KEY_OPTION, '' );

		if ( ! is_string( $key ) || strlen( $key ) < 32 || '' === $sent || ! hash_equals( $key, $sent ) ) {
			return new WP_Error(
				'ccf_zapier_key',
				__( 'The Zapier key is missing or wrong. Copy it again from Forms > Integrations > Zapier on your WordPress site.', 'custom-contact-forms' ),
				array( 'status' => 401 )
			);
		}

		return true;
	}

	/* --------------------------------------------------------------------- */
	/* Routes                                                                 */
	/* --------------------------------------------------------------------- */

	public function routes() {
		$permission = array( $this, 'permission' );

		register_rest_route(
			self::NAMESPACE_V1,
			'/me',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'route_me' ),
				'permission_callback' => $permission,
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/forms',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'route_forms' ),
				'permission_callback' => $permission,
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/entries',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'route_entries' ),
				'permission_callback' => $permission,
				'args'                => array(
					'form_id' => array(
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/hooks',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'route_subscribe' ),
				'permission_callback' => $permission,
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/hooks/(?P<id>[a-zA-Z0-9]+)',
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'route_unsubscribe' ),
				'permission_callback' => $permission,
			)
		);
	}

	/**
	 * Connection test. Zapier shows the site name as the account label.
	 *
	 * @return array
	 */
	public function route_me() {
		return array(
			'site_name' => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
			'site_url'  => home_url( '/' ),
			'version'   => defined( 'CCF_VERSION' ) ? CCF_VERSION : '',
		);
	}

	/**
	 * Published forms, newest first.
	 *
	 * @return array
	 */
	public function route_forms() {
		$forms = get_posts(
			array(
				'post_type'      => 'ccf_form',
				'post_status'    => 'publish',
				'posts_per_page' => 200,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);

		$out = array();

		foreach ( $forms as $form ) {
			$out[] = array(
				'id'    => (int) $form->ID,
				'title' => '' !== $form->post_title ? $form->post_title : sprintf( /* translators: %d: form ID */ __( 'Form %d', 'custom-contact-forms' ), $form->ID ),
			);
		}

		return $out;
	}

	/**
	 * Recent entries so Zapier can show real fields while setting up a Zap.
	 * With no entries yet, sample data built from the form's fields.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array|WP_Error
	 */
	public function route_entries( $request ) {
		$form_id = (int) $request->get_param( 'form_id' );

		if ( $form_id && 'ccf_form' !== get_post_type( $form_id ) ) {
			return new WP_Error( 'ccf_zapier_form', __( 'That form was not found.', 'custom-contact-forms' ), array( 'status' => 404 ) );
		}

		$args = array(
			'post_type'      => 'ccf_submission',
			'post_status'    => 'publish',
			'posts_per_page' => 3,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);

		if ( $form_id ) {
			$args['post_parent'] = $form_id;
		}

		$out = array();

		foreach ( get_posts( $args ) as $entry ) {
			$out[] = $this->entry( $entry->ID, (int) $entry->post_parent );
		}

		if ( ! $out && $form_id ) {
			$sample       = CCF_Webhooks::factory()->sample_payload( $form_id );
			$sample['id'] = 'sample-' . $form_id;
			$out[]        = $sample;
		}

		return $out;
	}

	/**
	 * Subscribe a Zapier hook URL. Form 0 means every form.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array|WP_Error
	 */
	public function route_subscribe( $request ) {
		$url     = esc_url_raw( (string) $request->get_param( 'hook_url' ) );
		$form_id = absint( $request->get_param( 'form_id' ) );

		if ( ! $this->is_zapier_url( $url ) ) {
			return new WP_Error( 'ccf_zapier_url', __( 'Only Zapier hook addresses can be subscribed.', 'custom-contact-forms' ), array( 'status' => 400 ) );
		}

		if ( $form_id && 'ccf_form' !== get_post_type( $form_id ) ) {
			return new WP_Error( 'ccf_zapier_form', __( 'That form was not found.', 'custom-contact-forms' ), array( 'status' => 404 ) );
		}

		$hooks = $this->hooks();
		$id    = strtolower( wp_generate_password( 16, false, false ) );

		$hooks[ $id ] = array(
			'url'     => $url,
			'form_id' => $form_id,
			'created' => time(),
		);

		update_option( self::HOOKS_OPTION, $hooks, false );

		return array(
			'id'      => $id,
			'form_id' => $form_id,
		);
	}

	/**
	 * Remove a subscription when a Zap is turned off or deleted.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array
	 */
	public function route_unsubscribe( $request ) {
		$id    = (string) $request->get_param( 'id' );
		$hooks = $this->hooks();

		unset( $hooks[ $id ] );
		update_option( self::HOOKS_OPTION, $hooks, false );

		return array( 'id' => $id, 'deleted' => true );
	}

	/* --------------------------------------------------------------------- */
	/* Sending                                                                */
	/* --------------------------------------------------------------------- */

	/**
	 * Saved subscriptions.
	 *
	 * @return array id => { url, form_id, created }
	 */
	public function hooks() {
		$hooks = get_option( self::HOOKS_OPTION, array() );

		return is_array( $hooks ) ? $hooks : array();
	}

	/**
	 * Only https addresses on zapier.com are accepted, so the site can't be
	 * used to post entries anywhere else.
	 *
	 * @param string $url URL.
	 * @return bool
	 */
	public function is_zapier_url( $url ) {
		$parts = wp_parse_url( $url );

		if ( empty( $parts['scheme'] ) || 'https' !== strtolower( $parts['scheme'] ) || empty( $parts['host'] ) ) {
			return false;
		}

		$host = strtolower( $parts['host'] );

		return 'zapier.com' === $host || '.zapier.com' === substr( $host, -11 );
	}

	/**
	 * One entry in the shape Zapier receives: the webhook body plus a
	 * top-level id, which Zapier uses to tell entries apart.
	 *
	 * @param int $submission_id Entry ID.
	 * @param int $form_id       Form ID.
	 * @return array
	 */
	public function entry( $submission_id, $form_id ) {
		$payload       = CCF_Webhooks::factory()->payload( $submission_id, $form_id );
		$payload['id'] = (int) $submission_id;

		return $payload;
	}

	/**
	 * Send a new entry to every Zap watching its form.
	 *
	 * @param int $submission_id Entry ID.
	 * @param int $form_id       Form ID.
	 */
	public function send( $submission_id, $form_id ) {
		$hooks = $this->hooks();

		if ( ! $hooks ) {
			return;
		}

		$payload = null;
		$changed = false;

		foreach ( $hooks as $id => $hook ) {
			if ( empty( $hook['url'] ) || ( ! empty( $hook['form_id'] ) && (int) $hook['form_id'] !== (int) $form_id ) ) {
				continue;
			}

			if ( null === $payload ) {
				$payload = $this->entry( $submission_id, $form_id );
			}

			$response = wp_safe_remote_post(
				$hook['url'],
				array(
					'timeout'     => 8,
					'redirection' => 0,
					'headers'     => array(
						'Content-Type' => 'application/json; charset=utf-8',
						'User-Agent'   => 'CustomContactForms/' . ( defined( 'CCF_VERSION' ) ? CCF_VERSION : '7' ),
					),
					'body'        => wp_json_encode( $payload ),
				)
			);

			$code = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );

			// Zapier answers 410 when the Zap no longer exists.
			if ( 410 === $code ) {
				unset( $hooks[ $id ] );
				$changed = true;
				continue;
			}

			if ( is_wp_error( $response ) || $code < 200 || $code >= 300 ) {
				$message = is_wp_error( $response )
					? $response->get_error_message()
					/* translators: %d: HTTP status code */
					: sprintf( __( 'Zapier answered with HTTP %d.', 'custom-contact-forms' ), $code );

				CCF_Integration_Log::add( 'Zapier', $form_id, $message );
			}
		}

		if ( $changed ) {
			update_option( self::HOOKS_OPTION, $hooks, false );
		}
	}

	/* --------------------------------------------------------------------- */
	/* Integrations page                                                      */
	/* --------------------------------------------------------------------- */

	/**
	 * Zapier card on Forms > Integrations.
	 *
	 * @param array $cards Cards.
	 * @return array
	 */
	public function card( $cards ) {
		$count = count( $this->hooks() );

		$cards['zapier'] = array(
			'id'          => 'zapier',
			'name'        => 'Zapier',
			'description' => __( 'Connect your forms to thousands of apps with the Custom Contact Forms app on Zapier. No webhook URLs to paste.', 'custom-contact-forms' ),
			'color'       => '#ff4f00',
			'icon'        => 'admin-links',
			'tier'        => 'free',
			'category'    => 'automation',
			'status'      => $count
				? array(
					'state' => 'ok',
					/* translators: %d: number of active Zaps */
					'label' => sprintf( _n( '%d active Zap', '%d active Zaps', $count, 'custom-contact-forms' ), $count ),
				)
				: array(
					'state' => 'off',
					'label' => __( 'Not connected yet', 'custom-contact-forms' ),
				),
			'render'      => array( $this, 'render_settings' ),
		);

		return $cards;
	}

	/**
	 * Settings shown in the Zapier drawer.
	 */
	public function render_settings() {
		$key = $this->key();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
		if ( isset( $_GET['ccf-zapier'] ) && 'new-key' === $_GET['ccf-zapier'] ) {
			echo '<div class="notice notice-success inline"><p>' . esc_html__( 'A new key was created. Reconnect your site in Zapier with the new key.', 'custom-contact-forms' ) . '</p></div>';
		}
		?>
		<p><?php esc_html_e( 'When Zapier asks you to connect Custom Contact Forms, enter your site address and this key.', 'custom-contact-forms' ); ?></p>
		<p>
			<label for="ccf-zapier-site"><strong><?php esc_html_e( 'Site address', 'custom-contact-forms' ); ?></strong></label><br>
			<input type="text" id="ccf-zapier-site" class="regular-text code" readonly value="<?php echo esc_attr( home_url( '/' ) ); ?>" onclick="this.select();">
		</p>
		<p>
			<label for="ccf-zapier-key"><strong><?php esc_html_e( 'Zapier key', 'custom-contact-forms' ); ?></strong></label><br>
			<input type="text" id="ccf-zapier-key" class="regular-text code" readonly value="<?php echo esc_attr( $key ); ?>" onclick="this.select();">
		</p>
		<p class="description"><?php esc_html_e( 'Keep this key private. Anyone with it can read your form entries through Zapier.', 'custom-contact-forms' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php echo esc_js( __( 'Create a new key? Your current Zaps will stop until you reconnect them with the new key.', 'custom-contact-forms' ) ); ?>');">
			<input type="hidden" name="action" value="ccf_zapier_new_key">
			<?php wp_nonce_field( 'ccf_zapier_new_key' ); ?>
			<?php submit_button( __( 'Create a new key', 'custom-contact-forms' ), 'secondary', 'submit', false ); ?>
		</form>
		<?php
	}

	/**
	 * @return CCF_Zapier
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
