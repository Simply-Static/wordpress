<?php

namespace Simply_Static_Studio\BasicAuth;

class Integration {

	public function integrate() {
		add_filter( 'ssp_basic_auth_enabled', [ $this, 'enableBasicAuth' ] );
		add_filter('ssp_exclude_auth_list', [ $this, 'excludeAuthList' ] );

		$this->definePluginConstant();
	}
	
	public function excludeAuthList( $list ) {
		$list[] = 'admin-ajax';
		$list[] = 'wp-comments';

		return $list;
	}

	public function definePluginConstant() {
		$basicAuth = new BasicAuth();

		if ( ! $basicAuth->isSet() ) {
			return;
		}

		define( 'SIMPLY_STATIC_' . strtoupper( 'http_basic_auth_username' ), $basicAuth->getAuthUsername() );
		define( 'SIMPLY_STATIC_' . strtoupper( 'http_basic_auth_password' ), $basicAuth->getAuthPassword() );
	}

	public function enableBasicAuth( $bool ) {
		if ( $bool ) {
			return $bool;
		}

		$basicAuth = new BasicAuth();

		return $basicAuth->isSet();
	}
}