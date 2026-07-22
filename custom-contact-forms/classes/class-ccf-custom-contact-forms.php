<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CCF_Custom_Contact_Forms {

	public function __construct() {}

	/**
	 * Setup general plugin stuff. Load API
	 *
	 * @since 6.0
	 */
	public function setup() {
		add_action( 'rest_api_init', array( $this, 'api_init' ), 1000 );
		add_action( 'plugins_loaded', array( $this, 'register_api_scripts' ), 1000 );
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
		add_filter( 'plugin_action_links', array( $this, 'filter_plugin_action_links' ), 10, 2 );
		add_action( 'admin_notices', array( $this, 'permalink_warning' ) );
		add_action( 'registered_post_type', array( $this, 'make_post_types_public' ), 11, 2 );
		add_action( 'admin_init', array( $this, 'flush_rewrites' ), 10000 );
	}

	/**
	 * Trick API into thinking non publically queryable post types are queryable
	 *
	 * @param string $post_type
	 * @param object $args
	 * @since 6.8.1
	 */
	public function make_post_types_public( $post_type, $args ) {
		global $wp_post_types;

		$json_post_types = array( 'ccf_form', 'ccf_submission' );

		if ( in_array( $post_type, $json_post_types, true ) && isset( $wp_post_types[ $post_type ] ) ) {
			$wp_post_types[ $post_type ]->show_in_rest = true;
		}
	}

	/**
	 * Flush rewrites if necessary
	 *
	 * @since 6.0
	 */
	public function flush_rewrites() {
		$flush_rewrites = get_option( 'ccf_flush_rewrites' );

		if ( ! empty( $flush_rewrites ) ) {
			add_action( 'shutdown', 'flush_rewrite_rules' );
			delete_option( 'ccf_flush_rewrites' );
		}
	}

	/**
	 * Output permalink warning
	 *
	 * @since 6.0
	 */
	public function permalink_warning() {
		$permalink_structure = get_option( 'permalink_structure' );

		if ( empty( $permalink_structure ) ) {
			?>
			<div class="notice notice-warning">
				<p>
					<?php
					printf(
						wp_kses(
							/* translators: %s: permalink settings URL */
							__( 'Custom Contact Forms will not work unless pretty permalinks (not default) are enabled. Please update your <a href="%s">permalinks settings</a>.', 'custom-contact-forms' ),
							array( 'a' => array( 'href' => array() ) )
						),
						esc_url( admin_url( 'options-permalink.php' ) )
					);
					?>
				</p>
			</div>
		<?php
		}
	}

	/**
	 * Add forms and form submissions link to plugin actions
	 *
	 * @param array  $plugin_actions
	 * @param string $plugin_file
	 * @since 6.1.4
	 * @return array
	 */
	public function filter_plugin_action_links( $plugin_actions, $plugin_file ) {
		$new_actions = array();

		if ( basename( plugin_dir_path( dirname( __FILE__ ) ) ) . '/custom-contact-forms.php' === $plugin_file ) {
			$new_actions['ccf_forms'] = sprintf(
				'<a href="%s">%s</a>',
				esc_url( admin_url( 'edit.php?post_type=ccf_form' ) ),
				esc_html__( 'Forms and Submissions', 'custom-contact-forms' )
			);

			if ( ! defined( 'CCFP_VERSION' ) ) {
				$new_actions['ccf_get_pro'] = sprintf(
					'<a href="%s" target="_blank" rel="noopener" style="color:#8659d6;font-weight:600;">%s</a>',
					esc_url( apply_filters( 'ccf_pro_upgrade_url', 'https://customformspro.com/' ) ),
					esc_html__( 'Get Pro', 'custom-contact-forms' )
				);
			}
		}

		return array_merge( $new_actions, $plugin_actions );
	}

	/**
	 * Load translation
	 *
	 * @since 6.0
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'custom-contact-forms', false, dirname( dirname( plugin_basename( __FILE__ ) ) ) . '/languages/' );
	}

	/**
	 * Register REST API scripts — WP Core now includes REST API since 4.7
	 *
	 * The vendored wp-api/ directory is no longer loaded. WordPress ships
	 * its own REST API and wp-api backbone client. We keep the JS client
	 * for the Backbone-based form builder.
	 *
	 * @since  7.9.0 — Replaces manually_load_api()
	 */
	public function register_api_scripts() {
		// WP Core REST API is available since 4.7 — no need to load vendored copy
		add_action( 'wp_enqueue_scripts', array( $this, 'rest_register_scripts_manual' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'rest_register_scripts_manual' ) );
	}

	/**
	 * Register REST API client scripts for Backbone builder
	 *
	 * @since  7.8.3
	 */
	public function rest_register_scripts_manual() {
		wp_enqueue_script( 'ccf-rest-api', plugins_url( '/assets/js/wp-api.js', dirname( __FILE__ ) ), array( 'jquery', 'backbone', 'underscore' ), CCF_VERSION, true );

		$settings = array( 'root' => esc_url_raw( get_rest_url() ), 'nonce' => wp_create_nonce( 'wp_rest' ) );
		wp_localize_script( 'ccf-rest-api', 'CCF_API_Settings', $settings );
	}

	/**
	 * Initialize REST API routes.
	 *
	 * @param WP_REST_Server $server
	 * @since 6.0
	 */
	public function api_init( $server = null ) {
		require_once dirname( __FILE__ ) . '/class-ccf-api-form-controller.php';

		$form_controller = new CCF_API_Form_Controller();
		$form_controller->register_routes();
	}

	/**
	 * @since 6.0
	 * @return CCF_Custom_Contact_Forms
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
