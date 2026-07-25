<?php
/**
 * One-time dismissible admin notice introducing Custom Contact Forms Pro.
 *
 * Shown once per site to users who can manage plugins, hidden when Pro is
 * active, and permanently dismissed with a single click.
 *
 * @since 7.13.1
 */

defined( 'ABSPATH' ) || exit;

class CCF_Pro_Notice {

	/**
	 * Option key storing permanent dismissal.
	 */
	const DISMISSED_OPTION = 'ccf_pro_notice_dismissed';

	/**
	 * Setup hooks
	 *
	 * @since 7.13.1
	 */
	public function setup() {
		add_action( 'admin_notices', array( $this, 'render_notice' ) );
		add_action( 'wp_ajax_ccf_dismiss_pro_notice', array( $this, 'ajax_dismiss' ) );
	}

	/**
	 * Whether the notice should display.
	 *
	 * @since 7.13.1
	 * @return bool
	 */
	private function should_show() {
		if ( defined( 'CCFP_VERSION' ) ) {
			return false;
		}

		if ( ! current_user_can( 'activate_plugins' ) ) {
			return false;
		}

		if ( get_option( self::DISMISSED_OPTION ) ) {
			return false;
		}

		// Keep the block editor clean — notices get relocated awkwardly there.
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && ! empty( $screen->is_block_editor ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Output the notice.
	 *
	 * @since 7.13.1
	 */
	public function render_notice() {
		if ( ! $this->should_show() ) {
			return;
		}

		$pro_url  = apply_filters( 'ccf_pro_upgrade_url', 'https://customformspro.com/' );
		$news_url = 'https://customformspro.com/introducing-custom-contact-forms-pro/';
		$nonce    = wp_create_nonce( 'ccf_dismiss_pro_notice' );
		?>
		<div class="notice ccf-pro-notice" id="ccf-pro-notice">
			<style>
				.ccf-pro-notice{border:0;padding:0;background:linear-gradient(135deg,#6d3fc4 0%,#8659d6 55%,#9a6fe8 100%);box-shadow:0 2px 8px rgba(109,63,196,.35);margin:20px 20px 20px 0;position:relative;overflow:hidden;border-radius:6px}
				.ccf-pro-notice .ccf-pn-inner{display:flex;flex-wrap:wrap;align-items:center;gap:18px;padding:22px 52px 22px 26px}
				.ccf-pro-notice .ccf-pn-main{flex:1 1 420px;min-width:280px}
				.ccf-pro-notice h2{margin:0 0 6px;font-size:19px;line-height:1.3;color:#fff}
				.ccf-pro-notice .ccf-pn-badge{display:inline-block;margin-left:10px;padding:2px 10px;border-radius:10px;font-size:10px;font-weight:700;letter-spacing:.5px;background:#fff;color:#6d3fc4;vertical-align:middle;text-transform:uppercase}
				.ccf-pro-notice p{margin:0;color:rgba(255,255,255,.92);font-size:13.5px;line-height:1.55}
				.ccf-pro-notice ul.ccf-pn-feats{margin:12px 0 0;padding:0;list-style:none;display:flex;flex-wrap:wrap;gap:6px 20px}
				.ccf-pro-notice ul.ccf-pn-feats li{margin:0;font-size:12.5px;color:#fff;padding-left:19px;position:relative;font-weight:500}
				.ccf-pro-notice ul.ccf-pn-feats li:before{content:"\2713";position:absolute;left:0;color:#c9b3f2;font-weight:700}
				.ccf-pro-notice .ccf-pn-actions{display:flex;flex-direction:column;gap:9px;flex:0 0 auto}
				.ccf-pro-notice .ccf-pn-btn{display:inline-block;background:#fff;border:1px solid #fff;color:#6d3fc4;border-radius:5px;padding:10px 22px;font-size:13.5px;font-weight:700;text-decoration:none;text-align:center}
				.ccf-pro-notice .ccf-pn-btn:hover{background:#f1eafc;border-color:#f1eafc;color:#5b32a8}
				.ccf-pro-notice .ccf-pn-link{font-size:12.5px;text-align:center;color:rgba(255,255,255,.85);text-decoration:underline}
				.ccf-pro-notice .ccf-pn-link:hover{color:#fff}
				.ccf-pro-notice .ccf-pn-dismiss{position:absolute;top:10px;right:12px;background:none;border:none;cursor:pointer;color:rgba(255,255,255,.75);font-size:16px;line-height:1;padding:4px}
				.ccf-pro-notice .ccf-pn-dismiss:hover{color:#fff}
			</style>
			<div class="ccf-pn-inner">
				<div class="ccf-pn-main">
					<h2><?php esc_html_e( 'Your forms can take payments now', 'custom-contact-forms' ); ?><span class="ccf-pn-badge"><?php esc_html_e( 'New', 'custom-contact-forms' ); ?></span></h2>
					<p><?php esc_html_e( 'Custom Contact Forms Pro adds paid-form superpowers to the builder you already use — no e-commerce stack required.', 'custom-contact-forms' ); ?></p>
					<ul class="ccf-pn-feats">
						<li><?php esc_html_e( 'Stripe payments on the form', 'custom-contact-forms' ); ?></li>
						<li><?php esc_html_e( 'Digital signatures', 'custom-contact-forms' ); ?></li>
						<li><?php esc_html_e( 'PDF receipts', 'custom-contact-forms' ); ?></li>
						<li><?php esc_html_e( 'Multi-step forms & conditional logic', 'custom-contact-forms' ); ?></li>
					</ul>
				</div>
				<div class="ccf-pn-actions">
					<a class="ccf-pn-btn" href="<?php echo esc_url( $pro_url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Explore Pro', 'custom-contact-forms' ); ?></a>
					<a class="ccf-pn-link" href="<?php echo esc_url( $news_url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( "See what's new in 7.14", 'custom-contact-forms' ); ?></a>
				</div>
			</div>
			<button type="button" class="ccf-pn-dismiss" aria-label="<?php esc_attr_e( 'Dismiss this notice', 'custom-contact-forms' ); ?>">&#10005;</button>
			<script>
			( function() {
				var notice = document.getElementById( 'ccf-pro-notice' );
				if ( ! notice ) { return; }
				notice.querySelector( '.ccf-pn-dismiss' ).addEventListener( 'click', function() {
					notice.style.display = 'none';
					var data = new FormData();
					data.append( 'action', 'ccf_dismiss_pro_notice' );
					data.append( 'nonce', '<?php echo esc_js( $nonce ); ?>' );
					fetch( ajaxurl, { method: 'POST', credentials: 'same-origin', body: data } );
				} );
			} )();
			</script>
		</div>
		<?php
	}

	/**
	 * Persist dismissal permanently.
	 *
	 * @since 7.13.1
	 */
	public function ajax_dismiss() {
		check_ajax_referer( 'ccf_dismiss_pro_notice', 'nonce' );

		if ( ! current_user_can( 'activate_plugins' ) ) {
			wp_send_json_error();
		}

		update_option( self::DISMISSED_OPTION, 1, false );

		wp_send_json_success();
	}

	/**
	 * Return an instance of the current class
	 *
	 * @since 7.13.1
	 * @return CCF_Pro_Notice
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
