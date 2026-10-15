<?php
/**
 * Review requests.
 *
 * Three quiet, dismissible touches, in line with WordPress.org guideline 11:
 *
 *  - A "How are we doing?" card on the Forms dashboard when the site's forms
 *    reach 10, 100, 500 and 1,000 entries. Each milestone is shown once. The
 *    visitor picks stars and can write a few words; "Post on WordPress.org"
 *    copies their words and opens the review page. Every rating gets the same
 *    options, and lower ratings also get a private way to reach us.
 *  - A line in the admin footer on Custom Contact Forms screens.
 *  - A link in the deactivation survey (see CCF_Feedback).
 *
 * Nothing is sent anywhere unless the user clicks "Send feedback", which
 * emails the maintainer through the site's own wp_mail().
 *
 * @package Custom_Contact_Forms
 * @since   7.15.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CCF_Review_Request {

	/**
	 * Entry totals that trigger the card.
	 */
	const MILESTONES = array( 10, 100, 500, 1000 );

	/**
	 * Stored state: 'done' once the user reviews or opts out for good,
	 * 'answered' for the highest milestone already shown and answered.
	 */
	const OPTION = 'ccf_review_request';

	/**
	 * Review page on WordPress.org.
	 */
	const REVIEW_URL = 'https://wordpress.org/support/plugin/custom-contact-forms/reviews/#new-post';

	/**
	 * Support forum on WordPress.org.
	 */
	const SUPPORT_URL = 'https://wordpress.org/support/plugin/custom-contact-forms/';

	/**
	 * Where "Send feedback" goes, the same address as the deactivation survey.
	 */
	const FEEDBACK_EMAIL = 'admin@oiopublisher.com';

	/**
	 * Return the singleton instance.
	 *
	 * @return CCF_Review_Request
	 */
	public static function factory() {
		static $instance;

		if ( ! $instance ) {
			$instance = new self();
			$instance->setup();
		}

		return $instance;
	}

	/**
	 * Register hooks.
	 */
	public function setup() {
		add_action( 'ccf_dashboard_top', array( $this, 'render_card' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'wp_ajax_ccf_review_response', array( $this, 'handle_response' ) );
		add_filter( 'admin_footer_text', array( $this, 'footer_text' ), 20 );
	}

	/**
	 * Stored state, always an array.
	 *
	 * @return array
	 */
	private function state() {
		$state = get_option( self::OPTION, array() );
		return is_array( $state ) ? $state : array();
	}

	/**
	 * Total saved entries.
	 *
	 * @return int
	 */
	private function total_entries() {
		$counts = wp_count_posts( 'ccf_submission' );
		return isset( $counts->publish ) ? (int) $counts->publish : 0;
	}

	/**
	 * The milestone to celebrate now, or 0 if there is none. Only the
	 * highest milestone reached is shown, so a busy site updating from an
	 * older version sees one card, not four.
	 *
	 * @return int
	 */
	public function current_milestone() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return 0;
		}

		$state = $this->state();

		if ( ! empty( $state['done'] ) ) {
			return 0;
		}

		$total   = $this->total_entries();
		$reached = 0;

		foreach ( self::MILESTONES as $milestone ) {
			if ( $total >= $milestone ) {
				$reached = $milestone;
			}
		}

		$answered = isset( $state['answered'] ) ? (int) $state['answered'] : 0;

		return $reached > $answered ? $reached : 0;
	}

	/**
	 * Whether the current screen belongs to Custom Contact Forms.
	 *
	 * @return bool
	 */
	private function on_plugin_screen() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( ! $screen ) {
			return false;
		}

		return 'ccf_form' === $screen->post_type || false !== strpos( $screen->id, 'ccf-' ) || false !== strpos( $screen->id, 'ccf_' );
	}

	/**
	 * Card script, on the Forms dashboard only.
	 *
	 * @param string $hook Current admin page.
	 */
	public function enqueue( $hook ) {
		if ( false === strpos( $hook, 'ccf-dashboard' ) || ! $this->current_milestone() ) {
			return;
		}

		wp_enqueue_script( 'ccf-review-card', plugins_url( '/assets/js/ccf-review-card.js', dirname( __FILE__ ) ), array(), CCF_VERSION, true );
		wp_localize_script(
			'ccf-review-card',
			'ccfReviewCard',
			array(
				'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
				'nonce'     => wp_create_nonce( 'ccf_review_response' ),
				'milestone' => $this->current_milestone(),
				'reviewUrl' => self::REVIEW_URL,
				'i18n'      => array(
					'copied'  => __( 'Thank you! Your words are copied. On WordPress.org, pick your stars, paste them in and submit.', 'custom-contact-forms' ),
					'opened'  => __( 'Thank you! The review page is open in a new tab. Pick your stars, add a few words and submit.', 'custom-contact-forms' ),
					'sent'    => __( 'Thanks, it is on its way to the developer. If something is broken, the support forum is the fastest way to get it fixed.', 'custom-contact-forms' ),
					'failed'  => __( 'That did not send. Please try again, or post in the support forum instead.', 'custom-contact-forms' ),
				),
			)
		);
	}

	/**
	 * The "How are we doing?" card, placed at the top of the Forms dashboard.
	 */
	public function render_card() {
		$milestone = $this->current_milestone();

		if ( ! $milestone ) {
			return;
		}

		$count = number_format_i18n( $milestone );
		?>
		<div class="ccf-rate" data-step="ask" role="region" aria-labelledby="ccf-rate-title">
			<button type="button" class="ccf-rate-close" data-ccf-rate="later" aria-label="<?php esc_attr_e( 'Dismiss', 'custom-contact-forms' ); ?>">&times;</button>

			<div class="ccf-rate-ask">
				<h2 id="ccf-rate-title">
					<?php
					/* translators: %s: number of entries, such as 100 */
					echo esc_html( sprintf( __( '%s entries and counting', 'custom-contact-forms' ), $count ) );
					?>
				</h2>
				<p>
					<?php
					/* translators: %s: number of entries, such as 100 */
					echo esc_html( sprintf( __( "That's %s messages, bookings and signups your forms caught for you. How is Custom Contact Forms working out?", 'custom-contact-forms' ), $count ) );
					?>
				</p>
				<div class="ccf-rate-stars" role="group" aria-label="<?php esc_attr_e( 'Your rating', 'custom-contact-forms' ); ?>">
					<?php for ( $i = 1; $i <= 5; $i++ ) : ?>
						<?php
						/* translators: %d: number of stars */
						$ccf_star_label = sprintf( _n( '%d star', '%d stars', $i, 'custom-contact-forms' ), $i );
						?>
						<button type="button" class="ccf-rate-star" data-stars="<?php echo (int) $i; ?>" aria-pressed="false" aria-label="<?php echo esc_attr( $ccf_star_label ); ?>">&#9733;</button>
					<?php endfor; ?>
				</div>
			</div>

			<div class="ccf-rate-happy" hidden>
				<h2><?php esc_html_e( 'Glad it is pulling its weight.', 'custom-contact-forms' ); ?></h2>
				<p><?php esc_html_e( 'Would you share that on WordPress.org? A few words help other site owners find Custom Contact Forms, and help us keep it free.', 'custom-contact-forms' ); ?></p>
				<label class="screen-reader-text" for="ccf-rate-happy-text"><?php esc_html_e( 'Your review', 'custom-contact-forms' ); ?></label>
				<textarea id="ccf-rate-happy-text" rows="3" placeholder="<?php esc_attr_e( 'What do you like most about it?', 'custom-contact-forms' ); ?>"></textarea>
				<p class="ccf-rate-actions">
					<button type="button" class="button button-primary" data-ccf-rate="post"><?php esc_html_e( 'Copy and post on WordPress.org', 'custom-contact-forms' ); ?></button>
					<button type="button" class="button-link" data-ccf-rate="later"><?php esc_html_e( 'Maybe later', 'custom-contact-forms' ); ?></button>
				</p>
			</div>

			<div class="ccf-rate-unhappy" hidden>
				<h2><?php esc_html_e( 'Thanks for being straight with us.', 'custom-contact-forms' ); ?></h2>
				<p><?php esc_html_e( 'What should we fix first? Your note goes straight to the developer.', 'custom-contact-forms' ); ?></p>
				<label class="screen-reader-text" for="ccf-rate-unhappy-text"><?php esc_html_e( 'Your feedback', 'custom-contact-forms' ); ?></label>
				<textarea id="ccf-rate-unhappy-text" rows="3" placeholder="<?php esc_attr_e( 'What is not working?', 'custom-contact-forms' ); ?>"></textarea>
				<p class="ccf-rate-actions">
					<button type="button" class="button button-primary" data-ccf-rate="feedback"><?php esc_html_e( 'Send feedback', 'custom-contact-forms' ); ?></button>
					<a class="button" href="<?php echo esc_url( self::SUPPORT_URL ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Get help in the support forum', 'custom-contact-forms' ); ?></a>
					<button type="button" class="button-link" data-ccf-rate="post"><?php esc_html_e( 'Post on WordPress.org', 'custom-contact-forms' ); ?></button>
				</p>
			</div>

			<div class="ccf-rate-thanks" hidden>
				<p class="ccf-rate-thanks-text" role="status"></p>
			</div>
		</div>
		<?php
	}

	/**
	 * Footer line on Custom Contact Forms screens.
	 *
	 * @param string $text Existing footer text.
	 * @return string
	 */
	public function footer_text( $text ) {
		if ( ! $this->on_plugin_screen() ) {
			return $text;
		}

		return sprintf(
			/* translators: 1: opening link tag, 2: closing link tag */
			esc_html__( 'Enjoying Custom Contact Forms? A %1$s★★★★★ rating on WordPress.org%2$s helps us keep it free and growing.', 'custom-contact-forms' ),
			'<a href="' . esc_url( self::REVIEW_URL ) . '" target="_blank" rel="noopener">',
			'</a>'
		);
	}

	/**
	 * Record an answer, and send feedback when asked to.
	 */
	public function handle_response() {
		if ( ! current_user_can( 'manage_options' ) || ! check_ajax_referer( 'ccf_review_response', 'nonce', false ) ) {
			wp_send_json_error( null, 403 );
		}

		$response  = isset( $_POST['response'] ) ? sanitize_key( wp_unslash( $_POST['response'] ) ) : '';
		$milestone = isset( $_POST['milestone'] ) ? absint( $_POST['milestone'] ) : 0;
		$state     = $this->state();

		if ( ! in_array( $milestone, self::MILESTONES, true ) ) {
			$milestone = $this->current_milestone();
		}

		if ( 'post' === $response ) {
			// They went to WordPress.org, so do not ask again.
			$state['done'] = true;
		} elseif ( 'feedback' === $response ) {
			$sent = $this->send_feedback( $milestone );
			$state['answered'] = max( $milestone, isset( $state['answered'] ) ? (int) $state['answered'] : 0 );
			update_option( self::OPTION, $state, false );

			if ( ! $sent ) {
				wp_send_json_error( null, 500 );
			}

			wp_send_json_success();
		} else {
			// Dismissed or "Maybe later": the next milestone may ask again.
			$state['answered'] = max( $milestone, isset( $state['answered'] ) ? (int) $state['answered'] : 0 );
		}

		update_option( self::OPTION, $state, false );

		wp_send_json_success();
	}

	/**
	 * Email the maintainer the rating and note, through the site's own mail.
	 *
	 * @param int $milestone Milestone the card was shown for.
	 * @return bool
	 */
	private function send_feedback( $milestone ) {
		$stars   = isset( $_POST['stars'] ) ? max( 1, min( 5, absint( $_POST['stars'] ) ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$comment = isset( $_POST['comment'] ) ? sanitize_textarea_field( wp_unslash( $_POST['comment'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$comment = mb_substr( $comment, 0, 3000 );

		$lines = array(
			'Rating:    ' . ( $stars ? $stars . ' of 5' : '(none)' ),
			'Comment:   ' . ( '' !== $comment ? $comment : '(none)' ),
			'',
			'Milestone: ' . $milestone . ' entries',
			'Site:      ' . home_url(),
			'Forms:     ' . (int) wp_count_posts( 'ccf_form' )->publish,
			'Entries:   ' . $this->total_entries(),
			'Plugin:    ' . ( defined( 'CCF_VERSION' ) ? CCF_VERSION : '?' ),
			'WP:        ' . get_bloginfo( 'version' ),
			'PHP:       ' . PHP_VERSION,
		);

		return (bool) wp_mail( self::FEEDBACK_EMAIL, sprintf( 'CCF feedback: %d of 5', $stars ), implode( "\n", $lines ) );
	}
}
