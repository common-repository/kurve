<?php
/**
 * Plugin specific constants.
 *
 * @package Kurve
 */

define( 'KRV_URL', plugin_dir_url( KRV_FILE ) );
define( 'KRV_DIR', plugin_dir_path( KRV_FILE ) );
define( 'KRV_VENDOR_DIR', KRV_DIR . 'vendor/' );
define( 'KRV_VENDOR_URL', KRV_URL . 'vendor/' );
define( 'KRV_FMWK_DIR', KRV_DIR . 'framework/' );
define( 'KRV_FMWK_URL', KRV_URL . 'framework/' );
define( 'KRV_PUBLIC_URL', KRV_URL . 'public/' );

define( 'KRV_SLUG', 'krv' );
define( 'KRV_FULL_NAME', 'Kurve' );
define( 'KRV_API_VERSION', 1 );
define( 'KRV_API_NS', KRV_SLUG . '/v' . KRV_API_VERSION . '/' );
define( 'KRV_INT_API_NS', KRV_API_NS . 'internal' );
define( 'KRV_VIEW_EXT', '.view.php' );
