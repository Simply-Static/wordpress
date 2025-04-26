<?php

namespace Simply_Static_Studio\Queue\Tasks;

use Simply_Static_Studio\Logs\Logger;
use Simply_Static_Studio\Migration\Migration;
use Simply_Static_Studio\Queue\Exceptions\SkippableException;
use Simply_Static_Studio\ThirdParty\Servmask\database\Ai1wm_Database_Utility;
use function Simply_Static_Studio\ThirdParty\Servmask\database\ai1wm_cache_flush;
use function Simply_Static_Studio\ThirdParty\Servmask\database\ai1wm_table_prefix;
use function Simply_Static_Studio\ThirdParty\Servmask\database\ai1wm_validate_plugin_basename;
use function Simply_Static_Studio\ThirdParty\Servmask\database\ai1wm_validate_theme_basename;

class Database extends MigrationTask {

	public function getFile() {
		return trailingslashit( $this->getMigrationFolder() ) . 'db.sql';
	}

	protected function migrate() {
		$file = $this->getFile();

		if ( ! is_file( $file ) ) {
			throw new SkippableException( 'Database file not found: ' . $file );
		}

		if ( ! function_exists( '\Simply_Static_Studio\ThirdParty\Servmask\database\ai1wm_cache_flush') ) {
			include_once trailingslashit( SSS_PATH ) . 'includes/ThirdParty/Servmask/database/functions.php';
		}

		Migration::setStatus("Migrating Database" );

		$old_table_prefixes = [];
		$new_table_prefixes = [];

		$old_replace_values = [];
		$new_replace_values = [];

		$old_replace_raw_values = [];
		$new_replace_raw_values = [];

		// Get database client
		$db_client = Ai1wm_Database_Utility::create_client();

		$db_client->set_old_table_prefixes( $old_table_prefixes )
		          ->set_new_table_prefixes( $new_table_prefixes )
		          ->set_old_replace_values( $old_replace_values )
		          ->set_new_replace_values( $new_replace_values )
		          ->set_old_replace_raw_values( $old_replace_raw_values )
		          ->set_new_replace_raw_values( $new_replace_raw_values );

		// Set atomic tables (do not stop current request for all listed tables if timeout has been exceeded)
		$db_client->set_atomic_tables( array( ai1wm_table_prefix() . 'options' ) );

		// Set empty tables (do not populate current data for all listed tables)
		$db_client->set_empty_tables( array( ai1wm_table_prefix() . 'eum_logs' ) );

		// Set Visual Composer
		$db_client->set_visual_composer( ai1wm_validate_plugin_basename( 'js_composer/js_composer.php' ) );

		// Set Oxygen Builder
		$db_client->set_oxygen_builder( ai1wm_validate_plugin_basename( 'oxygen/functions.php' ) );

		// Set Optimize Press
		$db_client->set_optimize_press( ai1wm_validate_plugin_basename( 'optimizePressPlugin/optimizepress.php' ) );

		// Set Avada Fusion Builder
		$db_client->set_avada_fusion_builder( ai1wm_validate_plugin_basename( 'fusion-builder/fusion-builder.php' ) );

		// Set BeTheme Responsive
		$db_client->set_betheme_responsive( ai1wm_validate_theme_basename( 'betheme/style.css' ) );

		$query_offset = get_option( 'sss_migration_db_query_offset', 0 );

		$completed = false;

		// Import database
		if ( $db_client->import( $file, $query_offset ) ) {

			// Set progress
			Migration::setStatus( 'Database Migrated' );
			Logger::log('Database Migrated');
			delete_option( 'sss_migration_db_query_offset' );

			$completed = true;

		} else {

			// Get total queries size
			$total_queries_size = filesize( $file );

			// What percent of queries have we processed?
			$progress = (int) ( ( $query_offset / $total_queries_size ) * 100 );

			// Set progress
			Migration::setStatus( sprintf( 'Migrating database...<br />%d%% complete', $progress ) );

			Logger::log( sprintf( 'Migrating database...<br />%d%% complete', $progress ) );

			update_option( 'sss_migration_db_query_offset', $query_offset );
		}

		// Flush WP cache
		ai1wm_cache_flush();

		if ( $completed ) {
			Migration::setStatus("Database migrated" );
		}
		$this->done = $completed;

		return $completed;

	}
}