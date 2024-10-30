<?php
/**
 * Routes interface.
 *
 * @package Kurve
 */

namespace KRV\Controllers\API\Routes;

/**
 * Interface for API routes.
 *
 * @author Aditya Bhaskar Sharma <adityabhaskarsharma@gmail.com>
 * @since 0.1.0
 */
interface RouteInterface {
	/**
	 * Undocumented function
	 *
	 * @author Aditya Bhaskar Sharma <adityabhaskarsharma@gmail.com>
	 * @since 0.1.0
	 * @param \WP_REST_Request $request Holds API request data.
	 * @return void
	 */
	public function get( \WP_REST_Request $request );
}
