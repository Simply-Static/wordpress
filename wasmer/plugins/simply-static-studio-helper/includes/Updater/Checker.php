<?php

namespace Simply_Static_Studio\Updater;

class Checker {

	public function getCurrentVersion() {
		return SSS_VERSION;
	}

	public function getVersionUri() {
		return 'https://api.static.studio/storage/v1/object/public/plugins/data.json';
	}

	public function isUpdateAvailable() {
		$response = wp_remote_get( $this->getVersionUri() );

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( empty( $data ) ) {
			return false;
		}

		if ( empty( $data['studio_version'] ) ) {
			return false;
		}

		if ( version_compare( $this->getCurrentVersion(), $data['studio_version'], '<' ) ) {
			return true;
		}

		return false;
	}
}