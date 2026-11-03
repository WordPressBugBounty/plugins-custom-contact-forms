<?php
/**
 * Fluent Forms importer.
 *
 * Fluent Forms keeps each form as one row in its fluentform_forms table, with
 * the fields stored as JSON in the form_fields column: a "fields" list plus a
 * "submitButton". Notifications and the confirmation message are JSON rows in
 * fluentform_form_meta, under the "notifications" and "formSettings" keys.
 *
 * Column layouts ("container" elements) hold other fields; those are
 * flattened so the fields inside still import in order. Everything read here
 * is treated as untrusted: element types are checked against a known map and
 * anything unrecognised is reported rather than guessed at.
 *
 * @since 7.20.0
 * @package Custom Contact Forms
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CCF_Importer_Fluent_Forms extends CCF_Importer {

	/**
	 * Fluent Forms element => CCF field type.
	 *
	 * Payment, rating, step and other paid-tier elements are deliberately
	 * absent and are reported instead.
	 *
	 * @since 7.20.0
	 * @var array
	 */
	protected $type_map = array(
		'input_name'          => 'name',
		'input_email'         => 'email',
		'input_text'          => 'single-line-text',
		'input_mask'          => 'single-line-text',
		'input_number'        => 'single-line-text',
		'select_country'      => 'single-line-text',
		'textarea'            => 'paragraph-text',
		'phone'               => 'phone',
		'input_url'           => 'website',
		'input_date'          => 'date',
		'address'             => 'address',
		'select'              => 'dropdown',
		'input_radio'         => 'radio',
		'input_checkbox'      => 'checkboxes',
		'gdpr_agreement'      => 'checkboxes',
		'terms_and_condition' => 'checkboxes',
		'input_file'          => 'file',
		'input_image'         => 'file',
		'input_hidden'        => 'hidden',
		'custom_html'         => 'html',
		'section_break'       => 'section-header',
		'recaptcha'           => 'recaptcha',
	);

	/**
	 * Importer key.
	 *
	 * @since 7.20.0
	 * @return string
	 */
	public function get_id() {
		return 'fluent-forms';
	}

	/**
	 * Source plugin name.
	 *
	 * @since 7.20.0
	 * @return string
	 */
	public function get_label() {
		return __( 'Fluent Forms', 'custom-contact-forms' );
	}

	/**
	 * Full table name.
	 *
	 * @since 7.20.0
	 * @param string $name Table name without prefix.
	 * @return string
	 */
	protected function table( $name ) {
		global $wpdb;
		return $wpdb->prefix . $name;
	}

	/**
	 * Whether a Fluent Forms table exists.
	 *
	 * @since 7.20.0
	 * @param string $name Table name without prefix.
	 * @return bool
	 */
	protected function table_exists( $name ) {
		global $wpdb;
		$table = $this->table( $name );
		return $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
	}

	/**
	 * Whether Fluent Forms forms exist on this site.
	 *
	 * Checks the table rather than the plugin, so forms can still be imported
	 * after Fluent Forms has been deactivated.
	 *
	 * @since 7.20.0
	 * @return bool
	 */
	public function is_available() {
		global $wpdb;

		if ( ! $this->table_exists( 'fluentform_forms' ) ) {
			return false;
		}

		return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . $this->table( 'fluentform_forms' ) ) > 0;
	}

	/**
	 * List Fluent Forms forms.
	 *
	 * @since 7.20.0
	 * @return array
	 */
	public function get_source_forms() {
		global $wpdb;

		if ( ! $this->is_available() ) {
			return array();
		}

		$rows  = $wpdb->get_results( 'SELECT id, title FROM ' . $this->table( 'fluentform_forms' ) . ' ORDER BY title ASC LIMIT 200', ARRAY_A );
		$forms = array();

		foreach ( (array) $rows as $row ) {
			$id = (int) $row['id'];

			$forms[] = array(
				'id'    => $id,
				'title' => '' !== (string) $row['title'] ? (string) $row['title'] : sprintf( __( 'Form #%d', 'custom-contact-forms' ), $id ),
			);
		}

		return $forms;
	}

	/**
	 * Convert a Fluent Forms form into a neutral definition.
	 *
	 * @since 7.20.0
	 * @param mixed $source_id Fluent Forms form ID.
	 * @return array|WP_Error
	 */
	public function convert_form( $source_id ) {
		global $wpdb;

		$form_id = absint( $source_id );

		if ( ! $form_id || ! $this->table_exists( 'fluentform_forms' ) ) {
			return new WP_Error( 'ccf_import_missing', esc_html__( 'That Fluent Forms form could not be found.', 'custom-contact-forms' ) );
		}

		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . $this->table( 'fluentform_forms' ) . ' WHERE id = %d', $form_id ), ARRAY_A );

		if ( empty( $row ) ) {
			return new WP_Error( 'ccf_import_missing', esc_html__( 'That Fluent Forms form could not be found.', 'custom-contact-forms' ) );
		}

		$data = json_decode( (string) $row['form_fields'], true );

		if ( ! is_array( $data ) || empty( $data['fields'] ) || ! is_array( $data['fields'] ) ) {
			return new WP_Error( 'ccf_import_empty', esc_html__( 'That Fluent Forms form has no readable field data.', 'custom-contact-forms' ) );
		}

		$parsed = $this->parse_fields( $this->flatten( $data['fields'] ) );

		$button_text = '';
		if ( isset( $data['submitButton']['settings']['button_ui']['text'] ) ) {
			$button_text = trim( wp_strip_all_tags( (string) $data['submitButton']['settings']['button_ui']['text'] ) );
		}

		$meta = $this->read_meta( $form_id );

		return array(
			'title'              => '' !== (string) $row['title'] ? (string) $row['title'] : sprintf( __( 'Form #%d', 'custom-contact-forms' ), $form_id ),
			'fields'             => $parsed['fields'],
			'skipped'            => $parsed['skipped'],
			'notifications'      => $this->convert_notifications( isset( $meta['notifications'] ) ? $meta['notifications'] : array() ),
			'button_text'        => '' !== $button_text ? $button_text : __( 'Submit', 'custom-contact-forms' ),
			'completion_message' => $this->confirmation_message( isset( $meta['formSettings'] ) ? $meta['formSettings'] : array() ),
		);
	}

	/**
	 * Flatten column containers into one list, keeping display order.
	 *
	 * @since 7.20.0
	 * @param array $fields Fluent Forms fields list.
	 * @param int   $depth  Nesting depth guard.
	 * @return array
	 */
	protected function flatten( $fields, $depth = 0 ) {
		$out = array();

		foreach ( (array) $fields as $field ) {
			if ( ! is_array( $field ) ) {
				continue;
			}

			if ( isset( $field['element'] ) && 'container' === $field['element'] && $depth < 5 ) {
				if ( ! empty( $field['columns'] ) && is_array( $field['columns'] ) ) {
					foreach ( $field['columns'] as $column ) {
						if ( is_array( $column ) && ! empty( $column['fields'] ) ) {
							$out = array_merge( $out, $this->flatten( $column['fields'], $depth + 1 ) );
						}
					}
				}
				continue;
			}

			$out[] = $field;
		}

		return $out;
	}

	/**
	 * A field's label.
	 *
	 * @since 7.20.0
	 * @param array $field Fluent Forms field.
	 * @return string
	 */
	protected function label( $field ) {
		foreach ( array( 'label', 'admin_field_label' ) as $key ) {
			if ( ! empty( $field['settings'][ $key ] ) ) {
				return trim( wp_strip_all_tags( (string) $field['settings'][ $key ] ) );
			}
		}
		return '';
	}

	/**
	 * Whether a field (or, for name and address, any visible part) is required.
	 *
	 * @since 7.20.0
	 * @param array $field Fluent Forms field.
	 * @return bool
	 */
	protected function is_required( $field ) {
		if ( ! empty( $field['settings']['validation_rules']['required']['value'] ) ) {
			return true;
		}

		if ( ! empty( $field['fields'] ) && is_array( $field['fields'] ) ) {
			foreach ( $field['fields'] as $part ) {
				if ( ! is_array( $part ) ) {
					continue;
				}
				$visible = ! isset( $part['settings']['visible'] ) || ! empty( $part['settings']['visible'] );
				if ( $visible && ! empty( $part['settings']['validation_rules']['required']['value'] ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Convert Fluent Forms fields into CCF field definitions.
	 *
	 * @since 7.20.0
	 * @param array $fields Flat list of Fluent Forms fields.
	 * @return array Fields and skipped notes.
	 */
	protected function parse_fields( $fields ) {
		$out     = array();
		$skipped = array();
		$taken   = array();

		foreach ( $fields as $field ) {
			$element = isset( $field['element'] ) ? (string) $field['element'] : '';
			$label   = $this->label( $field );

			if ( '' === $element ) {
				continue;
			}

			if ( 'form_step' === $element ) {
				$skipped[] = esc_html__( 'Form steps were not imported. Multi-step forms are a Pro feature.', 'custom-contact-forms' );
				continue;
			}

			if ( in_array( $element, array( 'hcaptcha', 'turnstile' ), true ) ) {
				$skipped[] = esc_html__( 'Spam protection field not imported. Turn on hCaptcha or Cloudflare Turnstile for the form in Custom Contact Forms instead.', 'custom-contact-forms' );
				continue;
			}

			if ( ! isset( $this->type_map[ $element ] ) ) {
				$skipped[] = sprintf(
					/* translators: 1: field label, 2: Fluent Forms element type. */
					esc_html__( 'Not converted: %1$s (%2$s)', 'custom-contact-forms' ),
					'' !== $label ? $label : esc_html__( 'untitled field', 'custom-contact-forms' ),
					$element
				);
				continue;
			}

			$type = $this->type_map[ $element ];

			if ( '' === $label ) {
				$defaults = array(
					'input_name'          => __( 'Name', 'custom-contact-forms' ),
					'address'             => __( 'Address', 'custom-contact-forms' ),
					'gdpr_agreement'      => __( 'Consent', 'custom-contact-forms' ),
					'terms_and_condition' => __( 'Terms and Conditions', 'custom-contact-forms' ),
				);
				$label = isset( $defaults[ $element ] ) ? $defaults[ $element ] : '';
			}

			// A multiple-choice dropdown collects several answers.
			if ( 'select' === $element && ! empty( $field['attributes']['multiple'] ) ) {
				$type      = 'checkboxes';
				$skipped[] = sprintf(
					/* translators: %s: field label. */
					esc_html__( 'Multi-select dropdown imported as checkboxes: %s', 'custom-contact-forms' ),
					'' !== $label ? $label : $element
				);
			}

			$definition = array(
				'type'     => $type,
				'slug'     => $this->unique_slug( '' !== $label ? $label : $element, $taken ),
				'label'    => '' !== $label ? $label : ucfirst( str_replace( '_', ' ', $element ) ),
				'required' => $this->is_required( $field ),
			);

			if ( ! empty( $field['attributes']['placeholder'] ) ) {
				$definition['placeholder'] = (string) $field['attributes']['placeholder'];
			}

			if ( ! empty( $field['settings']['help_message'] ) ) {
				$definition['description'] = trim( wp_strip_all_tags( (string) $field['settings']['help_message'] ) );
			}

			if ( 'section_break' === $element && ! empty( $field['settings']['description'] ) ) {
				$definition['description'] = trim( wp_strip_all_tags( (string) $field['settings']['description'] ) );
			}

			if ( 'custom_html' === $element ) {
				$definition['value'] = wp_kses_post( isset( $field['settings']['html_codes'] ) ? (string) $field['settings']['html_codes'] : '' );
			}

			if ( 'input_hidden' === $element && isset( $field['attributes']['value'] ) && is_scalar( $field['attributes']['value'] ) ) {
				$definition['value'] = (string) $field['attributes']['value'];
			}

			if ( in_array( $element, array( 'gdpr_agreement', 'terms_and_condition' ), true ) ) {
				$text = '';
				if ( ! empty( $field['settings']['tnc_html'] ) ) {
					$text = trim( wp_strip_all_tags( (string) $field['settings']['tnc_html'] ) );
				}
				$text = '' !== $text ? $text : __( 'I agree', 'custom-contact-forms' );

				$definition['choices'] = array(
					array(
						'label'    => $text,
						'value'    => 'agreed',
						'selected' => false,
					),
				);
			} elseif ( in_array( $type, array( 'dropdown', 'radio', 'checkboxes' ), true ) ) {
				$choices = $this->convert_options( $field );

				if ( empty( $choices ) ) {
					$skipped[] = sprintf(
						/* translators: %s: field label. */
						esc_html__( 'Imported without options: %s', 'custom-contact-forms' ),
						$definition['label']
					);
				}

				$definition['choices'] = $choices;
			}

			$out[] = $definition;
		}

		return array(
			'fields'  => $out,
			'skipped' => $skipped,
		);
	}

	/**
	 * Convert a choice field's options into CCF choices.
	 *
	 * Current Fluent Forms versions store "advanced_options" as a list of
	 * label/value pairs. Older versions stored a value => label map in
	 * "options". The field's default value marks which are pre-selected.
	 *
	 * @since 7.20.0
	 * @param array $field Fluent Forms field.
	 * @return array
	 */
	protected function convert_options( $field ) {
		$pairs = array();

		if ( ! empty( $field['settings']['advanced_options'] ) && is_array( $field['settings']['advanced_options'] ) ) {
			foreach ( $field['settings']['advanced_options'] as $option ) {
				if ( is_array( $option ) && isset( $option['label'] ) ) {
					$pairs[] = array( (string) $option['label'], isset( $option['value'] ) ? (string) $option['value'] : '' );
				}
			}
		} elseif ( ! empty( $field['options'] ) && is_array( $field['options'] ) ) {
			foreach ( $field['options'] as $value => $label ) {
				if ( is_scalar( $label ) ) {
					$pairs[] = array( (string) $label, (string) $value );
				}
			}
		}

		$defaults = array();
		if ( isset( $field['attributes']['value'] ) ) {
			$defaults = is_array( $field['attributes']['value'] ) ? array_map( 'strval', $field['attributes']['value'] ) : array( (string) $field['attributes']['value'] );
		}

		$choices = array();

		foreach ( $pairs as $pair ) {
			$label = trim( wp_strip_all_tags( $pair[0] ) );

			if ( '' === $label ) {
				continue;
			}

			$value = '' !== $pair[1] ? $pair[1] : $label;

			$choices[] = array(
				'label'    => $label,
				'value'    => $value,
				'selected' => in_array( $value, $defaults, true ) && '' !== $value,
			);
		}

		return $choices;
	}

	/**
	 * Read the form's notifications and settings from fluentform_form_meta.
	 *
	 * @since 7.20.0
	 * @param int $form_id Fluent Forms form ID.
	 * @return array 'notifications' => list, 'formSettings' => array.
	 */
	protected function read_meta( $form_id ) {
		global $wpdb;

		$meta = array(
			'notifications' => array(),
			'formSettings'  => array(),
		);

		if ( ! $this->table_exists( 'fluentform_form_meta' ) ) {
			return $meta;
		}

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT meta_key, value FROM ' . $this->table( 'fluentform_form_meta' ) . " WHERE form_id = %d AND meta_key IN ('notifications', 'formSettings') ORDER BY id ASC",
				$form_id
			),
			ARRAY_A
		);

		foreach ( (array) $rows as $row ) {
			$value = json_decode( (string) $row['value'], true );

			if ( ! is_array( $value ) ) {
				continue;
			}

			if ( 'notifications' === $row['meta_key'] ) {
				$meta['notifications'][] = $value;
			} elseif ( empty( $meta['formSettings'] ) ) {
				$meta['formSettings'] = $value;
			}
		}

		return $meta;
	}

	/**
	 * The form's thank-you message, when it shows a message on the same page.
	 *
	 * @since 7.20.0
	 * @param array $settings formSettings meta.
	 * @return string
	 */
	protected function confirmation_message( $settings ) {
		if ( empty( $settings['confirmation'] ) || ! is_array( $settings['confirmation'] ) ) {
			return '';
		}

		$confirmation = $settings['confirmation'];

		if ( isset( $confirmation['redirectTo'] ) && 'samePage' !== $confirmation['redirectTo'] ) {
			return '';
		}

		$message = isset( $confirmation['messageToShow'] ) ? trim( wp_strip_all_tags( (string) $confirmation['messageToShow'] ) ) : '';

		// Smart codes such as {inputs.names} cannot be resolved here.
		return false === strpos( $message, '{' ) ? $message : '';
	}

	/**
	 * Convert the first enabled email notification into a CCF notification.
	 *
	 * Recipients written as smart codes, such as {wp.admin_email}, fall back
	 * to the site admin address. The body becomes CCF's [all_fields].
	 *
	 * @since 7.20.0
	 * @param array $notifications Notification settings.
	 * @return array
	 */
	protected function convert_notifications( $notifications ) {
		foreach ( (array) $notifications as $notification ) {
			if ( ! is_array( $notification ) ) {
				continue;
			}

			if ( isset( $notification['enabled'] ) && ! $notification['enabled'] ) {
				continue;
			}

			$send_to = isset( $notification['sendTo'] ) && is_array( $notification['sendTo'] ) ? $notification['sendTo'] : array();

			if ( isset( $send_to['type'] ) && 'email' !== $send_to['type'] ) {
				continue;
			}

			$raw       = isset( $send_to['email'] ) ? (string) $send_to['email'] : '';
			$addresses = array();

			foreach ( array_map( 'trim', explode( ',', $raw ) ) as $candidate ) {
				if ( '' === $candidate ) {
					continue;
				}

				if ( false !== strpos( $candidate, '{' ) ) {
					$candidate = get_option( 'admin_email' );
				}

				if ( is_email( $candidate ) ) {
					$addresses[ strtolower( $candidate ) ] = array(
						'type'  => 'custom',
						'email' => sanitize_email( $candidate ),
					);
				}
			}

			if ( empty( $addresses ) ) {
				continue;
			}

			$subject = isset( $notification['subject'] ) ? trim( (string) $notification['subject'] ) : '';

			if ( false !== strpos( $subject, '{' ) ) {
				$subject = '';
			}

			return array(
				array(
					'title'          => __( 'Imported notification', 'custom-contact-forms' ),
					'active'         => true,
					'addresses'      => array_values( $addresses ),
					'subjectType'    => '' !== $subject ? 'custom' : 'default',
					'subject'        => sanitize_text_field( $subject ),
					'fromType'       => 'default',
					'fromNameType'   => 'default',
					'fromAddress'    => '',
					'fromName'       => '',
					'content'        => '[all_fields]',
					'includeUploads' => true,
				),
			);
		}

		return array();
	}
}
