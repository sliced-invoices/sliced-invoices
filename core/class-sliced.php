<?php
// Exit if accessed directly
if ( ! defined('ABSPATH') ) { exit; }
/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       http://slicedinvoices.com
 * @since      2.0.0
 *
 * @package    Sliced_Invoices
 */


class Sliced_Invoices {

	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 *
	 * @since   2.0.0
	 * @access   protected
	 * @var      Sliced_Loader    $loader    Maintains and registers all hooks for the plugin.
	 */
	protected $loader;

	/**
	 * The unique identifier of this plugin.
	 *
	 * @since   2.0.0
	 * @access   protected
	 * @var      string    $plugin_name    The string used to uniquely identify this plugin.
	 */
	protected $plugin_name = 'sliced-invoices';

	/**
	 * The current version of the plugin.
	 *
	 * @since   2.0.0
	 * @access   protected
	 * @var      string    $version    The current version of the plugin.
	 */
	protected $version = SLICED_VERSION;

	/**
	 * Define the core functionality of the plugin.
	 *
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies, define the locale, and set the hooks for the admin area and
	 * the public-facing side of the site.
	 *
	 * @since   2.0.0
	 */
	public function __construct() {

		$this->load_dependencies();
		$this->set_locale();
		$this->define_admin_hooks();
		$this->define_public_hooks();

	}


	/**
	 * Load the required dependencies for this plugin.
	 *
	 * Include the following files that make up the plugin:
	 *
	 * - Sliced_Loader. Orchestrates the hooks of the plugin.
	 * - Sliced_i18n. Defines internationalization functionality.
	 * - Sliced_Admin. Defines all hooks for the admin area.
	 * - Sliced_Public. Defines all hooks for the public side of the site.
	 *
	 * Create an instance of the loader which will be used to register the hooks
	 * with WordPress.
	 *
	 * @version 3.9.0
	 * @since   2.0.0
	 * @access   private
	 */
	private function load_dependencies() {

		global $pagenow;


		/**
		 * The class responsible for orchestrating the actions and filters of the
		 * core plugin.
		 */
		require_once SLICED_PATH . 'core/class-sliced-loader.php';
		require_once SLICED_PATH . 'core/class-sliced-i18n.php';
		require_once SLICED_PATH . 'core/class-sliced-semaphore.php';

		/**
		 * The classes responsible for defining all actions that occur in the admin area.
		 */
		require_once SLICED_PATH . 'admin/class-sliced-admin.php';
		require_once SLICED_PATH . 'admin/includes/sliced-admin-options.php';
		if( $pagenow == 'post.php' || $pagenow == 'post-new.php' || $pagenow == 'user-edit.php' || $pagenow == 'user-new.php' ) {
			require_once SLICED_PATH . 'admin/includes/sliced-admin-metaboxes.php';
		}
		require_once SLICED_PATH . 'admin/includes/sliced-admin-notices.php';
		require_once SLICED_PATH . 'admin/includes/sliced-admin-notifications.php';
		require_once SLICED_PATH . 'admin/includes/sliced-admin-columns.php';
		require_once SLICED_PATH . 'admin/includes/sliced-admin-quick-edit.php';

		if( is_admin() ) {
			require_once SLICED_PATH . 'admin/includes/sliced-admin-reports.php';
			require_once SLICED_PATH . 'admin/includes/sliced-admin-help.php';
			require_once SLICED_PATH . 'admin/includes/sliced-admin-tools.php';
		}


		/**
		 * The class responsible for defining all actions that occur in the public-facing
		 * side of the site.
		 */
		require_once SLICED_PATH . 'public/class-sliced-public.php';

		/**
		 * The classes responsible for defining all actions that occur for public and admin
		 * sides of the site.
		 */
		require_once SLICED_PATH . 'includes/class-sliced-shared.php';
		require_once SLICED_PATH . 'includes/vendor/cmb2/init.php';

		require_once SLICED_PATH . 'includes/payments/sliced-shared-payments.php';
		require_once SLICED_PATH . 'includes/gateways/sliced-gateway-paypal.php';

		require_once SLICED_PATH . 'includes/invoice/class-sliced-invoice.php';
		require_once SLICED_PATH . 'includes/quote/class-sliced-quote.php';

		require_once SLICED_PATH . 'includes/csv/csv-importer.php';
		require_once SLICED_PATH . 'includes/csv/csv-exporter.php';

		require_once SLICED_PATH . 'admin/includes/sliced-admin-logs.php';

		/**
		 * Template tags
		 */
		require_once SLICED_PATH . 'includes/template-tags/sliced-tags-business.php';
		require_once SLICED_PATH . 'includes/template-tags/sliced-tags-payments.php';
		require_once SLICED_PATH . 'includes/template-tags/sliced-tags-client.php';
		require_once SLICED_PATH . 'includes/template-tags/sliced-tags-invoice.php';
		require_once SLICED_PATH . 'includes/template-tags/sliced-tags-quote.php';
		require_once SLICED_PATH . 'includes/template-tags/sliced-tags-general.php';
		require_once SLICED_PATH . 'includes/template-tags/sliced-tags-display-modules.php';
		
		// Global functions
		require_once( SLICED_PATH . 'includes/functions.php' );

		$this->loader = new Sliced_Loader();

	}

