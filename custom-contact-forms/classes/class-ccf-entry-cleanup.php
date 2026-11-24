<?php
/**
 * Automatic entry cleanup.
 *
 * Each form can delete its entries, and their uploaded files, once they are
 * older than a set number of days. This helps with privacy rules such as
 * GDPR, which ask sites not to keep personal data longer than needed.
 *
 * @package Custom_Contact_Forms
 * @since   7.21.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CCF_Entry_Cleanup {

	const META  = 'ccf_form_cleanup_days';
	const HOOK  = 'ccf_entry_cleanup';
	const BATCH = 200;

	public function setup() {
		add_action( 'init', array( $this, 'schedule' ) );
		add_action( self::HOOK, array( $this, 'run' ) );
		add_action( 'ccf_save_form', array( $this, 'save_form' ), 10, 2 );
		add_filter( 'ccf_form_response', array( $this, 'form_response' ), 10, 2 );
	}

	/**
	 * Choices offered in the builder, as days => label.
	 *
	 * @return array
	 */
	public static function choices() {
		return array(
			0   => __( 'Never (keep all entries)', 'custom-contact-forms' ),
			7   => __( '7 days', 'custom-contact-forms' ),
			30  => __( '30 days', 'custom-contact-forms' ),
			60  => __( '60 days', 'custom-contact-forms' ),
			90  => __( '90 days', 'custom-contact-forms' ),
			180 => __( '6 months', 'custom-contact-forms' ),
			365 => __( '1 year', 'custom-contact-forms' ),
			730 => __( '2 years', 'custom-contact-forms' ),
		);
	}

	/**
	 * A form's cleanup setting in days (0 = never).
	 *
	 * @param int $form_id Form ID.
	 * @return int
	 */
	public function days( $form_id ) {
		return max( 0, (int) get_post_meta( $form_id, self::META, true ) );
	}

	public function schedule() {
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::HOOK );
		}
	}

	/**
	 * Delete old entries for every form that has cleanup turned on.
	 *
	 * Works in batches. If a form still has old entries after a batch, the
	 * job runs again a few minutes later.
	 *
	 * @param string $batch Set to "more" for a follow-up batch.
	 * @return int Number of entries deleted.
	 */
	public function run( $batch = '' ) {
		$forms = get_posts(
			array(
				'post_type'      => 'ccf_form',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_query'     => array(
					array(
						'key'     => self::META,
						'value'   => 0,
						'compare' => '>',
						'type'    => 'NUMERIC',
					),
				),
			)
		);

		$deleted = 0;
		$more    = false;

		foreach ( $forms as $form_id ) {
			$days = $this->days( $form_id );

			if ( ! $days ) {
				continue;
			}

			$ids = $this->old_entries( $form_id, $days, self::BATCH + 1 );

			if ( count( $ids ) > self::BATCH ) {
				$more = true;
				$ids  = array_slice( $ids, 0, self::BATCH );
			}

			foreach ( $ids as $id ) {
				$this->delete_entry( $id );
				$deleted++;
			}
		}

		if ( $more && ! wp_next_scheduled( self::HOOK, array( 'more' ) ) ) {
			wp_schedule_single_event( time() + 5 * MINUTE_IN_SECONDS, self::HOOK, array( 'more' ) );
		}

		if ( $deleted ) {
			/**
			 * Fires after old entries are deleted.
			 *
			 * @since 7.21.0
			 * @param int $deleted Number of entries deleted.
			 */
			do_action( 'ccf_entry_cleanup_done', $deleted );
		}

		return $deleted;
	}

	/**
	 * IDs of a form's entries older than a number of days. Spam entries are
	 * left alone: the spam folder cleans itself up.
	 *
	 * @param int $form_id Form ID.
	 * @param int $days    Age in days.
	 * @param int $limit   Most to return.
	 * @return int[]
	 */
	public function old_entries( $form_id, $days, $limit ) {
		return get_posts(
			array(
				'post_type'      => 'ccf_submission',
				'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'trash' ),
				'post_parent'    => (int) $form_id,
				'posts_per_page' => (int) $limit,
				'fields'         => 'ids',
				'orderby'        => 'date',
				'order'          => 'ASC',
				'date_query'     => array(
					array(
						'column' => 'post_date_gmt',
						'before' => gmdate( 'Y-m-d H:i:s', time() - (int) $days * DAY_IN_SECONDS ),
					),
				),
			)
		);
	}

	/**
	 * Delete an entry and its uploaded files.
	 *
	 * @param int $id Entry ID.
	 */
	private function delete_entry( $id ) {
		$attachments = get_children(
			array(
				'post_parent' => $id,
				'post_type'   => 'attachment',
				'fields'      => 'ids',
			)
		);

		foreach ( $attachments as $attachment_id ) {
			wp_delete_attachment( $attachment_id, true );
		}

		wp_delete_post( $id, true );
	}

	/**
	 * Save the setting sent by the builder.
	 *
	 * @param int   $form_id Form ID.
	 * @param array $data    Request data.
	 */
	public function save_form( $form_id, $data ) {
		if ( ! array_key_exists( 'entryCleanupDays', $data ) ) {
			return;
		}

		$days = (int) $data['entryCleanupDays'];

		if ( $days > 0 && array_key_exists( $days, self::choices() ) ) {
			update_post_meta( $form_id, self::META, $days );
		} else {
			delete_post_meta( $form_id, self::META );
		}
	}

	/**
	 * Add the setting to the form data the builder loads.
	 *
	 * @param array   $data Form data.
	 * @param WP_Post $item Form post.
	 * @return array
	 */
	public function form_response( $data, $item ) {
		$data['entryCleanupDays'] = $this->days( $item->ID );

		return $data;
	}

	/**
	 * @return CCF_Entry_Cleanup
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
