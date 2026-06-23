<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CCF_Export {

	public function __construct() {}

	/**
	 * @var array|false
	 * @since 6.5
	 */
	public $old_post_types = false;

	/**
	 * @since 6.5
	 */
	public function setup() {
		add_action( 'admin_init', array( $this, 'action_handle_export' ) );
		add_filter( 'export_args', array( $this, 'filter_export_args' ) );
		add_action( 'rss2_head', array( $this, 'action_rss2_head' ) );
		add_action( 'import_end', array( $this, 'action_import_end' ) );
		add_action( 'wp_import_insert_post', array( $this, 'action_wp_import_insert_post' ), 10, 1 );
		add_action( 'admin_menu', array( $this, 'action_admin_menu' ) );
		add_action( 'all_admin_notices', array( $this, 'action_all_admin_notices' ) );
		add_action( 'export_filters', array( $this, 'action_export_filters' ) );
	}

	/**
	 * @since 6.5
	 */
	public function action_all_admin_notices() {
		global $pagenow;

		if ( 'export.php' === $pagenow && ! isset( $_GET['download'] ) ) {
			global $wp_post_types;
			$this->old_post_types = $wp_post_types;

			$ccf_post_types = array( 'ccf_field', 'ccf_choice', 'ccf_submission' );

			foreach ( $wp_post_types as $slug => $post_type ) {
				if ( in_array( $slug, $ccf_post_types, true ) ) {
					$this->old_post_types[ $slug ] = clone $post_type;
					$post_type->can_export = false;
				}

				if ( 'ccf_form' === $slug ) {
					$this->old_post_types[ $slug ] = clone $post_type;
					$post_type->label = esc_html__( 'Forms and Submissions', 'custom-contact-forms' );
				}
			}
		}
	}

	/**
	 * @since 6.5
	 */
	public function action_export_filters() {
		global $pagenow;

		if ( 'export.php' === $pagenow && ! isset( $_GET['download'] ) ) {
			global $wp_post_types;

			if ( false !== $this->old_post_types ) {
				$wp_post_types = $this->old_post_types;
			}
		}
	}

	/**
	 * @since 6.5
	 */
	public function action_admin_menu() {
		// Removed: old "Import" link that pointed to WordPress core import.php
		// CSV import is now handled by CCF_CSV_Importer under Forms → Import CSV
	}

	/**
	 * @param int $post_id
	 * @since 6.5
	 */
	public function action_wp_import_insert_post( $post_id ) {
		$types = array( 'ccf_form', 'ccf_field' );

		if ( in_array( get_post_type( $post_id ), $types, true ) ) {
			update_post_meta( $post_id, 'ccf_import_cleanup', true );
		}
	}

	/**
	 * @since 6.5
	 */
	public function action_import_end() {
		$forms = new WP_Query( array(
			'post_type' => 'ccf_form',
			'posts_per_page' => 1000,
			'no_found_rows' => true,
		));

		if ( $forms->have_posts() ) {
			foreach ( $forms->posts as $form ) {
				$cleanup = get_post_meta( $form->ID, 'ccf_import_cleanup', true );

				if ( ! empty( $cleanup ) ) {
					$fields = wp_list_pluck( get_children( array( 'post_type' => 'ccf_field', 'post_parent' => $form->ID, 'numberposts' => 500 ) ), 'ID' );
					if ( ! empty( $fields ) ) {
						$fields = array_values( $fields );
					} else {
						$fields = array();
					}

					update_post_meta( $form->ID, 'ccf_attached_fields', $fields );
					delete_post_meta( $form->ID, 'ccf_import_cleanup' );
				}
			}
		}

		$fields = new WP_Query( array(
			'post_type' => 'ccf_field',
			'posts_per_page' => 2000,
			'no_found_rows' => true,
		));

		if ( $fields->have_posts() ) {
			foreach ( $fields->posts as $field ) {
				$cleanup = get_post_meta( $field->ID, 'ccf_import_cleanup', true );

				if ( ! empty( $cleanup ) ) {
					$choices = wp_list_pluck( get_children( array( 'post_type' => 'ccf_choice', 'post_parent' => $field->ID, 'numberposts' => 500 ) ), 'ID' );
					if ( ! empty( $choices ) ) {
						$choices = array_values( $choices );
					} else {
						$choices = array();
					}

					// FIX: was saving to wrong meta key (ccf_attached_fields instead of ccf_attached_choices)
					update_post_meta( $field->ID, 'ccf_attached_choices', $choices );
					delete_post_meta( $field->ID, 'ccf_import_cleanup' );
				}
			}
		}
	}

	/**
	 * Filter query for exporting a single form — SECURITY FIX: proper integer cast
	 *
	 * @param string $query
	 * @since 6.5
	 * @return string
	 */
	public function filter_query( $query ) {
		global $wpdb;

		if ( isset( $_GET['post'] ) && stripos( $query, 'ccf_form' ) !== false ) {
			remove_filter( 'query', array( $this, 'filter_query' ) );

			$form_id = absint( $_GET['post'] );

			if ( $form_id <= 0 || 'ccf_form' !== get_post_type( $form_id ) ) {
				return $query;
			}

			$post_ids = array( $form_id );

			// Get submissions
			$submissions = wp_list_pluck( get_children( array( 'post_parent' => $form_id, 'post_type' => 'ccf_submission', 'numberposts' => apply_filters( 'ccf_max_submissions', 5000, get_post( $form_id ) ) ) ), 'ID' );
			$post_ids = array_merge( $post_ids, $submissions );

			// Get fields
			$fields = get_post_meta( $form_id, 'ccf_attached_fields', true );

			if ( ! empty( $fields ) && is_array( $fields ) ) {
				foreach ( $fields as $field_id ) {
					$field_id = (int) $field_id;
					$post_ids[] = $field_id;

					$type = get_post_meta( $field_id, 'ccf_field_type', true );

					if ( 'dropdown' === $type || 'radio' === $type || 'checkboxes' === $type ) {
						$choices = get_post_meta( $field_id, 'ccf_attached_choices', true );

						if ( ! empty( $choices ) && is_array( $choices ) ) {
							$post_ids = array_merge( $post_ids, $choices );
						}
					}
				}
			}

			if ( ! empty( $post_ids ) ) {
				// SECURITY FIX: ensure all IDs are integers
				$post_ids = implode( ',', array_map( 'intval', $post_ids ) );

				$query = preg_replace( "#post_type.*=.*('|\").*?('|\")#i", "ID in ({$post_ids}) ", $query );
			}
		}

		return $query;
	}

	/**
	 * @since 6.5
	 */
	public function action_handle_export() {
		if ( ! empty( $_GET['post'] ) && ! empty( $_GET['export'] ) && isset( $_GET['nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['nonce'] ) ), 'ccf_form_export' ) ) {
			if ( ! current_user_can( 'manage_options' ) ) {
				return;
			}

			require_once( ABSPATH . 'wp-admin/includes/export.php' );

			add_filter( 'query', array( $this, 'filter_query' ) );
			export_wp( array( 'content' => 'ccf_form' ) );

			exit;
		}
	}

	/**
	 * @since 6.5
	 */
	public function action_rss2_head() {
		if ( isset( $_GET['content'] ) && 'ccf_form' === $_GET['content'] && defined( 'WXR_VERSION' ) && WXR_VERSION ) {
			global $wp_post_types;

			if ( false !== $this->old_post_types ) {
				$wp_post_types = $this->old_post_types;
			}
		}
	}

	/**
	 * @param array $args
	 * @since 6.5
	 * @return array
	 */
	public function filter_export_args( $args ) {
		if ( isset( $_GET['content'] ) && 'ccf_form' === $_GET['content'] && defined( 'WXR_VERSION' ) && WXR_VERSION ) {
			$args['content'] = 'all';

			global $wp_post_types;
			$this->old_post_types = $wp_post_types;

			$ccf_post_types = array( 'ccf_form', 'ccf_field', 'ccf_choice', 'ccf_submission' );

			foreach ( $wp_post_types as $slug => $post_type ) {
				if ( ! in_array( $slug, $ccf_post_types, true ) ) {
					$this->old_post_types[ $slug ] = clone $post_type;
					$post_type->can_export = false;
				}
			}
		}

		return $args;
	}

	/**
	 * @since 6.5
	 * @return CCF_Export
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
