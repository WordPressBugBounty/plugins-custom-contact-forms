<?php
/**
 * Contact Form 7 importer.
 *
 * CF7 stores a form as a text template of shortcode-like tags, e.g.
 * [text* your-name placeholder "Name"]. This class parses those tags and maps
 * them onto CCF field definitions.
 *
 * @since 7.15.0
 * @package Custom Contact Forms
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CCF_Importer_CF7 extends CCF_Importer {

	/**
	 * CF7 tag type => CCF field type.
	 *
	 * Tags absent from this map are reported as unconvertible rather than
	 * guessed at. Notably quiz, acceptance and CF7's own captcha have no CCF
	 * equivalent that would behave the same way.
	 *
	 * @since 7.15.0
	 * @var array
	 */
	protected $type_map = array(
		'text'      => 'single-line-text',
		'email'     => 'email',
		'url'       => 'website',
		'tel'       => 'phone',
		'number'    => 'single-line-text',
		'range'     => 'single-line-text',
		'date'      => 'date',
		'textarea'  => 'paragraph-text',
		'select'    => 'dropdown',
		'radio'     => 'radio',
		'checkbox'  => 'checkboxes',
		'file'      => 'file',
		'hidden'    => 'hidden',
	);

	/**
	 * Importer key.
	 *
	 * @since 7.15.0
	 * @return string
	 */
	public function get_id() {
		return 'cf7';
	}

	/**
	 * Source plugin name.
	 *
	 * @since 7.15.0
	 * @return string
	 */
	public function get_label() {
		return __( 'Contact Form 7', 'custom-contact-forms' );
	}

	/**
	 * Whether CF7 forms exist on this site.
	 *
	 * Checks for the post type rather than the class, so forms can still be
	 * imported after CF7 has been deactivated — which is exactly when someone
	 * is most likely to be migrating.
	 *
	 * @since 7.15.0
	 * @return bool
	 */
	public function is_available() {
		$found = get_posts(
			array(
				'post_type'      => 'wpcf7_contact_form',
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);

		return ! empty( $found );
	}

	/**
	 * List CF7 forms.
	 *
	 * @since 7.15.0
	 * @return array
	 */
	public function get_source_forms() {
		$posts = get_posts(
			array(
				'post_type'      => 'wpcf7_contact_form',
				'post_status'    => 'any',
				'posts_per_page' => 200,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'no_found_rows'  => true,
			)
		);

		$forms = array();

		foreach ( $posts as $post ) {
			$forms[] = array(
				'id'    => $post->ID,
				'title' => '' !== $post->post_title ? $post->post_title : sprintf( __( 'Form #%d', 'custom-contact-forms' ), $post->ID ),
			);
		}

		return $forms;
	}

	/**
	 * Convert a CF7 form into a neutral definition.
	 *
	 * @since 7.15.0
	 * @param mixed $source_id CF7 post ID.
	 * @return array|WP_Error
	 */
	public function convert_form( $source_id ) {
		$post = get_post( (int) $source_id );

		if ( ! $post || 'wpcf7_contact_form' !== $post->post_type ) {
			return new WP_Error( 'ccf_import_missing', esc_html__( 'That Contact Form 7 form could not be found.', 'custom-contact-forms' ) );
		}

		$template = get_post_meta( $post->ID, '_form', true );

		if ( '' === $template || ! is_string( $template ) ) {
			return new WP_Error( 'ccf_import_empty', esc_html__( 'That Contact Form 7 form has no form template.', 'custom-contact-forms' ) );
		}

		$parsed = $this->parse_template( $template );

		$definition = array(
			'title'         => '' !== $post->post_title ? $post->post_title : sprintf( __( 'Form #%d', 'custom-contact-forms' ), $post->ID ),
			'fields'        => $parsed['fields'],
			'skipped'       => $parsed['skipped'],
			'notifications' => $this->convert_mail( $post->ID, $parsed['fields'] ),
			'button_text'   => $parsed['button_text'],
		);

		return $definition;
	}

	/**
	 * Parse a CF7 form template into field definitions.
	 *
	 * @since 7.15.0
	 * @param string $template CF7 form template.
	 * @return array Fields, skipped notes and button text.
	 */
	protected function parse_template( $template ) {
		$fields      = array();
		$skipped     = array();
		$button_text = __( 'Submit', 'custom-contact-forms' );
		$taken       = array();

		// Matches [type name options...] and [type* name options...].
		if ( ! preg_match_all( '/\[([a-zA-Z0-9_]+)(\*?)([^\]]*)\]/', $template, $matches, PREG_SET_ORDER ) ) {
			return array(
				'fields'      => $fields,
				'skipped'     => $skipped,
				'button_text' => $button_text,
			);
		}

		foreach ( $matches as $match ) {
			$tag      = strtolower( $match[1] );
			$required = '*' === $match[2];
			$rest     = trim( $match[3] );

			if ( 'submit' === $tag ) {
				$label = $this->extract_quoted( $rest );
				if ( '' !== $label ) {
					$button_text = $label;
				}
				continue;
			}

			// CF7's own captcha and quiz have no equivalent that would behave
			// the same way; recaptcha maps to CCF's own reCAPTCHA field.
			if ( in_array( $tag, array( 'captchac', 'captchar', 'quiz' ), true ) ) {
				$skipped[] = sprintf(
					/* translators: %s: Contact Form 7 tag name. */
					esc_html__( 'Not converted: [%s]. Custom Contact Forms offers Cloudflare Turnstile and reCAPTCHA instead.', 'custom-contact-forms' ),
					$tag
				);
				continue;
			}

			if ( 'recaptcha' === $tag ) {
				$fields[] = array(
					'type'  => 'recaptcha',
					'slug'  => $this->unique_slug( 'recaptcha', $taken ),
					'label' => __( 'reCAPTCHA', 'custom-contact-forms' ),
				);
				continue;
			}

			if ( 'acceptance' === $tag ) {
				$name  = $this->extract_name( $rest );
				$label = $this->extract_quoted( $rest );

				$fields[] = array(
					'type'     => 'checkboxes',
					'slug'     => $this->unique_slug( '' !== $name ? $name : 'acceptance', $taken ),
					'label'    => '' !== $label ? $label : __( 'Acceptance', 'custom-contact-forms' ),
					'required' => $required,
					'choices'  => array(
						array( 'label' => '' !== $label ? $label : __( 'I agree', 'custom-contact-forms' ) ),
					),
				);
				continue;
			}

			if ( ! isset( $this->type_map[ $tag ] ) ) {
				$skipped[] = sprintf(
					/* translators: %s: Contact Form 7 tag name. */
					esc_html__( 'Not converted: [%s]', 'custom-contact-forms' ),
					$tag
				);
				continue;
			}

			$name = $this->extract_name( $rest );

			if ( '' === $name ) {
				continue;
			}

			$field = array(
				'type'     => $this->type_map[ $tag ],
				'slug'     => $this->unique_slug( $name, $taken ),
				'label'    => $this->humanize( $name ),
				'required' => $required,
			);

			$placeholder = $this->extract_placeholder( $rest );

			if ( '' !== $placeholder ) {
				$field['placeholder'] = $placeholder;
			}

			if ( in_array( $tag, array( 'select', 'radio', 'checkbox' ), true ) ) {
				$choices = $this->extract_choices( $rest );

				if ( empty( $choices ) ) {
					$skipped[] = sprintf(
						/* translators: %s: field name. */
						esc_html__( 'Imported without options: %s', 'custom-contact-forms' ),
						$this->humanize( $name )
					);
				}

				$field['choices'] = $choices;
			}

			if ( 'hidden' === $tag ) {
				$default = $this->extract_quoted( $rest );
				if ( '' !== $default ) {
					$field['value'] = $default;
				}
			}

			$fields[] = $field;
		}

		return array(
			'fields'      => $fields,
			'skipped'     => $skipped,
			'button_text' => $button_text,
		);
	}

	/**
	 * Convert CF7's mail settings into a CCF notification.
	 *
	 * CF7 mail templates use [field-name] placeholders. CCF uses the same
	 * bracket convention for field slugs, and slugs are preserved on import,
	 * so recipient and subject usually carry over intact. The body is left to
	 * CCF's default rather than translated, since CF7 bodies routinely contain
	 * special mail tags with no equivalent.
	 *
	 * @since 7.15.0
	 * @param int   $source_id CF7 post ID.
	 * @param array $fields    Converted fields.
	 * @return array
	 */
	protected function convert_mail( $source_id, $fields ) {
		$mail = get_post_meta( $source_id, '_mail', true );

		if ( ! is_array( $mail ) || empty( $mail['recipient'] ) ) {
			return array();
		}

		$recipient = trim( $mail['recipient'] );

		// A recipient containing a CF7 mail tag can't be resolved here; fall
		// back to the site admin rather than writing an address that will
		// never deliver.
		if ( false !== strpos( $recipient, '[' ) ) {
			$recipient = get_option( 'admin_email' );
		}

		if ( ! is_email( $recipient ) ) {
			return array();
		}

		$subject = isset( $mail['subject'] ) ? trim( $mail['subject'] ) : '';

		if ( false !== strpos( $subject, '[' ) || '' === $subject ) {
			$subject = '';
		}

		return array(
			array(
				'title'         => __( 'Imported notification', 'custom-contact-forms' ),
				'active'        => true,
				'addresses'     => array(
					array(
						'type'  => 'custom',
						'email' => sanitize_email( $recipient ),
					),
				),
				'subjectType'   => '' !== $subject ? 'custom' : 'default',
				'subject'       => sanitize_text_field( $subject ),
				'fromType'      => 'default',
				'fromNameType'  => 'default',
				'fromAddress'   => '',
				'fromName'      => '',
				'content'       => '',
			),
		);
	}

	/**
	 * Read the field name from a CF7 tag's option string.
	 *
	 * @since 7.15.0
	 * @param string $rest Tag options.
	 * @return string
	 */
	protected function extract_name( $rest ) {
		if ( preg_match( '/^\s*([a-zA-Z0-9_\-]+)/', $rest, $m ) ) {
			return $m[1];
		}

		return '';
	}

	/**
	 * Read a placeholder value from a CF7 tag's option string.
	 *
	 * @since 7.15.0
	 * @param string $rest Tag options.
	 * @return string
	 */
	protected function extract_placeholder( $rest ) {
		if ( false === stripos( $rest, 'placeholder' ) ) {
			return '';
		}

		if ( preg_match( '/placeholder\s+"([^"]*)"/i', $rest, $m ) ) {
			return $m[1];
		}

		return $this->extract_quoted( $rest );
	}

	/**
	 * Read the first double-quoted value from a CF7 tag's option string.
	 *
	 * @since 7.15.0
	 * @param string $rest Tag options.
	 * @return string
	 */
	protected function extract_quoted( $rest ) {
		if ( preg_match( '/"([^"]*)"/', $rest, $m ) ) {
			return $m[1];
		}

		return '';
	}

	/**
	 * Read choice options from a select, radio or checkbox tag.
	 *
	 * CF7 writes each option as a quoted string after the field name and any
	 * keyword options, so quoted values that are themselves option keywords
	 * are excluded.
	 *
	 * @since 7.15.0
	 * @param string $rest Tag options.
	 * @return array
	 */
	protected function extract_choices( $rest ) {
		if ( ! preg_match_all( '/"([^"]*)"/', $rest, $m ) ) {
			return array();
		}

		$choices  = array();
		$reserved = array( 'placeholder', 'class', 'id', 'first_as_label', 'use_label_element', 'default' );

		foreach ( $m[1] as $value ) {
			$value = trim( $value );

			if ( '' === $value || in_array( strtolower( $value ), $reserved, true ) ) {
				continue;
			}

			$choices[] = array(
				'label' => $value,
				'value' => $value,
			);
		}

		return $choices;
	}

	/**
	 * Turn a CF7 field name into a readable label.
	 *
	 * @since 7.15.0
	 * @param string $name Field name, e.g. "your-name".
	 * @return string
	 */
	protected function humanize( $name ) {
		$label = str_replace( array( '-', '_' ), ' ', $name );
		$label = preg_replace( '/^your\s+/i', '', $label );
		$label = trim( $label );

		if ( '' === $label ) {
			return __( 'Field', 'custom-contact-forms' );
		}

		return ucfirst( $label );
	}
}
