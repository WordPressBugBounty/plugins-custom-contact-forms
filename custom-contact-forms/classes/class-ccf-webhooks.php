<?php
/**
 * Webhooks: send each new entry as JSON to one or more URLs.
 *
 * Works with Zapier, Make, n8n, Pabbly and anything else that accepts a
 * webhook. URLs are set per form in the form builder. Entries are only sent
 * to URLs the site owner entered.
 *
 * @package Custom_Contact_Forms
 * @since   7.18.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CCF_Webhooks {

	const FORM_META = 'ccf_form_webhooks';

	const MAX_URLS = 5;

	public function setup() {
		add_action( 'ccf_save_form', array( $this, 'save_form' ), 10, 2 );
		add_filter( 'ccf_form_response', array( $this, 'form_response' ), 10, 2 );
		add_action( 'ccf_successful_submission', array( $this, 'send' ), 30, 2 );
		add_action( 'wp_ajax_ccf_test_webhook', array( $this, 'ajax_test' ) );
	}

	/**
	 * A form's webhook URLs.
	 *
	 * @param int $form_id Form ID.
	 * @return string[]
	 */
	public function urls( $form_id ) {
		$saved = get_post_meta( $form_id, self::FORM_META, true );

		if ( ! is_array( $saved ) || empty( $saved['urls'] ) || ! is_array( $saved['urls'] ) ) {
			return array();
		}

		return $saved['urls'];
	}

	/**
	 * Turn the builder's text box into a clean list of URLs.
	 *
	 * @param string $text One URL per line.
	 * @return string[]
	 */
	public function parse_urls( $text ) {
		$urls = array();

		foreach ( preg_split( '/[\r\n,]+/', (string) $text ) as $line ) {
			$line = trim( $line );

			if ( ! preg_match( '#^https?://\S+$#i', $line ) ) {
				continue;
			}

			$url = esc_url_raw( $line, array( 'http', 'https' ) );

			// Only the format is checked here. Whether the address is safe to
			// call is checked when sending, by wp_safe_remote_post().
			if ( '' !== $url && wp_parse_url( $url, PHP_URL_HOST ) && ! in_array( $url, $urls, true ) ) {
				$urls[] = $url;
			}
		}

		return array_slice( $urls, 0, self::MAX_URLS );
	}

	public function save_form( $form_id, $data ) {
		if ( ! isset( $data['webhooks'] ) || ! is_array( $data['webhooks'] ) ) {
			return;
		}

		$text = isset( $data['webhooks']['urls'] ) ? $data['webhooks']['urls'] : '';

		if ( is_array( $text ) ) {
			$text = implode( "\n", array_map( 'strval', $text ) );
		}

		update_post_meta( $form_id, self::FORM_META, array( 'urls' => $this->parse_urls( $text ) ) );
	}

	public function form_response( $data, $item ) {
		$data['webhooks'] = array( 'urls' => implode( "\n", $this->urls( $item->ID ) ) );

		return $data;
	}

	/**
	 * Build the JSON body for an entry.
	 *
	 * @param int $submission_id Entry ID.
	 * @param int $form_id       Form ID.
	 * @return array
	 */
	public function payload( $submission_id, $form_id ) {
		$data   = get_post_meta( $submission_id, 'ccf_submission_data', true );
		$map    = get_post_meta( $submission_id, 'ccf_submission_data_map', true );
		$fields = get_post_meta( $submission_id, 'ccf_submission_form_fields', true );
		$data   = is_array( $data ) ? $data : array();
		$map    = is_array( $map ) ? $map : array();
		$fields = is_array( $fields ) ? $fields : array();

		$out_fields = array();
		$flat       = array();

		foreach ( $data as $slug => $value ) {
			$type  = isset( $map[ $slug ]['type'] ) ? $map[ $slug ]['type'] : '';
			$label = isset( $fields[ $slug ]['ccf_field_label'] ) && '' !== $fields[ $slug ]['ccf_field_label'] ? wp_strip_all_tags( $fields[ $slug ]['ccf_field_label'] ) : $slug;
			$value = $this->public_value( $value, $type );

			$out_fields[ $slug ] = array(
				'label' => $label,
				'type'  => $type,
				'value' => $value,
			);

			$flat[ $slug ] = $this->flatten( $value, $type );
		}

		$post = get_post( $submission_id );

		$payload = array(
			'form'   => array(
				'id'    => (int) $form_id,
				'title' => get_the_title( $form_id ),
			),
			'entry'  => array(
				'id'      => (int) $submission_id,
				'date'    => $post ? mysql_to_rfc3339( $post->post_date_gmt ) : '',
				'page'    => (string) get_post_meta( $submission_id, 'ccf_submission_form_page', true ),
				'site'    => home_url( '/' ),
				'version' => defined( 'CCF_VERSION' ) ? CCF_VERSION : '',
			),
			'fields' => $out_fields,
			'data'   => $flat,
		);

		/**
		 * Filter the webhook body before it is sent.
		 *
		 * @since 7.18.0
		 * @param array $payload       Body.
		 * @param int   $submission_id Entry ID.
		 * @param int   $form_id       Form ID.
		 */
		return apply_filters( 'ccf_webhook_payload', $payload, $submission_id, $form_id );
	}

	/**
	 * File uploads become their URL; signatures become the image URL.
	 *
	 * @param mixed  $value Stored value.
	 * @param string $type  Field type.
	 * @return mixed
	 */
	private function public_value( $value, $type ) {
		if ( 'file' === $type && is_array( $value ) ) {
			return isset( $value['url'] ) ? esc_url_raw( $value['url'] ) : '';
		}

		if ( 'signature' === $type ) {
			if ( is_array( $value ) && isset( $value['url'] ) ) {
				return esc_url_raw( $value['url'] );
			}

			// The raw drawing is too large to send; it is saved with the entry.
			return is_string( $value ) && 0 === strpos( $value, 'data:' ) ? __( '(signature saved with the entry)', 'custom-contact-forms' ) : $value;
		}

		if ( 'email' === $type && is_array( $value ) ) {
			return isset( $value['email'] ) ? $value['email'] : '';
		}

		return $value;
	}

	/**
	 * A single text value for the "data" section.
	 *
	 * @param mixed  $value Value.
	 * @param string $type  Field type.
	 * @return string
	 */
	private function flatten( $value, $type = '' ) {
		if ( ! is_array( $value ) ) {
			return (string) $value;
		}

		$parts = array();

		foreach ( $value as $part ) {
			if ( is_scalar( $part ) && '' !== (string) $part ) {
				$parts[] = (string) $part;
			}
		}

		// "Ann Lee" reads better than "Ann, Lee"; lists keep commas.
		return implode( 'name' === $type ? ' ' : ', ', $parts );
	}

	/**
	 * POST a body to one URL.
	 *
	 * @param string $url     URL.
	 * @param array  $payload Body.
	 * @return true|WP_Error
	 */
	public function post( $url, $payload ) {
		$response = wp_safe_remote_post(
			$url,
			array(
				'timeout'     => 8,
				'redirection' => 2,
				'headers'     => array(
					'Content-Type' => 'application/json; charset=utf-8',
					'User-Agent'   => 'CustomContactForms/' . ( defined( 'CCF_VERSION' ) ? CCF_VERSION : '7' ),
				),
				'body'        => wp_json_encode( $payload ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );

		if ( $code < 200 || $code >= 300 ) {
			/* translators: %d: HTTP status code */
			return new WP_Error( 'ccf_webhook_http', sprintf( __( 'The URL answered with HTTP %d.', 'custom-contact-forms' ), $code ) );
		}

		return true;
	}

	/**
	 * Send a new entry to the form's URLs.
	 *
	 * @param int $submission_id Entry ID.
	 * @param int $form_id       Form ID.
	 */
	public function send( $submission_id, $form_id ) {
		$urls = $this->urls( $form_id );

		if ( empty( $urls ) ) {
			return;
		}

		$payload = $this->payload( $submission_id, $form_id );
		$sent    = 0;

		foreach ( $urls as $url ) {
			$result = $this->post( $url, $payload );

			if ( is_wp_error( $result ) ) {
				/* translators: 1: host name, 2: error message */
				CCF_Integration_Log::add( __( 'Webhook', 'custom-contact-forms' ), $form_id, sprintf( __( '%1$s: %2$s', 'custom-contact-forms' ), wp_parse_url( $url, PHP_URL_HOST ), $result->get_error_message() ) );
			} else {
				$sent++;
			}
		}

		update_post_meta( $submission_id, 'ccf_webhooks_sent', $sent . '/' . count( $urls ) );
	}

	/**
	 * "Send a test" button in the builder: sends sample data so Zapier and
	 * similar tools can learn the fields before a real entry arrives.
	 */
	public function ajax_test() {
		check_ajax_referer( 'ccf_nonce', 'nonce' );

		$form_id = isset( $_POST['form_id'] ) ? absint( $_POST['form_id'] ) : 0;

		if ( ! $form_id || ! current_user_can( 'edit_post', $form_id ) || 'ccf_form' !== get_post_type( $form_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Save the form first, then send a test.', 'custom-contact-forms' ) ) );
		}

		$urls = $this->parse_urls( isset( $_POST['urls'] ) ? wp_unslash( $_POST['urls'] ) : '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		if ( empty( $urls ) ) {
			wp_send_json_error( array( 'message' => __( 'Enter a webhook URL that starts with https://', 'custom-contact-forms' ) ) );
		}

		$payload = $this->sample_payload( $form_id );
		$errors  = array();

		foreach ( $urls as $url ) {
			$result = $this->post( $url, $payload );

			if ( is_wp_error( $result ) ) {
				$errors[] = wp_parse_url( $url, PHP_URL_HOST ) . ': ' . $result->get_error_message();
			}
		}

		if ( $errors ) {
			wp_send_json_error( array( 'message' => implode( ' ', $errors ) ) );
		}

		wp_send_json_success( array( 'message' => __( 'Test sent.', 'custom-contact-forms' ) ) );
	}

	/**
	 * Sample body with the form's real fields and example values.
	 *
	 * @param int $form_id Form ID.
	 * @return array
	 */
	private function sample_payload( $form_id ) {
		$field_ids = get_post_meta( $form_id, 'ccf_attached_fields', true );
		$fields    = array();
		$flat      = array();
		$skip      = array( 'html', 'section-header', 'recaptcha', 'simple-captcha' );

		foreach ( is_array( $field_ids ) ? $field_ids : array() as $field_id ) {
			$type = get_post_meta( $field_id, 'ccf_field_type', true );

			if ( in_array( $type, $skip, true ) ) {
				continue;
			}

			$slug  = get_post_meta( $field_id, 'ccf_field_slug', true );
			$label = wp_strip_all_tags( (string) get_post_meta( $field_id, 'ccf_field_label', true ) );

			switch ( $type ) {
				case 'email':
					$value = 'test@example.com';
					break;
				case 'name':
					$value = array(
						'first' => 'Test',
						'last'  => 'Person',
					);
					break;
				case 'website':
					$value = 'https://example.com';
					break;
				default:
					$value = __( 'Test value', 'custom-contact-forms' );
			}

			$fields[ $slug ] = array(
				'label' => '' !== $label ? $label : $slug,
				'type'  => $type,
				'value' => $value,
			);

			$flat[ $slug ] = $this->flatten( $value, $type );
		}

		return array(
			'form'   => array(
				'id'    => (int) $form_id,
				'title' => get_the_title( $form_id ),
			),
			'entry'  => array(
				'id'      => 0,
				'date'    => gmdate( 'Y-m-d\TH:i:s\Z' ),
				'page'    => '',
				'site'    => home_url( '/' ),
				'version' => defined( 'CCF_VERSION' ) ? CCF_VERSION : '',
				'test'    => true,
			),
			'fields' => $fields,
			'data'   => $flat,
		);
	}

	/**
	 * @return CCF_Webhooks
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
