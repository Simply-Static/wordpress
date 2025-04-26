<?php

namespace Simply_Static_Studio\Rest;

use Simply_Static_Studio\Migration\Migration;

class Migrations extends Rest {

	protected $availableRoutes = [
		'getItems'
	];

	protected $route = 'migration';

	public function getItems( \WP_REST_Request $request ) {
		if ( ! Migration::hasMigration() ) {
			return rest_ensure_response( [
				'status' => 'deployed' // No migration needed.
			] );
		}

		if ( Migration::migrated() ) {
			return rest_ensure_response( [ 'status' => 'deployed' ] ); // Sending 'deployed' status over to stop the checks.
		}

		return rest_ensure_response( [ 'status' => 'migrating', 'details' => Migration::getStatus() ] );
	}
}