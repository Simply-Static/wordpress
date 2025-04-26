<?php

namespace Simply_Static_Studio;

class Helper {

	public static function getPublicUrl() {
		// Get the public URL by getting the current URL and removing the prefix "wp."
		$public_url = str_replace( 'wp.', '', get_site_url() );

		/**
		 * @todo Make sure to get the domain here as well later.
		 */

		return $public_url;
	}

	public static function getSubdomainString() {
		$site_url = get_site_url();
		$parts    = explode( '.', $site_url );

		/**
		 * wp.subdomain.static.studio
		 * [0] - wp
		 * [1] - subdmain
		 * [2] - static
		 * [3] - studio
		 */
		return $parts[1];
	}


	/**
	 * Returns the global $wp_filesystem with credentials set.
	 * Returns null in case of any errors.
	 *
	 * @return \WP_Filesystem_Base|null
	 */
	public static function getFileSystem() {
		global $wp_filesystem;

		$success = true;

		// Initialize the file system if it has not been done yet.
		if ( ! $wp_filesystem ) {
			require_once ABSPATH . '/wp-admin/includes/file.php';

			$constants = array(
				'hostname'    => 'FTP_HOST',
				'username'    => 'FTP_USER',
				'password'    => 'FTP_PASS',
				'public_key'  => 'FTP_PUBKEY',
				'private_key' => 'FTP_PRIKEY',
			);

			$credentials = array();

			// We provide credentials based on wp-config.php constants.
			// Reference https://developer.wordpress.org/apis/wp-config-php/#wordpress-upgrade-constants
			foreach ( $constants as $key => $constant ) {
				if ( defined( $constant ) ) {
					$credentials[ $key ] = constant( $constant );
				}
			}

			$success = WP_Filesystem( $credentials );
		}

		if ( ! $success || $wp_filesystem->errors->has_errors() ) {
			return null;
		}

		return $wp_filesystem;
	}
}