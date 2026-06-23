<?php
/**
 * Plugin Name: Custom Contact Forms
 * Plugin URI: https://oiopublisher.com/
 * Description: Build beautiful custom forms and manage submissions the WordPress way. Gutenberg block, Cloudflare Turnstile, anti-spam protection, and email diagnostics.
 * Author: Dmitry Alexander
 * Version: 7.9.1
 * Text Domain: custom-contact-forms
 * Domain Path: /languages
 * Author URI: https://oiopublisher.com/
 * Requires PHP: 7.4
 * Requires at least: 5.0
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Copyright 2013-2018 Taylor Lovett
 * Copyright 2026 Dmitry Alexander
 *
 * Originally developed by Taylor Lovett. Now maintained by Dmitry Alexander.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CCF_VERSION', '7.9.1' );
define( 'CCF_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'CCF_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once CCF_PLUGIN_DIR . 'classes/class-ccf-constants.php';
require_once CCF_PLUGIN_DIR . 'classes/class-ccf-custom-contact-forms.php';
require_once CCF_PLUGIN_DIR . 'classes/class-ccf-form-cpt.php';
require_once CCF_PLUGIN_DIR . 'classes/class-ccf-submission-cpt.php';
require_once CCF_PLUGIN_DIR . 'classes/class-ccf-form-mail.php';
require_once CCF_PLUGIN_DIR . 'classes/class-ccf-field-cpt.php';
require_once CCF_PLUGIN_DIR . 'classes/class-ccf-choice-cpt.php';
require_once CCF_PLUGIN_DIR . 'classes/class-ccf-form-manager.php';
require_once CCF_PLUGIN_DIR . 'classes/class-ccf-field-renderer.php';
require_once CCF_PLUGIN_DIR . 'classes/class-ccf-form-renderer.php';
require_once CCF_PLUGIN_DIR . 'classes/class-ccf-form-handler.php';
require_once CCF_PLUGIN_DIR . 'classes/class-ccf-upgrader.php';
require_once CCF_PLUGIN_DIR . 'classes/class-ccf-widget.php';
require_once CCF_PLUGIN_DIR . 'classes/class-ccf-export.php';
require_once CCF_PLUGIN_DIR . 'classes/class-ccf-ads.php';
require_once CCF_PLUGIN_DIR . 'classes/class-ccf-settings.php';
require_once CCF_PLUGIN_DIR . 'classes/class-ccf-gutenberg-block.php';
require_once CCF_PLUGIN_DIR . 'classes/class-ccf-turnstile.php';
require_once CCF_PLUGIN_DIR . 'classes/class-ccf-anti-spam.php';
require_once CCF_PLUGIN_DIR . 'classes/class-ccf-email-logger.php';
require_once CCF_PLUGIN_DIR . 'classes/class-ccf-csv-importer.php';

CCF_Custom_Contact_Forms::factory();
CCF_Constants::factory();
CCF_Form_CPT::factory();
CCF_Submission_CPT::factory();
CCF_Field_CPT::factory();
CCF_Choice_CPT::factory();
CCF_Form_Manager::factory();
CCF_Form_Renderer::factory();
CCF_Field_Renderer::factory();
CCF_Form_Handler::factory();
CCF_Upgrader::factory();
CCF_Export::factory();
CCF_Ads::factory();
CCF_Settings::factory();
CCF_Gutenberg_Block::factory();
CCF_Turnstile::factory();
CCF_Anti_Spam::factory();
CCF_Email_Logger::factory();
CCF_CSV_Importer::factory();

/**
 * Setup the widget
 *
 * @since 6.4
 */
function ccf_register_widget() {
	register_widget( 'CCF_Widget' );
}
add_action( 'widgets_init', 'ccf_register_widget' );

/**
 * Flush the rewrites at the end of init after the plugin has been activated.
 *
 * @since 6.0
 */
function ccf_flush_rewrites() {
	update_option( 'ccf_flush_rewrites', true );
}

/**
 * Upgrade CCF DB information
 *
 * @since 7.1
 */
function ccf_upgrade() {
	$version = get_option( 'ccf_db_version' );

	if ( empty( $version ) || version_compare( $version, '7.1', '<' ) ) {
		CCF_Upgrader::factory()->notifications_upgrade_71();
	}

	update_option( 'ccf_db_version', '7.9' );
}

register_activation_hook( __FILE__, 'ccf_flush_rewrites' );
register_activation_hook( __FILE__, 'ccf_upgrade' );
