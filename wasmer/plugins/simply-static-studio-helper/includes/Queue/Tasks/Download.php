<?php

namespace Simply_Static_Studio\Queue\Tasks;

use Simply_Static_Studio\Migration\File;
use Simply_Static_Studio\Migration\Migration;

class Download extends MigrationTask {

	protected $longRunning = true;

	public function migrate() {
		$file = File::downloadFile();

		Migration::setStatus( "Downloading the migration file..." );

		if ( is_wp_error( $file ) ) {
			throw new \Exception( $file->get_error_message() );
		}

		Migration::saveMigrationFilePath( $file );

		$this->done = true;

		Migration::setStatus( "File Downloaded" );

		return true;
	}
}