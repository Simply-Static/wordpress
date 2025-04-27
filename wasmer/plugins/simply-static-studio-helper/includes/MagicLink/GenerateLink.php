<?php

namespace Simply_Static_Studio\MagicLink;

use Simply_Static_Studio\BasicAuth\BasicAuth;

class GenerateLink {

	protected $user = null;

	protected $expiresAt = null;

	protected $publicKey = null;

	public function __construct( \WP_User $user, $duration = null ) {
		$this->user = $user;

		if ( ! $duration ) {
			$duration = 10 * MINUTE_IN_SECONDS;
		}

		$this->expiresAt = time() + $duration;
		$this->publicKey = $this->generateKey();
	}

	public function generate() {
		$data       = $this->getLinkData();
		$publicHash = $this->hashData( $this->publicKey );

		set_transient( 'ss_' . $publicHash, $data, $this->expiresAt );

		$basicAuth = new BasicAuth();

		return $basicAuth->prepare_link( get_rest_url( null, '/static-studio/v1/login/' . $this->publicKey ) );
	}

	protected function getLinkData() {
		return [
			'user'         => $this->user->ID,
			'private'      => $this->hashData( $this->signature() ),
			'expires_at'   => $this->expiresAt,
		];
	}

	protected function hashData( $data ) {
		// We need to hash the salt to produce a key that won't exceed the maximum of 64 bytes.
		$key = sodium_crypto_generichash(wp_salt('auth'));
		$bin = sodium_crypto_generichash( $data, $key);

		return sodium_bin2base64($bin, SODIUM_BASE64_VARIANT_URLSAFE);
	}

	/**
	 * Build the signature for the given endpoint.
	 *
	 * @param $endpoint
	 *
	 * @return string
	 */
	private function signature()
	{
		return join('|', [
			$this->publicKey,
			$this->user->ID,
			$this->expiresAt
		]);
	}

	/**
	 * This is the key that will be used for the login link.
	 *
	 * @return string
	 */
	public function generateKey() {

		return implode( '-', [
			$this->getRandomString(3, 5),
			$this->getRandomString(3, 5),
			$this->getRandomString(3, 5)
		]);

	}

	/**
	 * Ger a random string of characters.
	 *
	 * @param integer $min
	 * @param integer $max
	 *
	 * @return string
	 * @throws \Random\RandomException
	 */
	public function getRandomString( $min, $max ) {
		$min = absint($min);
		$max = absint($max ? $max : $min);
		return bin2hex(random_bytes(random_int($min, $max)));
	}
}