<?php
/**
 * A single, dismissible introduction to Pro, shown once in the form builder.
 *
 * Deliberately once per site, ever. A prompt someone can dismiss and never see
 * again is a fair trade for their attention; one that returns is the reason
 * people leave one-star reviews.
 *
 * @package Custom_Contact_Forms
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CCF_Pro_Modal {

	/**
	 * Option recording that this has been shown or dismissed.
	 */
	const OPTION = 'ccf_pro_modal_seen';

	/**
	 * Singleton.
	 *
	 * @return CCF_Pro_Modal
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
		add_action( 'admin_footer', array( $this, 'render' ) );
		add_action( 'wp_ajax_ccf_dismiss_pro_modal', array( $this, 'ajax_dismiss' ) );
	}

	/**
	 * Whether to show it on this request.
	 *
	 * @return bool
	 */
	private function should_show() {
		// Pro is installed — nothing to sell.
		if ( defined( 'CCFP_VERSION' ) ) {
			return false;
		}

		if ( get_option( self::OPTION ) ) {
			return false;
		}

		if ( ! current_user_can( 'edit_posts' ) ) {
			return false;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( ! $screen || ! isset( $screen->post_type ) || 'ccf_form' !== $screen->post_type ) {
			return false;
		}

		// The builder lives on the form edit screens, not the list table.
		if ( ! in_array( $screen->base, array( 'post', 'post-new' ), true ) ) {
			return false;
		}

		// Not until they have actually built something.
		//
		// "Customize more" means nothing to someone who has not customized
		// anything yet, and interrupting a first visit costs goodwill from
		// people who have not seen the plugin work. Waiting until a form
		// exists also filters out everyone who installed, looked around and
		// left — they were never going to buy, and they are the ones most
		// likely to resent being sold to.
		return wp_count_posts( 'ccf_form' )->publish > 0;
	}

	/**
	 * Features shown in the modal.
	 *
	 * Pro only. Conditional logic is deliberately absent — it ships in the free
	 * plugin, and listing it here would be the first thing an existing user
	 * noticed was wrong.
	 *
	 * @return array
	 */
	private function features() {
		return array(
			array(
				'icon'  => 'M2 5h20v14H2z M2 10h20',
				'title' => __( 'Stripe &amp; PayPal payments', 'custom-contact-forms' ),
				'desc'  => __( 'Take card, wallet or PayPal payments on the form itself — no cart, no redirect.', 'custom-contact-forms' ),
			),
			array(
				'icon'  => 'M3 3h18v18H3z M8 12h8 M12 8v8',
				'title' => __( 'Products, totals &amp; coupons', 'custom-contact-forms' ),
				'desc'  => __( 'Priced fields with quantities and a total that updates as people choose.', 'custom-contact-forms' ),
			),
			array(
				'icon'  => 'M12 19l7-7 3 3-7 7-3-3z M18 13l-1.5-7.5L2 2l3.5 14.5L13 18l5-5z',
				'title' => __( 'Digital signatures', 'custom-contact-forms' ),
				'desc'  => __( 'Signed with a finger or a mouse, saved with the submission.', 'custom-contact-forms' ),
			),
			array(
				'icon'  => 'M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z M14 2v6h6 M9 15h6',
				'title' => __( 'PDF receipts', 'custom-contact-forms' ),
				'desc'  => __( 'Attached to notification emails automatically, or downloaded from any entry.', 'custom-contact-forms' ),
			),
			array(
				'icon'  => 'M3 3h18v18H3z M9 3v18',
				'title' => __( 'Multi-step forms', 'custom-contact-forms' ),
				'desc'  => __( 'Split a long form over pages, with a progress bar and per-step validation.', 'custom-contact-forms' ),
			),
			array(
				'icon'  => 'M21 10c0 6-9 12-9 12s-9-6-9-12a9 9 0 1118 0z M12 10a3 3 0 100-6 3 3 0 000 6z',
				'title' => __( 'Address autocomplete', 'custom-contact-forms' ),
				'desc'  => __( 'Visitors pick their address and the rest of the fields fill themselves in.', 'custom-contact-forms' ),
			),
		);
	}

	/**
	 * Output the modal.
	 */
	public function render() {
		if ( ! $this->should_show() ) {
			return;
		}

		// Recorded as it is rendered rather than on dismissal. If the page is
		// closed without a click it still counts as shown — the promise is one
		// appearance, not one dismissal.
		update_option( self::OPTION, time(), false );

		$url = function_exists( 'ccf_pro_url' ) ? ccf_pro_url( 'builder-modal' ) : 'https://customformspro.com/';
		?>
		<div class="ccf-pm-overlay" id="ccf-pro-modal">
			<div class="ccf-pm" role="dialog" aria-modal="true" aria-labelledby="ccf-pm-title">

				<button type="button" class="ccf-pm-x" aria-label="<?php esc_attr_e( 'Close', 'custom-contact-forms' ); ?>">&times;</button>

				<div class="ccf-pm-head">
					<span class="ccf-pm-eyebrow"><?php esc_html_e( 'Custom Contact Forms Pro', 'custom-contact-forms' ); ?></span>
					<h2 id="ccf-pm-title"><?php esc_html_e( 'Customize more with Pro', 'custom-contact-forms' ); ?></h2>
					<p><?php esc_html_e( 'The same builder you are using now — with payments, signatures and receipts built in. No cart, no e-commerce stack.', 'custom-contact-forms' ); ?></p>
				</div>

				<div class="ccf-pm-body">
					<div class="ccf-pm-grid">
						<?php foreach ( $this->features() as $feature ) : ?>
							<div class="ccf-pm-item">
								<span class="ccf-pm-ico" aria-hidden="true">
									<svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="<?php echo esc_attr( $feature['icon'] ); ?>"/></svg>
								</span>
								<div>
									<strong><?php echo wp_kses_post( $feature['title'] ); ?></strong>
									<span><?php echo esc_html( $feature['desc'] ); ?></span>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
				</div>

				<div class="ccf-pm-foot">
					<p class="ccf-pm-more"><?php esc_html_e( 'Plus SMS confirmations, surveys, star ratings and 13 ready-made templates.', 'custom-contact-forms' ); ?></p>
					<a class="ccf-pm-cta" href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'See everything in Pro', 'custom-contact-forms' ); ?></a>
					<button type="button" class="ccf-pm-later"><?php esc_html_e( 'Maybe later', 'custom-contact-forms' ); ?></button>
					<p class="ccf-pm-once"><?php esc_html_e( 'You will only see this once.', 'custom-contact-forms' ); ?></p>
				</div>

			</div>
		</div>

		<style>
		.ccf-pm-overlay{position:fixed;inset:0;background:rgba(17,24,39,.62);z-index:160000;display:flex;align-items:center;justify-content:center;padding:24px;animation:ccfPmIn .2s ease}
		.ccf-pm-overlay.ccf-pm-gone{display:none}
		@keyframes ccfPmIn{from{opacity:0}to{opacity:1}}
		.ccf-pm{position:relative;background:#fff;border-radius:14px;width:min(760px,100%);max-height:min(88vh,660px);display:flex;flex-direction:column;overflow:hidden;box-shadow:0 24px 70px rgba(17,24,39,.35);animation:ccfPmRise .26s cubic-bezier(.2,.8,.3,1)}
		@keyframes ccfPmRise{from{opacity:0;transform:translateY(16px) scale(.985)}to{opacity:1;transform:none}}
		.ccf-pm-x{position:absolute;top:12px;right:14px;background:none;border:0;color:rgba(255,255,255,.75);font-size:26px;line-height:1;cursor:pointer;padding:4px 8px}
		.ccf-pm-x:hover{color:#fff}
		.ccf-pm-head{background:linear-gradient(135deg,#0e9f6e,#0b8259);color:#fff;padding:26px 34px 22px;flex:none}
		.ccf-pm-eyebrow{display:inline-block;font-size:.72rem;font-weight:700;letter-spacing:.12em;text-transform:uppercase;opacity:.85;margin-bottom:8px}
		.ccf-pm-head h2{color:#fff;margin:0 0 8px;font-size:1.6rem;font-weight:800;letter-spacing:-.02em;line-height:1.18}
		.ccf-pm-head p{margin:0;font-size:.97rem;line-height:1.55;opacity:.92;max-width:520px}
		.ccf-pm-body{padding:24px 34px 8px;overflow:auto;flex:1 1 auto;min-height:0}
		.ccf-pm-grid{display:grid;grid-template-columns:1fr 1fr;gap:18px 26px}
		.ccf-pm-item{display:flex;gap:12px;align-items:flex-start}
		.ccf-pm-ico{flex:none;width:34px;height:34px;border-radius:8px;background:#f3faf7;border:1px solid #d1ede2;display:flex;align-items:center;justify-content:center}
		.ccf-pm-ico svg{width:17px;height:17px;stroke:#0e9f6e}
		.ccf-pm-item strong{display:block;color:#111827;font-size:.94rem;font-weight:700;margin-bottom:2px}
		.ccf-pm-item span{color:#6b7280;font-size:.85rem;line-height:1.5}
		.ccf-pm-foot{padding:18px 34px 22px;text-align:center;flex:none;border-top:1px solid #f0f0f1;background:#fff}
		.ccf-pm-more{margin:0 0 14px;color:#6b7280;font-size:.84rem;line-height:1.5}
		.ccf-pm-cta{display:inline-block;background:#0e9f6e;color:#fff !important;text-decoration:none;font-weight:700;font-size:1rem;padding:13px 34px;border-radius:8px;transition:background .15s}
		.ccf-pm-cta:hover{background:#0b8259;color:#fff !important}
		.ccf-pm-later{display:block;margin:12px auto 0;background:none;border:0;color:#6b7280;font-size:.88rem;cursor:pointer;text-decoration:underline}
		.ccf-pm-later:hover{color:#374151}
		.ccf-pm-once{margin:10px 0 0;color:#9ca3af;font-size:.78rem}
		@media(max-width:640px){.ccf-pm-grid{grid-template-columns:1fr}.ccf-pm-head{padding:26px 22px 22px}.ccf-pm-body,.ccf-pm-foot{padding-left:22px;padding-right:22px}}
		</style>

		<script>
		( function () {
			var overlay = document.getElementById( 'ccf-pro-modal' );

			if ( ! overlay ) {
				return;
			}

			function close() {
				overlay.classList.add( 'ccf-pm-gone' );

				// Belt and braces: the option is already set server-side when
				// the modal rendered, but record the dismissal too so the
				// intent is unambiguous if that ever changes.
				var body = new FormData();
				body.append( 'action', 'ccf_dismiss_pro_modal' );
				body.append( 'nonce', '<?php echo esc_js( wp_create_nonce( 'ccf_dismiss_pro_modal' ) ); ?>' );

				if ( window.fetch ) {
					fetch( ajaxurl, { method: 'POST', credentials: 'same-origin', body: body } );
				}
			}

			overlay.querySelector( '.ccf-pm-x' ).addEventListener( 'click', close );
			overlay.querySelector( '.ccf-pm-later' ).addEventListener( 'click', close );

			overlay.addEventListener( 'click', function ( event ) {
				if ( event.target === overlay ) {
					close();
				}
			} );

			document.addEventListener( 'keydown', function ( event ) {
				if ( 'Escape' === event.key ) {
					close();
				}
			} );

			// Following the link is not a dismissal to argue with — they acted
			// on it, so close behind them.
			overlay.querySelector( '.ccf-pm-cta' ).addEventListener( 'click', close );
		} )();
		</script>
		<?php
	}

	/**
	 * Record the dismissal.
	 */
	public function ajax_dismiss() {
		check_ajax_referer( 'ccf_dismiss_pro_modal', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error();
		}

		update_option( self::OPTION, time(), false );

		wp_send_json_success();
	}
}
