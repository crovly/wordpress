=== Crovly ===
Contributors: crovly
Tags: captcha, spam, security, proof of work, bot protection
Requires at least: 5.8
Tested up to: 6.9
Stable tag: 1.0.6
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

* **Privacy-friendly** — No cookies, no cross-site tracking
* **No image puzzles** — Invisible to legitimate users
* **Resistant to AI vision attacks** — Proof of Work cannot be solved by image recognition
* **IP binding** — Tokens are bound to the solver's IP address
* **Adaptive difficulty** — Suspicious visitors receive harder challenges
* **22+ integrations** — Works with major WordPress form plugins
* **Lightweight** — Widget is under 25KB gzipped, zero dependencies, 42 languages

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

== External services ==

This plugin relies on the Crovly captcha service to function. It connects to two external endpoints:

**1. Crovly Widget CDN (get.crovly.com)**

The plugin loads the JavaScript widget from `https://get.crovly.com/widget.js` on any page that contains a protected form. The widget runs Proof of Work in the visitor's browser and collects a hashed browser fingerprint.

* **When:** Loaded on frontend pages that display a protected form (login, register, comment, checkout, etc.)
* **What is sent:** Standard HTTP request headers (IP address, user agent). No personal data.
* **Terms of Service:** [https://crovly.com/terms](https://crovly.com/terms)
* **Privacy Policy:** [https://crovly.com/privacy](https://crovly.com/privacy)

**2. Crovly Verification API (api.crovly.com)**

When a visitor submits a protected form, the plugin sends the generated captcha token to `https://api.crovly.com/verify-token` for server-side verification.

* **When:** On form submission of any form protected by Crovly.
* **What is sent:** The captcha token (opaque string), the visitor's IP address (for IP binding), and your Secret Key (for authentication).
* **What is received:** A success/failure response indicating whether the token is valid.
* **Terms of Service:** [https://crovly.com/terms](https://crovly.com/terms)
* **Privacy Policy:** [https://crovly.com/privacy](https://crovly.com/privacy)

Both services are operated by Crovly. No data is shared with third parties. The plugin does not set cookies or track visitors across sites.

== Installation ==

1. Upload the `crovly` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to Settings > Crovly
4. Enter your Site Key and Secret Key (obtained from the Crovly service dashboard)
5. Select which forms to protect
6. Done! Your forms are now protected

== Frequently Asked Questions ==

= Where do I get my API keys? =

Sign up for an account at the Crovly service. Create a site and you will receive a Site Key (public) and a Secret Key (private). See the External services section above for the service URL.

= Does Crovly use cookies? =

No. Crovly does not set any cookies and does not track users across sites.

= How does Crovly handle user data? =

Crovly only transmits a hashed browser fingerprint and the visitor's IP address (used for IP binding to prevent token replay). No personal data is stored. See the External services section for full details.

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
3. Crovly service dashboard showing verification analytics

== Changelog ==

= 1.0.6 =
* Inline admin script moved to enqueued asset file (wp_enqueue_script)
* Service badge is now opt-in (off by default) per WordPress.org guideline 10
* Added "Show service badge" setting under Advanced

= 1.0.5 =
* Removed promotional language and pricing references per WordPress.org guidelines
* Reduced external links in description (kept only in External services section)
* Reworded compliance claims to be more accurate

= 1.0.4 =
* Added "External services" disclosure section per WordPress.org requirements
* Added rel="noopener" to external links
* Updated "Tested up to" to WordPress 6.8

= 1.0.3 =
* Widget i18n: 42 languages with automatic browser language detection
* Updated docs URL structure (SDKs and Platforms separation)

= 1.0.2 =
* Added data-fallback="open" for HA fail-open behavior
* Updated docs links and widget size references
* Synced readme.txt stable tag

= 1.0.1 =
* XSS sanitization hardening

= 1.0.0 =
* Initial release
* 22+ form integrations
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

= 1.0.6 =
Compliance update: opt-in service badge, enqueued admin script.

= 1.0.5 =
Guideline compliance update. Recommended.

= 1.0.4 =
External services disclosure and minor fixes. Recommended update.

= 1.0.3 =
42-language widget support. Recommended update.

= 1.0.2 =
Widget size and docs link updates. Recommended update.

= 1.0.1 =
Security hardening. Recommended update.

= 1.0.0 =
Initial release of Crovly — privacy-first Proof of Work captcha for WordPress.
