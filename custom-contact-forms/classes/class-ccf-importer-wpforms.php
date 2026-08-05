<?php
/**
 * WPForms importer.
 *
 * WPForms stores each form as a JSON document in the post_content of its
 * "wpforms" post type: a "fields" map keyed by field ID, plus "settings"
 * holding the form title and notifications.
 *
 * Everything read here is treated as untrusted. Field types are checked
 * against a known map and anything unrecognised is reported rather than
 * guessed at, so a WPForms release adding new field types can only ever
 * produce a skip note.
 *
 * @since 7.15.0
 * @package Custom Contact Forms
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CCF_Importer_WPForms extends CCF_Importer {

	/**
	 * WPForms field type => CCF field type.
	 *
	 * Paid-tier and layout field types are deliberately absent: they either
	 * have no CCF equivalent or exist only to arrange other fields, and a
	 * wrong guess produces a form that looks imported but behaves differently.
	 *
	 * @since 7.15.0
	 * @var array
	 */
	protected $type_map = array(
		'text'          => 'single-line-text',
		'textarea'      => 'paragraph-text',
		'email'         => 'email',
		'name'          => 'name',
		'phone'         => 'phone',
		'url'           => 'website',
		'address'       => 'address',
		'number'        => 'single-line-text',
		'number-slider' => 'single-line-text',
		'date-time'     => 'date',
		'select'        => 'dropdown',
		'radio'         => 'radio',
		'checkbox'      => 'checkboxes',
		'gdpr-checkbox' => 'checkboxes',
		'file-upload'   => 'file',
		'hidden'        => 'hidden',
		'html'          => 'html',
		'divider'       => 'section-header',
		'captcha'       => 'recaptcha',
	);

	/**
	 * Importer key.
	 *
	 * @since 7.15.0
	 * @return string
	 */
	public function get_id() {
		return 'wpforms';
	}

	/**
	 * Source plugin name.
	 *
	 * @since 7.15.0
	 * @return string
	 */
	public function get_label() {
		return __( 'WPForms', 'custom-contact-forms' );
	}

	/**
	 * Whether WPForms forms exist on this site.
	 *
	 * Checks for the post type rather than the plugin, so forms can still be
	 * imported after WPForms has been deactivated.
	 *
	 * @since 7.15.0
	 * @return bool
	 */
	public function is_available() {
		$found = get_posts(
			array(
				'post_type'      => 'wpforms',
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);

		return ! empty( $found );
	}

	/**
	 * List WPForms forms.
	 *
	 * @since 7.15.0
	 * @return array
	 */
	public function get_source_forms() {
		$posts = get_posts(
			array(
				'post_type'      => 'wpforms',
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
	 * Convert a WPForms form into a neutral definition.
	 *
	 * @since 7.15.0
	 * @param mixed $source_id WPForms post ID.
	 * @return array|WP_Error
	 */
	public function convert_form( $source_id ) {
		$post = get_post( (int) $source_id );

		if ( ! $post || 'wpforms' !== $post->post_type ) {
			return new WP_Error( 'ccf_import_missing', esc_html__( 'That WPForms form could not be found.', 'custom-contact-forms' ) );
		}

		$data = json_decode( $post->post_content, true );

		if ( ! is_array( $data ) || empty( $data['fields'] ) || ! is_array( $data['fields'] ) ) {
			return new WP_Error( 'ccf_import_empty', esc_html__( 'That WPForms form has no readable field data.', 'custom-contact-forms' ) );
		}

		$settings = isset( $data['settings'] ) && is_array( $data['settings'] ) ? $data['settings'] : array();

		$title = '';

		if ( ! empty( $settings['form_title'] ) ) {
			$title = (string) $settings['form_title'];
		} elseif ( '' !== $post->post_title ) {
			$title = $post->post_title;
		} else {
			$title = sprintf( __( 'Form #%d', 'custom-contact-forms' ), $post->ID );
		}

		$parsed = $this->parse_fields( $data['fields'] );

		return array(
			'title'              => $title,
			'fields'             => $parsed['fields'],
			'skipped'            => $parsed['skipped'],
			'notifications'      => $this->convert_notifications( $settings ),
			'button_text'        => ! empty( $settings['submit_text'] ) ? (string) $settings['submit_text'] : __( 'Submit', 'custom-contact-forms' ),
			'completion_message' => ! empty( $settings['confirmation_message'] ) ? wp_strip_all_tags( (string) $settings['confirmation_message'] ) : '',
		);
	}

	/**
	 * Convert the WPForms fields map into CCF field definitions.
	 *
	 * WPForms keys fields by ID rather than storing them in display order, so
	 * the map is sorted by numeric key first to preserve the order the form
	 * was built in.
	 *
	 * @since 7.15.0
	 * @param array $fields WPForms fields map.
	 * @return array Fields and skipped notes.
	 */
	protected function parse_fields( $fields ) {
		$out     = array();
		$skipped = array();
		$taken   = array();

		ksort( $fields, SORT_NUMERIC );

		foreach ( $fields as $field ) {
			if ( ! is_array( $field ) || empty( $field['type'] ) ) {
				continue;
			}

			$type  = (string) $field['type'];
			$label = isset( $field['label'] ) ? (string) $field['label'] : '';

			// Layout containers hold other fields rather than collecting
			// anything themselves. Their children appear in the same flat map,
			// so skipping the container loses the columns but keeps the fields.
			if ( in_array( $type, array( 'layout', 'repeater' ), true ) ) {
				$skipped[] = sprintf(
					/* translators: %s: WPForms field type. */
					esc_html__( 'Layout not preserved: %s. The fields inside it were imported in order.', 'custom-contact-forms' ),
					$type
				);
				continue;
			}

			if ( 'pagebreak' === $type ) {
				$skipped[] = esc_html__( 'Page breaks were not imported. Multi-step forms are a Pro feature.', 'custom-contact-forms' );
				continue;
			}

			if ( ! isset( $this->type_map[ $type ] ) ) {
				$skipped[] = sprintf(
					/* translators: 1: field label, 2: WPForms field type. */
					esc_html__( 'Not converted: %1$s (%2$s)', 'custom-contact-forms' ),
					'' !== $label ? $label : esc_html__( 'untitled field', 'custom-contact-forms' ),
					$type
				);
				continue;
			}

			$slug_source = '' !== $label ? $label : $type;

			$definition = array(
				'type'     => $this->type_map[ $type ],
				'slug'     => $this->unique_slug( $slug_source, $taken ),
				'label'    => '' !== $label ? $label : ucfirst( str_replace( '-', ' ', $type ) ),
				'required' => ! empty( $field['required'] ),
			);

			if ( ! empty( $field['placeholder'] ) ) {
				$definition['placeholder'] = (string) $field['placeholder'];
			}

			if ( ! empty( $field['description'] ) ) {
				$definition['description'] = wp_strip_all_tags( (string) $field['description'] );
			}

			if ( 'html' === $type && ! empty( $field['code'] ) ) {
				$definition['value'] = wp_kses_post( (string) $field['code'] );
			}

			if ( 'hidden' === $type && isset( $field['default_value'] ) ) {
				$definition['value'] = (string) $field['default_value'];
			}

			if ( ! empty( $field['choices'] ) && is_array( $field['choices'] ) ) {
				$choices = array();

				foreach ( $field['choices'] as $choice ) {
					if ( ! is_array( $choice ) ) {
						continue;
					}

					$choice_label = isset( $choice['label'] ) ? trim( (string) $choice['label'] ) : '';

					if ( '' === $choice_label ) {
						continue;
					}

					$choices[] = array(
						'label'    => $choice_label,
						'value'    => isset( $choice['value'] ) && '' !== $choice['value'] ? (string) $choice['value'] : $choice_label,
						'selected' => ! empty( $choice['default'] ),
					);
				}

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
	 * Convert WPForms notification settings into a CCF notification.
	 *
	 * Only the first notification is converted, and only its recipient and
	 * subject. WPForms message bodies use smart tags such as {all_fields}
	 * that have no direct equivalent, so the body is left to CCF's default
	 * rather than carried across in a form that would not resolve.
	 *
	 * @since 7.15.0
	 * @param array $settings WPForms form settings.
	 * @return array
	 */
	protected function convert_notifications( $settings ) {
		if ( empty( $settings['notifications'] ) || ! is_array( $settings['notifications'] ) ) {
			return array();
		}

		$first = reset( $settings['notifications'] );

		if ( ! is_array( $first ) || empty( $first['email'] ) ) {
			return array();
		}

		// The recipient may be a comma-separated list, and may contain smart
		// tags such as {admin_email} or {field_id="3"} which cannot be
		// resolved here. Anything unresolvable falls back to the site admin.
		$candidates = array_map( 'trim', explode( ',', (string) $first['email'] ) );
		$addresses  = array();

		foreach ( $candidates as $candidate ) {
			if ( '' === $candidate ) {
				continue;
			}

			if ( false !== strpos( $candidate, '{' ) ) {
				$candidate = get_option( 'admin_email' );
			}

			if ( ! is_email( $candidate ) ) {
				continue;
			}

			$addresses[] = array(
				'type'  => 'custom',
				'email' => sanitize_email( $candidate ),
			);
		}

		// Two smart tags could both fall back to the admin address.
		$addresses = array_values(
			array_intersect_key( $addresses, array_unique( array_column( $addresses, 'email' ) ) )
		);

		if ( empty( $addresses ) ) {
			return array();
		}

		$subject = isset( $first['subject'] ) ? trim( (string) $first['subject'] ) : '';

		if ( '' !== $subject && false !== strpos( $subject, '{' ) ) {
			$subject = '';
		}

		return array(
			array(
				'title'        => __( 'Imported notification', 'custom-contact-forms' ),
				'active'       => true,
				'addresses'    => $addresses,
				'subjectType'  => '' !== $subject ? 'custom' : 'default',
				'subject'      => sanitize_text_field( $subject ),
				'fromType'     => 'default',
				'fromNameType' => 'default',
				'fromAddress'  => '',
				'fromName'     => '',
				'content'      => '',
			),
		);
	}
}
