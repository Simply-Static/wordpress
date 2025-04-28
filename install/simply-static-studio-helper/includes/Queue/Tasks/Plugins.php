<?php

namespace Simply_Static_Studio\Queue\Tasks;

use Simply_Static_Studio\Helper;
use Simply_Static_Studio\Migration\Migration;
use Simply_Static_Studio\Queue\Exceptions\SkippableException;

class Plugins extends MigrationTask {

	public function migrate() {
		$filesystem = Helper::getFileSystem();
		$folder = trailingslashit( $this->getMigrationFolder() ) . 'wp-content/plugins/';

		if ( ! is_dir( $folder ) ) {
			throw new SkippableException( 'Folder does not exist: ' . $folder );
		}

		Migration::setStatus("Migrating plugins folder" );

		$pluginFolder = WP_CONTENT_DIR . '/plugins/';

		// Scan $folder for folders within it and copy them over to the $themefolder
		$folders = scandir( $folder );
		foreach( $folders as $pluginName ) {
			// Skip . and .. directories
			if ($pluginName === '.' || $pluginName === '..') {
				continue;
			}

			if ( 'simply-static' === $pluginName ) { continue; }
			if ( 'simply-static-pro' === $pluginName ) { continue; }
			$filesystem->move( $folder . $pluginName, trailingslashit( $pluginFolder ) . $pluginName, true );
		}

		Migration::setStatus("Migrated plugins folder" );

		$this->done = true;
		return true;
	}
}