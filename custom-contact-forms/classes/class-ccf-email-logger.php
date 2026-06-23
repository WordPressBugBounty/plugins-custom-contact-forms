<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Email diagnostics and logging for CCF.
 *
 * - Logs wp_mail() failures to a transient-based log
 * - Provides a "Send Test Email" AJAX button in settings
 * - Shows recent email failures in the admin
 *
 * @since 7.9.0
 */
class CCF_Email_Logger {

	/** @var int Max failures to store */
	const MAX_LOG_ENTRIES = 50;

	public function __construct() {}

	/**
	 * Setup hooks
	 *
	 * @since 7.9.0
	 */
	public function setup() {
		add_action( 'wp_mail_failed', array( $this, 'log_failure' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'wp_ajax_ccf_send_test_email', array( $this, 'ajax_send_test_email' ) );
	}

	/**
	 * Register settings section
	 *
	 * @since 7.9.0
	 */
	public function register_settings() {
		add_settings_section(
			'ccf-email-diagnostics',
			esc_html__( 'Email Diagnostics', 'custom-contact-forms' ),
			array( $this, 'section_output' ),
			'custom-contact-forms'
		);
	}

	/**
	 * Output the email diagnostics section
	 *
	 * @since 7.9.0
	 */
	public function section_output() {
		$log = $this->get_log();
		$admin_email = get_option( 'admin_email' );
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Test Notification', 'custom-contact-forms' ); ?></th>
				<td>
					<button type="button" class="button" id="ccf-send-test-email">
						<?php esc_html_e( 'Send Test Email', 'custom-contact-forms' ); ?>
					</button>
					<span id="ccf-test-email-status" style="margin-left:10px;"></span>
					<p class="description">
						<?php
						printf(
							/* translators: %s: admin email address */
							esc_html__( 'Sends a test email to %s to verify your server can deliver emails.', 'custom-contact-forms' ),
							'<code>' . esc_html( $admin_email ) . '</code>'
						);
						?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Recent Failures', 'custom-contact-forms' ); ?></th>
				<td>
					<?php if ( empty( $log ) ) : ?>
						<p style="color:#0a6b0e;font-weight:500;">
							<?php esc_html_e( 'No email failures recorded.', 'custom-contact-forms' ); ?>
						</p>
					<?php else : ?>
						<details>
							<summary style="cursor:pointer;font-weight:500;color:#d63638;">
								<?php
								printf(
									esc_html(
										/* translators: %d: number of failed emails */
										_n( '%d failed email', '%d failed emails', count( $log ), 'custom-contact-forms' )
									),
									count( $log )
								);
								?>
							</summary>
							<table class="widefat striped" style="margin-top:8px;max-width:700px;">
								<thead>
									<tr>
										<th><?php esc_html_e( 'Date', 'custom-contact-forms' ); ?></th>
										<th><?php esc_html_e( 'To', 'custom-contact-forms' ); ?></th>
										<th><?php esc_html_e( 'Error', 'custom-contact-forms' ); ?></th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ( array_slice( array_reverse( $log ), 0, 20 ) as $entry ) : ?>
										<tr>
											<td><?php echo esc_html( isset( $entry['date'] ) ? $entry['date'] : '-' ); ?></td>
											<td><?php echo esc_html( isset( $entry['to'] ) ? $entry['to'] : '-' ); ?></td>
											<td><small><?php echo esc_html( isset( $entry['error'] ) ? $entry['error'] : '-' ); ?></small></td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
							<p>
								<button type="button" class="button button-link-delete" id="ccf-clear-email-log">
									<?php esc_html_e( 'Clear Log', 'custom-contact-forms' ); ?>
								</button>
							</p>
						</details>
					<?php endif; ?>
				</td>
			</tr>
		</table>

		<?php
		wp_enqueue_script( 'ccf-email-diag', plugins_url( '/assets/js/ccf-email-diag.js', dirname( __FILE__ ) ), array( 'jquery' ), CCF_VERSION, true );
		wp_localize_script( 'ccf-email-diag', 'ccfEmailDiagL10n', array(
			'nonce'   => wp_create_nonce( 'ccf_test_email' ),
			'sending' => esc_html__( 'Sending...', 'custom-contact-forms' ),
			'failed'  => esc_html__( 'Request failed.', 'custom-contact-forms' ),
		) );
		?>
		<?php
	}

	/**
	 * Log a wp_mail failure
	 *
	 * @param WP_Error $error
	 * @since 7.9.0
	 */
	public function log_failure( $error ) {
		if ( ! is_wp_error( $error ) ) {
			return;
		}

		$log = $this->get_log();

		$mail_data = $error->get_error_data();
		$to = '';
		if ( is_array( $mail_data ) && isset( $mail_data['to'] ) ) {
			$to = is_array( $mail_data['to'] ) ? implode( ', ', $mail_data['to'] ) : $mail_data['to'];
		}

		$log[] = array(
			'date'  => wp_date( 'Y-m-d H:i:s' ),
			'to'    => sanitize_text_field( $to ),
			'error' => sanitize_text_field( $error->get_error_message() ),
		);

		// Keep only the last N entries
		if ( count( $log ) > self::MAX_LOG_ENTRIES ) {
			$log = array_slice( $log, -self::MAX_LOG_ENTRIES );
		}

		update_option( 'ccf_email_failure_log', $log, false );
	}

	/**
	 * Get the failure log
	 *
	 * @since 7.9.0
	 * @return array
	 */
	public function get_log() {
		$log = get_option( 'ccf_email_failure_log', array() );
		return is_array( $log ) ? $log : array();
	}

	/**
	 * AJAX: Send test email or clear log
	 *
	 * @since 7.9.0
	 */
	public function ajax_send_test_email() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Permission denied.', 'custom-contact-forms' ) );
		}

		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'ccf_test_email' ) ) {
			wp_send_json_error( __( 'Security check failed.', 'custom-contact-forms' ) );
		}

		// Clear log
		if ( ! empty( $_POST['clear_log'] ) ) {
			delete_option( 'ccf_email_failure_log' );
			wp_send_json_success( __( 'Log cleared.', 'custom-contact-forms' ) );
		}

		// Send test
		$to = get_option( 'admin_email' );
		/* translators: %s: site name */
		$subject = sprintf( __( '[%s] CCF Test Email', 'custom-contact-forms' ), get_bloginfo( 'name' ) );
		/* translators: %1$s: current date/time, %2$s: site URL */
		$message = sprintf(
			__( "This is a test email from Custom Contact Forms.\n\nIf you received this, your server can send emails successfully.\n\nSent at: %1\$s\nSite: %2\$s", 'custom-contact-forms' ),
			wp_date( 'Y-m-d H:i:s' ),
			home_url()
		);

		$sent = wp_mail( $to, $subject, $message );

		if ( $sent ) {
			/* translators: %s: email address */
			wp_send_json_success( sprintf( __( 'Test email sent to %s', 'custom-contact-forms' ), $to ) );
		} else {
			wp_send_json_error( __( 'Failed to send. Check the failure log below after refreshing.', 'custom-contact-forms' ) );
		}
	}

	/**
	 * @since 7.9.0
	 * @return CCF_Email_Logger
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
