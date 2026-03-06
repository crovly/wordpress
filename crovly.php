<?php
/**
 * Plugin Name: Crovly
 * Plugin URI: https://crovly.com
 * Description: Privacy-first, Proof of Work captcha for WordPress. 30+ integrations: login, registration, comments, Contact Form 7, WPForms, Gravity Forms, Elementor, Ninja Forms, Fluent Forms, Formidable, Forminator, WooCommerce, BuddyPress, bbPress, Ultimate Member, MemberPress, Divi, Easy Digital Downloads, Mailchimp, GiveWP, and more. All free — no premium gating.
 * Version: 1.0.2
 * Author: Crovly
 * Author URI: https://crovly.com
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: crovly
 * Domain Path: /languages
 * Requires at least: 5.8
 * Tested up to: 6.7
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) {
    exit;
}

define('CROVLY_VERSION', '1.0.2');
define('CROVLY_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('CROVLY_PLUGIN_URL', plugin_dir_url(__FILE__));

class Crovly_Plugin {

    private static $instance = null;
    private $site_key = '';
    private $secret_key = '';
    private $widget_loaded = false;
    private $widget_counter = 0;

    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->site_key = defined('CROVLY_SITE_KEY') ? CROVLY_SITE_KEY : get_option('crovly_site_key', '');
        $this->secret_key = defined('CROVLY_SECRET_KEY') ? CROVLY_SECRET_KEY : get_option('crovly_secret_key', '');

        // i18n
        add_action('init', function () {
            load_plugin_textdomain('crovly', false, dirname(plugin_basename(__FILE__)) . '/languages');
        });

        // Admin
        add_action('admin_menu', [$this, 'add_settings_page']);
        add_action('admin_init', [$this, 'register_settings']);
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), [$this, 'add_settings_link']);
        add_action('admin_init', [$this, 'activation_redirect']);
        add_action('admin_notices', [$this, 'admin_notice_keys']);
        add_action('wp_ajax_crovly_test_connection', [$this, 'ajax_test_connection']);

        // Cloudflare Rocket Loader compat
        add_filter('script_loader_tag', [$this, 'add_cfasync_attr'], 10, 2);

        // Shortcode & PHP function
        add_shortcode('crovly', [$this, 'shortcode_widget']);

        if (empty($this->site_key) || empty($this->secret_key)) {
            return;
        }

        $this->init_wp_core();
        $this->init_woocommerce();
        $this->init_contact_form_7();
        $this->init_wpforms();
        $this->init_gravity_forms();
        $this->init_elementor();
        $this->init_ninja_forms();
        $this->init_fluent_forms();
        $this->init_formidable();
        $this->init_forminator();
        $this->init_jetpack();
        $this->init_divi();
        $this->init_buddypress();
        $this->init_bbpress();
        $this->init_ultimate_member();
        $this->init_memberpress();
        $this->init_paid_memberships_pro();
        $this->init_edd();
        $this->init_mailchimp();
        $this->init_givewp();
        $this->init_wpdiscuz();
        $this->init_wpforo();
    }

    // ═══════════════════════════════════════
    //  Helpers
    // ═══════════════════════════════════════

    private function is_enabled($form) {
        $forms = get_option('crovly_enabled_forms', ['login', 'register', 'lostpassword', 'comment']);
        return is_array($forms) && in_array($form, $forms, true);
    }

    private function should_skip() {
        if (!is_user_logged_in()) return false;

        $skip = get_option('crovly_skip_logged_in', 'none');
        if ($skip === 'all') return true;
        if ($skip === 'admins' && current_user_can('manage_options')) return true;
        return false;
    }

    public function enqueue_widget() {
        if ($this->widget_loaded) return;
        wp_enqueue_script('crovly-widget', 'https://get.crovly.com/widget.js', [], CROVLY_VERSION, ['in_footer' => true, 'strategy' => 'defer']);
        $this->widget_loaded = true;
    }

    private static $kses_allowed = ['div' => ['id' => [], 'class' => [], 'data-site-key' => [], 'data-theme' => [], 'data-fallback' => [], 'style' => []]];

    public function render_widget() {
        echo wp_kses($this->get_widget_html(), self::$kses_allowed);
    }

    public function get_widget_html($extra_attrs = '') {
        $this->enqueue_widget();
        $this->widget_counter++;
        $id = 'crovly-captcha-' . $this->widget_counter;
        $theme = get_option('crovly_theme', 'auto');
        // Sanitize extra_attrs: strip tags and only allow safe attribute patterns
        $safe_extra = $extra_attrs ? ' ' . wp_kses_no_null(wp_strip_all_tags($extra_attrs)) : '';
        return '<div id="' . esc_attr($id) . '" class="crovly-captcha" data-site-key="' . esc_attr($this->site_key) . '" data-theme="' . esc_attr($theme) . '" data-fallback="open"' . $safe_extra . ' style="margin:10px 0"></div>';
    }

    public function shortcode_widget($atts) {
        $atts = shortcode_atts(['theme' => ''], $atts, 'crovly');
        $this->enqueue_widget();
        $this->widget_counter++;
        $id = 'crovly-captcha-' . $this->widget_counter;
        $theme = $atts['theme'] ?: get_option('crovly_theme', 'auto');
        return '<div id="' . esc_attr($id) . '" class="crovly-captcha" data-site-key="' . esc_attr($this->site_key) . '" data-theme="' . esc_attr($theme) . '" data-fallback="open" style="margin:10px 0"></div>';
    }

    // ═══════════════════════════════════════
    //  Token Verification
    // ═══════════════════════════════════════

    public function verify_token() {
        if (defined('CROVLY_DISABLE') && CROVLY_DISABLE) return true;
        if ($this->should_skip()) return true;

        $token = isset($_POST['crovly-token']) ? sanitize_text_field(wp_unslash($_POST['crovly-token'])) : '';
        if (empty($token)) return false;

        // IP allowlist
        $ip = $this->get_client_ip();
        $allowlist = array_filter(array_map('trim', explode("\n", get_option('crovly_ip_allowlist', ''))));
        if (!empty($allowlist) && in_array($ip, $allowlist, true)) return true;

        $response = wp_remote_post('https://api.crovly.com/verify-token', [
            'timeout' => 10,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->secret_key,
                'Content-Type'  => 'application/json',
            ],
            'body' => wp_json_encode([
                'token'      => $token,
                'expectedIp' => $ip,
            ]),
        ]);

        if (is_wp_error($response)) return true; // fail open

        $body = json_decode(wp_remote_retrieve_body($response), true);
        return isset($body['success']) && $body['success'] === true;
    }

    private function get_client_ip() {
        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_REAL_IP', 'HTTP_X_FORWARDED_FOR'] as $h) {
            if (!empty($_SERVER[$h])) {
                $ip = sanitize_text_field(wp_unslash($_SERVER[$h]));
                if (strpos($ip, ',') !== false) $ip = trim(explode(',', $ip)[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
            }
        }
        return isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
    }

    private function fail_error() {
        $msg = get_option('crovly_error_message', '');
        return $msg ?: __('Captcha verification failed. Please try again.', 'crovly');
    }

    public function activation_redirect() {
        if (get_transient('crovly_activation_redirect')) {
            delete_transient('crovly_activation_redirect');
            if (!isset($_GET['activate-multi'])) {
                wp_safe_redirect(admin_url('options-general.php?page=crovly'));
                exit;
            }
        }
    }

    public function admin_notice_keys() {
        if (!current_user_can('manage_options')) return;
        if (!empty($this->site_key) && !empty($this->secret_key)) return;
        $url = admin_url('options-general.php?page=crovly');
        echo '<div class="notice notice-warning"><p>' . sprintf(
            wp_kses(
                /* translators: %s: settings page URL */
                __('<strong>Crovly</strong> needs your API keys to protect forms. <a href="%s">Configure now</a>.', 'crovly'),
                ['strong' => [], 'a' => ['href' => []]]
            ),
            esc_url($url)
        ) . '</p></div>';
    }

    public function add_cfasync_attr($tag, $handle) {
        if ($handle === 'crovly-widget') {
            $tag = str_replace('<script ', '<script data-cfasync="false" ', $tag);
        }
        return $tag;
    }

    public function ajax_test_connection() {
        check_ajax_referer('crovly_test_connection', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized.', 'crovly')]);
        }

        $site_key = defined('CROVLY_SITE_KEY') ? CROVLY_SITE_KEY : get_option('crovly_site_key', '');
        $secret_key = defined('CROVLY_SECRET_KEY') ? CROVLY_SECRET_KEY : get_option('crovly_secret_key', '');

        if (empty($site_key) || empty($secret_key)) {
            wp_send_json_error(['message' => __('Please enter both Site Key and Secret Key.', 'crovly')]);
        }

        $response = wp_remote_post('https://api.crovly.com/verify-token', [
            'timeout' => 10,
            'headers' => [
                'Authorization' => 'Bearer ' . $secret_key,
                'Content-Type'  => 'application/json',
            ],
            'body' => wp_json_encode(['token' => 'test_connection']),
        ]);

        if (is_wp_error($response)) {
            wp_send_json_error(['message' => __('Could not reach Crovly API.', 'crovly') . ' ' . $response->get_error_message()]);
        }

        $code = wp_remote_retrieve_response_code($response);

        if ($code === 401) {
            wp_send_json_error(['message' => __('Invalid Secret Key. Please check your credentials.', 'crovly')]);
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (is_array($body)) {
            wp_send_json_success(['message' => __('Connection successful! API is reachable and keys are valid.', 'crovly')]);
        }

        wp_send_json_error(['message' => __('Unexpected API response.', 'crovly')]);
    }

    // ═══════════════════════════════════════
    //  WordPress Core
    // ═══════════════════════════════════════

    private function init_wp_core() {
        if ($this->is_enabled('login')) {
            add_action('login_enqueue_scripts', [$this, 'enqueue_widget']);
            add_action('login_form', [$this, 'render_widget']);
            add_filter('wp_authenticate_user', [$this, 'wp_verify_login'], 10, 2);
        }
        if ($this->is_enabled('register')) {
            add_action('login_enqueue_scripts', [$this, 'enqueue_widget']);
            add_action('register_form', [$this, 'render_widget']);
            add_filter('registration_errors', [$this, 'wp_verify_register'], 10, 3);
        }
        if ($this->is_enabled('lostpassword')) {
            add_action('login_enqueue_scripts', [$this, 'enqueue_widget']);
            add_action('lostpassword_form', [$this, 'render_widget']);
            add_action('lostpassword_post', [$this, 'wp_verify_lostpassword']);
        }
        if ($this->is_enabled('comment')) {
            add_action('wp_enqueue_scripts', [$this, 'enqueue_widget']);
            add_action('comment_form_after_fields', [$this, 'render_widget']);
            add_action('comment_form_logged_in_after', [$this, 'render_widget']);
            add_filter('preprocess_comment', [$this, 'wp_verify_comment']);
        }
        if (is_multisite()) {
            add_action('signup_extra_fields', [$this, 'render_widget']);
            add_filter('wpmu_validate_user_signup', [$this, 'ms_verify_signup']);
        }
    }

    public function wp_verify_login($user, $password) {
        if (is_wp_error($user)) return $user;
        if (!$this->verify_token()) return new WP_Error('crovly_failed', '<strong>' . __('Error:', 'crovly') . '</strong> ' . $this->fail_error());
        return $user;
    }

    public function wp_verify_register($errors, $login, $email) {
        if (!$this->verify_token()) $errors->add('crovly_failed', '<strong>' . __('Error:', 'crovly') . '</strong> ' . $this->fail_error());
        return $errors;
    }

    public function wp_verify_lostpassword($errors) {
        if (!$this->verify_token()) $errors->add('crovly_failed', '<strong>' . __('Error:', 'crovly') . '</strong> ' . $this->fail_error());
        return $errors;
    }

    public function wp_verify_comment($commentdata) {
        if (!$this->verify_token()) {
            wp_die(esc_html($this->fail_error()), esc_html__('Crovly Verification Failed', 'crovly'), ['response' => 403, 'back_link' => true]);
        }
        return $commentdata;
    }

    public function ms_verify_signup($result) {
        if (!$this->verify_token()) {
            $result['errors']->add('crovly_failed', $this->fail_error());
        }
        return $result;
    }

    // ═══════════════════════════════════════
    //  WooCommerce
    // ═══════════════════════════════════════

    private function init_woocommerce() {
        if (!$this->is_enabled('woocommerce') || !class_exists('WooCommerce')) return;
        add_action('wp_enqueue_scripts', [$this, 'enqueue_widget']);
        // Checkout
        add_action('woocommerce_review_order_before_submit', [$this, 'render_widget']);
        add_action('woocommerce_checkout_process', [$this, 'woo_verify']);
        // Login
        add_action('woocommerce_login_form', [$this, 'render_widget']);
        add_filter('woocommerce_process_login_errors', [$this, 'woo_verify_login'], 10, 3);
        // Register
        add_action('woocommerce_register_form', [$this, 'render_widget']);
        add_filter('woocommerce_process_registration_errors', [$this, 'woo_verify_register'], 10, 4);
        // Lost password
        add_action('woocommerce_lostpassword_form', [$this, 'render_widget']);
        // Pay for order
        add_action('woocommerce_pay_order_before_submit', [$this, 'render_widget']);
        add_action('woocommerce_before_pay_action', [$this, 'woo_verify']);
    }

    public function woo_verify() {
        if (!$this->verify_token()) wc_add_notice($this->fail_error(), 'error');
    }

    public function woo_verify_login($validation_error, $username, $password) {
        if (!$this->verify_token()) $validation_error->add('crovly_failed', $this->fail_error());
        return $validation_error;
    }

    public function woo_verify_register($validation_error, $username, $password, $email) {
        if (!$this->verify_token()) $validation_error->add('crovly_failed', $this->fail_error());
        return $validation_error;
    }

    // ═══════════════════════════════════════
    //  Contact Form 7
    // ═══════════════════════════════════════

    private function init_contact_form_7() {
        if (!$this->is_enabled('cf7') || !defined('WPCF7_VERSION')) return;
        add_action('wp_enqueue_scripts', [$this, 'enqueue_widget']);
        add_filter('wpcf7_form_elements', [$this, 'cf7_inject']);
        add_filter('wpcf7_validate', [$this, 'cf7_verify'], 20, 2);
    }

    public function cf7_inject($content) {
        $widget = $this->get_widget_html();
        if (preg_match('/(<input[^>]*type=["\']submit["\'][^>]*>)/i', $content)) {
            $content = preg_replace('/(<input[^>]*type=["\']submit["\'][^>]*>)/i', $widget . '$1', $content, 1);
        } else {
            $content .= $widget;
        }
        return $content;
    }

    public function cf7_verify($result, $tags) {
        if (!$this->verify_token()) {
            $result->invalidate(isset($tags[0]) ? $tags[0] : (object)['name' => 'crovly'], $this->fail_error());
        }
        return $result;
    }

    // ═══════════════════════════════════════
    //  WPForms
    // ═══════════════════════════════════════

    private function init_wpforms() {
        if (!$this->is_enabled('wpforms') || !function_exists('wpforms')) return;
        add_action('wp_enqueue_scripts', [$this, 'enqueue_widget']);
        add_action('wpforms_frontend_output_before_submit', [$this, 'wpforms_render'], 10, 2);
        add_action('wpforms_process_before', [$this, 'wpforms_verify'], 10, 2);
    }

    public function wpforms_render($form_data, $form) { $this->render_widget(); }

    public function wpforms_verify($entry, $form_data) {
        if (!$this->verify_token()) wpforms()->process->errors[$form_data['id']]['header'] = $this->fail_error();
    }

    // ═══════════════════════════════════════
    //  Gravity Forms
    // ═══════════════════════════════════════

    private function init_gravity_forms() {
        if (!$this->is_enabled('gravityforms') || !class_exists('GFForms')) return;
        add_action('wp_enqueue_scripts', [$this, 'enqueue_widget']);
        add_filter('gform_submit_button', [$this, 'gf_inject'], 10, 2);
        add_filter('gform_validation', [$this, 'gf_verify']);
    }

    public function gf_inject($button, $form) { return $this->get_widget_html() . $button; }

    public function gf_verify($result) {
        if (!$this->verify_token()) {
            $result['is_valid'] = false;
            if (!empty($result['form']['fields'])) {
                $last = end($result['form']['fields']);
                $last->failed_validation = true;
                $last->validation_message = $this->fail_error();
            }
        }
        return $result;
    }

    // ═══════════════════════════════════════
    //  Elementor Pro Forms
    // ═══════════════════════════════════════

    private function init_elementor() {
        if (!$this->is_enabled('elementor') || !defined('ELEMENTOR_PRO_VERSION')) return;
        add_action('wp_enqueue_scripts', [$this, 'enqueue_widget']);
        add_action('elementor_pro/forms/render/item/crovly', [$this, 'elementor_render'], 10, 3);
        add_action('elementor_pro/forms/validation', [$this, 'elementor_verify'], 10, 2);
        add_filter('elementor_pro/forms/field_types', [$this, 'elementor_register']);
    }

    public function elementor_register($fields) { $fields['crovly'] = __('Crovly Captcha', 'crovly'); return $fields; }
    public function elementor_render($item, $idx, $form) { echo wp_kses($this->get_widget_html(), self::$kses_allowed); }

    public function elementor_verify($record, $handler) {
        if (!$this->verify_token()) $handler->add_error('crovly', $this->fail_error());
    }

    // ═══════════════════════════════════════
    //  Ninja Forms
    // ═══════════════════════════════════════

    private function init_ninja_forms() {
        if (!$this->is_enabled('ninjaforms') || !class_exists('Ninja_Forms')) return;
        add_action('wp_enqueue_scripts', [$this, 'enqueue_widget']);
        add_filter('ninja_forms_display_before_submit', [$this, 'nf_inject']);
        add_filter('ninja_forms_submit_data', [$this, 'nf_verify']);
    }

    public function nf_inject($content) { return $this->get_widget_html() . $content; }

    public function nf_verify($data) {
        if (!$this->verify_token()) $data['errors']['form']['crovly'] = $this->fail_error();
        return $data;
    }

    // ═══════════════════════════════════════
    //  Fluent Forms
    // ═══════════════════════════════════════

    private function init_fluent_forms() {
        if (!$this->is_enabled('fluentforms') || !defined('FLUENTFORM_VERSION')) return;
        add_action('fluentform_render_item_submit_button', [$this, 'ff_render'], 10, 2);
        add_filter('fluentform_validate_input', [$this, 'ff_verify'], 10, 5);
    }

    public function ff_render($btn, $form) { echo wp_kses($this->get_widget_html(), self::$kses_allowed); }

    public function ff_verify($errorMessage, $field, $formData, $fields, $form) {
        static $checked = null;
        if ($checked === null) $checked = $this->verify_token();
        if (!$checked) return $this->fail_error();
        return $errorMessage;
    }

    // ═══════════════════════════════════════
    //  Formidable Forms
    // ═══════════════════════════════════════

    private function init_formidable() {
        if (!$this->is_enabled('formidable') || !class_exists('FrmForm')) return;
        add_filter('frm_submit_button_html', [$this, 'formidable_inject'], 10, 2);
        add_filter('frm_validate_entry', [$this, 'formidable_verify'], 20, 2);
    }

    public function formidable_inject($html, $args) { return $this->get_widget_html() . $html; }

    public function formidable_verify($errors, $values) {
        if (!$this->verify_token()) $errors['crovly'] = $this->fail_error();
        return $errors;
    }

    // ═══════════════════════════════════════
    //  Forminator
    // ═══════════════════════════════════════

    private function init_forminator() {
        if (!$this->is_enabled('forminator') || !class_exists('Forminator')) return;
        add_filter('forminator_custom_form_submit_before_set_fields', [$this, 'forminator_verify'], 10, 3);
        add_action('forminator_before_form_render', [$this, 'forminator_before']);
        add_action('forminator_after_form_render', [$this, 'forminator_after']);
    }

    public function forminator_before($id) { ob_start(); }

    public function forminator_after($id) {
        $html = ob_get_clean();
        $widget = $this->get_widget_html();
        if (preg_match('/(<button[^>]*class="[^"]*forminator-button-submit[^"]*"[^>]*>)/i', $html, $m)) {
            $html = str_replace($m[0], $widget . $m[0], $html);
        } else {
            $html .= $widget;
        }
        echo wp_kses_post($html);
    }

    public function forminator_verify($can_submit, $id, $data) {
        if (!$this->verify_token()) {
            wp_send_json_error(['message' => $this->fail_error()]);
        }
        return $can_submit;
    }

    // ═══════════════════════════════════════
    //  Jetpack Contact Form
    // ═══════════════════════════════════════

    private function init_jetpack() {
        if (!$this->is_enabled('jetpack') || !defined('JETPACK__VERSION')) return;
        add_filter('jetpack_contact_form_html', [$this, 'jetpack_inject']);
        add_filter('jetpack_contact_form_is_spam', [$this, 'jetpack_verify'], 20, 2);
    }

    public function jetpack_inject($html) {
        $widget = $this->get_widget_html();
        return preg_replace('/(<button[^>]*type=["\']submit["\'][^>]*>)/i', $widget . '$1', $html, 1) ?: $html . $widget;
    }

    public function jetpack_verify($is_spam, $form) {
        if (!$this->verify_token()) return true;
        return $is_spam;
    }

    // ═══════════════════════════════════════
    //  Divi Builder
    // ═══════════════════════════════════════

    private function init_divi() {
        if (!$this->is_enabled('divi')) return;
        if (!defined('ET_BUILDER_VERSION') && !function_exists('et_setup_theme')) return;
        add_filter('et_pb_contact_form_submit_button', [$this, 'divi_inject']);
        add_filter('et_pb_contact_form_valid', [$this, 'divi_verify']);
        // Divi login form
        add_action('et_pb_login_form_after_fields', [$this, 'render_widget']);
    }

    public function divi_inject($button) { return $this->get_widget_html() . $button; }

    public function divi_verify($valid) {
        if (!$this->verify_token()) return false;
        return $valid;
    }

    // ═══════════════════════════════════════
    //  BuddyPress
    // ═══════════════════════════════════════

    private function init_buddypress() {
        if (!$this->is_enabled('buddypress') || !class_exists('BuddyPress')) return;
        add_action('wp_enqueue_scripts', [$this, 'enqueue_widget']);
        add_action('bp_before_registration_submit_buttons', [$this, 'render_widget']);
        add_action('bp_before_directory_activity_post_form_submit', [$this, 'render_widget']);
        add_action('bp_signup_validate', [$this, 'bp_verify']);
    }

    public function bp_verify() {
        if (!$this->verify_token()) buddypress()->signup->errors['crovly'] = $this->fail_error();
    }

    // ═══════════════════════════════════════
    //  bbPress
    // ═══════════════════════════════════════

    private function init_bbpress() {
        if (!$this->is_enabled('bbpress') || !class_exists('bbPress')) return;
        add_action('bbp_theme_before_topic_form_submit_wrapper', [$this, 'render_widget']);
        add_action('bbp_theme_before_reply_form_submit_wrapper', [$this, 'render_widget']);
        add_action('bbp_new_topic_pre_extras', [$this, 'bbp_verify']);
        add_action('bbp_new_reply_pre_extras', [$this, 'bbp_verify']);
    }

    public function bbp_verify() {
        if (!$this->verify_token()) bbp_add_error('crovly_failed', '<strong>' . __('Error:', 'crovly') . '</strong> ' . $this->fail_error());
    }

    // ═══════════════════════════════════════
    //  Ultimate Member
    // ═══════════════════════════════════════

    private function init_ultimate_member() {
        if (!$this->is_enabled('ultimatemember') || !class_exists('UM')) return;
        add_action('um_after_login_fields', [$this, 'render_widget']);
        add_action('um_after_register_fields', [$this, 'render_widget']);
        add_action('um_after_password_reset_fields', [$this, 'render_widget']);
        add_action('um_submit_form_errors_hook_login', [$this, 'um_verify'], 30, 1);
        add_action('um_submit_form_errors_hook_', [$this, 'um_verify'], 30, 1);
    }

    public function um_verify($args) {
        if (!$this->verify_token()) {
            UM()->form()->add_error('crovly', $this->fail_error());
        }
    }

    // ═══════════════════════════════════════
    //  MemberPress
    // ═══════════════════════════════════════

    private function init_memberpress() {
        if (!$this->is_enabled('memberpress') || !defined('MEPR_VERSION')) return;
        add_action('mepr-checkout-before-submit', [$this, 'render_widget']);
        add_action('mepr-login-form-before-submit', [$this, 'render_widget']);
        add_filter('mepr-validate-signup', [$this, 'mepr_verify']);
        add_filter('mepr-validate-login', [$this, 'mepr_verify']);
    }

    public function mepr_verify($errors) {
        if (!$this->verify_token()) $errors[] = $this->fail_error();
        return $errors;
    }

    // ═══════════════════════════════════════
    //  Paid Memberships Pro
    // ═══════════════════════════════════════

    private function init_paid_memberships_pro() {
        if (!$this->is_enabled('pmpro') || !defined('PMPRO_VERSION')) return;
        add_action('pmpro_checkout_before_submit_button', [$this, 'render_widget']);
        add_filter('pmpro_registration_checks', [$this, 'pmpro_verify']);
    }

    public function pmpro_verify($ok) {
        if (!$this->verify_token()) {
            pmpro_setMessage($this->fail_error(), 'pmpro_error');
            return false;
        }
        return $ok;
    }

    // ═══════════════════════════════════════
    //  Easy Digital Downloads
    // ═══════════════════════════════════════

    private function init_edd() {
        if (!$this->is_enabled('edd') || !class_exists('Easy_Digital_Downloads')) return;
        add_action('edd_purchase_form_before_submit', [$this, 'render_widget']);
        add_action('edd_register_form_fields_before_submit', [$this, 'render_widget']);
        add_action('edd_login_fields_after', [$this, 'render_widget']);
        add_filter('edd_checkout_error_checks', [$this, 'edd_verify'], 10, 2);
    }

    public function edd_verify($valid_data, $post) {
        if (!$this->verify_token()) edd_set_error('crovly_failed', $this->fail_error());
        return $valid_data;
    }

    // ═══════════════════════════════════════
    //  Mailchimp for WordPress (MC4WP)
    // ═══════════════════════════════════════

    private function init_mailchimp() {
        if (!$this->is_enabled('mc4wp') || !defined('MC4WP_VERSION')) return;
        add_filter('mc4wp_form_content', [$this, 'mc4wp_inject']);
        add_filter('mc4wp_valid_form_request', [$this, 'mc4wp_verify'], 10, 2);
    }

    public function mc4wp_inject($content) {
        $widget = $this->get_widget_html();
        return preg_replace('/(<input[^>]*type=["\']submit["\'][^>]*>)/i', $widget . '$1', $content, 1) ?: $content . $widget;
    }

    public function mc4wp_verify($valid, $form) {
        if (!$this->verify_token()) {
            $form->add_error('crovly_failed');
            $form->errors['crovly_failed'] = $this->fail_error();
            return false;
        }
        return $valid;
    }

    // ═══════════════════════════════════════
    //  GiveWP
    // ═══════════════════════════════════════

    private function init_givewp() {
        if (!$this->is_enabled('givewp') || !class_exists('Give')) return;
        add_action('give_donation_form_before_submit', [$this, 'render_widget']);
        add_action('give_checkout_error_checks', [$this, 'givewp_verify'], 10, 2);
    }

    public function givewp_verify($valid_data, $post) {
        if (!$this->verify_token()) give_set_error('crovly_failed', $this->fail_error());
    }

    // ═══════════════════════════════════════
    //  wpDiscuz
    // ═══════════════════════════════════════

    private function init_wpdiscuz() {
        if (!$this->is_enabled('wpdiscuz') || !class_exists('WpdiscuzCore')) return;
        add_action('wpdiscuz_comment_form_footer', [$this, 'render_widget']);
        add_filter('wpdiscuz_before_save_comment', [$this, 'wpdiscuz_verify']);
    }

    public function wpdiscuz_verify($commentdata) {
        if (!$this->verify_token()) {
            wp_die(esc_html($this->fail_error()), '', ['response' => 403, 'back_link' => true]);
        }
        return $commentdata;
    }

    // ═══════════════════════════════════════
    //  wpForo
    // ═══════════════════════════════════════

    private function init_wpforo() {
        if (!$this->is_enabled('wpforo') || !function_exists('WPF')) return;
        add_action('wpforo_topic_form_buttons_hook', [$this, 'render_widget']);
        add_action('wpforo_reply_form_buttons_hook', [$this, 'render_widget']);
        add_filter('wpforo_before_add_topic', [$this, 'wpforo_verify']);
        add_filter('wpforo_before_add_reply', [$this, 'wpforo_verify']);
    }

    public function wpforo_verify($data) {
        if (!$this->verify_token()) {
            WPF()->notice->add($this->fail_error(), 'error');
            return false;
        }
        return $data;
    }

    // ═══════════════════════════════════════
    //  Admin Settings
    // ═══════════════════════════════════════

    private static function get_all_forms() {
        $forms = [
            __('WordPress', 'crovly') => [
                'login'        => __('Login', 'crovly'),
                'register'     => __('Registration', 'crovly'),
                'lostpassword' => __('Lost Password', 'crovly'),
                'comment'      => __('Comments', 'crovly'),
            ],
        ];

        // Conditionally show plugin integrations
        $plugins = [
            'WooCommerce' => [
                'check' => class_exists('WooCommerce'),
                'forms' => ['woocommerce' => __('Checkout, Login, Register, Lost Password, Pay for Order', 'crovly')],
            ],
            'Contact Form 7' => [
                'check' => defined('WPCF7_VERSION'),
                'forms' => ['cf7' => __('All forms', 'crovly')],
            ],
            'WPForms' => [
                'check' => function_exists('wpforms'),
                'forms' => ['wpforms' => __('All forms', 'crovly')],
            ],
            'Gravity Forms' => [
                'check' => class_exists('GFForms'),
                'forms' => ['gravityforms' => __('All forms', 'crovly')],
            ],
            'Elementor Pro' => [
                'check' => defined('ELEMENTOR_PRO_VERSION'),
                'forms' => ['elementor' => __('All forms', 'crovly')],
            ],
            'Ninja Forms' => [
                'check' => class_exists('Ninja_Forms'),
                'forms' => ['ninjaforms' => __('All forms', 'crovly')],
            ],
            'Fluent Forms' => [
                'check' => defined('FLUENTFORM_VERSION'),
                'forms' => ['fluentforms' => __('All forms', 'crovly')],
            ],
            'Formidable Forms' => [
                'check' => class_exists('FrmForm'),
                'forms' => ['formidable' => __('All forms', 'crovly')],
            ],
            'Forminator' => [
                'check' => class_exists('Forminator'),
                'forms' => ['forminator' => __('All forms', 'crovly')],
            ],
            'Jetpack' => [
                'check' => defined('JETPACK__VERSION'),
                'forms' => ['jetpack' => __('Contact forms', 'crovly')],
            ],
            'Divi' => [
                'check' => defined('ET_BUILDER_VERSION') || function_exists('et_setup_theme'),
                'forms' => ['divi' => __('Contact Form, Login', 'crovly')],
            ],
            'BuddyPress' => [
                'check' => class_exists('BuddyPress'),
                'forms' => ['buddypress' => __('Registration, Activity Post', 'crovly')],
            ],
            'bbPress' => [
                'check' => class_exists('bbPress'),
                'forms' => ['bbpress' => __('New Topic, Reply', 'crovly')],
            ],
            'Ultimate Member' => [
                'check' => class_exists('UM'),
                'forms' => ['ultimatemember' => __('Login, Register, Password Reset', 'crovly')],
            ],
            'MemberPress' => [
                'check' => defined('MEPR_VERSION'),
                'forms' => ['memberpress' => __('Checkout, Login', 'crovly')],
            ],
            'Paid Memberships Pro' => [
                'check' => defined('PMPRO_VERSION'),
                'forms' => ['pmpro' => __('Checkout', 'crovly')],
            ],
            'Easy Digital Downloads' => [
                'check' => class_exists('Easy_Digital_Downloads'),
                'forms' => ['edd' => __('Checkout, Login, Register', 'crovly')],
            ],
            'Mailchimp for WP' => [
                'check' => defined('MC4WP_VERSION'),
                'forms' => ['mc4wp' => __('Signup forms', 'crovly')],
            ],
            'GiveWP' => [
                'check' => class_exists('Give'),
                'forms' => ['givewp' => __('Donation forms', 'crovly')],
            ],
            'wpDiscuz' => [
                'check' => class_exists('WpdiscuzCore'),
                'forms' => ['wpdiscuz' => __('Comments', 'crovly')],
            ],
            'wpForo' => [
                'check' => function_exists('WPF'),
                'forms' => ['wpforo' => __('New Topic, Reply', 'crovly')],
            ],
        ];

        foreach ($plugins as $name => $cfg) {
            if ($cfg['check']) {
                $forms[$name] = $cfg['forms'];
            }
        }

        return $forms;
    }

    public function add_settings_page() {
        add_options_page(__('Crovly Settings', 'crovly'), __('Crovly', 'crovly'), 'manage_options', 'crovly', [$this, 'render_settings_page']);
    }

    public function add_settings_link($links) {
        array_unshift($links, '<a href="' . esc_url(admin_url('options-general.php?page=crovly')) . '">' . __('Settings', 'crovly') . '</a>');
        return $links;
    }

    public function register_settings() {
        register_setting('crovly_settings', 'crovly_site_key', ['sanitize_callback' => 'sanitize_text_field']);
        register_setting('crovly_settings', 'crovly_secret_key', ['sanitize_callback' => 'sanitize_text_field']);
        register_setting('crovly_settings', 'crovly_theme', ['sanitize_callback' => 'sanitize_text_field']);
        register_setting('crovly_settings', 'crovly_error_message', ['sanitize_callback' => 'sanitize_text_field']);
        register_setting('crovly_settings', 'crovly_skip_logged_in', ['sanitize_callback' => 'sanitize_text_field']);
        register_setting('crovly_settings', 'crovly_ip_allowlist', ['sanitize_callback' => 'sanitize_textarea_field']);
        register_setting('crovly_settings', 'crovly_enabled_forms', [
            'sanitize_callback' => function ($value) {
                return is_array($value) ? array_map('sanitize_text_field', $value) : [];
            }
        ]);
        register_setting('crovly_settings', 'crovly_delete_data', [
            'sanitize_callback' => function ($value) {
                return $value ? '1' : '0';
            }
        ]);
    }

    public function render_settings_page() {
        if (!current_user_can('manage_options')) return;

        $site_key = defined('CROVLY_SITE_KEY') ? CROVLY_SITE_KEY : get_option('crovly_site_key', '');
        $secret_key = defined('CROVLY_SECRET_KEY') ? CROVLY_SECRET_KEY : get_option('crovly_secret_key', '');
        $theme = get_option('crovly_theme', 'auto');
        $error_msg = get_option('crovly_error_message', '');
        $skip = get_option('crovly_skip_logged_in', 'none');
        $allowlist = get_option('crovly_ip_allowlist', '');
        $enabled = get_option('crovly_enabled_forms', ['login', 'register', 'lostpassword', 'comment']);
        if (!is_array($enabled)) $enabled = [];
        $form_groups = self::get_all_forms();
        ?>
        <div class="wrap">
            <h1>
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:6px"><path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/><path d="m9 12 2 2 4-4"/></svg>
                <?php esc_html_e('Crovly Settings', 'crovly'); ?>
            </h1>
            <p><?php echo wp_kses(__('Privacy-first captcha powered by Proof of Work. Get your keys at <a href="https://app.crovly.com" target="_blank">app.crovly.com</a>.', 'crovly'), ['a' => ['href' => [], 'target' => []]]); ?></p>

            <form method="post" action="options.php">
                <?php settings_fields('crovly_settings'); ?>

                <h2><?php esc_html_e('API Keys', 'crovly'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th><label for="crovly_site_key"><?php esc_html_e('Site Key', 'crovly'); ?></label></th>
                        <td><input type="text" id="crovly_site_key" name="crovly_site_key" value="<?php echo esc_attr($site_key); ?>" class="regular-text" placeholder="crvl_site_..." <?php if (defined('CROVLY_SITE_KEY') || defined('CROVLY_SECRET_KEY')) echo esc_attr('readonly'); ?> /></td>
                    </tr>
                    <tr>
                        <th><label for="crovly_secret_key"><?php esc_html_e('Secret Key', 'crovly'); ?></label></th>
                        <td><input type="password" id="crovly_secret_key" name="crovly_secret_key" value="<?php echo esc_attr($secret_key); ?>" class="regular-text" placeholder="crvl_secret_..." <?php if (defined('CROVLY_SITE_KEY') || defined('CROVLY_SECRET_KEY')) echo esc_attr('readonly'); ?> /></td>
                    </tr>
                    <tr>
                        <th></th>
                        <td>
                            <?php if (defined('CROVLY_SITE_KEY') || defined('CROVLY_SECRET_KEY')) : ?>
                                <p class="description" style="color:#2271b1;margin-bottom:8px"><?php esc_html_e('Keys are defined in wp-config.php and cannot be changed here.', 'crovly'); ?></p>
                            <?php endif; ?>
                            <button type="button" id="crovly-test-btn" class="button button-secondary"><?php esc_html_e('Test Connection', 'crovly'); ?></button>
                            <span id="crovly-test-result" style="margin-left:10px;font-weight:500"></span>
                        </td>
                    </tr>
                </table>

                <h2><?php esc_html_e('Appearance', 'crovly'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th><label for="crovly_theme"><?php esc_html_e('Theme', 'crovly'); ?></label></th>
                        <td>
                            <select id="crovly_theme" name="crovly_theme">
                                <option value="auto" <?php selected($theme, 'auto'); ?>><?php esc_html_e('Auto (match system)', 'crovly'); ?></option>
                                <option value="light" <?php selected($theme, 'light'); ?>><?php esc_html_e('Light', 'crovly'); ?></option>
                                <option value="dark" <?php selected($theme, 'dark'); ?>><?php esc_html_e('Dark', 'crovly'); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="crovly_error_message"><?php esc_html_e('Error Message', 'crovly'); ?></label></th>
                        <td>
                            <input type="text" id="crovly_error_message" name="crovly_error_message" value="<?php echo esc_attr($error_msg); ?>" class="regular-text" placeholder="<?php esc_attr_e('Captcha verification failed. Please try again.', 'crovly'); ?>" />
                            <p class="description"><?php esc_html_e('Leave blank for default message.', 'crovly'); ?></p>
                        </td>
                    </tr>
                </table>

                <h2><?php esc_html_e('Protected Forms', 'crovly'); ?></h2>
                <table class="form-table">
                    <tr>
                        <td colspan="2">
                            <fieldset>
                                <?php foreach ($form_groups as $group => $forms) : ?>
                                    <strong style="display:block;margin:12px 0 4px;color:#1d2327"><?php echo esc_html($group); ?></strong>
                                    <?php foreach ($forms as $key => $label) : ?>
                                        <label style="display:block;margin:2px 0 2px 16px">
                                            <input type="checkbox" name="crovly_enabled_forms[]" value="<?php echo esc_attr($key); ?>" <?php checked(in_array($key, $enabled, true)); ?> />
                                            <?php echo esc_html($label); ?>
                                        </label>
                                    <?php endforeach; ?>
                                <?php endforeach; ?>
                            </fieldset>
                            <p class="description" style="margin-top:12px"><?php esc_html_e('Only installed and active plugins are shown above.', 'crovly'); ?></p>
                        </td>
                    </tr>
                </table>

                <h2><?php esc_html_e('Advanced', 'crovly'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th><?php esc_html_e('Skip for Logged-in Users', 'crovly'); ?></th>
                        <td>
                            <select name="crovly_skip_logged_in">
                                <option value="none" <?php selected($skip, 'none'); ?>><?php esc_html_e('No — verify everyone', 'crovly'); ?></option>
                                <option value="admins" <?php selected($skip, 'admins'); ?>><?php esc_html_e('Administrators only', 'crovly'); ?></option>
                                <option value="all" <?php selected($skip, 'all'); ?>><?php esc_html_e('All logged-in users', 'crovly'); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="crovly_ip_allowlist"><?php esc_html_e('IP Allowlist', 'crovly'); ?></label></th>
                        <td>
                            <textarea id="crovly_ip_allowlist" name="crovly_ip_allowlist" rows="4" class="regular-text" placeholder="192.168.1.1&#10;10.0.0.0"><?php echo esc_textarea($allowlist); ?></textarea>
                            <p class="description"><?php esc_html_e('One IP per line. These IPs skip captcha verification.', 'crovly'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Delete data on uninstall', 'crovly'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="crovly_delete_data" value="1" <?php checked(get_option('crovly_delete_data', '0'), '1'); ?> />
                                <?php esc_html_e('Remove all Crovly settings when the plugin is deleted. Does not apply to deactivation.', 'crovly'); ?>
                            </label>
                        </td>
                    </tr>
                </table>

                <h2><?php esc_html_e('Shortcode & PHP', 'crovly'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th><?php esc_html_e('Shortcode', 'crovly'); ?></th>
                        <td>
                            <code>[crovly]</code> &mdash; <?php esc_html_e('Add to any page/post', 'crovly'); ?><br>
                            <code>[crovly theme="dark"]</code> &mdash; <?php esc_html_e('With theme override', 'crovly'); ?>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('PHP Function', 'crovly'); ?></th>
                        <td>
                            <code>&lt;?php Crovly_Plugin::instance()-&gt;render_widget(); ?&gt;</code><br>
                            <code>&lt;?php Crovly_Plugin::instance()-&gt;verify_token(); // returns bool ?&gt;</code>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('wp-config.php', 'crovly'); ?></th>
                        <td>
                            <code>define('CROVLY_SITE_KEY', 'crvl_site_...');</code><br>
                            <code>define('CROVLY_SECRET_KEY', 'crvl_secret_...');</code><br>
                            <code>define('CROVLY_DISABLE', true);</code> &mdash; <small><?php esc_html_e('emergency bypass', 'crovly'); ?></small>
                            <p class="description"><?php esc_html_e('Constants override database settings. Use CROVLY_DISABLE to bypass all verification (e.g. lockout recovery).', 'crovly'); ?></p>
                        </td>
                    </tr>
                </table>

                <?php submit_button(); ?>
            </form>
            <script>
            document.getElementById('crovly-test-btn').addEventListener('click', function() {
                var btn = this, res = document.getElementById('crovly-test-result');
                btn.disabled = true;
                res.textContent = '<?php echo esc_js(__('Testing...', 'crovly')); ?>';
                res.style.color = '#666';
                var fd = new FormData();
                fd.append('action', 'crovly_test_connection');
                fd.append('nonce', '<?php echo esc_js(wp_create_nonce('crovly_test_connection')); ?>');
                fetch(ajaxurl, {method:'POST', body:fd})
                    .then(function(r){return r.json()})
                    .then(function(r){
                        res.textContent = r.data.message;
                        res.style.color = r.success ? '#00a32a' : '#d63638';
                        btn.disabled = false;
                    })
                    .catch(function(){
                        res.textContent = '<?php echo esc_js(__('Request failed.', 'crovly')); ?>';
                        res.style.color = '#d63638';
                        btn.disabled = false;
                    });
            });
            </script>
        </div>
        <?php
    }
}

// Global helper function for theme developers
function crovly_render() {
    Crovly_Plugin::instance()->render_widget();
}

function crovly_verify() {
    return Crovly_Plugin::instance()->verify_token();
}

// WooCommerce HPOS compatibility
add_action('before_woocommerce_init', function () {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});

// Activation
register_activation_hook(__FILE__, function () {
    set_transient('crovly_activation_redirect', true, 30);
});

// Initialize
Crovly_Plugin::instance();
