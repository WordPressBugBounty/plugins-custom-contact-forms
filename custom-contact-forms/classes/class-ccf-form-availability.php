<?php
/**
 * Form scheduling and entry limits.
 *
 * A form can open and close on set dates and stop taking entries after a
 * maximum number. The checks run when the form is shown and again when it
 * is submitted, so a page served from a cache cannot get past them.
 *
 * @package Custom_Contact_Forms
 * @since   7.21.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CCF_Form_Availability {

	const META = 'ccf_form_availability';

	/**
	 * Entry counts already looked up in this request.
	 *
	 * @var array
	 */
	private $counts = array();

	public function setup() {
		add_action( 'ccf_save_form', array( $this, 'save_form' ), 10, 2 );
		add_filter( 'ccf_form_response', array( $this, 'form_response' ), 10, 2 );
		add_action( 'ccf_successful_submission', array( $this, 'forget_count' ), 1, 2 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ), 20 );
	}

	/**
	 * Default messages.
	 *
	 * @return array
	 */
	public static function default_messages() {
		return array(
			'beforeMessage' => __( 'This form is not open yet. Please check back soon.', 'custom-contact-forms' ),
			'afterMessage'  => __( 'This form is now closed. Thank you for your interest.', 'custom-contact-forms' ),
			'limitMessage'  => __( 'Sorry, we have reached the maximum number of entries for this form.', 'custom-contact-forms' ),
		);
	}

	/**
	 * A form's saved settings with defaults filled in.
	 *
	 * @param int $form_id Form ID.
	 * @return array
	 */
	public function settings( $form_id ) {
		$saved = get_post_meta( $form_id, self::META, true );

		if ( ! is_array( $saved ) ) {
			$saved = array();
		}

		return wp_parse_args(
			$saved,
			array(
				'openAt'        => '',
				'closeAt'       => '',
				'beforeMessage' => '',
				'afterMessage'  => '',
				'limit'         => 0,
				'limitMessage'  => '',
				'showRemaining' => false,
			)
		);
	}

	/**
	 * Turn a builder date ("2026-12-01T09:00") in the site's time zone into
	 * a Unix timestamp.
	 *
	 * @param string $value Local date and time.
	 * @return int 0 when empty or invalid.
	 */
	public function to_timestamp( $value ) {
		$value = trim( (string) $value );

		if ( '' === $value ) {
			return 0;
		}

		$date = date_create_immutable_from_format( 'Y-m-d\TH:i', $value, wp_timezone() );

		return $date ? $date->getTimestamp() : 0;
	}

	/**
	 * Clean a builder date, or return an empty string.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	private function clean_date( $value ) {
		$value = substr( sanitize_text_field( (string) $value ), 0, 16 );

		return $this->to_timestamp( $value ) ? $value : '';
	}

	/**
	 * Number of entries a form has (spam and trashed entries do not count).
	 *
	 * @param int $form_id Form ID.
	 * @return int
	 */
	public function entry_count( $form_id ) {
		$form_id = (int) $form_id;

		if ( ! isset( $this->counts[ $form_id ] ) ) {
			global $wpdb;

			$this->counts[ $form_id ] = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_type = 'ccf_submission' AND post_status = 'publish' AND post_parent = %d",
					$form_id
				)
			);
		}

		return $this->counts[ $form_id ];
	}

	/**
	 * Forget a cached count once a new entry is saved.
	 *
	 * @param int $submission_id Entry ID.
	 * @param int $form_id       Form ID.
	 */
	public function forget_count( $submission_id, $form_id ) {
		unset( $this->counts[ (int) $form_id ] );
	}

	/**
	 * Where a form stands right now.
	 *
	 * @param int $form_id Form ID.
	 * @return array {
	 *     @type string   $state     open, before, after or full.
	 *     @type string   $message   What to show instead of the form ('' when open).
	 *     @type int|null $remaining Entries left, or null with no limit.
	 * }
	 */
	public function status( $form_id ) {
		$settings = $this->settings( $form_id );
		$messages = self::default_messages();
		$now      = time();
		$open     = $this->to_timestamp( $settings['openAt'] );
		$close    = $this->to_timestamp( $settings['closeAt'] );
		$limit    = max( 0, (int) $settings['limit'] );

		$status = array(
			'state'     => 'open',
			'message'   => '',
			'remaining' => null,
		);

		if ( $open && $now < $open ) {
			$status['state']   = 'before';
			$status['message'] = '' !== $settings['beforeMessage'] ? $settings['beforeMessage'] : $messages['beforeMessage'];
		} elseif ( $close && $now >= $close ) {
			$status['state']   = 'after';
			$status['message'] = '' !== $settings['afterMessage'] ? $settings['afterMessage'] : $messages['afterMessage'];
		} elseif ( $limit ) {
			$status['remaining'] = max( 0, $limit - $this->entry_count( $form_id ) );

			if ( 0 === $status['remaining'] ) {
				$status['state']   = 'full';
				$status['message'] = '' !== $settings['limitMessage'] ? $settings['limitMessage'] : $messages['limitMessage'];
			}
		}

		/**
		 * Filter whether a form is open.
		 *
		 * @since 7.21.0
		 * @param array $status  State, message and remaining entries.
		 * @param int   $form_id Form ID.
		 */
		return apply_filters( 'ccf_form_availability_status', $status, $form_id );
	}

	/**
	 * The message to show instead of the form, or '' when it is open.
	 *
	 * @param int $form_id Form ID.
	 * @return string
	 */
	public function closed_message( $form_id ) {
		$status = $this->status( $form_id );

		return 'open' === $status['state'] ? '' : (string) $status['message'];
	}

	/**
	 * "12 spots left" above the form, when turned on.
	 *
	 * @param int $form_id Form ID.
	 * @return string HTML.
	 */
	public function remaining_html( $form_id ) {
		$settings = $this->settings( $form_id );

		if ( empty( $settings['showRemaining'] ) ) {
			return '';
		}

		$status = $this->status( $form_id );

		if ( 'open' !== $status['state'] || null === $status['remaining'] ) {
			return '';
		}

		/* translators: %s: number of entries left */
		$text = sprintf( _n( '%s spot left', '%s spots left', $status['remaining'], 'custom-contact-forms' ), number_format_i18n( $status['remaining'] ) );

		return '<div class="ccf-form-remaining">' . esc_html( $text ) . '</div>';
	}

	/**
	 * Show the closed message when a submission is turned away. The form
	 * script only shows field errors, so this adds the rest.
	 */
	public function enqueue() {
		if ( ! wp_script_is( 'ccf-form', 'enqueued' ) ) {
			return;
		}

		$js = <<<'JS'
(function($){if(!$){return;}$(function(){$('.ccf-form-frame').on('load',function(){var t=$(this).contents().find('body').text(),d;if(!t){return;}try{d=JSON.parse(t);}catch(e){return;}if(!d||d.success||'form_closed'!==d.error||!d.message){return;}var f=$(this).closest('.ccf-form-wrapper').find('form.ccf-form');f.replaceWith($('<div class="ccf-form-closed"></div>').text(d.message));});});})(window.jQuery);
JS;

		wp_add_inline_script( 'ccf-form', $js, 'after' );
	}

	/**
	 * Save the settings sent by the builder.
	 *
	 * @param int   $form_id Form ID.
	 * @param array $data    Request data.
	 */
	public function save_form( $form_id, $data ) {
		if ( ! isset( $data['availability'] ) || ! is_array( $data['availability'] ) ) {
			return;
		}

		$in = $data['availability'];

		$clean = array(
			'openAt'        => isset( $in['openAt'] ) ? $this->clean_date( $in['openAt'] ) : '',
			'closeAt'       => isset( $in['closeAt'] ) ? $this->clean_date( $in['closeAt'] ) : '',
			'beforeMessage' => isset( $in['beforeMessage'] ) ? sanitize_textarea_field( $in['beforeMessage'] ) : '',
			'afterMessage'  => isset( $in['afterMessage'] ) ? sanitize_textarea_field( $in['afterMessage'] ) : '',
			'limit'         => isset( $in['limit'] ) ? min( 1000000, max( 0, (int) $in['limit'] ) ) : 0,
			'limitMessage'  => isset( $in['limitMessage'] ) ? sanitize_textarea_field( $in['limitMessage'] ) : '',
			'showRemaining' => ! empty( $in['showRemaining'] ),
		);

		$empty = '' === $clean['openAt'] && '' === $clean['closeAt'] && 0 === $clean['limit']
			&& '' === $clean['beforeMessage'] && '' === $clean['afterMessage'] && '' === $clean['limitMessage'];

		if ( $empty ) {
			delete_post_meta( $form_id, self::META );
		} else {
			update_post_meta( $form_id, self::META, $clean );
		}
	}

	/**
	 * Add the settings to the form data the builder loads.
	 *
	 * @param array   $data Form data.
	 * @param WP_Post $item Form post.
	 * @return array
	 */
	public function form_response( $data, $item ) {
		$settings          = $this->settings( $item->ID );
		$settings['count'] = $this->entry_count( $item->ID );

		$data['availability'] = $settings;

		return $data;
	}

	/**
	 * @return CCF_Form_Availability
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
