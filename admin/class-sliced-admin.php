<?php
// Exit if accessed directly
if ( ! defined('ABSPATH') ) { exit; }

/**
 * The admin-specific functionality of the plugin.
 *
 * @package    Sliced_Invoices
 * @subpackage Sliced_Invoices/admin
 */
class Sliced_Admin {

	/**
	 * The ID of this plugin.
	 *
	 * @since   2.0.0
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since   2.0.0
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since   2.0.0
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;

		// stop Simple Lightbox Gallery from loading
		if( sliced_get_the_type() ) {
			remove_action( 'media_buttons_context', 'add_slgf_custom_button' );
			remove_action( 'admin_footer', 'add_slgf_inline_popup_content' );
		}
		
		// Sliced Recurring Tasks
		if ( ! wp_next_scheduled ( 'sliced_invoices_hourly_tasks' ) ) {
			wp_schedule_event( time(), 'hourly', 'sliced_invoices_hourly_tasks' );
		}

	}


	/**
	 * Ajax handler to search existing clients
	 *
	 * @version 3.9.0
	 * @since   3.8.0
	 */
	public function ajax_search_clients() {

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		
		if ( $_GET['action'] !== 'sliced-search-clients' ) {
			return;
		}
		
		// verify the nonce
		$nonce = isset( $_REQUEST['nonce'] ) ? $_REQUEST['nonce'] : false;
		if ( ! wp_verify_nonce( $nonce, 'sliced_ajax_nonce' ) ) {
			echo json_encode( array() );
			exit;
		}
		
		$search_term = sanitize_text_field( $_GET['term'] );
		
		// search 2 ways, then combine
		$users1 = new WP_User_Query( array(
			'search'         => "*{$search_term}*",
			'search_columns' => array(
				'user_login',
				'user_nicename',
				'user_email',
				'user_url',
				'display_name',
			),
		) );

		$users2 = get_users( array(
			'meta_query' => array(
				array( 'relation' => 'OR' ),
				array(
					'key' => '_sliced_client_business',
					'value' => $search_term,
					'compare' => 'LIKE',
				),
			),
		) );

		$user_ids = array();
		foreach ( $users1->results as $user ) {
			$user_ids[] = $user->ID;
		}
		foreach ( $users2 as $user ) {
			$user_ids[] = $user->ID;
		}
		$user_ids = array_unique( $user_ids );
		
		/*
		$current_user = wp_get_current_user();
		if ( ( $key = array_search( $current_user->ID, $user_ids ) ) !== false ) {
			unset( $user_ids[$key] );
		}
		*/
		
		if ( empty( $user_ids ) ) {
			echo json_encode( array() );
			exit;
		}
		
		$args = array(
			'orderby'   => 'meta_value',
			'order'     => 'ASC',
			'include'   => $user_ids,
			'meta_key'  => '_sliced_client_business',
			'compare'   => 'EXISTS',
		);

		$user_query = new WP_User_Query( $args );

		$output = array();
		if ( ! empty( $user_query->results ) ) {
			foreach ( $user_query->results as $user ) {
				$name = get_user_meta( $user->ID, '_sliced_client_business', true );
				$name .= $user->user_email ? ' (' . $user->user_email . ')' : '';
				if ( ! $name ) {
					$name = __( 'User ID:', 'sliced-invoices' ) . ' ' . $user->ID;
				}
				$output[$user->ID] = apply_filters( 'sliced_client_query_display_name', $name, $user );
			}
		}
		
		echo json_encode( $output );
		exit;
	}

	
	/**
	 * Ajax handler to search existing users who are not clients
	 *
	 * @version 3.9.0
	 * @since   3.8.0
	 */
	public function ajax_search_non_clients() {

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( $_GET['action'] !== 'sliced-search-non-clients' ) {
			return;
		}
		
		// verify the nonce
		$nonce = isset( $_REQUEST['nonce'] ) ? $_REQUEST['nonce'] : false;
		if ( ! wp_verify_nonce( $nonce, 'sliced_ajax_nonce' ) ) {
			echo json_encode( array() );
			exit;
		}
		
		$search_term = sanitize_text_field( $_GET['term'] );

		// find users that are not registered as clients
		$users1 = new WP_User_Query( array(
			'meta_query' => array(
				'relation' => 'OR',
				array(
					'key'  => '_sliced_client_business',
					'compare'   => 'NOT EXISTS',
				),
				array(
					'key'  => '_sliced_client_business',
					'value' => '',
					'compare'   => '<=',
				),
			),
		) );
		
		$user_ids = array();
		foreach ( $users1->results as $user ) {
			$user_ids[] = $user->ID;
		}
		$user_ids = array_unique( $user_ids );
		
		if ( empty( $user_ids ) ) {
			echo json_encode( array() );
			exit;
		}
		
		// now search for our search term, including only the non-client user_ids from above
		$users2 = new WP_User_Query( array(
			'search'         => "*{$search_term}*",
			'search_columns' => array(
				'user_login',
				'user_nicename',
				'user_email',
				'user_url',
				'display_name',
			),
			'include' => $user_ids,
		) );
		
		$users = array();
		foreach ( $users2->results as $user ) {
			$users[ $user->ID ] = $user;
		}
		
		/*
		$current_user = wp_get_current_user();
		if ( isset( $users[ $current_user->ID ] ) ) {
			unset( $users[ $current_user->ID ] );
		}
		*/
		
		if ( empty( $users ) ) {
			echo json_encode( array() );
			exit;
		}

		// put it all together
		$output = array();
		foreach ( $users as $user ) {
			$name = $user->user_login;
			$name .= $user->user_email ? ' (' . $user->user_email . ')' : '';
			if ( ! $name ) {
				$name = __( 'User ID:', 'sliced-invoices' ) . ' ' . $user->ID;
			}
			$output[$user->ID] = apply_filters( 'sliced_client_query_display_name', $name, $user );
		}
		
		echo json_encode( $output );
		exit;
	}
	
	
	/*
	 * Make sure the jQuery UI datepicker is enqueued for CMB2.
	 *
	 * If there are no cmb2 fields of the datepicker type on a page, cmb2 will
	 * not enqueue the datepicker scripts.  Since we are now using cmb2 field
	 * type 'text' for dates and initializing datepickers on our own, we need to
	 * manually add them as cmb2 dependencies.
	 *
	 * @since   3.8.0
	 */
	public function cmb2_enqueue_datepicker( $dependencies ) {
		$dependencies['jquery-ui-core'] = 'jquery-ui-core';
		$dependencies['jquery-ui-datepicker'] = 'jquery-ui-datepicker';
		$dependencies['jquery-ui-datetimepicker'] = 'jquery-ui-datetimepicker';
		return $dependencies;
	}
	
	
	/*
	 * Matches each symbol of PHP date format standard
	 * with jQuery equivalent codeword
	 * @author Tristan Jahier
	 *
	 * @since   3.8.0
	 */
	private function dateformat_PHP_to_jQueryUI( $php_format ) {
		$SYMBOLS_MATCHING = array(
			// Day
			'd' => 'dd',
			'D' => 'D',
			'j' => 'd',
			'l' => 'DD',
			'N' => '',
			'S' => '',
			'w' => '',
			'z' => 'o',
			// Week
			'W' => '',
			// Month
			'F' => 'MM',
			'm' => 'mm',
			'M' => 'M',
			'n' => 'm',
			't' => '',
			// Year
			'L' => '',
			'o' => '',
			'Y' => 'yy',
			'y' => 'y',
			// Time
			'a' => '',
			'A' => '',
			'B' => '',
			'g' => '',
			'G' => '',
			'h' => '',
			'H' => '',
			'i' => '',
			's' => '',
			'u' => ''
		);
		$jqueryui_format = "";
		$escaping = false;
		for($i = 0; $i < strlen($php_format); $i++)
		{
			$char = $php_format[$i];
			if($char === '\\') // PHP date format escaping character
			{
				$i++;
				if($escaping) $jqueryui_format .= $php_format[$i];
				else $jqueryui_format .= '\'' . $php_format[$i];
				$escaping = true;
			}
			else
			{
				if($escaping) { $jqueryui_format .= "'"; $escaping = false; }
				if(isset($SYMBOLS_MATCHING[$char]))
					$jqueryui_format .= $SYMBOLS_MATCHING[$char];
				else
					$jqueryui_format .= $char;
			}
		}
		return $jqueryui_format;
	}


	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since   2.0.0
	 */
	public function enqueue_styles() {

		global $pagenow;
		
		// SelectWoo
		if ( ( $pagenow === 'post.php' || $pagenow === 'post-new.php' ) && sliced_get_the_type() ) {
			wp_enqueue_style( 'sliced-select-woo', plugin_dir_url( __FILE__ ) . 'css/selectWoo.min.css', array(), $this->version, 'all' );
		}

		// Thickbox
		if ( ( $pagenow === 'post.php' || $pagenow === 'post-new.php' || $pagenow === 'edit.php' ) && sliced_get_the_type() ) {
			wp_enqueue_style( 'thickbox' );
		}
		
		// the main style
		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/admin.css', array(), $this->version, 'all' );
		
	}


	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @version 3.9.0
	 * @since   2.0.0
	 */
	public function enqueue_scripts() {

		global $pagenow;

		if ( ! Sliced_Shared::is_sliced_invoices_page() ) {
			return;
		}
		
		// main scripts & localisation
		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/admin.js', array( 'jquery' ), $this->version, false );
		wp_enqueue_script( $this->plugin_name . '-decimal', plugin_dir_url( __FILE__ ) . 'js/decimal.min.js', array( 'jquery' ), $this->version, false );
		wp_localize_script( $this->plugin_name, 'sliced_invoices', apply_filters( 'sliced_invoices_localized_script', array(
			'ajaxurl'    => admin_url( 'admin-ajax.php' ),
			'ajax_nonce' => wp_create_nonce( 'sliced_ajax_nonce' ),
		) ) );
		wp_localize_script( $this->plugin_name, 'sliced_payments', apply_filters( 'sliced_payments_localized_script', array(
			'tax'             => sliced_get_tax_amount(),
			'tax_calc_method' => Sliced_Shared::get_tax_calc_method(),
			'currency_symbol' => sliced_get_currency_symbol(),
			'currency_pos'    => sliced_get_currency_position(),
			'thousand_sep'    => sliced_get_thousand_seperator(),
			'decimal_sep'     => sliced_get_decimal_seperator(),
			'decimals'        => sliced_get_decimals(),
			)
		) );
		wp_localize_script( $this->plugin_name, 'sliced_invoices_i18n', array(
			'convert_quote_to_invoice'      => sprintf(
				/* translators: %1s is a placeholder for the localized version of "quote". %2s is a placeholder for the localized version of "invoice". */
				__( 'Are you sure you want to convert this %1s to an %2s? This cannot be undone.', 'sliced-invoices' ),
				sliced_get_quote_label(),
				sliced_get_invoice_label()
			),
			'create_invoice_from_quote'     => sprintf(
				/* translators: %1s is a placeholder for the localized version of "invoice". %2s is a placeholder for the localized version of "quote". */
				__( 'Are you sure you want to create a new %1s from this %2s?', 'sliced-invoices' ),
				sliced_get_invoice_label(),
				sliced_get_quote_label()
			),
			'datepicker_clear'              => __( 'Clear', 'sliced-invoices' ),
			'datepicker_close'              => __( 'Close', 'sliced-invoices' ),
			'datepicker_dateFormat'         => $this->dateformat_PHP_to_jQueryUI( get_option( 'date_format' ) ),
			'datepicker_dayNames'           => explode( ',', esc_html__( 'Sunday, Monday, Tuesday, Wednesday, Thursday, Friday, Saturday', 'sliced-invoices' ) ),
			'datepicker_dayNamesMin'        => explode( ',', esc_html__( 'Su, Mo, Tu, We, Th, Fr, Sa', 'sliced-invoices' ) ),
			'datepicker_dayNamesShort'      => explode( ',', esc_html__( 'Sun, Mon, Tue, Wed, Thu, Fri, Sat', 'sliced-invoices' ) ),
			'datepicker_monthNames'         => explode( ',', esc_html__( 'January, February, March, April, May, June, July, August, September, October, November, December', 'sliced-invoices' ) ),
			'datepicker_monthNamesShort'    => explode( ',', esc_html__( 'Jan, Feb, Mar, Apr, May, Jun, Jul, Aug, Sep, Oct, Nov, Dec', 'sliced-invoices' ) ),
			'datepicker_today'              => __( 'Today', 'sliced-invoices' ),
			'select_create_new_client'      => __( 'Create new client', 'sliced-invoices' ),
			/* translators: %qty% is a placeholder for any number greater than 1 */
			'select_input_too_short'        => __( 'Please enter %qty% or more characters', 'sliced-invoices' ),
			'select_input_too_short_single' => __( 'Please enter 1 or more characters', 'sliced-invoices' ),
			'select_loading_more'           => __( 'Loading more results&hellip;', 'sliced-invoices' ),
			/* translators: %qty% is a placeholder for any number greater than 1 */
			'select_max'                    => __( 'You can only select %qty% items', 'sliced-invoices' ),
			'select_max_single'             => __( 'You can only select 1 item', 'sliced-invoices' ),
			'select_no_matches'             => __( 'No matches found', 'sliced-invoices' ),
			'select_placeholder'            => __( 'Choose client', 'sliced-invoices' ),
			'select_searching'              => __( 'Searching&hellip;', 'sliced-invoices' ),
		) );

		// New client scripts
		if ( ( $pagenow === 'post.php' || $pagenow === 'post-new.php' ) && sliced_get_the_type() ) {
			//wp_enqueue_script( $this->plugin_name . '-new-client', plugin_dir_url( __FILE__ ) . 'js/new-client.js', array( 'jquery' ), $this->version, false );
			//wp_localize_script( $this->plugin_name . '-new-client' , 'sliced_new_client', array( 'sliced_ajax_url' => admin_url( 'admin-ajax.php' ) ) );
			wp_enqueue_script( 'password-strength-meter' );
			wp_enqueue_script( 'user-profile' );
		}
		
		// SelectWoo
		if ( ( $pagenow === 'post.php' || $pagenow === 'post-new.php' ) && sliced_get_the_type() ) {
			wp_enqueue_script( 'sliced-selectWoo', plugin_dir_url( __FILE__ ) . 'js/selectWoo.full.min.js', array( 'jquery' ), $this->version, false );
		}

		// Thickbox
		if ( ( $pagenow === 'post.php' || $pagenow === 'post-new.php' || $pagenow === 'edit.php' ) && sliced_get_the_type() ) {
			wp_enqueue_script( 'thickbox' );
		}

		// Quick edit
		if ( $pagenow === 'edit.php' && sliced_get_the_type() ) {
			//wp_enqueue_script( 'jquery-ui-datepicker', array( 'jquery' ), '', false );
			wp_enqueue_script( $this->plugin_name . 'quick-edit', plugin_dir_url( __FILE__ ) . 'js/quick-edit.js', array( 'jquery' ), $this->version, false );
		}

		// Charts
		if ( $pagenow === 'admin.php' && $_GET['page'] === 'sliced_reports' ) {
			wp_enqueue_script( $this->plugin_name . '-chart', plugin_dir_url( __FILE__ ) . 'js/Chart.min.js', array( 'jquery' ), $this->version, false );
		}

	}


