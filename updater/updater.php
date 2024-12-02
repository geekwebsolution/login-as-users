<?php

if (!defined('ABSPATH')) exit;

/**
 * License manager module
 */
function gwslau_updater_utility() {
    $prefix = 'GWSLAU_';
    $settings = [
        'prefix' => $prefix,
        'get_base' => GWSLAU_PLUGIN_BASENAME,
        'get_slug' => GWSLAU_PLUGIN_DIR,
        'get_version' => GWSLAU_BUILD,
        'get_api' => 'https://download.geekcodelab.com/',
        'license_update_class' => $prefix . 'Update_Checker'
    ];

    return $settings;
}

// register_activation_hook(__FILE__, 'gwslau_updater_activate');
function gwslau_updater_activate() {

    // Refresh transients
    delete_site_transient('update_plugins');
    delete_transient('gwslau_plugin_updates');
    delete_transient('gwslau_plugin_auto_updates');
}

require_once(GWSLAU_PLUGIN_DIR_PATH . 'updater/class-update-checker.php');
