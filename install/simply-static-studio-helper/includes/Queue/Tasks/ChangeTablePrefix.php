<?php

namespace Simply_Static_Studio\Queue\Tasks;

use Simply_Static_Studio\Logs\Logger;
use Simply_Static_Studio\Migration\Migration;

/**
 * Task to change the prefix of all the tables that we migrated to the current prefix.
 *
 * Ideally, the currently prefixed table, change the prefix to keep data just in case for now.
 * Maybe later, delete those tables after the migration is a success.
 * Also, set migration data back to the new tables as well before changing the prefix so we don't restart migration again.
 */
class ChangeTablePrefix extends MigrationTask {

	protected $longRunning = true;

	protected function migrate() {
		$this->maybeTestStart();

		Migration::setStatus("Changing prefixes" );

		Logger::log("Changing prefixes");
		$this->migratePrefixes();
		Logger::log("Prefixes changed");
		$this->resetTableCache();
		Logger::log("Migrating origin data");
		$this->mergeOriginTableData();

		Migration::setStatus("Prefixes changed" );

		$this->done = true;

		$this->maybeTestEnd();
	}

	public function copyOriginUsers() {
		global $wpdb;

		$origin_prefix = 'origin_';

		if ( ! $this->tableExists( "{$origin_prefix}users"  ) ) {
			return;
		}

		$origin_users = $wpdb->get_results("SELECT ID, user_login, user_pass, user_nicename, user_email, user_url, user_registered, user_activation_key, user_status, display_name FROM {$origin_prefix}users", ARRAY_A );

		if ( ! $origin_users ) {
			return;
		}

		// Insert users
		foreach ( $origin_users as $user ) {
			$userId = absint( $user['ID'] );
			$userMeta = $wpdb->get_results("SELECT meta_key, meta_value FROM {$origin_prefix}usermeta WHERE user_id = {$userId}", ARRAY_A );

			unset( $user['ID'] );
			$inserted = $wpdb->insert( "{$wpdb->prefix}users", $user );

			if ( $inserted && $userMeta ) {
				$newUserId = $wpdb->insert_id;
				$metaQuery = "INSERT INTO {$wpdb->prefix}usermeta (user_id, meta_key, meta_value) VALUES ";
				$lastIndex = count( $userMeta ) - 1;

				// Insert user meta
				foreach ( $userMeta as $metaIndex => $meta ) {
					$metaQuery .= "({$newUserId}, '{$meta['meta_key']}', '{$meta['meta_value']}')";
					if ( $lastIndex !== $metaIndex) {
						$metaQuery .= ", ";
					} else {
						$metaQuery .= ";";
					}
				}

				$wpdb->query( $metaQuery );
			}

		}
	}

	public function copyCron() {
		global $wpdb;
		$origin_prefix = 'origin_';
		$current_prefix = $wpdb->prefix;

		if ( ! $this->tableExists( $origin_prefix . "options" ) ) {
			return;
		}

		$cron_query = $wpdb->prepare(
				"SELECT option_value FROM {$origin_prefix}options 
            WHERE option_name = %s",
				'cron'
			);
		$cron_value = $wpdb->get_var($cron_query);

		if ($cron_value) {
			// Update cron in new options table
			$wpdb->update(
				$current_prefix . 'options',
				array('option_value' => $cron_value),
				array('option_name' => 'cron')
			);
		}
	}

	public function copySimplyStaticSettings() {
		global $wpdb;
		$origin_prefix = 'origin_';
		$current_prefix = $wpdb->prefix;

		if ( ! $this->tableExists( $origin_prefix . "options" ) ) {
			return;
		}

		$ss_options_query = "SELECT option_name, option_value 
            FROM {$origin_prefix}options 
            WHERE option_name LIKE 'sss_%' 
               OR option_name LIKE 'simply-static%'";

			$ss_options = $wpdb->get_results($ss_options_query);

			foreach ($ss_options as $option) {
				// Delete existing option if it exists
				$wpdb->delete(
					$current_prefix . 'options',
					array('option_name' => $option->option_name)
				);

				// Insert the option from origin table
				$wpdb->insert(
					$current_prefix . 'options',
					array(
						'option_name' => $option->option_name,
						'option_value' => $option->option_value,
						'autoload' => 'yes'
					)
				);
			}
	}

	function mergeOriginTableData() {
		global $wpdb;

		try {
			// Start transaction
			$wpdb->query('START TRANSACTION');

			$this->copyOriginUsers();

			$this->copyCron();

			$this->copySimplyStaticSettings();

			// Commit transaction
			$wpdb->query('COMMIT');

			return true;

		} catch (\Exception $e) {
			// Rollback transaction on error
			$wpdb->query('ROLLBACK');

			return new \WP_Error( 500, $e->getMessage() );
		}
	}

