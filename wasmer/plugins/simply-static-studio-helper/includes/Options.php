<?php

namespace Simply_Static_Studio;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Simply Static options class
 */
class Options {
	/**
	 * Singleton instance
	 * @var \Simply_Static_Studio\Options
	 */
	protected static $instance = null;

	/**
	 * Options array
	 * @var array
	 */
	protected $options = array();

	/**
	 * Disable usage of "new"
	 * @return void
	 */
	protected function __construct() {
	}

	/**
	 * Disable cloning of the class
	 * @return void
	 */
	protected function __clone() {
	}

	/**
	 * Disable unserializing of the class
	 * @return void
	 */
	public function __wakeup() {
	}

	/**
	 * Return an instance of Simply_Static\Options
	 * @return Options
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();

			$db_options = get_option( 'simply-static-studio' );

			$options = apply_filters( 'sss_get_options', $db_options );
			if ( false === $options ) {
				$options = array();
			}

			self::$instance->options = $options;
		}

		return self::$instance;
	}

	/**
	 * Return a fresh instance of Simply_Static\Options
	 * @return Options
	 */
	public static function reinstance() {
		self::$instance = null;

		return self::instance();
	}

	/**
	 * Updates the option identified by $name with the value provided in $value
	 *
	 * @param string $name The option name
	 * @param mixed $value The option value
	 *
	 * @return Options
	 */
	public function set( $name, $value ) {
		$this->options[ $name ] = $value;

		return $this;
	}

	/**
	 * Set all options.
	 *
	 * @param array $options All options.
	 *
	 * @return Options
	 */
	public function set_options( $options ) {
		$this->options = $options;

		return $this;
	}

	/**
	 * Returns a value of the option identified by $name
	 *
	 * @param string $name The option name
	 *
	 * @return mixed|null
	 */
	public function get( $name = '' ) {
		return array_key_exists( $name, $this->options ) ?
			(
			'VERSION' !== strtoupper( $name ) && defined( 'STATIC_STUDIO_' . strtoupper( $name ) ) ?
				constant( 'STATIC_STUDIO_' . strtoupper( $name ) ) :
				apply_filters( 'static_studio_get_option_' . strtolower( $name ), $this->options[ $name ], $this )
			)
			: null;
	}

	/**
	 * Destroy an option
	 *
	 * @param string $name The option name to destroy
	 *
	 * @return boolean true if the key existed, false if it didn't
	 */
	public function destroy( $name ) {
		if ( array_key_exists( $name, $this->options ) ) {
			unset( $this->options[ $name ] );

			return true;
		} else {
			return false;
		}
	}

	/**
	 * Returns all options as an array
	 * @return array
	 */
	public function get_as_array() {
		return $this->options;
	}

	/**
	 * Saves the internal options data to the wp_options table
	 * @return boolean
	 */
	public function save() {
		return is_network_admin() ? update_site_option( 'simply-static-studio', $this->options ) : update_option( 'simply-static-studio', $this->options );
	}
}
