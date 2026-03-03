<?php
/**
 * Crovly Uninstall
 *
 * Removes all plugin data when the plugin is deleted via WordPress admin.
 * Only runs if the user has opted in via Settings > Crovly > "Delete data on uninstall".
 *
 * @package Crovly
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

if (get_option('crovly_delete_data') !== '1') {
    return;
}

$crovly_options = [
    'crovly_site_key',
    'crovly_secret_key',
    'crovly_theme',
    'crovly_error_message',
    'crovly_skip_logged_in',
    'crovly_ip_allowlist',
    'crovly_enabled_forms',
    'crovly_delete_data',
];

if (is_multisite()) {
    $sites = get_sites(['fields' => 'ids', 'number' => 0]);
    foreach ($sites as $blog_id) {
        switch_to_blog($blog_id);
        foreach ($crovly_options as $option) {
            delete_option($option);
        }
        restore_current_blog();
    }
} else {
    foreach ($crovly_options as $option) {
        delete_option($option);
    }
}

delete_transient('crovly_activation_redirect');
