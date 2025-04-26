<?php

namespace Simply_Static_Studio\Rest;

use Simply_Static_Studio\Options;

class Users extends Rest {

	protected $route = 'users';

	public function deleteItem( \WP_REST_Request $request ) {
		$email = sanitize_email( wp_unslash( $request->get_param( 'email' ) ) );

		if ( ! is_email( $email ) ) {
			return json_encode( [ 'status' => 500, 'message' => "Not valid email" ] );
		}

		// Find user by email and delete it.
		$user = get_user_by( 'email', $email );

		if ( $user ) {
			wp_delete_user( $user->ID );

			return json_encode( [ 'status' => 200, 'message' => "User deleted" ] );
		}

		return json_encode( [ 'status' => 404, 'message' => "User not found" ] );
	}

	public function createItem( \WP_REST_Request $request ) {
		$email = $request->get_param( 'email' );

		// Create user with e-mail.
		$user_id = wp_insert_user( [
			'user_email' => $email,
			'user_login' => $email,
			'user_pass'  => wp_generate_password(),
			'role'       => 'administrator',
		] );

		if ( $user_id ) {
			return json_encode( [ 'status' => 200, 'message' => "User created" ] );
		}

		return json_encode( [ 'status' => 404, 'message' => "User not created" ] );
	}

	public function verifyDeleteItemPermission( \WP_REST_Request $request ) {
		return $this->verifyRequest( $request );
	}

	public function registerDeleteItemRoute( $options = [] ) {
		$this->registerRoute(
			\WP_REST_Server::DELETABLE,
			[ $this, 'deleteItem' ],
			[ $this, 'verifyDeleteItemPermission' ],
			'(?P<email>.+)',
			$options
		);
	}

	public function verifyRequest( \WP_REST_Request $request ) {
		$secret  = SSS_SECRET_KEY;

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