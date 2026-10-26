<?php
/**
 * Plugin Name: Custom Contact Forms
 * Plugin URI: https://customformspro.com/
 * Description: Build custom forms with a drag-and-drop builder. Saved entries, a forms dashboard, signatures, Mailchimp, webhooks, and modern spam protection.
 * Author: Dmitry Alexander
 * Version: 7.19.1
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

define( 'CCF_VERSION', '7.19.1' );
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
require_once CCF_PLUGIN_DIR . 'classes/class-ccf-form-templates.php';
require_once CCF_PLUGIN_DIR . 'classes/class-ccf-importer.php';
require_once CCF_PLUGIN_DIR . 'classes/class-ccf-importer-cf7.php';
require_once CCF_PLUGIN_DIR . 'classes/class-ccf-importer-wpforms.php';
require_once CCF_PLUGIN_DIR . 'classes/class-ccf-form-importer-admin.php';
require_once CCF_PLUGIN_DIR . 'classes/class-ccf-review-request.php';
require_once CCF_PLUGIN_DIR . 'classes/class-ccf-feedback.php';
require_once CCF_PLUGIN_DIR . 'classes/class-ccf-pro-notice.php';
require_once CCF_PLUGIN_DIR . 'classes/class-ccf-dashboard-widget.php';
require_once CCF_PLUGIN_DIR . 'classes/class-ccf-pro-modal.php';
require_once CCF_PLUGIN_DIR . 'classes/class-ccf-ai-form-builder.php';
require_once CCF_PLUGIN_DIR . 'classes/class-ccf-ai-admin.php';
require_once CCF_PLUGIN_DIR . 'classes/class-ccf-signature-field.php';
require_once CCF_PLUGIN_DIR . 'classes/class-ccf-stats.php';
require_once CCF_PLUGIN_DIR . 'classes/class-ccf-auto-updates.php';
require_once CCF_PLUGIN_DIR . 'classes/class-ccf-dashboard.php';
require_once CCF_PLUGIN_DIR . 'classes/class-ccf-integration-log.php';
require_once CCF_PLUGIN_DIR . 'classes/class-ccf-email-provider.php';
require_once CCF_PLUGIN_DIR . 'classes/class-ccf-provider-mailchimp.php';
require_once CCF_PLUGIN_DIR . 'classes/class-ccf-email-marketing.php';
require_once CCF_PLUGIN_DIR . 'classes/class-ccf-webhooks.php';
require_once CCF_PLUGIN_DIR . 'classes/class-ccf-form-integrations.php';
require_once CCF_PLUGIN_DIR . 'classes/class-ccf-hcaptcha.php';
require_once CCF_PLUGIN_DIR . 'classes/class-ccf-spam.php';
require_once CCF_PLUGIN_DIR . 'classes/class-ccf-email-summary.php';
require_once CCF_PLUGIN_DIR . 'classes/class-ccf-integrations-page.php';
require_once CCF_PLUGIN_DIR . 'classes/class-ccf-newsletter.php';

CCF_Custom_Contact_Forms::factory();
CCF_Constants::factory();
CCF_Form_CPT::factory();
CCF_Submission_CPT::factory();
CCF_Field_CPT::factory();
CCF_Choice_CPT::factory();
CCF_Form_Manager::factory();
CCF_Pro_Notice::factory();
CCF_Dashboard_Widget::factory();
CCF_Signature_Field::factory();
CCF_Stats::factory();
if ( is_admin() ) {
	CCF_Auto_Updates::factory();
	CCF_Dashboard::factory();
}

// Only ever surfaces on WordPress 7.0+ with an AI provider configured; the
// class checks for itself and stays invisible otherwise.
if ( is_admin() ) {
	CCF_AI_Admin::factory();
	// The one-time builder popup is now the tips and news signup (7.19.0).
	CCF_Newsletter::factory();
}
CCF_Form_Renderer::factory();
CCF_Field_Renderer::factory();
CCF_Form_Handler::factory();
CCF_Upgrader::factory();
CCF_Export::factory();
CCF_Ads::factory();
CCF_Settings::factory();
CCF_Gutenberg_Block::factory();
CCF_Turnstile::factory();
CCF_Hcaptcha::factory();
CCF_Spam::factory();
CCF_Email_Marketing::factory();
CCF_Webhooks::factory();
CCF_Form_Integrations::factory();
CCF_Email_Summary::factory();
if ( is_admin() ) {
	CCF_Integrations_Page::factory();
}
CCF_Anti_Spam::factory();
CCF_Email_Logger::factory();
CCF_CSV_Importer::factory();
CCF_Form_Templates::factory();
CCF_Form_Importer_Admin::factory();
CCF_Review_Request::factory();
CCF_Feedback::factory();

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
/**
 * Address of the Forms > Integrations page, optionally opening one card.
 *
 * @since 7.18.0
 * @param string $open Card ID to open, such as "mailchimp".
 * @return string
 */