	function migratePrefixes() {
		global $wpdb;

		// First find the new prefix using our previous function
		$new_prefix = $this->findNewPrefix();

		if (empty($new_prefix) || ! $new_prefix) {
			Logger::log("No different prefix found to migrate.");
			return "No different prefix found to migrate";
		}

		$current_prefix = $wpdb->prefix;
		$origin_prefix = 'origin_';

		try {
			// Start transaction
			$wpdb->query('START TRANSACTION');

			Logger::log("Backing origin data: options, users, usermeta");
			// 1. First backup critical tables with 'origin' prefix
			$critical_tables = ['options', 'users', 'usermeta'];
			foreach ($critical_tables as $table) {
				$old_table = $current_prefix . $table;
				$origin_table = $origin_prefix . $table;

				// Check if original table exists
				if ( $this->tableExists( $old_table ) ) {
					// Drop the origin table if it exists
					$wpdb->query("DROP TABLE IF EXISTS $origin_table");
					Logger::log( "Query: RENAME TABLE $old_table TO $origin_table" );
					// Rename current table to origin
					$wpdb->query("RENAME TABLE $old_table TO $origin_table");
				}
			}

			// 2. Get all tables with new prefix
			$all_tables = $this->getTables();
			$tables_to_rename = array();

			foreach ($all_tables as $table_name) {
				if (strpos($table_name, $new_prefix) === 0) {
					$tables_to_rename[] = $table_name;
				}
			}
			Logger::log( "Renaming tables to prefix $current_prefix: " . print_r($tables_to_rename, true) );

			foreach ($tables_to_rename as $table) {
				$new_table_name = $current_prefix . substr($table, strlen($new_prefix));

				// Drop the target table if it exists (except for our origin tables)
				$wpdb->query("DROP TABLE IF EXISTS $new_table_name");
				Logger::log( "Query: RENAME TABLE $table TO $new_table_name" );

				// Rename table
				$wpdb->query("RENAME TABLE $table TO $new_table_name");
			}

			// Commit transaction
			$wpdb->query('COMMIT');

			return true;

		} catch (\Exception $e) {
			// Rollback transaction on error
			$wpdb->query('ROLLBACK');

			return array(
				'status' => 'error',
				'message' => 'Error during migration: ' . $e->getMessage()
			);
		}
	}

	public function findNewPrefix() {
		global $wpdb;
		// List of common WordPress table suffixes to check
		$common_tables = ['options', 'posts', 'users', 'comments', 'terms'];

		$current_prefix = $wpdb->prefix;
		$found_prefix = '';
		$ignore_prefixes = [
			'backup_',
			'origin_'
		];

		// Flatten the array of tables
		$tables = $this->getTables();

		// Look for known table patterns
		foreach ($tables as $table) {
			foreach ($common_tables as $wp_table) {
				// Check if table ends with known WordPress table name
				if (preg_match('/(.*?)' . $wp_table . '$/', $table, $matches)) {
					$possible_prefix = $matches[1];

					if ( in_array( $possible_prefix, $ignore_prefixes, true ) ) {
						continue;
					}

					// Verify this prefix exists with other common tables
					$prefix_count = 0;
					foreach ($common_tables as $verify_table) {
						if (in_array($possible_prefix . $verify_table, $tables)) {
							$prefix_count++;
						}
					}

					// If we found at least 3 tables with this prefix, it's likely valid
					if ($prefix_count >= 3 && $possible_prefix !== $current_prefix) {
						$found_prefix = $possible_prefix;
						break 2;
					}
				}
			}
		}

		return $found_prefix;
	}

	public function getFile() {
		return trailingslashit( $this->getMigrationFolder() ) . 'db.sql';
	}

	public function extractWordPressPrefix() {

		$sqlFilePath = $this->getFile();

		// Check if file exists
		if ( ! file_exists( $sqlFilePath ) ) {
			return false;
		}

		// Read file content
		$content = file_get_contents( $sqlFilePath );
		if ( ! $content ) {
			return false;
		}

		// Pattern to match table creation lines
		$pattern = '/CREATE TABLE `([a-zA-Z0-9_]+)_[a-zA-Z0-9_]+`/';

		// Find all matches
		if ( preg_match_all( $pattern, $content, $matches ) ) {
			$prefixes = [];

			// Extract prefixes from table names
			foreach ( $matches[1] as $prefix ) {
				$prefixes[ $prefix ] = isset( $prefixes[ $prefix ] ) ? $prefixes[ $prefix ] + 1 : 1;
			}

			// Find the most common prefix
			if ( ! empty( $prefixes ) ) {
				arsort( $prefixes );
				$commonPrefix = key( $prefixes );

				return $commonPrefix . '_';
			}
		}

		return false;
	}



}