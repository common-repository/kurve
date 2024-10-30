<?php
/**
 * Plugin Name:       Kurve
 * Description:       View your store's in-depth metrics and improve the health of your business. Monitor key metrics like revenue, orders, conversion rates, top customer orders and more.
 * Version:           0.1.1
 * Author:            LazyCodeLab
 * Author URI:        https://lazycodelab.com
 * License:           GPLv2 or later
 * License URI:       http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 * Requires at least: 4.9
 * Tested up to:      5.8
 * Requires PHP:      5.6
 *
 * @package Kurve
 */

defined( 'ABSPATH' || exit );
define( 'KRV_FILE', __FILE__ );

require_once 'vendor/autoload.php';

use KRV\Controllers\DependencyController;
use KRV\Exceptions\MissingDependencyException;

/**
 * Undocumented class
 *
 * @author Aditya Bhaskar Sharma <adityabhaskarsharma@gmail.com>
 * @since 0.1.0
 */
final class Kurve {
	/**
	 * Undocumented function
	 *
	 * @author Aditya Bhaskar Sharma <adityabhaskarsharma@gmail.com>
	 * @since 0.1.0
	 */
	public function __construct()
	{
		add_action( 'admin_init', [ $this, 'activate' ] );
	}

	/**
	 * Undocumented function
	 *
	 * @author Aditya Bhaskar Sharma <adityabhaskarsharma@gmail.com>
	 * @since 0.1.0
	 * @return void
	 */
	public function activate() {
		try {
			DependencyController::check();
			KRV\Bootstrap::boot();
		} catch ( \Exception $e ) {
			deactivate_plugins( KRV_FILE );
			$this->display_missing_dependencies_notice( $e );
		}
	}

	/**
	 * Undocumented function
	 *
	 * @author Aditya Bhaskar Sharma <adityabhaskarsharma@gmail.com>
	 * @since 0.1.0
	 * @param \Exception $e Exception instance.
	 * @return void
	 */
	private function display_missing_dependencies_notice( \Exception $e ) {
		$missing_dependency_reporter = new MissingDependencyException( $e->getMessage() );
		$missing_dependency_reporter->init();
	}
}

new Kurve();
