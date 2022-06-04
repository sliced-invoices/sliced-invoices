<?php
/**
 * @wordpress-plugin
 * Plugin Name:       Sliced Invoices
 * Plugin URI:        http://slicedinvoices.com/
 * Description:       Create professional Quotes & Invoices that clients can pay for online.
 * Version:           3.9.1
 * Author:            Sliced Invoices
 * Author URI:        http://slicedinvoices.com/
 * Text Domain:       sliced-invoices
 * Domain Path:       /languages
 * Copyright:         © 2022 Sliced Software, LLC. All rights reserved.
 * License:           GPLv2
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SLICED_VERSION', '3.9.1' );
define( 'SLICED_DB_VERSION', '9' );
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
	
	// 2019-06-15 SLICED_TIMEZONE may be removed in the near future
	// it is currently used by the following extensions: Subscription Invoices
	// use Sliced_Shared::get_local_timezone() instead
	define( 'SLICED_TIMEZONE', (get_option( 'timezone_string' ) ? get_option( 'timezone_string' ) : date_default_timezone_get() ) );
	
	$plugin = new Sliced_Invoices();
	$plugin->run();
}
add_action( 'plugins_loaded', 'run_sliced_invoices' ); // wait until 'plugins_loaded' hook fires, for WP Multisite compatibility




/* ==============================================================================
 * DATABASE UPDATES
 * ==============================================================================
 *
 * History:
 * 2022-05-25 -- DB 9, for Sliced Invoices versions < 3.9.0
 * 2019-06-15 -- DB 8, for Sliced Invoices versions < 3.8.0
 * 2018-03-06 -- DB 7, for Sliced Invoices versions < 3.7.0
 * 2017-11-03 -- DB 6, for Sliced Invoices versions < 3.6.1
 * 2017-10-16 -- DB 5, for Sliced Invoices versions < 3.6.0
 * 2017-06-06 -- DB 4, for Sliced Invoices versions < 3.4.0
 * 2016-08-30 -- DB 3, for Sliced Invoices versions < 2.873
 */