	/**
	 * Define the locale for this plugin for internationalization.
	 *
	 * Uses the Sliced_i18n class in order to set the domain and to register the hook
	 * with WordPress.
	 *
	 * @since   2.0.0
	 */
	private function set_locale() {

		$plugin_i18n = new Sliced_i18n();
		$plugin_i18n->set_domain( $this->get_plugin_name() );
		$plugin_i18n->load_plugin_textdomain();

	}


	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 *
	 * @version 3.9.0
	 * @since   2.0.0
	 */
	private function define_admin_hooks() {

		$plugin_admin = new Sliced_Admin( $this->get_plugin_name(), $this->get_version() );

		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts' );
		
		$this->loader->add_filter( 'cmb2_script_dependencies', $plugin_admin, 'cmb2_enqueue_datepicker' );

		$this->loader->add_action( 'init', $plugin_admin, 'new_cpt_quote', 1 );
		$this->loader->add_action( 'init', $plugin_admin, 'new_cpt_invoice', 1 );
		$this->loader->add_action( 'init', $plugin_admin, 'new_taxonomy_quote_status', 1 );
		$this->loader->add_action( 'init', $plugin_admin, 'new_taxonomy_invoice_status', 1 );
		$this->loader->add_action( 'init', $plugin_admin, 'new_taxonomy_terms', 1 );
		$this->loader->add_action( 'init', $plugin_admin, 'legacy_compatibility' );
		$this->loader->add_action( 'init', $plugin_admin, 'settings_check' );

		$this->loader->add_filter( 'admin_body_class', $plugin_admin, 'add_admin_body_class', 11 );
		$this->loader->add_filter( 'add_meta_boxes', $plugin_admin, 'remove_some_junk', 11 );

		$this->loader->add_filter( 'plugin_action_links_' . trailingslashit( $this->get_plugin_name() ) . $this->get_plugin_name() . '.php', $plugin_admin, 'plugin_action_links' );
		
		$this->loader->add_action( 'wp_ajax_sliced-search-clients', $plugin_admin, 'ajax_search_clients' );
		$this->loader->add_action( 'wp_ajax_sliced-search-non-clients', $plugin_admin, 'ajax_search_non_clients' );
		$this->loader->add_action( 'wp_ajax_sliced-create-user', $plugin_admin, 'create_user' );
		$this->loader->add_action( 'wp_ajax_sliced-update-user', $plugin_admin, 'update_user' );
		$this->loader->add_action( 'wp_ajax_sliced-get-client', $plugin_admin, 'get_client' );
		$this->loader->add_action( 'wp_ajax_sliced-update-client', $plugin_admin, 'update_client' );
		$this->loader->add_action( 'admin_footer-post-new.php', $plugin_admin, 'client_registration_form' );
		$this->loader->add_action( 'admin_footer-post.php', $plugin_admin, 'client_registration_form' );
		$this->loader->add_action( 'admin_footer_text', $plugin_admin, 'admin_footer_text' );

		$this->loader->add_action( 'admin_action_duplicate_quote_invoice', $plugin_admin, 'duplicate_quote_invoice' );
		$this->loader->add_filter( 'post_row_actions', $plugin_admin, 'duplicate_quote_invoice_link', 10, 2 );
		$this->loader->add_filter( 'page_row_actions', $plugin_admin, 'duplicate_quote_invoice_link', 10, 2 );

		$this->loader->add_filter( 'post_updated_messages', $plugin_admin, 'invoice_quote_updated_messages' );
		$this->loader->add_action( 'admin_notices', $plugin_admin, 'custom_admin_notices' );
		$this->loader->add_filter( 'enter_title_here', $plugin_admin, 'custom_enter_title' );

		$this->loader->add_action( 'load-edit.php', $plugin_admin, 'export_csv' );
		$this->loader->add_action( 'admin_init', $plugin_admin, 'export_csv_full' );
		$this->loader->add_filter( 'admin_action_convert_quote_to_invoice', $plugin_admin, 'convert_quote_to_invoice' );
		$this->loader->add_filter( 'admin_action_create_invoice_from_quote', $plugin_admin, 'create_invoice_from_quote' );
		$this->loader->add_action( 'save_post', $plugin_admin, 'set_published_date_as_created' );
		$this->loader->add_action( 'save_post', $plugin_admin, 'set_number_for_search' );
		$this->loader->add_action( 'save_post', $plugin_admin, 'maybe_mark_as_paid' );

		//$this->loader->add_filter( 'load-edit.php', $plugin_admin, 'mark_quote_expired' );
		//$this->loader->add_filter( 'load-edit.php', $plugin_admin, 'mark_invoice_overdue' );
		
		$this->loader->add_action( 'sliced_invoices_hourly_tasks', $plugin_admin, 'sliced_invoices_hourly_tasks' );

	}

