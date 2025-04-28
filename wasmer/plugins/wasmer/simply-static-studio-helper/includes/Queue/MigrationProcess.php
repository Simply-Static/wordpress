<?php

namespace Simply_Static_Studio\Queue;

use Simply_Static_Studio\Logs\Logger;
use Simply_Static_Studio\Migration\Migration;
use Simply_Static_Studio\Queue\Exceptions\DownloadChunkContinueException;
use Simply_Static_Studio\Queue\Exceptions\DownloadChunkException;
use Simply_Static_Studio\Queue\Exceptions\SkippableException;
use Simply_Static_Studio\Queue\Tasks\MigrationTask;

class MigrationProcess extends BackgroundProcess {

	/**
	 * @var string
	 */
	protected $prefix = 'simply_static_studio';

	/**
	 * @var string
	 */
	protected $action = 'migration_process';

	/**
	 * Perform task with queued item.
	 *
	 * Override this method to perform any actions required on each
	 * queue item. Return the modified item for further processing
	 * in the next pass through. Or, return false to remove the
	 * item from the queue.
	 *
	 * @param mixed $item Queue item to iterate over.
	 *
	 * @return mixed
	 */
	protected function task( $item ) {
		// Actions to perform.

		$task = $this->getItem( $item );

		if ( ! $task ) {
			return false;
		}

		try {
			Logger::log( 'Performing: ' . $item );
			$done = $task->perform();

			if ( ! $done ) {
				Logger::log( 'Not done with: ' . $item );

				return $item;
			}

			$nextTask = Migration::getNextTask( $item );

			if ( null === $nextTask ) {
				// No more tasks.
				Logger::log( "No more tasks");
				return false;
			}

			Logger::log( 'New task: ' . $nextTask );
			return $nextTask;

		} catch (DownloadChunkContinueException $e) {
			Logger::log( 'Chunk Download Error: ' . $e->getMessage() );
			Logger::log( 'Not done with: ' . $item );
			return $item;
		} catch (DownloadChunkException $e) {
			Logger::log( 'Chunk Download Error: ' . $e->getMessage() );
			Logger::log( $e->getTraceAsString() );
			Logger::log( 'Moving to using download_url instead.' );
			Logger::log( 'New task: download' );
			return 'download';
		} catch (SkippableException $e) {
			Logger::log( 'Skippable: ' . $e->getMessage() );
			Logger::log( $e->getTraceAsString() );
			$nextTask = Migration::getNextTask( $item );
			if ( null === $nextTask ) {
				// No more tasks.
				Logger::log( "No more tasks");
				return false;
			}

			Logger::log( 'New task: ' . $nextTask );
			return $nextTask;
		} catch ( \Exception $e ) {
			$this->pause();
			Migration::setStatus('Migration paused. Reason: ' . $e->getMessage() );
			Logger::log( $e->getMessage() );
			Logger::log( $e->getTraceAsString() );
			do_action( 'simply_static_studio_migration_process_failed', [
					'message' => $e->getMessage(),
					'code'    => $e->getCode(),
					'task'    => $item,
					'task_object' => $task
				],
				$this
			);
			// Something to do here when exception is met: pause migration.
			return $item;
		}


		return false;
	}

	/**
	 * @param $item
	 *
	 * @return false|MigrationTask
	 */
	protected function getItem( $item ) {

		$className = '\Simply_Static_Studio\Queue\Tasks\\' . ucfirst( $item );

		if ( class_exists( $className ) ) {
			return new $className();
		}

		return false;
	}

	protected function complete() {
		Logger::log('Migration Process Completed.');
		parent::complete();
		Migration::markAsMigrated();
		Migration::deleteFiles();
	}
}