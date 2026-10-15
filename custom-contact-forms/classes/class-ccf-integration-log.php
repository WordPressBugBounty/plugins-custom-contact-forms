<?php
/**
 * A short log of integration failures (email marketing, webhooks).
 *
 * Kept on the site only, capped at the most recent entries, and shown on the
 * settings page so a site owner can see why a subscriber or webhook did not
 * arrive without turning on debugging.
 *
 * @package Custom_Contact_Forms
 * @since   7.18.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CCF_Integration_Log {

	const OPTION = 'ccf_integration_log';

	const MAX_ENTRIES = 25;

	/**
	 * Record a failure.
	 *
	 * @param string $service Service label, such as "Mailchimp" or "Webhook".
	 * @param int    $form_id Form ID.
	 * @param string $message What went wrong.
	 */
	public static function add( $service, $form_id, $message ) {
		$log = get_option( self::OPTION, array() );

		if ( ! is_array( $log ) ) {
			$log = array();
		}

		array_unshift(
			$log,
			array(
				'time'    => time(),
				'service' => sanitize_text_field( $service ),
				'form_id' => (int) $form_id,
				'message' => mb_substr( sanitize_text_field( $message ), 0, 300 ),
			)
		);

		update_option( self::OPTION, array_slice( $log, 0, self::MAX_ENTRIES ), false );
	}

	/**
	 * Recent entries, newest first.
	 *
	 * @return array
	 */
	public static function get() {
		$log = get_option( self::OPTION, array() );

		return is_array( $log ) ? $log : array();
	}

	/**
	 * Clear the log.
	 */
	public static function clear() {
		delete_option( self::OPTION );
	}

	/**
	 * Print the log as a small table, or nothing when it is empty.
	 */
	public static function render() {
		$log = self::get();

		if ( empty( $log ) ) {
			echo '<p class="description">' . esc_html__( 'No integration errors recorded.', 'custom-contact-forms' ) . '</p>';
			return;
		}
		?>
		<table class="widefat striped" style="max-width:900px;">
			<thead>
				<tr>
					<th><?php esc_html_e( 'When', 'custom-contact-forms' ); ?></th>
					<th><?php esc_html_e( 'Service', 'custom-contact-forms' ); ?></th>
					<th><?php esc_html_e( 'Form', 'custom-contact-forms' ); ?></th>
					<th><?php esc_html_e( 'What happened', 'custom-contact-forms' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $log as $row ) : ?>
					<tr>
						<td><?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $row['time'] ) ); ?></td>
						<td><?php echo esc_html( $row['service'] ); ?></td>
						<td><?php echo esc_html( $row['form_id'] ? get_the_title( $row['form_id'] ) : '' ); ?></td>
						<td><?php echo esc_html( $row['message'] ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<p>
			<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=ccf_clear_integration_log' ), 'ccf_clear_integration_log' ) ); ?>"><?php esc_html_e( 'Clear log', 'custom-contact-forms' ); ?></a>
		</p>
		<?php
	}

	/**
	 * Handle the "Clear log" button.
	 */
	public static function handle_clear() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'custom-contact-forms' ) );
		}

		check_admin_referer( 'ccf_clear_integration_log' );
		self::clear();

		wp_safe_redirect( ccf_integrations_url() . '#ccf-problems' );
		exit;
	}
}

add_action( 'admin_post_ccf_clear_integration_log', array( 'CCF_Integration_Log', 'handle_clear' ) );
