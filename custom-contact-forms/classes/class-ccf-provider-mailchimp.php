<?php
/**
 * Mailchimp, through the Marketing API v3 with the site owner's API key.
 *
 * @package Custom_Contact_Forms
 * @since   7.18.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CCF_Provider_Mailchimp extends CCF_Email_Provider {

	public function id() {
		return 'mailchimp';
	}

	public function label() {
		return 'Mailchimp';
	}

	public function list_label() {
		return __( 'Audience', 'custom-contact-forms' );
	}

	public function supports_double_optin() {
		return true;
	}

	public function key_help() {
		return __( 'In Mailchimp, go to Profile > Extras > API keys and create a key.', 'custom-contact-forms' );
	}

	/**
	 * API base URL. The data center (such as "us21") is the part of the key
	 * after the dash.
	 *
	 * @return string|WP_Error
	 */
	private function base_url() {
		$key = $this->setting( 'api_key' );
		$pos = strrpos( $key, '-' );
		$dc  = false === $pos ? '' : substr( $key, $pos + 1 );

		if ( ! preg_match( '/^[a-z]+[0-9]+$/', $dc ) ) {
			return new WP_Error( 'ccf_mailchimp_key', __( 'This does not look like a Mailchimp API key. It should end with a dash and a data center, such as -us21.', 'custom-contact-forms' ) );
		}

		return 'https://' . $dc . '.api.mailchimp.com/3.0';
	}

	private function auth_headers() {
		return array(
			'Authorization' => 'Basic ' . base64_encode( 'ccf:' . $this->setting( 'api_key' ) ),
		);
	}

	protected function fetch_lists() {
		$base = $this->base_url();

		if ( is_wp_error( $base ) ) {
			return $base;
		}

		$body = $this->request(
			'GET',
			$base . '/lists?count=200&fields=lists.id,lists.name',
			array( 'headers' => $this->auth_headers() )
		);

		if ( is_wp_error( $body ) ) {
			return $body;
		}

		$lists = array();

		if ( ! empty( $body['lists'] ) && is_array( $body['lists'] ) ) {
			foreach ( $body['lists'] as $list ) {
				if ( isset( $list['id'], $list['name'] ) ) {
					$lists[ (string) $list['id'] ] = (string) $list['name'];
				}
			}
		}

		return $lists;
	}

	public function subscribe( $contact, $list_id, $args ) {
		$base = $this->base_url();

		if ( is_wp_error( $base ) ) {
			return $base;
		}

		$url = $base . '/lists/' . rawurlencode( $list_id ) . '/members/' . md5( strtolower( $contact['email'] ) );

		// status_if_new only applies to new contacts, so someone who
		// unsubscribed earlier is never quietly subscribed again.
		$body = array(
			'email_address' => $contact['email'],
			'status_if_new' => empty( $args['double_optin'] ) ? 'subscribed' : 'pending',
		);

		$merge = array();

		if ( '' !== $contact['first_name'] ) {
			$merge['FNAME'] = $contact['first_name'];
		}

		if ( '' !== $contact['last_name'] ) {
			$merge['LNAME'] = $contact['last_name'];
		}

		if ( $merge ) {
			$body['merge_fields'] = $merge;
		}

		$result = $this->request(
			'PUT',
			$url,
			array(
				'headers' => $this->auth_headers(),
				'body'    => $body,
			)
		);

		// Audiences can delete the default name fields. Try once more
		// without them rather than lose the subscriber.
		if ( is_wp_error( $result ) && $merge ) {
			$data = $result->get_error_data();

			if ( isset( $data['status'] ) && 400 === (int) $data['status'] ) {
				unset( $body['merge_fields'] );

				$result = $this->request(
					'PUT',
					$url,
					array(
						'headers' => $this->auth_headers(),
						'body'    => $body,
					)
				);
			}
		}

		return is_wp_error( $result ) ? $result : true;
	}
}
