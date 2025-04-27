<?php

namespace Simply_Static_Studio\Migration;

use Simply_Static_Studio\Helper;

class File {

	/**
	 * @param $file
	 *
	 * @return true|\WP_Error
	 */
	public static function unzip( $file ) {
		Helper::getFileSystem();
		$unzipFolder = self::getUnzipFolder();
		return unzip_file( $file, $unzipFolder );
	}

	public static function getFileName() {
		return 'site-migration-' . Helper::getSubdomainString() . '.zip';
	}

	public static function getFileUrl() {
		return 'https://api.static.studio/storage/v1/object/public/site_migrations/public/' . self::getFileName();
	}

	public static function downloadFile() {
		$url = self::getFileUrl();

		return self::downloadUrlAndSave( $url );
	}

	public static function downloadUrlAndSave( $url ) {
		$filesystem = Helper::getFileSystem();

		if ( ! $filesystem ) {
			return new \WP_Error( 'no-system', 'No File System found' );
		}

		$file_name = self::getFileName();
		$file_path = trailingslashit( self::getDownloadFolder() ) . $file_name;

		$downloaded = download_url( $url );
		// Check if the file was downloaded correctly
		if ( is_wp_error( $downloaded ) ) {
			return $downloaded;
		}

		$filesystem->copy( $downloaded, $file_path, true );

		return $file_path;
	}

	public static function getDownloadFolder() {
		$folder = WP_CONTENT_DIR . '/migration';

		if ( ! is_dir( $folder ) ) {
			wp_mkdir_p( $folder );
		}

		return $folder;
	}

	public static function getUnzipFolder() {
		$folder = self::getDownloadFolder();
		$folder = trailingslashit( $folder ) . 'files';

		if ( ! is_dir( $folder ) ) {
			wp_mkdir_p( $folder );
		}

		return $folder;
	}
}