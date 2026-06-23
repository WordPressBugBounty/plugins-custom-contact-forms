<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CSV Submission Importer for CCF.
 *
 * Adds a "Import CSV" page under the Forms menu. Users can:
 * 1. Select a form and upload a CSV file
 * 2. Map CSV columns to form field slugs
 * 3. Import submissions in bulk
 *
 * @since 7.9.0
 */
class CCF_CSV_Importer {

	/** @var int Max rows to preview */
	const PREVIEW_ROWS = 5;

	/** @var int Max rows per import */
	const MAX_IMPORT_ROWS = 5000;

	public function __construct() {}

	/**
	 * Setup hooks
	 *
	 * @since 7.9.0
	 */
	public function setup() {
		add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
	}

	/**
	 * Add submenu page under Forms
	 *
	 * @since 7.9.0
	 */
	public function add_menu_page() {
		add_submenu_page(
			'edit.php?post_type=ccf_form',
			esc_html__( 'Import Submissions from CSV', 'custom-contact-forms' ),
			esc_html__( 'Import CSV', 'custom-contact-forms' ),
			'manage_options',
			'ccf-csv-import',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Get all form field slugs, labels, and types for a form
	 *
	 * @param int $form_id
	 * @since 7.9.0
	 * @return array [ slug => [ 'label' => ..., 'type' => ..., 'id' => ... ] ]
	 */
	private function get_form_fields( $form_id ) {
		$attached_fields = get_post_meta( $form_id, 'ccf_attached_fields', true );
		$fields = array();

		if ( empty( $attached_fields ) || ! is_array( $attached_fields ) ) {
			return $fields;
		}

		foreach ( $attached_fields as $field_id ) {
			$slug  = get_post_meta( $field_id, 'ccf_field_slug', true );
			$label = get_post_meta( $field_id, 'ccf_field_label', true );
			$type  = get_post_meta( $field_id, 'ccf_field_type', true );

			if ( ! empty( $slug ) ) {
				$fields[ $slug ] = array(
					'label' => ! empty( $label ) ? $label : $slug,
					'type'  => $type,
					'id'    => $field_id,
				);
			}
		}

		return $fields;
	}

	/**
	 * Parse an uploaded CSV file
	 *
	 * @param string $file_path
	 * @since 7.9.0
	 * @return array|WP_Error [ 'headers' => [...], 'rows' => [...] ]
	 */
	private function parse_csv( $file_path ) {
		if ( ! file_exists( $file_path ) || ! is_readable( $file_path ) ) {
			return new WP_Error( 'file_error', __( 'Cannot read the uploaded file.', 'custom-contact-forms' ) );
		}

		$handle = fopen( $file_path, 'r' );
		if ( false === $handle ) {
			return new WP_Error( 'file_error', __( 'Cannot open the CSV file.', 'custom-contact-forms' ) );
		}

		// Handle BOM
		$bom = fread( $handle, 3 );
		if ( "\xEF\xBB\xBF" !== $bom ) {
			rewind( $handle );
		}

		$headers = fgetcsv( $handle );
		if ( false === $headers || empty( $headers ) ) {
			fclose( $handle );
			return new WP_Error( 'empty_csv', __( 'The CSV file is empty or has no header row.', 'custom-contact-forms' ) );
		}

		// Trim headers
		$headers = array_map( 'trim', $headers );

		$rows = array();
		$count = 0;
		while ( false !== ( $row = fgetcsv( $handle ) ) && $count < self::MAX_IMPORT_ROWS ) {
			if ( count( $row ) === 1 && empty( $row[0] ) ) {
				continue; // Skip blank lines
			}
			$rows[] = $row;
			$count++;
		}

		fclose( $handle );

		if ( empty( $rows ) ) {
			return new WP_Error( 'no_data', __( 'The CSV file has headers but no data rows.', 'custom-contact-forms' ) );
		}

		return array(
			'headers' => $headers,
			'rows'    => $rows,
		);
	}

	/**
	 * Auto-map CSV headers to form field slugs
	 *
	 * @param array $csv_headers
	 * @param array $form_fields [ slug => [ 'label' => ..., 'type' => ... ] ]
	 * @since 7.9.0
	 * @return array [ csv_column_index => field_slug ]
	 */
	private function auto_map( $csv_headers, $form_fields ) {
		$mapping = array();

		foreach ( $csv_headers as $col_index => $header ) {
			$header_lower = strtolower( trim( $header ) );

			// Exact slug match
			if ( isset( $form_fields[ $header ] ) ) {
				$mapping[ $col_index ] = $header;
				continue;
			}

			// Case-insensitive slug match
			foreach ( $form_fields as $slug => $field ) {
				if ( strtolower( $slug ) === $header_lower ) {
					$mapping[ $col_index ] = $slug;
					continue 2;
				}
			}

			// Label match
			foreach ( $form_fields as $slug => $field ) {
				if ( strtolower( $field['label'] ) === $header_lower ) {
					$mapping[ $col_index ] = $slug;
					continue 2;
				}
			}

			// Skip columns like 'date', 'ip', 'ip address', 'form page url'
			// These are meta columns from CCF exports, not field data
		}

		return $mapping;
	}

	/**
	 * Import a single row as a submission
	 *
	 * @param int   $form_id
	 * @param array $row CSV row data
	 * @param array $mapping [ csv_col_index => field_slug ]
	 * @param array $form_fields
	 * @since 7.9.0
	 * @return int|WP_Error Submission post ID or error
	 */
	private function import_row( $form_id, $row, $mapping, $form_fields ) {
		$submission_data = array();
		$field_slug_to_id = array();

		foreach ( $mapping as $col_index => $slug ) {
			if ( ! isset( $row[ $col_index ] ) || '' === $slug ) {
				continue;
			}

			$value = sanitize_text_field( $row[ $col_index ] );
			$submission_data[ $slug ] = $value;

			if ( isset( $form_fields[ $slug ] ) ) {
				$field_slug_to_id[ $slug ] = array(
					'id'   => $form_fields[ $slug ]['id'],
					'type' => $form_fields[ $slug ]['type'],
				);
			}
		}

		if ( empty( $submission_data ) ) {
			return new WP_Error( 'empty_row', __( 'No mapped data in this row.', 'custom-contact-forms' ) );
		}

		$submission_id = wp_insert_post( array(
			'post_status' => 'publish',
			'post_type'   => 'ccf_submission',
			'post_parent' => $form_id,
			'post_title'  => 'Form Submission ' . $form_id,
		) );

		if ( is_wp_error( $submission_id ) ) {
			return $submission_id;
		}

		update_post_meta( $submission_id, 'ccf_submission_data', $submission_data );
		update_post_meta( $submission_id, 'ccf_submission_data_map', $field_slug_to_id );
		update_post_meta( $submission_id, 'ccf_submission_ip', 'CSV Import' );
		update_post_meta( $submission_id, 'ccf_submission_form_page', 'CSV Import' );

		return $submission_id;
	}

	/**
	 * Render the import page
	 *
	 * @since 7.9.0
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'custom-contact-forms' ) );
		}

		$step = isset( $_POST['ccf_import_step'] ) ? sanitize_text_field( wp_unslash( $_POST['ccf_import_step'] ) ) : 'upload';

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Import Submissions from CSV', 'custom-contact-forms' ) . '</h1>';

		switch ( $step ) {
			case 'map':
				$this->render_mapping_step();
				break;
			case 'import':
				$this->render_import_step();
				break;
			default:
				$this->render_upload_step();
				break;
		}

		echo '</div>';
	}

	/**
	 * Step 1: Upload form
	 *
	 * @since 7.9.0
	 */
	private function render_upload_step() {
		$forms = get_posts( array(
			'post_type'      => 'ccf_form',
			'post_status'    => 'publish',
			'posts_per_page' => 200,
			'orderby'        => 'title',
			'order'          => 'ASC',
		) );

		if ( empty( $forms ) ) {
			echo '<div class="notice notice-warning"><p>' . esc_html__( 'No forms found. Create a form first before importing submissions.', 'custom-contact-forms' ) . '</p></div>';
			return;
		}

		?>
		<div class="card" style="max-width:600px;margin-top:20px;">
			<h2 style="margin-top:0;"><?php esc_html_e( 'Step 1: Select Form & Upload CSV', 'custom-contact-forms' ); ?></h2>
			<p><?php esc_html_e( 'Choose which form the submissions belong to, then upload your CSV file. The first row should be column headers.', 'custom-contact-forms' ); ?></p>

			<form method="post" enctype="multipart/form-data">
				<?php wp_nonce_field( 'ccf_csv_import', 'ccf_import_nonce' ); ?>
				<input type="hidden" name="ccf_import_step" value="map">

				<table class="form-table">
					<tr>
						<th scope="row"><label for="ccf_form_id"><?php esc_html_e( 'Form', 'custom-contact-forms' ); ?></label></th>
						<td>
							<select name="ccf_form_id" id="ccf_form_id" required>
								<option value=""><?php esc_html_e( '— Select a form —', 'custom-contact-forms' ); ?></option>
								<?php foreach ( $forms as $form ) : ?>
									<option value="<?php echo (int) $form->ID; ?>">
										<?php
										$title = get_the_title( $form->ID );
										/* translators: %d: form ID */
										echo esc_html( ! empty( $title ) ? $title : sprintf( __( '(Untitled #%d)', 'custom-contact-forms' ), $form->ID ) );
										?>
									</option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="ccf_csv_file"><?php esc_html_e( 'CSV File', 'custom-contact-forms' ); ?></label></th>
						<td>
							<input type="file" name="ccf_csv_file" id="ccf_csv_file" accept=".csv,text/csv" required>
							<p class="description"><?php
							/* translators: %s: maximum number of rows */
							echo sprintf( esc_html__( 'Maximum %s rows. UTF-8 encoding recommended.', 'custom-contact-forms' ), number_format( self::MAX_IMPORT_ROWS ) ); ?></p>
						</td>
					</tr>
				</table>

				<?php submit_button( __( 'Upload & Map Columns', 'custom-contact-forms' ), 'primary', 'submit', true ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Step 2: Column mapping
	 *
	 * @since 7.9.0
	 */
	private function render_mapping_step() {
		if ( ! isset( $_POST['ccf_import_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ccf_import_nonce'] ) ), 'ccf_csv_import' ) ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Security check failed. Please try again.', 'custom-contact-forms' ) . '</p></div>';
			return;
		}

		$form_id = isset( $_POST['ccf_form_id'] ) ? (int) $_POST['ccf_form_id'] : 0;
		$form = get_post( $form_id );

		if ( empty( $form ) || 'ccf_form' !== $form->post_type ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Invalid form selected.', 'custom-contact-forms' ) . '</p></div>';
			return;
		}

		if ( empty( $_FILES['ccf_csv_file']['tmp_name'] ) ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'No file uploaded.', 'custom-contact-forms' ) . '</p></div>';
			return;
		}

		// Use WordPress upload handler
		$upload_overrides = array(
			'test_form' => false,
			'mimes'     => array( 'csv' => 'text/csv' ),
		);

		$uploaded = wp_handle_upload( $_FILES['ccf_csv_file'], $upload_overrides );

		if ( isset( $uploaded['error'] ) ) {
			echo '<div class="notice notice-error"><p>' . esc_html( $uploaded['error'] ) . '</p></div>';
			return;
		}

		$temp_file = $uploaded['file'];

		$parsed = $this->parse_csv( $temp_file );

		if ( is_wp_error( $parsed ) ) {
			wp_delete_file( $temp_file );
			echo '<div class="notice notice-error"><p>' . esc_html( $parsed->get_error_message() ) . '</p></div>';
			return;
		}

		$csv_headers = $parsed['headers'];
		$csv_rows    = $parsed['rows'];
		$form_fields = $this->get_form_fields( $form_id );
		$auto_mapped = $this->auto_map( $csv_headers, $form_fields );

		$form_title = get_the_title( $form_id );

		?>
		<div class="card" style="max-width:900px;margin-top:20px;">
			<h2 style="margin-top:0;">
				<?php
				echo sprintf(
					/* translators: %s: form title */
					esc_html__( 'Step 2: Map Columns → %s', 'custom-contact-forms' ),
					'<strong>' . esc_html( $form_title ) . '</strong>'
				);
				?>
			</h2>
			<p><?php
			/* translators: %d: number of rows found */
			echo sprintf( esc_html__( 'Found %d rows. Map each CSV column to a form field. Unmapped columns will be skipped.', 'custom-contact-forms' ), count( $csv_rows ) ); ?></p>

			<form method="post">
				<?php wp_nonce_field( 'ccf_csv_import_run', 'ccf_import_run_nonce' ); ?>
				<input type="hidden" name="ccf_import_step" value="import">
				<input type="hidden" name="ccf_form_id" value="<?php echo (int) $form_id; ?>">
				<input type="hidden" name="ccf_temp_file" value="<?php echo esc_attr( basename( $temp_file ) ); ?>">

				<table class="widefat striped" style="margin-bottom:20px;">
					<thead>
						<tr>
							<th style="width:30%;"><?php esc_html_e( 'CSV Column', 'custom-contact-forms' ); ?></th>
							<th style="width:30%;"><?php esc_html_e( 'Map to Field', 'custom-contact-forms' ); ?></th>
							<th><?php esc_html_e( 'Preview (first row)', 'custom-contact-forms' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $csv_headers as $col_index => $header ) : ?>
							<tr>
								<td><strong><?php echo esc_html( $header ); ?></strong></td>
								<td>
									<select name="ccf_mapping[<?php echo (int) $col_index; ?>]" style="width:100%;">
										<option value=""><?php esc_html_e( '— Skip this column —', 'custom-contact-forms' ); ?></option>
										<?php foreach ( $form_fields as $slug => $field ) : ?>
											<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( isset( $auto_mapped[ $col_index ] ) ? $auto_mapped[ $col_index ] : '', $slug ); ?>>
												<?php echo esc_html( $field['label'] . ' (' . $slug . ')' ); ?>
											</option>
										<?php endforeach; ?>
									</select>
								</td>
								<td style="color:#666;">
									<?php
									$preview = isset( $csv_rows[0][ $col_index ] ) ? $csv_rows[0][ $col_index ] : '';
									echo esc_html( mb_strimwidth( $preview, 0, 80, '...' ) );
									?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>

				<?php if ( count( $csv_rows ) > 1 ) : ?>
					<details style="margin-bottom:20px;">
						<summary style="cursor:pointer;font-weight:500;"><?php
						/* translators: %d: number of preview rows */
						echo sprintf( esc_html__( 'Preview first %d rows', 'custom-contact-forms' ), (int) min( self::PREVIEW_ROWS, count( $csv_rows ) ) ); ?></summary>
						<table class="widefat striped" style="margin-top:10px;">
							<thead>
								<tr>
									<?php foreach ( $csv_headers as $header ) : ?>
										<th><?php echo esc_html( $header ); ?></th>
									<?php endforeach; ?>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( array_slice( $csv_rows, 0, self::PREVIEW_ROWS ) as $row ) : ?>
									<tr>
										<?php foreach ( $csv_headers as $col_index => $header ) : ?>
											<td><?php echo esc_html( isset( $row[ $col_index ] ) ? mb_strimwidth( $row[ $col_index ], 0, 60, '...' ) : '' ); ?></td>
										<?php endforeach; ?>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</details>
				<?php endif; ?>

				<?php
				/* translators: %d: number of submissions to import */
				submit_button( sprintf( __( 'Import %d Submissions', 'custom-contact-forms' ), count( $csv_rows ) ), 'primary', 'submit', true ); ?>
				<p><a href="<?php echo esc_url( admin_url( 'edit.php?post_type=ccf_form&page=ccf-csv-import' ) ); ?>"><?php esc_html_e( '← Start Over', 'custom-contact-forms' ); ?></a></p>
			</form>
		</div>
		<?php
	}

	/**
	 * Step 3: Run the import
	 *
	 * @since 7.9.0
	 */
	private function render_import_step() {
		if ( ! isset( $_POST['ccf_import_run_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ccf_import_run_nonce'] ) ), 'ccf_csv_import_run' ) ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Security check failed.', 'custom-contact-forms' ) . '</p></div>';
			return;
		}

