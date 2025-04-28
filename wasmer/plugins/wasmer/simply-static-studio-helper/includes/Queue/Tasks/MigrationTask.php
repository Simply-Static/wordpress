<?php

namespace Simply_Static_Studio\Queue\Tasks;

use Simply_Static_Studio\Helper;
use Simply_Static_Studio\Migration\File;
use Simply_Static_Studio\Migration\Migration;

class MigrationTask {

	protected $id = '';

	protected $done = false;

	protected $test = false;
	protected $tables = null;


	protected $longRunning = false;

	/**
	 * @param boolean $test If true, it will output queries but not perform them.
	 *
	 * @return void
	 */
	public function setTest( $test ) {
		$this->test = $test;
	}

	public function getTables() {
		global $wpdb;

		if ( null === $this->tables ) {
			$this->tables = $wpdb->get_col( "SHOW TABLES" );
		}

		return $this->tables;
	}

	public function tableExists( $table ) {
		$tables = $this->getTables();

		return in_array( $table, $tables, true );
	}


	public function maybeTestStart() {
		if ( ! $this->test ) {
			return;
		}

		add_filter('query', [ $this, 'output_query' ] );
	}

	public function maybeTestEnd() {
		if ( ! $this->test ) {
			return;
		}

		remove_filter('query', [ $this, 'output_query' ] );
	}

	public function output_query( $query ) {
		print_r( $query . "\n" );

		if ( 'SHOW TABLES' === $query ) {
			return $query;
		}

		return '';
	}

	public function resetTableCache() {
		$this->tables = null;
	}


	protected function migrate() {}

	public function isLongRunning() {
		return $this->longRunning;
	}

	public function perform() {
		if ( $this->isLongRunning() ) {
			set_time_limit( 0 );
		}

		$this->migrate();

		return $this->done;
	}

	public function getFilesystem() {
		return Helper::getFileSystem();
	}

	public function getMigrationFolder() {
		return File::getUnzipFolder();
	}

	public function move( $from, $to ) {
		$filesystem = $this->getFilesystem();

		if ( ! $filesystem ) {
			throw new \Exception( 'Filesystem is not available' );
		}

		$filesystem->copy( $from, $to );
	}
}