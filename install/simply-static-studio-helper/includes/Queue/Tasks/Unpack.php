<?php

namespace Simply_Static_Studio\Queue\Tasks;

use Simply_Static_Studio\Helper;
use Simply_Static_Studio\Migration\File;
use Simply_Static_Studio\Migration\Migration;

class Unpack extends MigrationTask {

	protected $longRunning = true;

	/**
	 * Scans a directory for 'wp-content' folder and moves it to the parent directory if found
	 *
	 * @param string $path The path to scan for 'wp-content' folder
	 * @return bool True if wp-content was found and moved, false otherwise
	 */
	function moveWpContentToParent( $path ) {
		$filesystem = Helper::getFileSystem();

		// Make sure the path exists and is a directory
		if ( ! is_dir( $path ) ) {
			return false;
		}

		// Get the parent directory of the provided path
		$parentDir = dirname($path);

		// The potential source and destination paths
		$wpContentSource = rtrim($path, '/') . '/wp-content';
		$wpContentDest = rtrim($parentDir, '/') . '/wp-content';

		if ( ! is_dir( $wpContentSource ) ) {
			return false;
		}

		// Check if destination already exists
		if (is_dir($wpContentDest)) {
			return false; // Cannot move as destination already exists
		}

		$filesystem->move( $wpContentSource, $wpContentDest, true );

		if (is_dir($wpContentDest)) {
			return true; // Successfully moved
		}

		return false; // wp-content not found
	}

	/**
	 * Scans a directory for .sql files and moves them to the parent directory
	 *
	 * @param string $path The path to scan for .sql files
	 * @return boolean
	 */
	function moveSqlFilesToParent($path) {
		// Make sure the path exists and is a directory
		if ( ! is_dir( $path ) ) {
			return false;
		}

		// Get the parent directory of the provided path
		$parentDir = dirname($path);

		// Check if parent directory is writable
		if ( ! is_writable( $parentDir ) ) {
			return false;
		}

		// Get all files in the directory
		$files      = scandir( $path );
		$filesystem = Helper::getFileSystem();

		foreach ($files as $file) {
			// Skip . and .. directories
			if ($file === '.' || $file === '..') {
				continue;
			}

			if ( pathinfo( $file, PATHINFO_EXTENSION) !== 'sql') {
				continue;
			}

			$sourceFile = rtrim($path, '/') . '/' . $file;
			$destFile = rtrim($parentDir, '/') . '/' . $file;

			// Check if a file with the same name already exists in parent directory
			if ( file_exists( $destFile ) ) {
				continue;
			}

			$filesystem->copy( $sourceFile, $destFile );
		}

		return true;
	}

	public function migrate() {

		Migration::setStatus( "Unpacking the migration file..." );
		$file   = Migration::getMigrationFilePath();

		$result = File::unzip( $file );

		if ( is_wp_error( $result ) ) {
			throw new \Exception( $result->get_error_message() );
		}

		$filesPath = $this->getMigrationFolder();
		$files     = scandir( $filesPath );
		$hasWpContent = is_dir( trailingslashit( $this->getMigrationFolder() ) . 'wp-content' );
		$hasSql       = false;
		$folders      = [];

		foreach ($files as $file) {
			// Skip . and .. directories
			if ( $file === '.' || $file === '..' ) {
				continue;
			}

			$folderPath = trailingslashit( $filesPath ) . $file;

			if ( $hasSql && $hasWpContent ) {
				break;
			}

			if ( ! $hasSql ) {
				if ( is_file( $folderPath ) &&
				     pathinfo( $folderPath, PATHINFO_EXTENSION) === 'sql' ){
					$hasSql = true;
				}

				if ( is_dir( $filesPath ) ) {
					$folders[] = $folderPath;
				}
			}

			if ( $hasWpContent ) {
				continue;
			}

			if ( ! is_dir( $folderPath ) ) {
				continue;
			}

			$hasWpContent = $this->moveWpContentToParent( $folderPath );

		}

		if ( ! $hasSql && ! empty( $folders ) ) {
			foreach ( $folders as $folder ) {
				$this->moveSqlFilesToParent( $folder );
			}
		}

		Migration::setStatus( "Unpacked" );

		$this->done = true;
		return true;
	}
}