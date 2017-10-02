<?php

/**
 * @wordpress-plugin
 * Plugin Name:       Sliced Invoices
 * Plugin URI:        http://slicedinvoices.com/
 * Description:       Create professional Quotes & Invoices that clients can pay for online.
 * Version:           3.5.4
 * Author:            Sliced Invoices
 * Author URI:        http://slicedinvoices.com/
 * Text Domain:       sliced-invoices
 * Domain Path:       /languages
 */

if ( ! defined('ABSPATH') ) {
	exit;
}

define( 'SLICED_VERSION', '3.5.4' );
define( 'SLICED_DB_VERSION', '4' );
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
	$plugin = new Sliced_Invoices();
	$plugin->run();
 }
add_action( 'plugins_loaded', 'run_sliced_invoices' ); // wait until 'plugins_loaded' hook fires, for WP Multisite compatibility




/* DATABASE UPDATES
============================================================================== */

/**
 * 2017-06-06: update from DB 3 to DB 4, for Sliced Invoices versions < 3.4.0
 * 2016-08-30: update from DB 2 to DB 3, for Sliced Invoices versions < 2.873
 */
function sliced_invoices_db_update() {
	
	global $post, $wpdb;
	
	$sliced_db_check = get_option('sliced_general');
	
	if ( isset( $sliced_db_check['db_version'] ) && $sliced_db_check['db_version'] >= SLICED_DB_VERSION ) {
		// all good
		return;
	}
	
	// upgrade from v3 to 4
	if ( ! isset( $sliced_db_check['db_version'] ) || $sliced_db_check['db_version'] < 4 ) {
		// check semaphore options
		$results = $wpdb->get_results("
			SELECT option_id
			  FROM $wpdb->options
			 WHERE option_name IN ('sliced_locked', 'sliced_unlocked')
		");
		if (!count($results)) {
			update_option('sliced_unlocked', '1');
			update_option('sliced_last_lock_time', current_time('mysql', 1));
			update_option('sliced_semaphore', '0');
		}
	}
	
	// upgrade from < v3
	if ( ! isset( $sliced_db_check['db_version'] ) || $sliced_db_check['db_version'] < 3 ) {
		// quotes:
		$args = array(
			'post_type' => 'sliced_quote',
			'posts_per_page' => -1,
			'meta_query' =>
				array(
					array(
						'key'     => '_sliced_quote_created',
						'compare' => 'NOT EXISTS'
					)
				)
			);
		$query = new WP_Query( $args );
		if ( $query->have_posts() ) { 
			while ( $query->have_posts() ) {
				$query->the_post();
				update_post_meta( $post->ID, '_sliced_quote_created', strtotime( $post->post_date ) > 0 ? strtotime( $post->post_date ) : strtotime( $post->post_date_gmt ) );
			}
		}
		wp_reset_postdata();

		// invoices:
		$args = array(
			'post_type' => 'sliced_invoice',
			'posts_per_page' => -1,
			'meta_query' =>
				array(
					array(
						'key'     => '_sliced_invoice_created',
						'compare' => 'NOT EXISTS'
					)
				)
			);
		$query = new WP_Query( $args );
		if ( $query->have_posts() ) { 
			while ( $query->have_posts() ) {
				$query->the_post();
				update_post_meta( $post->ID, '_sliced_invoice_created', strtotime( $post->post_date ) > 0 ? strtotime( $post->post_date ) : strtotime( $post->post_date_gmt ) );
			}
		}
		wp_reset_postdata();
	}
	
	// Done
	$sliced_db_check['db_version'] = SLICED_DB_VERSION;
	update_option( 'sliced_general', $sliced_db_check );
	
}
add_action( 'init', 'sliced_invoices_db_update' );




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
