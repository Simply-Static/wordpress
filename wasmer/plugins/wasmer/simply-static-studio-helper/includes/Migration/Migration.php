<?php

namespace Simply_Static_Studio\Migration;

use Simply_Static_Studio\Helper;
use Simply_Static_Studio\Logs\Logger;
use Simply_Static_Studio\Queue\MigrationProcess;

class Migration {

	public static function hasMigration() {
		return defined( 'SSS_HAS_MIGRATION' ) && SSS_HAS_MIGRATION;
	}

	public static function migrated() {
		return get_option( 'sss_migrated', false );
	}

	public static function deleteFiles() {
		$filesystem = Helper::getFileSystem();
		$file = File::getDownloadFolder();

		if ( file_exists( $file ) ) {
			$filesystem->delete( $file );
		}
	}

	public static function markAsMigrated() {
		update_option( 'sss_migrated', true );
		self::setAsNotRunning();
	}

	public static function needsToRun() {
		return self::hasMigration() && ! self::migrated();
	}

	public static function getStatus() {
		return get_option( 'sss_migration_status', '' );
	}

	public static function setStatus( $status ) {
		update_option( 'sss_migration_status', $status );
	}

	public static function saveMigrationFilePath( $path ) {
		update_option( 'sss_migration_path', $path );
	}

	public static function getMigrationFilePath() {
		return get_option( 'sss_migration_path', null );
	}

	public static function getMigrationProcess() {
		return new MigrationProcess();
	}

	public static function isRunning() {
		return absint( get_option( 'sss_migrating', 0 ) ) === 1;
	}

	public static function setAsRunning() {
		update_option( 'sss_migrating', 1 );
	}

	public static function setAsNotRunning() {
		update_option( 'sss_migrating', 0 );
	}

	/**
	 * Return the list of tasks.
	 *
	 * @return string[]
	 */
	public static function getTasks() {
		return [
			'downloadChunks',
			'unpackDirect',
			//'theme',
			//'plugins',
			//'uploads',
			'database',
			'searchReplace',
			'changeTablePrefix',
			'cleanup'
		];
	}

	public static function getNextTask( $task = null ) {
		$tasks = self::getTasks();

		if ( null === $task ) {
			return $tasks[0];
		}

		// We're calling 'download' task if the chunkDownload fails.
		if ( 'download' === $task ) {
			return 'unpackDirect';
		}

		$currentIndex = array_search( $task, $tasks, true );

		if ( false === $currentIndex || $currentIndex < 0 ) {
			return null;
		}

		$nextIndex = $currentIndex + 1;

		if ( ! isset( $tasks[ $nextIndex ] ) ) {
			return null;
		}

		return $tasks[ $nextIndex ];
	}

	public static function startMigration() {
		// No need to start another migration process.
		if ( self::isRunning() ) {
			Logger::log('Migration already started');
			return;
		}

		$process   = self::getMigrationProcess();
		$firstTask = self::getNextTask();

		$process->push_to_queue( $firstTask );

		$process->save()->dispatch();
		self::setAsRunning();
	}


}