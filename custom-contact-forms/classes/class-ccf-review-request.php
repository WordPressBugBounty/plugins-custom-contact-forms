<?php
/**
 * Review request.
 *
 * Asks for a review once, and only after the plugin has demonstrably worked:
 * the trigger is a submission milestone, not elapsed time. Someone whose forms
 * have collected real submissions has had the experience worth reviewing.
 * Someone who installed the plugin a week ago and never came back has not.
 *
 * @since 7.15.0
 * @package Custom Contact Forms
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CCF_Review_Request {

	/**
	 * Submissions required before asking.
	 *
	 * @since 7.15.0
	 * @var int
	 */
	const THRESHOLD = 10;

	/**
	 * Option storing the request state.
	 *
	 * @since 7.15.0
	 * @var string
	 */
	const OPTION = 'ccf_review_request';

	/**
	 * How long "not yet" defers the ask, in seconds.
	 *
	 * @since 7.15.0
	 * @var int
	 */
	const SNOOZE = 7776000; // 90 days.

	/**
	 * Factory method.
	 *
	 * @since 7.15.0
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
	 * Setup hooks.
	 *
	 * @since 7.15.0
	 */
	public function setup() {
		add_action( 'admin_notices', array( $this, 'maybe_render' ) );
		add_action( 'wp_ajax_ccf_review_response', array( $this, 'handle_response' ) );
	}

	/**
	 * Whether the notice should be shown.
	 *
	 * @since 7.15.0
	 * @return bool
	 */
	protected function should_show() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return false;
		}

		// Scoped to this plugin's own screens. A review request has no business
		// appearing while someone is working on something else.
		$screen = get_current_screen();

		if ( ! $screen || false === strpos( $screen->id, 'ccf_' ) ) {
			return false;
		}

		$state = get_option( self::OPTION, array() );

		if ( ! empty( $state['done'] ) ) {
			return false;
		}

		if ( ! empty( $state['snooze_until'] ) && time() < (int) $state['snooze_until'] ) {
			return false;
		}

		$counts = wp_count_posts( 'ccf_submission' );
		$total  = isset( $counts->publish ) ? (int) $counts->publish : 0;

		return $total >= self::THRESHOLD;
	}

	/**
	 * Render the notice.
	 *
	 * @since 7.15.0
	 */
	public function maybe_render() {
		if ( ! $this->should_show() ) {
			return;
		}

		$nonce = wp_create_nonce( 'ccf_review_response' );
		?>
		<div class="notice notice-info is-dismissible ccf-review-notice">
			<p>
				<strong><?php esc_html_e( 'Enjoying Custom Contact Forms?', 'custom-contact-forms' ); ?></strong><br>
				<?php esc_html_e( 'Your forms have collected a good few submissions now. If the plugin has been useful, a review on WordPress.org genuinely helps other people find it — it takes a minute.', 'custom-contact-forms' ); ?>
			</p>
			<p>
				<a href="https://wordpress.org/support/plugin/custom-contact-forms/reviews/#new-post" class="button button-primary" target="_blank" rel="noopener" data-ccf-review="done"><?php esc_html_e( 'Leave a review', 'custom-contact-forms' ); ?></a>
				<button type="button" class="button" data-ccf-review="snooze"><?php esc_html_e( 'Maybe later', 'custom-contact-forms' ); ?></button>
				<button type="button" class="button-link" data-ccf-review="done"><?php esc_html_e( 'Don\'t ask again', 'custom-contact-forms' ); ?></button>
			</p>
		</div>
		<script>
		( function () {
			var notice = document.querySelector( '.ccf-review-notice' );
			if ( ! notice ) { return; }

			function respond( action ) {
				var body = new URLSearchParams();
				body.append( 'action', 'ccf_review_response' );
				body.append( 'nonce', <?php echo wp_json_encode( $nonce ); ?> );
				body.append( 'response', action );

				fetch( ajaxurl, {
					method: 'POST',
					credentials: 'same-origin',
					headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
					body: body.toString()
				} );

				notice.style.display = 'none';
			}

			notice.addEventListener( 'click', function ( e ) {
				var el = e.target.closest ? e.target.closest( '[data-ccf-review]' ) : null;

				// Dismissing with the X is treated as "later", not "never".
				if ( ! el ) {
					if ( e.target.classList && e.target.classList.contains( 'notice-dismiss' ) ) {
						respond( 'snooze' );
					}
					return;
				}

				respond( el.getAttribute( 'data-ccf-review' ) );
			} );
		} )();
		</script>
		<?php
	}

	/**
	 * Record the response.
	 *
	 * @since 7.15.0
	 */
	public function handle_response() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error();
		}

		check_ajax_referer( 'ccf_review_response', 'nonce' );

		$response = isset( $_POST['response'] ) ? sanitize_key( wp_unslash( $_POST['response'] ) ) : '';
		$state    = get_option( self::OPTION, array() );

		if ( ! is_array( $state ) ) {
			$state = array();
		}

		if ( 'done' === $response ) {
			$state['done'] = true;
		} else {
			$state['snooze_until'] = time() + self::SNOOZE;
		}

		update_option( self::OPTION, $state, false );

		wp_send_json_success();
	}
}
