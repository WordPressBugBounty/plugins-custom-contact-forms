=== Custom Contact Forms ===
Contributors: outlawgt, tlovett1
Donate link: https://oiopublisher.com/
Tags: contact form, form builder, custom form, spam protection, turnstile
Requires at least: 5.0
Tested up to: 6.9
Stable tag: 7.9.1
Requires PHP: 7.4
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Build custom forms and manage submissions the WordPress way. Gutenberg block, Cloudflare Turnstile, and anti-spam protection.

== Description ==

Custom Contact Forms lets you build forms and manage submissions entirely within WordPress. The drag-and-drop builder lives inside the media manager — no separate admin pages, no learning a new interface. Live previews update as you build, and forms can be inserted via shortcode, Gutenberg block, widget, or PHP function.

**This plugin is actively maintained.** Originally created by Taylor Lovett, it is now maintained by [Dmitry Alexander](https://oiopublisher.com/) as of 2026. Version 7.9.0 is a comprehensive modernization: security hardened, PHP 8+ compatible, with new features and a modern UI.

= What You Can Build =

* Contact forms, quote requests, support forms, event registrations, newsletter signups
* Text, paragraph, email (with optional confirmation), name, phone, website, address (US + international), date/time, dropdowns, checkboxes, radio buttons, file uploads, hidden fields, and HTML blocks
* Conditional fields and sections — show/hide fields based on other field values
* Multiple email notifications per form — customize recipients, subject, from name, reply-to, and body with field mapping
* Post creation on submission — map form fields to post fields, meta, and taxonomies

= What's New in 7.9.0 =

* **Gutenberg block** — Insert forms directly from the block editor with a live server-side preview
* **Cloudflare Turnstile** — Modern, invisible, privacy-friendly spam protection (free from Cloudflare)
* **Anti-spam controls** — Enhanced honeypot, time-based trap, IP rate limiting, disposable email blocking, keyword blacklist
* **Email diagnostics** — Send test emails, view wp_mail failure log, debug delivery issues
* **Copy shortcode** — One-click copy from form list, edit screen, and At a Glance panel
* **Modern CSS** — Clean, responsive form styles with proper focus states, dark/light theme support
* **Security hardened** — Full code audit, XSS fixes, SQL injection prevention, input sanitization, capability checks
* **PHP 8+ compatible** — All deprecation warnings and type errors resolved

= Features =

* Drag-and-drop form builder with live preview in the media manager
* Gutenberg block, shortcode, widget, and PHP template support
* AJAX form submission — no page reloads
* Export submissions to CSV
* Import submissions from CSV with automatic column mapping
* Import and export forms via WordPress XML
* Multiple form themes (light and dark)
* Cloudflare Turnstile, reCAPTCHA, and simple captcha options
* Restrict forms to logged-in users
* Pause forms with a custom message
* Customizable completion text or redirect URL
* Conditional asset loading — only load scripts where forms appear
* Extensible with hooks, filters, and custom field types
* Translations: French, Chinese, German, Danish

= Quick Start =

1. Go to Forms → Forms and Submissions
2. Click "Manage Form" on any post or page
3. Drag fields from the sidebar into the form area
4. Save and insert into your content
5. Or use the Gutenberg block: search "CCF" in the block inserter

== Installation ==

1. Upload the `custom-contact-forms` folder to `/wp-content/plugins/`
2. Activate the plugin through the Plugins menu
3. Go to Forms → Forms and Submissions to create your first form
4. Insert forms using the `[ccf_form id="X"]` shortcode or the Gutenberg block

= Shortcode =

`[ccf_form id="123"]`

= PHP Template Tag =

`<?php if ( function_exists( 'ccf_output_form' ) ) { ccf_output_form( 123 ); } ?>`

== Frequently Asked Questions ==

= How do I add Cloudflare Turnstile? =

Go to Forms → Settings and scroll to "Cloudflare Turnstile." Enter your site key and secret key (free from [Cloudflare Dashboard](https://dash.cloudflare.com/?to=/:account/turnstile)), enable it, and save. All forms will automatically show the Turnstile challenge.

= How do I insert a form? =

Use the shortcode `[ccf_form id="X"]`, the Gutenberg block (search "CCF" or "Contact Form"), the CCF widget, or the PHP template tag `ccf_output_form( X )`.

= Can I export form submissions? =

Yes. Edit any form and click the download icon to export submissions as a CSV file.

= Can I import submissions from a CSV? =

Yes. Go to Forms → Import CSV. Select a form, upload your CSV file, and map the CSV columns to form fields. The importer auto-detects matching columns by name. You can import submissions exported from other form plugins or any CSV source.

= Does this plugin create custom database tables? =

No. All data is stored using WordPress custom post types and post meta. Nothing custom is added to your database schema.

= Is this plugin compatible with PHP 8? =

Yes. Version 7.9.0 resolves all PHP 8.0, 8.1, 8.2, 8.3, and 8.4 compatibility issues.

== Screenshots ==

1. Drag-and-drop form builder with live preview
2. Form submissions management
3. Gutenberg block with server-side preview
4. Settings page with Turnstile and spam protection options

== External Services ==

This plugin optionally connects to the following third-party services for spam protection. These services are only used when the site administrator explicitly enables and configures them.

= Google reCAPTCHA =

When enabled in the form builder, this plugin loads the Google reCAPTCHA widget on form pages and sends form submission verification requests to Google's servers. The user's reCAPTCHA response token is sent to Google for validation. No personal data is sent by the plugin itself — Google may collect usage data through their widget script.

* Service provider: Google LLC
* [Terms of Service](https://policies.google.com/terms)
* [Privacy Policy](https://policies.google.com/privacy)

= Cloudflare Turnstile =

When enabled in Forms → Settings → Cloudflare Turnstile, this plugin loads the Cloudflare Turnstile widget script on form pages and sends form submission verification requests to Cloudflare's servers. The user's Turnstile response token and the visitor's IP address are sent to Cloudflare for validation.

* Service provider: Cloudflare, Inc.
* [Terms of Service](https://www.cloudflare.com/terms/)
* [Privacy Policy](https://www.cloudflare.com/privacypolicy/)

== Changelog ==

= 7.9.1 =
* Privacy: Google reCAPTCHA API is no longer loaded by default — it now loads only on pages that actually render a reCAPTCHA field, preventing unsolicited third-party requests
* Fix: register_setting() calls updated to the modern array syntax with explicit sanitize_callback

= 7.9.0 =
* New maintainer: Dmitry Alexander (outlawgt)
* Security: Full top-down code audit and hardening
* Security: Fixed XSS vulnerability in form renderer (unescaped REQUEST_URI)
* Security: Fixed potential SQL injection in export filter_query()
* Security: Added capability checks to export and API delete endpoints
* Security: Removed abandoned external Mailchimp subscription (dead URL)
* Security: All API permission callbacks return WP_Error for proper REST responses
* Security: Sanitized IP address and nonce inputs throughout
* New: Gutenberg block with form selector and live server-side preview
* New: Cloudflare Turnstile integration (Settings → Cloudflare Turnstile)
* New: Enhanced anti-spam — improved honeypot, time-based trap, IP rate limiting
* New: Disposable email blocking and keyword blacklist
* New: Email diagnostics — test email button and wp_mail failure logging
* New: Copy shortcode button in form list, edit screen, and At a Glance panel
* New: CSV submission importer (Forms → Import CSV) with column auto-mapping
* New: Modern responsive form CSS with proper focus states and transitions
* New: Dark and light theme overrides with modern styling
* New: jQuery UI datepicker modern style override
* Fix: PHP 8+ compatibility — resolved all deprecation warnings and type errors
* Fix: session_start() checks session_status() and headers_sent()
* Fix: (double) cast replaced with proper int math
* Fix: Settings page array offset on false when options not yet set
* Fix: Null-safe array access in submission CPT formatters
* Fix: Import bug — choices saving to wrong meta key
* Fix: show_in_json replaced with show_in_rest
* Fix: Removed obsolete vendored WP-API loader
* Tweak: ABSPATH guards added to all PHP files
* Tweak: date() replaced with wp_date(), parse_url() with wp_parse_url()
* Tweak: wp_send_json() replaces echo json_encode() + exit pattern

= 7.8.5 =
* Prevent submissions from being accessible in API

= 7.8.4 =
* Fix WP 4.7 conflict

= 7.8.3 =
* Fix WooCommerce conflict

= 7.8.2 =
* Add $submission to ccf_email_subject filter, correct "Invalid Date" issue with datepicker
* Fix WooCommerce conflict
* Add support for Customize Posts plugin

= 7.8.1 =
* Cache busy form submission URL
* Improve field choice UI

= 7.8 =
* Hide form title setting
* Reply to notification fields
* Activate form notifications by default

= 7.7 =
* New CAPTCHA option
* Fix "0" choice input bug
* Fix empty conditional bug
* Reset field renderer bug fixed
* Guide user for whitelisting file extensions in file field
* Submit class form option
* Logged in users only form option

= 7.6 =
* Form duplication
* Fix multiple section header bug
* Button class field

= 7.5 =
* Conditional fields and sections
* [current_date_time] notification variable

== Upgrade Notice ==

= 7.9.0 =
Major security and compatibility update. Fixes PHP 8+ errors, adds Gutenberg block, Cloudflare Turnstile spam protection, and modern form styling. All existing forms and submissions are preserved — no data migration needed. Recommended for all users.
