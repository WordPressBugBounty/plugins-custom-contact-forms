<?php
/**
 * Signature field.
 *
 * Visitors draw a signature with a mouse, finger or stylus. The drawing is
 * posted as a PNG data URL, validated, and after the submission is saved it
 * is written to the uploads folder as a PNG attachment of the submission, so
 * it is removed when the submission is deleted. The stored value is the
 * image URL.
 *
 * The field type is "signature", the same type Custom Contact Forms Pro
 * used before 7.17, so existing signature fields keep working.
 *
 * @package Custom_Contact_Forms
 * @since   7.17.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CCF_Signature_Field {

	/**
	 * Largest data URL accepted, in bytes. A signature drawn on the pad is
	 * normally well under 100 KB.
	 */
	const MAX_DATA_URL_LENGTH = 500000;

	/**
	 * Data URL prefix every valid signature starts with.
	 */
	const DATA_URL_PREFIX = 'data:image/png;base64,';

	/**
	 * Register hooks.
	 */
	public function setup() {
		add_filter( 'ccf_special_field_labels', array( $this, 'label' ) );
		add_action( 'ccf_successful_submission', array( $this, 'store_signatures' ), 10, 2 );
		add_action( 'ccf_underscore_templates', array( $this, 'builder_template' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_builder' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_frontend' ) );
	}

	/**
	 * Add the field to the builder palette.
	 *
	 * @param array $labels Field labels keyed by type.
	 * @return array
	 */
	public function label( $labels ) {
		$labels['signature'] = __( 'Signature', 'custom-contact-forms' );
		return $labels;
	}

	/**
	 * Sanitizer: accept only a size-capped PNG data URL. Anything else
	 * becomes an empty value, which the required check then catches.
	 *
	 * @param mixed $value    Posted value.
	 * @param int   $field_id Field ID.
	 * @return string
	 */
	public static function sanitize( $value, $field_id = 0 ) {
		$value = is_string( $value ) ? trim( $value ) : '';

		if ( '' === $value || strlen( $value ) > self::MAX_DATA_URL_LENGTH ) {
			return '';
		}

		if ( ! preg_match( '#^data:image/png;base64,[A-Za-z0-9+/=]+$#', $value ) ) {
			return '';
		}

		return $value;
	}

	/**
	 * Validator. Runs on the posted value before it is sanitized, so it
	 * checks the value the sanitizer will keep: a required signature must be
	 * a real drawing, not just any non-empty text.
	 *
	 * @param mixed $value    Posted value.
	 * @param int   $field_id Field ID.
	 * @param bool  $required Whether the field is required.
	 * @return true|array
	 */
	public static function validate( $value, $field_id, $required ) {
		$clean = self::sanitize( $value );

		if ( '' === $clean && ( $required || ( is_string( $value ) && '' !== trim( $value ) ) ) ) {
			return array( 'required' => esc_html__( 'This field is required.', 'custom-contact-forms' ) );
		}

		return true;
	}

	/**
	 * After a submission is saved, turn each signature data URL into a PNG
	 * attachment of the submission and store its URL in place of the data.
	 *
	 * @param int $submission_id Submission ID.
	 * @param int $form_id       Form ID.
	 */
	public function store_signatures( $submission_id, $form_id ) {
		$fields = get_post_meta( $form_id, 'ccf_attached_fields', true );
		$data   = get_post_meta( $submission_id, 'ccf_submission_data', true );

		if ( ! is_array( $fields ) || ! is_array( $data ) ) {
			return;
		}

		$changed = false;

		foreach ( $fields as $field_id ) {
			if ( 'signature' !== get_post_meta( $field_id, 'ccf_field_type', true ) ) {
				continue;
			}

			$slug = get_post_meta( $field_id, 'ccf_field_slug', true );

			if ( empty( $data[ $slug ] ) || ! is_string( $data[ $slug ] ) || 0 !== strpos( $data[ $slug ], self::DATA_URL_PREFIX ) ) {
				continue;
			}

			$url = $this->write_png( $data[ $slug ], $submission_id, $slug );

			if ( $url ) {
				$data[ $slug ] = $url;
				$changed       = true;
			}
		}

		if ( $changed ) {
			update_post_meta( $submission_id, 'ccf_submission_data', $data );
		}
	}

	/**
	 * Decode a signature data URL, flatten it onto white so it displays
	 * correctly in email clients and PDF tools, and save it as an attachment.
	 *
	 * @param string $data_url      PNG data URL.
	 * @param int    $submission_id Submission ID the file belongs to.
	 * @param string $slug          Field slug.
	 * @return string File URL, or an empty string on failure.
	 */
	private function write_png( $data_url, $submission_id, $slug ) {
		$png = base64_decode( substr( $data_url, strlen( self::DATA_URL_PREFIX ) ), true );

		if ( false === $png || 0 !== strpos( $png, "\x89PNG" ) ) {
			return '';
		}

		if ( function_exists( 'imagecreatefromstring' ) ) {
			$image = @imagecreatefromstring( $png ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

			if ( $image ) {
				$width  = imagesx( $image );
				$height = imagesy( $image );
				$flat   = imagecreatetruecolor( $width, $height );

				imagefill( $flat, 0, 0, imagecolorallocate( $flat, 255, 255, 255 ) );
				imagecopy( $flat, $image, 0, 0, 0, 0, $width, $height );

				ob_start();
				imagepng( $flat );
				$flat_png = ob_get_clean();

				imagedestroy( $image );
				imagedestroy( $flat );

				if ( is_string( $flat_png ) && 0 === strpos( $flat_png, "\x89PNG" ) ) {
					$png = $flat_png;
				}
			}
		}

		// A random part keeps signature files from being found by guessing
		// submission IDs.
		$filename = sprintf(
			'ccf-signature-%d-%s-%s.png',
			(int) $submission_id,
			sanitize_file_name( $slug ),
			wp_generate_password( 12, false )
		);

		$upload = wp_upload_bits( $filename, null, $png );

		if ( ! empty( $upload['error'] ) || empty( $upload['file'] ) || empty( $upload['url'] ) ) {
			return '';
		}

		$attachment_id = wp_insert_attachment(
			array(
				'post_mime_type' => 'image/png',
				'post_title'     => sanitize_file_name( $slug ) . '-' . (int) $submission_id,
				'post_status'    => 'inherit',
				'post_content'   => '',
			),
			$upload['file'],
			$submission_id
		);

		if ( is_wp_error( $attachment_id ) || ! $attachment_id ) {
			wp_delete_file( $upload['file'] );
			return '';
		}

		return esc_url_raw( $upload['url'] );
	}

	/**
	 * Front-end markup. The canvas is added by JavaScript; the hidden input
	 * carries the drawing to the server.
	 *
	 * @param int $field_id Field ID.
	 * @param int $form_id  Form ID.
	 * @return string
	 */
	public function render( $field_id, $form_id ) {
		// Some page builders render forms before wp_enqueue_scripts runs.
		if ( ! wp_script_is( 'ccf-signature', 'registered' ) ) {
			$this->register_frontend();
		}

		wp_enqueue_script( 'ccf-signature' );
		wp_enqueue_style( 'ccf-signature' );

		$slug        = get_post_meta( $field_id, 'ccf_field_slug', true );
		$label       = get_post_meta( $field_id, 'ccf_field_label', true );
		$description = get_post_meta( $field_id, 'ccf_field_description', true );
		$required    = get_post_meta( $field_id, 'ccf_field_required', true );
		$class_name  = get_post_meta( $field_id, 'ccf_field_className', true );
		$field_width = get_post_meta( $field_id, 'ccf_field_fieldWidth', true );

		if ( ! empty( $field_width ) ) {
			$class_name .= ' ccf-width-' . $field_width;
		}

		$errors = CCF_Form_Handler::factory()->get_errors( $form_id, $slug );

		ob_start();
		?>
		<div data-field-type="signature" data-field-slug="<?php echo esc_attr( $slug ); ?>" class="form-group <?php if ( ! empty( $errors ) ) : ?>field-error has-error<?php endif; ?> field <?php echo esc_attr( $slug ); ?> field-type-signature field-<?php echo (int) $field_id; ?> <?php echo esc_attr( $class_name ); ?> <?php if ( ! empty( $required ) ) : ?>field-required<?php endif; ?>">
			<label class="main-label" id="ccf_field_<?php echo esc_attr( $slug ); ?>_label">
				<?php if ( ! empty( $required ) ) : ?><span class="required">*</span><?php endif; ?>
				<?php echo esc_html( $label ); ?>
			</label>

			<div class="ccf-signature-wrap" data-label-id="ccf_field_<?php echo esc_attr( $slug ); ?>_label">
				<div class="ccf-signature-pad"></div>
				<div class="ccf-signature-actions">
					<span class="ccf-signature-hint"><?php esc_html_e( 'Sign in the box using your mouse, finger or stylus.', 'custom-contact-forms' ); ?></span>
					<button type="button" class="ccf-signature-clear"><?php esc_html_e( 'Clear', 'custom-contact-forms' ); ?></button>
				</div>
				<input type="hidden" class="ccf-signature-value field-input" name="ccf_field_<?php echo esc_attr( $slug ); ?>" id="ccf_field_<?php echo esc_attr( $slug ); ?>" value="" <?php if ( ! empty( $required ) ) : ?>aria-required="true"<?php endif; ?>>
			</div>

			<?php if ( ! empty( $errors ) ) : ?>
				<div class="error required-error"><?php echo esc_html( is_array( $errors ) ? reset( $errors ) : $errors ); ?></div>
			<?php endif; ?>

			<?php if ( ! empty( $description ) ) : ?>
				<div class="field-description help-block text-muted"><?php echo esc_html( $description ); ?></div>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Register front-end assets. They are enqueued only when a form with a
	 * signature field is rendered.
	 */
	public function register_frontend() {
		wp_register_script( 'ccf-signature-pad', plugins_url( '/assets/vendor/signature-pad/signature_pad.umd.js', dirname( __FILE__ ) ), array(), '4.2.0', true );
		wp_register_script( 'ccf-signature', plugins_url( '/assets/js/ccf-signature.js', dirname( __FILE__ ) ), array( 'ccf-signature-pad', 'ccf-form' ), CCF_VERSION, true );
		wp_localize_script(
			'ccf-signature',
			'ccfSignature',
			array(
				'canvasLabel' => esc_html__( 'Signature pad', 'custom-contact-forms' ),
			)
		);
		wp_register_style( 'ccf-signature', plugins_url( '/build/css/ccf-signature.css', dirname( __FILE__ ) ), array(), CCF_VERSION );
	}

	/**
	 * Builder script that registers the field's model and settings view.
	 *
	 * @param string $hook Current admin page.
	 */
	public function enqueue_builder( $hook ) {
		if ( 'post.php' !== $hook && 'post-new.php' !== $hook ) {
			return;
		}

		$screen = get_current_screen();

		if ( ! $screen || 'ccf_form' !== $screen->post_type ) {
			return;
		}

		wp_enqueue_script( 'ccf-signature-builder', plugins_url( '/assets/js/ccf-signature-builder.js', dirname( __FILE__ ) ), array( 'ccf-form-manager' ), CCF_VERSION, true );
	}

	/**
	 * Settings panel shown in the builder when the field is selected.
	 */
	public function builder_template() {
		?>
		<script type="text/html" id="ccf-signature-template">
			<div class="accordion-section <# if ( 'basic' === startPanel ) { #>expanded<# } #>">
				<a class="accordion-heading"><?php esc_html_e( 'Basic', 'custom-contact-forms' ); ?></a>
				<div class="section-content">
					<div>
						<label><span class="required">*</span> <?php esc_html_e( 'Internal Unique Slug', 'custom-contact-forms' ); ?> (a-z, 0-9, -, _):</label>
						<input class="field-slug" type="text" value="{{ field.slug }}">
					</div>
					<div>
						<label><?php esc_html_e( 'Label:', 'custom-contact-forms' ); ?></label>
						<input class="field-label" type="text" value="{{ field.label }}">
					</div>
					<div>
						<label><?php esc_html_e( 'Description:', 'custom-contact-forms' ); ?></label>
						<textarea class="field-description" rows="2">{{ field.description }}</textarea>
					</div>
					<div>
						<label><?php esc_html_e( 'Required:', 'custom-contact-forms' ); ?></label>
						<select class="field-required">
							<option value="1" <# if ( field.required ) { #>selected<# } #>><?php esc_html_e( 'Yes', 'custom-contact-forms' ); ?></option>
							<option value="0" <# if ( ! field.required ) { #>selected<# } #>><?php esc_html_e( 'No', 'custom-contact-forms' ); ?></option>
						</select>
					</div>
					<p class="explain"><?php esc_html_e( 'Visitors sign with a mouse, finger or stylus. Each signature is saved as an image with the submission and linked in notification emails.', 'custom-contact-forms' ); ?></p>
				</div>
			</div>
			<div class="accordion-section <# if ( 'advanced' === startPanel ) { #>expanded<# } #>">
				<a class="accordion-heading"><?php esc_html_e( 'Advanced', 'custom-contact-forms' ); ?></a>
				<div class="section-content">
					<div>
						<label><?php esc_html_e( 'Class Name:', 'custom-contact-forms' ); ?></label>
						<input class="field-class-name" type="text" value="{{ field.className }}">
					</div>
					<div>
						<label><?php esc_html_e( 'Field Width:', 'custom-contact-forms' ); ?></label>
						<select class="field-width">
							<option value="" <# if ( ! field.fieldWidth ) { #>selected<# } #>><?php esc_html_e( 'Full Width', 'custom-contact-forms' ); ?></option>
							<option value="half" <# if ( 'half' === field.fieldWidth ) { #>selected<# } #>><?php esc_html_e( 'Half (1/2)', 'custom-contact-forms' ); ?></option>
							<option value="two-thirds" <# if ( 'two-thirds' === field.fieldWidth ) { #>selected<# } #>><?php esc_html_e( 'Two Thirds (2/3)', 'custom-contact-forms' ); ?></option>
						</select>
					</div>
				</div>
			</div>
		</script>
		<?php
	}

	/**
	 * Return the singleton instance.
	 *
	 * @return CCF_Signature_Field
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
