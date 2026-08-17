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
	 * Copy for the Pro feature match shown after the survey.
	 *
	 * Returns an empty set when the Pro add-on is active, which disables the
	 * match path entirely rather than telling an existing customer about
	 * features they already have.
	 *
	 * @since 7.15.0
	 * @return array
	 */
	private function get_pro_copy() {
		if ( defined( 'CCFP_VERSION' ) ) {
			return array();
		}

		return array(
			'url'        => ccf_pro_url( 'deactivation-survey' ),
			'signature'  => __( 'Before you go — Custom Contact Forms Pro has a signature field. People sign with a finger, mouse or stylus, and it works on the same form as a payment.', 'custom-contact-forms' ),
			'payments'   => __( 'Before you go — Custom Contact Forms Pro takes Stripe payments on the form itself. Cards, Apple Pay and Google Pay, with no cart or checkout page.', 'custom-contact-forms' ),
			'pdf'        => __( 'Before you go — Custom Contact Forms Pro attaches a PDF receipt to notification emails, and any submission can be downloaded as a PDF.', 'custom-contact-forms' ),
			'multistep'  => __( 'Before you go — Custom Contact Forms Pro splits long forms into steps, with a progress bar and validation on each step.', 'custom-contact-forms' ),
			'survey'     => __( 'Before you go — Custom Contact Forms Pro adds survey grids and star rating fields.', 'custom-contact-forms' ),
			'agreements' => __( 'Before you go — Custom Contact Forms Pro has a consent field that records the exact terms someone agreed to, alongside a signature field. Waiver and agreement templates are included.', 'custom-contact-forms' ),
		);
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

				<div id="ccf-deactivate-match" hidden>
					<p class="ccf-deactivate-matchlead"></p>
					<p class="ccf-deactivate-matchactions">
						<a href="#" class="button button-primary" id="ccf-deactivate-matchlink" target="_blank" rel="noopener"><?php esc_html_e( 'Show me', 'custom-contact-forms' ); ?></a>
						<button type="button" class="button" id="ccf-deactivate-matchskip"><?php esc_html_e( 'No thanks, deactivate', 'custom-contact-forms' ); ?></button>
					</p>
				</div>

				<div id="ccf-deactivate-error-wrap" hidden>
					<p class="ccf-deactivate-errorlead"><?php esc_html_e( 'Saw an error message? Paste it below and we will look into it. Error messages usually name the file and line, which is most of the fix.', 'custom-contact-forms' ); ?></p>
					<textarea id="ccf-deactivate-error" rows="3" placeholder="<?php esc_attr_e( 'Paste the error message here (optional)', 'custom-contact-forms' ); ?>"></textarea>
				</div>

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
			#ccf-deactivate-comment,#ccf-deactivate-error{width:100%;box-sizing:border-box;border:1px solid #dcdcde;border-radius:6px;padding:8px 10px;font-size:13px;resize:vertical;}
			#ccf-deactivate-error-wrap{margin-top:10px;}
			.ccf-deactivate-errorlead{margin:0 0 6px;font-size:12px;color:#646970;}
			#ccf-deactivate-match{margin-top:14px;padding:12px 14px;background:#f3faf7;border:1px solid #d1ede2;border-radius:6px;}
			.ccf-deactivate-matchlead{margin:0 0 10px;font-size:13px;color:#1d2327;}
			.ccf-deactivate-matchactions{margin:0;display:flex;gap:8px;flex-wrap:wrap;}
			.ccf-deactivate-actions{display:flex;align-items:center;gap:14px;margin-top:16px;}
			.ccf-deactivate-skip{color:#787c82;font-size:13px;text-decoration:none;}
			.ccf-deactivate-skip:hover{color:#d63638;}
		</style>

		<script>
		( function () {
			var basename = <?php echo wp_json_encode( $this->plugin_basename ); ?>;
			var nonce = <?php echo wp_json_encode( $nonce ); ?>;
			var deactivateUrl = '';
			var proCopy = <?php echo wp_json_encode( $this->get_pro_copy() ); ?>;

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

			// Someone reporting a fault has the error on screen in another tab.
			// Ask for it then, rather than leaving "it didn't work" as the whole
			// report — the text usually names the file and line.
			var errorWrap = document.getElementById( 'ccf-deactivate-error-wrap' );
			var commentBox = document.getElementById( 'ccf-deactivate-comment' );

			function maybeRevealError() {
				var picked = overlay.querySelector( 'input[name="ccf_deactivate_reason"]:checked' );
				var reason = picked ? picked.value : '';
				var typed = ( commentBox.value || '' ).toLowerCase();
				var wants = ( 'not_working' === reason || 'other' === reason ) ||
					typed.indexOf( 'error' ) !== -1 ||
					typed.indexOf( 'broke' ) !== -1 ||
					typed.indexOf( 'crash' ) !== -1 ||
					typed.indexOf( 'white screen' ) !== -1 ||
					typed.indexOf( 'fatal' ) !== -1;

				errorWrap.hidden = ! wants;
			}

			overlay.querySelectorAll( 'input[name="ccf_deactivate_reason"]' ).forEach( function ( input ) {
				input.addEventListener( 'change', maybeRevealError );
			} );
			commentBox.addEventListener( 'input', maybeRevealError );

			document.getElementById( 'ccf-deactivate-skip' ).addEventListener( 'click', function ( e ) {
				e.preventDefault();
				go();
			} );

			// People who leave because a feature seemed missing have just told
			// us, in their own words, what they were looking for. Where that
			// names something Pro does, say so once. It never blocks the
			// deactivation — they have already decided.
			var proMatches = [
				{ re: /\bsign|signature|e-?sign|initial\b/i, msg: proCopy.signature },
				{ re: /\bpay|payment|stripe|checkout|charge|deposit|invoice|credit card\b/i, msg: proCopy.payments },
				{ re: /\bpdf|receipt\b/i, msg: proCopy.pdf },
				{ re: /\bmulti[- ]?step|multi[- ]?page|wizard|page break\b/i, msg: proCopy.multistep },
				{ re: /\bsurvey|likert|rating|star\b/i, msg: proCopy.survey },
				{ re: /\bwaiver|agreement|terms|consent|contract\b/i, msg: proCopy.agreements }
			];

			function findProMatch( text ) {
				if ( ! text || ! text.trim() ) { return ''; }
				for ( var i = 0; i < proMatches.length; i++ ) {
					if ( proMatches[ i ].re.test( text ) ) { return proMatches[ i ].msg; }
				}
				return '';
			}

			var matchWrap = document.getElementById( 'ccf-deactivate-match' );
			var matchLead = matchWrap.querySelector( '.ccf-deactivate-matchlead' );
			var matchLink = document.getElementById( 'ccf-deactivate-matchlink' );
			var matchShown = false;

			matchLink.href = proCopy.url;
			document.getElementById( 'ccf-deactivate-matchskip' ).addEventListener( 'click', function () {
				send();
			} );

			function send() {
				var checked = overlay.querySelector( 'input[name="ccf_deactivate_reason"]:checked' );
				var reason = checked ? checked.value : '';
				var comment = document.getElementById( 'ccf-deactivate-comment' ).value || '';
				var errorText = document.getElementById( 'ccf-deactivate-error' ).value || '';

				// Nothing selected and nothing written — just deactivate, send nothing.
				if ( ! reason && ! comment.trim() && ! errorText.trim() ) { go(); return; }

				var body = new URLSearchParams();
				body.append( 'action', 'ccf_deactivation_feedback' );
				body.append( 'nonce', nonce );
				body.append( 'reason', reason );
				body.append( 'comment', comment );
				body.append( 'error_text', errorText );

				fetch( ajaxurl, {
					method: 'POST',
					credentials: 'same-origin',
					headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
					body: body.toString()
				} ).then( go ).catch( go );
			}

			document.getElementById( 'ccf-deactivate-submit' ).addEventListener( 'click', function () {
				var comment = document.getElementById( 'ccf-deactivate-comment' ).value || '';
				var msg = matchShown ? '' : findProMatch( comment );

				if ( msg ) {
					matchShown = true;
					matchLead.textContent = msg;
					matchWrap.hidden = false;
					return;
				}

				send();
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

		// Capped: error output can run to hundreds of lines of stack trace, and
		// the first part is where the useful detail is.
		$error_text = isset( $_POST['error_text'] ) ? sanitize_textarea_field( wp_unslash( $_POST['error_text'] ) ) : '';
		$error_text = mb_substr( $error_text, 0, 2000 );

		$form_count = (int) wp_count_posts( 'ccf_form' )->publish;

		// A form count alone cannot tell "used it for a month and moved on"
		// apart from "built a form and never launched it" — which mean
		// opposite things. The submission count separates them.
		$submission_counts = wp_count_posts( 'ccf_submission' );
		$submissions       = isset( $submission_counts->publish ) ? (int) $submission_counts->publish : 0;

		// How long they actually had it. No install date has ever been stored,
		// so use the oldest form as a proxy — it works retroactively for every
		// existing install, which a new option would not.
		$age = '(no forms created)';

		if ( $form_count > 0 ) {
			$oldest = get_posts(
				array(
					'post_type'      => 'ccf_form',
					'post_status'    => 'publish',
					'posts_per_page' => 1,
					'orderby'        => 'date',
					'order'          => 'ASC',
					'fields'         => 'ids',
				)
			);

			if ( ! empty( $oldest ) ) {
				$days = (int) floor( ( time() - get_post_time( 'U', true, $oldest[0] ) ) / DAY_IN_SECONDS );
				$age  = sprintf( '%d day%s', $days, 1 === $days ? '' : 's' );
			}
		}

		$lines = array(
			'Reason:  ' . $reason,
			'Comment: ' . ( $comment ? $comment : '(none)' ),
		);

		if ( '' !== trim( $error_text ) ) {
			$lines[] = '';
			$lines[] = 'Error reported:';
			$lines[] = $error_text;
		}

		$lines = array_merge( $lines, array(
			'',
			'Site:    ' . home_url(),
			'Forms:   ' . $form_count,
			'Entries: ' . $submissions,
			'Age:     ' . $age,
			'Plugin:  ' . ( defined( 'CCF_VERSION' ) ? CCF_VERSION : '?' ),
			'WP:      ' . get_bloginfo( 'version' ),
			'PHP:     ' . PHP_VERSION,
		) );

		$subject = sprintf( 'CCF deactivation feedback: %s', $reason );

		wp_mail( $this->admin_email, $subject, implode( "\n", $lines ) );

		wp_send_json_success();
	}
}
