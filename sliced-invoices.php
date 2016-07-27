<?php

/**
 * @wordpress-plugin
 * Plugin Name:       Sliced Invoices
 * Plugin URI:        http://slicedinvoices.com/
 * Description:       Create professional Quotes & Invoices that clients can pay for online.
 * Version:           2.85
 * Author:            Sliced Invoices
 * Author URI:        http://slicedinvoices.com/
 * Text Domain:       sliced-invoices
 * Domain Path:       /languages
 */

if ( ! defined('ABSPATH') ) {
	exit;
}

define( 'SLICED_VERSION', '2.85' );
define( 'SLICED_PATH', plugin_dir_path( __FILE__ ) );

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-sliced-activator.php
 */
function activate_sliced_invoices() {
	require_once SLICED_PATH . 'core/class-sliced-activator.php';
	Sliced_Activator::activate();
}


/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-sliced-deactivator.php
 */
function deactivate_sliced_invoices() {
	require_once SLICED_PATH . 'core/class-sliced-deactivator.php';
	Sliced_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_sliced_invoices' );
register_deactivation_hook( __FILE__, 'deactivate_sliced_invoices' );



/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require SLICED_PATH . 'core/class-sliced.php';


/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since   2.0.0
 */
function run_sliced_invoices() {
	define( 'SLICED_TIMEZONE', (get_option( 'timezone_string' ) ? get_option( 'timezone_string' ) : date_default_timezone_get() ) );
	date_default_timezone_set( SLICED_TIMEZONE );
	$plugin = new Sliced_Invoices();
	$plugin->run();
 }
run_sliced_invoices();


/* FILTERS AND ACTIONS TO AVOID PLUGIN AND THEME CONFLICTS
============================================================================== */

/*
 * Ignore autoptimize plugin
 */
function sliced_filter_for_autoptimize() {
	return (bool) sliced_get_the_type();
}
add_filter('autoptimize_filter_noptimize','sliced_filter_for_autoptimize',10,0);


/*
 * Helper function for quick debugging
 */
if (!function_exists('pp')) {
	function pp( $array ) {
		echo '<pre style="white-space:pre-wrap;">';
			print_r( $array );
		echo '</pre>';
	}
}


/*
 * For quick debugging
 */
// add_action( 'shutdown', function(){
//     pp( $GLOBALS['wp_actions'] );
//     die;
// });
