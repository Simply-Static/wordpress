<?php

namespace Simply_Static_Studio\MagicLink;

use Simply_Static_Studio\BasicAuth\BasicAuth;

class Link {

	protected $key = null;

	public function __construct( $publicKey ) {
		$this->key = $publicKey;
	}


	protected function hashData( $data ) {
		// We need to hash the salt to produce a key that won't exceed the maximum of 64 bytes.
		$key = sodium_crypto_generichash(wp_salt('auth'));
		$bin = sodium_crypto_generichash( $data, $key);

		return sodium_bin2base64($bin, SODIUM_BASE64_VARIANT_URLSAFE);
	}

	public function login() {
		try {
			$data = $this->getData();
			$this->validate( $data );
			$this->loginUser( $data );
			$this->redirect();
		} catch ( \Exception $e ) {
			throw $e;
			return new \WP_Error( $e->getCode(), $e->getMessage() );
		}
	}

	public function redirect() {
		$redirect  = admin_url();
		$basicAuth = new BasicAuth();
		$redirect  = $basicAuth->prepare_link($redirect);
		wp_redirect($redirect);
		exit;
	}

	public function loginUser( $data ) {
		$this->expireLink();

		$user = get_user_by('id',  $data['user'] );

		if ( ! $user ) {
			throw new \Exception( 'Invalid Link' );
		}

		wp_set_auth_cookie($user->ID);

		/**
		 * Fires after the user has successfully logged in.
		 *
		 * @param string  $user_login Username.
		 * @param \WP_User $user       WP_User object of the logged-in user.
		 */
		do_action('wp_login', $user->user_login, $user);
	}

	public function validate( $data ) {
		$signature = $this->signature( $data );

		if ( hash_equals( $data['private'], $this->hashData( $signature ) ) ) {
			return true;
		}

		throw new \Exception( 'Invalid link' );
	}

	/**
	 * Build the signature for the given endpoint.
	 *
	 * @param $endpoint
	 *
	 * @return string
	 */
	private function signature( $data )
	{
		return join('|', [
			$this->key,
			$data['user'],
			$data['expires_at']
		]);
	}

	public function getData() {
		$publicHash = $this->hashData( $this->key );

		$data = get_transient( 'ss_' . $publicHash );

		if ( ! $data ) {
			throw new \Exception( 'Link Expired' );
		}

		return $data;
	}

	public function expireLink() {
		$publicHash = $this->hashData( $this->key );

		delete_transient( 'ss_' . $publicHash );
	}
}