<?php

namespace Simply_Static_Studio\Rest;

use Simply_Static_Studio\Options;

class Domains extends Rest {

	protected $route = 'domains';

	public function createItem( \WP_REST_Request $request ) {
		$domain                      = $request->get_param( 'domain' );
		$options                     = get_option( 'simply-static' );
		$options['destination_host'] = $domain;

		update_option( 'simply-static', $options );

		return json_encode( [ 'status' => 200, 'message' => "Ok" ] );
	}

	public function verifyRequest( \WP_REST_Request $request ) {
		$secret = SSS_SECRET_KEY;

		if ( empty( $secret ) ) {
			return true;
		}

		// Get secret from $request as a header parameter
		$secret_from_request = $request->get_header( 'X-Simply-Static-Studio-Secret' );

		if ( empty( $secret_from_request ) ) {
			return false;
		}

		if ( $secret !== $secret_from_request ) {
			return false;
		}

		return true;
	}
}