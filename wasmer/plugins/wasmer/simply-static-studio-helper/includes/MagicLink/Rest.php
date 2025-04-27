<?php

namespace Simply_Static_Studio\MagicLink;

class Rest {

	public function register_routes() {
		add_action( 'rest_api_init', function () {
			register_rest_route( 'static-studio/v1', '/login/(?P<id>[a-zA-Z0-9-]+)', array(
				'methods' => 'GET',
				'show_in_index' => false,
				'permission_callback' => '__return_true',
				'callback' => [ $this, 'login'],
			) );

			register_rest_route( 'static-studio/v1', '/get-login/(?P<secret>\w+)/(?P<data>[a-zA-Z0-9-\.\@]+)', array(
				'methods' => 'GET',
				'show_in_index' => false,
				'permission_callback' => '__return_true',
				'callback' => [ $this, 'get_login_link' ],
			) );
		} );
	}

	function login( $data ) {
		$login_key = $data['id'];

		require_once 'Link.php';

		$link = new Link( $login_key );

		$link->login();
	}

	function get_login_link( $data ) {
		if ( empty( $data['secret'] ) || empty( $data['data'] ) ) {
			return new \WP_Error( 404, 'Missing parameters to generate the link' );
		}

		if ( ! defined( 'SSS_SITE_ID' ) ) {
			return new \WP_Error( 404, 'Missing configuration. Contact Studio support.' );
		}

		$site_id = SSS_SITE_ID;

		if ( absint( $data['secret'] ) !== absint( $site_id ) ) {
			return new \WP_Error( 404, 'Parameters to generate the link should be a valid' );
		}

		$email = $data['data'];

		$user = get_user_by( 'email', $email );

		if ( ! $user ) {
			return new \WP_Error( 404, 'Parameters to generate the link should be a valid' );
		}

		require_once 'GenerateLink.php';

		$link = new GenerateLink( $user );
		return rest_ensure_response( $link->generate() );
	}
}