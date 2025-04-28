<?php

namespace Simply_Static_Studio\Plugins;

use simply_static_pro\Form_Settings;
use Simply_Static_Studio\BasicAuth\BasicAuth;
use Simply_Static_Studio\Logs\Logger;
use Simply_Static_Studio\Migration\Migration;
use Simply_Static_Studio\Models\FormEntry;

class WPForms {

	public function __construct() {
		// Making sure we don't see welcome screen on WP Forms.
		// This prevents it from user being redirected on the Welcome screen on the first login.
		add_filter( 'pre_option_wpforms_activation_redirect', '__return_true' );

		add_action( 'simply_static_studio_form_submitted_set_data', [ $this, 'form_set_data' ] );
		add_filter( 'simply_static_studio_formatted_entry', [ $this, 'form_format_data' ] );
		add_filter( 'ssp_forms_args', [ $this, 'add_studio_form_args' ], 10, 2 );
	}

	/**
	 * Format the entry.
	 *
	 * @param array $posted Array of posted data.
	 *
	 * @return mixed
	 */
	public function form_format_data( $posted ) {
		// Clean up the data
		$formatted_content = '';

		if ( ! empty( $posted['wpforms'] ) ) {
			if ( $posted['wpforms']['fields'] ) {
				$formatted_content .= '<div class="sss-entry-data">';

				foreach ( $posted['wpforms']['fields'] as $key => $value ) {
					if ( $value ) {
						if ( is_array( $value ) ) {
							$value_object = $value;
							$value_string = '';

							foreach ( $value_object as $key => $value ) {
								if ( ! is_array( $value ) ) {
									$value_string .= $value . ' ';
								}
							}

							$formatted_content .= $value_string . '<br>';
						} else {
							$formatted_content .= $value . '<br>';
						}
					}
				}

				$formatted_content .= '</div>';

			}
		} else {
			$formatted_content .= '<div class="sss-entry-data">';

			foreach ( $posted as $key => $value ) {
				if ( $value ) {
					if ( is_array( $value ) ) {
						$value_object = $value;
						$value_string = '';

						foreach ( $value_object as $key => $value ) {
							$value_string .= $value . ' ';
						}

						$formatted_content .= $value_string . '<br>';
					} else {
						$formatted_content .= $value . '<br>';
					}
				}
			}

			$formatted_content .= '</div>';
		}

		return $formatted_content;
	}

	/**
	 * @param FormEntry $entry Object.
	 *
	 * @return void
	 */
	public function form_set_data( $entry ) {
		$data    = $entry->posted;
		$decoded = json_decode( $data, true );

		if ( empty( $decoded['wpforms'] ) ) {
			return;
		}

		if ( empty( $decoded['wpforms']['id'] ) ) {
			return;
		}

		$entry->form_id     = absint( $decoded['wpforms']['id'] );
		$entry->form_plugin = 'wpforms';
		$entry->title       = wpforms_get_post_title( get_post( $entry->form_id ) );
		$fields             = wpforms_get_form_fields( $entry->form_id );

		foreach ( $fields as $field ) {
			if ( $field['type'] !== 'email' ) {
				continue;
			}
			break;
		}
	}

	public function install() {

		// Only if WP Forms exists.
		if ( ! defined( 'WPFORMS_VERSION' ) ) {
			return;
		}

		// If this site has a migration, no need for this to be set.
		if ( Migration::hasMigration() ) {
			return;
		}

		try {
			$this->set_form_use();

			$form_id = $this->create_wp_form();

			$this->embed_wp_form( $form_id );

			$this->create_ssp_connection( $form_id );
		} catch ( \Exception $e ) {
			Logger::log( "WP Forms installation error: " . $e->getMessage() );
		}

	}

	protected function set_form_use() {
		$options = get_option( 'simply-static', [] );

		$options['use_forms'] = true;

		update_option( 'simply-static', $options );
	}

