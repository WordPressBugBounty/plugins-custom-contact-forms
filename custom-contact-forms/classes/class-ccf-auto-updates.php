<?php
/**
 * Automatic updates prompt.
 *
 * WordPress lets site owners turn on automatic updates per plugin, but it is
 * off by default. This shows one dismissible notice on Custom Contact Forms
 * screens asking whether to turn it on. Nothing changes unless the site
 * owner clicks the button, and the notice never returns once answered.
 *
 * @package Custom_Contact_Forms
 * @since   7.17.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CCF_Auto_Updates {

	/**
	 * User meta flag set when the notice is answered or dismissed.
	 */
	const DISMISSED_META = 'ccf_auto_update_prompt_dismissed';

	/**
	 * Register hooks.
	 */
	public function setup() {
		add_action( 'admin_notices', array( $this, 'notice' ) );
		add_action( 'admin_post_ccf_enable_auto_updates', array( $this, 'handle_enable' ) );
		add_action( 'admin_post_ccf_dismiss_auto_updates', array( $this, 'handle_dismiss' ) );
	}

	/**
	 * This plugin's basename, e.g. custom-contact-forms/custom-contact-forms.php.
	 *
	 * @return string
	 */
	public static function basename() {
		return plugin_basename( CCF_PLUGIN_DIR . 'custom-contact-forms.php' );
	}

	/**
	 * Whether automatic updates are already on for this plugin.
	 *
	 * @return bool
	 */
	public static function is_enabled() {
		$enabled = (array) get_site_option( 'auto_update_plugins', array() );
		return in_array( self::basename(), $enabled, true );
	}

	/**
	 * Whether this user can turn automatic updates on here. False when the
	 * site has plugin auto-updates switched off entirely, since the setting
	 * would do nothing.
	 *
	 * @return bool
	 */
	public static function can_enable() {
		if ( ! current_user_can( 'update_plugins' ) ) {
			return false;
		}

		if ( is_multisite() && ! is_network_admin() && ! is_super_admin() ) {
			return false;
		}

		if ( function_exists( 'wp_is_auto_update_enabled_for_type' ) && ! wp_is_auto_update_enabled_for_type( 'plugin' ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Link that turns automatic updates on, protected by a nonce.
	 *
	 * @return string
	 */
	public static function enable_url() {
		return wp_nonce_url( admin_url( 'admin-post.php?action=ccf_enable_auto_updates' ), 'ccf_enable_auto_updates' );
	}

	/**
	 * Whether the current screen belongs to Custom Contact Forms.
	 *
	 * @return bool
	 */
	private function on_plugin_screen() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( ! $screen ) {
			return false;
		}

		if ( 'ccf_form' === $screen->post_type && 'edit' === $screen->base ) {
			return true;
		}

		// The dashboard offers this in its Next steps panel instead.
		return false !== strpos( $screen->id, 'ccf-settings' );
	}

	/**
	 * The notice.
	 */
	public function notice() {
		if ( isset( $_GET['ccf_auto_updates'] ) && 'on' === $_GET['ccf_auto_updates'] && self::is_enabled() ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Automatic updates are on for Custom Contact Forms.', 'custom-contact-forms' ) . '</p></div>';
			return;
		}

		if ( ! $this->on_plugin_screen() || ! self::can_enable() || self::is_enabled() ) {
			return;
		}

		if ( get_user_meta( get_current_user_id(), self::DISMISSED_META, true ) ) {
			return;
		}

		$dismiss_url = wp_nonce_url( admin_url( 'admin-post.php?action=ccf_dismiss_auto_updates' ), 'ccf_dismiss_auto_updates' );
		?>
		<div class="notice notice-info ccf-auto-update-notice">
			<p><strong><?php esc_html_e( 'Keep Custom Contact Forms up to date automatically?', 'custom-contact-forms' ); ?></strong></p>
			<p><?php esc_html_e( 'Turn on automatic updates and new versions, including security fixes, install as soon as they are released. You can turn this off at any time on the Plugins screen.', 'custom-contact-forms' ); ?></p>
			<p>
				<a class="button button-primary" href="<?php echo esc_url( self::enable_url() ); ?>"><?php esc_html_e( 'Turn on automatic updates', 'custom-contact-forms' ); ?></a>
				<a class="button" href="<?php echo esc_url( $dismiss_url ); ?>"><?php esc_html_e( 'No thanks', 'custom-contact-forms' ); ?></a>
			</p>
		</div>
		<?php
	}

	/**
	 * Where to go after answering.
	 *
	 * @return string
	 */
	private function back_url() {
		$referer = wp_get_referer();
		return $referer ? $referer : admin_url( 'edit.php?post_type=ccf_form&page=ccf-dashboard' );
	}

	/**
	 * Turn automatic updates on for this plugin, the same way the Plugins
	 * screen does.
	 */
	public function handle_enable() {
		check_admin_referer( 'ccf_enable_auto_updates' );

		if ( ! self::can_enable() ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to change automatic updates.', 'custom-contact-forms' ), 403 );
		}

		$enabled   = (array) get_site_option( 'auto_update_plugins', array() );
		$enabled[] = self::basename();
		update_site_option( 'auto_update_plugins', array_values( array_unique( $enabled ) ) );

		update_user_meta( get_current_user_id(), self::DISMISSED_META, 'enabled' );

		wp_safe_redirect( add_query_arg( 'ccf_auto_updates', 'on', $this->back_url() ) );
		exit;
	}

	/**
	 * Remember that the user said no.
	 */
	public function handle_dismiss() {
		check_admin_referer( 'ccf_dismiss_auto_updates' );

		update_user_meta( get_current_user_id(), self::DISMISSED_META, 'no' );

		wp_safe_redirect( remove_query_arg( 'ccf_auto_updates', $this->back_url() ) );
		exit;
	}

	/**
	 * Return the singleton instance.
	 *
	 * @return CCF_Auto_Updates
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
