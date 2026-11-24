<?php
/**
 * A simple front-end login form: a block and the [ccf_login] shortcode.
 *
 * The form posts to wp-login.php like the normal WordPress login, so
 * security plugins that watch logins keep working. When a login fails, the
 * visitor is sent back to the page they came from with a short message
 * instead of the WordPress login screen.
 *
 * @package Custom_Contact_Forms
 * @since   7.21.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CCF_Login_Form {

	public function setup() {
		add_shortcode( 'ccf_login', array( $this, 'shortcode' ) );
		add_action( 'init', array( $this, 'register_block' ), 20 );
		add_action( 'wp_login_failed', array( $this, 'login_failed' ), 999, 2 );
		add_filter( 'authenticate', array( $this, 'empty_fields' ), 9999, 3 );
	}

	/**
	 * Default settings, shared by the block and the shortcode.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'title'            => '',
			'redirect'         => '',
			'style'            => 'match',
			'buttonText'       => '',
			'showRemember'     => true,
			'showLostPassword' => true,
			'showRegister'     => true,
		);
	}

	/**
	 * Was this request sent by our login form?
	 *
	 * @return bool
	 */
	private function is_our_login() {
		// The login form has no nonce, the same as wp-login.php.
		return ! empty( $_POST['ccf_login'] ) && ! empty( $_POST['ccf_login_return'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	}

	/**
	 * Send the visitor back to the page with the form.
	 *
	 * @param string $reason failed or empty.
	 */
	private function back_to_form( $reason ) {
		$return = esc_url_raw( wp_unslash( $_POST['ccf_login_return'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$return = wp_validate_redirect( $return, home_url( '/' ) );
		$return = remove_query_arg( array( 'ccf_login', 'loggedout' ), $return );
		$return = add_query_arg( 'ccf_login', $reason, $return );

		wp_safe_redirect( $return . '#ccf-login' );
		exit;
	}

	/**
	 * A wrong username or password. Runs after other plugins have counted
	 * the failed attempt.
	 */
	public function login_failed( $username = '', $error = null ) {
		if ( ! $this->is_our_login() ) {
			return;
		}

		// An account waiting for its email address to be confirmed.
		if ( is_wp_error( $error ) && in_array( 'unconfirmed_email', $error->get_error_codes(), true ) ) {
			$this->back_to_form( 'unconfirmed' );
		}

		$this->back_to_form( 'failed' );
	}

	/**
	 * WordPress does not count empty fields as a failed login, so they are
	 * caught here.
	 *
	 * @param WP_User|WP_Error|null $user     Result so far.
	 * @param string                $username Username.
	 * @param string                $password Password.
	 * @return WP_User|WP_Error|null
	 */
	public function empty_fields( $user, $username, $password ) {
		if ( ! is_wp_error( $user ) || ! $this->is_our_login() ) {
			return $user;
		}

		$codes = $user->get_error_codes();

		if ( in_array( 'empty_username', $codes, true ) || in_array( 'empty_password', $codes, true ) ) {
			$this->back_to_form( 'empty' );
		}

		return $user;
	}

	/**
	 * The address of the page being viewed, without our status flag.
	 *
	 * @return string
	 */
	private function current_url() {
		if ( empty( $_SERVER['HTTP_HOST'] ) || empty( $_SERVER['REQUEST_URI'] ) ) {
			return home_url( '/' );
		}

		$url = set_url_scheme( 'http://' . sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) . esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) );

		return remove_query_arg( array( 'ccf_login', 'loggedout' ), $url );
	}

	/**
	 * Load the form styles, even on sites that limit where form files load.
	 *
	 * @param string $style Style slug.
	 */
	private function enqueue( $style ) {
		if ( ! wp_style_is( 'ccf-modern', 'registered' ) ) {
			wp_register_style( 'ccf-modern', plugins_url( '/build/css/ccf-modern.css', dirname( __FILE__ ) ), array(), CCF_VERSION );
		}

		wp_enqueue_style( 'ccf-modern' );
		CCF_Form_Styles::factory()->enqueue( $style );
	}

	/**
	 * Build the form.
	 *
	 * @param array $args Settings, see defaults().
	 * @return string HTML.
	 */
	public function render( $args ) {
		$args  = wp_parse_args( $args, self::defaults() );
		$style = sanitize_key( $args['style'] );

		if ( ! in_array( $style, array( 'match', 'clean', 'rounded', 'bold', 'light', 'dark', 'minimal', 'none' ), true ) ) {
			$style = 'match';
		}

		$this->enqueue( $style );

		$styles     = CCF_Form_Styles::factory();
		$is_preset  = $styles->is_preset( $style );
		$vars       = $is_preset ? $styles->inline_vars( 0, $style ) : '';
		$detect     = 'match' === $style && false === strpos( $vars, '--ccf-accent:' );
		$here       = $this->current_url();
		$redirect   = '' !== trim( $args['redirect'] ) ? esc_url_raw( $args['redirect'] ) : $here;
		$button     = '' !== trim( $args['buttonText'] ) ? $args['buttonText'] : __( 'Log In', 'custom-contact-forms' );
		$id         = 'ccf-login-' . wp_unique_id();
		$wrap_class = 'ccf-form-wrapper ccf-login-wrapper' . ( $is_preset ? ' ccf-style-' . $style : '' );

		// The block editor preview always shows the form, even though the
		// person editing is logged in.
		$preview = defined( 'REST_REQUEST' ) && REST_REQUEST;

		ob_start();
		?>
		<div id="ccf-login" class="<?php echo esc_attr( $wrap_class ); ?>"<?php if ( $vars ) : ?> style="<?php echo esc_attr( $vars ); ?>"<?php endif; ?><?php if ( $detect ) : ?> data-ccf-match="1"<?php endif; ?>>
			<?php if ( is_user_logged_in() && ! $preview ) : ?>
				<?php $user = wp_get_current_user(); ?>
				<div class="ccf-logged-in">
					<?php
					printf(
						/* translators: %s: the user's display name */
						esc_html__( 'You are logged in as %s.', 'custom-contact-forms' ),
						'<strong>' . esc_html( $user->display_name ) . '</strong>'
					);
					?>
					<a href="<?php echo esc_url( wp_logout_url( $here ) ); ?>"><?php esc_html_e( 'Log out', 'custom-contact-forms' ); ?></a>
				</div>
			<?php else : ?>
				<form class="ccf-form ccf-login-form ccf-theme-<?php echo esc_attr( $style ); ?>" method="post" action="<?php echo esc_url( site_url( 'wp-login.php', 'login_post' ) ); ?>">
					<?php if ( '' !== trim( $args['title'] ) ) : ?>
						<div class="form-title"><?php echo esc_html( $args['title'] ); ?></div>
					<?php endif; ?>

					<?php echo wp_kses_post( $this->notice() ); ?>

					<div class="field field-type-single-line-text">
						<label class="main-label" for="<?php echo esc_attr( $id ); ?>-user"><?php esc_html_e( 'Username or email', 'custom-contact-forms' ); ?></label>
						<input type="text" class="field-input" name="log" id="<?php echo esc_attr( $id ); ?>-user" autocomplete="username" autocapitalize="off" required>
					</div>

					<div class="field field-type-single-line-text">
						<label class="main-label" for="<?php echo esc_attr( $id ); ?>-pass"><?php esc_html_e( 'Password', 'custom-contact-forms' ); ?></label>
						<input type="password" class="field-input" name="pwd" id="<?php echo esc_attr( $id ); ?>-pass" autocomplete="current-password" required>
					</div>

					<?php if ( $args['showRemember'] ) : ?>
						<div class="field ccf-login-remember">
							<label><input type="checkbox" name="rememberme" value="forever"> <?php esc_html_e( 'Remember me', 'custom-contact-forms' ); ?></label>
						</div>
					<?php endif; ?>

					<?php
					/**
					 * Add fields to the login form, for example a captcha.
					 *
					 * @since 7.21.0
					 * @param string $html Extra HTML.
					 * @param array  $args Form settings.
					 */
					echo apply_filters( 'ccf_login_form_extra_fields', '', $args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					?>

					<div class="form-submit">
						<input type="submit" class="btn btn-primary ccf-submit-button<?php echo 'match' === $style ? ' wp-element-button' : ''; ?>" value="<?php echo esc_attr( $button ); ?>">
					</div>

					<input type="hidden" name="redirect_to" value="<?php echo esc_url( $redirect ); ?>">
					<input type="hidden" name="ccf_login" value="1">
					<input type="hidden" name="ccf_login_return" value="<?php echo esc_url( $here ); ?>">

					<?php
					$links = array();

					if ( $args['showLostPassword'] ) {
						$links[] = '<a href="' . esc_url( wp_lostpassword_url( $here ) ) . '">' . esc_html__( 'Forgot your password?', 'custom-contact-forms' ) . '</a>';
					}

					if ( $args['showRegister'] ) {
						/**
						 * The "Create an account" link. Empty hides it. By default it
						 * shows when Settings > General > Anyone can register is on.
						 *
						 * @since 7.21.0
						 * @param string $url Registration URL.
						 */
						$register = apply_filters( 'ccf_login_register_url', get_option( 'users_can_register' ) ? wp_registration_url() : '' );

						if ( $register ) {
							$links[] = '<a href="' . esc_url( $register ) . '">' . esc_html__( 'Create an account', 'custom-contact-forms' ) . '</a>';
						}
					}
					?>

					<?php if ( $links ) : ?>
						<div class="ccf-login-links"><?php echo wp_kses_post( implode( '', $links ) ); ?></div>
					<?php endif; ?>
				</form>
			<?php endif; ?>
		</div>
		<?php

		return ob_get_clean();
	}

	/**
	 * Message after a failed login.
	 *
	 * @return string HTML.
	 */
	private function notice() {
		$status = isset( $_GET['ccf_login'] ) ? sanitize_key( wp_unslash( $_GET['ccf_login'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( 'failed' === $status ) {
			$text = __( 'The username or password is not correct. Please try again.', 'custom-contact-forms' );
		} elseif ( 'empty' === $status ) {
			$text = __( 'Please enter your username and password.', 'custom-contact-forms' );
		} elseif ( 'unconfirmed' === $status ) {
			$text = __( 'Please confirm your email address first. Check your inbox for the link we sent you.', 'custom-contact-forms' );
		} else {
			return '';
		}

		return '<div class="ccf-login-notice is-error" role="alert">' . esc_html( $text ) . '</div>';
	}

	/**
	 * [ccf_login redirect="/my-account/" style="clean" title="Log in"]
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'title'         => '',
				'redirect'      => '',
				'style'         => 'match',
				'button'        => '',
				'remember'      => 'yes',
				'lost_password' => 'yes',
				'register'      => 'yes',
			),
			$atts,
			'ccf_login'
		);

		$yes = function( $value ) {
			return ! in_array( strtolower( (string) $value ), array( 'no', '0', 'false', 'off' ), true );
		};

		return $this->render(
			array(
				'title'            => $atts['title'],
				'redirect'         => $atts['redirect'],
				'style'            => $atts['style'],
				'buttonText'       => $atts['button'],
				'showRemember'     => $yes( $atts['remember'] ),
				'showLostPassword' => $yes( $atts['lost_password'] ),
				'showRegister'     => $yes( $atts['register'] ),
			)
		);
	}

	/**
	 * Register the Login Form block.
	 */
	public function register_block() {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		$deps = array( 'wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-i18n' );

		if ( wp_script_is( 'wp-server-side-render', 'registered' ) ) {
			$deps[] = 'wp-server-side-render';
		}

		wp_register_script( 'ccf-login-block', plugins_url( '/assets/js/ccf-login-block.js', dirname( __FILE__ ) ), $deps, CCF_VERSION, true );

		/**
		 * Show the CCF Pro note in the block settings.
		 *
		 * @since 7.21.0
		 * @param bool $show Show it.
		 */
		$teaser = apply_filters( 'ccf_login_show_pro_teaser', ! defined( 'CCFP_VERSION' ) );

		wp_localize_script(
			'ccf-login-block',
			'ccfLoginBlock',
			array(
				'canRegister' => (bool) get_option( 'users_can_register' ),
				'settingsUrl' => admin_url( 'options-general.php' ),
				'proUrl'      => $teaser ? ccf_pro_url( 'login-block', '/pricing/' ) : '',
			)
		);

		if ( ! wp_style_is( 'ccf-modern-editor', 'registered' ) ) {
			return;
		}

		$defaults   = self::defaults();
		$attributes = array();

		foreach ( $defaults as $key => $value ) {
			$attributes[ $key ] = array(
				'type'    => is_bool( $value ) ? 'boolean' : 'string',
				'default' => $value,
			);
		}

		register_block_type(
			'ccf/login-form',
			array(
				'editor_script'   => 'ccf-login-block',
				'editor_style'    => 'ccf-modern-editor',
				'attributes'      => $attributes,
				'render_callback' => array( $this, 'render_block' ),
			)
		);
	}

	/**
	 * Block render callback.
	 *
	 * @param array $attributes Block attributes.
	 * @return string
	 */
	public function render_block( $attributes ) {
		$args = array();

		foreach ( self::defaults() as $key => $default ) {
			$args[ $key ] = isset( $attributes[ $key ] ) ? $attributes[ $key ] : $default;
		}

		$args['title']      = sanitize_text_field( (string) $args['title'] );
		$args['buttonText'] = sanitize_text_field( (string) $args['buttonText'] );

		return $this->render( $args );
	}

	/**
	 * @return CCF_Login_Form
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