	/**
	 * Register all of the hooks related to the public-facing functionality
	 * of the plugin.
	 *
	 * @since   2.0.0
	 * @access   private
	 */
	private function define_public_hooks() {
		
		$plugin_public = Sliced_Public::get_instance();

		$this->loader->add_action( 'sliced_head', $plugin_public, 'output_styles' );

		$this->loader->add_action( 'sliced_invoice_head', $plugin_public, 'output_invoice_scripts' );
		$this->loader->add_action( 'sliced_invoice_head', $plugin_public, 'output_invoice_styles' );

		$this->loader->add_action( 'sliced_quote_head', $plugin_public, 'output_quote_scripts' );
		$this->loader->add_action( 'sliced_quote_head', $plugin_public, 'output_quote_styles' );

		$this->loader->add_action( 'sliced_quote_footer', $plugin_public, 'display_quote_comments' );

		$this->loader->add_action( 'script_loader_tag', $plugin_public, 'add_defer_attribute' );

		$this->loader->add_filter( 'single_template', $plugin_public, 'invoice_quote_template', 999 );
		$this->loader->add_filter( 'page_template', $plugin_public, 'payment_templates', 999 );

		$this->loader->add_filter( 'private_title_format', $plugin_public, 'title_format');
		$this->loader->add_filter( 'protected_title_format', $plugin_public, 'title_format');

		$this->loader->add_action( 'sliced_invoice_after_body', $plugin_public, 'display_invoice_top_bar');
		$this->loader->add_action( 'sliced_quote_after_body', $plugin_public, 'display_quote_top_bar');
		
	}


	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 *
	 * @since   2.0.0
	 */
	public function run() {
		$this->loader->run();
	}

	/**
	 * The name of the plugin used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality.
	 *
	 * @since     2.0.0
	 * @return    string    The name of the plugin.
	 */
	public function get_plugin_name() {
		return $this->plugin_name;
	}

	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 *
	 * @since     2.0.0
	 * @return    Sliced_Loader    Orchestrates the hooks of the plugin.
	 */
	public function get_loader() {
		return $this->loader;
	}

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @since     2.0.0
	 * @return    string    The version number of the plugin.
	 */
	public function get_version() {
		return $this->version;
	}

}
