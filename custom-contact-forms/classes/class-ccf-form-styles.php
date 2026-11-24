<?php
/**
 * Form style presets: Match my site, Clean, Rounded and Bold, plus an
 * optional accent color per form.
 *
 * The presets are extra choices in the existing Form Theme setting, so old
 * forms keep the look they have. "Match my site" takes its colors and button
 * shape from the theme's theme.json when it has one, and otherwise from the
 * page itself in the browser.
 *
 * @package Custom_Contact_Forms
 * @since   7.21.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CCF_Form_Styles {

	const ACCENT_META = 'ccf_form_accent';

	/**
	 * Presets added by this class, as theme slug => label.
	 *
	 * @return array
	 */
	public static function presets() {
		return array(
			'match'   => __( 'Match my site', 'custom-contact-forms' ),
			'clean'   => __( 'Clean', 'custom-contact-forms' ),
			'rounded' => __( 'Rounded', 'custom-contact-forms' ),
			'bold'    => __( 'Bold', 'custom-contact-forms' ),
		);
	}

	public function setup() {
		add_action( 'init', array( $this, 'register' ), 5 );
		add_action( 'ccf_save_form', array( $this, 'save_form' ), 10, 2 );
		add_filter( 'ccf_form_response', array( $this, 'form_response' ), 10, 2 );
	}

	/**
	 * Register the preset stylesheet and the "Match my site" helper.
	 */
	public function register() {
		if ( wp_style_is( 'ccf-styles', 'registered' ) ) {
			return;
		}

		wp_register_style( 'ccf-styles', plugins_url( '/build/css/ccf-styles.css', dirname( __FILE__ ) ), array(), CCF_VERSION );
		wp_register_script( 'ccf-styles-match', plugins_url( '/assets/js/ccf-styles-match.js', dirname( __FILE__ ) ), array(), CCF_VERSION, true );
	}

	/**
	 * Load the preset styles, plus the "Match my site" helper when needed.
	 *
	 * The preset rules are more specific than the base form styles, so it
	 * does not matter which stylesheet prints first.
	 *
	 * @param string $theme Form theme slug.
	 */
	public function enqueue( $theme = '' ) {
		$this->register();

		wp_enqueue_style( 'ccf-styles' );

		if ( 'match' === $theme && ! is_admin() && ! ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			wp_enqueue_script( 'ccf-styles-match' );
		}
	}

	/**
	 * Is this one of the presets from this class?
	 *
	 * @param string $theme Theme slug.
	 * @return bool
	 */
	public function is_preset( $theme ) {
		return array_key_exists( (string) $theme, self::presets() );
	}

	/**
	 * A form's custom accent color, or an empty string.
	 *
	 * @param int $form_id Form ID.
	 * @return string
	 */
	public function accent( $form_id ) {
		$color = sanitize_hex_color( (string) get_post_meta( $form_id, self::ACCENT_META, true ) );

		return $color ? $color : '';
	}

	/**
	 * Turn a theme.json value such as "var:preset|color|primary" into CSS.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	private function css_value( $value ) {
		if ( ! is_string( $value ) || '' === trim( $value ) ) {
			return '';
		}

		$value = trim( $value );

		if ( 0 === strpos( $value, 'var:' ) ) {
			$parts = explode( '|', substr( $value, 4 ) );
			$parts = array_map( 'sanitize_key', $parts );
			return 'var(--wp--' . implode( '--', $parts ) . ')';
		}

		// Only allow simple, safe CSS values.
		if ( preg_match( '/^[#a-zA-Z0-9(),.%\s\-]+$/', $value ) ) {
			return $value;
		}

		return '';
	}

	/**
	 * Colors and button shape from the active theme's theme.json.
	 *
	 * @return array CSS custom properties, name => value.
	 */
	public function theme_vars() {
		static $vars = null;

		if ( null !== $vars ) {
			return $vars;
		}

		$vars = array();

		if ( ! function_exists( 'wp_get_global_styles' ) || ! function_exists( 'wp_theme_has_theme_json' ) || ! wp_theme_has_theme_json() ) {
			return $vars;
		}

		$button = wp_get_global_styles( array( 'elements', 'button' ) );

		if ( is_array( $button ) ) {
			$bg     = isset( $button['color']['background'] ) ? $this->css_value( $button['color']['background'] ) : '';
			$text   = isset( $button['color']['text'] ) ? $this->css_value( $button['color']['text'] ) : '';
			$radius = isset( $button['border']['radius'] ) ? $button['border']['radius'] : '';

			if ( is_array( $radius ) ) {
				$radius = isset( $radius['topLeft'] ) ? $radius['topLeft'] : '';
			}

			$radius = $this->css_value( $radius );

			if ( $bg ) {
				$vars['--ccf-accent'] = $bg;
			}
			if ( $text ) {
				$vars['--ccf-accent-text'] = $text;
			}
			if ( $radius ) {
				$vars['--ccf-button-radius'] = $radius;
			}
		}

		// No button color in theme.json: use the theme's main palette color.
		if ( empty( $vars['--ccf-accent'] ) && function_exists( 'wp_get_global_settings' ) ) {
			$palette = wp_get_global_settings( array( 'color', 'palette', 'theme' ) );

			if ( is_array( $palette ) ) {
				$slugs = wp_list_pluck( $palette, 'slug' );

				foreach ( array( 'primary', 'accent', 'accent-1', 'vivid-cyan-blue', 'contrast' ) as $slug ) {
					if ( in_array( $slug, $slugs, true ) ) {
						$vars['--ccf-accent'] = 'var(--wp--preset--color--' . $slug . ')';
						break;
					}
				}
			}
		}

		return $vars;
	}

	/**
	 * Inline style attribute value for a form's wrapper.
	 *
	 * @param int    $form_id Form ID.
	 * @param string $theme   Form theme slug.
	 * @return string
	 */
	public function inline_vars( $form_id, $theme ) {
		if ( ! $this->is_preset( $theme ) ) {
			return '';
		}

		$vars = 'match' === $theme ? $this->theme_vars() : array();

		$accent = $this->accent( $form_id );

		if ( $accent ) {
			$vars['--ccf-accent']      = $accent;
			$vars['--ccf-accent-text'] = $this->text_on( $accent );
		}

		$css = '';

		foreach ( $vars as $name => $value ) {
			$css .= $name . ':' . $value . ';';
		}

		return $css;
	}

	/**
	 * Black or white, whichever reads better on a hex color.
	 *
	 * @param string $hex Hex color.
	 * @return string
	 */
	public function text_on( $hex ) {
		$hex = ltrim( $hex, '#' );

		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}

		$r = hexdec( substr( $hex, 0, 2 ) );
		$g = hexdec( substr( $hex, 2, 2 ) );
		$b = hexdec( substr( $hex, 4, 2 ) );

		$luminance = ( 0.299 * $r + 0.587 * $g + 0.114 * $b ) / 255;

		return $luminance > 0.6 ? '#111111' : '#ffffff';
	}

	/**
	 * Save the accent color sent by the builder.
	 *
	 * @param int   $form_id Form ID.
	 * @param array $data    Request data.
	 */
	public function save_form( $form_id, $data ) {
		if ( ! array_key_exists( 'accentColor', $data ) ) {
			return;
		}

		$color = sanitize_hex_color( (string) $data['accentColor'] );

		if ( $color ) {
			update_post_meta( $form_id, self::ACCENT_META, $color );
		} else {
			delete_post_meta( $form_id, self::ACCENT_META );
		}
	}

	/**
	 * Add the accent color to the form data the builder loads.
	 *
	 * @param array   $data Form data.
	 * @param WP_Post $item Form post.
	 * @return array
	 */
	public function form_response( $data, $item ) {
		$data['accentColor'] = $this->accent( $item->ID );

		return $data;
	}

	/**
	 * @return CCF_Form_Styles
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
