<?php

namespace Simply_Static_Studio\Rest;

use Simply_Static_Studio\Options;

class Settings extends Rest {

	protected $route = 'settings';

	public function getItems( \WP_REST_Request $request ) {
		$options = Options::instance();

		return rest_ensure_response(
			[
				'data' => $options->get_as_array()
			]
		);
	}

	public function createItem( \WP_REST_Request $request ) {

		$options = sanitize_option( 'simply-static-studio', $request->get_params() );

		// Sanitize each key/value pair in options.
		foreach ( $options as $key => $value ) {

			$options[ $key ] = sanitize_text_field( $value );

		}

		update_option( 'simply-static-studio', $options );

		return json_encode( [ 'status' => 200, 'message' => "Ok" ] );
	}
}