<?php
/**
 * Weekly email summary.
 *
 * Every Monday morning (site time) the site sends its own admin a short
 * email: entries and views per form for the past seven days, the change
 * from the week before, and blocked spam. It is sent by the site through
 * wp_mail() and nothing is sent to us. Weeks with no activity are skipped.
 * It can be turned off from the email itself or under Forms > Settings.
 *
 * @package Custom_Contact_Forms
 * @since   7.18.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CCF_Email_Summary {

	const OPTION = 'ccf_summary_settings';

	const HOOK = 'ccf_weekly_summary';

	public function setup() {
		add_action( 'init', array( $this, 'schedule' ) );
		add_action( self::HOOK, array( $this, 'send_scheduled' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ), 30 );
		add_action( 'admin_post_ccf_summary_test', array( $this, 'handle_test' ) );
		add_action( 'admin_post_ccf_summary_off', array( $this, 'handle_off' ) );
		add_action( 'admin_post_nopriv_ccf_summary_off', array( $this, 'handle_off' ) );
		add_action( 'admin_notices', array( $this, 'test_notice' ) );
	}

	/**
	 * Result of "Send me one now".
	 */
	public function test_notice() {
		if ( ! isset( $_GET['ccf_summary_sent'], $_GET['page'] ) || 'ccf-settings' !== $_GET['page'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		if ( '1' === $_GET['ccf_summary_sent'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			/* translators: %s: email address */
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( sprintf( __( 'Weekly summary sent to %s.', 'custom-contact-forms' ), $this->recipient() ) ) . '</p></div>';
		} else {
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'The summary could not be sent. Check the email diagnostics under Forms > Settings.', 'custom-contact-forms' ) . '</p></div>';
		}
	}

	/**
	 * Settings with defaults. On unless turned off.
	 *
	 * @return array
	 */
	public function settings() {
		$saved = get_option( self::OPTION, array() );

		return wp_parse_args(
			is_array( $saved ) ? $saved : array(),
			array(
				'enabled'   => true,
				'recipient' => '',
			)
		);
	}

	/**
	 * Address the summary goes to.
	 *
	 * @return string
	 */
	public function recipient() {
		$settings = $this->settings();
		$email    = sanitize_email( $settings['recipient'] );

		return is_email( $email ) ? $email : get_option( 'admin_email' );
	}

	/* --------------------------------------------------------------------- */
	/* Scheduling                                                             */
	/* --------------------------------------------------------------------- */

	/**
	 * Next Monday at 8:00 in the site's time zone, as a timestamp.
	 *
	 * @return int
	 */
	public function next_send_time() {
		$tz   = wp_timezone();
		$next = new DateTimeImmutable( 'monday this week 08:00', $tz );
		$now  = new DateTimeImmutable( 'now', $tz );

		if ( $next <= $now ) {
			$next = $next->modify( '+1 week' );
		}

		return $next->getTimestamp();
	}

	public function schedule() {
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( $this->next_send_time(), 'weekly', self::HOOK );
		}
	}

	/* --------------------------------------------------------------------- */
	/* Building and sending                                                   */
	/* --------------------------------------------------------------------- */

	/**
	 * The past seven full days, and the seven before that.
	 *
	 * @return array { report, previous }
	 */
	public function data() {
		$today = new DateTimeImmutable( 'today', wp_timezone() );
		$end   = $today->modify( '-1 day' );
		$start = $today->modify( '-7 days' );

		return array(
			'report'   => CCF_Stats::report( $start->format( 'Y-m-d' ), $end->format( 'Y-m-d' ) ),
			'previous' => CCF_Stats::report( $start->modify( '-7 days' )->format( 'Y-m-d' ), $start->modify( '-1 day' )->format( 'Y-m-d' ) ),
		);
	}

	public function send_scheduled() {
		$settings = $this->settings();

		if ( empty( $settings['enabled'] ) ) {
			return;
		}

		$this->send( false );
	}

	/**
	 * Build and send the summary.
	 *
	 * @param bool $force Send even when there was no activity.
	 * @return bool Whether an email was sent.
	 */
	public function send( $force ) {
		$data   = $this->data();
		$totals = $data['report']['totals'];

		if ( ! $force ) {
			if ( empty( $totals['forms'] ) ) {
				return false;
			}

			if ( 0 === (int) $totals['entries'] + (int) $totals['views'] + (int) $totals['spam'] ) {
				return false;
			}
		}

		$site = ccf_site_name();

		$subject = sprintf(
			/* translators: 1: site name, 2: number of entries */
			_n( '[%1$s] %2$s new form entry this week', '[%1$s] %2$s new form entries this week', (int) $totals['entries'], 'custom-contact-forms' ),
			$site,
			number_format_i18n( (int) $totals['entries'] )
		);

		$headers = array( 'Content-Type: text/html; charset=UTF-8' );

		return ccf_mail( $site, $this->recipient(), $subject, $this->html( $data ), $headers );
	}

	/**
	 * "+3 from last week" style text.
	 *
	 * @param int $now  This week.
	 * @param int $then Last week.
	 * @return string
	 */
	private function change( $now, $then ) {
		$diff = (int) $now - (int) $then;

		if ( 0 === $diff ) {
			return __( 'Same as last week', 'custom-contact-forms' );
		}

		/* translators: %s: signed number, such as +3 or -2 */
		return sprintf( __( '%s from last week', 'custom-contact-forms' ), ( $diff > 0 ? '+' : '−' ) . number_format_i18n( abs( $diff ) ) );
	}

	/**
	 * One tip about a free feature, rotating by week. Pro gets its own line
	 * further down the email instead (see html()).
	 *
	 * @return array { text, url, link }
	 */
	private function tip() {
		$settings_url = admin_url( 'edit.php?post_type=ccf_form&page=ccf-settings' );

		$tips = array(
			array(
				'text' => __( 'Send new entries to Zapier, Make or n8n. Open a form in the builder, then Form Settings > Webhooks.', 'custom-contact-forms' ),
				'url'  => admin_url( 'edit.php?post_type=ccf_form' ),
				'link' => __( 'Open your forms', 'custom-contact-forms' ),
			),
			array(
				'text' => __( 'Add people who fill in your forms to your Mailchimp audience, with double opt-in.', 'custom-contact-forms' ),
				'url'  => ccf_integrations_url( 'mailchimp' ),
				'link' => __( 'Connect Mailchimp', 'custom-contact-forms' ),
			),
			array(
				'text' => __( 'Collect signatures on agreements, waivers and sign-up forms with the free Signature field.', 'custom-contact-forms' ),
				'url'  => admin_url( 'edit.php?post_type=ccf_form' ),
				'link' => __( 'Add a signature field', 'custom-contact-forms' ),
			),
		);

		$tips[] = array(
			'text' => __( 'Turn on Akismet checks so entries it flags go to a spam folder you can review, instead of being thrown away.', 'custom-contact-forms' ),
			'url'  => ccf_integrations_url( 'akismet' ),
			'link' => __( 'Set up Akismet', 'custom-contact-forms' ),
		);
		$tips[] = array(
			'text' => __( 'Stop bots without puzzles. Cloudflare Turnstile and hCaptcha are free to set up.', 'custom-contact-forms' ),
			'url'  => ccf_integrations_url( 'turnstile' ),
			'link' => __( 'Open Integrations', 'custom-contact-forms' ),
		);

		$week = (int) wp_date( 'W' );

		return $tips[ $week % count( $tips ) ];
	}

	/**
	 * Signed link that turns the summary off without logging in.
	 *
	 * @return string
	 */
	public function off_url() {
		return add_query_arg(
			array(
				'action' => 'ccf_summary_off',
				'token'  => $this->token(),
			),
			admin_url( 'admin-post.php' )
		);
	}

	private function token() {
		return hash_hmac( 'sha256', 'ccf_summary_off|' . $this->recipient(), wp_salt( 'auth' ) );
	}

	/**
	 * Email body.
	 *
	 * @param array $data From data().
	 * @return string
	 */
	public function html( $data ) {
		$report   = $data['report'];
		$previous = $data['previous'];
		$totals   = $report['totals'];
		$site     = ccf_site_name();
		/* translators: 1: first day, 2: last day */
		$range    = sprintf( __( '%1$s to %2$s', 'custom-contact-forms' ), wp_date( 'M j', strtotime( $report['start'] . ' 12:00:00' ) ), wp_date( 'M j', strtotime( $report['end'] . ' 12:00:00' ) ) );
		$tip      = $this->tip();
		$forms    = array_slice(
			array_values(
				array_filter(
					$report['forms'],
					function ( $form ) {
						return $form['entries'] > 0 || $form['views'] > 0;
					}
				)
			),
			0,
			6
		);

		$ink    = '#1d2327';
		$muted  = '#646970';
		$line   = '#dcdcde';
		$accent = '#2271b1';

		$stat = function ( $label, $value, $note ) use ( $ink, $muted, $line ) {
			return '<td style="width:33%;padding:14px 12px;border:1px solid ' . $line . ';border-radius:6px;vertical-align:top;">'
				. '<div style="font-size:12px;color:' . $muted . ';text-transform:uppercase;letter-spacing:.04em;">' . esc_html( $label ) . '</div>'
				. '<div style="font-size:26px;font-weight:700;color:' . $ink . ';margin-top:4px;">' . esc_html( $value ) . '</div>'
				. '<div style="font-size:12px;color:' . $muted . ';margin-top:2px;">' . esc_html( $note ) . '</div>'
				. '</td>';
		};

		ob_start();
		?>
<!DOCTYPE html>
<html>
<body style="margin:0;padding:0;background:#f0f0f1;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f0f0f1;">
<tr><td align="center" style="padding:24px 12px;">
<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:8px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:<?php echo esc_attr( $ink ); ?>;">
	<tr><td style="padding:24px 24px 8px;">
		<div style="font-size:13px;color:<?php echo esc_attr( $muted ); ?>;"><?php echo esc_html( $site ); ?> · <?php echo esc_html( $range ); ?></div>
		<h1 style="font-size:20px;margin:6px 0 0;"><?php esc_html_e( 'Your forms this week', 'custom-contact-forms' ); ?></h1>
	</td></tr>

	<tr><td style="padding:12px 24px;">
		<table role="presentation" width="100%" cellpadding="0" cellspacing="6"><tr>
			<?php
			echo $stat( __( 'Entries', 'custom-contact-forms' ), number_format_i18n( (int) $totals['entries'] ), $this->change( $totals['entries'], $previous['totals']['entries'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo $stat( __( 'Form views', 'custom-contact-forms' ), number_format_i18n( (int) $totals['views'] ), $this->change( $totals['views'], $previous['totals']['views'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo $stat( __( 'Conversion', 'custom-contact-forms' ), null === $totals['conversion'] ? __( 'n/a', 'custom-contact-forms' ) : $totals['conversion'] . '%', __( 'Entries per view', 'custom-contact-forms' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			?>
		</tr></table>
		<p style="font-size:13px;color:<?php echo esc_attr( $muted ); ?>;margin:8px 6px 0;">
			<?php
			/* translators: %s: number of blocked spam submissions */
			echo esc_html( sprintf( _n( '%s spam submission blocked.', '%s spam submissions blocked.', (int) $totals['spam'], 'custom-contact-forms' ), number_format_i18n( (int) $totals['spam'] ) ) );
			?>
		</p>
	</td></tr>

	<?php if ( $forms ) : ?>
	<tr><td style="padding:8px 24px;">
		<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="width:100%;font-size:14px;border-collapse:collapse;">
			<tr>
				<th align="left" style="text-align:left;padding:8px 6px;border-bottom:1px solid <?php echo esc_attr( $line ); ?>;font-size:12px;color:<?php echo esc_attr( $muted ); ?>;font-weight:600;"><?php esc_html_e( 'Form', 'custom-contact-forms' ); ?></th>
				<th align="right" width="80" style="width:80px;text-align:right;padding:8px 6px;border-bottom:1px solid <?php echo esc_attr( $line ); ?>;font-size:12px;color:<?php echo esc_attr( $muted ); ?>;font-weight:600;"><?php esc_html_e( 'Entries', 'custom-contact-forms' ); ?></th>
				<th align="right" width="80" style="width:80px;text-align:right;padding:8px 6px;border-bottom:1px solid <?php echo esc_attr( $line ); ?>;font-size:12px;color:<?php echo esc_attr( $muted ); ?>;font-weight:600;"><?php esc_html_e( 'Views', 'custom-contact-forms' ); ?></th>
			</tr>
			<?php foreach ( $forms as $form ) : ?>
			<tr>
				<td style="padding:8px 6px;border-bottom:1px solid <?php echo esc_attr( $line ); ?>;"><a href="<?php echo esc_url( admin_url( 'post.php?post=' . (int) $form['id'] . '&action=edit' ) ); ?>" style="color:<?php echo esc_attr( $accent ); ?>;text-decoration:none;"><?php echo esc_html( $form['title'] ); ?></a></td>
				<td align="right" width="80" style="width:80px;text-align:right;padding:8px 6px;border-bottom:1px solid <?php echo esc_attr( $line ); ?>;"><?php echo esc_html( number_format_i18n( (int) $form['entries'] ) ); ?></td>
				<td align="right" width="80" style="width:80px;text-align:right;padding:8px 6px;border-bottom:1px solid <?php echo esc_attr( $line ); ?>;"><?php echo esc_html( number_format_i18n( (int) $form['views'] ) ); ?></td>
			</tr>
			<?php endforeach; ?>
		</table>
	</td></tr>
	<?php endif; ?>

	<tr><td style="padding:16px 24px;">
		<a href="<?php echo esc_url( CCF_Dashboard::url() ); ?>" style="display:inline-block;background:<?php echo esc_attr( $accent ); ?>;color:#ffffff;text-decoration:none;padding:10px 16px;border-radius:6px;font-size:14px;font-weight:600;"><?php esc_html_e( 'Open the Forms dashboard', 'custom-contact-forms' ); ?></a>
	</td></tr>

	<tr><td style="padding:4px 24px 20px;">
		<div style="background:#f6f7f7;border-radius:6px;padding:12px 14px;font-size:13px;line-height:1.5;">
			<strong><?php esc_html_e( 'Tip:', 'custom-contact-forms' ); ?></strong>
			<?php echo esc_html( $tip['text'] ); ?>
			<a href="<?php echo esc_url( $tip['url'] ); ?>" style="color:<?php echo esc_attr( $accent ); ?>;"><?php echo esc_html( $tip['link'] ); ?></a>
		</div>
	</td></tr>

	<?php if ( ! defined( 'CCFP_VERSION' ) ) : ?>
	<tr><td style="padding:0 24px 20px;">
		<div style="border:1px solid <?php echo esc_attr( $line ); ?>;border-radius:6px;padding:12px 14px;font-size:13px;line-height:1.5;">
			<strong><?php esc_html_e( 'Do more with your forms:', 'custom-contact-forms' ); ?></strong>
			<?php esc_html_e( 'take Stripe and PayPal payments, send PDF receipts, record signed agreements and build multi-step forms with Custom Contact Forms Pro.', 'custom-contact-forms' ); ?>
			<a href="<?php echo esc_url( ccf_pro_url( 'weekly-summary' ) ); ?>" style="color:<?php echo esc_attr( $accent ); ?>;"><?php esc_html_e( 'See Pro', 'custom-contact-forms' ); ?></a>
		</div>
	</td></tr>
	<?php endif; ?>

	<tr><td style="padding:0 24px 24px;font-size:12px;color:<?php echo esc_attr( $muted ); ?>;line-height:1.5;">
		<?php esc_html_e( 'Sent by Custom Contact Forms on your site. Views and entries are counted on your site and never leave it.', 'custom-contact-forms' ); ?>
		<a href="<?php echo esc_url( $this->off_url() ); ?>" style="color:<?php echo esc_attr( $muted ); ?>;"><?php esc_html_e( 'Turn off weekly summaries', 'custom-contact-forms' ); ?></a>
	</td></tr>
</table>
</td></tr>
</table>
</body>
</html>
		<?php
		return (string) ob_get_clean();
	}

	/* --------------------------------------------------------------------- */
	/* Settings                                                               */
	/* --------------------------------------------------------------------- */

	public function register_settings() {
		register_setting(
			'ccf-settings',
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
			)
		);

		add_settings_section(
			'ccf-email-summary',
			esc_html__( 'Weekly Summary', 'custom-contact-forms' ),
			array( $this, 'section_description' ),
			'custom-contact-forms'
		);

		add_settings_field( 'ccf-summary-enabled', esc_html__( 'Weekly summary email', 'custom-contact-forms' ), array( $this, 'field_enabled' ), 'custom-contact-forms', 'ccf-email-summary' );
		add_settings_field( 'ccf-summary-recipient', esc_html__( 'Send to', 'custom-contact-forms' ), array( $this, 'field_recipient' ), 'custom-contact-forms', 'ccf-email-summary' );
	}

	public function sanitize_settings( $input ) {
		if ( ! is_array( $input ) ) {
			return $this->settings();
		}

		$email = isset( $input['recipient'] ) ? sanitize_email( $input['recipient'] ) : '';

		return array(
			'enabled'   => ! empty( $input['enabled'] ),
			'recipient' => is_email( $email ) ? $email : '',
		);
	}

	public function section_description() {
		echo '<p>' . esc_html__( 'Every Monday morning, get entries, views and blocked spam for the past week. Weeks with no activity are skipped.', 'custom-contact-forms' ) . '</p>';
	}

	public function field_enabled() {
		$settings = $this->settings();
		?>
		<input type="hidden" name="<?php echo esc_attr( self::OPTION ); ?>[present]" value="1">
		<label>
			<input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[enabled]" value="1" <?php checked( ! empty( $settings['enabled'] ) ); ?>>
			<?php esc_html_e( 'Email me a weekly summary of my forms', 'custom-contact-forms' ); ?>
		</label>
		<p>
			<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=ccf_summary_test' ), 'ccf_summary_test' ) ); ?>"><?php esc_html_e( 'Send me one now', 'custom-contact-forms' ); ?></a>
		</p>
		<?php
	}

	public function field_recipient() {
		$settings = $this->settings();
		?>
		<input type="email" class="regular-text" name="<?php echo esc_attr( self::OPTION ); ?>[recipient]" value="<?php echo esc_attr( $settings['recipient'] ); ?>" placeholder="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>">
		<p class="description"><?php esc_html_e( 'Leave empty to use the site admin email.', 'custom-contact-forms' ); ?></p>
		<?php
	}

	public function handle_test() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'custom-contact-forms' ) );
		}

		check_admin_referer( 'ccf_summary_test' );

		$sent = $this->send( true );

		wp_safe_redirect( add_query_arg( 'ccf_summary_sent', $sent ? '1' : '0', admin_url( 'edit.php?post_type=ccf_form&page=ccf-settings' ) ) );
		exit;
	}

	public function handle_off() {
		$token = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : '';

		if ( ! hash_equals( $this->token(), $token ) ) {
			wp_die( esc_html__( 'This link is not valid any more. You can turn weekly summaries off under Forms > Settings.', 'custom-contact-forms' ), '', array( 'response' => 403 ) );
		}

		$settings            = $this->settings();
		$settings['enabled'] = false;
		update_option( self::OPTION, $settings );

		wp_die(
			esc_html__( 'Weekly summaries are off. You can turn them back on under Forms > Settings.', 'custom-contact-forms' ),
			esc_html__( 'Weekly summaries turned off', 'custom-contact-forms' ),
			array( 'response' => 200 )
		);
	}

	/**
	 * @return CCF_Email_Summary
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
