<?php
/**
 * Pro sale banner with a countdown.
 *
 * Shown only while a Pro sale is running (see ccf_pro_promo()), only on
 * Custom Contact Forms screens, only to administrators on sites without
 * Pro, and gone for good once dismissed. The countdown runs to the sale's
 * real end time, so the deadline it shows is the actual one.
 *
 * @since 7.20.0
 * @package Custom_Contact_Forms
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CCF_Sale_Banner {

	/**
	 * Setup hooks.
	 *
	 * @since 7.20.0
	 */
	public function setup() {
		add_action( 'admin_notices', array( $this, 'render' ), 5 );
		add_action( 'wp_ajax_ccf_dismiss_sale_banner', array( $this, 'ajax_dismiss' ) );
	}

	/**
	 * Per-user meta key that remembers a dismissal for one sale.
	 *
	 * @since 7.20.0
	 * @param string $code Sale coupon code.
	 * @return string
	 */
	private function dismiss_key( $code ) {
		return 'ccf_sale_dismissed_' . sanitize_key( $code );
	}

	/**
	 * Whether the current screen belongs to Custom Contact Forms.
	 *
	 * @since 7.20.0
	 * @return bool
	 */
	private function is_ccf_screen() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( ! $screen || ! empty( $screen->is_block_editor ) ) {
			return false;
		}

		if ( in_array( $screen->post_type, array( 'ccf_form', 'ccf_submission' ), true ) ) {
			return true;
		}

		return false !== strpos( (string) $screen->id, 'ccf' );
	}

	/**
	 * Whether the banner shows on this request.
	 *
	 * @since 7.20.0
	 * @return bool
	 */
	public function is_showing() {
		if ( defined( 'CCFP_VERSION' ) || ! current_user_can( 'manage_options' ) ) {
			return false;
		}

		$promo = ccf_pro_promo();

		if ( empty( $promo['code'] ) || empty( $promo['ends'] ) || time() > (int) $promo['ends'] ) {
			return false;
		}

		if ( get_user_meta( get_current_user_id(), $this->dismiss_key( $promo['code'] ), true ) ) {
			return false;
		}

		return $this->is_ccf_screen();
	}

	/**
	 * Output the banner.
	 *
	 * @since 7.20.0
	 */
	public function render() {
		if ( ! $this->is_showing() ) {
			return;
		}

		$promo = ccf_pro_promo();
		$ends  = (int) $promo['ends'];
		$left  = max( 0, $ends - time() );
		$parts = array(
			'd' => (int) floor( $left / DAY_IN_SECONDS ),
			'h' => (int) floor( ( $left % DAY_IN_SECONDS ) / HOUR_IN_SECONDS ),
			'm' => (int) floor( ( $left % HOUR_IN_SECONDS ) / MINUTE_IN_SECONDS ),
			's' => (int) ( $left % MINUTE_IN_SECONDS ),
		);
		$labels = array(
			'd' => __( 'Days', 'custom-contact-forms' ),
			'h' => __( 'Hours', 'custom-contact-forms' ),
			'm' => __( 'Mins', 'custom-contact-forms' ),
			's' => __( 'Secs', 'custom-contact-forms' ),
		);
		?>
		<div class="notice ccf-sale" id="ccf-sale" role="region" aria-label="<?php esc_attr_e( 'Custom Contact Forms Pro sale', 'custom-contact-forms' ); ?>" data-ends="<?php echo esc_attr( $ends * 1000 ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'ccf_dismiss_sale_banner' ) ); ?>">
			<style>
				#ccf-sale.ccf-sale{position:relative;overflow:hidden;margin:20px 20px 16px 0;padding:0;border:0;border-radius:12px;background:#0b0b10;box-shadow:0 10px 30px rgba(0,0,0,.28);color:#fff}
				#ccf-sale:before{content:"";position:absolute;inset:0;background:radial-gradient(circle at 12% 0%,rgba(255,214,10,.22),transparent 45%),radial-gradient(circle at 95% 120%,rgba(14,159,110,.35),transparent 50%);pointer-events:none}
				#ccf-sale .ccf-sale-in{position:relative;display:flex;flex-wrap:wrap;align-items:center;gap:20px 28px;padding:22px 56px 22px 26px}
				#ccf-sale .ccf-sale-copy{flex:1 1 320px;min-width:250px}
				#ccf-sale .ccf-sale-tag{display:inline-block;margin:0 0 8px;padding:3px 10px;border-radius:999px;background:#ffd60a;color:#0b0b10;font-size:11px;font-weight:800;letter-spacing:.12em;text-transform:uppercase}
				#ccf-sale h2{margin:0 0 6px;color:#fff;font-size:24px;line-height:1.2;font-weight:800;letter-spacing:-.01em}
				#ccf-sale h2 .ccf-sale-pct{color:#ffd60a}
				#ccf-sale p{margin:0;color:rgba(255,255,255,.78);font-size:13.5px;line-height:1.5}
				#ccf-sale .ccf-sale-clock{display:flex;gap:8px;flex:0 0 auto}
				#ccf-sale .ccf-sale-unit{min-width:58px;padding:9px 6px 7px;border-radius:9px;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.14);text-align:center}
				#ccf-sale .ccf-sale-num{display:block;color:#fff;font-size:24px;line-height:1;font-weight:800;font-variant-numeric:tabular-nums}
				#ccf-sale .ccf-sale-lbl{display:block;margin-top:5px;color:rgba(255,255,255,.6);font-size:10px;font-weight:600;letter-spacing:.08em;text-transform:uppercase}
				#ccf-sale .ccf-sale-cta{display:flex;flex-direction:column;align-items:center;gap:6px;flex:0 0 auto}
				#ccf-sale .ccf-sale-btn{display:inline-block;padding:13px 26px;border-radius:8px;background:#ffd60a;color:#0b0b10;font-size:15px;font-weight:800;text-decoration:none;box-shadow:0 6px 18px rgba(255,214,10,.3);transition:transform .15s ease,box-shadow .15s ease}
				#ccf-sale .ccf-sale-btn:hover,#ccf-sale .ccf-sale-btn:focus{color:#0b0b10;transform:translateY(-1px);box-shadow:0 10px 22px rgba(255,214,10,.4)}
				#ccf-sale .ccf-sale-btn:focus{outline:2px solid #fff;outline-offset:2px}
				#ccf-sale .ccf-sale-note{color:rgba(255,255,255,.6);font-size:11.5px}
				#ccf-sale .ccf-sale-x{position:absolute;top:10px;right:12px;padding:4px 7px;border:0;background:none;color:rgba(255,255,255,.65);font-size:18px;line-height:1;cursor:pointer}
				#ccf-sale .ccf-sale-x:hover,#ccf-sale .ccf-sale-x:focus{color:#fff}
				@media (prefers-reduced-motion: reduce){#ccf-sale .ccf-sale-btn{transition:none}#ccf-sale .ccf-sale-btn:hover{transform:none}}
				@media (max-width:782px){#ccf-sale.ccf-sale{margin-right:10px}#ccf-sale .ccf-sale-in{padding:20px 44px 20px 18px}#ccf-sale h2{font-size:20px}#ccf-sale .ccf-sale-unit{min-width:50px}}
			</style>
			<div class="ccf-sale-in">
				<div class="ccf-sale-copy">
					<span class="ccf-sale-tag"><?php esc_html_e( 'Black Friday', 'custom-contact-forms' ); ?></span>
					<h2><?php
					printf(
						/* translators: %s: discount, e.g. "30% off" */
						esc_html__( '%s Custom Contact Forms Pro', 'custom-contact-forms' ),
						'<span class="ccf-sale-pct">' . esc_html__( '30% off', 'custom-contact-forms' ) . '</span>'
					);
					?></h2>
					<p><?php esc_html_e( 'Take Stripe and PayPal payments, collect signed agreements, send PDF receipts and build multi-step forms. Unlimited sites.', 'custom-contact-forms' ); ?></p>
				</div>
				<div class="ccf-sale-clock" aria-label="<?php esc_attr_e( 'Time left in the sale', 'custom-contact-forms' ); ?>">
					<?php foreach ( $parts as $key => $value ) : ?>
						<div class="ccf-sale-unit">
							<span class="ccf-sale-num" data-unit="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( str_pad( (string) $value, 2, '0', STR_PAD_LEFT ) ); ?></span>
							<span class="ccf-sale-lbl"><?php echo esc_html( $labels[ $key ] ); ?></span>
						</div>
					<?php endforeach; ?>
				</div>
				<div class="ccf-sale-cta">
					<a class="ccf-sale-btn" href="<?php echo esc_url( ccf_pro_link( 'sale-banner' ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Get 30% off', 'custom-contact-forms' ); ?></a>
					<span class="ccf-sale-note"><?php esc_html_e( 'Code applied at checkout. 14-day money-back guarantee.', 'custom-contact-forms' ); ?></span>
				</div>
			</div>
			<button type="button" class="ccf-sale-x" aria-label="<?php esc_attr_e( 'Dismiss the sale banner', 'custom-contact-forms' ); ?>">&#10005;</button>
			<script>
			( function () {
				var box = document.getElementById( 'ccf-sale' );
				if ( ! box ) { return; }
				var ends = parseInt( box.getAttribute( 'data-ends' ), 10 );
				var nums = {};
				Array.prototype.forEach.call( box.querySelectorAll( '[data-unit]' ), function ( el ) {
					nums[ el.getAttribute( 'data-unit' ) ] = el;
				} );
				function pad( n ) { return ( n < 10 ? '0' : '' ) + n; }
				function tick() {
					var left = Math.max( 0, Math.floor( ( ends - Date.now() ) / 1000 ) );
					if ( left <= 0 ) {
						box.parentNode && box.parentNode.removeChild( box );
						return;
					}
					nums.d.textContent = pad( Math.floor( left / 86400 ) );
					nums.h.textContent = pad( Math.floor( ( left % 86400 ) / 3600 ) );
					nums.m.textContent = pad( Math.floor( ( left % 3600 ) / 60 ) );
					nums.s.textContent = pad( left % 60 );
					setTimeout( tick, 1000 );
				}
				tick();
				box.querySelector( '.ccf-sale-x' ).addEventListener( 'click', function () {
					var body = new FormData();
					body.append( 'action', 'ccf_dismiss_sale_banner' );
					body.append( 'nonce', box.getAttribute( 'data-nonce' ) );
					if ( window.fetch ) {
						fetch( window.ajaxurl || '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>', { method: 'POST', credentials: 'same-origin', body: body } );
					}
					box.parentNode && box.parentNode.removeChild( box );
				} );
			} )();
			</script>
		</div>
		<?php
	}

	/**
	 * Remember that this user closed the banner for this sale.
	 *
	 * @since 7.20.0
	 */
	public function ajax_dismiss() {
		check_ajax_referer( 'ccf_dismiss_sale_banner', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( null, 403 );
		}

		$promo = ccf_pro_promo();

		if ( ! empty( $promo['code'] ) ) {
			update_user_meta( get_current_user_id(), $this->dismiss_key( $promo['code'] ), 1 );
		}

		wp_send_json_success();
	}

	/**
	 * Return an instance of the current class.
	 *
	 * @since 7.20.0
	 * @return CCF_Sale_Banner
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
