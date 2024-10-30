<?php
/**
 * Dependency controller.
 *
 * @package kurve
 */

namespace KRV\Controllers;

use KRV\Exceptions\MissingDependencyException;

/**
 * Undocumented class
 *
 * @author Aditya Bhaskar Sharma <adityabhaskarsharma@gmail.com>
 * @since 0.1.1
 */
class DependencyController {
	const REQUIRED_PLUGINS = [
		'WooCommerce' => 'woocommerce/woocommerce.php',
	];

	/**
	 * Undocumented function
	 *
	 * @author Aditya Bhaskar Sharma <adityabhaskarsharma@gmail.com>
	 * @since 0.1.0
	 * @throws MissingDependencyException Lmao lol.
	 * @return void
	 */
	public static function check()
	{
		$missing_plugins = self::get_missing_plugin_list();

		if ( ! empty( $missing_plugins ) ) {
			// The exception holds the names of missing plugins.
			throw new \Exception( $missing_plugins[0] );
		}
	}

	/**
	 * Undocumented function
	 *
	 * @author Aditya Bhaskar Sharma <adityabhaskarsharma@gmail.com>
	 * @since 0.1.0
	 * @return array
	 */
	private static function get_missing_plugin_list()
	{
		// Only get the plugins that are *not* active.
		$missing_plugins = array_filter(
			self::REQUIRED_PLUGINS,
			array( __CLASS__, 'is_plugin_inactive' ),
			ARRAY_FILTER_USE_BOTH
		);

		return array_keys( $missing_plugins );
	}

	private static function is_plugin_inactive( $main_plugin_file_path )
	{
		return ! in_array( $main_plugin_file_path, self::get_active_plugins() );
	}

	private static function get_active_plugins()
	{
		return apply_filters( 'active_plugins', get_option( 'active_plugins' ) );
	}
}
