<?php
/**
 * Form builder settings added in 7.21.0: accent color, schedule and entry
 * limits, and automatic entry cleanup. Added under Form Settings by
 * assets/js/ccf-form-settings-extras.js.
 *
 * @package Custom_Contact_Forms
 * @since   7.21.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CCF_Form_Settings_Extras {

	public function setup() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ), 21 );
		add_action( 'customize_controls_enqueue_scripts', array( $this, 'enqueue' ), 21 );
		add_action( 'ccf_underscore_templates', array( $this, 'templates' ) );
	}

	/**
	 * Load the builder script wherever the form builder loads.
	 */
	public function enqueue() {
		if ( ! wp_script_is( 'ccf-form-manager', 'enqueued' ) ) {
			return;
		}

		$deps = array( 'ccf-form-manager', 'jquery', 'underscore' );

		if ( wp_script_is( 'ccf-integrations-builder', 'enqueued' ) ) {
			$deps[] = 'ccf-integrations-builder';
		}

		wp_enqueue_script(
			'ccf-form-settings-extras',
			plugins_url( '/assets/js/ccf-form-settings-extras.js', dirname( __FILE__ ) ),
			$deps,
			CCF_VERSION,
			true
		);

		wp_localize_script(
			'ccf-form-settings-extras',
			'ccfFormExtras',
			array(
				'presets'  => array_keys( CCF_Form_Styles::presets() ),
				'messages' => CCF_Form_Availability::default_messages(),
				'i18n'     => array(
					/* translators: %s: number of entries */
					'entriesSoFar' => __( '%s entries so far.', 'custom-contact-forms' ),
					'oneEntry'     => __( '1 entry so far.', 'custom-contact-forms' ),
					'closeBefore'  => __( 'The closing date is before the opening date, so this form would never open.', 'custom-contact-forms' ),
				),
			)
		);
	}

	/**
	 * Underscore templates for the new settings.
	 */
	public function templates() {
		$timezone = wp_timezone_string();
		?>
		<script type="text/html" id="ccf-form-accent-template">
			<p class="ccf-accent-row">
				<label for="ccf_form_accent_custom"><?php esc_html_e( 'Accent color:', 'custom-contact-forms' ); ?></label>
				<select class="ccf-accent-mode" id="ccf_form_accent_custom">
					<option value="0"><?php esc_html_e( 'Automatic', 'custom-contact-forms' ); ?></option>
					<option value="1" <# if ( data.accent ) { #>selected<# } #>><?php esc_html_e( 'Choose a color', 'custom-contact-forms' ); ?></option>
				</select>
				<input type="color" class="ccf-accent-color" value="{{ data.accent || '#2563eb' }}" aria-label="<?php esc_attr_e( 'Accent color', 'custom-contact-forms' ); ?>">
				<span class="explain"><?php esc_html_e( 'Used for the button and the outline around the field being filled in. Automatic uses your theme\'s color with "Match my site", and blue with the other looks.', 'custom-contact-forms' ); ?></span>
			</p>
		</script>

		<script type="text/html" id="ccf-form-availability-template">
			<h3><?php esc_html_e( 'Schedule and Entry Limit', 'custom-contact-forms' ); ?></h3>

			<p>
				<label for="ccf_form_open_at"><?php esc_html_e( 'Open the form on:', 'custom-contact-forms' ); ?></label>
				<input type="datetime-local" class="ccf-open-at" id="ccf_form_open_at" value="{{ data.openAt }}">
				<a href="#" class="ccf-clear-date" data-target=".ccf-open-at"><?php esc_html_e( 'Clear', 'custom-contact-forms' ); ?></a>
				<span class="explain"><?php esc_html_e( 'Leave empty to take entries right away.', 'custom-contact-forms' ); ?></span>
			</p>
			<p class="ccf-before-message-row">
				<label for="ccf_form_before_message"><?php esc_html_e( 'Message before it opens:', 'custom-contact-forms' ); ?></label>
				<textarea class="widefat ccf-before-message" id="ccf_form_before_message" rows="2" placeholder="{{ data.defaults.beforeMessage }}">{{ data.beforeMessage }}</textarea>
			</p>

			<p>
				<label for="ccf_form_close_at"><?php esc_html_e( 'Close the form on:', 'custom-contact-forms' ); ?></label>
				<input type="datetime-local" class="ccf-close-at" id="ccf_form_close_at" value="{{ data.closeAt }}">
				<a href="#" class="ccf-clear-date" data-target=".ccf-close-at"><?php esc_html_e( 'Clear', 'custom-contact-forms' ); ?></a>
				<span class="explain ccf-date-warning" style="display:none;color:#b32d2e;"></span>
				<span class="explain">
					<?php
					printf(
						/* translators: %s: time zone, for example America/Phoenix */
						esc_html__( 'Leave empty to keep it open. Times use your site\'s time zone (%s), set under Settings > General.', 'custom-contact-forms' ),
						esc_html( $timezone )
					);
					?>
				</span>
			</p>
			<p class="ccf-after-message-row">
				<label for="ccf_form_after_message"><?php esc_html_e( 'Message after it closes:', 'custom-contact-forms' ); ?></label>
				<textarea class="widefat ccf-after-message" id="ccf_form_after_message" rows="2" placeholder="{{ data.defaults.afterMessage }}">{{ data.afterMessage }}</textarea>
			</p>

			<p>
				<label for="ccf_form_entry_limit"><?php esc_html_e( 'Maximum number of entries:', 'custom-contact-forms' ); ?></label>
				<input type="number" min="0" step="1" class="ccf-entry-limit" id="ccf_form_entry_limit" value="<# if ( data.limit ) { #>{{ data.limit }}<# } #>" placeholder="<?php esc_attr_e( 'No limit', 'custom-contact-forms' ); ?>">
				<span class="explain ccf-entry-count"></span>
				<span class="explain"><?php esc_html_e( 'Good for event sign-ups, giveaways and classes with limited spots. Spam does not count toward the limit.', 'custom-contact-forms' ); ?></span>
			</p>
			<div class="ccf-limit-details">
				<p>
					<label for="ccf_form_limit_message"><?php esc_html_e( 'Message when full:', 'custom-contact-forms' ); ?></label>
					<textarea class="widefat ccf-limit-message" id="ccf_form_limit_message" rows="2" placeholder="{{ data.defaults.limitMessage }}">{{ data.limitMessage }}</textarea>
				</p>
				<p>
					<label for="ccf_form_show_remaining"><?php esc_html_e( 'Show spots left above the form:', 'custom-contact-forms' ); ?></label>
					<select class="ccf-show-remaining" id="ccf_form_show_remaining">
						<option value="0"><?php esc_html_e( 'No', 'custom-contact-forms' ); ?></option>
						<option value="1" <# if ( data.showRemaining ) { #>selected<# } #>><?php esc_html_e( 'Yes, for example "12 spots left"', 'custom-contact-forms' ); ?></option>
					</select>
				</p>
			</div>
		</script>

		<script type="text/html" id="ccf-form-cleanup-template">
			<h3><?php esc_html_e( 'Entry Cleanup', 'custom-contact-forms' ); ?></h3>
			<p>
				<label for="ccf_form_cleanup_days"><?php esc_html_e( 'Delete entries older than:', 'custom-contact-forms' ); ?></label>
				<select class="ccf-cleanup-days" id="ccf_form_cleanup_days">
					<?php foreach ( CCF_Entry_Cleanup::choices() as $days => $label ) : ?>
						<option value="<?php echo (int) $days; ?>" <# if ( <?php echo (int) $days; ?> === data.days ) { #>selected<# } #>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
				<span class="explain"><?php esc_html_e( 'Keeping personal data only as long as you need it helps with privacy laws such as GDPR. Old entries and their uploaded files are deleted once a day and cannot be restored, so export any you want to keep first.', 'custom-contact-forms' ); ?></span>
			</p>
		</script>
		<?php
	}

	/**
	 * @return CCF_Form_Settings_Extras
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
