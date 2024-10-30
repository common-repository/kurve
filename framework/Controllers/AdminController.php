<?php
/**
 * Admin controller.
 *
 * @package Kurve
 */

namespace KRV\Controllers;

/**
 * Undocumented class
 *
 * @author Aditya Bhaskar Sharma <adityabhaskarsharma@gmail.com>
 * @since 0.1.0
 */
class AdminController {
	/**
	 * Undocumented function
	 *
	 * @author Aditya Bhaskar Sharma <adityabhaskarsharma@gmail.com>
	 * @since 0.1.0
	 * @return void
	 */
	public static function boot()
	{
		add_action( 'admin_enqueue_scripts', [ new AssetsController, 'enqueueBackendAssets' ] );

		// load menus.
		add_action( 'admin_menu', [ new MenusController, 'init' ] );
	}
}
