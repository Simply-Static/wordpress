<?php
/**
 * Plugin Name:       Simply Static Studio Helper
 * Plugin URI:        https://static.studio
 * Description:       A helper plugin to integrate with Static Studio
 * Version:           1.0.1
 * Author:            Simply Static
 * Author URI:        https://simplystatic.com
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       simply-static-studio-helper
 * Domain Path:       /languages
 */

// Disable automatic core updates
add_filter('automatic_updater_disabled', '__return_true');

// Disable automatic theme updates
add_filter('auto_update_theme', '__return_false');

// Disable automatic plugin updates
add_filter('auto_update_plugin', '__return_false');

if (defined('WP_CLI') && WP_CLI) {
    include_once __DIR__ . '/wasmer/class-wasmer-aio-install-command.php';
}

require_once  __DIR__  . '/wasmer/simply-static-studio-helper/simply-static-studio-helper.php';
require_once __DIR__ . '/wasmer/wasmer.php';

// require_once __DIR__ . '/hostinger/hostinger.php';