	/**
	 * Create a WP Forms Form
	 *
	 * @return mixed
	 */
	protected function create_wp_form() {
		add_filter( 'wpforms_current_user_can', '__return_true' );

		$form_id = wpforms()->obj( 'form' )->add(
			'Contact Form',
			[],
			[
				'template'    => 'simple-contact-form-template',
				'category'    => 'all',
				'subcategory' => 'all',
			]
		);

		$wpforms = wpforms()->obj( 'form' )->get( $form_id );

		$form_data = wpforms_decode( $wpforms->post_content );

		// Making sure the ID is set inside of JSON to remove PHP warnings.
		if ( empty( $form_data['id'] ) ) {
			$form_data['id'] = $form_id;
		}

		if ( empty( $form_data['settings'] ) ) {
			$form_data['settings'] = [];
		}

		// Disabling AJAX Submit on the form.
		//$form_data['settings']['ajax_submit'] = "0";

		wpforms()->obj( 'form' )->update( $form_id, $form_data );

		remove_filter( 'wpforms_current_user_can', '__return_true' );

		return $form_id;
	}

	/**
	 * Embed the form on a Page.
	 *
	 * @param integer $form_id Form Id.
	 *
	 * @return void
	 */
	protected function embed_wp_form( $form_id ) {

		if ( wpforms_is_gutenberg_active() ) {
			$pattern = '<!-- wp:wpforms/form-selector {"formId":"%d"} /-->';
		} else {
			$pattern = '[wpforms id="%d" title="false" description="false"]';
		}

		$post_content = sprintf( $pattern, absint( $form_id ) );

		$contactPageId = wp_insert_post( [
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_title'   => 'Contact',
			'post_content' => $post_content,
		] );

		$url = get_permalink( $contactPageId );
		$url = str_replace( untrailingslashit( home_url() ), '', $url );

		$formLocations = [
			[
				'type'    => 'page',
				'title'   => 'Contact',
				'form_id' => $form_id,
				'id'      => $contactPageId,
				'status'  => 'publish',
				'url'     => $url,
			]
		];

		update_post_meta( $form_id, 'wpforms_form_locations', $formLocations );

	}

	/**
	 * Create a SSP Form Connection.
	 *
	 * @param integer $form_id WP Form Id.
	 *
	 * @return void
	 */
	protected function create_ssp_connection( $form_id ) {
		$basicAuth = new BasicAuth();

		$meta = [
			'form_type'      => 'webhook',
			'form_plugin'    => 'wp_forms',
			'form_id'        => 'wpforms-form-' . $form_id,
			'form_shortcode' => '[wpforms id="' . $form_id . '" title="false" description="false"]',
			'form_webhook'   => rest_url( 'static-studio/v1/entries' ),
		];

		$secret = SSS_SECRET_KEY;

		if ( ! empty( $secret ) ) {
			$meta['form_custom_headers'] = 'X-Simply-Static-Studio-Secret:' . $secret;
		}

		$connection_id = wp_insert_post( [
			'post_type'   => 'ssp-form',
			'post_status' => 'publish',
			'post_title'  => 'Contact Form',
		] );

		foreach ( $meta as $key => $value ) {
			update_post_meta( $connection_id, $key, $value );
		}

		/** @var Form_Settings $form_settings */
		$form_settings = Form_Settings::get_instance();
		$form_settings->create_config_file();
	}

	public function add_studio_form_args( $args, $post_id ) {
		$meta = [
			'form_email_recipient' => get_post_meta( $post_id, 'form_email_recipient', true )
		];

		// Merge meta fields.
		$args['meta'] = array_merge( $args['meta'], $meta );

		// Add e-mail related data to the form.
		$args['endpoint']  = get_rest_url( null, '/static-studio/v1/entries' );
		$args['secret']    = SSS_SECRET_KEY;
		$args['email']     = SSS_EMAIL;
		$args['site_name'] = get_bloginfo( 'name' );

		return $args;
	}
}