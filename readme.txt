=== Crovly ===
Contributors: crovly
Tags: captcha, spam, security, proof of work, bot protection
Requires at least: 5.8
Tested up to: 6.7
Stable tag: 1.0.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Privacy-first, Proof of Work captcha for WordPress. No image puzzles, no tracking. 22+ integrations included.

== Description ==

Crovly is a privacy-first captcha service powered by Proof of Work. Unlike traditional captchas that rely on image puzzles (easily solved by AI) or invasive tracking, Crovly makes the visitor's browser do computational work to prove it's not a bot.

**How it works:**

1. Your visitor's browser solves a small cryptographic puzzle (Proof of Work)
2. Browser fingerprint and environment signals are collected (as a hash — no personal data stored)
3. Behavioral analysis detects automated patterns (mouse, keyboard, scroll)
4. A composite score determines if the visitor is human

**Key features:**

* **Privacy-first** — No cookies, no tracking, fully GDPR compliant
* **No image puzzles** — Invisible to legitimate users, no "select all buses"
* **AI-resistant** — Proof of Work can't be bypassed by vision AI
* **IP binding** — Tokens are bound to the solver's IP, preventing human farms
* **Adaptive difficulty** — Suspicious visitors get harder challenges
* **22+ integrations** — Works with all major WordPress plugins out of the box
* **Lightweight** — Widget is under 10KB gzipped, zero dependencies

**Supported integrations:**

* WordPress login, registration, lost password, comments
* WooCommerce (checkout, login, register, lost password, pay for order)
* Contact Form 7
* WPForms
* Gravity Forms
* Elementor Pro Forms
* Ninja Forms
* Fluent Forms
* Formidable Forms
* Forminator
* Jetpack Contact Form
* Divi (contact form, login)
* BuddyPress (registration, activity)
* bbPress (topics, replies)
* Ultimate Member (login, register, password reset)
* MemberPress (checkout, login)
* Paid Memberships Pro
* Easy Digital Downloads
* Mailchimp for WordPress
* GiveWP
* wpDiscuz
* wpForo
* WordPress Multisite signup

**Shortcode & PHP support:**

Use `[crovly]` shortcode in any page or post, or call `crovly_render()` and `crovly_verify()` in your theme templates.

== Installation ==

1. Upload the `crovly` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to Settings > Crovly
4. Enter your Site Key and Secret Key from [app.crovly.com](https://app.crovly.com)
5. Select which forms to protect
6. Done! Your forms are now protected

== Frequently Asked Questions ==

= Where do I get my API keys? =

Sign up for a free account at [app.crovly.com](https://app.crovly.com). Create a site and you'll receive a Site Key (public) and Secret Key (private).

= Is Crovly free? =

Yes! The free plan includes unlimited verifications and unlimited sites. The Pro plan ($9/month) adds badge removal, 30-day analytics with CSV export, webhooks, and custom difficulty.

= Does Crovly use cookies? =

No. Crovly does not set any cookies and does not track users across sites.

= Is Crovly GDPR compliant? =

Yes. Crovly collects only a hashed browser fingerprint (not reversible to personal data) and does not use cookies or cross-site tracking.

= What happens if the Crovly API is unreachable? =

The plugin fails open — if the verification API cannot be reached, the form submission is allowed through. This prevents legitimate users from being blocked by network issues.

= Can I use Crovly with a form plugin not listed? =

Yes! Use the `[crovly]` shortcode to add the widget to any form, and call `crovly_verify()` in your form processing code to verify the token.

= Does it work with WordPress Multisite? =

Yes. Crovly supports Multisite signup forms and cleans up data across all sites on uninstall (if enabled).

= Can I define API keys in wp-config.php? =

Yes. Add these constants to your `wp-config.php` to override the database settings:

`define('CROVLY_SITE_KEY', 'crvl_site_...');`
`define('CROVLY_SECRET_KEY', 'crvl_secret_...');`

When constants are defined, the settings page inputs become read-only.

= I'm locked out! How do I disable Crovly? =

Add this to your `wp-config.php`:

`define('CROVLY_DISABLE', true);`

This bypasses all captcha verification so you can log in and fix your settings. Remove it when done.

= Is Crovly compatible with WooCommerce HPOS? =

Yes. Crovly declares High-Performance Order Storage (HPOS) compatibility.

= Does Crovly work with Cloudflare? =

Yes. The widget script is automatically tagged with `data-cfasync="false"` to prevent conflicts with Cloudflare Rocket Loader.

== Screenshots ==

1. Settings page — API keys, theme, and form selection
2. Protected login form with Crovly widget
3. Dashboard at app.crovly.com showing verification analytics

== Changelog ==

= 1.0.0 =
* Initial release
* 22+ form integrations — all free, no premium gating
* Proof of Work captcha with adaptive difficulty
* Browser fingerprint and headless detection
* Behavioral analysis (mouse, keyboard, scroll, touch)
* Light, dark, and auto theme support
* IP allowlist
* Shortcode and PHP function support
* WordPress Multisite support
* wp-config.php constants support (CROVLY_SITE_KEY, CROVLY_SECRET_KEY, CROVLY_DISABLE)
* Test Connection button for API key validation
* WooCommerce HPOS compatibility
* Cloudflare Rocket Loader compatibility
* Emergency lockout recovery via CROVLY_DISABLE constant

== Upgrade Notice ==

= 1.0.0 =
Initial release of Crovly — privacy-first Proof of Work captcha for WordPress.
