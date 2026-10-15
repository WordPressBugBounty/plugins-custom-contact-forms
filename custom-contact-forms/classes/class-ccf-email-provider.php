<?php
/**
 * Base class for an email marketing service (Mailchimp, and services added
 * by Custom Contact Forms Pro).
 *
 * A provider knows how to store its own connection settings, list the
 * audiences (lists, groups, forms) a form can send subscribers to, and add
 * one contact to one of them. Everything else, such as reading the entry,
 * consent and logging, is handled by CCF_Email_Marketing.
 *
 * @package Custom_Contact_Forms
 * @since   7.18.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class CCF_Email_Provider {

	/**
	 * Unique ID, such as "mailchimp". Used in option names and form settings.
	 *
	 * @return string
	 */
	abstract public function id();

	/**
	 * Name shown to people, such as "Mailchimp".
	 *
	 * @return string
	 */
	abstract public function label();

	/**
	 * Fetch the available lists from the service.
	 *
	 * @return array|WP_Error List ID => list name.
	 */
	abstract protected function fetch_lists();

	/**
	 * Add or update one contact.
	 *
	 * @param array  $contact { email, first_name, last_name }.
	 * @param string $list_id List, audience or group ID.
	 * @param array  $args    { double_optin: bool }.
	 * @return true|WP_Error
	 */
	abstract public function subscribe( $contact, $list_id, $args );

	/**
	 * What this service calls a list, for the form builder.
	 *
	 * @return string
	 */
	public function list_label() {
		return __( 'List', 'custom-contact-forms' );
	}

	/**
	 * Whether the form builder should offer a double opt-in choice.
	 *
	 * @return bool
	 */
	public function supports_double_optin() {
		return false;
	}

	/**
	 * Where to find the API key, shown under the key field.
	 *
	 * @return string Plain text help, may contain a URL.
	 */
	public function key_help() {
		return '';
	}

	/**
	 * Settings fields: key => array( label, type ). Most services need only
	 * an API key.
	 *
	 * @return array
	 */
	public function settings_fields() {
		return array(
			'api_key' => array(
				'label' => __( 'API key', 'custom-contact-forms' ),
				'type'  => 'password',
			),
		);
	}

	/**
	 * Option that stores this provider's settings.
	 *
	 * @return string
	 */
	public function option_name() {
		return 'ccf_' . $this->id() . '_settings';
	}

	/**
	 * Saved settings with defaults filled in.
	 *
	 * @return array
	 */
	public function get_settings() {
		$saved    = get_option( $this->option_name(), array() );
		$defaults = array_fill_keys( array_keys( $this->settings_fields() ), '' );

		return wp_parse_args( is_array( $saved ) ? $saved : array(), $defaults );
	}

	/**
	 * Get one setting.
	 *
	 * @param string $key Setting key.
	 * @return string
	 */
	public function setting( $key ) {
		$settings = $this->get_settings();

		return isset( $settings[ $key ] ) ? trim( (string) $settings[ $key ] ) : '';
	}

	/**
	 * Sanitize settings from the settings page.
	 *
	 * @param mixed $input Raw input.
	 * @return array
	 */
	public function sanitize_settings( $input ) {
		// options.php saves every option in the group. When this provider's
		// fields were not on the page, keep what is already stored.
		if ( ! is_array( $input ) ) {
			return $this->get_settings();
		}

		$clean = array();

		foreach ( $this->settings_fields() as $key => $field ) {
			$clean[ $key ] = isset( $input[ $key ] ) ? sanitize_text_field( wp_unslash( $input[ $key ] ) ) : '';
		}

		if ( $clean !== $this->get_settings() ) {
			delete_transient( $this->lists_transient() );
		}

		return $clean;
	}

	/**
	 * Whether enough settings are filled in to try the service.
	 *
	 * @return bool
	 */
	public function is_configured() {
		foreach ( $this->settings_fields() as $key => $field ) {
			if ( empty( $field['optional'] ) && '' === $this->setting( $key ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Transient that caches the list of lists.
	 *
	 * @return string
	 */
	protected function lists_transient() {
		return 'ccf_lists_' . $this->id();
	}

	/**
	 * Lists, cached for ten minutes.
	 *
	 * @param bool $refresh Skip the cache.
	 * @return array|WP_Error
	 */
	public function get_lists( $refresh = false ) {
		if ( ! $this->is_configured() ) {
			return new WP_Error( 'ccf_not_configured', __( 'Not connected yet. Add your API key under Forms > Integrations.', 'custom-contact-forms' ) );
		}

		if ( ! $refresh ) {
			$cached = get_transient( $this->lists_transient() );

			if ( is_array( $cached ) ) {
				return $cached;
			}
		}

		$lists = $this->fetch_lists();

		if ( ! is_wp_error( $lists ) ) {
			set_transient( $this->lists_transient(), $lists, 10 * MINUTE_IN_SECONDS );
		}

		return $lists;
	}

	/**
	 * Send an HTTP request and decode a JSON reply.
	 *
	 * @param string $method HTTP method.
	 * @param string $url    URL.
	 * @param array  $args   wp_remote_request() arguments.
	 * @return array|WP_Error Decoded body (empty array when there is none).
	 */
	protected function request( $method, $url, $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'method'  => $method,
				'timeout' => 15,
				'headers' => array(),
			)
		);

		$args['headers'] = wp_parse_args(
			$args['headers'],
			array(
				'Accept'     => 'application/json',
				'User-Agent' => 'CustomContactForms/' . ( defined( 'CCF_VERSION' ) ? CCF_VERSION : '7' ),
			)
		);

		if ( isset( $args['body'] ) && is_array( $args['body'] ) ) {
			$args['body']                    = wp_json_encode( $args['body'] );
			$args['headers']['Content-Type'] = 'application/json';
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $body ) ) {
			$body = array();
		}

		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error( 'ccf_http_' . $code, $this->error_message( $body, $code ), array( 'status' => $code, 'body' => $body ) );
		}

		return $body;
	}

	/**
	 * Pick a readable error message out of a failed reply.
	 *
	 * @param array $body Decoded body.
	 * @param int   $code HTTP status.
	 * @return string
	 */
	protected function error_message( $body, $code ) {
		foreach ( array( 'detail', 'message', 'error', 'title' ) as $key ) {
			if ( ! empty( $body[ $key ] ) && is_string( $body[ $key ] ) ) {
				return $body[ $key ];
			}
		}

		// Some services return a list of errors instead.
		if ( ! empty( $body['errors'] ) && is_array( $body['errors'] ) ) {
			$first = reset( $body['errors'] );

			if ( is_string( $first ) ) {
				return $first;
			}

			if ( is_array( $first ) ) {
				foreach ( array( 'detail', 'title', 'message' ) as $key ) {
					if ( ! empty( $first[ $key ] ) && is_string( $first[ $key ] ) ) {
						return $first[ $key ];
					}
				}
			}
		}

		/* translators: %d: HTTP status code */
		return sprintf( __( 'The service returned an error (HTTP %d).', 'custom-contact-forms' ), $code );
	}
}
