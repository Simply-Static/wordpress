<?php

namespace Simply_Static_Studio\BasicAuth;

class BasicAuth {

	public function isSet() {
		return $this->getAuthUsername() && $this->getAuthPassword();
	}

	public function getAuthUsername() {
		return defined( 'SSS_BASIC_AUTH_USER' ) ? SSS_BASIC_AUTH_USER : '';
	}


	public function getAuthPassword() {
		return defined( 'SSS_BASIC_AUTH_PASSWORD' ) ? SSS_BASIC_AUTH_PASSWORD : '';
	}

	public function prepare_link( $link ) {
		if ( ! $this->isSet() ) {
			return $link;
		}

		$prefix = 'https://';

		if ( str_starts_with( $link, 'http://' ) ) {
			$prefix = 'http://';
		}

		$link = str_replace( $prefix, '', $link );

		if ( str_starts_with( $link, 'www.' ) ) {
			$link = str_replace( 'www.', '', $link );
		}

		return $prefix . $this->getAuthUsername() . ':' . $this->getAuthPassword() . '@' . $link;
	}
}