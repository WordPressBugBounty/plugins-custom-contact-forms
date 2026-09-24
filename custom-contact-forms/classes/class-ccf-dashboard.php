<?php
/**
 * Forms dashboard.
 *
 * The first screen under Forms: totals for the chosen period, an entries
 * and views chart, a per-form table, and suggested next steps.
 *
 * @package Custom_Contact_Forms
 * @since   7.17.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CCF_Dashboard {

	/**
	 * Page slug.
	 */
	const SLUG = 'ccf-dashboard';

	/**
	 * Capability needed to see the dashboard, matching the forms list.
	 */
	const CAPABILITY = 'edit_posts';

	/**
	 * Hook suffix of the page.
	 *
	 * @var string
	 */
	private $hook = '';

	/**
	 * Register hooks.
	 */
	public function setup() {
		add_action( 'admin_menu', array( $this, 'menu' ), 9 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'wp_ajax_ccf_dashboard_report', array( $this, 'ajax_report' ) );
	}

	/**
	 * Add the page as the first item under Forms. WordPress links the
	 * top-level Forms menu to its first submenu item, so clicking Forms opens
	 * the dashboard.
	 */
	public function menu() {
		$this->hook = add_submenu_page(
			'edit.php?post_type=ccf_form',
			__( 'Forms Dashboard', 'custom-contact-forms' ),
			__( 'Dashboard', 'custom-contact-forms' ),
			self::CAPABILITY,
			self::SLUG,
			array( $this, 'render' ),
			0
		);
	}

	/**
	 * Dashboard URL.
	 *
	 * @return string
	 */
	public static function url() {
		return admin_url( 'edit.php?post_type=ccf_form&page=' . self::SLUG );
	}

	/**
	 * Where "New form" goes: the templates screen for people who can use it,
	 * otherwise a blank form.
	 *
	 * @return string
	 */
	public static function new_form_url() {
		return current_user_can( 'manage_options' ) ? admin_url( 'edit.php?post_type=ccf_form&page=ccf-templates' ) : admin_url( 'post-new.php?post_type=ccf_form' );
	}

	/**
	 * Load assets on the dashboard only.
	 *
	 * @param string $hook Current admin page.
	 */
	public function enqueue( $hook ) {
		if ( ! $this->hook || $hook !== $this->hook ) {
			return;
		}

		wp_enqueue_style( 'ccf-dashboard', plugins_url( '/build/css/ccf-dashboard.css', dirname( __FILE__ ) ), array(), CCF_VERSION );
		wp_enqueue_script( 'ccf-dashboard', plugins_url( '/assets/js/ccf-dashboard.js', dirname( __FILE__ ) ), array(), CCF_VERSION, true );

		$end   = CCF_Stats::today();
		$start = wp_date( 'Y-m-d', time() - 29 * DAY_IN_SECONDS );

		wp_localize_script(
			'ccf-dashboard',
			'ccfDashboard',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'ccf_dashboard' ),
				'report'  => CCF_Stats::report( $start, $end ),
				'today'   => $end,
				'locale'  => str_replace( '_', '-', get_user_locale() ),
				'i18n'    => array(
					'entries'      => __( 'Entries', 'custom-contact-forms' ),
					'views'        => __( 'Views', 'custom-contact-forms' ),
					'noViews'      => __( 'n/a', 'custom-contact-forms' ),
					'never'        => __( 'None yet', 'custom-contact-forms' ),
					'loading'      => __( 'Loading...', 'custom-contact-forms' ),
					'error'        => __( 'The report could not be loaded. Please try again.', 'custom-contact-forms' ),
					'badRange'     => __( 'Choose a start date on or before the end date, up to one year apart.', 'custom-contact-forms' ),
					'noForms'      => __( 'No forms yet.', 'custom-contact-forms' ),
					'chartLabel'   => __( 'Entries and views per day', 'custom-contact-forms' ),
					/* translators: 1: number of entries, 2: date */
					'tipEntries'   => __( '%1$s entries on %2$s', 'custom-contact-forms' ),
					/* translators: %s: number of views */
					'tipViews'     => __( '%s views', 'custom-contact-forms' ),
				),
			)
		);
	}

	/**
	 * Validate a Y-m-d date.
	 *
	 * @param string $date Date.
	 * @return bool
	 */
	private static function valid_date( $date ) {
		$parsed = DateTime::createFromFormat( 'Y-m-d', (string) $date );
		return $parsed && $parsed->format( 'Y-m-d' ) === $date;
	}

	/**
	 * Report for another period.
	 */
	public function ajax_report() {
		check_ajax_referer( 'ccf_dashboard', 'nonce' );

		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_send_json_error( null, 403 );
		}

		$start = isset( $_POST['start'] ) ? sanitize_text_field( wp_unslash( $_POST['start'] ) ) : '';
		$end   = isset( $_POST['end'] ) ? sanitize_text_field( wp_unslash( $_POST['end'] ) ) : '';

		if ( ! self::valid_date( $start ) || ! self::valid_date( $end ) || $start > $end ) {
			wp_send_json_error( null, 400 );
		}

		if ( ( strtotime( $end ) - strtotime( $start ) ) / DAY_IN_SECONDS > 366 ) {
			wp_send_json_error( null, 400 );
		}

		wp_send_json_success( CCF_Stats::report( $start, $end ) );
	}

	/**
	 * Suggested next steps, most useful first. At most four are shown.
	 *
	 * @return array
	 */
	private function next_steps() {
		$steps = array();
		$forms = CCF_Stats::forms();

		if ( empty( $forms ) ) {
			$steps[] = array(
				'title' => __( 'Create your first form', 'custom-contact-forms' ),
				'text'  => __( 'Start from a template or describe the form you need.', 'custom-contact-forms' ),
				'url'   => self::new_form_url(),
				'cta'   => __( 'Choose a template', 'custom-contact-forms' ),
			);
		}

		if ( CCF_Auto_Updates::can_enable() && ! CCF_Auto_Updates::is_enabled() ) {
			$steps[] = array(
				'title' => __( 'Turn on automatic updates', 'custom-contact-forms' ),
				'text'  => __( 'New versions and security fixes install as soon as they are released.', 'custom-contact-forms' ),
				'url'   => CCF_Auto_Updates::enable_url(),
				'cta'   => __( 'Turn on', 'custom-contact-forms' ),
			);
		}

		$silent = array();
		foreach ( $forms as $form ) {
			$notifications = get_post_meta( $form->ID, 'ccf_form_notifications', true );
			$active        = false;

			foreach ( (array) $notifications as $notification ) {
				if ( is_array( $notification ) && ! empty( $notification['active'] ) && ! empty( $notification['addresses'] ) ) {
					$active = true;
					break;
				}
			}

			if ( ! $active ) {
				$silent[] = $form;
			}
		}

		if ( ! empty( $silent ) ) {
			$steps[] = array(
				'title' => __( 'Get emailed about new entries', 'custom-contact-forms' ),
				'text'  => sprintf(
					/* translators: %d: number of forms */
					_n( '%d form sends no email when someone submits it.', '%d forms send no email when someone submits them.', count( $silent ), 'custom-contact-forms' ),
					count( $silent )
				),
				'url'   => get_edit_post_link( $silent[0]->ID, 'raw' ),
				'cta'   => __( 'Add a notification', 'custom-contact-forms' ),
			);
		}

		$turnstile = class_exists( 'CCF_Turnstile' ) && CCF_Turnstile::factory()->is_active();
		if ( ! $turnstile && ! empty( $forms ) && current_user_can( 'manage_options' ) ) {
			$steps[] = array(
				'title' => __( 'Stop more spam', 'custom-contact-forms' ),
				'text'  => __( 'Cloudflare Turnstile blocks bots without making visitors solve puzzles. It is free.', 'custom-contact-forms' ),
				'url'   => admin_url( 'edit.php?post_type=ccf_form&page=ccf-settings' ),
				'cta'   => __( 'Set up Turnstile', 'custom-contact-forms' ),
			);
		}

		if ( ! defined( 'CCFP_VERSION' ) ) {
			$steps[] = array(
				'title' => __( 'Take payments on your forms', 'custom-contact-forms' ),
				'text'  => __( 'Custom Contact Forms Pro adds Stripe and PayPal payments, PDF receipts and signed agreements to the forms you already have.', 'custom-contact-forms' ),
				'url'   => ccf_pro_url( 'dashboard-next-steps' ),
				'cta'   => __( 'See Pro', 'custom-contact-forms' ),
				'pro'   => true,
			);
		}

		return array_slice( apply_filters( 'ccf_dashboard_next_steps', $steps ), 0, 4 );
	}

	/**
	 * Page markup. The numbers, chart and table are filled in by
	 * assets/js/ccf-dashboard.js from the localized report.
	 */
	public function render() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}

		$steps = $this->next_steps();
		$cards = apply_filters(
			'ccf_dashboard_cards',
			array(
				'entries'    => __( 'Entries', 'custom-contact-forms' ),
				'views'      => __( 'Form views', 'custom-contact-forms' ),
				'conversion' => __( 'Conversion rate', 'custom-contact-forms' ),
				'spam'       => __( 'Spam blocked', 'custom-contact-forms' ),
				'forms'      => __( 'Active forms', 'custom-contact-forms' ),
			)
		);
		?>
		<div class="wrap ccf-dash">
			<div class="ccf-dash-head">
				<h1><?php esc_html_e( 'Forms Dashboard', 'custom-contact-forms' ); ?></h1>
				<div class="ccf-dash-actions">
					<a class="button" href="<?php echo esc_url( admin_url( 'edit.php?post_type=ccf_form' ) ); ?>"><?php esc_html_e( 'All forms', 'custom-contact-forms' ); ?></a>
					<a class="button button-primary" href="<?php echo esc_url( self::new_form_url() ); ?>"><?php esc_html_e( 'New form', 'custom-contact-forms' ); ?></a>
				</div>
			</div>
			<hr class="wp-header-end">


			<div class="ccf-dash-range" role="group" aria-label="<?php esc_attr_e( 'Date range', 'custom-contact-forms' ); ?>">
				<button type="button" class="ccf-dash-range-btn" data-days="7"><?php esc_html_e( 'Last 7 days', 'custom-contact-forms' ); ?></button>
				<button type="button" class="ccf-dash-range-btn is-active" data-days="30" aria-pressed="true"><?php esc_html_e( 'Last 30 days', 'custom-contact-forms' ); ?></button>
				<button type="button" class="ccf-dash-range-btn" data-days="90"><?php esc_html_e( 'Last 90 days', 'custom-contact-forms' ); ?></button>
				<button type="button" class="ccf-dash-range-btn" data-days="custom"><?php esc_html_e( 'Custom', 'custom-contact-forms' ); ?></button>
				<span class="ccf-dash-custom" hidden>
					<label><span class="screen-reader-text"><?php esc_html_e( 'Start date', 'custom-contact-forms' ); ?></span><input type="date" class="ccf-dash-start"></label>
					<span aria-hidden="true"><?php esc_html_e( 'to', 'custom-contact-forms' ); ?></span>
					<label><span class="screen-reader-text"><?php esc_html_e( 'End date', 'custom-contact-forms' ); ?></span><input type="date" class="ccf-dash-end"></label>
					<button type="button" class="button ccf-dash-apply"><?php esc_html_e( 'Apply', 'custom-contact-forms' ); ?></button>
				</span>
				<span class="ccf-dash-status" aria-live="polite"></span>
			</div>

			<div class="ccf-dash-cards">
				<?php foreach ( $cards as $key => $label ) : ?>
					<div class="ccf-dash-card" data-card="<?php echo esc_attr( $key ); ?>">
						<span class="ccf-dash-card-label"><?php echo esc_html( $label ); ?></span>
						<span class="ccf-dash-card-value">0</span>
					</div>
				<?php endforeach; ?>
			</div>

			<div class="ccf-dash-grid">
				<div class="ccf-dash-panel ccf-dash-chart-panel">
					<div class="ccf-dash-panel-head">
						<h2><?php esc_html_e( 'Entries over time', 'custom-contact-forms' ); ?></h2>
						<div class="ccf-dash-legend" role="group" aria-label="<?php esc_attr_e( 'Show on chart', 'custom-contact-forms' ); ?>">
							<button type="button" class="ccf-dash-key ccf-dash-key-entries" data-series="entries" aria-pressed="true"><?php esc_html_e( 'Entries', 'custom-contact-forms' ); ?></button>
							<button type="button" class="ccf-dash-key ccf-dash-key-views" data-series="views" aria-pressed="false"><?php esc_html_e( 'Views', 'custom-contact-forms' ); ?></button>
						</div>
					</div>
					<div class="ccf-dash-chart"></div>
					<p class="ccf-dash-note"><?php esc_html_e( 'Views and spam are counted from version 7.17 onward. Views by people who can edit forms are not counted.', 'custom-contact-forms' ); ?></p>
				</div>

				<div class="ccf-dash-panel ccf-dash-steps">
					<h2><?php esc_html_e( 'Next steps', 'custom-contact-forms' ); ?></h2>
					<?php if ( empty( $steps ) ) : ?>
						<p class="ccf-dash-empty"><?php esc_html_e( 'You are all set.', 'custom-contact-forms' ); ?></p>
					<?php else : ?>
						<ul>
							<?php foreach ( $steps as $step ) : ?>
								<li class="<?php echo ! empty( $step['pro'] ) ? 'is-pro' : ''; ?>">
									<strong><?php echo esc_html( $step['title'] ); ?><?php if ( ! empty( $step['pro'] ) ) : ?> <span class="ccf-dash-pro"><?php esc_html_e( 'Pro', 'custom-contact-forms' ); ?></span><?php endif; ?></strong>
									<span><?php echo esc_html( $step['text'] ); ?></span>
									<a href="<?php echo esc_url( $step['url'] ); ?>" <?php echo ! empty( $step['pro'] ) ? 'target="_blank" rel="noopener"' : ''; ?>><?php echo esc_html( $step['cta'] ); ?></a>
								</li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</div>
			</div>

			<div class="ccf-dash-panel">
				<h2><?php esc_html_e( 'Forms', 'custom-contact-forms' ); ?></h2>
				<div class="ccf-dash-table-wrap">
					<table class="ccf-dash-table">
						<thead>
							<tr>
								<th scope="col"><?php esc_html_e( 'Form', 'custom-contact-forms' ); ?></th>
								<th scope="col" class="num"><?php esc_html_e( 'Entries', 'custom-contact-forms' ); ?></th>
								<th scope="col" class="num"><?php esc_html_e( 'Views', 'custom-contact-forms' ); ?></th>
								<th scope="col" class="num"><?php esc_html_e( 'Conversion', 'custom-contact-forms' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Last entry', 'custom-contact-forms' ); ?></th>
							</tr>
						</thead>
						<tbody></tbody>
					</table>
				</div>
			</div>

			<?php do_action( 'ccf_dashboard_after' ); ?>
		</div>
		<?php
	}

	/**
	 * Return the singleton instance.
	 *
	 * @return CCF_Dashboard
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
