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

if ( file_exists( WP_CONTENT_DIR . '/mu-plugins/simply-static-studio-helper/simply-static-studio-helper.php' ) ) {
	require_once  'simply-static-studio-helper/simply-static-studio-helper.php';
}

