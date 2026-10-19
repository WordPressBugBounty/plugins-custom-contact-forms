<?php
/**
 * Dashboard widget showing submission activity by form.
 *
 * Displays total submissions and a per-form breakdown for a selectable
 * period. Counts are cached in transients. When Pro is not active, a
 * locked payments stat links to the Pro site; Pro (or any add-on) can
 * inject real stats via the `ccf_dashboard_widget_extra_stats` filter.
 *
 * @since 7.14.0
 */

defined( 'ABSPATH' ) || exit;

class CCF_Dashboard_Widget {

	/**
	 * Valid periods and their day spans (0 = all time).
	 *
	 * @var array
	 */
	private $periods = array(
		'7'   => 7,
		'30'  => 30,
		'all' => 0,
	);

	/**
	 * Setup hooks
	 *
	 * @since 7.14.0
	 */
	public function setup() {
		add_action( 'wp_dashboard_setup', array( $this, 'register_widget' ) );
		add_action( 'wp_ajax_ccf_dashboard_widget', array( $this, 'ajax_widget_data' ) );
		add_action( 'wp_ajax_ccf_dismiss_widget_promo', array( $this, 'ajax_dismiss_promo' ) );
		add_action( 'wp_ajax_ccf_dashboard_blog', array( $this, 'ajax_blog_posts' ) );
		add_action( 'pre_get_posts', array( $this, 'filter_submissions_by_form' ) );
		add_action( 'save_post_ccf_submission', array( $this, 'clear_cache' ) );
		add_action( 'deleted_post', array( $this, 'clear_cache' ) );
	}

	/**
	 * Clear cached counts when submissions change.
	 *
	 * @since 7.14.0
	 */
	public function clear_cache() {
		foreach ( array_keys( $this->periods ) as $period ) {
			delete_transient( 'ccf_dash_counts_' . $period );
		}
	}

	/**
	 * Filter the submissions list table by form when linked from the widget.
	 *
	 * @since 7.14.0
	 * @param WP_Query $query Current query.
	 */
	public function filter_submissions_by_form( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}

		if ( 'ccf_submission' !== $query->get( 'post_type' ) ) {
			return;
		}

