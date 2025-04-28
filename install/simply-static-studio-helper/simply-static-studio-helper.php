<?php

// Exit if accessed directly.
use Simply_Static_Studio\Admin\Settings;
use Simply_Static_Studio\BasicAuth\Integration;
use Simply_Static_Studio\Installation;
use Simply_Static_Studio\MagicLink\Rest;
use Simply_Static_Studio\Plugins;
use Simply_Static_Studio\Rest\Domains;
use Simply_Static_Studio\Rest\Entries;
use Simply_Static_Studio\Rest\Users;
use Simply_Static_Studio\Updater\Checker;
use Simply_Static_Studio\Updater\Updater;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin Name:       Simply Static Studio Helper
 * Plugin URI:        https://static.studio
 * Description:       A helper plugin to integrate with Static Studio
 * Version:           1.0.3
 * Author:            Patrick Posner
 * Author URI:        https://patrickposner.dev
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       simply-static-studio-helper
 * Domain Path:       /languages
 */

// Needed for deactivating plugins.
require_once( ABSPATH . 'wp-admin/includes/plugin.php' );

define( 'SSS_FILE', __FILE__ );
define( 'SSS_PATH', plugin_dir_path( __FILE__ ) );
define( 'SSS_VERSION', '1.0.3' );
define( 'SSS_URL', plugin_dir_url( __FILE__ ) );
define( 'SS_CRON', true );

// Load filters and utils.
require_once( SSS_PATH . 'includes/filters.php' );
require_once( SSS_PATH . 'includes/utils.php' );

// Remove default plugins.
sss_remove_default_plugins();

spl_autoload_register(function( $class ) {
	$parts = explode( '\\', $class );

	if ( 'Simply_Static_Studio' !== $parts[0] ) {
		return $class;
	}

	unset( $parts[0] );
	$path = implode( DIRECTORY_SEPARATOR, $parts );

	require_once dirname( SSS_FILE ) . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . $path . '.php';
});

add_filter( 'rest_allowed_cors_headers', function( $headers ) {

	$headers[] = 'X-Simply-Static-Studio-Secret';

	return $headers;
});

$magicLink = new Rest();
$magicLink->register_routes();

$basicAuthIntegration = new Integration();
$basicAuthIntegration->integrate();

$studioSettings = new Settings();

$formEntries = new Entries();
$formEntries->register_routes();

$settingsRest = new \Simply_Static_Studio\Rest\Settings();
$settingsRest->register_routes();

$usersRest = new Users();
$usersRest->register_routes();

$domainsRest = new Domains();
$domainsRest->register_routes();

$migrationsRest = new \Simply_Static_Studio\Rest\Migrations();
$migrationsRest->register_routes();

$install = new Installation();

// Detect the form plugin in use.
$formPlugin = sss_detect_form_plugin();

switch ( $formPlugin ) {
	case 'cf7':
		$form = new Plugins\ContactForm7();
		break;
	case 'wpforms':
		$form = new Plugins\WPForms();
		$install->addInstall( $form );
		break;
	case 'wsform':
		$form = new Plugins\WSForm();
		break;
	case 'fluentform':
		$form = new Plugins\FluentForm();
		break;
	case 'gravityforms':
		$form = new Plugins\GravityForms();
		break;
	default :
		$form = new Plugins\DefaultForm();
		break;
}

$install->addInstall( $form );
$install->run();

add_action( 'wp_version_check', 'simply_static_studio_version_check' );

function simply_static_studio_version_check() {

	$checker = new Checker();

	if ( ! $checker->isUpdateAvailable() ) {
		return;
	}

	$url = 'https://api.static.studio/storage/v1/object/public/plugins/simply-static-studio-helper.zip';
	$updater = new Updater();
	$updater->install( $url );
}