	/**
	 * Add a class to the admin body
	 *
	 * @since 	2.0.0
	 */
	public function add_admin_body_class( $classes ) {

		global $pagenow;
		$add_class = false;
		if( $pagenow == 'admin.php' && isset( $_GET['page'] ) ) {
			$add_class = strpos( $_GET['page'], 'sliced_' );
		}

		if ( sliced_get_the_type() || $add_class !== false ) {
			$classes .= ' sliced ';
		}

		return $classes;
	}


	/**
	 * Register our custom post type 'sliced_quote'.
	 *
	 * @version 3.9.0
	 * @since   2.0.0
	 */
	public static function new_cpt_quote() {
		
		$opts   = array();
		$single = sliced_get_quote_label();
		$plural = sliced_get_quote_label_plural();
		
		$opts['can_export']           = true;
		$opts['capability_type']      = 'post';
		$opts['description']          = '';
		$opts['exclude_from_search']  = true;
		$opts['has_archive']          = false;
		$opts['hierarchical']         = true;
		$opts['map_meta_cap']         = true;
		$opts['menu_icon']            = 'dashicons-sliced';
		// $opts['menu_position']        = 99.3;
		$opts['public']               = true;
		$opts['publicly_querable']    = true;
		$opts['query_var']            = true;
		$opts['register_meta_box_cb'] = '';
		$opts['rewrite']              = false;
		$opts['show_in_admin_bar']    = true;
		$opts['show_in_menu']         = true;
		$opts['show_in_nav_menu']     = true;
		$opts['show_ui']              = true;
		$opts['supports']             = array( 'title', 'comments' );
		$opts['taxonomies']           = array( 'quote_status' );
		
		$opts['capabilities']['delete_others_posts']    = 'delete_others_posts';
		$opts['capabilities']['delete_post']            = 'delete_post';
		$opts['capabilities']['delete_posts']           = 'delete_posts';
		$opts['capabilities']['delete_private_posts']   = 'delete_private_posts';
		$opts['capabilities']['delete_published_posts'] = 'delete_published_posts';
		$opts['capabilities']['edit_others_posts']      = 'edit_others_posts';
		$opts['capabilities']['edit_post']              = 'edit_post';
		$opts['capabilities']['edit_posts']             = 'edit_posts';
		$opts['capabilities']['edit_private_posts']     = 'edit_private_posts';
		$opts['capabilities']['edit_published_posts']   = 'edit_published_posts';
		$opts['capabilities']['publish_posts']          = 'publish_posts';
		$opts['capabilities']['read_post']              = 'read_post';
		$opts['capabilities']['read_private_posts']     = 'read_private_posts';
		
		$opts['labels']['add_new']            = sprintf(
			/* translators: %s is a placeholder for the localized word "Quote" (singular) */
			__( 'Add New %s', 'sliced-invoices' ), $single
		);
		$opts['labels']['add_new_item']       = sprintf(
			/* translators: %s is a placeholder for the localized word "Quote" (singular) */
			__( 'Add New %s', 'sliced-invoices' ), $single
		);
		$opts['labels']['all_items']          = $plural;
		$opts['labels']['edit_item']          = sprintf(
			/* translators: %s is a placeholder for the localized word "Quote" (singular) */
			__( 'Edit %s' , 'sliced-invoices' ), $single
		);
		$opts['labels']['menu_name']          = $plural;
		$opts['labels']['name']               = $plural;
		$opts['labels']['name_admin_bar']     = $single;
		$opts['labels']['new_item']           = sprintf(
			/* translators: %s is a placeholder for the localized word "Quote" (singular) */
			__( 'New %s', 'sliced-invoices' ), $single
		);
		$opts['labels']['not_found']          = sprintf(
			/* translators: %s is a placeholder for the localized word "Quotes" (plural) */
			__( 'No %s Found', 'sliced-invoices' ), $plural
		);
		$opts['labels']['not_found_in_trash'] = sprintf(
			/* translators: %s is a placeholder for the localized word "Quotes" (plural) */
			__( 'No %s Found in Trash', 'sliced-invoices' ), $plural
		);
		$opts['labels']['parent_item_colon']  = sprintf(
			/* translators: %s is a placeholder for the localized word "Quote" (singular) */
			__( 'Parent %s:', 'sliced-invoices' ), $single
		);
		$opts['labels']['search_items']       = sprintf(
			/* translators: %s is a placeholder for the localized word "Quotes" (plural) */
			__( 'Search %s', 'sliced-invoices' ), $plural
		);
		$opts['labels']['singular_name']      = $single;
		$opts['labels']['view_item']          = sprintf(
			/* translators: %s is a placeholder for the localized word "Quote" (singular) */
			__( 'View %s', 'sliced-invoices' ), $single
		);
		
		$opts['rewrite']['slug']       = false;
		$opts['rewrite']['with_front'] = false;
		$opts['rewrite']['feeds']      = false;
		$opts['rewrite']['pages']      = false;
		
		$opts = apply_filters( 'sliced_quote_params', $opts );
		
		register_post_type( 'sliced_quote', $opts );
		
	}
	
	
	/**
	 * Register our custom post type 'sliced_quote'.
	 *
	 * @version 3.9.0
	 * @since   2.0.0
	 */
	public static function new_cpt_invoice() {
		
		$opts   = array();
		$single = sliced_get_invoice_label();
		$plural = sliced_get_invoice_label_plural();
		
		$opts['can_export']           = true;
		$opts['capability_type']      = 'post';
		$opts['description']          = '';
		$opts['exclude_from_search']  = true;
		$opts['has_archive']          = false;
		$opts['hierarchical']         = true;
		$opts['map_meta_cap']         = true;
		$opts['menu_icon']            = 'dashicons-sliced';
		// $opts['menu_position']        = 99.4;
		$opts['public']               = true;
		$opts['publicly_querable']    = true;
		$opts['query_var']            = true;
		$opts['register_meta_box_cb'] = '';
		$opts['rewrite']              = false;
		$opts['show_in_admin_bar']    = true;
		$opts['show_in_menu']         = true;
		$opts['show_in_nav_menu']     = true;
		$opts['show_ui']              = true;
		$opts['supports']             = array( 'title' );
		$opts['taxonomies']           = array( 'invoice_status' );
		
		$opts['capabilities']['delete_others_posts']    = 'delete_others_posts';
		$opts['capabilities']['delete_post']            = 'delete_post';
		$opts['capabilities']['delete_posts']           = 'delete_posts';
		$opts['capabilities']['delete_private_posts']   = 'delete_private_posts';
		$opts['capabilities']['delete_published_posts'] = 'delete_published_posts';
		$opts['capabilities']['edit_others_posts']      = 'edit_others_posts';
		$opts['capabilities']['edit_post']              = 'edit_post';
		$opts['capabilities']['edit_posts']             = 'edit_posts';
		$opts['capabilities']['edit_private_posts']     = 'edit_private_posts';
		$opts['capabilities']['edit_published_posts']   = 'edit_published_posts';
		$opts['capabilities']['publish_posts']          = 'publish_posts';
		$opts['capabilities']['read_post']              = 'read_post';
		$opts['capabilities']['read_private_posts']     = 'read_private_posts';
		
		$opts['labels']['add_new']            = sprintf(
			/* translators: %s is a placeholder for the localized word "Invoice" (singular) */
			__( 'Add New %s', 'sliced-invoices' ), $single
		);
		$opts['labels']['add_new_item']       = sprintf(
			/* translators: %s is a placeholder for the localized word "Invoice" (singular) */
			__( 'Add New %s', 'sliced-invoices' ), $single
		);
		$opts['labels']['all_items']          = $plural;
		$opts['labels']['edit_item']          = sprintf(
			/* translators: %s is a placeholder for the localized word "Invoice" (singular) */
			__( 'Edit %s' , 'sliced-invoices' ), $single
		);
		$opts['labels']['menu_name']          = $plural;
		$opts['labels']['name']               = $plural;
		$opts['labels']['name_admin_bar']     = $single;
		$opts['labels']['new_item']           = sprintf(
			/* translators: %s is a placeholder for the localized word "Invoice" (singular) */
			__( 'New %s', 'sliced-invoices' ), $single
		);
		$opts['labels']['not_found']          = sprintf(
			/* translators: %s is a placeholder for the localized word "Invoices" (plural) */
			__( 'No %s Found', 'sliced-invoices' ), $plural
		);
		$opts['labels']['not_found_in_trash'] = sprintf(
			/* translators: %s is a placeholder for the localized word "Invoices" (plural) */
			__( 'No %s Found in Trash', 'sliced-invoices' ), $plural
		);
		$opts['labels']['parent_item_colon']  = sprintf(
			/* translators: %s is a placeholder for the localized word "Invoice" (singular) */
			__( 'Parent %s:', 'sliced-invoices' ), $single
		);
		$opts['labels']['search_items']       = sprintf(
			/* translators: %s is a placeholder for the localized word "Invoices" (plural) */
			__( 'Search %s', 'sliced-invoices' ), $plural
		);
		$opts['labels']['singular_name']      = $single;
		$opts['labels']['view_item']          = sprintf(
			/* translators: %s is a placeholder for the localized word "Invoice" (singular) */
			__( 'View %s', 'sliced-invoices' ), $single
		);
		
		$opts['rewrite']['slug']       = false;
		$opts['rewrite']['with_front'] = false;
		$opts['rewrite']['feeds']      = false;
		$opts['rewrite']['pages']      = false;
		
		$opts = apply_filters( 'sliced_invoice_params', $opts );
		
		register_post_type( 'sliced_invoice', $opts );
		
	}
	
	
	/**
	 * Register our taxonomy 'quote_status'.
	 *
	 * @version 3.9.0
	 * @since   2.0.0
	 */
	public static function new_taxonomy_quote_status() {
		
		$opts   = array();
		$single = __( 'Status', 'sliced-invoices' );
		$plural = __( 'Statuses', 'sliced-invoices' );
		
		$opts['hierarchical']      = true;
		$opts['public']            = true;
		$opts['query_var']         = 'quote_status';
		$opts['show_admin_column'] = true;
		$opts['show_in_nav_menus'] = false;
		$opts['show_tag_cloud']    = false;
		$opts['show_ui']           = false;
		$opts['sort']              = '';
		
		$opts['capabilities']['assign_terms'] = 'edit_posts';
		$opts['capabilities']['delete_terms'] = 'manage_categories';
		$opts['capabilities']['edit_terms']   = 'manage_categories';
		$opts['capabilities']['manage_terms'] = 'manage_categories';
		
		$opts['labels']['add_new_item']               = sprintf(
			/* translators: %s is a placeholder for the localized word "Status" (singular) */
			__( 'Add New %s', 'sliced-invoices' ), $single
		);
		$opts['labels']['add_or_remove_items']        = sprintf(
			/* translators: %s is a placeholder for the localized word "Statuses" (plural) */
			__( 'Add or remove %s', 'sliced-invoices' ), $plural
		);
		$opts['labels']['all_items']                  = $plural;
		$opts['labels']['choose_from_most_used']      = sprintf(
			/* translators: %s is a placeholder for the localized word "Statuses" (plural) */
			__( 'Choose from most used %s', 'sliced-invoices' ), $plural
		);
		$opts['labels']['edit_item']                  = sprintf(
			/* translators: %s is a placeholder for the localized word "Status" (singular) */
			__( 'Edit %s' , 'sliced-invoices' ), $single
		);
		$opts['labels']['menu_name']                  = $plural;
		$opts['labels']['name']                       = $plural;
		$opts['labels']['new_item_name']              = sprintf(
			/* translators: %s is a placeholder for the localized word "Status" (singular) */
			__( 'New %s Name', 'sliced-invoices' ), $single
		);
		$opts['labels']['not_found']                  = sprintf(
			/* translators: %s is a placeholder for the localized word "Statuses" (plural) */
			__( 'No %s Found', 'sliced-invoices' ), $plural
		);
		$opts['labels']['parent_item']                = sprintf(
			/* translators: %s is a placeholder for the localized word "Status" (singular) */
			__( 'Parent %s', 'sliced-invoices' ), $single
		);
		$opts['labels']['parent_item_colon']          = sprintf(
			/* translators: %s is a placeholder for the localized word "Status" (singular) */
			__( 'Parent %s:', 'sliced-invoices' ), $single
		);
		$opts['labels']['popular_items']              = sprintf(
			/* translators: %s is a placeholder for the localized word "Statuses" (plural) */
			__( 'Popular %s', 'sliced-invoices' ), $plural
		);
		$opts['labels']['search_items']               = sprintf(
			/* translators: %s is a placeholder for the localized word "Statuses" (plural) */
			__( 'Search %s', 'sliced-invoices' ), $plural
		);
		$opts['labels']['separate_items_with_commas'] = sprintf(
			/* translators: %s is a placeholder for the localized word "Statuses" (plural) */
			__( 'Separate %s with commas', 'sliced-invoices' ), $plural
		);
		$opts['labels']['singular_name']              = $single;
		$opts['labels']['update_item']                = sprintf(
			/* translators: %s is a placeholder for the localized word "Status" (singular) */
			__( 'Update %s', 'sliced-invoices' ), $single
		);
		$opts['labels']['view_item']                  = sprintf(
			/* translators: %s is a placeholder for the localized word "Status" (singular) */
			__( 'View %s', 'sliced-invoices' ), $single
		);
		
		$opts['rewrite']['slug'] = 'quote_status';
		
		$opts = apply_filters( 'sliced_quote_status_params', $opts );
		
		register_taxonomy( 'quote_status', 'sliced_quote', $opts );
		
	}
	
	
	/**
	 * Register our taxonomy 'invoice_status'.
	 *
	 * @version 3.9.0
	 * @since   2.0.0
	 */
	public static function new_taxonomy_invoice_status() {
		
		$opts   = array();
		$single = __( 'Status', 'sliced-invoices' );
		$plural = __( 'Statuses', 'sliced-invoices' );
		
		$opts['hierarchical']      = true;
		$opts['public']            = true;
		$opts['query_var']         = 'invoice_status';
		$opts['show_admin_column'] = true;
		$opts['show_in_nav_menus'] = false;
		$opts['show_tag_cloud']    = false;
		$opts['show_ui']           = false;
		$opts['sort']              = '';
		
		$opts['capabilities']['assign_terms'] = 'edit_posts';
		$opts['capabilities']['delete_terms'] = 'manage_categories';
		$opts['capabilities']['edit_terms']   = 'manage_categories';
		$opts['capabilities']['manage_terms'] = 'manage_categories';
		
		$opts['labels']['add_new_item']               = sprintf(
			/* translators: %s is a placeholder for the localized word "Status" (singular) */
			__( 'Add New %s', 'sliced-invoices' ), $single
		);
		$opts['labels']['add_or_remove_items']        = sprintf(
			/* translators: %s is a placeholder for the localized word "Statuses" (plural) */
			__( 'Add or remove %s', 'sliced-invoices' ), $plural
		);
		$opts['labels']['all_items']                  = $plural;
		$opts['labels']['choose_from_most_used']      = sprintf(
			/* translators: %s is a placeholder for the localized word "Statuses" (plural) */
			__( 'Choose from most used %s', 'sliced-invoices' ), $plural
		);
		$opts['labels']['edit_item']                  = sprintf(
			/* translators: %s is a placeholder for the localized word "Status" (singular) */
			__( 'Edit %s' , 'sliced-invoices' ), $single
		);
		$opts['labels']['menu_name']                  = $plural;
		$opts['labels']['name']                       = $plural;
		$opts['labels']['new_item_name']              = sprintf(
			/* translators: %s is a placeholder for the localized word "Status" (singular) */
			__( 'New %s Name', 'sliced-invoices' ), $single
		);
		$opts['labels']['not_found']                  = sprintf(
			/* translators: %s is a placeholder for the localized word "Statuses" (plural) */
			__( 'No %s Found', 'sliced-invoices' ), $plural
		);
		$opts['labels']['parent_item']                = sprintf(
			/* translators: %s is a placeholder for the localized word "Status" (singular) */
			__( 'Parent %s', 'sliced-invoices' ), $single
		);
		$opts['labels']['parent_item_colon']          = sprintf(
			/* translators: %s is a placeholder for the localized word "Status" (singular) */
			__( 'Parent %s:', 'sliced-invoices' ), $single
		);
		$opts['labels']['popular_items']              = sprintf(
			/* translators: %s is a placeholder for the localized word "Statuses" (plural) */
			__( 'Popular %s', 'sliced-invoices' ), $plural
		);
		$opts['labels']['search_items']               = sprintf(
			/* translators: %s is a placeholder for the localized word "Statuses" (plural) */
			__( 'Search %s', 'sliced-invoices' ), $plural
		);
		$opts['labels']['separate_items_with_commas'] = sprintf(
			/* translators: %s is a placeholder for the localized word "Statuses" (plural) */
			__( 'Separate %s with commas', 'sliced-invoices' ), $plural
		);
		$opts['labels']['singular_name']              = $single;
		$opts['labels']['update_item']                = sprintf(
			/* translators: %s is a placeholder for the localized word "Status" (singular) */
			__( 'Update %s', 'sliced-invoices' ), $single
		);
		$opts['labels']['view_item']                  = sprintf(
			/* translators: %s is a placeholder for the localized word "Status" (singular) */
			__( 'View %s', 'sliced-invoices' ), $single
		);
		
		$opts['rewrite']['slug'] = 'invoice_status';
		
		$opts = apply_filters( 'sliced_invoice_status_params', $opts );
		
		register_taxonomy( 'invoice_status', 'sliced_invoice', $opts );
		
	}
	
	
	/**
	 * Insert our taxonomy terms, if they don't exist.
	 *
	 * @version 3.9.0
	 * @since   3.0.0
	 */
	public static function new_taxonomy_terms( $force = false ) {
		
		$bypass = get_transient( 'sliced_taxonomy_terms_check' );
		if ( $bypass && ! $force ) {
			return;
		}
		
		$flush_needed = false;
		
		$quote_status_terms = array(
			// Here we use the '_x' function only so PoEdit will pick up these strings.
			// Our terms should always be inserted into WP by their original English names
			// (i.e. by the keys, not the values, below), since this is how we will use them
			// internally.  Translated versions will be used later only at display time.
			'Draft'     => _x( 'Draft',     'a status which may be assigned to quotes & invoices', 'sliced-invoices' ),
			'Sent'      => _x( 'Sent',      'a status which may be assigned to quotes',            'sliced-invoices' ),
			'Accepted'  => _x( 'Accepted',  'a status which may be assigned to quotes',            'sliced-invoices' ),
			'Declined'  => _x( 'Declined',  'a status which may be assigned to quotes',            'sliced-invoices' ),
			'Cancelled' => _x( 'Cancelled', 'a status which may be assigned to quotes & invoices', 'sliced-invoices' ),
			'Expired'   => _x( 'Expired',   'a status which may be assigned to quotes',            'sliced-invoices' ),
		);
		
		foreach ( $quote_status_terms as $key => $value ) {
			if ( ! get_term_by( 'slug', sanitize_title( $key ), 'quote_status' ) ) {
				$result = wp_insert_term( $key, 'quote_status' );
				$flush_needed = true;
			}
		}
		
		$invoice_status_terms = array(
			// Here we use the '_x' function only so PoEdit will pick up these strings.
			// Our terms should always be inserted into WP by their original English names
			// (i.e. by the keys, not the values, below), since this is how we will use them
			// internally.  Translated versions will be used later only at display time.
			'Draft'     => _x( 'Draft',     'a status which may be assigned to quotes & invoices', 'sliced-invoices' ),
			'Paid'      => _x( 'Paid',      'a status which may be assigned to invoices',          'sliced-invoices' ),
			'Unpaid'    => _x( 'Unpaid',    'a status which may be assigned to invoices',          'sliced-invoices' ),
			'Overdue'   => _x( 'Overdue',   'a status which may be assigned to invoices',          'sliced-invoices' ),
			'Cancelled' => _x( 'Cancelled', 'a status which may be assigned to quotes & invoices', 'sliced-invoices' ),
		);
		
		foreach ( $invoice_status_terms as $key => $value ) {
			if ( ! get_term_by( 'slug', sanitize_title( $key ), 'invoice_status' ) ) {
				$result = wp_insert_term( $key, 'invoice_status' );
				$flush_needed = true;
			}
		}
		
		if ( $flush_needed ) {
			flush_rewrite_rules();
		}
		
		set_transient( 'sliced_taxonomy_terms_check', 'ok', 60*60*24 );
		
	}
	
	
	/**
	 * Admin notices
	 *
	 * @version 3.9.0
	 * @since   2.0.0
	 */
	public function custom_admin_notices() {

	    global $pagenow;

		/*
		 * Options updated notice
		 */
		if ( $pagenow == 'admin.php' && ( isset( $_GET['page'] ) && strpos( $_GET['page'], 'sliced_' ) !== false ) && isset( $_POST['submit-cmb'] ) ) {
			echo '<div class="updated">
				<p>' . __( 'Settings saved successfully.', 'sliced-invoices' ) . '</p>
			</div>';
		}

		/*
		 * Email sent notice
		 */
		if ( $pagenow == 'edit.php' && isset($_GET['email']) && $_GET['email'] == 'sent' ) {
			echo '<div class="updated">
				<p>' . __( 'Email was sent successfully.', 'sliced-invoices' ) . '</p>
			</div>';
		}
		
		/*
		 * Converted quote to invoice notice
		 */
		if ( $pagenow === 'post.php' && isset( $_GET['converted'] ) && $_GET['converted'] === 'invoice' ) {
			echo '<div class="updated">
				<p>' . sprintf(
					/* translators: %1s is a placeholder for the localized version of "quote". %2s is a placeholder for the localized version of "invoice". */
					__( 'Successfully converted %1s to %2s', 'sliced-invoices' ),
					sliced_get_quote_label(),
					sliced_get_invoice_label()
				) . '</p>
			</div>';
		}
		
