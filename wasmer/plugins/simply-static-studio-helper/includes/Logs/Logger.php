<?php

namespace Simply_Static_Studio\Logs;

use Simply_Static_Studio\Helper;

class Logger {

	/**
	 * Return the filename for the debug log
	 *
	 * @return string Filename for the debug log
	 */
	public static function getFilename() {
		// Get directories.
		$uploads_dir       = wp_upload_dir();
		$simply_static_dir = $uploads_dir['basedir'] . DIRECTORY_SEPARATOR . 'simply-static' . DIRECTORY_SEPARATOR;

		return apply_filters( 'sss_debug_log_file', $simply_static_dir . 'sss-debug.txt', '' );
	}

	/**
	 * Save an object/string to the debug log
	 *
	 * @param mixed $object Object to save to the debug log
	 *
	 * @return void
	 */
	public static function log( $object = null ) {
		$filesystem = Helper::getFileSystem();
		$debug_file = self::getFilename();

		// add timestamp and newline
		$message = '[' . date( 'Y-m-d H:i:s' ) . '] ';

		$trace = debug_backtrace();
		if ( isset( $trace[0]['file'] ) ) {
			$file = basename( $trace[0]['file'] );
			if ( isset( $trace[0]['line'] ) ) {
				$file .= ':' . $trace[0]['line'];
			}
			$message .= '[' . $file . '] ';
		}

		$contents = self::getContent( $object );

		// get message onto a single line
		$contents = preg_replace( "/\r|\n/", "", $contents );

		$message .= $contents . "\n";

		if ( ! $filesystem->exists( $debug_file ) ) {
			$filesystem->touch( $debug_file );
		}

		// log the message to the debug file instead of the usual error_log location
		error_log( $message, 3, $debug_file );
	}

	/**
	 * Get contents of an object as a string
	 *
	 * @param mixed $object Object to get string for
	 *
	 * @return string         String containing the contents of the object
	 */
	protected static function getContent( $object ) {
		if ( is_string( $object ) ) {
			return $object;
		}

		ob_start();
		var_dump( $object );
		$contents = ob_get_contents();
		ob_end_clean();

		return $contents;
	}
}