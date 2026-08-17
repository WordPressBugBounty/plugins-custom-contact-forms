<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CCF_Form_CPT {

	/**
	 * Placeholder method
	 *
	 * @since 6.0
	 */
	public function __construct() {}

	/**
	 * Keep track of post types when we change them
	 *
	 * @var $old_post_types
	 * @since 6.5
	 */
	public $old_post_types = false;

	/**
	 * Setup form screen with actions and filters
	 *
	 * @since 6.0
	 */
	public function setup() {

		add_action( 'init', array( $this, 'setup_cpt' ) );
		add_filter( 'manage_edit-ccf_form_columns', array( $this, 'filter_columns' ) );
		add_action( 'manage_ccf_form_posts_custom_column', array( $this, 'action_columns' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'action_admin_enqueue_scripts' ), 9 );
		add_action( 'customize_controls_enqueue_scripts', array( $this, 'action_admin_enqueue_scripts' ) );
		add_action( 'edit_form_after_title', array( $this, 'action_edit_form_after_title' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
		add_filter( 'post_row_actions', array( $this, 'filter_post_row_actions' ), 10, 2 );
		add_filter( 'get_the_excerpt', array( $this, 'filter_get_the_excerpt' ) );
		add_filter( 'screen_settings', array( $this, 'filter_screen_options' ), 10, 2 );
		add_action( 'before_delete_post', array( $this, 'action_before_delete_post' ) );
		add_filter( 'wp_link_query_args', array( $this, 'filter_wp_link_query_args' ) );
		add_action( 'admin_init', array( $this, 'action_parse_request' ) );
	}

	/**
	 * Handle submissions csv download
	 *
	 * @since 6.6
	 */
	public function action_parse_request() {
		if ( empty( $_GET['download_submissions'] ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( empty( $_GET['post'] ) || 'ccf_form' !== get_post_type( absint( $_GET['post'] ) ) ) {
			return;
		}

		if ( empty( $_GET['download_submissions_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['download_submissions_nonce'] ) ), 'ccf_download_submissions_nonce' ) ) {
			return;
		}

		$post_id = absint( $_GET['post'] );

		$submissions = new WP_Query( array(
			'post_type' => 'ccf_submission',
			'posts_per_page' => apply_filters( 'ccf_max_submissions', 5000, $post_id ),
			'no_found_rows' => true,
			'cache_results' => false,
			'fields' => 'ids',
			'order' => 'DESC',
			'orderby' => 'date',
			'post_parent' => $post_id,
		) );

		// Todo: Unit tests
		header( 'Content-Type: text/csv' );
		header( 'Content-Disposition: attachment; filename=form-' . $post_id . '-submission-' . gmdate( 'Y-m-d' ) . '.csv' );
		header( 'Cache-Control: no-cache, no-store, must-revalidate' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		$output = fopen( 'php://output', 'w' );
		fwrite( $output,chr( 0xEF ).chr( 0xBB ).chr( 0xBF ) );
		if ( $submissions->have_posts() ) {
			$last_submission_id = $submissions->posts[0];

			$fields = get_post_meta( $last_submission_id, 'ccf_submission_data_map', true );

			if ( empty( $fields ) ) {

				$slugs = array_keys( get_post_meta( $last_submission_id, 'ccf_submission_data', true ) );

				$fields = array();

				$attached_fields = get_post_meta( $post_id, 'ccf_attached_fields', true );
				$attached_field_slugs = array();

				foreach ( $attached_fields as $field_id ) {
					$slug = get_post_meta( $field_id, 'ccf_field_slug', true );

					if ( ! empty( $slug ) ) {
						$attached_field_slugs[ $slug ] = $field_id;
					}
				}

				foreach ( $slugs as $slug ) {
					if ( ! empty( $attached_field_slugs[ $slug ] ) ) {
						$fields[ $slug ] = array( 'id' => $attached_field_slugs[ $slug ], 'type' => get_post_meta( $attached_field_slugs[ $slug ], 'ccf_field_type', true ) );
					}
				}
			}

			$headers = array_merge( array( 'date', 'ip' ), array_keys( $fields ) );

			fputcsv( $output, $headers );

			foreach ( $submissions->posts as $submission_id ) {
				$submission_data = get_post_meta( $submission_id, 'ccf_submission_data', true );
				$submission_ip = get_post_meta( $submission_id, 'ccf_submission_ip', true );

				$row = array(
					get_the_time( 'Y-m-d H:i:s', $submission_id ),
					sanitize_text_field( $submission_ip ),
				);

				foreach ( $fields as $slug => $field_array ) {
					$type = $field_array['type'];

					if ( ! empty( $submission_data[ $slug ] ) ) {
						$field = $submission_data[ $slug ];

						if ( 'date' === $type ) {

							$row[] = stripslashes( CCF_Submission_CPT::factory()->get_pretty_field_date( $field ) );

						} elseif ( 'name' === $type ) {

							$row[] = stripslashes( CCF_Submission_CPT::factory()->get_pretty_field_name( $field ) );

						} elseif ( 'file' === $type ) {

							$row[] = $field['url'];

						} elseif ( 'address' === $type ) {

							$row[] = stripslashes( CCF_Submission_CPT::factory()->get_pretty_field_address( $field ) );

						} elseif ( 'email' === $type ) {

							if ( is_array( $field ) ) {
								$row[] = stripslashes( $field['email'] );
							} else {
								$row[] = stripslashes( $field );
							}
						} elseif ( 'dropdown' === $type || 'radio' === $type || 'checkboxes' === $type ) {
							if ( is_array( $field ) ) {
								$i = 0;
								$outputval = '';

								foreach ( $field as $value ) {
									if ( ! empty( $value ) ) {
										if ( $i !== 0 ) {
											$outputval .= ', ';
										}

										$outputval .= stripslashes( $value );

										$i++;
									}
								}

								if ( 0 === $i ) {
									$row[] = '';
								} else {
									$row[] = $outputval;
								}
							} else {
								$row[] = stripslashes( $field );
							}
						} else {
							$row[] = stripslashes( $field );
						}
					} else {
						$row[] = '';
					}
				}

				fputcsv( $output, $row );
			}

			fclose( $output );
		}

		exit;
	}

	/**
	 * Remove CCF post types from links query
	 *
	 * @param array $query
	 * @since 6.5.1
	 * @return array
	 */
	public function filter_wp_link_query_args( $query ) {
		if ( is_array( $query ) && ! empty( $query['post_type'] ) ) {

			$post_types = array();
			$ccf_post_types = array( 'ccf_submission', 'ccf_form', 'ccf_field', 'ccf_choice' );

			foreach ( $query['post_type'] as $post_type ) {
				if ( ! in_array( $post_type, $ccf_post_types ) ) {
					$post_types[] = $post_type;
				}
			}

			$query['post_type'] = $post_types;
		}

		return $query;
	}

	/**
	 * When a form is deleted from the trash, delete all fields, submissions, and choices
	 *
	 * @param int $form_id
	 * @since 6.0
	 */
	public function action_before_delete_post( $form_id ) {
		if ( 'ccf_form' !== get_post_type( $form_id ) ) {
			return;
		}

		$submissions = get_children( array( 'post_parent' => $form_id, 'post_type' => 'ccf_submission', 'numberposts' => apply_filters( 'ccf_max_submissions', 5000, get_post( $form_id ) ) ) );
		if ( ! empty( $submissions ) ) {
			foreach ( $submissions as $submission ) {
				wp_delete_post( $submission->ID, true );
			}
		}

		$attached_fields = get_post_meta( $form_id, 'ccf_attached_fields', true );

		if ( ! empty( $attached_fields ) ) {
			foreach ( $attached_fields as $field_id ) {
				$attached_choices = get_post_meta( $field_id, 'ccf_attached_choices', true );

				if ( ! empty( $attached_choices ) ) {
					foreach ( $attached_choices as $choice_id ) {
						wp_delete_post( $choice_id, true );
					}
				}

				wp_delete_post( $field_id, true );
			}
		}

	}

	/**
	 * Add extra html to screen options for submission columns
	 *
	 * @param array $options
	 * @param array $screen
	 * @since 6.0
	 * @return string
	 */
	public function filter_screen_options( $options, $screen ) {
		global $pagenow;
		if ( 'post.php' !== $pagenow || empty( $_GET['post'] ) || 'ccf_form' !== get_post_type( absint( $_GET['post'] ) ) ) {
			return $options;
		}

		ob_start();
		?>
		<h5 class="screen-layout"><?php esc_html_e( 'Form Submission Columns', 'custom-contact-forms' ); ?></h5>
		<div class="submission-table-prefs ccf-submission-column-controller">
			<div class="spinner"></div>
		</div>

		<?php

		return $options . ob_get_clean();
	}

	/**
	 * Use form description instead of excerpt. Excerpt presents unnecessary complications in the form manager
	 * so we store the description in post meta instead.
	 *
	 * @param string $excerpt
	 * @since 6.0
	 * @return string
	 */
	public function filter_get_the_excerpt( $excerpt ) {
		if ( 'ccf_form' === get_post_type() ) {
			$excerpt = get_post_meta( get_the_ID(), 'ccf_form_description', true );
		}

		return $excerpt;
	}

	/**
	 * Modify row actions to fit a form.
	 *
	 * @param array  $actions
	 * @param object $post
	 * @since 6.0
	 * @return array
	 */
	public function filter_post_row_actions( $actions, $post ) {
		if ( 'ccf_form' !== get_post_type( $post ) ) {
			return $actions;
		}

		if ( ! empty( $_GET['post_status'] ) && 'trash' === $_GET['post_status'] ) {
			return $actions;
		}

		unset( $actions['view'] );
		unset( $actions['inline hide-if-no-js'] );

		$trash = $actions['trash'];
		unset( $actions['trash'] );

		$actions['submissions'] = '<a href="' . esc_url( get_edit_post_link( $post->ID ) ) . '#ccf-submissions">' . esc_html__( 'Submissions', 'custom-contact-forms' ) . '</a>';
		$actions['copy_shortcode'] = '<a href="#" class="ccf-copy-shortcode" data-shortcode="[ccf_form id=&quot;' . (int) $post->ID . '&quot;]" title="' . esc_attr__( 'Copy shortcode to clipboard', 'custom-contact-forms' ) . '">' . esc_html__( 'Copy Shortcode', 'custom-contact-forms' ) . '</a>';
		$actions['trash'] = $trash;
		return $actions;
	}

	/**
	 * Register form meta boxes
	 *
	 * @since 6.0
	 */
	public function add_meta_boxes() {
		global $pagenow;

		remove_meta_box( 'slugdiv', 'ccf_form', 'normal' );
		add_meta_box( 'ccf-at-a-glance', esc_html__( 'At a Glance', 'custom-contact-forms' ), array( $this, 'meta_box_at_a_glance' ), 'ccf_form', 'side', 'core' );
		add_meta_box( 'ccf-preview', esc_html__( 'Preview', 'custom-contact-forms' ), array( $this, 'meta_box_preview' ), 'ccf_form', 'normal', 'core' );

		if ( 'post-new.php' !== $pagenow ) {
			add_meta_box( 'ccf-submissions', esc_html__( 'Submissions', 'custom-contact-forms' ), array( $this, 'meta_box_submissions' ), 'ccf_form', 'normal', 'core' );
		}

		remove_meta_box( 'wpseo_meta', 'ccf_form', 'normal' );
	}

	/**
	 * Output preview meta box
	 *
	 * @param object $post
	 * @since 6.0
	 */
	public function meta_box_preview( $post ) {
		global $pagenow;

		if ( 'post-new.php' === $pagenow ) {
			?>
			<p><?php esc_html_e( 'Save your new form to see a preview.', 'custom-contact-forms' ); ?></p>
			<?php
		} else {
			?>
			<div class="ccf-form-cpt-preview" data-form-id="<?php echo (int) $post->ID; ?>">
				<div class="spinner"></div>
			</div>
			<?php
		}
	}

	/**
	 * Output submissions meta box. This is a placeholder method since JS will do the work.
	 *
	 * @param object $post
	 * @since 6.0
	 */
	public function meta_box_submissions( $post ) {
		// Toolbar CSS is enqueued via ccf-admin-toolbar.css
		// Toolbar JS is added via wp_add_inline_script on ccf-form-cpt-preview
	}

	/**
	 * Output at a glance meta box. This contains stats about the form.
	 *
	 * @param object $post
	 * @since 6.0
	 */
	public function meta_box_at_a_glance( $post ) {
		$user = get_userdata( $post->post_author );
		$fields = get_post_meta( $post->ID, 'ccf_attached_fields', true );
		if ( empty( $fields ) ) {
			$fields = array();
		}

		$submissions = get_children( array( 'post_type' => 'ccf_submission', 'post_parent' => $post->ID, 'numberposts' => apply_filters( 'ccf_max_submissions', 5000, $post ) ) );

		?>
		<div class="minor-publishing-actions">
			<div class="misc-pub-section curtime">
				<span id="timestamp" class="has-icon"><?php esc_html_e( 'Created on:', 'custom-contact-forms' ); ?> <strong><?php echo get_the_date( '', $post->ID ); ?></strong></span>
			</div>
			<div class="misc-pub-section">
				<span id="ccf-created-by" class="has-icon"><?php esc_html_e( 'Author:', 'custom-contact-forms' ); ?> <strong><?php printf( '<a href="%s">%s</a>', esc_url( add_query_arg( array( 'post_type' => $post->post_type, 'author' => (int) $user->ID ), 'edit.php' ) ), esc_attr( $user->user_nicename ) ); ?></strong></span>
			</div>
			<div class="misc-pub-section">
				<span id="ccf-field-num" class="has-icon"><?php esc_html_e( 'Number of fields:', 'custom-contact-forms' ); ?> <strong><?php echo count( $fields ); ?></strong></span>
			</div>
			<div class="misc-pub-section">
				<span id="ccf-submission-num" class="has-icon"><?php esc_html_e( 'Number of submissions:', 'custom-contact-forms' ); ?> <strong><?php echo count( $submissions ); ?></strong></span>
			</div>
			<div class="misc-pub-section" style="border-top:1px solid #f0f0f1;padding-top:8px;">
				<label style="font-weight:600;display:block;margin-bottom:4px;"><?php esc_html_e( 'Shortcode:', 'custom-contact-forms' ); ?></label>
				<code class="ccf-copy-target" style="display:block;padding:5px 8px;background:#f6f7f7;border:1px solid #dcdcde;border-radius:3px;font-size:12px;cursor:pointer;user-select:all;" data-copy="[ccf_form id=&quot;<?php echo (int) $post->ID; ?>&quot;]" title="<?php esc_attr_e( 'Click to copy', 'custom-contact-forms' ); ?>">[ccf_form id="<?php echo (int) $post->ID; ?>"]</code>
			</div>
			<div class="misc-pub-section">
				<label style="font-weight:600;display:block;margin-bottom:4px;"><?php esc_html_e( 'PHP:', 'custom-contact-forms' ); ?></label>
				<code class="ccf-copy-target" style="display:block;padding:5px 8px;background:#f6f7f7;border:1px solid #dcdcde;border-radius:3px;font-size:11px;cursor:pointer;user-select:all;" data-copy="&lt;?php ccf_output_form( <?php echo (int) $post->ID; ?> ); ?&gt;" title="<?php esc_attr_e( 'Click to copy', 'custom-contact-forms' ); ?>">&lt;?php ccf_output_form( <?php echo (int) $post->ID; ?> ); ?&gt;</code>
			</div>
		</div>

		<div id="major-publishing-actions">
			<div id="delete-action">
				<a class="submitdelete deletion" href="<?php echo get_delete_post_link( $post->ID ); ?>"><?php esc_html_e( 'Move to Trash', 'custom-contact-forms' ); ?></a><br>
				<a class="submitdelete duplicate" href=""><?php esc_html_e( 'Duplicate', 'custom-contact-forms' ); ?></a>
				<div class="clear"></div>
			</div>

			<a class="button export-button" href="<?php echo esc_url( admin_url( 'post.php?action=edit&post=' . (int) $post->ID . '&nonce=' . wp_create_nonce( 'ccf_form_export' ) ) . '&export=1' ); ?>"><?php esc_html_e( 'Export', 'custom-contact-forms' ); ?></a>
		</div>
		<?php
	}

	/**
	 * Form title is not editable through normal edit screen so remove the input. We all need to add the
	 * manage form button. The edit form screen is not used for editing the form but rather to display
	 * information.
	 *
	 * @param object $post
	 * @since 6.0
	 */
	public function action_edit_form_after_title( $post ) {
		global $pagenow;

		if ( 'ccf_form' != get_post_type() ) {
			return;
		}

		$title = get_the_title();

		?>
		<div class="ccf-title">
			<h1>
				<span class="ccf-form-cpt-title">
					<?php if ( ! empty( $title ) ) : ?>
						<?php echo esc_html( $title ); ?>
					<?php else : ?>
						<?php esc_html_e( '(No title)', 'custom-contact-forms' ); ?>
					<?php endif; ?>
				</span>

				<a class="button button-primary ccf-open-form-manager" <?php if ( 'post.php' === $pagenow ) : ?>data-form-id="<?php the_ID(); ?>"<?php endif; ?>>
					<?php esc_html_e( 'Manage Form', 'custom-contact-forms' ); ?>
				</a>

				<?php if ( class_exists( 'CCF_AI_Form_Builder' ) ) : ?>
					<button type="button" class="button ccf-ai-open ccf-ai-beside-manage">&#10024; <?php esc_html_e( 'Generate with AI', 'custom-contact-forms' ); ?></button>
				<?php endif; ?>
			</h1>
		</div>
		<?php
	}

	/**
	 * Setup JS and CSS
	 *
	 * @since 6.0
	 */
	public function action_admin_enqueue_scripts() {
		if ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) {
			$admin_css_path = '/assets/build/css/admin.css';
			$form_cpt_css_path = '/assets/build/css/form-cpt.css';
			$form_table_css_path = '/assets/build/css/form-table.css';
			$form_cpt_preview_js_path = '/assets/js/form-cpt-preview.js';
			$mce_css_path = '/assets/build/css/form-mce.css';
		} else {
			$admin_css_path = '/assets/build/css/admin.min.css';
			$form_cpt_css_path = '/assets/build/css/form-cpt.min.css';
			$form_table_css_path = '/assets/build/css/form-table.min.css';
			$form_cpt_preview_js_path = '/assets/build/js/form-cpt-preview.min.js';
			$mce_css_path = '/assets/build/css/form-mce.min.css';
		}
		wp_enqueue_style( 'ccf-admin', plugins_url( $admin_css_path, dirname( __FILE__ ) ), array(), CCF_VERSION );

		global $pagenow, $wp_customize;

		$is_edit_post = (
			( 'post.php' === $pagenow && 'ccf_form' === get_post_type() )
			||
			( 'post-new.php' === $pagenow && isset( $_GET['post_type'] ) && 'ccf_form' === $_GET['post_type'] )
			||
			( ! empty( $wp_customize ) && isset( $wp_customize->posts ) )
		);
		if ( $is_edit_post ) {
			wp_dequeue_script( 'autosave' );

			add_thickbox();

			wp_enqueue_style( 'ccf-form-mce', plugins_url( $mce_css_path, dirname( __FILE__ ) ), array(), CCF_VERSION );
			wp_enqueue_style( 'ccf-form-cpt', plugins_url( $form_cpt_css_path, dirname( __FILE__ ) ), array(), CCF_VERSION );
			wp_enqueue_style( 'ccf-admin-toolbar', plugins_url( '/build/css/ccf-admin-toolbar.css', dirname( __FILE__ ) ), array(), CCF_VERSION );

			wp_enqueue_script( 'ccf-form-cpt-preview', plugins_url( $form_cpt_preview_js_path, dirname( __FILE__ ) ), array( 'jquery', 'backbone', 'ccf-form-manager' ), CCF_VERSION, true );

			// Toolbar injection script — runs after Backbone creates the submissions table
			$toolbar_js = '(function(){var ci=setInterval(function(){var m=document.getElementById("ccf-submissions");if(!m)return;var ins=m.querySelector(".inside");if(!ins)return;var t=ins.querySelector("table");if(!t)return;clearInterval(ci);var old=m.querySelectorAll(".ccf-submission-icon,.ccf-submission-icon-btn");for(var i=0;i<old.length;i++)old[i].parentNode.removeChild(old[i]);if(m.querySelector(".ccf-toolbar"))return;var s=window.ccfSettings||{};var url=s.adminUrl+"post.php?action=edit&post="+parseInt(s.postId)+"&download_submissions=1&download_submissions_nonce="+s.downloadSubmissionsNonce;var tb=document.createElement("div");tb.className="ccf-toolbar";tb.innerHTML=\'<a href="\'+url+\'" class="button button-small" title="Download CSV"><span class="dashicons dashicons-download"></span> Export CSV</a><button type="button" class="button button-small" title="Column Settings" onclick="var b=document.getElementById(\\\'show-settings-link\\\');if(b){b.click();}else{var w=document.getElementById(\\\'screen-options-wrap\\\');if(w){w.style.display=w.style.display===\\\'none\\\'?\\\'block\\\':\\\'none\\\';}}"><span class="dashicons dashicons-admin-generic"></span> Columns</button>\';ins.insertBefore(tb,ins.firstChild);},200);setTimeout(function(){clearInterval(ci);},10000);})();';
			wp_add_inline_script( 'ccf-form-cpt-preview', $toolbar_js );

			// Clipboard copy script for shortcodes
			add_action( 'admin_footer', array( $this, 'clipboard_script' ) );
		} elseif ( 'edit.php' === $pagenow && 'ccf_form' === get_post_type() ) {
			wp_enqueue_style( 'ccf-form-table', plugins_url( $form_table_css_path, dirname( __FILE__ ) ), array(), CCF_VERSION );

			// Clipboard copy script for shortcodes
			add_action( 'admin_footer', array( $this, 'clipboard_script' ) );
		}
	}

	/**
	 * Filter form table columns
	 *
	 * @param array $columns
	 * @since 6.0
	 * @return array
	 */
	public function filter_columns( $columns ) {

		$columns = array(
			'cb' => '<input type="checkbox" />',
			'title' => esc_html__( 'Form Title', 'custom-contact-forms' ),
			'author' => esc_html__( 'Author', 'custom-contact-forms' ),
			'submissions' => esc_html__( 'Submissions', 'custom-contact-forms' ),
			'fields' => esc_html__( 'Number of Fields', 'custom-contact-forms' ),
			'ccf_form_id' => esc_html__( 'Form ID', 'custom-contact-forms' ),
			'ccf_date' => esc_html__( 'Date', 'custom-contact-forms' ),
		);

		return $columns;
	}

	/**
	 * Output form columns. We redo the date column to get of inappropriate text such as "Published".
	 *
	 * @param string $column
	 * @param int    $post_id
	 * @since 6.0
	 */
	public function action_columns( $column, $post_id ) {
		$post = get_post( $post_id );

		switch ( $column ) {
			case 'submissions':
				$submissions = get_children( array( 'post_type' => 'ccf_submission', 'post_parent' => $post_id, 'numberposts' => apply_filters( 'ccf_max_submissions', 5000, $post ) ) );
				echo '<a href="' . esc_url( get_edit_post_link( $post->ID ) ) . '#ccf-submissions">' . count( $submissions ) . '</a>';

				break;
			case 'fields':
				$fields = get_post_meta( $post_id, 'ccf_attached_fields', true );
				if ( empty( $fields ) ) {
					echo 0;
				} else {
					echo count( $fields );
				}

				break;
			case 'ccf_form_id':
				$shortcode = '[ccf_form id="' . (int) $post->ID . '"]';
				$php_code = '<?php ccf_output_form( ' . (int) $post->ID . ' ); ?>';
				echo '<code class="ccf-shortcode-display" style="display:block;padding:3px 6px;background:#f0f0f1;border-radius:3px;font-size:12px;cursor:pointer;user-select:all;" title="' . esc_attr__( 'Click to copy', 'custom-contact-forms' ) . '">' . esc_html( $shortcode ) . '</code>';
				echo '<small style="color:#757575;display:block;margin-top:2px;">ID: ' . (int) $post->ID . '</small>';

				break;
			case 'ccf_date':
				if ( '0000-00-00 00:00:00' == $post->post_date ) {
					$t_time = $h_time = __( 'Unpublished', 'custom-contact-forms' );
				} else {
					$t_time = get_the_time( __( 'Y/m/d g:i:s A', 'custom-contact-forms' ) );
					$m_time = $post->post_date;
					$time = get_post_time( 'G', true, $post );

					$time_diff = time() - $time;

					if ( $time_diff > 0 && $time_diff < DAY_IN_SECONDS ) {
						/* translators: %s: human-readable time difference */
						$h_time = sprintf( __( '%s ago', 'custom-contact-forms' ), human_time_diff( $time ) );
					} else { 						$h_time = mysql2date( __( 'Y/m/d', 'custom-contact-forms' ), $m_time ); }
				}

				echo '<abbr title="' . esc_attr( $t_time ) . '">' . esc_html( $h_time ) . '</abbr>';
				break;
		}
	}

	/**
	 * Register form post type
	 *
	 * @since 6.0
	 */
	public function setup_cpt() {

		$labels = array(
			'name' => esc_html__( 'Forms', 'custom-contact-forms' ),
			'singular_name' => esc_html__( 'Form', 'custom-contact-forms' ),
			'add_new' => esc_attr__( 'New Form', 'custom-contact-forms' ),
			'add_new_item' => esc_html__( 'Add New Form', 'custom-contact-forms' ),
			'edit_item' => esc_html__( 'Edit Form', 'custom-contact-forms' ),
			'new_item' => esc_html__( 'New Form', 'custom-contact-forms' ),
			'all_items' => esc_html__( 'Forms and Submissions', 'custom-contact-forms' ),
			'view_item' => esc_attr__( 'View Form', 'custom-contact-forms' ),
			'search_items' => esc_html__( 'Search Forms', 'custom-contact-forms' ),
			'not_found' => esc_html__( 'No forms found.', 'custom-contact-forms' ),
			'not_found_in_trash' => esc_html__( 'No forms found in trash.', 'custom-contact-forms' ),
			'parent_item_colon' => '',
			'menu_name' => esc_html__( 'Forms', 'custom-contact-forms' ),
		);

		$args = array(
			'labels' => $labels,
			'public' => false,
			'publicly_queryable' => false,
			'exclude_from_search' => true,
			'show_ui' => true,
			'show_in_menu' => true,
			'show_in_customizer' => false,
			'query_var' => false,
			'rewrite' => false,
			'capability_type' => 'post',
			'hierarchical' => false,
			'supports' => array( 'title' ),
			'has_archive' => false,
		);

		register_post_type( 'ccf_form', $args );
	}

	/**
	 * Output clipboard copy JS for shortcode buttons
	 *
	 * @since 7.9.0
	 */
	public function clipboard_script() {
		wp_enqueue_script( 'ccf-clipboard', plugins_url( '/assets/js/ccf-clipboard.js', dirname( __FILE__ ) ), array(), CCF_VERSION, true );
		wp_localize_script( 'ccf-clipboard', 'ccfClipboardL10n', array(
			'copied' => esc_html__( 'Copied!', 'custom-contact-forms' ),
		) );
	}

	/**
	 * Return singleton instance of class
	 *
	 * @since 6.0
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
}
