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
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since   2.0.0
	 */
	public static function deactivate() {

		flush_rewrite_rules();

	}

}
