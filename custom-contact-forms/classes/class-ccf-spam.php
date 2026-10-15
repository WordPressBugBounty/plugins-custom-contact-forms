<?php
/**
 * Akismet checks and the spam folder.
 *
 * Entries Akismet flags are kept with the post status "ccf_spam" instead of
 * being thrown away, because Akismet is sometimes wrong. They send no email
 * and run no integrations. From Forms > Spam they can be moved to the
 * entries list, which then runs integrations as for a new entry, or deleted.
 * Spam older than 30 days is deleted automatically.
 *
 * @package Custom_Contact_Forms
 * @since   7.18.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CCF_Spam {

	const STATUS = 'ccf_spam';

	const KEEP_DAYS = 30;

	const OPTION = 'ccf_akismet_settings';

	public function setup() {
		add_action( 'init', array( $this, 'register_status' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ), 20 );
		add_filter( 'ccf_submission_spam_check', array( $this, 'akismet_check' ), 10, 4 );
		add_action( 'admin_menu', array( $this, 'menu' ), 20 );
		add_action( 'admin_post_ccf_spam_action', array( $this, 'handle_action' ) );
		add_action( 'ccf_spam_cleanup', array( $this, 'cleanup' ) );
		add_action( 'init', array( $this, 'schedule_cleanup' ) );
		add_action( 'before_delete_post', array( $this, 'delete_form_spam' ) );
	}

	public function register_status() {
		register_post_status(
			self::STATUS,
			array(
				'label'                     => _x( 'Spam', 'entry status', 'custom-contact-forms' ),
				'public'                    => false,
				'internal'                  => true,
				'protected'                 => true,
				'exclude_from_search'       => true,
				'show_in_admin_all_list'    => false,
				'show_in_admin_status_list' => false,
			)
		);
	}

	/* --------------------------------------------------------------------- */
	/* Akismet                                                                */
	/* --------------------------------------------------------------------- */

	/**
	 * Whether the Akismet plugin is active with a key.
	 *
	 * @return bool
	 */
	public function akismet_available() {
		return class_exists( 'Akismet' ) && is_callable( array( 'Akismet', 'get_api_key' ) ) && is_callable( array( 'Akismet', 'http_post' ) ) && Akismet::get_api_key();
	}

	/**
	 * Whether the site owner turned on Akismet checks for forms.
	 *
	 * @return bool
	 */
	public function akismet_enabled() {
		$settings = get_option( self::OPTION, array() );

		return is_array( $settings ) && ! empty( $settings['enabled'] ) && $this->akismet_available();
	}

	/**
	 * Ask Akismet about a submission.
	 *
	 * @param false|string $spam             Earlier result.
	 * @param array        $submission       Sanitized data.
	 * @param array        $field_slug_to_id Field map.
	 * @param int          $form_id          Form ID.
	 * @return false|string
	 */
	public function akismet_check( $spam, $submission, $field_slug_to_id, $form_id ) {
		if ( $spam || ! $this->akismet_enabled() ) {
			return $spam;
		}

		$author  = '';
		$email   = '';
		$url     = '';
		$content = array();

		foreach ( $submission as $slug => $value ) {
			$type = isset( $field_slug_to_id[ $slug ]['type'] ) ? $field_slug_to_id[ $slug ]['type'] : '';

			if ( 'email' === $type && '' === $email ) {
				$email = is_array( $value ) ? ( isset( $value['email'] ) ? $value['email'] : '' ) : $value;
			} elseif ( 'name' === $type && '' === $author ) {
				$author = is_array( $value ) ? trim( implode( ' ', array_filter( array_map( 'strval', $value ) ) ) ) : $value;
			} elseif ( 'website' === $type && '' === $url ) {
				$url = is_string( $value ) ? $value : '';
			} elseif ( in_array( $type, array( 'single-line-text', 'paragraph-text' ), true ) ) {
				$content[] = is_array( $value ) ? implode( ' ', array_map( 'strval', $value ) ) : (string) $value;
			}
		}

		$query = array(
			'blog'                 => get_option( 'home' ),
			'blog_lang'            => get_locale(),
			'blog_charset'         => get_option( 'blog_charset' ),
			'user_ip'              => isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '',
			'user_agent'           => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '',
			'referrer'             => isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '',
			'permalink'            => ! empty( $_POST['form_page'] ) ? esc_url_raw( wp_unslash( $_POST['form_page'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'comment_type'         => 'contact-form',
			'comment_author'       => $author,
			'comment_author_email' => $email,
			'comment_author_url'   => $url,
			'comment_content'      => implode( "\n\n", $content ),
		);

		$response = Akismet::http_post( Akismet::build_query( $query ), 'comment-check' );

		// If Akismet cannot be reached, let the entry through.
		if ( empty( $response[1] ) ) {
			return false;
		}

		return 'true' === trim( $response[1] ) ? 'akismet' : false;
	}

	/**
	 * Tell Akismet it was wrong, so it learns from the site owner.
	 *
	 * @param int    $submission_id Entry ID.
	 * @param string $verdict       "ham" or "spam".
	 */
	private function report_to_akismet( $submission_id, $verdict ) {
		if ( ! $this->akismet_available() || 'akismet' !== get_post_meta( $submission_id, 'ccf_spam_reason', true ) ) {
			return;
		}

		$data   = get_post_meta( $submission_id, 'ccf_submission_data', true );
		$map    = get_post_meta( $submission_id, 'ccf_submission_data_map', true );
		$parts  = array();
		$email  = '';

		foreach ( is_array( $data ) ? $data : array() as $slug => $value ) {
			$type = isset( $map[ $slug ]['type'] ) ? $map[ $slug ]['type'] : '';

			if ( 'email' === $type && '' === $email ) {
				$email = is_array( $value ) ? ( isset( $value['email'] ) ? $value['email'] : '' ) : $value;
			} elseif ( in_array( $type, array( 'single-line-text', 'paragraph-text' ), true ) ) {
				$parts[] = is_array( $value ) ? implode( ' ', array_map( 'strval', $value ) ) : (string) $value;
			}
		}

		$query = array(
			'blog'                 => get_option( 'home' ),
			'user_ip'              => (string) get_post_meta( $submission_id, 'ccf_submission_ip', true ),
			'comment_type'         => 'contact-form',
			'comment_author_email' => $email,
			'comment_content'      => implode( "\n\n", $parts ),
		);

		Akismet::http_post( Akismet::build_query( $query ), 'ham' === $verdict ? 'submit-ham' : 'submit-spam' );
	}

	public function register_settings() {
		// Saved from its card on Forms > Integrations.
		register_setting(
			'ccf-int-akismet',
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
			)
		);
	}

	/**
	 * Settings shown inside the Akismet card on Forms > Integrations.
	 */
	public function render_settings() {
		$this->field_akismet();
	}

	public function sanitize_settings( $input ) {
		return array( 'enabled' => is_array( $input ) && ! empty( $input['enabled'] ) );
	}

	public function field_akismet() {
		$settings = get_option( self::OPTION, array() );
		$enabled  = is_array( $settings ) && ! empty( $settings['enabled'] );
		?>
		<label>
			<input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[enabled]" value="1" <?php checked( $enabled ); ?> <?php disabled( ! $this->akismet_available() && ! $enabled ); ?>>
			<?php esc_html_e( 'Check entries with Akismet and keep flagged ones in the spam folder', 'custom-contact-forms' ); ?>
		</label>
		<p class="description">
			<?php
			if ( $this->akismet_available() ) {
				esc_html_e( 'The name, email, website and text fields of each entry are sent to Akismet to check. Flagged entries go to Forms > Spam, where you can move them back.', 'custom-contact-forms' );
			} else {
				esc_html_e( 'Install and set up the Akismet Anti-spam plugin to use this.', 'custom-contact-forms' );
			}
			?>
		</p>
		<?php
	}

	/* --------------------------------------------------------------------- */
	/* Spam folder                                                            */
	/* --------------------------------------------------------------------- */

	/**
	 * Number of entries in the spam folder.
	 *
	 * @return int
	 */
	public function count() {
		$counts = wp_count_posts( 'ccf_submission' );

		return isset( $counts->{self::STATUS} ) ? (int) $counts->{self::STATUS} : 0;
	}

	public function menu() {
		$count = $this->count();
		$label = esc_html__( 'Spam', 'custom-contact-forms' );

		if ( $count ) {
			$label .= ' <span class="awaiting-mod count-' . $count . '"><span class="pending-count">' . number_format_i18n( $count ) . '</span></span>';
		}

		add_submenu_page( 'edit.php?post_type=ccf_form', esc_html__( 'Spam Entries', 'custom-contact-forms' ), $label, 'edit_posts', 'ccf-spam', array( $this, 'render_page' ) );
	}

	/**
	 * Short preview of an entry's values.
	 *
	 * @param int $submission_id Entry ID.
	 * @return string
	 */
	private function preview( $submission_id ) {
		$data  = get_post_meta( $submission_id, 'ccf_submission_data', true );
		$parts = array();

		foreach ( is_array( $data ) ? $data : array() as $value ) {
			if ( is_array( $value ) ) {
				$value = isset( $value['email'] ) ? $value['email'] : implode( ' ', array_filter( array_map( 'strval', array_filter( $value, 'is_scalar' ) ) ) );
			}

			$value = trim( wp_strip_all_tags( (string) $value ) );

			if ( '' !== $value && 0 !== strpos( $value, 'data:' ) ) {
				$parts[] = $value;
			}
		}

		return wp_html_excerpt( implode( ' · ', $parts ), 220, '…' );
	}

	public function render_page() {
		$paged = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$query = new WP_Query(
			array(
				'post_type'      => 'ccf_submission',
				'post_status'    => self::STATUS,
				'posts_per_page' => 20,
				'paged'          => $paged,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);

		$notice = isset( $_GET['ccf_spam_done'] ) ? sanitize_key( wp_unslash( $_GET['ccf_spam_done'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Spam Entries', 'custom-contact-forms' ); ?></h1>
			<?php if ( $query->found_posts ) : ?>
				<a href="<?php echo esc_url( $this->action_url( 'empty' ) ); ?>" class="page-title-action" onclick="return window.confirm( <?php echo esc_attr( wp_json_encode( __( 'Delete every entry in the spam folder?', 'custom-contact-forms' ) ) ); ?> );"><?php esc_html_e( 'Empty spam', 'custom-contact-forms' ); ?></a>
			<?php endif; ?>
			<hr class="wp-header-end">

			<?php if ( 'restored' === $notice ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Moved to the form\'s entries. Notification emails are not sent for entries moved out of spam.', 'custom-contact-forms' ); ?></p></div>
			<?php elseif ( 'deleted' === $notice ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Deleted.', 'custom-contact-forms' ); ?></p></div>
			<?php elseif ( 'emptied' === $notice ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Spam folder emptied.', 'custom-contact-forms' ); ?></p></div>
			<?php endif; ?>

			<p>
				<?php
				/* translators: %d: number of days */
				echo esc_html( sprintf( __( 'Entries flagged as spam are kept here for %d days, then deleted. They do not send emails. If a real message landed here, choose "Not spam" to move it to the form\'s entries.', 'custom-contact-forms' ), self::KEEP_DAYS ) );
				?>
			</p>

			<table class="widefat striped">
				<thead>
					<tr>
						<th style="width:160px;"><?php esc_html_e( 'Received', 'custom-contact-forms' ); ?></th>
						<th style="width:180px;"><?php esc_html_e( 'Form', 'custom-contact-forms' ); ?></th>
						<th><?php esc_html_e( 'Entry', 'custom-contact-forms' ); ?></th>
						<th style="width:110px;"><?php esc_html_e( 'Flagged by', 'custom-contact-forms' ); ?></th>
						<th style="width:170px;"><?php esc_html_e( 'Actions', 'custom-contact-forms' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( ! $query->have_posts() ) : ?>
						<tr><td colspan="5"><?php esc_html_e( 'No spam. Nice.', 'custom-contact-forms' ); ?></td></tr>
					<?php endif; ?>
					<?php foreach ( $query->posts as $entry ) : ?>
						<tr>
							<td><?php echo esc_html( get_date_from_gmt( $entry->post_date_gmt, get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) ); ?></td>
							<td><?php echo esc_html( $entry->post_parent ? get_the_title( $entry->post_parent ) : '' ); ?></td>
							<td><?php echo esc_html( $this->preview( $entry->ID ) ); ?></td>
							<td><?php echo esc_html( 'akismet' === get_post_meta( $entry->ID, 'ccf_spam_reason', true ) ? 'Akismet' : get_post_meta( $entry->ID, 'ccf_spam_reason', true ) ); ?></td>
							<td>
								<a class="button button-small" href="<?php echo esc_url( $this->action_url( 'restore', $entry->ID ) ); ?>"><?php esc_html_e( 'Not spam', 'custom-contact-forms' ); ?></a>
								<a class="button button-small button-link-delete" href="<?php echo esc_url( $this->action_url( 'delete', $entry->ID ) ); ?>"><?php esc_html_e( 'Delete', 'custom-contact-forms' ); ?></a>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<?php
			if ( $query->max_num_pages > 1 ) {
				echo '<div class="tablenav"><div class="tablenav-pages">';
				echo wp_kses_post(
					paginate_links(
						array(
							'base'    => add_query_arg( 'paged', '%#%' ),
							'format'  => '',
							'current' => $paged,
							'total'   => $query->max_num_pages,
						)
					)
				);
				echo '</div></div>';
			}
			?>
		</div>
		<?php
	}

	/**
	 * Signed URL for a spam folder action.
	 *
	 * @param string $do  restore, delete or empty.
	 * @param int    $id  Entry ID.
	 * @return string
	 */
	private function action_url( $do, $id = 0 ) {
		return wp_nonce_url(
			add_query_arg(
				array(
					'action' => 'ccf_spam_action',
					'do'     => $do,
					'entry'  => (int) $id,
				),
				admin_url( 'admin-post.php' )
			),
			'ccf_spam_' . $do . '_' . (int) $id
		);
	}

	public function handle_action() {
		$do = isset( $_GET['do'] ) ? sanitize_key( wp_unslash( $_GET['do'] ) ) : '';
		$id = isset( $_GET['entry'] ) ? absint( $_GET['entry'] ) : 0;

		check_admin_referer( 'ccf_spam_' . $do . '_' . $id );

		$back = admin_url( 'edit.php?post_type=ccf_form&page=ccf-spam' );

		if ( 'empty' === $do ) {
			if ( ! current_user_can( 'delete_others_posts' ) ) {
				wp_die( esc_html__( 'You do not have permission to do that.', 'custom-contact-forms' ) );
			}

			$this->delete_where( array() );
			wp_safe_redirect( add_query_arg( 'ccf_spam_done', 'emptied', $back ) );
			exit;
		}

		$entry = get_post( $id );

		if ( ! $entry || 'ccf_submission' !== $entry->post_type || self::STATUS !== $entry->post_status ) {
			wp_safe_redirect( $back );
			exit;
		}

		if ( 'restore' === $do ) {
			if ( ! current_user_can( 'edit_post', $id ) ) {
				wp_die( esc_html__( 'You do not have permission to do that.', 'custom-contact-forms' ) );
			}

			wp_update_post(
				array(
					'ID'          => $id,
					'post_status' => 'publish',
				)
			);

			$this->report_to_akismet( $id, 'ham' );
			update_post_meta( $id, 'ccf_spam_restored', time() );

			/** This action is documented in classes/class-ccf-form-handler.php */
			do_action( 'ccf_successful_submission', $id, (int) $entry->post_parent );

			wp_safe_redirect( add_query_arg( 'ccf_spam_done', 'restored', $back ) );
			exit;
		}

		if ( 'delete' === $do ) {
			if ( ! current_user_can( 'delete_post', $id ) ) {
				wp_die( esc_html__( 'You do not have permission to do that.', 'custom-contact-forms' ) );
			}

			$this->delete_entry( $id );
			wp_safe_redirect( add_query_arg( 'ccf_spam_done', 'deleted', $back ) );
			exit;
		}

		wp_safe_redirect( $back );
		exit;
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
	 * Delete spam entries matching extra query arguments.
	 *
	 * @param array $args Extra WP_Query arguments.
	 */
	private function delete_where( $args ) {
		$ids = get_posts(
			array_merge(
				array(
					'post_type'      => 'ccf_submission',
					'post_status'    => self::STATUS,
					'posts_per_page' => 500,
					'fields'         => 'ids',
				),
				$args
			)
		);

		foreach ( $ids as $id ) {
			$this->delete_entry( $id );
		}
	}

	public function schedule_cleanup() {
		if ( ! wp_next_scheduled( 'ccf_spam_cleanup' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'ccf_spam_cleanup' );
		}
	}

	/**
	 * Delete spam older than the keep period.
	 */
	public function cleanup() {
		$this->delete_where(
			array(
				'date_query' => array(
					array(
						'column' => 'post_date_gmt',
						'before' => gmdate( 'Y-m-d H:i:s', time() - self::KEEP_DAYS * DAY_IN_SECONDS ),
					),
				),
			)
		);
	}

	/**
	 * When a form is deleted, delete its spam too. The form's normal entries
	 * are already removed with it.
	 *
	 * @param int $post_id Post being deleted.
	 */
	public function delete_form_spam( $post_id ) {
		if ( 'ccf_form' !== get_post_type( $post_id ) ) {
			return;
		}

		$this->delete_where( array( 'post_parent' => (int) $post_id ) );
	}

	/**
	 * @return CCF_Spam
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