function ccf_integrations_url( $open = '' ) {
	$url = admin_url( 'edit.php?post_type=ccf_form&page=ccf-integrations' );

	return $open ? add_query_arg( 'open', sanitize_key( $open ), $url ) . '#ccf-int-' . sanitize_key( $open ) : $url;
}

/**
 * Build the Pro upgrade URL, tagged so its source is identifiable in analytics.
 *
 * Every prompt in the plugin previously linked to the same bare URL, so a
 * click from the deactivation survey and a click from the form builder were
 * indistinguishable once they arrived - there was no way to learn which
 * prompts actually work. The tag travels in the URL only; nothing is sent
 * anywhere from the user's site.
 *
 * @param string $source Short identifier for the prompt, e.g. 'form-builder'.
 * @param string $path   Optional path on the site.
 * @return string
 */
function ccf_pro_url( $source, $path = '/' ) {
	$url = 'https://customformspro.com' . $path;

	$url = add_query_arg(
		array(
			'utm_source'   => 'ccf-free',
			'utm_medium'   => 'plugin',
			'utm_campaign' => 'pro',
			'utm_content'  => sanitize_key( $source ),
		),
		$url
	);

	/**
	 * Filter the Pro upgrade URL.
	 *
	 * @param string $url    Tagged URL.
	 * @param string $source Prompt identifier.
	 */
	return apply_filters( 'ccf_pro_upgrade_url', $url, $source );
}

/**
 * The site's name for use in emails.
 *
 * Uses the Site Title, or the site's domain when the title is blank, so
 * subjects never read "[]" or ": Form Submission".
 *
 * @since 7.19.0
 * @return string
 */
function ccf_site_name() {
	$name = trim( wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES ) );

	if ( '' === $name ) {
		$name = (string) wp_parse_url( home_url(), PHP_URL_HOST );

		if ( 0 === strpos( $name, 'www.' ) ) {
			$name = substr( $name, 4 );
		}
	}

	return $name;
}

/**
 * Send an email with a clearer sender name than WordPress's default.
 *
 * WordPress sends every email as "WordPress" unless something sets a name.
 * This swaps only that built-in default for the name given, such as the
 * form's title. The sending address is never changed, and a name set in the
 * From header, by an SMTP plugin or by another filter is left alone.
 *
 * @since 7.19.0
 * @param string       $from_name   Sender name to use instead of "WordPress".
 * @param string|array $to          Recipient(s).
 * @param string       $subject     Subject.
 * @param string       $message     Message.
 * @param string|array $headers     Headers.
 * @param string|array $attachments Attachments.
 * @return bool Whether wp_mail() reported success.
 */
function ccf_mail( $from_name, $to, $subject, $message, $headers = '', $attachments = array() ) {
	$from_name = trim( wp_strip_all_tags( (string) $from_name ) );

	$swap = function ( $name ) use ( $from_name ) {
		return ( 'WordPress' === $name && '' !== $from_name ) ? $from_name : $name;
	};

	// Early priority, so an SMTP plugin that forces its own name still wins.
	add_filter( 'wp_mail_from_name', $swap, 5 );
	$sent = wp_mail( $to, $subject, $message, $headers, $attachments );
	remove_filter( 'wp_mail_from_name', $swap, 5 );

	return (bool) $sent;
}

function ccf_upgrade() {
	$version = get_option( 'ccf_db_version' );

	if ( empty( $version ) || version_compare( $version, '7.1', '<' ) ) {
		CCF_Upgrader::factory()->notifications_upgrade_71();
	}

	update_option( 'ccf_db_version', '7.9' );
}

/**
 * Remove scheduled events on deactivation. They are added again on the next
 * page load after the plugin is activated.
 *
 * @since 7.18.0
 */
function ccf_deactivate() {
	wp_clear_scheduled_hook( 'ccf_weekly_summary' );
	wp_clear_scheduled_hook( 'ccf_spam_cleanup' );
}
register_deactivation_hook( __FILE__, 'ccf_deactivate' );
register_activation_hook( __FILE__, 'ccf_flush_rewrites' );
register_activation_hook( __FILE__, 'ccf_upgrade' );
