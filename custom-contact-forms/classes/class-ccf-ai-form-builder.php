<?php
/**
 * Build a form from a plain-language description, using WordPress 7.0's
 * built-in AI Client.
 *
 * Deliberately uses core's client rather than talking to a provider directly:
 * the site owner has already supplied their key under Settings > Connectors,
 * so there is no key to ask for, nothing to store, and no provider lock-in.
 * On anything older than WordPress 7.0 the feature simply does not appear.
 *
 * @package Custom_Contact_Forms
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CCF_AI_Form_Builder {

	/**
	 * Field types the free plugin can actually render.
	 *
	 * Generating a type that is not in this list would insert a field whose
	 * validator does not exist, and CCF's submit handler constructs one per
	 * field type with no guard — the form would silently stop submitting.
	 *
	 * @return array
	 */
	public static function supported_types() {
		return array(
			'single-line-text',
			'paragraph-text',
			'email',
			'name',
			'phone',
			'address',
			'website',
			'date',
			'dropdown',
			'checkboxes',
			'radio',
			'file',
			'section-header',
			'html',
			'hidden',
			'recaptcha',
			'simple-captcha',
		);
	}

	/**
	 * Capabilities that only exist in Pro, keyed by the token the model is
	 * told to use. Surfaced next to the finished form rather than inserted
	 * into it.
	 *
	 * @return array
	 */
	public static function pro_capabilities() {
		return array(
			'payment'   => __( 'Take card or PayPal payments on the form', 'custom-contact-forms' ),
			'signature' => __( 'Collect a drawn signature', 'custom-contact-forms' ),
			'product'   => __( 'Sell products with prices and quantities', 'custom-contact-forms' ),
			'agreement' => __( 'Record agreement to terms, with a versioned record', 'custom-contact-forms' ),
			'multistep' => __( 'Split the form over several pages', 'custom-contact-forms' ),
			'rating'    => __( 'Star ratings and survey grids', 'custom-contact-forms' ),
			'upload'    => __( 'Large or restricted file uploads', 'custom-contact-forms' ),
		);
	}

	/**
	 * Whether the feature can run at all right now.
	 *
	 * Feature detection is free — is_supported_for_text_generation() answers
	 * from the registry without contacting a provider, so this is safe to call
	 * on every admin page load.
	 *
	 * @return bool
	 */
	public static function is_available() {
		$check = self::diagnose();

		return 'ok' === $check['status'];
	}

	/**
	 * Work out precisely why the feature is or is not usable.
	 *
	 * Separated from is_available() so the settings screen can say which check
	 * failed instead of offering one generic "connect a provider" message for
	 * four different causes.
	 *
	 * @return array{status:string,detail:string}
	 */
	public static function diagnose() {
		if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
			return array( 'status' => 'no_client', 'detail' => 'wp_ai_client_prompt() not defined' );
		}

		// prompt_ai is WordPress 7.0's capability for AI access. It is not
		// granted to any role by default on every install, so an
		// administrator can end up locked out of their own site's feature.
		// Treat manage_options as sufficient — an administrator can grant
		// themselves any capability anyway — while still honouring prompt_ai
		// where a site has deliberately handed it to other roles.
		if ( ! current_user_can( 'prompt_ai' ) && ! current_user_can( 'manage_options' ) ) {
			return array( 'status' => 'no_cap', 'detail' => 'neither prompt_ai nor manage_options' );
		}

		// Sites can switch AI off entirely. Core's builder short-circuits every
		// is_supported_* and generate_* call when this is false, so a connected
		// provider still reports unsupported.
		if ( function_exists( 'wp_supports_ai' ) && ! wp_supports_ai() ) {
			return array( 'status' => 'ai_off', 'detail' => 'wp_supports_ai() is false' );
		}

		$builder = wp_ai_client_prompt( 'test' );

		if ( is_wp_error( $builder ) ) {
			return array( 'status' => 'builder_error', 'detail' => $builder->get_error_message() );
		}

		// is_callable, not method_exists: core routes is_supported_* and
		// generate_* through __call(), which method_exists() cannot see. That
		// distinction made a perfectly working setup look unsupported.
		if ( ! is_object( $builder ) || ! is_callable( array( $builder, 'is_supported_for_text_generation' ) ) ) {
			return array( 'status' => 'no_method', 'detail' => 'is_supported_for_text_generation() not callable on ' . ( is_object( $builder ) ? get_class( $builder ) : gettype( $builder ) ) );
		}

		if ( ! $builder->is_supported_for_text_generation() ) {
			return array( 'status' => 'unsupported', 'detail' => 'no configured model reports text generation' );
		}

		return array( 'status' => 'ok', 'detail' => '' );
	}

	/**
	 * The instruction sent to the model.
	 *
	 * @param string $description What the user asked for.
	 * @return string
	 */
	public static function build_prompt( $description ) {
		$types = implode( ', ', self::supported_types() );
		$pro   = implode( ', ', array_keys( self::pro_capabilities() ) );

		return "You design web forms. Return ONLY a JSON object, no markdown fences and no commentary.\n\n" .
			"Shape:\n" .
			'{"title":"Form title","button":"Submit","fields":[{"type":"...","label":"...","required":true,"placeholder":"","description":"","choices":["a","b"]}],"pro":["payment"]}' . "\n\n" .
			"Rules:\n" .
			"- \"type\" MUST be one of: {$types}\n" .
			"- Use \"name\" for a person's name and \"email\" for an email address rather than single-line-text.\n" .
			"- \"choices\" only for dropdown, checkboxes and radio. Omit it otherwise.\n" .
			"- \"required\" is a boolean.\n" .
			"- Keep it to what was asked for. Do not invent extra fields.\n" .
			"- If the form needs something outside that list, do not invent a type. Instead add the matching token to \"pro\", chosen from: {$pro}\n" .
			"- \"button\" is the submit button wording, a couple of words suited to the form — \"Register\", \"Send enquiry\", \"Book now\".\n" .
			"- Labels in the same language as the description.\n\n" .
			'Description: ' . $description;
	}

	/**
	 * Ask the model for a form specification.
	 *
	 * @param string $description Plain-language description.
	 * @return array|WP_Error
	 */
	public static function generate( $description ) {
		if ( ! self::is_available() ) {
			return new WP_Error( 'ccf_ai_unavailable', __( 'AI form building is not available on this site.', 'custom-contact-forms' ) );
		}

		$builder = wp_ai_client_prompt( self::build_prompt( $description ) );

		if ( is_wp_error( $builder ) ) {
			return $builder;
		}

		$text = $builder->generate_text();

		if ( is_wp_error( $text ) ) {
			return $text;
		}

		return self::parse( $text );
	}

	/**
	 * Turn the model's reply into a field list we are willing to create.
	 *
	 * Everything here is treated as untrusted. A model can return prose around
	 * the JSON, invent a field type, or omit a required key, and none of that
	 * should produce a broken form.
	 *
	 * @param string $text Raw model output.
	 * @return array|WP_Error
	 */
	public static function parse( $text ) {
		$text = trim( (string) $text );

		// Strip markdown fences if the model added them despite instructions.
		$text = preg_replace( '/^```(?:json)?\s*|\s*```$/m', '', $text );

		// Take the outermost JSON object, ignoring any preamble.
		$start = strpos( $text, '{' );
		$end   = strrpos( $text, '}' );

		if ( false === $start || false === $end || $end <= $start ) {
			return new WP_Error( 'ccf_ai_unparseable', __( 'The response could not be read as a form.', 'custom-contact-forms' ) );
		}

		$data = json_decode( substr( $text, $start, $end - $start + 1 ), true );

		if ( ! is_array( $data ) || empty( $data['fields'] ) || ! is_array( $data['fields'] ) ) {
			return new WP_Error( 'ccf_ai_unparseable', __( 'The response could not be read as a form.', 'custom-contact-forms' ) );
		}

		$supported = self::supported_types();
		$fields    = array();
		$ord       = 0;
		$skipped   = array();

		foreach ( $data['fields'] as $raw ) {
			if ( ! is_array( $raw ) || empty( $raw['type'] ) ) {
				continue;
			}

			$type = sanitize_key( $raw['type'] );

			if ( ! in_array( $type, $supported, true ) ) {
				$skipped[] = $type;
				continue;
			}

			$field = array(
				'type'     => $type,
				'slug'     => $type . '-' . ( ++$ord ),
				'label'    => isset( $raw['label'] ) ? sanitize_text_field( $raw['label'] ) : '',
				'required' => ! empty( $raw['required'] ),
			);

			if ( ! empty( $raw['placeholder'] ) ) {
				$field['placeholder'] = sanitize_text_field( $raw['placeholder'] );
			}

			if ( ! empty( $raw['description'] ) ) {
				$field['description'] = sanitize_text_field( $raw['description'] );
			}

			if ( in_array( $type, array( 'dropdown', 'checkboxes', 'radio' ), true ) && ! empty( $raw['choices'] ) && is_array( $raw['choices'] ) ) {
				$choices = array();

				foreach ( $raw['choices'] as $choice ) {
					$label = sanitize_text_field( is_array( $choice ) && isset( $choice['label'] ) ? $choice['label'] : $choice );

					if ( '' !== $label ) {
						$choices[] = array(
							'label'    => $label,
							'value'    => $label,
							'selected' => false,
						);
					}
				}

				if ( ! empty( $choices ) ) {
					$field['choices'] = $choices;
				}
			}

			$fields[] = $field;
		}

		if ( empty( $fields ) ) {
			return new WP_Error( 'ccf_ai_no_fields', __( 'No usable fields came back. Try describing the form differently.', 'custom-contact-forms' ) );
		}

		// Only report Pro capabilities the model was actually offered.
		$pro       = array();
		$available = self::pro_capabilities();

		if ( ! empty( $data['pro'] ) && is_array( $data['pro'] ) ) {
			foreach ( $data['pro'] as $token ) {
				$token = sanitize_key( $token );

				if ( isset( $available[ $token ] ) ) {
					$pro[ $token ] = $available[ $token ];
				}
			}
		}

		$button = isset( $data['button'] ) ? sanitize_text_field( $data['button'] ) : '';

		return array(
			'title'   => isset( $data['title'] ) ? sanitize_text_field( $data['title'] ) : __( 'Untitled form', 'custom-contact-forms' ),
			'button'  => '' !== $button ? $button : __( 'Submit', 'custom-contact-forms' ),
			'fields'  => $fields,
			'pro'     => $pro,
			'skipped' => array_values( array_unique( $skipped ) ),
		);
	}
}
