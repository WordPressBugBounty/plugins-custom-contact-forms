<?php
/**
 * Admin screen for importing forms from other form plugins.
 *
 * Source-agnostic: it renders whatever importers are registered and reports
 * results uniformly, so adding a source requires no changes here.
 *
 * @since 7.15.0
 * @package Custom Contact Forms
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CCF_Form_Importer_Admin {

	/**
	 * Results of the last import, for rendering after the redirect.
	 *
	 * @since 7.15.0
	 * @var array
	 */
	private $results = array();

	/**
	 * Factory method.
	 *
	 * @since 7.15.0
	 * @return CCF_Form_Importer_Admin
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
	 * Setup hooks.
	 *
	 * @since 7.15.0
	 */
	public function setup() {
		add_action( 'admin_menu', array( $this, 'register_menu_page' ) );
		add_action( 'admin_init', array( $this, 'handle_import' ) );
	}

	/**
	 * Registered importers.
	 *
	 * @since 7.15.0
	 * @return array Importer objects keyed by ID.
	 */
	public function get_importers() {
		$importers = array();

		$cf7 = new CCF_Importer_CF7();
		$importers[ $cf7->get_id() ] = $cf7;

		$wpforms = new CCF_Importer_WPForms();
		$importers[ $wpforms->get_id() ] = $wpforms;

		/**
		 * Filter the available form importers.
		 *
		 * @since 7.15.0
		 * @param array $importers CCF_Importer objects keyed by ID.
		 */
		return apply_filters( 'ccf_form_importers', $importers );
	}

	/**
	 * Register the Import submenu page.
	 *
	 * @since 7.15.0
	 */
	public function register_menu_page() {
		add_submenu_page(
			'edit.php?post_type=ccf_form',
			esc_html__( 'Import Forms', 'custom-contact-forms' ),
			esc_html__( 'Import', 'custom-contact-forms' ),
			'manage_options',
			'ccf-import',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Handle a submitted import request.
	 *
	 * @since 7.15.0
	 */
	public function handle_import() {
		if ( empty( $_POST['ccf_import_source'] ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( empty( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'ccf_import_forms' ) ) {
			return;
		}

		$source    = sanitize_key( wp_unslash( $_POST['ccf_import_source'] ) );
		$importers = $this->get_importers();

		if ( ! isset( $importers[ $source ] ) ) {
			return;
		}

		$raw_ids = isset( $_POST['ccf_import_forms'] ) ? (array) wp_unslash( $_POST['ccf_import_forms'] ) : array();
		$ids     = array_filter( array_map( 'sanitize_text_field', $raw_ids ) );

		if ( empty( $ids ) ) {
			return;
		}

		$importer = $importers[ $source ];
		$results  = array();

		foreach ( $ids as $id ) {
			$result = $importer->import( $id );

			if ( is_wp_error( $result ) ) {
				$results[] = array(
					'error' => $result->get_error_message(),
					'title' => '',
				);
				continue;
			}

			$results[] = $result;
		}

		set_transient( 'ccf_import_results_' . get_current_user_id(), $results, 60 );

		wp_safe_redirect(
			add_query_arg(
				array(
					'post_type' => 'ccf_form',
					'page'      => 'ccf-import',
					'imported'  => 1,
				),
				admin_url( 'edit.php' )
			)
		);
		exit;
	}

	/**
	 * Render the import screen.
	 *
	 * @since 7.15.0
	 */
	public function render_page() {
		$importers = $this->get_importers();
		$results   = get_transient( 'ccf_import_results_' . get_current_user_id() );

		if ( $results ) {
			delete_transient( 'ccf_import_results_' . get_current_user_id() );
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Import Forms', 'custom-contact-forms' ); ?></h1>

			<style>
				.ccf-import-source{background:#fff;border:1px solid #c3c4c7;border-radius:4px;padding:16px 20px;margin:20px 0;max-width:780px}
				.ccf-import-source h2{margin-top:0;font-size:15px}
				.ccf-import-list{margin:12px 0}
				.ccf-import-list label{display:block;padding:5px 0}
				.ccf-import-none{color:#646970;font-style:italic}
				.ccf-import-results{background:#fff;border:1px solid #c3c4c7;border-left:4px solid #00a32a;border-radius:2px;padding:12px 20px;margin:20px 0;max-width:780px}
				.ccf-import-results h2{margin-top:8px;font-size:15px}
				.ccf-import-results code{font-size:12px}
				.ccf-import-notes{color:#646970;font-size:13px;margin:4px 0 0 0}
				.ccf-import-error{border-left-color:#d63638}
			</style>

			<?php if ( is_array( $results ) && ! empty( $results ) ) : ?>
				<div class="ccf-import-results">
					<h2><?php esc_html_e( 'Import complete', 'custom-contact-forms' ); ?></h2>
					<?php foreach ( $results as $result ) : ?>
						<?php if ( ! empty( $result['error'] ) ) : ?>
							<p><strong><?php echo esc_html( $result['error'] ); ?></strong></p>
						<?php elseif ( ! empty( $result['existing'] ) ) : ?>
							<p>
								<?php
								printf(
									/* translators: %s: form title. */
									esc_html__( '%s was already imported — skipped.', 'custom-contact-forms' ),
									'<strong>' . esc_html( $result['title'] ) . '</strong>'
								);
								?>
								<a href="<?php echo esc_url( admin_url( 'post.php?post=' . absint( $result['form_id'] ) . '&action=edit' ) ); ?>"><?php esc_html_e( 'Edit', 'custom-contact-forms' ); ?></a>
							</p>
						<?php else : ?>
							<p>
								<strong><?php echo esc_html( $result['title'] ); ?></strong> —
								<a href="<?php echo esc_url( admin_url( 'post.php?post=' . absint( $result['form_id'] ) . '&action=edit' ) ); ?>"><?php esc_html_e( 'Edit form', 'custom-contact-forms' ); ?></a>
								<br>
								<?php esc_html_e( 'Replace the old shortcode on your pages with:', 'custom-contact-forms' ); ?>
								<code>[ccf_form id="<?php echo absint( $result['form_id'] ); ?>"]</code>
							</p>
							<?php if ( ! empty( $result['skipped'] ) ) : ?>
								<p class="ccf-import-notes">
									<?php esc_html_e( 'Needs your attention:', 'custom-contact-forms' ); ?>
									<?php echo esc_html( implode( ' · ', $result['skipped'] ) ); ?>
								</p>
							<?php endif; ?>
						<?php endif; ?>
					<?php endforeach; ?>
					<p class="ccf-import-notes"><?php esc_html_e( 'Your original forms were not modified. Check each imported form before replacing it on a live page.', 'custom-contact-forms' ); ?></p>
				</div>
			<?php endif; ?>

			<p><?php esc_html_e( 'Bring forms over from another form plugin. Your existing forms are only read, never changed, and re-importing a form you have already imported will skip it rather than create a duplicate.', 'custom-contact-forms' ); ?></p>

			<?php foreach ( $importers as $importer ) : ?>
				<div class="ccf-import-source">
					<h2><?php echo esc_html( $importer->get_label() ); ?></h2>

					<?php if ( ! $importer->is_available() ) : ?>
						<p class="ccf-import-none">
							<?php
							printf(
								/* translators: %s: source plugin name. */
								esc_html__( 'No %s forms found on this site.', 'custom-contact-forms' ),
								esc_html( $importer->get_label() )
							);
							?>
						</p>
					<?php else : ?>
						<?php $forms = $importer->get_source_forms(); ?>
						<form method="post">
							<?php wp_nonce_field( 'ccf_import_forms' ); ?>
							<input type="hidden" name="ccf_import_source" value="<?php echo esc_attr( $importer->get_id() ); ?>">

							<div class="ccf-import-list">
								<?php foreach ( $forms as $form ) : ?>
									<?php $already = $importer->find_existing_import( $form['id'] ); ?>
									<label>
										<input type="checkbox" name="ccf_import_forms[]" value="<?php echo esc_attr( $form['id'] ); ?>" <?php checked( ! $already ); ?>>
										<?php echo esc_html( $form['title'] ); ?>
										<?php if ( $already ) : ?>
											<span class="ccf-import-none"><?php esc_html_e( '(already imported)', 'custom-contact-forms' ); ?></span>
										<?php endif; ?>
									</label>
								<?php endforeach; ?>
							</div>

							<?php submit_button( esc_html__( 'Import selected forms', 'custom-contact-forms' ), 'primary', 'submit', false ); ?>
						</form>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
	}
}
