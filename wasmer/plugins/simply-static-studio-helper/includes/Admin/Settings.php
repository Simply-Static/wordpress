<?php

namespace Simply_Static_Studio\Admin;

class Settings {

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu' ), PHP_INT_MAX );

	}

	public function add_menu() {
		 $menu_hook = add_submenu_page(
			'simply-static-generate',
			__( 'Entries', 'simply-static-studio' ),
			__( 'Entries', 'simply-static-studio' ),
			apply_filters( 'ss_user_capability', 'publish_pages', 'studio' ),
			'simply-static-entries',
			array( $this, 'render' ),
             4
		);

		add_action( "admin_print_scripts-{$menu_hook}", array( $this, 'add_settings_scripts' ) );

	}

    public function add_settings_scripts() {

	    wp_enqueue_script( 'simplystatic-settings', SSS_URL . '/includes/Admin/build/index.js', array(
		    'wp-api',
		    'wp-components',
		    'wp-element',
		    'wp-api-fetch',
		    'wp-data',
		    'wp-i18n',
		    'wp-block-editor'
	    ), SSS_VERSION, true );

        $args = [
	        'initial' => '/',
	        'logo'    => defined('SIMPLY_STATIC_URL' ) ? SIMPLY_STATIC_URL . '/assets/simply-static-logo.svg' : '',
        ];

	    wp_localize_script( 'simplystatic-settings', 'studio_options', $args );

	    wp_enqueue_style( 'simplystatic-settings-style', SSS_URL . '/includes/Admin/build/index.css', array( 'wp-components' ) );
    }

	public function render() {
		?>

			<div id="static-studio-settings"></div>

		<?php
	}
}