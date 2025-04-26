<?php

// Needed for deactivating plugins.
require_once( ABSPATH . 'wp-admin/includes/plugin.php' );

/**
 * Recursively remove a directory.
 *
 * @param string $src The directory to remove.
 */
function sss_rrm_dir( string $src ): void {
	$dir = opendir( $src );
	while ( false !== ( $file = readdir( $dir ) ) ) {
		if ( ( $file != '.' ) && ( $file != '..' ) ) {
			$full = $src . '/' . $file;
			if ( is_dir( $full ) ) {
				sss_rrm_dir( $full );
			} else {
				unlink( $full );
			}
		}
	}
	closedir( $dir );
	rmdir( $src );
}

/**
 * Detect the form plugin in use.
 *
 * @return false|string
 */
function sss_detect_form_plugin() {
	$plugins = get_option( 'active_plugins' );

	if ( in_array( 'contact-form-7/wp-contact-form-7.php', $plugins, true ) ) {
		return 'cf7';
	}

	if ( in_array( 'wpforms-lite/wpforms.php', $plugins, true ) ) {
		return 'wpforms';
	}

	if ( in_array( 'ws-form/ws-form.php', $plugins, true ) ) {
		return 'wsform';
	}

	if ( in_array( 'fluentform/fluentform.php', $plugins, true ) ) {
		return 'fluentform';
	}

	if ( in_array( 'gravityforms/gravityforms.php', $plugins, true ) ) {
		return 'gravityforms';
	}

	return 'wpforms';
}

function sss_remove_default_plugins() {
	if ( file_exists( ABSPATH . 'wp-content/plugins/spinupwp/' ) ) {
		deactivate_plugins( 'spinupwp/spinupwp.php' );

		sss_rrm_dir( ABSPATH . 'wp-content/plugins/spinupwp/' );
	}

	if ( file_exists( ABSPATH . 'wp-content/mu-plugins/spinupwp-debug-log-path.php' ) ) {
		// Delete file.
		unlink( ABSPATH . 'wp-content/mu-plugins/spinupwp-debug-log-path.php' );
	}
}