function sliced_invoices_db_update() {
	
	global $post, $wpdb;
	
	$sliced_db_check = get_option('sliced_general');
	
	if ( isset( $sliced_db_check['db_version'] ) && $sliced_db_check['db_version'] >= SLICED_DB_VERSION ) {
		// all good
		return;
	}
	
	// upgrade from v8 to 9
	if ( ! isset( $sliced_db_check['db_version'] ) || $sliced_db_check['db_version'] < 9 ) {
		$payment_settings = get_option( 'sliced_payments' );
		$payment_settings['paypal_enabled'] = 'on';
		update_option( 'sliced_payments', $payment_settings );
		$quotes_settings = get_option( 'sliced_quotes' );
		$quotes_settings['decline_reason_required'] = 'on';
		update_option( 'sliced_quotes', $quotes_settings );
	}
	
	// upgrade from v7 to 8
	if ( ! isset( $sliced_db_check['db_version'] ) || $sliced_db_check['db_version'] < 8 ) {
		
		// quote created:
		$args = array(
			'post_type' => 'sliced_quote',
			'posts_per_page' => -1,
			'meta_query' =>
				array(
					array(
						'key'     => '_sliced_quote_created',
						'compare' => 'EXISTS'
					)
				)
			);
		$query = new WP_Query( $args );
		if ( $query->have_posts() ) { 
			while ( $query->have_posts() ) {
				$query->the_post();
				$created = get_post_meta( $post->ID, '_sliced_quote_created', true );
				$date = intval( $created );
				if ( $date > 0 ) {
					$Y = date( 'Y', $date );
					$m = date( 'm', $date );
					$d = date( 'd', $date );
					$H = '00';
					$i = '00';
					$s = '00';
					$out = Sliced_Shared::get_timestamp_from_local_time( $Y, $m, $d, $H, $i, $s );
					update_post_meta( $post->ID, '_sliced_quote_created', $out );
				}
			}
		}
		wp_reset_postdata();
		
		// quote valid until:
		$args = array(
			'post_type' => 'sliced_quote',
			'posts_per_page' => -1,
			'meta_query' =>
				array(
					array(
						'key'     => '_sliced_quote_valid_until',
						'compare' => 'EXISTS'
					)
				)
			);
		$query = new WP_Query( $args );
		if ( $query->have_posts() ) { 
			while ( $query->have_posts() ) {
				$query->the_post();
				$valid = get_post_meta( $post->ID, '_sliced_quote_valid_until', true );
				$date = intval( $valid );
				if ( $date > 0 ) {
					$Y = date( 'Y', $date );
					$m = date( 'm', $date );
					$d = date( 'd', $date );
					$H = '23';
					$i = '59';
					$s = '59';
					$out = Sliced_Shared::get_timestamp_from_local_time( $Y, $m, $d, $H, $i, $s );
					update_post_meta( $post->ID, '_sliced_quote_valid_until', $out );
				}
			}
		}
		wp_reset_postdata();
		
		// invoice created:
		$args = array(
			'post_type' => 'sliced_invoice',
			'posts_per_page' => -1,
			'meta_query' =>
				array(
					array(
						'key'     => '_sliced_invoice_created',
						'compare' => 'EXISTS'
					)
				)
			);
		$query = new WP_Query( $args );
		if ( $query->have_posts() ) { 
			while ( $query->have_posts() ) {
				$query->the_post();
				$created = get_post_meta( $post->ID, '_sliced_invoice_created', true );
				$date = intval( $created );
				if ( $date > 0 ) {
					$Y = date( 'Y', $date );
					$m = date( 'm', $date );
					$d = date( 'd', $date );
					$H = '00';
					$i = '00';
					$s = '00';
					$out = Sliced_Shared::get_timestamp_from_local_time( $Y, $m, $d, $H, $i, $s );
					update_post_meta( $post->ID, '_sliced_invoice_created', $out );
				}
			}
		}
		wp_reset_postdata();
		
		// invoice due:
		$args = array(
			'post_type' => 'sliced_invoice',
			'posts_per_page' => -1,
			'meta_query' =>
				array(
					array(
						'key'     => '_sliced_invoice_due',
						'compare' => 'EXISTS'
					)
				)
			);
		$query = new WP_Query( $args );
		if ( $query->have_posts() ) { 
			while ( $query->have_posts() ) {
				$query->the_post();
				$due = get_post_meta( $post->ID, '_sliced_invoice_due', true );
				$date = intval( $due );
				if ( $date > 0 ) {
					$Y = date( 'Y', $date );
					$m = date( 'm', $date );
					$d = date( 'd', $date );
					$H = '23';
					$i = '59';
					$s = '59';
					$out = Sliced_Shared::get_timestamp_from_local_time( $Y, $m, $d, $H, $i, $s );
					update_post_meta( $post->ID, '_sliced_invoice_due', $out );
				}
			}
		}
		wp_reset_postdata();
	}
	
	// upgrade from v6 to 7
	if ( ! isset( $sliced_db_check['db_version'] ) || $sliced_db_check['db_version'] < 7 ) {
		
		// options re-shuffle:
		$general  = get_option( 'sliced_general' );
		$invoices = get_option( 'sliced_invoices' );
		$quotes   = get_option( 'sliced_quotes' );
		$payments = get_option( 'sliced_payments' );
		if ( ! isset( $invoices['footer'] ) && isset( $general['footer'] ) ) {
			$invoices['footer'] = $general['footer'];
			update_option( 'sliced_invoices', $invoices );
		}
		if ( ! isset( $quotes['footer'] ) && isset( $general['footer'] ) ) {
			$quotes['footer'] = $general['footer'];
			update_option( 'sliced_quotes', $quotes );
		}
		if ( ! isset( $payments['footer'] ) && isset( $general['footer'] ) ) {
			$payments['footer'] = $general['footer'];
			update_option( 'sliced_payments', $payments );
		}
		
		// tax:
		$payments = get_option('sliced_payments');
		$tax = get_option('sliced_tax');
		if ( ! $tax ) {
			$tax = array();
		}
		$tax['tax_calc_method'] = 'exclusive';
		$tax['tax']             = isset( $payments['tax'] ) ? $payments['tax'] : '10';
		$tax['tax_name']        = isset( $payments['tax_name'] ) ? $payments['tax_name'] : 'Tax';
		update_option( 'sliced_tax', $tax );
		
		// quotes:
		$args = array(
			'post_type' => 'sliced_quote',
			'posts_per_page' => -1,
			'meta_query' =>
				array(
					array(
						'key'     => '_sliced_number',
						'compare' => 'NOT EXISTS'
					)
				)
			);
		$query = new WP_Query( $args );
		if ( $query->have_posts() ) { 
			while ( $query->have_posts() ) {
				$query->the_post();
				$prefix = get_post_meta( $post->ID, '_sliced_quote_prefix', true );
				$number = get_post_meta( $post->ID, '_sliced_quote_number', true );
				$suffix = get_post_meta( $post->ID, '_sliced_quote_suffix', true );
				$number_for_search = $prefix . $number . $suffix;
				update_post_meta( $post->ID, '_sliced_number', $number_for_search );
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
						'key'     => '_sliced_number',
						'compare' => 'NOT EXISTS'
					)
				)
			);
		$query = new WP_Query( $args );
		if ( $query->have_posts() ) { 
			while ( $query->have_posts() ) {
				$query->the_post();
				$prefix = get_post_meta( $post->ID, '_sliced_invoice_prefix', true );
				$number = get_post_meta( $post->ID, '_sliced_invoice_number', true );
				$suffix = get_post_meta( $post->ID, '_sliced_invoice_suffix', true );
				$number_for_search = $prefix . $number . $suffix;
				update_post_meta( $post->ID, '_sliced_number', $number_for_search );
			}
		}
		wp_reset_postdata();
	}
	
	// upgrade from v5 to 6
	if ( ! isset( $sliced_db_check['db_version'] ) || $sliced_db_check['db_version'] < 6 ) {
		$args = array(
			'post_type' => 'sliced_invoice',
			'posts_per_page' => -1,
		);
		$query = new WP_Query( $args );
		if ( $query->have_posts() ) { 
			while ( $query->have_posts() ) {
				$query->the_post();
				$old_payments = get_post_meta( $post->ID, 'sliced_invoice_paid', true );
				$new_payments = get_post_meta( $post->ID, '_sliced_payment', true );
				if ( $old_payments && ! $new_payments ) {
					if ( update_post_meta( $post->ID, '_sliced_payment', array( array(
						'amount'     => $old_payments,
						'status'     => 'success',
					) ) ) ) {
						delete_post_meta( $post->ID, 'sliced_invoice_paid' );
					}
				}
			}
		}
		wp_reset_postdata();
	}
	
	// upgrade from v4 to 5
	if ( ! isset( $sliced_db_check['db_version'] ) || $sliced_db_check['db_version'] < 5 ) {
		$args = array(
			'post_type' => array( 'sliced_quote', 'sliced_invoice' ),
			'posts_per_page' => -1,
		);
		$query = new WP_Query( $args );
		if ( $query->have_posts() ) { 
			while ( $query->have_posts() ) {
				$query->the_post();
				$line_items = get_post_meta( $post->ID, '_sliced_items', true );
				if ( ! is_array( $line_items ) ) {
					continue;
				}
				foreach ( $line_items as &$line_item ) {
					$line_item['taxable'] = 'on';
					$line_item['second_taxable'] = 'on';
				}
				update_post_meta( $post->ID, '_sliced_items', $line_items );
			}
		}
		wp_reset_postdata();
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




/* ==============================================================================
 * FILTERS AND ACTIONS TO AVOID PLUGIN AND THEME CONFLICTS
 * ============================================================================== */

// Ignore autoptimize plugin
function sliced_filter_for_autoptimize() {
	return (bool) sliced_get_the_type();
}
add_filter('autoptimize_filter_noptimize','sliced_filter_for_autoptimize',10,0);

// Kill DAPP, if it's in use. (all of DAPP's features are now built-in as of v3.6.0)
function sliced_no_dapp() {
	global $wp_filter;
	$tag = 'init';
	$class_name = 'Sliced_Discounts_And_Partial_Payment';
	$method_name = 'init';
	$priority = 10;
	if ( ! isset( $wp_filter[ $tag ] ) ) {
		return FALSE;
	}
	if ( is_object( $wp_filter[ $tag ] ) && isset( $wp_filter[ $tag ]->callbacks ) ) {
		$fob       = $wp_filter[ $tag ];
		$callbacks = &$wp_filter[ $tag ]->callbacks;
	} else {
		$callbacks = &$wp_filter[ $tag ];
	}
	if ( ! isset( $callbacks[ $priority ] ) || empty( $callbacks[ $priority ] ) ) {
		return FALSE;
	}
	foreach ( (array) $callbacks[ $priority ] as $filter_id => $filter ) {
		if ( ! isset( $filter['function'] ) || ! is_array( $filter['function'] ) ) {
			continue;
		}
		if ( ! is_object( $filter['function'][0] ) ) {
			continue;
		}
		if ( $filter['function'][1] !== $method_name ) {
			continue;
		}
		if ( get_class( $filter['function'][0] ) === $class_name ) {
			if ( isset( $fob ) ) {
				$fob->remove_filter( $tag, $filter['function'], $priority );
			} else {
				unset( $callbacks[ $priority ][ $filter_id ] );
				if ( empty( $callbacks[ $priority ] ) ) {
					unset( $callbacks[ $priority ] );
				}
				if ( empty( $callbacks ) ) {
					$callbacks = array();
				}
				unset( $GLOBALS['merged_filters'][ $tag ] );
			}
			return TRUE;
		}
	}
	return FALSE;
}
if ( class_exists( 'Sliced_Discounts_And_Partial_Payment' ) ) {
	add_action( 'init', 'sliced_no_dapp', 9 );
}

// Patch for Sage-based themes
function sliced_patch_for_sage_based_themes() {
	// The following is our own solution to the problem of Sage-based themes
	// which force their own "wrapper", injecting code into our templates where
	// it is not wanted, breaking them.
	// i.e.: https://discourse.roots.io/t/single-template-filter-from-plugins/6637
	global $wp_filter;
	$tag = 'template_include';
	$priority = 99;
	if ( ! isset( $wp_filter[ $tag ] ) ) {
		return FALSE;
	}
	if ( is_object( $wp_filter[ $tag ] ) && isset( $wp_filter[ $tag ]->callbacks ) ) {
		$fob       = $wp_filter[ $tag ];
		$callbacks = &$wp_filter[ $tag ]->callbacks;
	} else {
		$callbacks = &$wp_filter[ $tag ];
	}
	if ( ! isset( $callbacks[ $priority ] ) || empty( $callbacks[ $priority ] ) ) {
		return FALSE;
	}
	foreach ( (array) $callbacks[ $priority ] as $filter_id => $filter ) {
		if ( ! isset( $filter['function'] ) || ! is_array( $filter['function'] ) ) {
			continue;
		}
		if ( $filter['function'][1] !== 'wrap' ) {
			continue;
		}
		if ( isset( $fob ) ) {
			$fob->remove_filter( $tag, $filter['function'], $priority );
		} else {
			unset( $callbacks[ $priority ][ $filter_id ] );
			if ( empty( $callbacks[ $priority ] ) ) {
				unset( $callbacks[ $priority ] );
			}
			if ( empty( $callbacks ) ) {
				$callbacks = array();
			}
			unset( $GLOBALS['merged_filters'][ $tag ] );
		}
		return TRUE;
	}
	return FALSE;
}
add_action( 'get_template_part_sliced-invoice-display', 'sliced_patch_for_sage_based_themes' );
add_action( 'get_template_part_sliced-quote-display', 'sliced_patch_for_sage_based_themes' );
add_action( 'get_template_part_sliced-payment-display', 'sliced_patch_for_sage_based_themes' );




/* That's all folks. Happy invoicing! */
