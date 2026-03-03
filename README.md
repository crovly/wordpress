# Crovly for WordPress

Privacy-first captcha powered by Proof of Work. No image puzzles, no tracking, no cookies. **22+ integrations, all free.**

## Installation

1. Download the [latest release](https://github.com/crovly/wordpress/releases)
2. Upload the `crovly` folder to `/wp-content/plugins/`
3. Activate in **Plugins → Installed Plugins**
4. Configure in **Settings → Crovly**

## Supported Integrations

All integrations are free — no premium gating.

### WordPress Core
- Login, Registration, Lost Password, Comments

### WooCommerce
- Checkout, Login, Register, Lost Password, Pay for Order

### Form Builders
- Contact Form 7
- WPForms
- Gravity Forms
- Elementor Pro Forms
- Ninja Forms
- Fluent Forms
- Formidable Forms
- Forminator
- Jetpack Contact Form

### Page Builders
- Divi Builder (Contact Form + Login)

### Membership & Community
- BuddyPress (Registration, Activity Post)
- bbPress (New Topic, Reply)
- Ultimate Member (Login, Register, Password Reset)
- MemberPress (Checkout, Login)
- Paid Memberships Pro (Checkout)

### E-Commerce & Payments
- Easy Digital Downloads (Checkout, Login, Register)
- GiveWP (Donation forms)

### Other
- Mailchimp for WordPress (Signup forms)
- wpDiscuz (Comments)
- wpForo (New Topic, Reply)

## Features

- **Dynamic settings** — Only shows integrations for plugins you have active
- **IP allowlist** — Bypass captcha for trusted IPs
- **Skip logged-in users** — None / Admins only / All
- **Custom error message** — Override the default failure text
- **Shortcode** — `[crovly]` or `[crovly theme="dark"]`
- **PHP functions** — `crovly_render()` and `crovly_verify()`
- **Fail-open** — Users are never locked out if the API is unreachable
- **Lightweight** — Widget is under 4KB gzipped

## Custom Forms

```php
// In your template
<?php crovly_render(); ?>

// In your handler
if (!crovly_verify()) {
    wp_die('Captcha verification failed.');
}
```

## Requirements

- WordPress 5.8+
- PHP 7.4+

## Links

- [Documentation](https://docs.crovly.com/guides/wordpress)
- [Dashboard](https://app.crovly.com)
- [Website](https://crovly.com)

## License

GPLv2 or later
