<?php

namespace Simply_Static_Studio\Queue\Tasks;

use Simply_Static_Studio\Helper;
use Simply_Static_Studio\Migration\File;

class Cleanup extends MigrationTask {

	public function perform() {

		$filesystem      = Helper::getFileSystem();
		$migrationFolder = File::getDownloadFolder();

		$filesystem->delete( $migrationFolder, true );

		return true;
	}
}