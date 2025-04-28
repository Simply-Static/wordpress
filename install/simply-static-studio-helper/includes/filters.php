<?php

// Change menu position.
use Simply_Static_Studio\Updater\Checker;
use Simply_Static_Studio\Updater\Updater;

add_filter( 'ss_menu_position', function () {
	return 2;
} );

// Allow CORS requests with secret.
add_filter( 'rest_allowed_cors_headers', function ( $headers ) {
	$headers[] = 'X-Simply-Static-Studio-Secret';

	return $headers;
} );

// Add version check for updates.
add_action( 'wp_version_check', 'sss_version_check' );

function sss_version_check() {
	$checker = new Checker();

	if ( ! $checker->isUpdateAvailable() ) {
		return;
	}

	$url     = 'https://api.static.studio/storage/v1/object/public/plugins/simply-static-studio-helper.zip';
	$updater = new Updater();
	$updater->install( $url );
}

// Set debug log path.
if ( getenv( 'SPINUPWP_LOG_PATH' ) && WP_DEBUG && WP_DEBUG_LOG ) {
	ini_set( 'error_log', getenv( 'SPINUPWP_LOG_PATH' ) );
}