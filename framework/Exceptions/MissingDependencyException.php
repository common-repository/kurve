<?php
/**
 * MissingDependencyException framework/Exceptions
 *
 * @package kurve
 */

namespace KRV\Exceptions;

/**
 * Undocumented class
 *
 * @author Aditya Bhaskar Sharma <adityabhaskarsharma@gmail.com>
 * @since 0.1.1
 */
class MissingDependencyException {
	const CAPABILITY_REQUIRED_TO_SEE_NOTICE = 'activate_plugins';

	/**
	 * Stores the missing plugin names extracted from the exception.
	 *
	 * @var array
	 */
	private $missing_plugin_names;

	/**
	 * Undocumented function
	 *
	 * @author Aditya Bhaskar Sharma <adityabhaskarsharma@gmail.com>
	 * @since 0.1.0
	 * @param array $missing_plugin_names Holds the plugin names extracted from the exception.
	 */
	public function __construct( $missing_plugin_names ) {
		$this->missing_plugin_names = $missing_plugin_names;
	}

	/**
	 * Main method that hooks into the 'admin_notices' hook that only
	 * runs in the admin dashboard.
	 */
	public function init() {
		add_action( 'admin_notices', array( $this, 'display_admin_notice' ) );
	}

	/**
	 * Undocumented function
	 *
	 * @author Aditya Bhaskar Sharma <adityabhaskarsharma@gmail.com>
	 * @since 0.1.0
	 * @return void
	 */
	public function display_admin_notice() {
		if ( current_user_can( self::CAPABILITY_REQUIRED_TO_SEE_NOTICE ) ) {
			$this->render_template();
		}
	}

	/**
	 * Undocumented function
	 *
	 * @author Aditya Bhaskar Sharma <adityabhaskarsharma@gmail.com>
	 * @since 0.1.0
	 * @return void
	 */
	private function render_template() {
		// This allows us to access the $missing_plugin_names variable in the view template.
		$missing_plugin_names = $this->missing_plugin_names;

		/**
		 * The notice informing of plugin dependencies not being met.
		 */
		include KRV_DIR . 'public/templates/errors/missing-dependencies-notice.php';
	}
}