		/*
		 * Created new invoice from quote notice
		 */
		if ( $pagenow === 'post.php' && isset( $_GET['created_invoice_from'] ) && $_GET['created_invoice_from'] ) {
			// $quote_id = intval( $_GET['created_invoice_from'] );
			echo '<div class="updated">
				<p>' . sprintf(
					/* translators: %1s is a placeholder for the localized version of "invoice". %2s is a placeholder for the localized version of "quote". */
					__( 'Successfully created new %1s from %2s', 'sliced-invoices' ),
					sliced_get_invoice_label(),
					sliced_get_quote_label()
				) . '</p>
			</div>';
		}
		
		/*
		 * Possible not compatible notices
		 */
		$errors = get_transient( 'sliced_activation_warning' );
	    if ( $errors ) {
	    
		    if ( $pagenow == 'plugins.php' && isset($errors['wp_error'] ) ) {
		         echo '<div class="error">
		             <p>' . __( 'Your WordPress version may not be compatible with the Sliced Invoices plugin. If you are having issues with the plugin, we recommend making a backup of your site and upgrading to the latest version of WordPress.', 'sliced-invoices' ) . '</p>
		         </div>';
		    }
		    if ( $pagenow == 'plugins.php' && isset($errors['php_error'] ) ) {
		         echo '<div class="error">
		             <p>' . __( 'Your PHP version may not be compatible with the Sliced Invoices plugin. We recommend contacting your server administrator and getting them to upgrade to a newer version of PHP.', 'sliced-invoices' ) . '</p>
		         </div>';
		    }
		    if ( $pagenow == 'plugins.php' && isset($errors['curl_error'] ) ) {
		         echo '<div class="error">
		             <p>' . __( 'You do not have the cURL extension installed on your server. This extension is required for some tasks including PayPal payments. Please contact your server administrator to have them install this on your server.', 'sliced-invoices' ) . '</p>
		         </div>';
		    }

		}

	}

	/**
	 * Modify post updated notices
	 *
	 * @since 	2.07
	 */
	public function invoice_quote_updated_messages( $messages ) {

		$post             = get_post();
		$post_type        = get_post_type( $post );
		$post_type_object = get_post_type_object( $post_type );

		if ( $post_type == 'sliced_quote' || $post_type == 'sliced_invoice' ) {

			$label = sliced_get_label();

			$messages[$post_type] = array(
				0  => '', // Unused. Messages start at index 1.
				1  => sprintf( __( '%s updated.', 'sliced-invoices' ), $label ),
				2  => '',
				3  => '',
				4  => sprintf( __( '%s updated.', 'sliced-invoices' ), $label ),
				/* translators: %s: date and time of the revision */
				5  => isset( $_GET['revision'] ) ? sprintf( __( '%1s restored to revision from %2s', 'sliced-invoices' ), $label, wp_post_revision_title( (int) $_GET['revision'], false ) ) : false,
				6  => sprintf( __( '%s published.', 'sliced-invoices' ), $label ),
				7  => sprintf( __( '%s saved.', 'sliced-invoices' ), $label ),
				8  => sprintf( __( '%s submitted.', 'sliced-invoices' ), $label ),
				9  => '',
				10 => sprintf( __( '%s draft updated.', 'sliced-invoices' ), $label )
			);

			if ( $post_type_object->publicly_queryable ) {

				$permalink = get_permalink( $post->ID );

				$view_link = sprintf( ' <a href="%s">%s</a>', esc_url( $permalink ), __( 'View ' . $label, 'sliced-invoices' ) );
				$messages[ $post_type ][1] .= $view_link;
				$messages[ $post_type ][6] .= $view_link;
				$messages[ $post_type ][9] .= $view_link;

				$preview_permalink = add_query_arg( 'preview', 'true', $permalink );
				$preview_link = sprintf( ' <a target="_blank" href="%s">%s</a>', esc_url( $preview_permalink ), __( 'Preview ' . $label, 'sliced-invoices' ) );
				$messages[ $post_type ][8]  .= $preview_link;
				$messages[ $post_type ][10] .= $preview_link;
			}

		}

		return $messages;

	}


	/**
	 * Add links to plugin page
	 *
	 * @since 	2.07
	 */
	public function custom_enter_title( $input ) {

		global $post_type;

		if ( is_admin() ) {
			if ( 'sliced_quote' == $post_type )
				return sprintf( __( 'Enter %s title', 'sliced-invoices' ), sliced_get_quote_label() );

			if ( 'sliced_invoice' == $post_type )
				return sprintf( __( 'Enter %s title', 'sliced-invoices' ), sliced_get_invoice_label() );
		}

		return $input;
	}


	/**
	 * Add links to plugin page
	 *
	 * @since 	2.0.0
	 */
	public function plugin_action_links( $links ) {

		$links[] = '<a href="'. esc_url( get_admin_url( null, 'admin.php?page=sliced_invoices_settings' ) ) .'">' . __( 'Settings', 'sliced-invoices' ) . '</a>';
		$links[] = '<a href="https://slicedinvoices.com/extensions/?utm_source=plugins_page&utm_campaign=free&utm_medium=sliced_invoices" target="_blank">' . __( 'Extensions', 'sliced-invoices' ) . '</a>';
		return $links;

	}

	/**
	 * Change the admin footer text on Sliced Invoices admin pages.
	 *
	 * @since  2.14
	 * @param  string $footer_text
	 * @return string
	 */
	public function admin_footer_text( $footer_text ) {

		if ( ! current_user_can( 'manage_options' ) )
			return $footer_text;

		if ( sliced_get_the_type() || ( isset( $_GET['page'] ) && strpos( $_GET['page'], 'sliced') ) ) {

			$footer_text = sprintf( __( 'If you like <strong>Sliced Invoices</strong> please leave us a %s&#9733;&#9733;&#9733;&#9733;&#9733;%s rating. A huge thank you in advance!', 'sliced-invoices' ), '<a href="https://wordpress.org/support/plugin/sliced-invoices/reviews/?filter=5#postform" target="_blank" class="">', '</a>' );

		}

		return $footer_text;
	}

	/**
	 * Hide metaboxes and extra items not needed
	 *
	 * @since 	2.0.0
	 */
	public function remove_some_junk() {

		$type = sliced_get_the_type();

		if ( $type ) {

			remove_meta_box('pageparentdiv', 'sliced_' . $type, 'side');
			remove_meta_box( $type . '_statusdiv', 'sliced_' . $type, 'side' );
			remove_meta_box('mymetabox_revslider_0', 'sliced_' . $type, 'normal' );

		}

	}


	/**
	 * Work out the date format
	 *
	 * @version 3.9.0
	 * @since   2.0.0
	 */
	public static function work_out_date_format( $date ) {
		
		$format = get_option( 'date_format' );
		
		$timestamp = false;
		
		if (
			is_int( $date ) ||
			( ctype_digit( $date ) && intval( $date ) <= PHP_INT_MAX && intval( $date ) >= ~PHP_INT_MAX )
		) {
			// $date is already a unix timestamp
			$timestamp = intval( $date );
		} else {
			// $date is a formatted string of some kind
			if ( strpos( $format, 'd/m') !== false ) {
				$date = str_replace( "/", ".", $date );
			}
			$timestamp = strtotime( $date );
		}
		
		$date = date( "Y-m-d H:i:s", $timestamp );
		
		// final check in case we got weird data
		if( $date === '1970-01-01 00:00:00' ) {
			$date = current_time( 'mysql' ); 
		}
		
		return $date;
		
	}
	
	
	/**
	 * Set published date as created date
	 *
	 * @since 	2.33
	 */
	public function set_published_date_as_created( $post_id ) {
		
		if ( ! $_POST ) {
			return;
		}
		
		// If this is a revision, get real post ID
		if ( $parent_id = wp_is_post_revision( $post_id ) ) {
			$post_id = $parent_id;
		}
		
		$type = sliced_get_the_type( $post_id );
		
		if ( ! in_array( $type, array( 'invoice', 'quote' ) ) ) {
			return;
		}
		
		$created = false;
		
		if ( isset( $_POST['sliced_created'] ) ) {
			$created = sanitize_text_field( $_POST['sliced_created'] );
		} elseif ( isset( $_POST['_sliced_' . $type . '_created'] ) ) {
			$created = sanitize_text_field( $_POST['_sliced_' . $type . '_created'] );
		}
		
		if ( ! $created ) {
			return;
		}
		
		// change the format if we have slashes
		$created_utc   = $this->work_out_date_format( $created ); // parses whatever $created is into UTC time formatted "Y-m-d H:i:s"
		$created_local = get_date_from_gmt( $created_utc, "Y-m-d H:i:s" ); // takes the above and converts it to local WordPress time
		
		// unhook this function so it doesn't loop infinitely
		remove_action( 'save_post', array( $this, 'set_published_date_as_created' ) );
		
		// update the post, which calls save_post again
		wp_update_post( array(
			'ID'            => $post_id,
			'post_date'     => $created_local,
			'post_date_gmt' => $created_utc,
		) );
		
		// re-hook this function
		add_action( 'save_post', array( $this, 'set_published_date_as_created' ) );
		
	}
	
	
	/**
	 * Set quote/invoice number for search
	 *
	 * @since 	3.7.0
	 */
	public function set_number_for_search( $post_id ) {
		
		if ( ! $_POST ) {
			return;
		}

		$type = sliced_get_the_type( $post_id );
		
		if ( ! $type ) {
			return;
		}

		$prefix = isset( $_POST['_sliced_'.$type.'_prefix'] ) ? sanitize_text_field( $_POST['_sliced_'.$type.'_prefix'] ) : '';
		$number = isset( $_POST['_sliced_'.$type.'_number'] ) ? sanitize_text_field( $_POST['_sliced_'.$type.'_number'] ) : '';
		$suffix = isset( $_POST['_sliced_'.$type.'_suffix'] ) ? sanitize_text_field( $_POST['_sliced_'.$type.'_suffix'] ) : '';
		
		$number_for_search = $prefix . $number . $suffix;
		
		update_post_meta( $post_id, '_sliced_number', $number_for_search );
		
	}
	
	
	/**
	 * Maybe mark invoice as paid (part 1 of 2).
	 *
	 * Fires on save_post before CMB2 has saved anything, to see if the payments field is dirty.
	 * If so, it will fire maybe_mark_as_paid_step_2() after CMB2 has completed saving.
	 *
	 * @since 	3.9.0
	 */
	public function maybe_mark_as_paid( $post_id ) {
		
		if ( ! $_POST ) {
			return;
		}
		
		$type = sliced_get_the_type( $post_id );
		
		if ( $type !== 'invoice' ) {
			return;
		}
		
		// compare the saved payments data with the just posted ones to determine if they were changed
		$payments      = get_post_meta( $post_id, '_sliced_payment', true );
		$payments_post = isset( $_POST['_sliced_payment'] ) ? $_POST['_sliced_payment'] : array();
		if ( is_array( $payments ) ) {
			foreach ( $payments as $key => $payment ) {
				foreach ( $payment as $k => $v ) {
					if ( $v === '' ) {
						unset( $payments[ $key ][ $k ] );
					}
				}
			}
		}
		foreach ( $payments_post as $key => $payment ) {
			foreach ( $payment as $k => $v ) {
				if ( $v === '' ) {
					unset( $payments_post[ $key ][ $k ] );
				}
			}
		}
		foreach ( $payments_post as $key => $payment ) {
			if ( ! is_int( $payment['date'] ) ) {
				$payments_post[ $key ]['date'] = strtotime( $payments_post[ $key ]['date'] );
			}
		}
		$payments_dirty = serialize( $payments ) !== serialize( $payments_post );
		
		if ( $payments_dirty ) {
			add_action( 'save_post', array( $this, 'maybe_mark_as_paid_step_2' ), PHP_INT_MAX );
		}
		
	}
	
	
	/**
	 * Maybe mark invoice as paid (part 2 of 2).
	 *
	 * @since 	3.9.0
	 */
	public function maybe_mark_as_paid_step_2( $post_id ) {
		
		$totals = Sliced_Shared::get_totals( $post_id );
		if ( $totals['sub_total'] > 0 && $totals['total_due'] < 0.0001 ) {
			Sliced_Invoice::set_as_paid( $post_id );
		}
		
	}


	/**
	 * Handle admin "convert from quote to invoice" action.
	 *
	 * @version 3.9.0
	 * @since   2.0.0
	 */
	public function convert_quote_to_invoice() {
		
		// get original post ID
		$id = isset( $_REQUEST['post'] ) ? intval( $_REQUEST['post'] ) : false;
		if ( ! $id ) {
			wp_die( __( 'Error: quote not found.', 'sliced-invoices' ) );
		}
		
		// verify the nonce
		if (
			! isset( $_REQUEST['sliced_convert_quote'] )
			|| ! wp_verify_nonce( $_REQUEST['sliced_convert_quote'], 'convert_' . $id )
		) {
			wp_die( __( 'The link you followed has expired.', 'sliced-invoices' ) );
		}
		
		// get the original post, verify it is a quote
		$post = get_post( $id );
		if ( ! $post || ! $post->post_type === 'sliced_quote' ) {
			wp_die( __( 'Error: quote not found.', 'sliced-invoices' ) );
		}
		
		// okay, now do the appropriate tasks...
		do_action( 'sliced_invoices_admin_before_convert_quote_to_invoice', $id );
		
		Sliced_Shared::convert_quote_to_invoice( $id );
		
		do_action( 'sliced_invoices_admin_after_convert_quote_to_invoice', $id );
		
		// redirect to the edit invoice screen and add query args to display the success message
		wp_redirect( add_query_arg(
			array(
				'post'      => $id,
				'action'    => 'edit',
				'converted' => 'invoice',
			),
			admin_url( 'post.php' )
		) );
		exit;
		
	}
	
	/**
	 * Handle admin "create invoice from quote" action.
	 *
	 * @since   3.9.0
	 */
	public function create_invoice_from_quote() {
		
		// get original post ID
		$id = isset( $_REQUEST['post'] ) ? intval( $_REQUEST['post'] ) : false;
		if ( ! $id ) {
			wp_die( __( 'Error: quote not found.', 'sliced-invoices' ) );
		}
		
		// verify the nonce
		if (
			! isset( $_REQUEST['sliced_create_invoice'] )
			|| ! wp_verify_nonce( $_REQUEST['sliced_create_invoice'], 'create_' . $id )
		) {
			wp_die( __( 'The link you followed has expired.', 'sliced-invoices' ) );
		}
		
		// get the original post, verify it is a quote
		$post = get_post( $id );
		if ( ! $post || ! $post->post_type === 'sliced_quote' ) {
			wp_die( __( 'Error: quote not found.', 'sliced-invoices' ) );
		}
		
		// okay, now do the appropriate tasks...
		do_action( 'sliced_invoices_admin_before_create_invoice_from_quote', $id );
		
		$new_post_id = Sliced_Shared::create_invoice_from_quote( $id );
		
		do_action( 'sliced_invoices_admin_after_create_invoice_from_quote', $id, $new_post_id );
		
		// redirect to the edit invoice screen and add query args to display the success message
		wp_redirect( add_query_arg(
			array(
				'post'                 => $new_post_id,
				'action'               => 'edit',
				'created_invoice_from' => $id,
			),
			admin_url( 'post.php' )
		) );
		exit;
		
	}
	
	/**
	 * Mark an invoice as overdue if it has unpaid as it's status.
	 *
	 * @since 	2.0.0
	 */
	public function mark_invoice_overdue() {

		$taxonomy = 'invoice_status';
		$args = array(
			'post_type'     =>  'sliced_invoice',
			'status'     	=>  'publish',
			'meta_query'    =>  array(
				array(
					'key' 		=>  '_sliced_invoice_due',
					'value' 	=>  0,	// this filters out invoices with no due date set
					'compare' 	=>  '>',
				),
				array(
					'key' 		=>  '_sliced_invoice_due',
					'value' 	=>  time(), // current_time( 'timestamp' ) no longer recommended. see https://codex.wordpress.org/Function_Reference/current_time
					'compare' 	=>  '<',
				),
			),
			'tax_query' => array(
				array(
					'taxonomy' => $taxonomy,
					'field'    => 'slug',
					'terms'    => 'unpaid',
				),
			),
			'posts_per_page' => -1,
		);
		$overdues = get_posts( apply_filters( 'sliced_mark_overdue_query', $args ) );

		// If a post exists, mark it as overdue.
		foreach ( $overdues as $overdue ) {
			Sliced_Invoice::set_as_overdue( $overdue->ID );
		}

	}
	
	
	/**
	 * Mark a quote as expired if it has sent as it's status.
	 *
	 * @since 	3.4.0
	 */
	public function mark_quote_expired() {

		$taxonomy = 'quote_status';
		$args = array(
			'post_type'     =>  'sliced_quote',
			'status'     	=>  'publish',
			'meta_query'    =>  array(
				array(
					'key' 		=>  '_sliced_quote_valid_until',
					'value' 	=>  0,	// this filters out invoices with no due date set
					'compare' 	=>  '>',
				),
				array(
					'key' 		=>  '_sliced_quote_valid_until',
					'value' 	=>  time(), // current_time( 'timestamp' ) no longer recommended. see https://codex.wordpress.org/Function_Reference/current_time
					'compare' 	=>  '<',
				),
			),
			'tax_query' => array(
				array(
					'taxonomy' => $taxonomy,
					'field'    => 'slug',
					'terms'    => 'sent',
				),
			),
			'posts_per_page' => -1,
		);
		$expireds = get_posts( apply_filters( 'sliced_mark_expired_query', $args ) );

		// If a post exists, mark it as expired.
		foreach ( $expireds as $expired ) {
			Sliced_Quote::set_as_expired( $expired->ID );
		}

	}


	/**
	 * Get the list of statuses for each taxonomy.
	 *
	 * @since 	2.0.0
	 */
	public static function get_statuses() {
		$type = sliced_get_the_type();
		$terms = get_terms( $type . '_status', array( 'hide_empty' => 0 ) );
		return $terms;
	}


	/**
	 * Get the list of clients for the metabox and for the client filter.
	 *
	 * @version 3.9.0
	 * @since   2.0.0
	 */
	public static function get_clients() {

		global $current_user;

		if( ! function_exists('wp_get_current_user')) {
			include(ABSPATH . "wp-includes/pluggable.php");
		}

		// $current_user = wp_get_current_user();

		$args = array(
			'orderby'   => 'meta_value',
			'order'     => 'ASC',
			// 'exclude'   => $current_user->ID,
			'meta_key'  => '_sliced_client_business',
			'compare'   => 'EXISTS',
		);

		$user_query = new WP_User_Query( apply_filters( 'sliced_client_query', $args ) );

		$user_options = array( '' => __( 'Choose client', 'sliced-invoices' ) );

		if ( ! empty( $user_query->results ) ) {
			foreach ( $user_query->results as $user ) {
				$name = get_user_meta( $user->ID, '_sliced_client_business', true );
				$name .= $user->user_email ? ' (' . $user->user_email . ')' : '';
				if ( ! $name ) {
					$name = __( 'User ID:', 'sliced-invoices' ) . ' ' . $user->ID;
				}
				$user_options[$user->ID] = apply_filters( 'sliced_client_query_display_name', $name, $user );
			}
		}

		return $user_options;

	}


	/**
	 * Get the list of users who are not yet clients, for the add client dialog box
	 *
	 * @version 3.9.0
	 * @since   3.6.0
	 */
	public static function get_non_client_users() {

		global $current_user;

		if( ! function_exists('wp_get_current_user')) {
			include(ABSPATH . "wp-includes/pluggable.php");
		}

		// $current_user = wp_get_current_user();

		$args = array(
			'orderby'    => 'meta_value',
			'order'      => 'ASC',
			// 'exclude'    => $current_user->ID,
			'meta_query' => array(
				array(
					'key'  => '_sliced_client_business',
					'compare'   => 'NOT EXISTS',
				),
			),
		);

		$user_query = new WP_User_Query( $args );

		$user_options = array( '' => __( 'Choose user', 'sliced-invoices' ) );

		if ( ! empty( $user_query->results ) ) {
			foreach ( $user_query->results as $user ) {
				$user_options[$user->ID] = $user->user_login;
			}
		}

		return $user_options;

	}


	/**
	 * New client registration form. Hidden in the footer until required
	 *
	 * @version 3.9.0
	 * @since   2.0.0
	 */
	public function client_registration_form() {

	    global $pagenow;

		/*
		 * Only load on the post edit or post new screens.
		 */
		if ( ( $pagenow == 'post.php' || $pagenow == 'post-new.php' ) && ( sliced_get_the_type() ) ) {

			/*
			 * Load up the passed data, else set to a default.
			 */
			$creating = isset( $_POST['createuser'] );

			$new_user_login = $creating && isset( $_POST['user_login'] ) ? wp_unslash( $_POST['user_login'] ) : '';
			$new_user_firstname = $creating && isset( $_POST['first_name'] ) ? wp_unslash( $_POST['first_name'] ) : '';
			$new_user_lastname = $creating && isset( $_POST['last_name'] ) ? wp_unslash( $_POST['last_name'] ) : '';
			$new_user_email = $creating && isset( $_POST['email'] ) ? wp_unslash( $_POST['email'] ) : '';
			$new_user_uri = $creating && isset( $_POST['url'] ) ? wp_unslash( $_POST['url'] ) : '';
			$new_user_role = $creating && isset( $_POST['role'] ) ? wp_unslash( $_POST['role'] ) : '';
			$new_user_send_password = $creating && isset( $_POST['send_password'] ) ? wp_unslash( $_POST['send_password'] ) : true;
			$new_user_ignore_pass = $creating && isset( $_POST['noconfirmation'] ) ? wp_unslash( $_POST['noconfirmation'] ) : '';
			
			$non_client_users = $this->get_non_client_users();

			/*
			 * The form is basically copied from the core new user page.
			 */
			?>
			<div id="add-ajax-user" style="display:none">

				<div class="result-message"></div>

				<p><span class="description"><?php printf( __( 'To create a new client, choose either an existing WordPress user to associate with the client, or create a new user.  For help see our support page about <a href="%s" target="_blank">Clients</a>.', 'sliced-invoices' ), 'https://slicedinvoices.com/support/clients/?utm_source=add_client_modal&utm_campaign=free&utm_medium=sliced_invoices' ); ?></span></p>

				<p><?php _e( 'Add new client from:', 'sliced-invoices' ); ?></p>
				
				<?php if ( current_user_can('create_users') ): ?>
				<p><input type="radio" name="sliced_add_client_type" id="sliced_add_client_type_existing" value="existing" /> <label for="sliced_add_client_type_existing"><?php _e( 'Existing User', 'sliced-invoices' ); ?></label></p>
				<?php else: ?>
				<div class="notice notice-error inline"><p><?php _e( 'Error: you do not have sufficient permissions to manage users.  Please contact an admin for assistance.', 'sliced-invoices' ); ?></p></div>
				<?php endif; ?>
				
				<form action="" method="post" name="sliced-update-user" id="sliced-update-user" class="validate sliced-new-client" novalidate="novalidate"<?php do_action( 'user_new_form_tag' );?> style="display:none;">

					<input name="action" type="hidden" value="sliced-update-user" />
					<?php wp_nonce_field( 'sliced-update-user', '_wpnonce_sliced-update-user' ); ?>

					<table class="form-table popup-form">

					<tbody>
						<tr class="form-field form-required">
							<th scope="row"><label for="sliced_update_user_user"><?php _e( 'Select Existing User:', 'sliced-invoices' ); ?>*</label></th>
							<td>
								<select name="sliced_update_user_user" id="sliced_update_user_user">
									<?php
									foreach ($non_client_users as $id => $username) {
										echo '<option value="' . esc_attr( $id ) . '">' . esc_html( $username ) . '</option>';
									}
									?>
								</select>
							</td>
						</tr>

						<tr class="form-field form-required">
							<th scope="row">
								<label for="_sliced_client_business"><?php _e( 'Business/Client Name', 'sliced-invoices' ); ?>*</label>
							</th>
							<td><input name="_sliced_client_business" value="" type="text" /></td>
						</tr>

						<tr class="form-field">
							<th scope="row">
								<label for="_sliced_client_address"><?php _e( 'Address', 'sliced-invoices' ); ?></label>
							</th><td>
								<textarea class="regular-text" name="_sliced_client_address"></textarea></td>
						</tr>

						<tr class="form-field">
							<th scope="row">
								<label for="_sliced_client_extra_info"><?php _e( 'Extra Info', 'sliced-invoices' ); ?></label>
							</th><td>
								<textarea class="regular-text" name="_sliced_client_extra_info"></textarea></td>
						</tr>

					</tbody>
					</table>

					<?php submit_button( __( 'Add New Client', 'sliced-invoices' ), 'primary', 'sliced-update-user', true, array( 'id' => 'sliced-update-user-submit', 'class' => 'submit button button-primary button-large' ) ); ?>

					<div class="indicator" style="display:none"><?php _e( 'Please wait...', 'sliced-invoices' ); ?></div>

				</form>
				
				<?php if ( current_user_can('create_users') ): ?>
				<p><input type="radio" name="sliced_add_client_type" id="sliced_add_client_type_new" value="new" /> <label for="sliced_add_client_type_new"><?php _e( 'Create New User', 'sliced-invoices' ); ?></label></p>
				<?php endif; ?>
				
				<form action="" method="post" name="sliced-create-user" id="sliced-create-user" class="validate sliced-new-client" novalidate="novalidate"<?php do_action( 'user_new_form_tag' );?> style="display:none;">

					<input name="action" type="hidden" value="sliced-create-user" />
					<?php wp_nonce_field( 'sliced-create-user', '_wpnonce_sliced-create-user' ); ?>

					<table class="form-table popup-form">

					<tbody>

						<tr class="form-field form-required">
							<th scope="row">
								<label for="_sliced_client_business"><?php _e( 'Business/Client Name', 'sliced-invoices' ); ?>*</label>
							</th>
							<td><input name="_sliced_client_business" value="" type="text" /></td>
						</tr>
						
						<tr class="form-field form-required">
							<th scope="row"><label for="email"><?php _e( 'E-mail' ); ?>*</label></th>
							<td><input name="email" type="email" id="email" value="<?php echo esc_attr( $new_user_email ); ?>" /></td>
						</tr>

						<tr class="form-field form-required">
							<th scope="row"><label for="user_login"><?php _e( 'Username' ); ?>*</label></th>
							<td><input name="user_login" type="text" id="user_login" value="<?php echo esc_attr( $new_user_login ); ?>" aria-required="true" autocapitalize="none" autocorrect="off" /></td>
						</tr>
						
						<tr class="form-field form-required user-pass1-wrap">
							<th scope="row">
								<label for="pass1">
									<?php _e( 'Password' ); ?>*
									<span class="description hide-if-js"><?php _e( '(required)' ); ?></span>
								</label>
							</th>
							<td>
								<input class="hidden" value=" " /><!-- #24364 workaround -->
								<button type="button" class="button button-secondary wp-generate-pw hide-if-no-js"><?php _e( 'Show password' ); ?></button>
								<div class="wp-pwd hide-if-js">
									<?php $initial_password = wp_generate_password( 24 ); ?>
									<span class="password-input-wrapper">
										<input type="password" name="pass1" id="pass1" class="regular-text" autocomplete="off" data-reveal="1" data-pw="<?php echo esc_attr( $initial_password ); ?>" aria-describedby="pass-strength-result" />
									</span>
									<button type="button" class="button button-secondary wp-hide-pw hide-if-no-js" data-toggle="0" aria-label="<?php esc_attr_e( 'Hide password' ); ?>">
										<span class="dashicons dashicons-hidden"></span>
										<span class="text"><?php _e( 'Hide' ); ?></span>
									</button>
									<button type="button" class="button button-secondary wp-cancel-pw hide-if-no-js" data-toggle="0" aria-label="<?php esc_attr_e( 'Cancel password change' ); ?>">
										<span class="text"><?php _e( 'Cancel' ); ?></span>
									</button>
									<div style="display:none" id="pass-strength-result" aria-live="polite"></div>
								</div>

							</td>
						</tr>
						<tr class="form-field form-required user-pass2-wrap hide-if-js">
							<th scope="row"><label for="pass2"><?php _e( 'Repeat Password' ); ?> <span class="description"><?php _e( '(required)' ); ?></span></label></th>
							<td>
							<input name="pass2" type="password" id="pass2" autocomplete="off" />
							</td>
						</tr>
						<tr class="pw-weak">
							<th><?php _e( 'Confirm Password' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="pw_weak" class="pw-checkbox" />
									<?php _e( 'Confirm use of weak password' ); ?>
								</label>
							</td>
						</tr>

						<tr class="form-field">
							<th scope="row">
								<label for="_sliced_client_address"><?php _e( 'Address', 'sliced-invoices' ); ?></label>
							</th><td>
								<textarea class="regular-text" name="_sliced_client_address"></textarea></td>
						</tr>

						<tr class="form-field">
							<th scope="row">
								<label for="_sliced_client_extra_info"><?php _e( 'Extra Info', 'sliced-invoices' ); ?></label>
							</th><td>
								<textarea class="regular-text" name="_sliced_client_extra_info"></textarea></td>
						</tr>

						<tr class="form-field">
							<th scope="row"><label for="first_name"><?php _e('First Name') ?> </label></th>
							<td><input name="first_name" type="text" id="first_name" value="<?php echo esc_attr( $new_user_firstname ); ?>" /></td>
						</tr>
						<tr class="form-field">
							<th scope="row"><label for="last_name"><?php _e('Last Name') ?> </label></th>
							<td><input name="last_name" type="text" id="last_name" value="<?php echo esc_attr( $new_user_lastname ); ?>" /></td>
						</tr>
						<tr class="form-field">
							<th scope="row"><label for="url"><?php _e('Website') ?></label></th>
							<td><input name="url" type="url" id="url" class="code" value="<?php echo esc_attr( $new_user_uri ); ?>" /></td>
						</tr>

					</tbody>
					</table>

					<?php submit_button( __( 'Add New Client', 'sliced-invoices' ), 'primary', 'sliced-create-user', true, array( 'id' => 'sliced-create-user-submit', 'class' => 'submit button button-primary button-large' ) ); ?>

					<div class="indicator" style="display:none"><?php _e( 'Please wait...', 'sliced-invoices' ); ?></div>

				</form>
				
			</div>
			
			<div id="sliced-ajax-update-client" style="display:none;">
			
				<div id="sliced-update-client-loading">
					<div class="spinner" style="visibility: visible; float: left;"></div>
					<p><?php _e( 'Loading client info...', 'sliced-invoices' ); ?></p>
				</div>

				<div id="sliced-update-client-loaded" style="display:none;">
				
					<div class="alert result-message">&nbsp;</div>

					<p><?php _e( 'Edit a client here.', 'sliced-invoices' ); ?><br>
					<span class="description"><?php printf(
						__( 'NOTE: Usernames/password cannot be changed here. For that, go to your <a href="%s">Users admin page</a>.', 'sliced-invoices' ),
						admin_url( 'users.php' )
					); ?></span></p>

					<form action="" method="post" name="sliced-update-client" id="sliced-update-client" class="validate sliced-update-client" novalidate="novalidate">
						
						<input name="user_login" type="hidden" value="" />
						<input name="action" type="hidden" value="sliced-update-client" />
						<?php wp_nonce_field( 'sliced-update-client', '_wpnonce_sliced-update-client' ); ?>

						<table class="form-table popup-form">

						<tbody>

							<tr class="form-field form-required">
								<th scope="row">
									<label for="_sliced_client_business"><?php _e( 'Business/Client Name', 'sliced-invoices' ); ?>*</label>
								</th>
								<td><input name="_sliced_client_business" value="" type="text" /></td>
							</tr>
							
							<tr class="form-field form-required">
								<th scope="row"><label for="email"><?php _e('E-mail'); ?>*</label></th>
								<td><input name="user_email" type="email" value="" /></td>
							</tr>

							<tr class="form-field">
								<th scope="row">
									<label for="_sliced_client_address"><?php _e( 'Address', 'sliced-invoices' ); ?></label>
								</th><td>
									<textarea class="regular-text" name="_sliced_client_address"></textarea></td>
							</tr>

							<tr class="form-field">
								<th scope="row">
									<label for="_sliced_client_extra_info"><?php _e( 'Extra Info', 'sliced-invoices' ); ?></label>
								</th><td>
									<textarea class="regular-text" name="_sliced_client_extra_info"></textarea></td>
							</tr>

							<tr class="form-field">
								<th scope="row"><label for="first_name"><?php _e('First Name') ?> </label></th>
								<td><input name="first_name" type="text" value="" /></td>
							</tr>
							<tr class="form-field">
								<th scope="row"><label for="last_name"><?php _e('Last Name') ?> </label></th>
								<td><input name="last_name" type="text" value="" /></td>
							</tr>
							<tr class="form-field">
								<th scope="row"><label for="url"><?php _e('Website') ?></label></th>
								<td><input name="user_url" type="url" class="code" value="" /></td>
							</tr>

						</tbody>
						</table>

						<?php submit_button( __( 'Update User', 'sliced-invoices' ), 'primary', 'sliced-update-client', true, array( 'id' => 'sliced-update-client-submit', 'class' => 'submit button button-primary button-large' ) ); ?>

						<div class="indicator" style="display:none"><?php _e( 'Please wait...', 'sliced-invoices' ); ?></div>

					</form>
				</div>
			</div>
			
			<script type="text/javascript">
				jQuery(document).ready(function($) {
				
					/*
					 * "Add New Client" button actions
					 */
					 
					// Toggle
					$('input[name="sliced_add_client_type"]').on('click',function(e){
						var type = $(this).val();
						if ( type === 'existing' ) {
							$('#sliced-create-user').hide();
							$('#sliced-update-user').slideDown();
						}
						if ( type === 'new' ) {
							$('#sliced-create-user').slideDown();
							$('#sliced-update-user').hide();
						}
					});
					
					// Autofill username when creating new user
					$( '#sliced-create-user' ).on( 'keyup change', 'input[name="_sliced_client_business"]', function(){
						var $usernameInput = $( '#sliced-create-user input[name="user_login"]' );
						if ( $usernameInput.data( 'sliced-has-user-input' ) !== 'true' ) {
							var autoUsername = $( this ).val();
							autoUsername = autoUsername.replace( /[^\w-]+/g, '' );
							$usernameInput.val( autoUsername );
						}
					});
					$( '#sliced-create-user' ).on( 'keyup change', 'input[name="user_login"]', function(){
						$( this ).data( 'sliced-has-user-input', 'true' );
					});
					
					// Update existing user
					$('#sliced-update-user-submit').click( function(event) {
						
						if (event.preventDefault) {
							event.preventDefault();
						} else {
							event.returnValue = false;
						}
						
						$('#_sliced_client .cmb2-metabox-description span').remove();
						$('.indicator').show();
						$('.result-message').hide();
						
						data = {
							action:                    'sliced-update-user',
							user_id:                   $('#sliced_update_user_user').val(),
							nonce:                     $('#_wpnonce_sliced-update-user').val(),
							_sliced_client_business:   $('#sliced-update-user input[name="_sliced_client_business"]').val(),
							_sliced_client_address:    $('#sliced-update-user textarea[name="_sliced_client_address"]').val(),
							_sliced_client_extra_info: $('#sliced-update-user textarea[name="_sliced_client_extra_info"]').val()
						};
						
						$.post( ajaxurl, data, function(response) {
							$('.indicator').hide();
							if( response != 'Error adding the new client.' ) {
								$("#_sliced_client").html(''); // prevents browser freezing when we insert the new content
								$("#_sliced_client").html(response);
								tb_remove();
								$('<span class="updated"><?php esc_attr_e( 'New Client Successfully Added', 'sliced-invoices' ); ?></span>').insertAfter('select#_sliced_client');
							} else {
								$('.result-message').addClass('form-invalid error notice notice-error inline');
								$('.result-message').show();
								$('.result-message').html( '<p><?php esc_attr_e( 'Please check that all required fields are filled in.', 'sliced-invoices' ); ?></p>' );
								$('.form-required').addClass('form-invalid');
							}
						});
						
					});
					
					// Create new user
					$('#sliced-create-user-submit').click( function(event) {
						
						if (event.preventDefault) {
							event.preventDefault();
						} else {
							event.returnValue = false;
						}

						$('#_sliced_client .cmb2-metabox-description span').remove();
						$('.indicator').show();
						$('.result-message').hide();

						data = {
							action:     'sliced-create-user',
							nonce:      $('#_wpnonce_sliced-create-user').val(),
							user_login: $('#user_login').val(),
							password:   $('#pass1').val(),
							email:      $('#email').val(),
							first_name: $('#first_name').val(),
							last_name:  $('#last_name').val(),
							website:    $('#url').val(),
							business:   $('#sliced-create-user input[name="_sliced_client_business"]').val(),
							address:    $('#sliced-create-user textarea[name="_sliced_client_address"]').val(),
							extra_info: $('#sliced-create-user textarea[name="_sliced_client_extra_info"]').val(),
						};

						$.post( ajaxurl, data, function(response) {
							$('.indicator').hide();
							if( response != 'Error adding the new user.' ) {
								$("#_sliced_client").html(''); // prevents browser freezing when we insert the new content
								$("#_sliced_client").html(response);
								tb_remove();
								$('<span class="updated"><?php esc_attr_e( 'New Client Successfully Added', 'sliced-invoices' ); ?></span>').insertAfter('select#_sliced_client');
							} else {
								$('.result-message').addClass('form-invalid error notice notice-error inline');
								$('.result-message').show();
								$('.result-message').html( '<p><?php esc_attr_e( 'Please check that all required fields are filled in, and that this user does not already exist.', 'sliced-invoices' ); ?></p>' );
								$('.form-required').addClass('form-invalid');
							}
						});

					});
					
				
					/*
					 * "Edit Client" button actions
					 */
					$('a[href*="sliced-ajax-update-client"]').on('click',function(e){
						var user_id = $('#_sliced_client.cmb2_select').val();
						if ( ! user_id > '' ) {
							alert( '<?php esc_attr_e( 'No client selected', 'sliced-invoices' ); ?>' );
							e.preventDefault();
							e.stopImmediatePropagation();
							return false;
						}
						$('#sliced-update-client-loading').show();
						$('#sliced-update-client-loaded').hide();
						$.ajax({
							url: ajaxurl,
							data: {
								'action': 'sliced-get-client',
								'client_id': user_id,
								'nonce': $('#_wpnonce_sliced-update-client').val()
							},
							dataType: 'json',
							success:function(data) {
								$('#sliced-update-client-loading').hide();
								$('#sliced-update-client-loaded').show();
								$('#sliced-update-client input, #sliced-update-client textarea').each(function(){
									var attr_name = $(this).attr( 'name' );
									if( data.hasOwnProperty( attr_name ) ) {
										$(this).val( data[attr_name] );
									}
								});
							},
							error: function(errorThrown){
								$('#sliced-update-client-loading').html("<p>"+errorThrown.responseText+"</p>").addClass("notice notice-error inline");
							}
						});  
					});
					 
					$('#sliced-update-client-submit').click( function(event) {
					
						if (event.preventDefault) {
							event.preventDefault();
						} else {
							event.returnValue = false;
						}
						
						$('#_sliced_client .cmb2-metabox-description span').remove();
						$('.indicator').show();
						$('.result-message').hide();
						
						data = {
							action:                    'sliced-update-client',
							client_id:                 $('#_sliced_client.cmb2_select').val(),
							nonce:                     $('#_wpnonce_sliced-update-client').val(),
							user_login:                $('#sliced-update-client input[name="user_login"]').val(),
							user_email:                $('#sliced-update-client input[name="user_email"]').val(),
							first_name:                $('#sliced-update-client input[name="first_name"]').val(),
							last_name:                 $('#sliced-update-client input[name="last_name"]').val(),
							user_url:                  $('#sliced-update-client input[name="user_url"]').val(),
							_sliced_client_business:   $('#sliced-update-client input[name="_sliced_client_business"]').val(),
							_sliced_client_address:    $('#sliced-update-client textarea[name="_sliced_client_address"]').val(),
							_sliced_client_extra_info: $('#sliced-update-client textarea[name="_sliced_client_extra_info"]').val()
						};
						
						$.post( ajaxurl, data, function(response) {
							$('.indicator').hide();
							if( response != 'Error updating the user.' ) {
								$("#_sliced_client").html(''); // prevents browser freezing when we insert the new content
								$("#_sliced_client").html(response);
								tb_remove();
								$('<span class="updated"><?php esc_attr_e( 'Client successfully updated', 'sliced-invoices' ); ?></span>').insertAfter('select#_sliced_client');
							} else {
								$('.result-message').addClass('form-invalid error notice notice-error inline');
								$('.result-message').show();
								$('.result-message').html('<p><?php esc_attr_e( 'Please check that all required fields are filled in.', 'sliced-invoices' ); ?></p>');
								$('.form-required').addClass('form-invalid');
							}
						});

					});
				});
			</script>
		<?php
		}
	}


	/**
	 * Action to register the new client
	 *
	 * @version 3.8.16
	 * @since   2.0.0
	 */
	public function create_user() {

		/*
		 * Verify the nonce
		 */
		if ( ! current_user_can('create_users') )
			wp_die( __( 'Cheatin&#8217; uh?' ), 403 );

		if( !isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'sliced-create-user' ) )
			wp_die( 'Ooops, something went wrong, please try again later.' );

		if ( empty( $_POST['business'] ) || empty( $_POST['email'] ) || empty ( $_POST['user_login'] ) ) {
			die( 'Error adding the new user.' );
		}

		/*
		 * Put the POSTED user data into array
		 */
		$userdata = array(
			'user_login' 	=> sanitize_text_field( $_POST['user_login'] ),
			'user_pass'  	=> sanitize_text_field( $_POST['password'] ),
			'user_email' 	=> sanitize_text_field( $_POST['email'] ),
			'first_name' 	=> sanitize_text_field( $_POST['first_name'] ),
			'last_name' 	=> sanitize_text_field( $_POST['last_name'] ),
			'user_url'   	=> sanitize_text_field( $_POST['website'] ),
		);

		/*
		 * Inserts the user into the database
		 */
		$user_id = wp_insert_user( apply_filters( 'sliced_register_client_data', $userdata ) );

		/*
		 * Add the custom user meta
		 */
		update_user_meta( $user_id, 'show_admin_bar_front', 'false' );
		update_user_meta( $user_id, '_sliced_client_business', sanitize_text_field( $_POST['business'] ) );
		update_user_meta( $user_id, '_sliced_client_address', wp_kses_post( $_POST['address'] ) );
		update_user_meta( $user_id, '_sliced_client_extra_info', wp_kses_post( $_POST['extra_info'] ) );

		/*
		 * Returns the updated client select input
		 */
		if( ! is_wp_error( $user_id ) ) {

			$clients = $this->get_clients();

			$option = '';

			foreach ($clients as $id => $business_name) {
				$option .= '<option value="' . esc_attr( $id ) . '"' . ( $id == $user_id ? ' selected' : '' ) . '>';
				$option .= esc_html( $business_name );
				$option .= '</option>';
			}

			echo $option;

		} else {


			die( 'Error adding the new user.' );

		}

		die();

	}


	/**
	 * Action to register existing user as new client
	 *
	 * @since 	3.6.0
	 */
	public function update_user() {

		/*
		 * Verify the nonce
		 */
		if ( ! current_user_can('create_users') )
			wp_die( __( 'Cheatin&#8217; uh?' ), 403 );

		if( !isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'sliced-update-user' ) )
			wp_die( 'Ooops, something went wrong, please try again later.' );

		if( empty( $_POST['user_id'] ) || empty( $_POST['_sliced_client_business'] ) ) {
			wp_die( 'Error adding the new client.' );
		}

		/*
		 * Inserts the user into the database
		 */
		$user_id = intval( sanitize_text_field( $_POST['user_id'] ) );

		/*
		 * Add the custom user meta
		 */
		update_user_meta( $user_id, 'show_admin_bar_front', 'false' );
		update_user_meta( $user_id, '_sliced_client_business', sanitize_text_field( $_POST['_sliced_client_business'] ) );
		update_user_meta( $user_id, '_sliced_client_address', wp_kses_post( $_POST['_sliced_client_address'] ) );
		update_user_meta( $user_id, '_sliced_client_extra_info', wp_kses_post( $_POST['_sliced_client_extra_info'] ) );

		/*
		 * Returns the updated client select input
		 */
		if( ! is_wp_error( $user_id ) ) {

			$clients = $this->get_clients();

			$option = '';

			foreach ($clients as $id => $business_name) {
				$option .= '<option value="' . esc_attr( $id ) . '"' . ( $id == $user_id ? ' selected' : '' ) . '>';
				$option .= esc_html( $business_name );
				$option .= '</option>';
			}

			echo $option;

		} else {

			die( 'Error adding the new user.' );

		}

		die();

	}
	
	
	/**
	 * Action to get existing client data
	 *
	 * @since 	3.2.0
	 */
	public function get_client() {
		
		if ( ! current_user_can('create_users') ) {
			wp_die( __( 'Error: you do not have sufficient permissions to manage users.  Please contact an admin for assistance.', 'sliced-invoices' ), 403 );
		}

		if( !isset( $_GET['nonce'] ) || ! wp_verify_nonce( $_GET['nonce'], 'sliced-update-client' ) ) {
			wp_die( __( 'Ooops, something went wrong, please try again later.', 'sliced-invoices' ), 403 );
		}
		
		if( ! isset( $_GET['client_id'] ) || empty( $_GET['client_id'] ) ) {
			wp_die( __( 'No client selected.', 'sliced-invoices' ), 403 );
		}
		
		$client = get_userdata( intval( $_GET['client_id'] ) );
		
		if ( ! current_user_can('manage_options') && user_can( $client->ID, 'manage_options' ) ) {
			// don't allow non-admins to edit admins
			wp_die( __( 'Error: you do not have sufficient permissions to edit this user. Please contact an admin for assistance.', 'sliced-invoices' ), 403 );
		}
		
		$return = array(
			'user_login'                => $client->user_login,
			'user_email'                => $client->user_email,
			'first_name'                => $client->first_name,
			'last_name'                 => $client->last_name,
			'user_url'                  => $client->user_url,
			'_sliced_client_business'   => $client->_sliced_client_business,
			'_sliced_client_address'    => $client->_sliced_client_address,
			'_sliced_client_extra_info' => $client->_sliced_client_extra_info,
		);
		
		echo( json_encode( $return ) );
		exit;
		
	}
	
	
	/**
	 * Action to edit an existing client
	 *
	 * @since 	3.2.0
	 */
	public function update_client() {
		
		if ( ! current_user_can('create_users') ) {
			wp_die( __( 'Error: you do not have sufficient permissions to manage users.  Please contact an admin for assistance.', 'sliced-invoices' ), 403 );
		}

		if( !isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'sliced-update-client' ) ) {
			wp_die( __( 'Ooops, something went wrong, please try again later.', 'sliced-invoices' ), 403 );
		}
		
		if( ! isset( $_POST['client_id'] ) || empty( $_POST['client_id'] ) ) {
			wp_die( __( 'No client selected.', 'sliced-invoices' ), 403 );
		}
		
		$client_id = intval( $_POST['client_id'] );
		
		if ( ! current_user_can('manage_options') && user_can( $client_id, 'manage_options' ) ) {
			// don't allow non-admins to edit admins
			wp_die( __( 'Error: you do not have sufficient permissions to edit this user. Please contact an admin for assistance.', 'sliced-invoices' ), 403 );
		}
		
		/*
		 * Do the updates
		 */
		$userdata = array(
			'ID'            => $client_id,
			'user_login' 	=> sanitize_text_field( $_POST['user_login'] ),
			'user_email' 	=> sanitize_text_field( $_POST['user_email'] ),
			'first_name' 	=> sanitize_text_field( $_POST['first_name'] ),
			'last_name' 	=> sanitize_text_field( $_POST['last_name'] ),
			'user_url'   	=> sanitize_text_field( $_POST['user_url'] ),
		);

		$user_id = wp_update_user( $userdata );
		update_user_meta( $client_id, '_sliced_client_business', sanitize_text_field( $_POST['_sliced_client_business'] ) );
		update_user_meta( $client_id, '_sliced_client_address', wp_kses_post( $_POST['_sliced_client_address'] ) );
		update_user_meta( $client_id, '_sliced_client_extra_info', wp_kses_post( $_POST['_sliced_client_extra_info'] ) );

		/*
		 * Returns the updated client select input
		 */
		if( ! is_wp_error( $user_id ) ) {

			$clients = $this->get_clients();

			$option = '';

			foreach ($clients as $id => $business_name) {
				$option .= '<option value="' . esc_attr( $id ) . '"' . ( $id == $client_id ? ' selected' : '' ) . '>';
				$option .= esc_html( $business_name );
				$option .= '</option>';
			}

			echo $option;

		} else {

			die( 'Error updating the user.' );

		}

		die();
		
	}


	/**
	 * Add the duplicate link to action list for post_row_actions
	 *
	 * @since 	2.0.0
	 */
	public function duplicate_quote_invoice_link( $actions, $post ) {

		if ( current_user_can('edit_posts') && ( $post->post_type == 'sliced_quote' || $post->post_type == 'sliced_invoice' ) ) {

			$nonce  = wp_create_nonce( 'sliced_invoices_duplicate_quote_invoice-' . $post->ID );
			$output = admin_url( 'admin.php?action=duplicate_quote_invoice&amp;post=' . $post->ID . '&amp;_wpnonce=' . $nonce );
			$actions['duplicate'] = '<a href="' . esc_url( $output ) . '" title="'. __( 'Clone this item', 'sliced-invoices' ) .'" rel="permalink">' . __( 'Clone', 'sliced-invoices' ) . '</a>';

		}

		return $actions;
	}


	/**
	 * Function creates post duplicate and redirects then to the edit post screen
	 *
	 * @version 3.9.0
	 * @since 	2.0.0
	 */
	public function duplicate_quote_invoice() {

		global $wpdb;
		
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}
		
		// get original post ID
		$post_id = isset( $_REQUEST['post'] ) ? intval( sanitize_text_field( $_REQUEST['post'] ) ) : false;
		if ( ! $post_id ) {
			wp_die( 'No quote or invoice to duplicate!' );
		}
		
		// verify the nonce
		$nonce = isset( $_REQUEST['_wpnonce'] ) ? $_REQUEST['_wpnonce'] : false;
		if ( ! wp_verify_nonce( $nonce, 'sliced_invoices_duplicate_quote_invoice-' . $post_id ) ) {
			wp_die( 'The link you followed has expired.' );
		}
		
		// get the original post, verify it is a quote or invoice
		$post = get_post( $post_id );
		if ( ! $post || ! in_array( $post->post_type, array( 'sliced_invoice', 'sliced_quote' ) ) ) {
			wp_die( 'Creation failed, could not find original invoice or quote: ' . $post_id );
		}
		
		/*
		 * create the post duplicate
		 */
		
		// new post data array
		$args = array(
			'comment_status' => $post->comment_status,
			'ping_status'    => $post->ping_status,
			'post_author'    => $post->post_author,
			'post_content'   => $post->post_content,
			'post_excerpt'   => $post->post_excerpt,
			'post_name'      => $post->post_name,
			'post_parent'    => $post->post_parent,
			'post_password'  => $post->post_password,
			'post_status'    => 'publish',
			'post_title'     => $post->post_title . ' - copy',
			'post_type'      => $post->post_type,
			'to_ping'        => $post->to_ping,
			'menu_order'     => $post->menu_order
		);

		// insert the post by wp_insert_post() function
		$new_post_id = wp_insert_post( $args );

		// get all current post terms ad set them to the new post draft
		$taxonomies = get_object_taxonomies($post->post_type); // returns array of taxonomy names for post type, ex array("category", "post_tag");
		foreach ($taxonomies as $taxonomy) {
			$post_terms = wp_get_object_terms($post_id, $taxonomy, array('fields' => 'slugs'));
			wp_set_object_terms($new_post_id, $post_terms, $taxonomy, false);
		}
		
		// duplicate post metas
		$non_cloneable_post_metas = apply_filters( 'sliced_invoices_non_cloneable_post_metas', array(
			'_sliced_log',
			'_sliced_number',
			'_sliced_payment',
			'_sliced_invoice_email_sent',
			'_sliced_quote_email_sent',
		) );
		$post_metas = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id=%d",
				$post_id
			)
		);
		if ( $post_metas && count( $post_metas ) ) {
			$sql_query = "INSERT INTO {$wpdb->postmeta} (post_id, meta_key, meta_value) VALUES ";
			$sql_values = array();
			foreach ( $post_metas as $post_meta ) {
				$meta_key = esc_sql( $post_meta->meta_key );
				$meta_value = esc_sql( $post_meta->meta_value );
				if ( ! in_array( $meta_key, $non_cloneable_post_metas ) ) {
					$sql_values[]= "($new_post_id, '$meta_key', '$meta_value')";
				}
			}
			$sql_query .= implode( ',', $sql_values );
			$wpdb->query( $sql_query );
		}
		
		// increment the number
		if ( $post->post_type === 'sliced_invoice' ) {
			$prefix = get_post_meta( $new_post_id, '_sliced_invoice_prefix', true );
			$number = sliced_get_next_invoice_number();
			$suffix = get_post_meta( $new_post_id, '_sliced_invoice_suffix', true );
			update_post_meta( $new_post_id, '_sliced_invoice_number', (string)$number );
			update_post_meta( $new_post_id, '_sliced_number', $prefix . $number . $suffix );
			Sliced_Invoice::update_invoice_number( $new_post_id );
		}
		if ( $post->post_type === 'sliced_quote' ) {
			$prefix = get_post_meta( $new_post_id, '_sliced_quote_prefix', true );
			$number = sliced_get_next_quote_number();
			$suffix = get_post_meta( $new_post_id, '_sliced_quote_suffix', true );
			update_post_meta( $new_post_id, '_sliced_quote_number', (string)$number );
			update_post_meta( $new_post_id, '_sliced_number', $prefix . $number . $suffix );
			Sliced_Quote::update_quote_number( $new_post_id );
		}
		
		// finally, redirect to the current(ish) url
		$current_url = admin_url( 'edit.php?post_type=' . $post->post_type . '' );
		wp_redirect( $current_url );
		exit;

	}



	/**
	 * Get the pre-defined line items dropdown
	 *
	 * @since 	2.0.0
	 */
	public static function get_pre_defined_items() {

		/*
		 * fetch pre-defined items
		 */
		$general     = get_option( 'sliced_general' );
		$pre_defined = isset( $general['pre_defined'] ) ? $general['pre_defined'] : '';

		/*
		 * Explode each line into an array
		 */
		$items = explode("\n", $pre_defined);
		$items = array_filter( $items ); // remove any empty items
		$price_array[] = "<option value='' data-qty='' data-price='' data-title='' data-desc=''>" . __( 'Add a pre-defined line item', 'sliced-invoices' ) . "</option>";

		/*
		 * Check that we have items
		 */
		if( $items ) :

			$index = 0;
			foreach ( $items as $item ) {
			
				$item_array = explode( '|', $item );
				$qty   = isset( $item_array[0] ) ? trim( $item_array[0] ) : '';
				$title = isset( $item_array[1] ) ? trim( $item_array[1] ) : '';
				$price = isset( $item_array[2] ) ? trim( $item_array[2] ) : '';
				$desc  = isset( $item_array[3] ) ? trim( $item_array[3] ) : '';

				$price_array[] = "<option value='" . esc_html( $title ) . "' data-qty='" . esc_html( $qty ) . "' data-price='" . esc_html( $price ) . "' data-title='" . esc_html( $title ) . "' data-desc='" . wp_kses_post( $desc ) . "'>" . esc_html( $title ) . "</option>";

				$index++;
			}

		endif;

		$set_items = "<select class='pre_defined_products'>" . implode( "", $price_array ) . "</select>";

		return $set_items;

	}
	
	
	/**
	 * Output data in correct CSV format.
	 *
	 * @version 3.8.16
	 * @since   3.8.16
	 *
	 * @param array $rows    Array of arrays, with each array representing 1 row of data
	 */
	public function output_csv( $rows ) {
		
		ini_set( 'display_errors', '0' ); // try not to output any errors into the CSV file
		
		$out = fopen( 'php://output', 'w' );
		fprintf( $out, chr(0xEF) . chr(0xBB) . chr(0xBF) ); // BOM
		
		foreach ( $rows as $row ) {
			
			$csv_string = '';
			$i = 0;
			
			foreach ( $row as $value ) {
				
				// begin sanitizing value
				$needsEnclosing = false;
				
				// sanitization #1 -- enclose if $value contains special characters
				if ( preg_match( '/["\,\s]/', $value ) ) {
					$needsEnclosing = true;
				}
				
				// sanitization #2 -- prefix $value with single quote if it begins with a dangerous character
				// (prevents CSV formula injection in Excel)
				if ( preg_match( '/^[=\+\-@\s]/', $value ) ) {
					$value = "'" . $value;
				}
				
				// sanitization #3 -- special case for European locales where Excel will interpret a semicolon
				// as a field seperator, wherever it happens to be, even though as per RFC 4180 the only valid
				// seperator is a comma.
				// (to prevent CSV formula injection, we'll add a single quote after the semicolon)
				$value = preg_replace( '/;(\s*[=\+\-@])/', ';\'$1', $value );
				
				// escape double quotes, if any
				$value = str_replace( '"', '""', $value );
				
				// add value
				if ( $needsEnclosing ) {
					$csv_string .= '"';
				}
				$csv_string .= $value;
				if ( $needsEnclosing ) {
					$csv_string .= '"';
				}
				
				// add seperator / line ending
				$i++;
				if ( $i < count( $row ) ) {
					$csv_string .= ',';
				} else {
					$csv_string .= "\n";
				}
				
			}
			
			fwrite( $out, $csv_string );
			
		}
		
		fclose( $out );
		
	}
	
	
	/**
	 * Set the headers for the CSV file
	 *
	 * @since 	2.0.0
	 */
	public function set_csv_headers( $filename ) {

		/*
		 * Disables caching
		 */
		$now = date("D, d M Y H:i:s");
		header("Expires: Tue, 03 Jul 2001 06:00:00 GMT");
		header("Cache-Control: max-age=0, no-cache, must-revalidate, proxy-revalidate");
		header("Last-Modified: {$now} GMT");

		/*
		 * Forces the download
		 */
		header("Content-Type: application/force-download");
		header("Content-Type: application/octet-stream");
		header("Content-Type: application/download");

		/*
		 * disposition / encoding on response body
		 */
		header("Content-Disposition: attachment;filename={$filename}");
		header("Content-Transfer-Encoding: binary");
	}


	/**
	 * Export to CSV (quick).
	 *
	 * @version 3.9.0
	 * @since   2.0.0
	 */
	public function export_csv() {

		// Do the checks
		if ( ! isset( $_GET['sliced_export'] ) ) {
			return;
		}

		if ( $_GET['sliced_export'] != 'csv' ) {
			return;
		}

		// Work out the post type
		$post_type = esc_html( $_GET['post_type'] );
		$type = sliced_get_the_type();

		// Create the header rows for the CSV
		$header_row = array(
			0  => __( 'Number', 'sliced-invoices' ),
			1  => __( 'Title', 'sliced-invoices' ),
			2  => __( 'Client', 'sliced-invoices' ),
			3  => __( 'Client Email', 'sliced-invoices' ),
			4  => __( 'Client Address', 'sliced-invoices' ),
			5  => __( 'Client Extra Info', 'sliced-invoices' ),
			6  => __( 'Status', 'sliced-invoices' ),
			7  => __( 'Created', 'sliced-invoices' ),
			8  => __( 'Sub Total', 'sliced-invoices' ),
			9  => __( 'Tax', 'sliced-invoices' ),
			10 => __( 'Total', 'sliced-invoices' ),
		);

		$data_rows = array();

		// Build our query args
		$args = array (
			'post_type'      => $post_type,
			'posts_per_page' => -1,
			'post_status'    => 'publish',
		);
		
		if ( isset( $_GET['sliced_client'] ) && $_GET['sliced_client'] ) {
			$args['meta_query'] = array(
				array(
					'key'   => '_sliced_client',
					'value' => intval( sanitize_text_field( $_GET['sliced_client'] ) ),
				),
			);
		}
		
		$date  = isset( $_GET['m'] ) ? sanitize_text_field( $_GET['m'] ) : null;
		$year  = $date ? intval( substr( $date, 0, 4 ) ) : null;
		$month = $date ? intval( substr( $date, -2 ) ) : null;
		if ( $year && $month ) {
			$args['date_query'] = array(
				array(
					'year'  => $year,
					'month' => $month,
				),
			);
		}
		
		// Query the posts
		$the_query = new WP_Query( apply_filters( 'sliced_export_csv_query', $args ) );
		
		if ( $the_query->have_posts() ) :
			while ( $the_query->have_posts() ) : $the_query->the_post();

			// Get statuses and create a comma separated list if more than one status exists
			$status_array = array();
			$statuses     = get_the_terms( Sliced_Shared::get_item_id(), $type . '_status' );
			if ( ! empty( $statuses ) && ! is_wp_error( $statuses ) ) {
				foreach ( $statuses as $status ) {
					$status_array[] = $status->name;
				}
			}

			if ( isset( $_GET[$type . '_status'] ) && $_GET[$type . '_status'] && ! in_array( ucfirst($_GET[$type . '_status'] ), $status_array) ) {
				continue;
			}
			
			// Put each posts data into the appropriate cell
			$row = array();
			$row[0]  = sliced_get_prefix() . sliced_get_number() . sliced_get_suffix();
			$row[1]  = html_entity_decode( get_the_title() );
			$row[2]  = sliced_get_client_business();
			$row[3]  = sliced_get_client_email();
			$row[4]  = sliced_get_client_address();
			$row[5]  = sliced_get_client_extra_info();
			$row[6]  = rtrim( implode( ',', $status_array ), ',' );
			$row[7]  = sliced_get_created() > 0 ? Sliced_Shared::get_local_date_i18n_from_timestamp( sliced_get_created() ) : '';
			$row[8]  = html_entity_decode( sliced_get_sub_total() );
			$row[9]  = html_entity_decode( sliced_get_tax_total() );
			$row[10] = html_entity_decode( sliced_get_total() );
			
			$row = apply_filters( 'sliced_export_csv_row', $row, get_the_ID() );
			$data_rows[] = $row;

			endwhile;
		endif;
		
		$header_row = apply_filters( 'sliced_export_csv_headers', $header_row );
		$data_rows = apply_filters( 'sliced_export_csv_data', $data_rows );
		
		$filename = sanitize_file_name( $type . '-export-' . date( 'Y-m-d' ) . '.csv' );
		
		$this->set_csv_headers( $filename );
		$this->output_csv( array_merge( array( $header_row ), $data_rows ) );
		
		die();
		
	}


	/**
	 * Export to CSV (full).
	 *
	 * From wp_admin -> Sliced Invoices -> Tools -> Export CSV.
	 *
	 * @version 3.8.16
	 * @since   3.6.0
	 */
	public function export_csv_full() {

		// Do the checks
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		
		$nonce = isset( $_POST['sliced-invoices-export-csv-nonce'] ) ? sanitize_text_field( $_POST['sliced-invoices-export-csv-nonce'] ) : false;
		if ( ! wp_verify_nonce( $nonce, 'sliced_invoices_export_csv' ) ) {
			return;
		}
		
		if ( ! isset( $_POST['csv_exporter_type'] ) ) {
			return;
		}
		
		if ( $_POST['csv_exporter_type'] === 'sliced_quote' ) {
			$post_type = 'sliced_quote';
			$type = 'quote';
		} elseif ( $_POST['csv_exporter_type'] === 'sliced_invoice' ) {
			$post_type = 'sliced_invoice';
			$type = 'invoice';
		} else {
			return;
		}

		global $wpdb;
		
		$header_row = array();
		$data_rows = array();

		// Query the posts
		$args 	= array (
			'post_type'     => $post_type,
			'posts_per_page'=> -1,
			'post_status'   => 'publish',
			);
		$the_query = new WP_Query( apply_filters( 'sliced_export_csv_query', $args ) );

		if ( $the_query->have_posts() ) :
		
			// post meta header row
			$postmeta_headers = array();
			while ( $the_query->have_posts() ) : $the_query->the_post();
				$id = get_the_ID();
				$post_metas = get_post_meta( $id, '', true );
				foreach ( $post_metas as $key => $value ) {
					if ( substr( $key, 0, 7 ) === '_sliced' || substr( $key, 0, 6 ) === 'sliced' ) {
						if ( ! in_array( $key, $postmeta_headers ) ) {
							$postmeta_headers[] = $key;
						}
					}
				}
			endwhile;
			
			sort( $postmeta_headers );
			
			// header row
			$header_row = array(
				0  => '__'.$type.'_title',
				1  => '__'.$type.'_description',
				2  => '__'.$type.'_client',
				3  => '__'.$type.'_client_name',
				4  => '__'.$type.'_client_email',
				5  => '__'.$type.'_client_address',
				6  => '__'.$type.'_client_extra_info',
				7  => '__'.$type.'_status',
				8  => '__'.$type.'_created',
			);
			$header_row = array_merge( $header_row, $postmeta_headers );
			$columns = count( $header_row );
		
			// reset to start populating data
			rewind_posts();
			
			while ( $the_query->have_posts() ) : $the_query->the_post();

				// Get statuses and create a comma separated list if more than one status exists
				$status_array = array();
				$statuses     = get_the_terms( Sliced_Shared::get_item_id(), $type . '_status' );
				if ( ! empty( $statuses ) && ! is_wp_error( $statuses ) ) {
					foreach ( $statuses as $status ) {
						$status_array[] = $status->name;
					}
				}
				
				// Get client name
				$client_id = sliced_get_client_id();
				$client = get_userdata( $client_id );
				
				// initialize row with empty cells
				$row = array();
				for ( $i = 0; $i < $columns; $i++ ) {
					$row[$i] = '';
				}
				
				// Put each posts data into the appropriate cell
				$row[0]  = html_entity_decode( get_the_title() );
				$row[1]  = html_entity_decode( get_the_content() );
				$row[2]  = sliced_get_client_business();
				$row[3]  = empty( $client ) ? sliced_get_client_email() : $client->user_login;
				$row[4]  = sliced_get_client_email();
				$row[5]  = sliced_get_client_address();
				$row[6]  = sliced_get_client_extra_info();
				$row[7]  = rtrim( implode( ',', $status_array ), ',' );
				$row[8]  = sliced_get_created() > 0 ? Sliced_Shared::get_local_date_i18n_from_timestamp( sliced_get_created() ) : '';
				
				// grab the remaining fields for a complete record
				$id = get_the_ID();
				$post_metas = get_post_meta( $id, '', true );
				foreach ( $post_metas as $key => $value ) {
					if ( substr( $key, 0, 7 ) === '_sliced' || substr( $key, 0, 6 ) === 'sliced' ) {
						$index = array_search( $key, $header_row );
						$row[ $index ] = is_array( $value ) ? $value[0] : $value;
					}
				}
				
				$row = apply_filters( 'sliced_export_csv_row', $row, get_the_ID(), $header_row );
				$data_rows[] = $row;

			endwhile;
			
		endif;
		
		$header_row = apply_filters( 'sliced_export_csv_headers', $header_row );
		$data_rows = apply_filters( 'sliced_export_csv_data', $data_rows );
		
		$filename = sanitize_file_name( $type . '-export-' . date( 'Y-m-d' ) . '.csv' );
		
		$this->set_csv_headers( $filename );
		$this->output_csv( array_merge( array( $header_row ), $data_rows ) );
		
		die();
		
	}
	
	
	/**
	 * Catch-all place to do things to maintain compatibility with previous versions, older extensions, etc.
	 *
	 * @since 3.6.0
	 */
	public function legacy_compatibility() {
	
		// 1) Check if _sliced_payment postmeta is properly wrapped in an array
		// for use by CMB2.  This is required as of Sliced Invoices v3.6.0,
		// however if old extensions are still in use they may be saving the
		// old format.  So we have to keep checking...
		if ( Sliced_Shared::get_item_id() && sliced_get_the_type() === 'invoice' ) {
			$id = Sliced_Shared::get_item_id();
			$update_needed = false;
			$payments = get_post_meta( $id, '_sliced_payment', true );
			if ( is_array( $payments ) ) {
				foreach ( $payments as $payment ) {
					if ( ! is_array( $payment ) ) {
						$update_needed = true;
						break;
					}
				}
			}
			if ( $update_needed ) {
				$payments = array( $payments );
				update_post_meta( $id, '_sliced_payment', $payments );
			}
		}
		
	}
	
	
	/**
	 * Trigger notices for any issues with settings, etc.
	 *
	 * @since 3.5.0
	 */
	public function settings_check() {
	
		if ( ! method_exists( 'Sliced_Admin_Notices', 'add_notice' ) ) {
			return;
		}
		
		// 1) invalid_payment_page check
		if ( is_admin() ) {
			if ( isset( $_POST['object_id'] ) && $_POST['object_id'] === 'sliced_payments' && isset( $_POST['payment_page'] ) ) {
				// true if we just hit save from the sliced_payments settings page
				// CMB2 will not have saved the value in time for this message to fire, so we'll grab it from $_POST:
				$payments = array( 'payment_page' => intval( sanitize_text_field( $_POST['payment_page'] ) ) );
			} else {
				$payments = get_option( 'sliced_payments' );
			}
			$frontpage_id = get_option( 'page_on_front' );
			$page = get_post( $payments['payment_page'] );
			if (
				$payments['payment_page'] > 0
				&& (int)$frontpage_id !== (int)$payments['payment_page']
				&& $page
				&& isset( $page->post_status )
				&& $page->post_status !== 'trash'
			) {
				Sliced_Admin_Notices::remove_notice( 'invalid_payment_page' );
			} else {
				Sliced_Admin_Notices::add_notice( 'invalid_payment_page', true );
			}
		}
		
		// 2) Additional Tax compatibility check
		if ( is_admin() ) {
			if ( defined('SI_ADD_TAX_VERSION') && version_compare( SI_ADD_TAX_VERSION, '1.3.0', '<' ) ) {
				Sliced_Admin_Notices::add_notice( 'update_needed_additional_tax', true );
			} else {
				Sliced_Admin_Notices::remove_notice( 'update_needed_additional_tax' );
			}
		}
		
		// 3) fix for bad update version
		if ( ! defined( 'SI_STRIPE_VERSION' ) ) {
			define( 'SI_STRIPE_VERSION', '2.0.1' );
		}
		if ( ! defined( 'SI_STRIPE_FILE' ) ) {
			define( 'SI_STRIPE_FILE', 'sliced-invoices-stripe' );
		}
		
	}
	
	
	/**
	 * Handle hourly tasks as needed
	 *
	 * @since     3.4.0
	 */
	public function sliced_invoices_hourly_tasks() {
	
		ini_set( 'max_execution_time', 300 );
	
		$semaphore = Sliced_Semaphore::factory();
		if ( $semaphore->lock() ) {
		
			$this->mark_quote_expired();
			$this->mark_invoice_overdue();
		
			$SN = new Sliced_Notifications();
			$SN->check_for_reminder_dates();
			
			$semaphore->unlock();
		}
		
	}

}
