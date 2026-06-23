<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Gutenberg block support for Custom Contact Forms.
 *
 * Registers a dynamic block that allows users to select and insert
 * existing CCF forms into the block editor. Uses inline JS to avoid
 * file path resolution issues across different server configurations.
 *
 * @since 7.9.0
 */
class CCF_Gutenberg_Block {

	public function __construct() {}

	/**
	 * Setup hooks
	 *
	 * @since 7.9.0
	 */
	public function setup() {
		add_action( 'init', array( $this, 'register_block' ) );
	}

	/**
	 * Register the block type and its editor script
	 *
	 * @since 7.9.0
	 */
	public function register_block() {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		$deps = array( 'wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-i18n' );

		// wp-server-side-render was split out in WP 5.3
		if ( wp_script_is( 'wp-server-side-render', 'registered' ) ) {
			$deps[] = 'wp-server-side-render';
		}

		// Register block editor JS
		wp_register_script( 'ccf-gutenberg-block', plugins_url( '/assets/js/ccf-block-editor.js', dirname( __FILE__ ) ), $deps, CCF_VERSION, true );

		// Register form styles for the editor preview
		$css_form_path = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG )
			? '/assets/build/css/form.css'
			: '/assets/build/css/form.min.css';

		wp_register_style(
			'ccf-form-editor',
			plugins_url( $css_form_path, dirname( __FILE__ ) ),
			array(),
			CCF_VERSION
		);

		wp_register_style(
			'ccf-modern-editor',
			plugins_url( '/build/css/ccf-modern.css', dirname( __FILE__ ) ),
			array( 'ccf-form-editor' ),
			CCF_VERSION
		);

		// Get all published forms for the dropdown
		$forms = get_posts( array(
			'post_type'      => 'ccf_form',
			'post_status'    => 'publish',
			'posts_per_page' => 200,
			'orderby'        => 'title',
			'order'          => 'ASC',
		) );

		$form_options = array();
		foreach ( $forms as $form ) {
			$title = get_the_title( $form->ID );
			if ( empty( $title ) ) {
				/* translators: %d: form ID */
				$title = sprintf( __( '(Untitled form #%d)', 'custom-contact-forms' ), $form->ID );
			}
			$form_options[] = array(
				'value' => $form->ID,
				'label' => $title,
			);
		}

		wp_localize_script( 'ccf-gutenberg-block', 'ccfBlockData', array(
			'forms'            => $form_options,
			'adminUrl'         => esc_url( admin_url( 'edit.php?post_type=ccf_form' ) ),
			'noFormsText'      => esc_html__( 'No forms found. Create a form first.', 'custom-contact-forms' ),
			'selectText'       => esc_html__( 'Select a form', 'custom-contact-forms' ),
			'blockTitle'       => esc_html__( 'Contact Form (CCF)', 'custom-contact-forms' ),
			'blockDesc'        => esc_html__( 'Display an existing Custom Contact Form.', 'custom-contact-forms' ),
			'settingsLabel'    => esc_html__( 'Form Settings', 'custom-contact-forms' ),
			'showTitleLabel'   => esc_html__( 'Show Title', 'custom-contact-forms' ),
			'showDescLabel'    => esc_html__( 'Show Description', 'custom-contact-forms' ),
			'themeLabel'       => esc_html__( 'Theme', 'custom-contact-forms' ),
			'editFormText'     => esc_html__( 'Edit Form', 'custom-contact-forms' ),
			'editFormUrl'      => esc_url( admin_url( 'post.php?action=edit&post=' ) ),
		) );

		register_block_type( 'ccf/form-block', array(
			'editor_script'   => 'ccf-gutenberg-block',
			'editor_style'    => 'ccf-modern-editor',
			'style'           => 'ccf-modern-editor',
			'attributes'      => array(
				'formId' => array(
					'type'    => 'number',
					'default' => 0,
				),
				'showTitle' => array(
					'type'    => 'boolean',
					'default' => true,
				),
				'showDescription' => array(
					'type'    => 'boolean',
					'default' => true,
				),
				'theme' => array(
					'type'    => 'string',
					'default' => '',
				),
			),
			'render_callback' => array( $this, 'render_block' ),
		) );
	}

	/**
	 * Server-side render callback
	 *
	 * @param array $attributes Block attributes.
	 * @since 7.9.0
	 * @return string
	 */
	public function render_block( $attributes ) {
		$form_id = isset( $attributes['formId'] ) ? (int) $attributes['formId'] : 0;

		if ( $form_id <= 0 ) {
			return '';
		}

		$form = get_post( $form_id );
		if ( empty( $form ) || 'ccf_form' !== $form->post_type || 'publish' !== $form->post_status ) {
			return '';
		}

		$show_title = isset( $attributes['showTitle'] ) ? $attributes['showTitle'] : true;
		$show_desc  = isset( $attributes['showDescription'] ) ? $attributes['showDescription'] : true;
		$theme      = isset( $attributes['theme'] ) && ! empty( $attributes['theme'] ) ? $attributes['theme'] : '';

		// Override title visibility via filter
		if ( ! $show_title ) {
			$hide_title_callback = function( $show, $id ) use ( $form_id ) {
				return ( $id === $form_id ) ? false : $show;
			};
			add_filter( 'ccf_show_form_title', $hide_title_callback, 20, 2 );
		}

		// Override description visibility via filter
		if ( ! $show_desc ) {
			$hide_desc_callback = function( $show, $id ) use ( $form_id ) {
				return ( $id === $form_id ) ? false : $show;
			};
			add_filter( 'ccf_show_form_description', $hide_desc_callback, 20, 2 );
		}

		// Override theme via filter on the post meta
		if ( ! empty( $theme ) ) {
			$theme_callback = function( $value, $post_id, $meta_key, $single ) use ( $form_id, $theme ) {
				if ( (int) $post_id === $form_id && 'ccf_form_theme' === $meta_key && $single ) {
					return $theme;
				}
				return $value;
			};
			add_filter( 'get_post_metadata', $theme_callback, 20, 4 );
		}

		$output = CCF_Form_Renderer::factory()->get_rendered_form( $form_id );

		// Clean up filters
		if ( ! $show_title ) {
			remove_filter( 'ccf_show_form_title', $hide_title_callback, 20 );
		}
		if ( ! $show_desc ) {
			remove_filter( 'ccf_show_form_description', $hide_desc_callback, 20 );
		}
		if ( ! empty( $theme ) ) {
			remove_filter( 'get_post_metadata', $theme_callback, 20 );
		}

		return $output;
	}

	/**
	 * @since 7.9.0
	 * @return CCF_Gutenberg_Block
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
