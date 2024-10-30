<?php
/**
 * Assets controller.
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
class AssetsController {

	/**
	 * Undocumented function
	 *
	 * @author Aditya Bhaskar Sharma <adityabhaskarsharma@gmail.com>
	 * @since 0.1.0
	 * @return array
	 */
	public function loadScripts()
	{
		return [
			[
				'id'        => KRV_SLUG,
				'path'      => KRV_PUBLIC_URL . 'app.js?v=' . microtime(),
				'deps'      => [ 'wp-i18n' ], // Added for JS localization support.
				'version'   => null,
				'in_footer' => true,
			],
		];
	}

	/**
	 * Undocumented function
	 *
	 * @author Aditya Bhaskar Sharma <adityabhaskarsharma@gmail.com>
	 * @since 0.1.0
	 * @return array
	 */
	public function loadStyles()
	{
		return [
			[
				'id'      => KRV_SLUG,
				'path'    => KRV_PUBLIC_URL . 'app.css?v=' . microtime(),
				'deps'    => [],
				'version' => false,
				'media'   => 'all',
			],
		];
	}

	/**
	 * Undocumented function
	 *
	 * @author Aditya Bhaskar Sharma <adityabhaskarsharma@gmail.com>
	 * @since 0.1.0
	 * @return void
	 */
	public function enqueueBackStyles()
	{
		$screen = get_current_screen();

		// Load styles only for the plugin page.
		if ( strpos( $screen->id, 'krv' ) !== false ) {
			foreach ( $this->loadStyles() as $style ) {
				wp_enqueue_style( $style['id'], $style['path'], $style['deps'], $style['version'], $style['media'] );
			}
		}
	}

	/**
	 * Undocumented function
	 *
	 * @author Aditya Bhaskar Sharma <adityabhaskarsharma@gmail.com>
	 * @since 0.1.0
	 * @return void
	 */
	public function enqueueBackScripts()
	{
		$screen = get_current_screen();

		// Load scripts only for the plugin page.
		if ( strpos( $screen->id, 'krv' ) !== false ) {
			foreach ( $this->loadScripts() as $script ) {
				wp_enqueue_script( $script['id'], $script['path'], $script['deps'], $script['version'], $script['in_footer'] );
			}

			// Making variables accessible in JS.
			// @todo Maybe fix this.
			$inlineJS = "const _KRVJS = '" . wp_json_encode(
				[
					'api'   => [
						'name'     => KRV_FULL_NAME,
						'internal' => rest_url() . KRV_INT_API_NS,
						'nonce'    => wp_create_nonce( 'wp_rest' ),
						'basename' => '/wp-admin/admin.php',
					],
					// @todo Improve this. Doing this to have array of JSON objects.
					'pages' => array_values(
						array_filter(
							( new MenusController )->getPages(),
							function( $page ) {
								if ( $page['is_submenu'] ) {
									return true;
								}
							}
						)
					),
				],
			) . "'";
			wp_add_inline_script( KRV_SLUG, $inlineJS, 'before' );
		}
	}

	/**
	 * Undocumented function
	 *
	 * @author Aditya Bhaskar Sharma <adityabhaskarsharma@gmail.com>
	 * @since 0.1.0
	 * @return void
	 */
	public function enqueueBackendAssets()
	{
		$this->enqueueBackScripts();
		$this->enqueueBackStyles();
	}
}
