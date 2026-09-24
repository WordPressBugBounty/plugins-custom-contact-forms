<?php
/**
 * Form statistics for the dashboard.
 *
 * Entries come from saved submissions, so they have full history. Views and
 * blocked spam are counted from 7.17.0 onward:
 *
 *  - Views: one count per form per page view, sent by the browser after the
 *    form loads, so page caching does not hide them. Stored as daily totals
 *    on the form. Nothing about the visitor is stored. Views by people who
 *    can edit forms, and by obvious bots, are not counted.
 *  - Spam: daily totals of submissions stopped by the anti-spam checks or
 *    Cloudflare Turnstile.
 *
 * All counts stay in this site's database.
 *
 * @package Custom_Contact_Forms
 * @since   7.17.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CCF_Stats {

	/**
	 * Days of daily totals kept for views and spam.
	 */
	const KEEP_DAYS = 400;

	/**
	 * Post meta key holding a form's daily view totals.
	 */
	const VIEWS_META = 'ccf_views_daily';

	/**
	 * Option holding daily spam totals.
	 */
	const SPAM_OPTION = 'ccf_spam_daily';

	/**
	 * Register hooks.
	 */
	public function setup() {
		add_action( 'wp_ajax_ccf_form_view', array( $this, 'ajax_record_view' ) );
		add_action( 'wp_ajax_nopriv_ccf_form_view', array( $this, 'ajax_record_view' ) );
	}

	/**
	 * Today's date in the site's time zone.
	 *
	 * @return string Y-m-d
	 */
	public static function today() {
		return wp_date( 'Y-m-d' );
	}

	/**
	 * Add one to a date-keyed total and drop days older than KEEP_DAYS.
	 *
	 * @param mixed $totals Stored totals.
	 * @return array
	 */
	private static function bump( $totals ) {
		$totals = is_array( $totals ) ? $totals : array();
		$today  = self::today();

		$totals[ $today ] = isset( $totals[ $today ] ) ? (int) $totals[ $today ] + 1 : 1;

		$cutoff = wp_date( 'Y-m-d', time() - self::KEEP_DAYS * DAY_IN_SECONDS );
		foreach ( array_keys( $totals ) as $day ) {
			if ( $day < $cutoff ) {
				unset( $totals[ $day ] );
			}
		}

		return $totals;
	}

	/**
	 * Record a blocked spam submission.
	 */
	public static function record_spam() {
		update_option( self::SPAM_OPTION, self::bump( get_option( self::SPAM_OPTION, array() ) ), false );
	}

	/**
	 * Record one view of a form.
	 *
	 * @param int $form_id Form ID.
	 * @return bool
	 */
	public static function record_view( $form_id ) {
		$form_id = (int) $form_id;

		if ( $form_id <= 0 || 'ccf_form' !== get_post_type( $form_id ) || 'publish' !== get_post_status( $form_id ) ) {
			return false;
		}

		update_post_meta( $form_id, self::VIEWS_META, self::bump( get_post_meta( $form_id, self::VIEWS_META, true ) ) );

		return true;
	}

	/**
	 * Browser request that records a view. Deliberately has no nonce: forms
	 * are often served from page caches, where a nonce would be stale. It
	 * only ever adds one to a counter for a published form.
	 */
	public function ajax_record_view() {
		$form_id = isset( $_POST['form_id'] ) ? absint( $_POST['form_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		if ( is_user_logged_in() && current_user_can( 'edit_posts' ) ) {
			wp_send_json_success( array( 'counted' => false ) );
		}

		$agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? strtolower( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) ) : '';

		if ( '' === $agent || preg_match( '/bot|crawl|spider|slurp|preview|headless|lighthouse|pingdom|monitor/', $agent ) ) {
			wp_send_json_success( array( 'counted' => false ) );
		}

		wp_send_json_success( array( 'counted' => self::record_view( $form_id ) ) );
	}

	/**
	 * Every day in a range, inclusive.
	 *
	 * @param string $start Y-m-d.
	 * @param string $end   Y-m-d.
	 * @return array
	 */
	public static function days( $start, $end ) {
		$days    = array();
		$current = new DateTime( $start, wp_timezone() );
		$last    = new DateTime( $end, wp_timezone() );

		while ( $current <= $last && count( $days ) < 400 ) {
			$days[] = $current->format( 'Y-m-d' );
			$current->modify( '+1 day' );
		}

		return $days;
	}

	/**
	 * All published forms.
	 *
	 * @return WP_Post[]
	 */
	public static function forms() {
		return get_posts(
			array(
				'post_type'      => 'ccf_form',
				'post_status'    => 'publish',
				'posts_per_page' => 500,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'no_found_rows'  => true,
			)
		);
	}

	/**
	 * Everything the dashboard shows for a date range.
	 *
	 * @param string $start Y-m-d, inclusive.
	 * @param string $end   Y-m-d, inclusive.
	 * @return array
	 */
	public static function report( $start, $end ) {
		global $wpdb;

		$days  = self::days( $start, $end );
		$zero  = array_fill_keys( $days, 0 );
		$forms = self::forms();

		// Entries per day and per form, in the site's time zone.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DATE(post_date) AS day, post_parent AS form_id, COUNT(*) AS num
				FROM {$wpdb->posts}
				WHERE post_type = 'ccf_submission'
				AND post_status = 'publish'
				AND post_date >= %s
				AND post_date < %s
				GROUP BY day, form_id",
				$start . ' 00:00:00',
				wp_date( 'Y-m-d', strtotime( $end . ' 12:00:00' ) + DAY_IN_SECONDS ) . ' 00:00:00'
			)
		);

		$entries_by_day  = $zero;
		$entries_by_form = array();

		foreach ( (array) $rows as $row ) {
			if ( isset( $entries_by_day[ $row->day ] ) ) {
				$entries_by_day[ $row->day ] += (int) $row->num;
			}

			$form_id                     = (int) $row->form_id;
			$entries_by_form[ $form_id ] = ( isset( $entries_by_form[ $form_id ] ) ? $entries_by_form[ $form_id ] : 0 ) + (int) $row->num;
		}

		// Latest entry per form, any date.
		$latest = array();
		$rows   = $wpdb->get_results(
			"SELECT post_parent AS form_id, MAX(post_date) AS latest
			FROM {$wpdb->posts}
			WHERE post_type = 'ccf_submission'
			AND post_status = 'publish'
			GROUP BY post_parent"
		);

		foreach ( (array) $rows as $row ) {
			$latest[ (int) $row->form_id ] = $row->latest;
		}

		// Views per day and per form.
		$views_by_day = $zero;
		$table        = array();

		foreach ( $forms as $form ) {
			$daily = get_post_meta( $form->ID, self::VIEWS_META, true );
			$views = 0;

			if ( is_array( $daily ) ) {
				foreach ( $daily as $day => $count ) {
					if ( isset( $views_by_day[ $day ] ) ) {
						$views_by_day[ $day ] += (int) $count;
						$views                += (int) $count;
					}
				}
			}

			$entries = isset( $entries_by_form[ $form->ID ] ) ? $entries_by_form[ $form->ID ] : 0;

			$table[] = array(
				'id'          => (int) $form->ID,
				'title'       => '' !== $form->post_title ? $form->post_title : __( '(no title)', 'custom-contact-forms' ),
				'edit_url'    => get_edit_post_link( $form->ID, 'raw' ),
				'entries'     => $entries,
				'views'       => $views,
				'conversion'  => $views > 0 ? round( min( 100, $entries / $views * 100 ), 1 ) : null,
				'last_entry'  => isset( $latest[ $form->ID ] ) ? mysql2date( get_option( 'date_format' ), $latest[ $form->ID ] ) : '',
				'last_sort'   => isset( $latest[ $form->ID ] ) ? $latest[ $form->ID ] : '',
			);
		}

		usort(
			$table,
			function ( $a, $b ) {
				if ( $a['entries'] === $b['entries'] ) {
					return strcmp( $b['last_sort'], $a['last_sort'] );
				}
				return $b['entries'] - $a['entries'];
			}
		);

		// Spam per day.
		$spam_daily = get_option( self::SPAM_OPTION, array() );
		$spam       = 0;

		if ( is_array( $spam_daily ) ) {
			foreach ( $spam_daily as $day => $count ) {
				if ( isset( $zero[ $day ] ) ) {
					$spam += (int) $count;
				}
			}
		}

		$total_entries = array_sum( $entries_by_day );
		$total_views   = array_sum( $views_by_day );

		return array(
			'start'   => $start,
			'end'     => $end,
			'days'    => $days,
			'entries' => array_values( $entries_by_day ),
			'views'   => array_values( $views_by_day ),
			'totals'  => array(
				'forms'      => count( $forms ),
				'entries'    => $total_entries,
				'views'      => $total_views,
				'conversion' => $total_views > 0 ? round( min( 100, $total_entries / $total_views * 100 ), 1 ) : null,
				'spam'       => $spam,
			),
			'forms'   => $table,
		);
	}

	/**
	 * Return the singleton instance.
	 *
	 * @return CCF_Stats
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
