<?php
/**
 * Deactivation feedback survey.
 *
 * When a user clicks "Deactivate" on the plugins screen, a short optional
 * survey asks why. If they choose to submit, the reason is emailed to the
 * maintainer via the site's own wp_mail() — no external server is contacted,
 * and nothing is sent unless the user explicitly clicks "Submit & Deactivate."
 * A "Skip & Deactivate" option sends nothing.
 *
 * @package Custom_Contact_Forms
 * @author  Dmitry Alexander
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CCF_Feedback {

	/**
	 * Where feedback is emailed.
	 *
	 * @var string
	 */
	private $admin_email = 'admin@oiopublisher.com';

	/**
	 * This plugin's basename (folder/file.php).
	 *
	 * @var string
	 */
	private $plugin_basename = '';

	/**
	 * Singleton.
	 *
	 * @return object
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
	 * Hook things up.
	 */
	public function setup() {
		$this->plugin_basename = plugin_basename( CCF_PLUGIN_DIR . 'custom-contact-forms.php' );

		add_action( 'admin_footer-plugins.php', array( $this, 'render_modal' ) );
		add_action( 'wp_ajax_ccf_deactivation_feedback', array( $this, 'handle_feedback' ) );
	}

	/**
	 * The survey reason options.
	 *
	 * @return array
	 */
	private function reasons() {
		return array(
			'found_better'  => __( 'I found a better plugin', 'custom-contact-forms' ),
			'not_working'   => __( "It didn't work the way I expected", 'custom-contact-forms' ),
			'too_complex'   => __( 'It was too complicated to set up', 'custom-contact-forms' ),
			'temporary'     => __( 'Only needed it temporarily', 'custom-contact-forms' ),
			'no_longer'     => __( 'I no longer need a contact form', 'custom-contact-forms' ),
			'other'         => __( 'Other', 'custom-contact-forms' ),
		);
	}

	/**
	 * Render the modal markup, styles, and behavior on the plugins screen.
	 */
	public function render_modal() {
		$nonce = wp_create_nonce( 'ccf_deactivation_feedback' );
		?>
		<div id="ccf-deactivate-overlay" class="ccf-deactivate-overlay" style="display:none;">
			<div class="ccf-deactivate-box" role="dialog" aria-modal="true" aria-labelledby="ccf-deactivate-title">
				<div class="ccf-deactivate-head">
					<h3 id="ccf-deactivate-title"><?php esc_html_e( 'Quick question before you go', 'custom-contact-forms' ); ?></h3>
					<button type="button" class="ccf-deactivate-close" aria-label="<?php esc_attr_e( 'Close', 'custom-contact-forms' ); ?>">&times;</button>
				</div>
				<p class="ccf-deactivate-sub"><?php esc_html_e( "If you have a moment, what's the main reason you're deactivating? This is optional and helps us improve.", 'custom-contact-forms' ); ?></p>

				<ul class="ccf-deactivate-reasons">
					<?php foreach ( $this->reasons() as $key => $label ) : ?>
						<li>
							<label>
								<input type="radio" name="ccf_deactivate_reason" value="<?php echo esc_attr( $key ); ?>" />
								<span><?php echo esc_html( $label ); ?></span>
							</label>
						</li>
					<?php endforeach; ?>
				</ul>

				<textarea id="ccf-deactivate-comment" rows="3" placeholder="<?php esc_attr_e( 'Anything we could do better? (optional)', 'custom-contact-forms' ); ?>"></textarea>

				<div class="ccf-deactivate-actions">
					<button type="button" class="button button-primary" id="ccf-deactivate-submit"><?php esc_html_e( 'Submit &amp; Deactivate', 'custom-contact-forms' ); ?></button>
					<a href="#" class="ccf-deactivate-skip" id="ccf-deactivate-skip"><?php esc_html_e( 'Skip &amp; Deactivate', 'custom-contact-forms' ); ?></a>
				</div>
			</div>
		</div>

		<style>
			.ccf-deactivate-overlay{position:fixed;inset:0;z-index:100000;background:rgba(0,0,0,.5);display:flex;align-items:center;justify-content:center;}
			.ccf-deactivate-box{background:#fff;width:460px;max-width:92vw;border-radius:10px;box-shadow:0 10px 40px rgba(0,0,0,.25);padding:22px 24px 20px;}
			.ccf-deactivate-head{display:flex;align-items:center;justify-content:space-between;}
			.ccf-deactivate-head h3{margin:0;font-size:17px;color:#1d2327;}
			.ccf-deactivate-close{background:none;border:0;font-size:22px;line-height:1;color:#787c82;cursor:pointer;padding:0 2px;}
			.ccf-deactivate-sub{color:#646970;font-size:13px;margin:8px 0 14px;}
			.ccf-deactivate-reasons{margin:0 0 12px;}
			.ccf-deactivate-reasons li{margin:0 0 8px;}
			.ccf-deactivate-reasons label{display:flex;align-items:center;gap:8px;font-size:13px;color:#2c3338;cursor:pointer;}
			#ccf-deactivate-comment{width:100%;box-sizing:border-box;border:1px solid #dcdcde;border-radius:6px;padding:8px 10px;font-size:13px;resize:vertical;}
			.ccf-deactivate-actions{display:flex;align-items:center;gap:14px;margin-top:16px;}
			.ccf-deactivate-skip{color:#787c82;font-size:13px;text-decoration:none;}
			.ccf-deactivate-skip:hover{color:#d63638;}
		</style>

		<script>
		( function () {
			var basename = <?php echo wp_json_encode( $this->plugin_basename ); ?>;
			var nonce = <?php echo wp_json_encode( $nonce ); ?>;
			var deactivateUrl = '';

			var overlay = document.getElementById( 'ccf-deactivate-overlay' );
			if ( ! overlay ) { return; }

			// Find this plugin's Deactivate link on the plugins screen.
			var row = document.querySelector( 'tr[data-plugin="' + basename + '"]' );
			var link = row ? row.querySelector( '.deactivate a' ) : null;

			if ( link ) {
				link.addEventListener( 'click', function ( e ) {
					e.preventDefault();
					deactivateUrl = link.getAttribute( 'href' );
					overlay.style.display = 'flex';
				} );
			}

			function go() { if ( deactivateUrl ) { window.location.href = deactivateUrl; } }
			function close() { overlay.style.display = 'none'; }

			overlay.querySelector( '.ccf-deactivate-close' ).addEventListener( 'click', close );
			overlay.addEventListener( 'click', function ( e ) { if ( e.target === overlay ) { close(); } } );

			document.getElementById( 'ccf-deactivate-skip' ).addEventListener( 'click', function ( e ) {
				e.preventDefault();
				go();
			} );

			document.getElementById( 'ccf-deactivate-submit' ).addEventListener( 'click', function () {
				var checked = overlay.querySelector( 'input[name="ccf_deactivate_reason"]:checked' );
				var reason = checked ? checked.value : '';
				var comment = document.getElementById( 'ccf-deactivate-comment' ).value || '';

				// Nothing selected and no comment — just deactivate, send nothing.
				if ( ! reason && ! comment.trim() ) { go(); return; }

				var body = new URLSearchParams();
				body.append( 'action', 'ccf_deactivation_feedback' );
				body.append( 'nonce', nonce );
				body.append( 'reason', reason );
				body.append( 'comment', comment );

				fetch( ajaxurl, {
					method: 'POST',
					credentials: 'same-origin',
					headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
					body: body.toString()
				} ).then( go ).catch( go );
			} );
		} )();
		</script>
		<?php
	}

	/**
	 * Receive the survey submission and email it. Only reached when the user
	 * explicitly submits.
	 */
	public function handle_feedback() {
		if ( ! current_user_can( 'activate_plugins' ) || ! check_ajax_referer( 'ccf_deactivation_feedback', 'nonce', false ) ) {
			wp_send_json_error();
		}

		$reasons = $this->reasons();
		$key     = isset( $_POST['reason'] ) ? sanitize_key( wp_unslash( $_POST['reason'] ) ) : '';
		$reason  = isset( $reasons[ $key ] ) ? $reasons[ $key ] : __( '(none given)', 'custom-contact-forms' );
		$comment = isset( $_POST['comment'] ) ? sanitize_textarea_field( wp_unslash( $_POST['comment'] ) ) : '';

		$form_count = (int) wp_count_posts( 'ccf_form' )->publish;

		$lines = array(
			'Reason:  ' . $reason,
			'Comment: ' . ( $comment ? $comment : '(none)' ),
			'',
			'Site:    ' . home_url(),
			'Forms:   ' . $form_count,
			'Plugin:  ' . ( defined( 'CCF_VERSION' ) ? CCF_VERSION : '?' ),
			'WP:      ' . get_bloginfo( 'version' ),
			'PHP:     ' . PHP_VERSION,
		);

		$subject = sprintf( 'CCF deactivation feedback: %s', $reason );

		wp_mail( $this->admin_email, $subject, implode( "\n", $lines ) );

		wp_send_json_success();
	}
}
