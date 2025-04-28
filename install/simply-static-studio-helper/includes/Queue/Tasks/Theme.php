<?php

namespace Simply_Static_Studio\Queue\Tasks;

use Simply_Static_Studio\Helper;
use Simply_Static_Studio\Migration\Migration;
use Simply_Static_Studio\Queue\Exceptions\SkippableException;

class Theme extends MigrationTask {

	public function migrate() {
		$filesystem = Helper::getFileSystem();
		$folder = trailingslashit( $this->getMigrationFolder() ) . 'wp-content/themes/';

		if ( ! is_dir( $folder ) ) {
			throw new SkippableException( 'Folder does not exist: ' . $folder );
		}

		Migration::setStatus("Migrating themes folder" );

		$themeFolder = WP_CONTENT_DIR . '/themes/';

		// Scan $folder for folders within it and copy them over to the $themefolder
		$folders = scandir( $folder );
		foreach( $folders as $theme ) {
			// Skip . and .. directories
			if ($theme === '.' || $theme === '..') {
				continue;
			}

			$filesystem->move( $folder . $theme, trailingslashit( $themeFolder ) . $theme, true );
		}

		Migration::setStatus("Migrated themes folder" );

		$this->done = true;

		return true;
	}
}