		if ( empty( $_GET['ccf_form_filter'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only list filter.
			return;
		}

		$query->set( 'post_parent', absint( $_GET['ccf_form_filter'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * Register the dashboard widget for users who can view submissions.
	 *
	 * @since 7.14.0
	 */
	public function register_widget() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}

		wp_add_dashboard_widget(
			'ccf_dashboard_widget',
			esc_html__( 'Custom Contact Forms', 'custom-contact-forms' ),
			array( $this, 'render_widget' )
		);
	}

	/**
	 * Sanitize a period value.
	 *
	 * @since 7.14.0
	 * @param string $period Raw period.
	 * @return string
	 */
	private function sanitize_period( $period ) {
		return isset( $this->periods[ $period ] ) ? $period : '7';
	}

	/**
	 * Get submission counts grouped by form for a period. Cached hourly.
	 *
	 * @since 7.14.0
	 * @param string $period One of 7|30|all.
	 * @return array { total: int, forms: array( form_id => count ) }
	 */
	private function get_counts( $period ) {
		global $wpdb;

		$period    = $this->sanitize_period( $period );
		$cache_key = 'ccf_dash_counts_' . $period;
		$cached    = get_transient( $cache_key );

		if ( false !== $cached && is_array( $cached ) ) {
			return $cached;
		}

		$days  = $this->periods[ $period ];
		$where = '';

		if ( $days > 0 ) {
			$since = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );
			$where = $wpdb->prepare( ' AND post_date_gmt >= %s', $since );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $where built with prepare above.
		$rows = $wpdb->get_results(
			"SELECT post_parent AS form_id, COUNT(*) AS num
			 FROM {$wpdb->posts}
			 WHERE post_type = 'ccf_submission'
			 AND post_status = 'publish'
			 {$where}
			 GROUP BY post_parent
			 ORDER BY num DESC"
		);

		$forms = array();
		$total = 0;

		foreach ( (array) $rows as $row ) {
			$forms[ (int) $row->form_id ] = (int) $row->num;
			$total                       += (int) $row->num;
		}

		$data = array(
			'total' => $total,
			'forms' => $forms,
		);

		set_transient( $cache_key, $data, HOUR_IN_SECONDS );

		return $data;
	}

	/**
	 * Render the widget shell with the user's saved period.
	 *
	 * @since 7.14.0
	 */
	public function render_widget() {
		$period = get_user_meta( get_current_user_id(), 'ccf_dashboard_widget_period', true );
		$period = $this->sanitize_period( $period );
		$nonce  = wp_create_nonce( 'ccf_dashboard_widget' );
		?>
		<div id="ccf-dash-widget" data-nonce="<?php echo esc_attr( $nonce ); ?>">
			<style>
				#ccf-dash-widget .ccf-dw-head{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:10px}
				#ccf-dash-widget .ccf-dw-total{font-size:13px;color:#3c434a}
				#ccf-dash-widget .ccf-dw-total strong{font-size:20px;color:#1d2327;margin-right:5px}
				#ccf-dash-widget table{width:100%;border-collapse:collapse}
				#ccf-dash-widget td{padding:7px 0;border-top:1px solid #f0f0f1;font-size:13px;color:#2271b1}
				#ccf-dash-widget td.ccf-dw-num{text-align:right;color:#1d2327;font-weight:600}
				#ccf-dash-widget tr.ccf-dw-hidden{display:none}
				#ccf-dash-widget .ccf-dw-more{display:block;margin-top:8px;font-size:12.5px;cursor:pointer;background:none;border:none;color:#2271b1;padding:0}
				#ccf-dash-widget .ccf-dw-empty{padding:14px 0;color:#646970;font-size:13px}
				#ccf-dash-widget .ccf-dw-pro{margin-top:12px;padding-top:10px;border-top:1px solid #f0f0f1;font-size:12.5px;color:#646970;display:flex;align-items:center;gap:6px;flex-wrap:wrap}
				#ccf-dash-widget .ccf-dw-pro a{color:#8659d6;font-weight:600;text-decoration:none}
				#ccf-dash-widget .ccf-dw-pro a:hover{text-decoration:underline}
				#ccf-dash-widget select{font-size:12.5px}
				#ccf-dash-widget .ccf-dw-promo{position:relative;background:linear-gradient(135deg,#f6f1ff 0%,#efe6ff 100%);border:1px solid #d9c8f5;border-radius:6px;padding:16px 34px 16px 16px;margin-bottom:14px;text-align:center}
				#ccf-dash-widget .ccf-dw-promo h3{margin:0 0 6px;font-size:14px;color:#1d2327}
				#ccf-dash-widget .ccf-dw-promo p{margin:0 0 12px;font-size:12.5px;color:#50575e;line-height:1.5}
				#ccf-dash-widget .ccf-dw-promo-btn{display:inline-block;background:#8659d6;border:1px solid #8659d6;color:#fff;border-radius:4px;padding:8px 20px;font-size:13px;font-weight:600;text-decoration:none}
				#ccf-dash-widget .ccf-dw-promo-btn:hover{background:#7248c0;border-color:#7248c0;color:#fff}
				#ccf-dash-widget .ccf-dw-promo-close{position:absolute;top:8px;right:10px;background:none;border:none;cursor:pointer;color:#8c8f94;font-size:14px;line-height:1;padding:3px}
				#ccf-dash-widget .ccf-dw-promo-close:hover{color:#1d2327}
				#ccf-dash-widget .ccf-dw-blog{margin-top:12px;padding-top:10px;border-top:1px solid #f0f0f1}
				#ccf-dash-widget .ccf-dw-blog h3{margin:0 0 6px;font-size:13px;font-weight:600;color:#1d2327}
				#ccf-dash-widget .ccf-dw-blog ul{margin:0;padding:0;list-style:none}
				#ccf-dash-widget .ccf-dw-blog li{display:flex;justify-content:space-between;align-items:baseline;gap:10px;margin:0;padding:5px 0;font-size:13px}
				#ccf-dash-widget .ccf-dw-blog li a{text-decoration:none}
				#ccf-dash-widget .ccf-dw-blog li a:hover{text-decoration:underline}
				#ccf-dash-widget .ccf-dw-blog .ccf-dw-date{flex:none;color:#646970;font-size:12px}
			</style>
			<?php $initial = $this->get_body_html( $period ); ?>
			<div class="ccf-dw-head">
				<span class="ccf-dw-total" id="ccf-dw-total"><?php echo $initial['total']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts. ?></span>
				<select id="ccf-dw-period">
					<option value="7" <?php selected( $period, '7' ); ?>><?php esc_html_e( 'Last 7 days', 'custom-contact-forms' ); ?></option>
					<option value="30" <?php selected( $period, '30' ); ?>><?php esc_html_e( 'Last 30 days', 'custom-contact-forms' ); ?></option>
					<option value="all" <?php selected( $period, 'all' ); ?>><?php esc_html_e( 'All time', 'custom-contact-forms' ); ?></option>
				</select>
			</div>
			<?php echo $this->get_promo_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts. ?>
			<div id="ccf-dw-body"><?php echo $initial['body']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts. ?></div>
			<?php echo $this->get_pro_row_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts. ?>
			<?php if ( $this->blog_enabled() ) : ?>
				<div class="ccf-dw-blog" id="ccf-dw-blog" hidden></div>
			<?php endif; ?>
			<script>
			( function() {
				var root = document.getElementById( 'ccf-dash-widget' );
				if ( ! root ) { return; }

				function wire() {
					var more = root.querySelector( '.ccf-dw-more' );
					if ( more ) {
						more.addEventListener( 'click', function() {
							root.querySelectorAll( 'tr.ccf-dw-hidden' ).forEach( function( tr ) { tr.classList.remove( 'ccf-dw-hidden' ); } );
							more.remove();
						} );
					}
				}

				wire();

				// Blog posts load after the dashboard has drawn, so a slow or
				// unreachable feed can never hold up the page.
				var blog = document.getElementById( 'ccf-dw-blog' );
				if ( blog ) {
					var bd = new FormData();
					bd.append( 'action', 'ccf_dashboard_blog' );
					bd.append( 'nonce', root.getAttribute( 'data-nonce' ) );
					fetch( ajaxurl, { method: 'POST', credentials: 'same-origin', body: bd } )
						.then( function( r ) { return r.json(); } )
						.then( function( res ) {
							if ( res && res.success && res.data.html ) {
								blog.innerHTML = res.data.html;
								blog.hidden = false;
							}
						} )
						.catch( function() {} );
				}

				var promo = document.getElementById( 'ccf-dw-promo' );
				if ( promo ) {
					promo.querySelector( '.ccf-dw-promo-close' ).addEventListener( 'click', function() {
						promo.style.display = 'none';
						var pd = new FormData();
						pd.append( 'action', 'ccf_dismiss_widget_promo' );
						pd.append( 'nonce', root.getAttribute( 'data-nonce' ) );
						fetch( ajaxurl, { method: 'POST', credentials: 'same-origin', body: pd } );
					} );
				}

				document.getElementById( 'ccf-dw-period' ).addEventListener( 'change', function() {
					var data = new FormData();
					data.append( 'action', 'ccf_dashboard_widget' );
					data.append( 'nonce', root.getAttribute( 'data-nonce' ) );
					data.append( 'period', this.value );
					fetch( ajaxurl, { method: 'POST', credentials: 'same-origin', body: data } )
						.then( function( r ) { return r.json(); } )
						.then( function( res ) {
							if ( res && res.success ) {
								document.getElementById( 'ccf-dw-body' ).innerHTML = res.data.body;
								document.getElementById( 'ccf-dw-total' ).innerHTML = res.data.total;
								wire();
							}
						} );
				} );
			} )();
			</script>
		</div>
		<?php
	}

	/**
	 * Build the list body HTML for a period.
	 *
	 * @since 7.14.0
	 * @param string $period Period key.
	 * @return array {
	 *     @type string $body  Markup for the widget body.
	 *     @type string $total Markup for the total line.
	 * }
	 */
	private function get_body_html( $period ) {
		$data = $this->get_counts( $period );

		$total_line = sprintf(
			'<strong>%s</strong>%s',
			esc_html( number_format_i18n( $data['total'] ) ),
			esc_html( _n( 'submission', 'submissions', $data['total'], 'custom-contact-forms' ) )
		);

		if ( empty( $data['forms'] ) ) {
			$html = '<div class="ccf-dw-empty">' . esc_html__( 'No submissions in this period. Your forms will report here.', 'custom-contact-forms' ) . '</div>';
			return array( 'body' => $html, 'total' => $total_line );
		}

		$rows    = '';
		$i       = 0;
		$visible = 5;

		foreach ( $data['forms'] as $form_id => $num ) {
			$title = get_the_title( $form_id );

			if ( '' === $title ) {
				$title = sprintf( esc_html__( 'Form #%d', 'custom-contact-forms' ), $form_id );
			}

			$link = admin_url( 'edit.php?post_type=ccf_submission&ccf_form_filter=' . absint( $form_id ) );

			$hidden = ( $i >= $visible ) ? ' class="ccf-dw-hidden"' : '';

			$rows .= sprintf(
				'<tr%s><td><a href="%s">%s</a></td><td class="ccf-dw-num">%s</td></tr>',
				$hidden,
				esc_url( $link ),
				esc_html( $title ),
				esc_html( number_format_i18n( $num ) )
			);

			$i++;
		}

		$html = '<table><tbody>' . $rows . '</tbody></table>';

		if ( $i > $visible ) {
			$html .= '<button type="button" class="ccf-dw-more">' . esc_html( sprintf( __( 'Show %d more', 'custom-contact-forms' ), $i - $visible ) ) . '</button>';
		}

		return array( 'body' => $html, 'total' => $total_line );
	}

	/**
	 * Build the upgrade promo card (free plugin, until dismissed per user).
	 *
	 * @since 7.14.0
	 * @return string
	 */
	private function get_promo_html() {
		if ( defined( 'CCFP_VERSION' ) ) {
			return '';
		}

		if ( get_user_meta( get_current_user_id(), 'ccf_widget_promo_dismissed', true ) ) {
			return '';
		}

		$pro_url = ccf_pro_url( 'dashboard-widget' );

		return sprintf(
			'<div class="ccf-dw-promo" id="ccf-dw-promo">
				<h3>%s</h3>
				<p>%s</p>
				<a class="ccf-dw-promo-btn" href="%s" target="_blank" rel="noopener">%s</a>
				<button type="button" class="ccf-dw-promo-close" aria-label="%s">&#10005;</button>
			</div>',
			esc_html__( 'Do more with your forms', 'custom-contact-forms' ),
			esc_html__( 'Upgrade to Pro for Stripe payments, PDF receipts, signed agreements, and more premium fields.', 'custom-contact-forms' ),
			esc_url( $pro_url ),
			esc_html__( 'Upgrade to Pro', 'custom-contact-forms' ),
			esc_attr__( 'Dismiss', 'custom-contact-forms' )
		);
	}

	/**
	 * AJAX: dismiss the widget promo for the current user.
	 *
	 * @since 7.14.0
	 */
	public function ajax_dismiss_promo() {
		check_ajax_referer( 'ccf_dashboard_widget', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error();
		}

		update_user_meta( get_current_user_id(), 'ccf_widget_promo_dismissed', 1 );

		wp_send_json_success();
	}

	/**
	 * Build the payments / extra stats row.
	 *
	 * Pro and add-ons can replace this via the filter; when Pro is active
	 * and provides nothing, the row is hidden entirely.
	 *
	 * @since 7.14.0
	 * @return string
	 */
	private function get_pro_row_html() {
		$extra = apply_filters( 'ccf_dashboard_widget_extra_stats', '' );

		if ( '' !== $extra ) {
			return '<div class="ccf-dw-pro">' . wp_kses_post( $extra ) . '</div>';
		}

		if ( defined( 'CCFP_VERSION' ) ) {
			return '';
		}

		if ( ! get_user_meta( get_current_user_id(), 'ccf_widget_promo_dismissed', true ) ) {
			return '';
		}

		$pro_url = ccf_pro_url( 'dashboard-widget-empty' );

		return sprintf(
			'<div class="ccf-dw-pro"><span aria-hidden="true">&#128274;</span> %s <a href="%s" target="_blank" rel="noopener">%s</a></div>',
			esc_html__( 'Payments collected: none', 'custom-contact-forms' ),
			esc_url( $pro_url ),
			esc_html__( 'Enable payments with Pro &rarr;', 'custom-contact-forms' )
		);
	}

	/**
	 * Whether the "From our blog" section is shown.
	 *
	 * Developers can turn it off with:
	 * add_filter( 'ccf_dashboard_blog_feed', '__return_false' );
	 *
	 * @since 7.19.0
	 * @return bool
	 */
	public function blog_enabled() {
		return (bool) apply_filters( 'ccf_dashboard_blog_feed', true );
	}

	/**
	 * Get the latest posts from the Custom Contact Forms blog.
	 *
	 * The feed is read from this site's server, never from the visitor's
	 * browser, and the result is cached for 12 hours (1 hour after a failed
	 * fetch), so a dashboard visit normally makes no outside request at all.
	 * Only titles, links and dates are used: no images, scripts or styles are
	 * loaded from the blog. Links pointing anywhere other than
	 * customformspro.com are dropped.
	 *
	 * @since 7.19.0
	 * @return array List of array( title, url, date ) items, newest first.
	 */
	private function get_blog_posts() {
		$cache_key = 'ccf_dash_blog_posts';
		$cached    = get_transient( $cache_key );

		if ( false !== $cached && is_array( $cached ) ) {
			return $cached;
		}

		if ( ! function_exists( 'fetch_feed' ) ) {
			require_once ABSPATH . WPINC . '/feed.php';
		}

		$set_timeout = function ( $feed ) {
			$feed->set_timeout( 5 );
			$feed->enable_cache( false );
		};

		add_action( 'wp_feed_options', $set_timeout );
		$feed = fetch_feed( 'https://customformspro.com/feed/' );
		remove_action( 'wp_feed_options', $set_timeout );

		$posts = array();

		if ( ! is_wp_error( $feed ) ) {
			foreach ( $feed->get_items( 0, 3 ) as $item ) {
				$title = trim( wp_strip_all_tags( html_entity_decode( (string) $item->get_title(), ENT_QUOTES, 'UTF-8' ) ) );
				$link  = esc_url_raw( (string) $item->get_permalink() );
				$host  = wp_parse_url( $link, PHP_URL_HOST );

				if ( '' === $title || ! in_array( $host, array( 'customformspro.com', 'www.customformspro.com' ), true ) ) {
					continue;
				}

				$posts[] = array(
					'title' => $title,
					'url'   => $link,
					'date'  => (int) $item->get_date( 'U' ),
				);
			}
		}

		set_transient( $cache_key, $posts, empty( $posts ) ? HOUR_IN_SECONDS : 12 * HOUR_IN_SECONDS );

		return $posts;
	}

	/**
	 * AJAX: return the "From our blog" markup.
	 *
	 * Used by both the WordPress dashboard widget and the Forms dashboard,
	 * which share one cached copy of the feed. "html" is the widget section
	 * with its heading, "list" is the bare list for the Forms dashboard panel.
	 *
	 * @since 7.19.0
	 */
	public function ajax_blog_posts() {
		check_ajax_referer( 'ccf_dashboard_widget', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) || ! $this->blog_enabled() ) {
			wp_send_json_error();
		}

		$posts = $this->get_blog_posts();

		if ( empty( $posts ) ) {
			wp_send_json_success(
				array(
					'html' => '',
					'list' => '',
				)
			);
		}

		$source = ( isset( $_POST['context'] ) && 'forms-dashboard' === sanitize_key( wp_unslash( $_POST['context'] ) ) ) ? 'forms-dashboard' : 'dashboard-widget';
		$items  = '';

		foreach ( $posts as $post ) {
			$url = add_query_arg(
				array(
					'utm_source'   => 'ccf-free',
					'utm_medium'   => 'plugin',
					'utm_campaign' => 'blog',
					'utm_content'  => $source,
				),
				$post['url']
			);

			$items .= sprintf(
				'<li><a href="%s" target="_blank" rel="noopener">%s</a>%s</li>',
				esc_url( $url ),
				esc_html( $post['title'] ),
				$post['date'] > 0 ? '<span class="ccf-dw-date">' . esc_html( date_i18n( get_option( 'date_format' ), $post['date'] ) ) . '</span>' : ''
			);
		}

		$list = '<ul>' . $items . '</ul>';
		$html = '<h3>' . esc_html__( 'From our blog', 'custom-contact-forms' ) . '</h3>' . $list;

		wp_send_json_success(
			array(
				'html' => $html,
				'list' => $list,
			)
		);
	}

	/**
	 * AJAX: return body HTML for a period and persist the preference.
	 *
	 * @since 7.14.0
	 */
	public function ajax_widget_data() {
		check_ajax_referer( 'ccf_dashboard_widget', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error();
		}

		$period = isset( $_POST['period'] ) ? $this->sanitize_period( sanitize_key( wp_unslash( $_POST['period'] ) ) ) : '7';

		update_user_meta( get_current_user_id(), 'ccf_dashboard_widget_period', $period );

		wp_send_json_success( $this->get_body_html( $period ) );
	}

	/**
	 * Return an instance of the current class
	 *
	 * @since 7.14.0
	 * @return CCF_Dashboard_Widget
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
