<?php

namespace Simply_Static_Studio;

use Simply_Static\Page;
use Simply_Static_Studio\Logs\Logger;
use Simply_Static_Studio\Migration\Migration;
use Simply_Static_Studio\Models\FormEntry;
use WPForms\Admin\Builder\Help;

class Installation {

	protected $objects = [];

	protected $migrationProcess = null;

	public function getMigrationProcess() {
		if ( null === $this->migrationProcess ) {
			$this->migrationProcess = Migration::getMigrationProcess();
		}

		return $this->migrationProcess;
	}

	public function addInstall( $object ) {
		$this->objects[] = $object;
	}

	public function run() {
		// Always instantiate it.
		$this->getMigrationProcess();

		// Using 'init' for globals to be set before installing.
		add_action( 'init', [ $this, 'maybe_install' ], PHP_INT_MAX );
	}

	public function getDestinationUrl() {
		$destinationUrl = Helper::getPublicUrl();

		return str_replace( 'https://', '', $destinationUrl );
	}

	public function scheduleFirstExport() {
		if ( ! is_plugin_active( 'simply-static/simply-static.php' ) ) {
			return;
		}

		$options = get_option( 'simply-static' );

		if ( ! empty( $options['initial_export'] ) ) {
			return;
		}

		wp_next_scheduled( 'simply_static_site_export_cron' ) || wp_schedule_single_event( time(), 'simply_static_site_export_cron' );

		// Set initial export flag.
		$options['initial_export'] = true;
		update_option( 'simply-static', $options );
	}

	public function installDefaults() {
		$this->installWPDefaults();
		$this->installSimplyStaticDefaults();
		$this->scheduleFirstExport();
	}

	public function installSimplyStaticDefaults() {
		$options = get_option( 'simply-static', [] );

		$defaults = [
			'destination_url_type' => 'absolute',
			'destination_scheme'   => 'https://',
			'destination_host'     => $this->getDestinationUrl(),
			'delivery_method'      => 'simply-static-studio'
		];

		// Only if the options are empty we do this.
		// In case of a migration, this options might not be empty,
		// so we only need delivery method.
		if ( empty( $options ) ) {
			$defaults = array_merge( $defaults, [
				'use_forms'            => 1,
				'use_search'           => 1,
				'search_type'          => 'fuse',
				'search_index_title'   => 'title',
				'search_index_content' => 'body',
				'search_index_excerpt' => '.entry-content',
				'fuse_selector'        => '.wp-block-search'
			]);
		}

		$options = array_merge( $options, $defaults );

		update_option( 'simply-static', $options );
	}

	public function installWPdefaults() {

		// If this site has a migration, no need for this to be set.
		if ( Migration::hasMigration() ) {
			return;
		}

		// Delete Sample Page & Privacy Policy.
		wp_delete_post( 2, true );
		wp_delete_post( 3, true );

		// Create "Home" page.
		$page_id = wp_insert_post( [
			'post_title'   => 'Home',
			'post_content' => '<!-- wp:pattern {"slug":"ollie/page-home"} /-->',
			'post_status'  => 'publish',
			'post_type'    => 'page'
		] );

		// Set template.
		update_post_meta( $page_id, '_wp_page_template', 'page-no-title' );

		// Set as front page.
		update_option( 'page_on_front', $page_id );
		update_option( 'show_on_front', 'page' );

		// Create "Blog" page.
		$blog_id = wp_insert_post( [
			'post_title'   => 'Blog',
			'post_content' => '<!-- wp:pattern {"slug":"ollie/page-blog"} /-->',
			'post_status'  => 'publish',
			'post_type'    => 'page'
		] );

		// Set template.
		update_post_meta( $blog_id, '_wp_page_template', 'page-no-title' );

		// Set as posts page.
		update_option( 'page_for_posts', $blog_id );

		// Find header template in Ollie theme and replace content.
		$theme           = wp_get_theme();
		$theme_path      = $theme->get_stylesheet_directory();
		$header_template = $theme_path . '/parts/header.html';

		if ( file_exists( $header_template ) ) {
			// Replace content of the header with the template content.
			$new_content = '<!-- wp:group {"tagName":"header","metadata":{"name":"Header"},"align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|medium","bottom":"var:preset|spacing|medium","right":"var:preset|spacing|medium","left":"var:preset|spacing|medium"}},"elements":{"link":{"color":{"text":"var:preset|color|main"}}},"border":{"bottom":{"color":"var:preset|color|border-light","width":"1px"},"top":{},"right":{},"left":{}}},"backgroundColor":"base","layout":{"inherit":true,"type":"constrained"}} -->
<header class="wp-block-group alignfull has-base-background-color has-background has-link-color" style="border-bottom-color:var(--wp--preset--color--border-light);border-bottom-width:1px;padding-top:var(--wp--preset--spacing--medium);padding-right:var(--wp--preset--spacing--medium);padding-bottom:var(--wp--preset--spacing--medium);padding-left:var(--wp--preset--spacing--medium)"><!-- wp:group {"align":"wide","layout":{"type":"flex","justifyContent":"space-between"}} -->
<div class="wp-block-group alignwide"><!-- wp:site-title {"level":0} /-->

<!-- wp:navigation {"openSubmenusOnClick":true,"icon":"menu","style":{"spacing":{"blockGap":"var:preset|spacing|small"},"layout":{"selfStretch":"fit","flexSize":null}},"fontSize":"small"} /-->
    <!-- wp:search {"label":"Search","showLabel":false,"placeholder":"Search..","buttonText":"Search","buttonPosition":"no-button"} /--></div>
<!-- /wp:group --></header>
<!-- /wp:group -->';

			// Overwrite content and save file.
			file_put_contents( $header_template, $new_content );
		}

		// Set default permalink structure.
		update_option( 'permalink_structure', '/%postname%/' );
	}

	public function maybe_install() {

		// Not yet, let migration first.
		if ( Migration::needsToRun() ) {
			Migration::startMigration();

			return;
		}

		$version = get_option( 'simply-static-studio-helper-version', null );

		// New installation, set default options.
		if ( null === $version ) {
			FormEntry::create_or_update_table();
			$this->installDefaults();

			if ( ! empty( $this->objects ) ) {
				foreach ( $this->objects as $object ) {
					if ( ! method_exists( $object, 'install' ) ) {
						continue;
					}

					$object->install();
				}
			}
		} else {
			if ( version_compare( $version, SSS_VERSION, '!=' ) ) {
				// Sync database.
				FormEntry::create_or_update_table();

				if ( ! empty( $this->objects ) ) {
					foreach ( $this->objects as $object ) {
						if ( ! method_exists( $object, 'update' ) ) {
							continue;
						}

						$object->update();
					}
				}
			}
		}

		update_option( 'simply-static-studio-helper-version', SSS_VERSION );

	}

}