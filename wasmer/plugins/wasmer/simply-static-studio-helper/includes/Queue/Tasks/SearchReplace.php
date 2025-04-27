<?php

namespace Simply_Static_Studio\Queue\Tasks;

use Simply_Static_Studio\Logs\Logger;
use Simply_Static_Studio\Migration\Migration;
use Simply_Static_Studio\ThirdParty\BetterSearchReplace\DB;

/**
 * Class to get the current site url from the option
 * Get the new one from the new options table
 * Replace the new one with the current one in the new tables (with different prefix).
 */
class SearchReplace extends MigrationTask {

	protected $longRunning = true;

	protected function migrate() {
		$this->maybeTestStart();

		Logger::log('Replacing URLs');
		Migration::setStatus("Replacing URLs" );

		$this->replaceCapabilities();
		$this->done = $this->replaceUrls();

		if ( $this->done ) {
			Logger::log('Replaced all URLs.');
			Migration::setStatus("Replaced URLs" );
		}

		$this->maybeTestEnd();
	}

	public function replaceCapabilities() {
		global $wpdb;

		$prefix    = $this->findNewPrefix();
		$oldPrefix = $wpdb->prefix;
		$options  = [ 'user_roles' ];
		$usermeta = [
			'capabilities',
			'user_level',
			'user-settings',
			'user-settings-time',
			'dashboard_quick_press_last_post_id',
		];

		if ( ! $prefix ) {
			Logger::log('Prefix not found for changing capabilities');
			return false;
		}

		foreach ( $options as $option ) {
			$query = "SELECT * FROM `{$prefix}options` WHERE option_name = '{$prefix}{$option}'";
			$results = $wpdb->get_results( $query, ARRAY_A );
			if ( empty( $results ) ) {
				continue;
			}

			foreach ( $results as $result ) {
				$query = "UPDATE `{$prefix}options` SET  option_name = '{$oldPrefix}{$option}'  WHERE option_id = {$result['option_id']}";
				$wpdb->query( $query );
			}
		}

		foreach ( $usermeta as $meta ) {
			$query = "SELECT * FROM {$prefix}usermeta WHERE meta_key = '{$prefix}{$meta}'}";
			$results = $wpdb->get_results( $query, ARRAY_A );
			if ( empty( $results ) ) {
				continue;
			}

			$query = "UPDATE `{$prefix}usermeta` SET meta_key = '{$oldPrefix}{$meta}' WHERE";
			foreach ( $results as $index =>  $result ) {
				if ( $index > 0 ) {
					$query .= " AND";
				}
				$query .= " meta_id = {$result['meta_id']}";
			}
			$wpdb->query( $query );
		}
	}

	public function getOldSiteUrl() {
		global $wpdb;

		$prefix = $this->findNewPrefix();

		if ( ! $prefix ) {
			Logger::log('Prefix not found.');
			return false;
		}
		Logger::log("Found prefix: " . $prefix);

		if ( ! $this->tableExists( $prefix . "options" ) ) {
			Logger::log( "Table does not exist: " . $prefix . "options" );
			return false;
		}


		$urlQuery = "SELECT option_value 
            FROM {$prefix}options 
            WHERE option_name = 'siteurl' LIMIT 1";

		$siteurl = $wpdb->get_var( $urlQuery );

		Logger::log( "Tried finding old siteurl. Found: " . $siteurl );

		return $siteurl;
	}

	function replaceUrls() {
		global $wpdb;

		try {
			$oldSiteUrl = $this->getOldSiteUrl();

			if ( ! $oldSiteUrl ) {
				Logger::log( "Old site URL not found. Returning true to finish process." );

				return true;
			}

			// Start
			// transaction
			$wpdb->query('START TRANSACTION');

			// Initialize the DB class.
			$db   = new DB();
			$step = get_option( 'sss_bsr_step', 0 );
			$page = get_option( 'sss_bsr_page', 0 );
			Logger::log( "Before starting. Step: " . $step );
			Logger::log( "Before starting. Page: " . $page );

			// Any operations that should only be performed at the beginning.
			if ( $step === 0 && $page === 0 ) {
				$tables = $this->getTables();
				$prefix = $this->findNewPrefix();
				$tables = array_filter( $tables, function ( $table ) use ( $prefix ) {
					return str_starts_with( $table, $prefix );
				});

				Logger::log( "Tables to perform search-replace: " . print_r( $tables, true ) );

				$args = array(
					'select_tables'    => array_values( array_map( 'trim', $tables ) ), // Making sure array keys are reset.
					'case_insensitive' => 'off',
					'replace_guids'    => 'on',
					'dry_run'          => 'off',
					'search_for'       => $oldSiteUrl,
					'replace_with'     => get_option( 'siteurl' ),
					'completed_pages'  => 0,
					'prefix'           => $prefix,
				);

				$args['total_pages'] = isset( $args['total_pages'] ) ? absint( $args['total_pages'] ) : $db->get_total_pages( $args['select_tables'] );

				// Clear the results of the last run.
				delete_transient( 'sss_bsr_results' );
				delete_option( 'sss_bsr_data' );
			} else {
				$args = get_option( 'sss_bsr_data' );
			}

			Logger::log( "Data used in performing search-replace:: " . print_r( $args, true ) );
			Logger::log( "Step: " . $step );
			Logger::log( "Page: " . $page );

			// Start processing data.
			if ( isset( $args['select_tables'][$step] ) ) {

				$result = $db->srdb( $args['select_tables'][$step], $page, $args );
				Logger::log( "Result from search replace: " . print_r( $result, true ) );
				if ( ! $result['table_complete'] ) {
					$page++;
				} else {
					$step++;
					$page = 0;
				}

				// Check if isset() again as the step may have changed since last check.
				if ( isset( $args['select_tables'][$step] ) ) {
					$msg_tbl = esc_html( $args['select_tables'][$step] );

					$message = sprintf(
						__( 'Processing table %d of %d: %s', 'better-search-replace' ),
						$step + 1,
						count( $args['select_tables'] ),
						$msg_tbl
					);

					Logger::log(  $message );

				}

				$args['completed_pages']++;
				$percentage = $args['completed_pages'] / $args['total_pages'] * 100 . '%';

			} else {
				$db->maybe_update_site_url( $args['prefix'] );
				$step 		= 'done';
				$percentage = '100%';
			}

			update_option( 'sss_bsr_page', $page );
			update_option( 'sss_bsr_step', $step );
			update_option( 'sss_bsr_data', $args );

			// Commit transaction
			$wpdb->query('COMMIT');
			Logger::log( "Changes committed" );

			if ( $step === 'done' ) {
				delete_option( 'sss_bsr_page' );
				delete_option( 'sss_bsr_step' );
				delete_option( 'sss_bsr_data' );
				Logger::log( "Search Replace completed." );

				return true;
			}

			return false;
		} catch (\Exception $e) {
			// Rollback transaction on error
			$wpdb->query('ROLLBACK');
			Logger::log( "Search Replace failed: " . $e->getMessage() );

			throw $e;
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

}