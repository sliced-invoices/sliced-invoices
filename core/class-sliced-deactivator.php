<?php
// Exit if accessed directly
if ( ! defined('ABSPATH') ) { exit; }
/**
 * Fired during plugin deactivation
 *
 * @link       http://slicedinvoices.com
 * @since      2.0.0
 *
 * @package    Sliced_Invoices
 */

class Sliced_Deactivator {

	/**
	 * Deactivation tasks.
	 *
	 * Things to do when the Sliced Invoices plugin is deactivated.
	 *
	 * @version 3.?.?
	 * @since   2.0.0
	 */
	public static function deactivate() {
		
		global $wpdb;
	
		// clear recurring tasks
		wp_clear_scheduled_hook( 'sliced_invoices_hourly_tasks' );
		
		// flush rewrite rules
		flush_rewrite_rules();
		
		// clear admin notices
		delete_option( 'sliced_admin_notices' );
		$wpdb->get_results( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'sliced_admin_notice_%'" );
		
	}

}