		$form_id   = isset( $_POST['ccf_form_id'] ) ? (int) $_POST['ccf_form_id'] : 0;
		$temp_name = isset( $_POST['ccf_temp_file'] ) ? sanitize_file_name( wp_unslash( $_POST['ccf_temp_file'] ) ) : '';
		$mapping   = isset( $_POST['ccf_mapping'] ) && is_array( $_POST['ccf_mapping'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['ccf_mapping'] ) ) : array();

		$form = get_post( $form_id );
		if ( empty( $form ) || 'ccf_form' !== $form->post_type ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Invalid form.', 'custom-contact-forms' ) . '</p></div>';
			return;
		}

		$upload_dir = wp_upload_dir();
		$temp_file  = $upload_dir['basedir'] . '/' . $temp_name;

		if ( empty( $temp_name ) || ! file_exists( $temp_file ) ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Temporary file not found. Please try again.', 'custom-contact-forms' ) . '</p></div>';
			return;
		}

		$parsed = $this->parse_csv( $temp_file );

		// Clean up temp file immediately
		wp_delete_file( $temp_file );

		if ( is_wp_error( $parsed ) ) {
			echo '<div class="notice notice-error"><p>' . esc_html( $parsed->get_error_message() ) . '</p></div>';
			return;
		}

		// Sanitize mapping
		$clean_mapping = array();
		foreach ( $mapping as $col_index => $slug ) {
			$slug = sanitize_text_field( $slug );
			if ( ! empty( $slug ) ) {
				$clean_mapping[ (int) $col_index ] = $slug;
			}
		}

