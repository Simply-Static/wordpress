<?php

namespace Simply_Static_Studio\Queue\Tasks;

use Simply_Static_Studio\Helper;
use Simply_Static_Studio\Migration\Migration;
use Simply_Static_Studio\Queue\Exceptions\SkippableException;

class Uploads extends MigrationTask {

	protected $longRunning = true;

	public function migrate() {
		$filesystem = Helper::getFileSystem();
		$folder = trailingslashit( $this->getMigrationFolder() ) . 'wp-content/uploads/';

		if ( ! is_dir( $folder ) ) {
			throw new SkippableException( 'Folder does not exist: ' . $folder );
		}

		Migration::setStatus("Migrating uploads folder" );

		$themeFolder = WP_CONTENT_DIR . '/uploads/';

		$copy = $filesystem->move( $folder, $themeFolder, true );

		if ( ! $copy ) {
			throw new \Exception( "Uploads not migrated. Something went wrong" );
		}

		Migration::setStatus("Uploads migrated." );

		$this->done = true;

		return true;
	}
}