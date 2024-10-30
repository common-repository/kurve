<?php
/**
 * Routes controller.
 *
 * @package Kurve
 */

namespace KRV\Controllers\API\Routes;

use KRV\Controllers\API\Routes\Reports\CustomersReportRouteController;
use KRV\Controllers\API\Routes\Reports\DevicesReportRouteController;
use KRV\Controllers\API\Routes\Reports\OrdersReportRouteController;
use KRV\Controllers\API\Routes\Reports\ProductsReportRouteController;
use KRV\Controllers\API\Routes\Reports\ProfitReportRouteController;
use KRV\Controllers\API\Routes\Reports\RefundsReportRouteController;
use KRV\Controllers\API\Routes\Reports\RevenueReportRouteController;
use KRV\Controllers\API\Routes\Reports\SourcesReportRouteController;
use KRV\Controllers\API\Routes\Reports\TaxesReportRouteController;

/**
 * Routes controller class.
 * Register and hook up all the API routes.
 *
 * @author Aditya Bhaskar Sharma <adityabhaskarsharma@gmail.com>
 * @since 0.1.0
 */
class RoutesController {
	/**
	 * Undocumented function
	 *
	 * @author Aditya Bhaskar Sharma <adityabhaskarsharma@gmail.com>
	 * @since 0.1.0
	 * @return void
	 */
	public static function boot()
	{
		add_action( 'rest_api_init', [ __CLASS__, 'initRoutes' ] );
	}
	/**
	 * Collection of all the routes controllers.
	 *
	 * @author Aditya Bhaskar Sharma <adityabhaskarsharma@gmail.com>
	 * @since 0.1.0
	 * @return void
	 */
	public static function initRoutes()
	{
		$allRoutes = [
			new RevenueReportRouteController,
			new OrdersReportRouteController,
			new TaxesReportRouteController,
			new ProfitReportRouteController,
			new CustomersReportRouteController,
			new RefundsReportRouteController,
			new ProductsReportRouteController,
			new SourcesReportRouteController,
			new DevicesReportRouteController,
		];

		foreach ( $allRoutes as $route ) {
			self::generateRoute( $route );
		}
	}

	/**
	 * Register all the API routes.
	 *
	 * @author Aditya Bhaskar Sharma <adityabhaskarsharma@gmail.com>
	 * @since 0.1.0
	 * @param object $route Route controller.
	 * @return void
	 */
	public static function generateRoute( $route )
	{
		register_rest_route(
			KRV_INT_API_NS,
			$route->namespace,
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $route, 'get' ],
					'permission_callback' => '__return_true',
				],
			]
		);
	}
}
