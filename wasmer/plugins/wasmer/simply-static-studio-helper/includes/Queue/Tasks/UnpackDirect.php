<?php

namespace Simply_Static_Studio\Queue\Tasks;

use SimplePie\Exception;
use Simply_Static_Studio\Helper;
use Simply_Static_Studio\Logs\Logger;
use Simply_Static_Studio\Migration\File;
use Simply_Static_Studio\Migration\Migration;
use ZipArchive;

/**
 * This task does not unpack the files in a folder first,
 * but it unpacks them directly where they need to go.
 */
class UnpackDirect extends MigrationTask {

	protected $longRunning = true;

	protected $zipArchive = null;

	public $file = null;

	public function getZipArchive() {
		if ( null === $this->zipArchive ) {
			$this->zipArchive = new ZipArchive();
		}

		return $this->zipArchive;
	}

	public function open( $file ) {
		$open = $this->getZipArchive()->open( $file, ZipArchive::RDONLY );

		if ( $open === true ) {
			return;
		}

		if ( ! $open ) {
			throw new Exception( 'Unable to open zip file: ' . $file );
		}

		$error = "Unable to open zip file: " . $file. ". ";
		switch ( $open ) {
			case ZipArchive::ER_EXISTS:
				$error .= "File already exists.";
				break;
			case ZipArchive::ER_INCONS:
				$error .= "Zip archive inconsistent.";
				break;
			case ZipArchive::ER_INVAL:
				$error .= "Invalid argument.";
				break;
			case ZipArchive::ER_MEMORY:
				$error .= "Malformed zip file.";
				break;
			case ZipArchive::ER_NOENT:
				$error .= "No such zip file.";
				break;
			case ZipArchive::ER_NOZIP:
				$error .= "Not a zip archive.";
				break;
			case ZipArchive::ER_OPEN:
				$error .= "Can't open zip file.";
				break;
			case ZipArchive::ER_READ:
				$error .= "Read error.";
				break;
			case ZipArchive::ER_SEEK:
				$error .= "Seek error.";
				break;
		}

		throw new Exception( $error );

	}

	public function close() {
		$this->zipArchive->close();
	}

	public function getTotalZipFiles() {
		return $this->getZipArchive()->numFiles;
	}

	public function getFileFromIndex( $index ) {
		$info = $this->getZipArchive()->statIndex( $index );

		if ( false === $info ) {
			return new \WP_Error( 'no-file', 'No file found at: ' . $index );
		}

		return $this->getFileInfo( $info );
	}

	public function getFileContent( $index ) {
		return $this->getZipArchive()->getFromIndex( $index );
	}

	public function getFile() {
		if ( null === $this->file ) {
			$this->file = Migration::getMigrationFilePath();
		}

		return $this->file;
	}

	public function migrate() {
		$file = $this->getFile();

		if ( ! $file ) {
			throw new \Exception( "No migration file path saved in DB. " );
		}

		if ( ! file_exists( $file ) ) {
			throw new \Exception( "Unable to locate migration file: " . $file );
		}

		Migration::setStatus( "Migrating files..." );

		$fileIndex = get_option( "simply_static_studio_unpack_file_index", null );


		$this->open( $file );
 		if ( null === $fileIndex ) {
			$fileIndex = 0;
		} else {
			$fileIndex++;
		}

		$totalFiles = $this->getTotalZipFiles();

		if ( $totalFiles === 0 ) {
			throw new \Exception( "ZIP files could not be read. States 0 files. File: " . $file );
		}

		if ( $fileIndex >= $totalFiles ) {
			Logger::log("File index at " . $fileIndex . " is out of bounds. Total Files: " . $totalFiles );
			$this->complete();
			return true;
		}

		$maybeCompleteAtLoopEnd = true;

		for ( $i = $fileIndex; $i < $totalFiles; $i++ ) {
			$info = $this->getFileFromIndex( $i );

			if ( is_wp_error( $info ) ) {
				throw new \Exception( $info->get_error_message() );
			}

			if ( ! $this->isValid( $info['path'] ) ) {
				Logger::log( "File " . $info['path'] . " is not a valid file to migrate." );
				update_option( "simply_static_studio_unpack_file_index", $i );
				continue;
			}

			if ( $this->memory_will_exceed( $info['size'] ) ) {
				Logger::log("Memory will exceed for file: " . $info['path'] . " - " . $info['size'] . " bytes");
				Logger::log("We are breaking the loop to continue in next iteration.");
				$maybeCompleteAtLoopEnd = false;
				break;
			}

			$extractPath = $this->getExtractPath( $info['path'] );

			$this->moveFile( $extractPath, $this->getFileContent( $i ) );

			update_option( "simply_static_studio_unpack_file_index", $i );

			if ( $this->memory_exceeded() ) {
				$maybeCompleteAtLoopEnd = false;
				break;
			}
		}

		$this->close();

		// $i starts at 0, so +1 will get us to totalFiles if it's there.
		if ( $maybeCompleteAtLoopEnd && ( ( $i + 1 ) >= $totalFiles ) ) {
			$this->complete();
			return true;
		}

		return false;
	}

	public function moveFile( $path, $contents = '' ) {

		$this->maybeMakeDirs( $path );

		if ( $this->test ) {
			echo "Moving file/directory to: " . $path . "\n";
			return; // Just testing.
		}

		if ( ! $this->isDir( $path ) ) {
			$filesystem = Helper::getFileSystem();
			if ( $filesystem->exists( $path ) ) {
				$filesystem->delete( $path );
			}
			$filesystem->put_contents( $path, $contents, FS_CHMOD_FILE );
		}
	}

