<?php
/**
 * Forms > Our Plugins: our other free plugins on WordPress.org, with
 * Install and Activate buttons.
 *
 * Plugins are only ever installed from WordPress.org, by an administrator
 * who clicks the button. Nothing is installed, activated or loaded on its
 * own, and the page makes no outside requests.
 *
 * @package Custom_Contact_Forms
 * @since   7.21.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CCF_Our_Plugins {

	const SLUG = 'ccf-our-plugins';

	/**
	 * @var string
	 */
	private $hook = '';

	public function setup() {
		add_action( 'admin_menu', array( $this, 'menu' ), 100 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'wp_ajax_ccf_activate_our_plugin', array( $this, 'ajax_activate' ) );
	}

	/**
	 * The plugins we list.
	 *
	 * @return array
	 */
	public function plugins() {
		$plugins = array(
			'rehearsa' => array(
				'name'         => 'Rehearsa Backup Tester',
				'tagline'      => __( 'Test your backup without touching your live site.', 'custom-contact-forms' ),
				'description'  => __( 'Rehearsa checks your UpdraftPlus backups, or a backup file from your computer, then test-boots the backup in a private copy that runs in your browser. You find out whether a backup really works before the day you need it.', 'custom-contact-forms' ),
				'points'       => array(
					__( 'Checks the files, the database and the archive checksums', 'custom-contact-forms' ),
					__( 'Boots the backup in a private test copy in your browser', 'custom-contact-forms' ),
					__( 'Shows the exact error if a plugin in the backup would break your site', 'custom-contact-forms' ),
					__( 'Your live site is never touched', 'custom-contact-forms' ),
				),
				'icon'         => plugins_url( '/assets/images/rehearsa-icon.png', dirname( __FILE__ ) ),
				'requires'     => '6.5',
				'requires_php' => '7.4',
				'menu_match'   => 'rehearsa',
				'fallback_url' => admin_url( 'tools.php' ),
			),
		);

		/**
		 * Filters the plugins listed under Forms > Our Plugins.
		 *
		 * @since 7.21.0
		 * @param array $plugins Plugins keyed by their WordPress.org slug.
		 */
		return apply_filters( 'ccf_our_plugins', $plugins );
	}

	public function menu() {
		$this->hook = add_submenu_page(
			'edit.php?post_type=ccf_form',
			esc_html__( 'Our Plugins', 'custom-contact-forms' ),
			esc_html__( 'Our Plugins', 'custom-contact-forms' ),
			'manage_options',
			self::SLUG,
			array( $this, 'render' )
		);
	}

	/**
	 * @param string $hook Current admin page.
	 */
	public function enqueue( $hook ) {
		if ( $hook !== $this->hook ) {
			return;
		}

		wp_enqueue_style( 'ccf-our-plugins', plugins_url( '/build/css/ccf-our-plugins.css', dirname( __FILE__ ) ), array(), CCF_VERSION );

		$deps = array( 'jquery' );
		if ( current_user_can( 'install_plugins' ) ) {
			$deps[] = 'updates';
		}

		wp_enqueue_script( 'ccf-our-plugins', plugins_url( '/assets/js/ccf-our-plugins.js', dirname( __FILE__ ) ), $deps, CCF_VERSION, true );
		wp_localize_script(
			'ccf-our-plugins',
			'ccfOurPlugins',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'ccf_our_plugins' ),
				'i18n'    => array(
					'installing' => __( 'Installing...', 'custom-contact-forms' ),
					'activating' => __( 'Activating...', 'custom-contact-forms' ),
					'activate'   => __( 'Activate', 'custom-contact-forms' ),
					'failed'     => __( 'That did not work. You can install it from Plugins > Add New Plugin instead.', 'custom-contact-forms' ),
					'alsoManual' => __( 'You can also install it from Plugins > Add New Plugin.', 'custom-contact-forms' ),
				),
			)
		);
	}

	/**
	 * Main file of an installed plugin, found by its folder name.
	 *
	 * @param string $slug WordPress.org slug.
	 * @return string Empty when it is not installed.
	 */
	private function plugin_file( $slug ) {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		foreach ( array_keys( get_plugins() ) as $file ) {
			if ( 0 === strpos( $file, $slug . '/' ) ) {
				return $file;
			}
		}

		return '';
	}

	/**
	 * Address of an active plugin's own admin page, read from the admin menu.
	 *
	 * @param array $plugin Plugin details.
	 * @return string
	 */
	private function open_url( $plugin ) {
		global $submenu, $menu;

		$match = $plugin['menu_match'];

		if ( is_array( $submenu ) ) {
			foreach ( $submenu as $parent => $items ) {
				foreach ( (array) $items as $item ) {
					if ( isset( $item[2] ) && false !== strpos( (string) $item[2], $match ) ) {
						$url = menu_page_url( $item[2], false );
						if ( $url ) {
							return $url;
						}
					}
				}
			}
		}

		if ( is_array( $menu ) ) {
			foreach ( $menu as $item ) {
				if ( isset( $item[2] ) && false !== strpos( (string) $item[2], $match ) ) {
					$url = menu_page_url( $item[2], false );
					if ( $url ) {
						return $url;
					}
				}
			}
		}

		return $plugin['fallback_url'];
	}

	/**
	 * Whether this site meets a plugin's WordPress and PHP versions.
	 *
	 * @param array $plugin Plugin details.
	 * @return bool
	 */
	private function compatible( $plugin ) {
		return version_compare( get_bloginfo( 'version' ), $plugin['requires'], '>=' ) && version_compare( PHP_VERSION, $plugin['requires_php'], '>=' );
	}

	public function render() {
		?>
		<div class="wrap ccf-our-plugins">
			<h1><?php esc_html_e( 'Our Plugins', 'custom-contact-forms' ); ?></h1>
			<p class="ccf-our-plugins-intro"><?php esc_html_e( 'Free plugins from the team behind Custom Contact Forms. Each one installs from WordPress.org.', 'custom-contact-forms' ); ?></p>

			<div class="ccf-our-plugins-grid">
				<?php foreach ( $this->plugins() as $slug => $plugin ) : ?>
					<?php $this->render_card( $slug, $plugin ); ?>
				<?php endforeach; ?>
			</div>
		</div>
		<?php

		if ( current_user_can( 'install_plugins' ) && function_exists( 'wp_print_request_filesystem_credentials_modal' ) ) {
			wp_print_request_filesystem_credentials_modal();
			wp_print_admin_notice_templates();
		}
	}

	/**
	 * @param string $slug   WordPress.org slug.
	 * @param array  $plugin Plugin details.
	 */
	private function render_card( $slug, $plugin ) {
		$file      = $this->plugin_file( $slug );
		$active    = $file && is_plugin_active( $file );
		$wporg_url = 'https://wordpress.org/plugins/' . rawurlencode( $slug ) . '/';
		?>
		<div class="ccf-our-plugin" data-slug="<?php echo esc_attr( $slug ); ?>" data-file="<?php echo esc_attr( $file ); ?>">
			<div class="ccf-our-plugin-head">
				<img class="ccf-our-plugin-icon" src="<?php echo esc_url( $plugin['icon'] ); ?>" width="64" height="64" alt="" />
				<div>
					<h2><?php echo esc_html( $plugin['name'] ); ?> <span class="ccf-our-plugin-badge"><?php esc_html_e( 'Free', 'custom-contact-forms' ); ?></span></h2>
					<p class="ccf-our-plugin-tagline"><?php echo esc_html( $plugin['tagline'] ); ?></p>
				</div>
			</div>

			<p><?php echo esc_html( $plugin['description'] ); ?></p>

			<?php if ( ! empty( $plugin['points'] ) ) : ?>
				<ul class="ccf-our-plugin-points">
					<?php foreach ( $plugin['points'] as $point ) : ?>
						<li><?php echo esc_html( $point ); ?></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>

			<div class="ccf-our-plugin-actions">
				<?php if ( $active ) : ?>
					<span class="ccf-our-plugin-status"><?php esc_html_e( 'Active', 'custom-contact-forms' ); ?></span>
					<a class="button button-primary" href="<?php echo esc_url( $this->open_url( $plugin ) ); ?>">
						<?php
						/* translators: %s: plugin name */
						echo esc_html( sprintf( __( 'Open %s', 'custom-contact-forms' ), strtok( $plugin['name'], ' ' ) ) );
						?>
					</a>
				<?php elseif ( $file ) : ?>
					<?php if ( current_user_can( 'activate_plugin', $file ) ) : ?>
						<button type="button" class="button button-primary ccf-our-plugin-activate"><?php esc_html_e( 'Activate', 'custom-contact-forms' ); ?></button>
					<?php else : ?>
						<span class="ccf-our-plugin-status"><?php esc_html_e( 'Installed', 'custom-contact-forms' ); ?></span>
					<?php endif; ?>
				<?php elseif ( ! $this->compatible( $plugin ) ) : ?>
					<span class="ccf-our-plugin-note">
						<?php
						/* translators: 1: WordPress version, 2: PHP version */
						echo esc_html( sprintf( __( 'Needs WordPress %1$s and PHP %2$s or newer.', 'custom-contact-forms' ), $plugin['requires'], $plugin['requires_php'] ) );
						?>
					</span>
				<?php elseif ( current_user_can( 'install_plugins' ) ) : ?>
					<button type="button" class="button button-primary ccf-our-plugin-install"><?php esc_html_e( 'Install Now', 'custom-contact-forms' ); ?></button>
				<?php endif; ?>

				<a class="ccf-our-plugin-more" href="<?php echo esc_url( $wporg_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'View on WordPress.org', 'custom-contact-forms' ); ?></a>
			</div>

			<p class="ccf-our-plugin-error" role="alert" hidden></p>
		</div>
		<?php
	}

	/**
	 * Activates one of the listed plugins after it has been installed.
	 */
	public function ajax_activate() {
		check_ajax_referer( 'ccf_our_plugins', 'nonce' );

		$slug    = isset( $_POST['slug'] ) ? sanitize_key( wp_unslash( $_POST['slug'] ) ) : '';
		$plugins = $this->plugins();

		if ( ! isset( $plugins[ $slug ] ) ) {
			wp_send_json_error( array( 'message' => __( 'Unknown plugin.', 'custom-contact-forms' ) ), 400 );
		}

		$file = $this->plugin_file( $slug );

		if ( ! $file ) {
			wp_send_json_error( array( 'message' => __( 'The plugin is not installed.', 'custom-contact-forms' ) ), 404 );
		}

		if ( ! current_user_can( 'activate_plugin', $file ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to activate plugins.', 'custom-contact-forms' ) ), 403 );
		}

		$result = activate_plugin( $file );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 500 );
		}

		wp_send_json_success();
	}

	/**
	 * @return CCF_Our_Plugins
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
