<?php
/**
 * Bootstrap all plugin logic.
 *
 * @package Kurve
 */

namespace KRV;

use KRV\Controllers\AdminController;
use KRV\Controllers\API\Routes\RoutesController;

/**
 * Bootstrap class.
 *
 * @author Aditya Bhaskar Sharma <adityabhaskarsharma@gmail.com>
 * @since 0.1.0
 */
class Bootstrap {
	/**
	 * Include all the classes that are holding the plugin together.
	 *
	 * @author Aditya Bhaskar Sharma <adityabhaskarsharma@gmail.com>
	 * @since 0.1.0
	 * @return void
	 */
	public static function boot()
	{
		RoutesController::boot();
		AdminController::boot();
	}
}
