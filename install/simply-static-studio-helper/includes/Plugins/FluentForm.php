<?php

namespace Simply_Static_Studio\Plugins;

use Simply_Static_Studio\Models\FormEntry;

class FluentForm {

	public function __construct() {
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

		$formatted_content .= '<div class="sss-entry-data">';

		foreach ( $posted as $key => $value ) {
			if ( strpos( $key, '_fluentform' ) !== false ) {
				continue;
			}

			if ( strpos( $key, '__fluent_form_embded_post_id' ) !== false ) {
				continue;
			}

			if ( strpos( $key, '_wp_http_referer' ) !== false ) {
				continue;
			}

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

		if ( empty( $decoded['__fluent_form_embded_post_id'] ) ) {
			return;
		}

		// Get the post id from the entry.
		$post_id = absint( $decoded['__fluent_form_embded_post_id'] );

		$entry->form_id     = $post_id;
		$entry->form_plugin = 'fluentforms';
		$entry->title       = get_the_title( $post_id );

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