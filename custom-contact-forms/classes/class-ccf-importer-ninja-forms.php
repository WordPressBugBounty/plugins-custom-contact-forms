<?php
/**
 * Ninja Forms importer.
 *
 * Ninja Forms 3 keeps forms in its own tables rather than in posts: one row
 * per form in nf3_forms, one row per field in nf3_fields, and each field's
 * settings as key/value rows in nf3_field_meta. Email and success-message
 * "actions" live in nf3_actions and nf3_action_meta.
 *
 * Older Ninja Forms databases store meta in "key"/"value" columns, newer ones
 * in "meta_key"/"meta_value", so both are read. Everything read here is
 * treated as untrusted: field types are checked against a known map and
 * anything unrecognised is reported rather than guessed at.
 *
 * @since 7.20.0
 * @package Custom Contact Forms
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CCF_Importer_Ninja_Forms extends CCF_Importer {

	/**
	 * Ninja Forms field type => CCF field type.
	 *
	 * Payment, rating and password fields are deliberately absent. They either
	 * have no free CCF equivalent or would import as something that looks
	 * right but behaves differently.
	 *
	 * @since 7.20.0
	 * @var array
	 */
	protected $type_map = array(
		'textbox'         => 'single-line-text',
		'firstname'       => 'single-line-text',
		'lastname'        => 'single-line-text',
		'address'         => 'single-line-text',
		'address2'        => 'single-line-text',
		'city'            => 'single-line-text',
		'zip'             => 'single-line-text',
		'listcountry'     => 'single-line-text',
		'number'          => 'single-line-text',
		'textarea'        => 'paragraph-text',
		'email'           => 'email',
		'phone'           => 'phone',
		'date'            => 'date',
		'listselect'      => 'dropdown',
		'liststate'       => 'dropdown',
		'listradio'       => 'radio',
		'listcheckbox'    => 'checkboxes',
		'listmultiselect' => 'checkboxes',
		'checkbox'        => 'checkboxes',
		'hidden'          => 'hidden',
		'html'            => 'html',
		'hr'              => 'section-header',
		'recaptcha'       => 'recaptcha',
		'file_upload'     => 'file',
	);

	/**
	 * Field types that exist only to drive the form and are skipped quietly.
	 *
	 * @since 7.20.0
	 * @var array
	 */
	protected $silent_types = array( 'submit', 'note' );

	/**
	 * Importer key.
	 *
	 * @since 7.20.0
	 * @return string
	 */
	public function get_id() {
		return 'ninja-forms';
	}

	/**
	 * Source plugin name.
	 *
	 * @since 7.20.0
	 * @return string
	 */
	public function get_label() {
		return __( 'Ninja Forms', 'custom-contact-forms' );
	}

	/**
	 * Full table name.
	 *
	 * @since 7.20.0
	 * @param string $name Table name without prefix, e.g. 'nf3_forms'.
	 * @return string
	 */
	protected function table( $name ) {
		global $wpdb;
		return $wpdb->prefix . $name;
	}

	/**
	 * Whether a Ninja Forms table exists.
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
	 * Whether Ninja Forms forms exist on this site.
	 *
	 * Checks the tables rather than the plugin, so forms can still be imported
	 * after Ninja Forms has been deactivated.
	 *
	 * @since 7.20.0
	 * @return bool
	 */
	public function is_available() {
		global $wpdb;

		if ( ! $this->table_exists( 'nf3_forms' ) || ! $this->table_exists( 'nf3_fields' ) ) {
			return false;
		}

		return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . $this->table( 'nf3_forms' ) ) > 0;
	}

	/**
	 * List Ninja Forms forms.
	 *
	 * @since 7.20.0
	 * @return array
	 */
	public function get_source_forms() {
		global $wpdb;

		if ( ! $this->is_available() ) {
			return array();
		}

		$rows  = $wpdb->get_results( 'SELECT * FROM ' . $this->table( 'nf3_forms' ) . ' ORDER BY id ASC LIMIT 200', ARRAY_A );
		$forms = array();

		foreach ( (array) $rows as $row ) {
			$id    = isset( $row['id'] ) ? (int) $row['id'] : 0;
			$title = $this->form_title( $row );

			if ( $id ) {
				$forms[] = array(
					'id'    => $id,
					'title' => '' !== $title ? $title : sprintf( __( 'Form #%d', 'custom-contact-forms' ), $id ),
				);
			}
		}

		usort(
			$forms,
			function ( $a, $b ) {
				return strcasecmp( $a['title'], $b['title'] );
			}
		);

		return $forms;
	}

	/**
	 * A form row's title. Newer versions use "title", some use "form_title".
	 *
	 * @since 7.20.0
	 * @param array $row nf3_forms row.
	 * @return string
	 */
	protected function form_title( $row ) {
		foreach ( array( 'title', 'form_title' ) as $column ) {
			if ( ! empty( $row[ $column ] ) ) {
				return (string) $row[ $column ];
			}
		}
		return '';
	}

	/**
	 * Read a meta table into parent ID => key => value.
	 *
	 * @since 7.20.0
	 * @param string $table      Table name without prefix.
	 * @param array  $parent_ids Parent IDs to read.
	 * @return array
	 */
	protected function read_meta( $table, $parent_ids ) {
		global $wpdb;

		$parent_ids = array_values( array_filter( array_map( 'absint', (array) $parent_ids ) ) );

		if ( empty( $parent_ids ) || ! $this->table_exists( $table ) ) {
			return array();
		}

		$placeholders = implode( ',', array_fill( 0, count( $parent_ids ), '%d' ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is built from the site prefix.
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . $this->table( $table ) . " WHERE parent_id IN ($placeholders)", $parent_ids ), ARRAY_A );
		$meta = array();

		foreach ( (array) $rows as $row ) {
			$key   = isset( $row['meta_key'] ) && '' !== (string) $row['meta_key'] ? (string) $row['meta_key'] : ( isset( $row['key'] ) ? (string) $row['key'] : '' );
			$value = isset( $row['meta_value'] ) && null !== $row['meta_value'] ? $row['meta_value'] : ( isset( $row['value'] ) ? $row['value'] : '' );

			if ( '' === $key ) {
				continue;
			}

			$meta[ (int) $row['parent_id'] ][ $key ] = $this->unpack( $value );
		}

		return $meta;
	}

	/**
	 * Decode a stored value. Ninja Forms serializes list options; objects are
	 * never allowed to be created from stored data.
	 *
	 * @since 7.20.0
	 * @param mixed $value Stored value.
	 * @return mixed
	 */
	protected function unpack( $value ) {
		if ( is_string( $value ) && is_serialized( $value ) ) {
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize -- classes are disallowed.
			$decoded = @unserialize( trim( $value ), array( 'allowed_classes' => false ) );
			return false === $decoded && 'b:0;' !== trim( $value ) ? '' : $decoded;
		}
		return $value;
	}

	/**
	 * Convert a Ninja Forms form into a neutral definition.
	 *
	 * @since 7.20.0
	 * @param mixed $source_id Ninja Forms form ID.
	 * @return array|WP_Error
	 */
	public function convert_form( $source_id ) {
		global $wpdb;

		$form_id = absint( $source_id );

		if ( ! $form_id || ! $this->is_available() ) {
			return new WP_Error( 'ccf_import_missing', esc_html__( 'That Ninja Forms form could not be found.', 'custom-contact-forms' ) );
		}

		$form = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . $this->table( 'nf3_forms' ) . ' WHERE id = %d', $form_id ), ARRAY_A );

		if ( empty( $form ) ) {
			return new WP_Error( 'ccf_import_missing', esc_html__( 'That Ninja Forms form could not be found.', 'custom-contact-forms' ) );
		}

		$field_rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . $this->table( 'nf3_fields' ) . ' WHERE parent_id = %d', $form_id ), ARRAY_A );

		if ( empty( $field_rows ) ) {
			return new WP_Error( 'ccf_import_empty', esc_html__( 'That Ninja Forms form has no readable field data.', 'custom-contact-forms' ) );
		}

		$field_meta = $this->read_meta( 'nf3_field_meta', wp_list_pluck( $field_rows, 'id' ) );

		// Merge columns and meta into one settings array per field. Meta wins:
		// it is what the Ninja Forms builder writes.
		$fields = array();
		foreach ( $field_rows as $row ) {
			$id       = (int) $row['id'];
			$settings = isset( $field_meta[ $id ] ) ? $field_meta[ $id ] : array();

			foreach ( array( 'type', 'label', 'key', 'required', 'order' ) as $column ) {
				if ( ! isset( $settings[ $column ] ) && isset( $row[ $column ] ) ) {
					$settings[ $column ] = $row[ $column ];
				}
			}

			if ( ! isset( $settings['label'] ) && isset( $row['field_label'] ) ) {
				$settings['label'] = $row['field_label'];
			}

			$settings['_id'] = $id;
			$fields[]        = $settings;
		}

		usort(
			$fields,
			function ( $a, $b ) {
				$oa = isset( $a['order'] ) ? (int) $a['order'] : 0;
				$ob = isset( $b['order'] ) ? (int) $b['order'] : 0;
				return $oa === $ob ? $a['_id'] - $b['_id'] : $oa - $ob;
			}
		);

		$title = $this->form_title( $form );
		if ( '' === $title ) {
			$form_meta = $this->read_meta( 'nf3_form_meta', array( $form_id ) );
			$title     = ! empty( $form_meta[ $form_id ]['title'] ) ? (string) $form_meta[ $form_id ]['title'] : sprintf( __( 'Form #%d', 'custom-contact-forms' ), $form_id );
		}

		$parsed  = $this->parse_fields( $fields );
		$actions = $this->read_actions( $form_id );

		return array(
			'title'              => $title,
			'fields'             => $parsed['fields'],
			'skipped'            => $parsed['skipped'],
			'notifications'      => $this->convert_notifications( $actions ),
			'button_text'        => '' !== $parsed['button_text'] ? $parsed['button_text'] : __( 'Submit', 'custom-contact-forms' ),
			'completion_message' => $this->success_message( $actions ),
		);
	}

	/**
	 * Convert Ninja Forms fields into CCF field definitions.
	 *
	 * @since 7.20.0
	 * @param array $fields Field settings arrays, in display order.
	 * @return array Fields, skipped notes and the submit button text.
	 */
	protected function parse_fields( $fields ) {
		$out         = array();
		$skipped     = array();
		$taken       = array();
		$button_text = '';

		foreach ( $fields as $field ) {
			$type  = isset( $field['type'] ) ? (string) $field['type'] : '';
			$label = isset( $field['label'] ) ? trim( wp_strip_all_tags( (string) $field['label'] ) ) : '';

			if ( '' === $type ) {
				continue;
			}

			if ( 'submit' === $type ) {
				if ( '' !== $label ) {
					$button_text = $label;
				}
				continue;
			}

			if ( in_array( $type, $this->silent_types, true ) ) {
				continue;
			}

			if ( ! isset( $this->type_map[ $type ] ) ) {
				$skipped[] = sprintf(
					/* translators: 1: field label, 2: Ninja Forms field type. */
					esc_html__( 'Not converted: %1$s (%2$s)', 'custom-contact-forms' ),
					'' !== $label ? $label : esc_html__( 'untitled field', 'custom-contact-forms' ),
					$type
				);
				continue;
			}

			$ccf_type   = $this->type_map[ $type ];
			$definition = array(
				'type'     => $ccf_type,
				'slug'     => $this->unique_slug( '' !== $label ? $label : $type, $taken ),
				'label'    => '' !== $label ? $label : ucfirst( $type ),
				'required' => ! empty( $field['required'] ) && '0' !== (string) $field['required'],
			);

			if ( ! empty( $field['placeholder'] ) ) {
				$definition['placeholder'] = (string) $field['placeholder'];
			}

			if ( ! empty( $field['desc_text'] ) ) {
				$definition['description'] = trim( wp_strip_all_tags( (string) $field['desc_text'] ) );
			}

			if ( 'html' === $type ) {
				$definition['value'] = wp_kses_post( isset( $field['default'] ) ? (string) $field['default'] : '' );
			}

			if ( 'hidden' === $type && isset( $field['default'] ) ) {
				$definition['value'] = (string) $field['default'];
			}

			if ( 'listmultiselect' === $type ) {
				$skipped[] = sprintf(
					/* translators: %s: field label. */
					esc_html__( 'Multi-select list imported as checkboxes: %s', 'custom-contact-forms' ),
					$definition['label']
				);
			}

			if ( 'checkbox' === $type ) {
				// A single Ninja Forms checkbox becomes a one-option checkbox list.
				$checked                = isset( $field['checked_value'] ) && '' !== (string) $field['checked_value'] ? (string) $field['checked_value'] : $definition['label'];
				$definition['choices'] = array(
					array(
						'label'    => $definition['label'],
						'value'    => $checked,
						'selected' => isset( $field['default_value'] ) && 'checked' === $field['default_value'],
					),
				);
			} elseif ( in_array( $ccf_type, array( 'dropdown', 'radio', 'checkboxes' ), true ) ) {
				$choices = $this->convert_options( isset( $field['options'] ) ? $field['options'] : array() );

				if ( empty( $choices ) && 'liststate' === $type ) {
					// State lists without stored options import as a text box.
					$definition['type'] = 'single-line-text';
				} elseif ( empty( $choices ) ) {
					$skipped[] = sprintf(
						/* translators: %s: field label. */
						esc_html__( 'Imported without options: %s', 'custom-contact-forms' ),
						$definition['label']
					);
					$definition['choices'] = array();
				} else {
					$definition['choices'] = $choices;
				}
			}

			$out[] = $definition;
		}

		return array(
			'fields'      => $out,
			'skipped'     => $skipped,
			'button_text' => $button_text,
		);
	}

	/**
	 * Convert a Ninja Forms options list into CCF choices.
	 *
	 * @since 7.20.0
	 * @param mixed $options Options array.
	 * @return array
	 */
	protected function convert_options( $options ) {
		if ( ! is_array( $options ) ) {
			return array();
		}

		usort(
			$options,
			function ( $a, $b ) {
				$oa = is_array( $a ) && isset( $a['order'] ) ? (int) $a['order'] : 0;
				$ob = is_array( $b ) && isset( $b['order'] ) ? (int) $b['order'] : 0;
				return $oa - $ob;
			}
		);

		$choices = array();

		foreach ( $options as $option ) {
			if ( ! is_array( $option ) ) {
				continue;
			}

			$label = isset( $option['label'] ) ? trim( wp_strip_all_tags( (string) $option['label'] ) ) : '';

			if ( '' === $label ) {
				continue;
			}

			$choices[] = array(
				'label'    => $label,
				'value'    => isset( $option['value'] ) && '' !== (string) $option['value'] ? (string) $option['value'] : $label,
				'selected' => ! empty( $option['selected'] ) && '0' !== (string) $option['selected'],
			);
		}

		return $choices;
	}

	/**
	 * Read the form's active actions with their settings.
	 *
	 * @since 7.20.0
	 * @param int $form_id Ninja Forms form ID.
	 * @return array
	 */
	protected function read_actions( $form_id ) {
		global $wpdb;

		if ( ! $this->table_exists( 'nf3_actions' ) ) {
			return array();
		}

		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . $this->table( 'nf3_actions' ) . ' WHERE parent_id = %d ORDER BY id ASC', $form_id ), ARRAY_A );

		if ( empty( $rows ) ) {
			return array();
		}

		$meta    = $this->read_meta( 'nf3_action_meta', wp_list_pluck( $rows, 'id' ) );
		$actions = array();

		foreach ( $rows as $row ) {
			$id       = (int) $row['id'];
			$settings = isset( $meta[ $id ] ) ? $meta[ $id ] : array();

			foreach ( array( 'type', 'active' ) as $column ) {
				if ( ! isset( $settings[ $column ] ) && isset( $row[ $column ] ) ) {
					$settings[ $column ] = $row[ $column ];
				}
			}

			if ( isset( $settings['active'] ) && ( '0' === (string) $settings['active'] || false === $settings['active'] ) ) {
				continue;
			}

			$actions[] = $settings;
		}

		return $actions;
	}

	/**
	 * The text of the form's first success message action.
	 *
	 * @since 7.20.0
	 * @param array $actions Active actions.
	 * @return string
	 */
	protected function success_message( $actions ) {
		foreach ( $actions as $action ) {
			if ( isset( $action['type'] ) && 'successmessage' === $action['type'] && ! empty( $action['success_msg'] ) ) {
				$message = trim( wp_strip_all_tags( (string) $action['success_msg'] ) );

				// Merge tags such as {field:name} cannot be resolved here.
				if ( '' !== $message && false === strpos( $message, '{' ) ) {
					return $message;
				}
			}
		}

		return '';
	}

	/**
	 * Convert the first email action into a CCF notification.
	 *
	 * Only the recipient and subject are carried over. Ninja Forms message
	 * bodies use merge tags such as {all_fields_table}, so the body is set to
	 * CCF's [all_fields] instead. Recipients written as merge tags fall back
	 * to the site admin address.
	 *
	 * @since 7.20.0
	 * @param array $actions Active actions.
	 * @return array
	 */
	protected function convert_notifications( $actions ) {
		foreach ( $actions as $action ) {
			if ( ! isset( $action['type'] ) || 'email' !== $action['type'] || empty( $action['to'] ) ) {
				continue;
			}

			$addresses = array();

			foreach ( array_map( 'trim', explode( ',', (string) $action['to'] ) ) as $candidate ) {
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

			$subject = isset( $action['email_subject'] ) ? trim( (string) $action['email_subject'] ) : '';

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