	/**
	 * @param $path
	 *
	 * @return void
	 */
	public function maybeMakeDirs( $path ) {

		$clean   = substr( $path, strlen(WP_CONTENT_DIR ) );
		$allDirs = array_values( array_Filter( explode( "/", $clean ) ) );
		$max     = count( $allDirs );

		if ( $this->test ) {
			print_r( "Preparing for making dirs: " );
			print_r([
				'path' => $path,
				'clean' => $clean,
				'allDirs' => $allDirs,
				'max' => $max,
			]);
		}

		if ( ! $this->isDir( $path ) ) {
			$max--;
		}

		if ( ! $max ) {
			return;
		}

		$pathTo = WP_CONTENT_DIR;

		for( $i = 0; $i < $max; $i++ ) {
			$dir = $allDirs[ $i ];
			$pathTo .= DIRECTORY_SEPARATOR . $dir;
			if ( is_dir( $pathTo ) ) {
				continue;
			}

			if ( $this->test ) {
				echo "Would create directory: " . $pathTo . "\n";
				continue;
			}
			wp_mkdir_p( $pathTo );
		}
	}

	/**
	 * Memory exceeded?
	 *
	 * Ensures the batch process never exceeds 90%
	 * of the maximum WordPress memory.
	 *
	 * @return bool
	 */
	protected function memory_exceeded() {
		$memory_limit   = $this->get_memory_limit() * 0.9; // 90% of max memory
		$current_memory = memory_get_usage( true );

		if ( $current_memory >= $memory_limit ) {
			return true;
		}

		return false;
	}

	protected function memory_will_exceed( $size ) {
		$memory_limit   = $this->get_memory_limit() * 0.9; // 90% of max memory
		$potential_memory = memory_get_usage( true ) + $size;

		if ( $potential_memory >= $memory_limit ) {
			return true;
		}

		return false;
	}

	/**
	 * Get memory limit in bytes.
	 *
	 * @return int
	 */
	protected function get_memory_limit() {
		if ( function_exists( 'ini_get' ) ) {
			$memory_limit = ini_get( 'memory_limit' );
		} else {
			// Sensible default.
			$memory_limit = '2000MB';
		}

		if ( ! $memory_limit || -1 === intval( $memory_limit ) ) {
			// Unlimited, set to 32GB.
			$memory_limit = '32000M';
		}

		return wp_convert_hr_to_bytes( $memory_limit );
	}

	public function complete() {
		$this->done = true;
		delete_option( "simply_static_studio_unpack_file_index" );
	}

	public function getFileInfo( $info ) {
		return [
			'path' => $info['name'],
			'size' => $info['size'],
		];
	}

	public function getExtractPath( $path ) {
		// First one, probably needs to be in migration as it's db.sql.
		if ( ! $this->isDir( $path ) && ! $this->isInsideDir( $path ) ) {
			return trailingslashit( File::getUnzipFolder() ) . $path;
		}

		// If file
		// If inside a directory (example, the first dir is)
		if ( ! $this->isDir( $path )
		     && $this->isInsideDir( $path )
		     && $this->isAllInsideDir()
			 && $this->getFirstDir( $path ) === $this->isAllInsideDir()
			 && $this->dirCount( $path ) === 1 // only after first dir
		) {
			$path = explode( '/', $path );
			unset( $path[0] );
			$path = implode( '/', $path );
			return trailingslashit( File::getUnzipFolder() ) . $path;
		}

		if ( 'wp-content' !== $this->getFirstDir( $path ) ) {
			return ABSPATH . substr( $path, strpos( $path, "wp-content" ) );
		}

		return ABSPATH . $path;
	}

	public function isAllInsideDir() {
		$info = $this->getFileInfo( $this->getZipArchive()->statIndex( 0 ) );

		if ( ! $this->isDir( $info['path'] ) ) {
			return false;
		}

		if ( 'wp-content' === $this->getFirstDir( $info['path'] ) ) {
			return false; // wp-content is differnt.
		}

		return $this->getFirstDir( $info['path'] );
	}

	public function isInsideDir( $path ) {
		$array = explode( "/", $path );

		return count( $array ) > 1;
	}

	public function dirCount( $path ) {
		$array = array_filter( explode( "/", $path ) );
		$dirCount = count( $array );

		if ( ! $this->isDir( $path ) ) {
			$dirCount--;
		}
		return $dirCount;
	}

	public function getFirstDir( $path ) {
		$array = explode( "/", $path );
		return current( $array );
	}

	protected function isValid( $path ) {
		// Skip the OS X-created __MACOSX directory
		if ( '__MACOSX/' === substr( $path, 0, 9 ) ) {
			return false;
		}

		// Don't extract invalid files:
		if ( 0 !== validate_file( $path ) ) {
			return false;
		}

		if ( ! $this->isDir( $path )
		     && $this->isInsideDir( $path )
		     && $this->isAllInsideDir()
		     && $this->getFirstDir( $path ) === $this->isAllInsideDir()
		     && $this->dirCount( $path ) === 1 // only after first dir
		) {
			return true; // direct file.
		}


		if ( ! $this->isDir( $path )
		     && ! $this->isInsideDir( $path )
		     && ! $this->isAllInsideDir()
		) {
			return true; // direct file.
		}

		if ( $this->isInsideDir( $path ) && ! str_contains( $path, "wp-content" ) ) {
			return false; // We only allow wp-content files and directories.
		}


		if ( $this->isInsideDir( $path ) && str_contains( $path, "wp-content/plugins/simply-static/" )  ) {
			return false; // We provide our own Simply Static and Simply Static Pro
		}

		if ( $this->isInsideDir( $path ) && str_contains( $path, "wp-content/plugins/simply-static-pro/" )  ) {
			return false; // We provide our own Simply Static and Simply Static Pro
		}

		return true;
	}

	protected function isDir( $path ) {
		return '/' === substr( $path, -1 );
	}
}