<?php
/**
 * Base class for importing forms from other form plugins.
 *
 * Subclasses translate a source plugin's form into the neutral array shape
 * described in convert_form(), and this class handles everything after that:
 * creating the CCF form, fields and choices, recording provenance so re-runs
 * are safe, and reporting what could not be converted.
 *
 * Adding a new source means writing one subclass. Nothing here should need to
 * change.
 *
 * @since 7.15.0
 * @package Custom Contact Forms
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class CCF_Importer {

	/**
	 * Field types CCF understands. A converted field claiming anything outside
	 * this list is dropped and reported rather than trusted, so a source plugin
	 * adding new field types can never write an unrenderable field.
	 *
	 * @since 7.15.0
	 * @var array
	 */
	protected $supported_types = array(
		'single-line-text',
		'paragraph-text',
		'email',
		'name',
		'phone',
		'website',
		'address',
		'date',
		'dropdown',
		'radio',
		'checkboxes',
		'file',
		'hidden',
		'html',
		'section-header',
		'recaptcha',
	);

	/**
	 * Unique key for this importer, e.g. "cf7".
	 *
	 * @since 7.15.0
	 * @return string
	 */
	abstract public function get_id();

	/**
	 * Human-readable source plugin name.
	 *
	 * @since 7.15.0
	 * @return string
	 */
	abstract public function get_label();

	/**
	 * Whether the source plugin is present on this site.
	 *
	 * @since 7.15.0
	 * @return bool
	 */
	abstract public function is_available();

	/**
	 * List the source plugin's forms.
	 *
	 * @since 7.15.0
	 * @return array Array of arrays with 'id' and 'title' keys.
	 */
	abstract public function get_source_forms();

	/**
	 * Convert one source form into a neutral definition.
	 *
	 * Returns an array with:
	 *   'title'         string  Form title.
	 *   'fields'        array   Field definitions (type, slug, label, required,
	 *                           placeholder, choices, etc.).
	 *   'notifications' array   Optional notification definitions.
	 *   'skipped'       array   Human-readable notes about anything that could
	 *                           not be converted.
	 *
	 * @since 7.15.0
	 * @param mixed $source_id Source form identifier.
	 * @return array|WP_Error
	 */
	abstract public function convert_form( $source_id );

	/**
	 * Import a single source form.
	 *
	 * The form is assembled and validated in full before anything is written,
	 * so a field that fails conversion cannot leave a half-built form behind.
	 *
	 * @since 7.15.0
	 * @param mixed $source_id Source form identifier.
	 * @return array|WP_Error Result with form_id, title, skipped, existing.
	 */
	public function import( $source_id ) {
		$definition = $this->convert_form( $source_id );

		if ( is_wp_error( $definition ) ) {
			return $definition;
		}

		if ( empty( $definition['fields'] ) ) {
			return new WP_Error(
				'ccf_import_empty',
				esc_html__( 'That form has no fields this importer can convert.', 'custom-contact-forms' )
			);
		}

		$existing = $this->find_existing_import( $source_id );

		if ( $existing ) {
			return array(
				'form_id'  => $existing,
				'title'    => get_the_title( $existing ),
				'skipped'  => array(),
				'existing' => true,
			);
		}

		$skipped = isset( $definition['skipped'] ) ? $definition['skipped'] : array();
		$clean   = array();

		foreach ( $definition['fields'] as $field ) {
			if ( empty( $field['type'] ) || ! in_array( $field['type'], $this->supported_types, true ) ) {
				$skipped[] = sprintf(
					/* translators: %s: source field name or type. */
					esc_html__( 'Unsupported field: %s', 'custom-contact-forms' ),
					isset( $field['label'] ) && '' !== $field['label'] ? $field['label'] : ( isset( $field['type'] ) ? $field['type'] : '?' )
				);
				continue;
			}

			$clean[] = $field;
		}

		if ( empty( $clean ) ) {
			return new WP_Error(
				'ccf_import_empty',
				esc_html__( 'None of that form\'s fields could be converted.', 'custom-contact-forms' )
			);
		}

		$form_id = $this->create_form( $definition, $clean );

		if ( is_wp_error( $form_id ) ) {
			return $form_id;
		}

		update_post_meta( $form_id, 'ccf_imported_from', sanitize_key( $this->get_id() ) );
		update_post_meta( $form_id, 'ccf_imported_source_id', sanitize_text_field( (string) $source_id ) );

		return array(
			'form_id'  => $form_id,
			'title'    => $definition['title'],
			'skipped'  => $skipped,
			'existing' => false,
		);
	}

	/**
	 * Find a CCF form previously imported from this source form.
	 *
	 * Lets the admin screen skip rather than silently duplicate on re-run.
	 *
	 * @since 7.15.0
	 * @param mixed $source_id Source form identifier.
	 * @return int|false Form ID, or false if not previously imported.
	 */
	public function find_existing_import( $source_id ) {
		$found = get_posts(
			array(
				'post_type'        => 'ccf_form',
				'post_status'      => 'any',
				'posts_per_page'   => 1,
				'fields'           => 'ids',
				'no_found_rows'    => true,
				'suppress_filters' => false,
				'meta_query'       => array(
					'relation' => 'AND',
					array(
						'key'   => 'ccf_imported_from',
						'value' => sanitize_key( $this->get_id() ),
					),
					array(
						'key'   => 'ccf_imported_source_id',
						'value' => sanitize_text_field( (string) $source_id ),
					),
				),
			)
		);

		return empty( $found ) ? false : (int) $found[0];
	}

	/**
	 * Create the CCF form, its fields and any choices.
	 *
	 * Mirrors CCF_Form_Templates::create_form_from_template() so imported forms
	 * are indistinguishable from ones built in the builder.
	 *
	 * @since 7.15.0
	 * @param array $definition Form definition.
	 * @param array $fields     Validated field definitions.
	 * @return int|WP_Error Form post ID.
	 */
	protected function create_form( $definition, $fields ) {
		$form_id = wp_insert_post(
			array(
				'post_type'   => 'ccf_form',
				'post_status' => 'publish',
				'post_title'  => sanitize_text_field( $definition['title'] ),
			)
		);

		if ( is_wp_error( $form_id ) ) {
			return $form_id;
		}

		update_post_meta( $form_id, 'ccf_form_description', '' );
		update_post_meta( $form_id, 'ccf_form_buttonText', esc_attr( isset( $definition['button_text'] ) ? $definition['button_text'] : __( 'Submit', 'custom-contact-forms' ) ) );
		update_post_meta( $form_id, 'ccf_form_buttonClass', '' );
		update_post_meta( $form_id, 'ccf_form_theme', '' );
		update_post_meta( $form_id, 'ccf_form_completion_action_type', 'text' );
		update_post_meta( $form_id, 'ccf_form_completion_message', isset( $definition['completion_message'] ) ? sanitize_text_field( $definition['completion_message'] ) : '' );
		update_post_meta( $form_id, 'ccf_form_completion_redirect_url', '' );
		update_post_meta( $form_id, 'ccf_form_send_email_notifications', true );
		update_post_meta( $form_id, 'ccf_form_pause', false );
		update_post_meta( $form_id, 'ccf_form_hide_title', false );
		update_post_meta( $form_id, 'ccf_form_require_logged_in', false );
		update_post_meta( $form_id, 'ccf_form_post_creation', false );
		update_post_meta( $form_id, 'ccf_form_post_field_mappings', array() );
		update_post_meta( $form_id, 'ccf_form_notifications', isset( $definition['notifications'] ) ? $definition['notifications'] : array() );

		$order     = 0;
		$field_ids = array();

		foreach ( $fields as $field ) {
			$field_id = wp_insert_post(
				array(
					'post_type'   => 'ccf_field',
					'post_status' => 'publish',
					'post_parent' => $form_id,
					'post_title'  => sanitize_text_field( $field['slug'] ),
					'menu_order'  => $order,
				)
			);

			if ( is_wp_error( $field_id ) ) {
				continue;
			}

			$field_ids[] = $field_id;

			update_post_meta( $field_id, 'ccf_field_type', sanitize_text_field( $field['type'] ) );
			update_post_meta( $field_id, 'ccf_field_slug', sanitize_text_field( $field['slug'] ) );
			update_post_meta( $field_id, 'ccf_field_label', sanitize_text_field( isset( $field['label'] ) ? $field['label'] : '' ) );
			update_post_meta( $field_id, 'ccf_field_required', ! empty( $field['required'] ) );
			update_post_meta( $field_id, 'ccf_field_value', isset( $field['value'] ) ? sanitize_text_field( $field['value'] ) : '' );
			update_post_meta( $field_id, 'ccf_field_className', '' );
			update_post_meta( $field_id, 'ccf_field_description', isset( $field['description'] ) ? sanitize_textarea_field( $field['description'] ) : '' );
			update_post_meta( $field_id, 'ccf_field_placeholder', isset( $field['placeholder'] ) ? sanitize_text_field( $field['placeholder'] ) : '' );
			update_post_meta( $field_id, 'ccf_field_fieldWidth', '' );

			if ( ! empty( $field['choices'] ) && is_array( $field['choices'] ) ) {
				$choice_order = 0;
				$choice_ids   = array();

				foreach ( $field['choices'] as $choice ) {
					$choice_id = wp_insert_post(
						array(
							'post_type'   => 'ccf_choice',
							'post_status' => 'publish',
							'post_parent' => $field_id,
							'post_title'  => sanitize_text_field( $choice['label'] ),
							'menu_order'  => $choice_order,
						)
					);

					if ( ! is_wp_error( $choice_id ) ) {
						update_post_meta( $choice_id, 'ccf_choice_label', sanitize_text_field( $choice['label'] ) );
						update_post_meta( $choice_id, 'ccf_choice_value', sanitize_text_field( isset( $choice['value'] ) ? $choice['value'] : $choice['label'] ) );
						update_post_meta( $choice_id, 'ccf_choice_selected', ! empty( $choice['selected'] ) );
						$choice_ids[] = $choice_id;
					}

					$choice_order++;
				}

				update_post_meta( $field_id, 'ccf_attached_choices', $choice_ids );
			}

			$order++;
		}

		update_post_meta( $form_id, 'ccf_attached_fields', $field_ids );

		return $form_id;
	}

	/**
	 * Build a slug that is unique within one form.
	 *
	 * @since 7.15.0
	 * @param string $raw   Candidate slug.
	 * @param array  $taken Slugs already used in this form, by reference.
	 * @return string
	 */
	protected function unique_slug( $raw, &$taken ) {
		$slug = sanitize_title( $raw );

		if ( '' === $slug ) {
			$slug = 'field';
		}

		$base = $slug;
		$i    = 2;

		while ( in_array( $slug, $taken, true ) ) {
			$slug = $base . '-' . $i;
			$i++;
		}

		$taken[] = $slug;

		return $slug;
	}
}
