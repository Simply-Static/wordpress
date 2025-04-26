<?php

namespace Simply_Static_Studio\Updater;

if ( ! class_exists( 'WP_Upgrader' ) ) {
	require_once  ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
}

if ( ! class_exists( 'Plugin_Upgrader' ) ) {
	require_once  ABSPATH . 'wp-admin/includes/class-plugin-upgrader.php';
}

class Updater extends \Plugin_Upgrader {

	/**
	 * Install a plugin package.
	 *
	 * @since 2.8.0
	 * @since 3.7.0 The `$args` parameter was added, making clearing the plugin update cache optional.
	 *
	 * @param string $package The full local path or URI of the package.
	 * @param array  $args {
	 *     Optional. Other arguments for installing a plugin package. Default empty array.
	 *
	 *     @type bool $clear_update_cache Whether to clear the plugin updates cache if successful.
	 *                                    Default true.
	 * }
	 * @return bool|\WP_Error True if the installation was successful, false or a WP_Error otherwise.
	 */
	public function install( $package, $args = array() ) {

		$package_basename = base64_decode( $package );
		$package_basename = str_replace( '.zip', '', $package_basename );

		$defaults    = array(
			'clear_update_cache' => true,
			'overwrite_package'  => true, // Do not overwrite files.
			'destination'        => WP_CONTENT_DIR . '/mu-plugins/' . $package_basename
		);
		$parsed_args = wp_parse_args( $args, $defaults );

		$this->init();
		$this->install_strings();

		add_filter( 'upgrader_source_selection', array( $this, 'check_package' ) );

		if ( $parsed_args['clear_update_cache'] ) {
			// Clear cache so wp_update_plugins() knows about the new plugin.
			add_action( 'upgrader_process_complete', 'wp_clean_plugins_cache', 9, 0 );
		}

		$this->run(
			array(
				'package'           => $package,
				'destination'       => $parsed_args['destination'],
				'clear_destination' => $parsed_args['overwrite_package'],
				'clear_working'     => false,
				'hook_extra'        => array(
					'type'   => 'plugin',
					'action' => 'install',
				),
			)
		);

		remove_action( 'upgrader_process_complete', 'wp_clean_plugins_cache', 9 );
		remove_filter( 'upgrader_source_selection', array( $this, 'check_package' ) );

		if ( ! $this->result || is_wp_error( $this->result ) ) {
			return $this->result;
		}

		// Force refresh of plugin update information.
		wp_clean_plugins_cache( $parsed_args['clear_update_cache'] );

		if ( $parsed_args['overwrite_package'] ) {
			/**
			 * Fires when the upgrader has successfully overwritten a currently installed
			 * plugin or theme with an uploaded zip package.
			 *
			 * @since 5.5.0
			 *
			 * @param string  $package      The package file.
			 * @param array   $data         The new plugin or theme data.
			 * @param string  $package_type The package type ('plugin' or 'theme').
			 */
			do_action( 'upgrader_overwrote_package', $package, $this->new_plugin_data, 'plugin' );
		}

		return true;
	}

}