		if ( empty( $clean_mapping ) ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'No columns were mapped. Please go back and map at least one column.', 'custom-contact-forms' ) . '</p></div>';
			return;
		}

		$form_fields = $this->get_form_fields( $form_id );
		$csv_rows    = $parsed['rows'];

		$imported = 0;
		$skipped  = 0;
		$errors   = 0;

		foreach ( $csv_rows as $row ) {
			$result = $this->import_row( $form_id, $row, $clean_mapping, $form_fields );

			if ( is_wp_error( $result ) ) {
				if ( 'empty_row' === $result->get_error_code() ) {
					$skipped++;
				} else {
					$errors++;
				}
			} else {
				$imported++;
			}
		}

		$form_title = get_the_title( $form_id );
		$edit_url   = admin_url( 'post.php?action=edit&post=' . $form_id );

		?>
		<div class="card" style="max-width:600px;margin-top:20px;">
			<h2 style="margin-top:0;"><?php esc_html_e( 'Import Complete', 'custom-contact-forms' ); ?></h2>

			<table class="widefat" style="margin:15px 0;">
				<tbody>
					<tr>
						<td><strong><?php esc_html_e( 'Form', 'custom-contact-forms' ); ?></strong></td>
						<td><?php echo esc_html( $form_title ); ?></td>
					</tr>
					<tr>
						<td><strong style="color:#0a6b0e;"><?php esc_html_e( 'Imported', 'custom-contact-forms' ); ?></strong></td>
						<td><strong style="color:#0a6b0e;"><?php echo (int) $imported; ?></strong></td>
					</tr>
					<?php if ( $skipped > 0 ) : ?>
						<tr>
							<td><strong style="color:#996800;"><?php esc_html_e( 'Skipped (empty)', 'custom-contact-forms' ); ?></strong></td>
							<td><?php echo (int) $skipped; ?></td>
						</tr>
					<?php endif; ?>
					<?php if ( $errors > 0 ) : ?>
						<tr>
							<td><strong style="color:#d63638;"><?php esc_html_e( 'Errors', 'custom-contact-forms' ); ?></strong></td>
							<td><?php echo (int) $errors; ?></td>
						</tr>
					<?php endif; ?>
				</tbody>
			</table>

			<p>
				<a href="<?php echo esc_url( $edit_url . '#ccf-submissions' ); ?>" class="button button-primary"><?php esc_html_e( 'View Submissions', 'custom-contact-forms' ); ?></a>
				<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=ccf_form&page=ccf-csv-import' ) ); ?>" class="button"><?php esc_html_e( 'Import More', 'custom-contact-forms' ); ?></a>
			</p>
		</div>
		<?php
	}

	/**
	 * @since 7.9.0
	 * @return CCF_CSV_Importer
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
