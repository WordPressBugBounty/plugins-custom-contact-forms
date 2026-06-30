=== Custom Contact Forms ===
Contributors: outlawgt, tlovett1
Donate link: https://oiopublisher.com/
Tags: contact form, form builder, custom form, spam protection, turnstile
Requires at least: 5.0
Tested up to: 7.0
Stable tag: 7.11.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Build custom forms and manage submissions the WordPress way. Drag-and-drop builder, prebuilt templates, Gutenberg block, and modern spam protection.

== Description ==

Custom Contact Forms lets you build forms and manage submissions entirely within WordPress. The drag-and-drop builder lives inside the media manager — no separate admin pages, no learning a new interface. Live previews update as you build, and forms can be inserted via Gutenberg block, shortcode, widget, or PHP function.

Start from a prebuilt template — contact, quote request, newsletter, event registration, or feedback — and have a working form in seconds. Or build your own from scratch with text, email, phone, address, dropdowns, checkboxes, file uploads, and more. Style it with included form themes and per-field width controls, or add your own custom CSS.

**Powering thousands of active websites, with over 1.3 million all-time downloads.** Originally created by Taylor Lovett, Custom Contact Forms is now actively maintained by [Dmitry Alexander](https://oiopublisher.com/) — rebuilt for modern WordPress with a hardened, PHP 8+ codebase, a refreshed builder, and new features added regularly.

= What You Can Build =

* Contact forms, quote requests, support forms, event registrations, newsletter signups
* Text, paragraph, email (with optional confirmation), name, phone, website, address (US + international), date/time, dropdowns, checkboxes, radio buttons, file uploads, hidden fields, and HTML blocks
* Conditional fields and sections — show/hide fields based on other field values
* Multiple email notifications per form — customize recipients, subject, from name, reply-to, and body with field mapping
* Post creation on submission — map form fields to post fields, meta, and taxonomies

= What's New in 7.11 =

* **Modernized builder** — Yes/No options are now clean toggle switches, and fields display as cards in the builder canvas
* **Friendly submission columns** — The submissions table now shows real field labels instead of internal slugs
* **Redesigned submissions table** — A cleaner, more modern look for managing entries
* **Redesigned templates screen** — Each form template now has its own custom icon, under Forms → Templates
* **WordPress 7.0 ready** — Tested up to the latest WordPress

= Powerful Features, Included Free =

* Drag-and-drop form builder with live preview in the media manager
* Prebuilt form templates for the most common form types
* Gutenberg block, shortcode, widget, and PHP template support
* Multiple form themes plus per-field width controls and custom CSS
* AJAX form submission — no page reloads
* Export submissions to CSV, and import submissions from CSV with automatic column mapping
* Import and export forms via WordPress XML
* Cloudflare Turnstile, reCAPTCHA, and simple captcha options
* Built-in spam protection — honeypot, time-based trap, IP rate limiting, disposable email blocking, keyword blacklist
* Email diagnostics — send test emails and view delivery failure logs
* Restrict forms to logged-in users, or pause forms with a custom message
* Customizable completion text or redirect URL
* Conditional asset loading — only load scripts where forms appear
* Extensible with hooks, filters, and custom field types

= Quick Start =

1. Go to Forms → Templates and pick a starting template — or go to Forms → Forms and Submissions to start from scratch
2. Drag fields from the sidebar into the form area, and click a field to edit its label, width, and options
3. Save the form
4. Insert it with the Gutenberg block (search "CCF" in the block inserter) or the `[ccf_form id="X"]` shortcode

== Installation ==

1. Upload the `custom-contact-forms` folder to `/wp-content/plugins/`
2. Activate the plugin through the Plugins menu
3. Go to Forms → Templates to start from a prebuilt form, or Forms → Forms and Submissions to build your own
4. Insert forms using the Gutenberg block or the `[ccf_form id="X"]` shortcode

= Shortcode =

`[ccf_form id="123"]`

= PHP Template Tag =

`<?php if ( function_exists( 'ccf_output_form' ) ) { ccf_output_form( 123 ); } ?>`

== Frequently Asked Questions ==

= How do I use a form template? =

Go to Forms → Templates and choose one of the prebuilt forms (Contact, Quote Request, Newsletter, Event Registration, or Feedback). A new form is created with the right fields already in place, ready for you to customize and insert.

= How do I make fields sit side by side? =

Click a field in the builder, open the Advanced panel, and set its Field Width (half, third, two-thirds, or quarter). Place two or more partial-width fields together and they line up in columns. Widths apply on the published form — in the block editor preview and on the front end — not inside the builder.

= How do I add custom styling? =

For per-form looks, choose a Form Theme (including the new Minimal theme) on the form or in the block settings. For site-wide custom styles, go to Forms → Settings and add your CSS in the Custom CSS box.

= How do I add Cloudflare Turnstile? =

Go to Forms → Settings and scroll to "Cloudflare Turnstile." Enter your site key and secret key (free from [Cloudflare Dashboard](https://dash.cloudflare.com/?to=/:account/turnstile)), enable it, and save. All forms will automatically show the Turnstile challenge.

= How do I insert a form? =

Use the Gutenberg block (search "CCF" or "Contact Form"), the shortcode `[ccf_form id="X"]`, the CCF widget, or the PHP template tag `ccf_output_form( X )`.

= Can I export and import form submissions? =

Yes. Edit any form and click the download icon to export submissions as a CSV file. To import, go to Forms → Import CSV, select a form, upload your CSV, and map the columns to form fields — the importer auto-detects matching columns by name.

= Does this plugin create custom database tables? =

No. All data is stored using WordPress custom post types and post meta. Nothing custom is added to your database schema.

= Is this plugin compatible with PHP 8? =

Yes. The plugin is fully compatible with PHP 8.0, 8.1, 8.2, 8.3, and 8.4.

== Screenshots ==

1. Drag-and-drop form builder with live preview and field settings
2. Form submissions management with CSV export
3. Gutenberg block with live preview and selectable form themes
4. Settings page with Cloudflare Turnstile and spam protection options
5. Prebuilt form templates to start a new form in one click

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

= 7.11.0 =
* Tweak: Modernized form builder — Yes/No options now display as toggle switches and fields appear as cards in the canvas
* Tweak: Friendly submission columns — the entry table now shows real field labels instead of internal slugs
* Tweak: Redesigned submissions table with a cleaner, more modern style
* Tweak: Redesigned Form Templates screen with custom template icons
* Dev: New ccf_pre_submission_errors filter so add-ons can run final pre-save validation
* Compatibility: Tested up to WordPress 7.0

= 7.10.0 =
* New: Form templates — prebuilt Contact, Quote Request, Newsletter, Event Registration, and Feedback forms under Forms → Templates
* New: Field width controls — set fields to full, half, third, two-thirds, or quarter width for multi-column layouts
* New: Minimal form theme — modern underlined-input style, selectable per form and per block
* New: Custom CSS setting — add site-wide form styles from Forms → Settings
* Tweak: Refreshed form builder with field-type icons and a cleaner, more modern interface

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

= 7.11.0 =
A visual refresh of the form builder (toggle switches and card-style fields), a modernized submissions table with friendly column labels, and a redesigned templates screen. Tested up to WordPress 7.0. All existing forms and submissions are preserved — no data migration needed.

= 7.10.0 =
Adds form templates, per-field width controls, a Minimal theme, custom CSS, and a refreshed builder. All existing forms and submissions are preserved — no data migration needed.

= 7.9.0 =
Major security and compatibility update. Fixes PHP 8+ errors, adds Gutenberg block, Cloudflare Turnstile spam protection, and modern form styling. All existing forms and submissions are preserved — no data migration needed. Recommended for all users.