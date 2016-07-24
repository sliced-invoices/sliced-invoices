<?php
// Exit if accessed directly
if ( ! defined('ABSPATH') ) { exit; }
/**
 * Define the internationalization functionality
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @link       http://slicedinvoices.com
 * @since      2.0.0
 *
 * @package    Sliced_Invoices
 */

class Sliced_i18n {

	/**
	 * The domain specified for this plugin.
	 *
	 * @since   2.0.0
	 * @access   private
	 * @var      string    $domain    The domain identifier for this plugin.
	 */
	private $domain;

	/**
	 * Load the plugin text domain for translation.
	 *
	 * @since   2.0.0
	 */
	public function load_plugin_textdomain() {

		load_plugin_textdomain(
			$this->domain,
			false,
			dirname( dirname( plugin_basename( __FILE__ ) ) ) . '/languages/'
		);

		do_action( 'sliced_loaded' );

	}

	/**
	 * Set the domain equal to that of the specified domain.
	 *
	 * @since   2.0.0
	 * @param    string    $domain    The domain that represents the locale of this plugin.
	 */
	public function set_domain( $domain ) {
		$this->domain = $domain;
	}

}
