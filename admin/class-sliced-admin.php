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
	 * Register the stylesheets for the admin area.
	 *
	 * @since   2.0.0
	 */
	public function enqueue_styles() {

		global $pagenow;

		/*
		 * Enqueue the main style
		 */
		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/admin.css', array(), $this->version, 'all' );

		/*
		 * Enqueue thickbox
		 */
		if ( ( $pagenow == 'post.php' || $pagenow == 'edit.php' ) && sliced_get_the_type() ) {
			wp_enqueue_style( 'thickbox' );
		}

	}


	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since   2.0.0
	 */
	public function enqueue_scripts() {

		global $pagenow;

		/*
		 * Enqueue the main script and localize the script
		 */
		if ( isset( $_GET['post_type'] ) && $_GET['post_type'] == 'page' )
			return;

		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/admin.js', array( 'jquery' ), $this->version, false );
		wp_enqueue_script( $this->plugin_name . '-decimal', plugin_dir_url( __FILE__ ) . 'js/decimal.min.js', array( 'jquery' ), $this->version, false );
		wp_localize_script( $this->plugin_name , 'sliced_payments', apply_filters( 'sliced_payments_localized_script', array(
			'tax' => sliced_get_tax_amount(),
			'currency_symbol' => sliced_get_currency_symbol(),
			'currency_pos' => sliced_get_currency_position(),
			'thousand_sep' => sliced_get_thousand_seperator(),
			'decimal_sep' => sliced_get_decimal_seperator(),
			'decimals' => sliced_get_decimals(),
			)
		)
		);
		wp_localize_script( $this->plugin_name, 'sliced_confirm', array(
			'convert_quote' => sprintf( __( 'Are you sure you want to convert from %1s to %2s', 'sliced-invoices' ), sliced_get_quote_label(), sliced_get_invoice_label() ),
			)
		);

		/*
		 * Conditionally enqueue the new client script
		 */
		if ( ( $pagenow == 'post.php' || $pagenow == 'post-new.php' ) && ( sliced_get_the_type() ) ) {
			//wp_enqueue_script( $this->plugin_name . '-new-client', plugin_dir_url( __FILE__ ) . 'js/new-client.js', array( 'jquery' ), $this->version, false );
			//wp_localize_script( $this->plugin_name . '-new-client' , 'sliced_new_client', array( 'sliced_ajax_url' => admin_url( 'admin-ajax.php' ) ) );
			wp_enqueue_script( 'password-strength-meter' );
			wp_enqueue_script( 'user-profile' );
		}

		/*
		 * Conditionally enqueue thickbox
		 */
		if ( ( $pagenow == 'post.php' || $pagenow == 'post-new.php' || $pagenow == 'edit.php' ) && ( sliced_get_the_type() ) ) {
			wp_enqueue_script( 'thickbox' );
		}

		/*
		 * Conditionally enqueue the quick edit js
		 */
		if ( ( $pagenow == 'edit.php' ) && ( sliced_get_the_type() ) ) {
			//wp_enqueue_script( 'jquery-ui-datepicker', array( 'jquery' ), '', false );
			wp_enqueue_script( $this->plugin_name . 'quick-edit', plugin_dir_url( __FILE__ ) . 'js/quick-edit.js', array( 'jquery' ), $this->version, false );
		}

		/*
		 * Conditionally enqueue the charts script
		 */
		if ( ( $pagenow == 'admin.php' ) && ( $_GET['page'] == 'sliced_reports' ) ) {
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
	 * Creates a new custom post type
	 *
	 * @since 	2.0.0
	 */
	public function new_cpt_quote() {

		$translate = get_option( 'sliced_translate' );

		$cap_type 	= 'post';
		$plural 	= sliced_get_quote_label_plural();
		$single 	= sliced_get_quote_label();
		$cpt_name 	= 'sliced_quote';

		$opts['can_export']								= TRUE;
		$opts['capability_type']						= $cap_type;
		$opts['description']							= '';
		$opts['exclude_from_search']					= TRUE;
		$opts['has_archive']							= FALSE;
		$opts['hierarchical']							= TRUE;
		$opts['map_meta_cap']							= TRUE;
		$opts['menu_icon']								= 'dashicons-sliced';
		// $opts['menu_position']							= 99.3;
		$opts['public']									= TRUE;
		$opts['publicly_querable']						= TRUE;
		$opts['query_var']								= TRUE;
		$opts['register_meta_box_cb']					= '';
		$opts['rewrite']								= FALSE;
		$opts['show_in_admin_bar']						= TRUE;
		$opts['show_in_menu']							= TRUE;
		$opts['show_in_nav_menu']						= TRUE;
		$opts['show_ui']								= TRUE;
		$opts['supports']								= array( 'title', 'comments' );
		$opts['taxonomies']								= array( 'quote_status' );

		$opts['capabilities']['delete_others_posts']	= "delete_others_{$cap_type}s";
		$opts['capabilities']['delete_post']			= "delete_{$cap_type}";
		$opts['capabilities']['delete_posts']			= "delete_{$cap_type}s";
		$opts['capabilities']['delete_private_posts']	= "delete_private_{$cap_type}s";
		$opts['capabilities']['delete_published_posts']	= "delete_published_{$cap_type}s";
		$opts['capabilities']['edit_others_posts']		= "edit_others_{$cap_type}s";
		$opts['capabilities']['edit_post']				= "edit_{$cap_type}";
		$opts['capabilities']['edit_posts']				= "edit_{$cap_type}s";
		$opts['capabilities']['edit_private_posts']		= "edit_private_{$cap_type}s";
		$opts['capabilities']['edit_published_posts']	= "edit_published_{$cap_type}s";
		$opts['capabilities']['publish_posts']			= "publish_{$cap_type}s";
		$opts['capabilities']['read_post']				= "read_{$cap_type}";
		$opts['capabilities']['read_private_posts']		= "read_private_{$cap_type}s";

		$opts['labels']['add_new']						= __( "Add New {$single}", 'sliced-invoices' );
		$opts['labels']['add_new_item']					= __( "Add New {$single}", 'sliced-invoices' );
		$opts['labels']['all_items']					= __( $plural, 'sliced-invoices' );
		$opts['labels']['edit_item']					= __( "Edit {$single}" , 'sliced-invoices' );
		$opts['labels']['menu_name']					= __( $plural, 'sliced-invoices' );
		$opts['labels']['name']							= __( $plural, 'sliced-invoices' );
		$opts['labels']['name_admin_bar']				= __( $single, 'sliced-invoices' );
		$opts['labels']['new_item']						= __( "New {$single}", 'sliced-invoices' );
		$opts['labels']['not_found']					= __( "No {$plural} Found", 'sliced-invoices' );
		$opts['labels']['not_found_in_trash']			= __( "No {$plural} Found in Trash", 'sliced-invoices' );
		$opts['labels']['parent_item_colon']			= __( "Parent {$single}:", 'sliced-invoices' );
		$opts['labels']['search_items']					= __( "Search {$plural}", 'sliced-invoices' );
		$opts['labels']['singular_name']				= __( $single, 'sliced-invoices' );
		$opts['labels']['view_item']					= __( "View {$single}", 'sliced-invoices' );

		$opts['rewrite']['slug']						= FALSE;
		$opts['rewrite']['with_front']					= FALSE;
		$opts['rewrite']['feeds']						= FALSE;
		$opts['rewrite']['pages']						= FALSE;

		$opts = apply_filters( 'sliced_quote_params', $opts );

		register_post_type( 'sliced_quote', $opts );

	}



	/**
	 * Creates a new custom post type
	 *
	 * @since 	2.0.0
	 */
	public function new_cpt_invoice() {

		$translate = get_option( 'sliced_translate' );

		$cap_type 	= 'post';
		$plural 	= sliced_get_invoice_label_plural();
		$single 	= sliced_get_invoice_label();
		$cpt_name 	= 'sliced_invoice';

		$opts['can_export']								= TRUE;
		$opts['capability_type']						= $cap_type;
		$opts['description']							= '';
		$opts['exclude_from_search']					= TRUE;
		$opts['has_archive']							= FALSE;
		$opts['hierarchical']							= TRUE;
		$opts['map_meta_cap']							= TRUE;
		$opts['menu_icon']								= 'dashicons-sliced';
		// $opts['menu_position']							= 99.4;
		$opts['public']									= TRUE;
		$opts['publicly_querable']						= TRUE;
		$opts['query_var']								= TRUE;
		$opts['register_meta_box_cb']					= '';
		$opts['rewrite']								= FALSE;
		$opts['show_in_admin_bar']						= TRUE;
		$opts['show_in_menu']							= TRUE;
		$opts['show_in_nav_menu']						= TRUE;
		$opts['show_ui']								= TRUE;
		$opts['supports']								= array( 'title' );
		$opts['taxonomies']								= array( 'invoice_status' );

		$opts['capabilities']['delete_others_posts']	= "delete_others_{$cap_type}s";
		$opts['capabilities']['delete_post']			= "delete_{$cap_type}";
		$opts['capabilities']['delete_posts']			= "delete_{$cap_type}s";
		$opts['capabilities']['delete_private_posts']	= "delete_private_{$cap_type}s";
		$opts['capabilities']['delete_published_posts']	= "delete_published_{$cap_type}s";
		$opts['capabilities']['edit_others_posts']		= "edit_others_{$cap_type}s";
		$opts['capabilities']['edit_post']				= "edit_{$cap_type}";
		$opts['capabilities']['edit_posts']				= "edit_{$cap_type}s";
		$opts['capabilities']['edit_private_posts']		= "edit_private_{$cap_type}s";
		$opts['capabilities']['edit_published_posts']	= "edit_published_{$cap_type}s";
		$opts['capabilities']['publish_posts']			= "publish_{$cap_type}s";
		$opts['capabilities']['read_post']				= "read_{$cap_type}";
		$opts['capabilities']['read_private_posts']		= "read_private_{$cap_type}s";

		$opts['labels']['add_new']						= __( "Add New {$single}", 'sliced-invoices' );
		$opts['labels']['add_new_item']					= __( "Add New {$single}", 'sliced-invoices' );
		$opts['labels']['all_items']					= __( $plural, 'sliced-invoices' );
		$opts['labels']['edit_item']					= __( "Edit {$single}" , 'sliced-invoices' );
		$opts['labels']['menu_name']					= __( $plural, 'sliced-invoices' );
		$opts['labels']['name']							= __( $plural, 'sliced-invoices' );
		$opts['labels']['name_admin_bar']				= __( $single, 'sliced-invoices' );
		$opts['labels']['new_item']						= __( "New {$single}", 'sliced-invoices' );
		$opts['labels']['not_found']					= __( "No {$plural} Found", 'sliced-invoices' );
		$opts['labels']['not_found_in_trash']			= __( "No {$plural} Found in Trash", 'sliced-invoices' );
		$opts['labels']['parent_item_colon']			= __( "Parent {$single}:", 'sliced-invoices' );
		$opts['labels']['search_items']					= __( "Search {$plural}", 'sliced-invoices' );
		$opts['labels']['singular_name']				= __( $single, 'sliced-invoices' );
		$opts['labels']['view_item']					= __( "View {$single}", 'sliced-invoices' );

		$opts['rewrite']['slug']						= FALSE;
		$opts['rewrite']['with_front']					= FALSE;
		$opts['rewrite']['feeds']						= FALSE;
		$opts['rewrite']['pages']						= FALSE;

		$opts = apply_filters( 'sliced_invoice_params', $opts );

		register_post_type( 'sliced_invoice', $opts );

	}


	/**
	 * Creates a new taxonomy for a custom post type
	 *
	 * @since 	2.0.0
	 */
	public function new_taxonomy_quote_status() {

		$plural 	= 'Statuses';
		$single 	= 'Status';
		$tax_name 	= 'quote_status';

		$opts['hierarchical']							= TRUE;
		$opts['public']									= TRUE;
		$opts['query_var']								= $tax_name;
		$opts['show_admin_column'] 						= TRUE;
		$opts['show_in_nav_menus']						= FALSE;
		$opts['show_tag_cloud'] 						= FALSE;
		$opts['show_ui']								= FALSE;
		$opts['sort'] 									= '';

		$opts['capabilities']['assign_terms'] 			= 'edit_posts';
		$opts['capabilities']['delete_terms'] 			= 'manage_categories';
		$opts['capabilities']['edit_terms'] 			= 'manage_categories';
		$opts['capabilities']['manage_terms'] 			= 'manage_categories';

		$opts['labels']['add_new_item'] 				= __( "Add New {$single}", 'sliced-invoices' );
		$opts['labels']['add_or_remove_items'] 			= __( "Add or remove {$plural}", 'sliced-invoices' );
		$opts['labels']['all_items'] 					= __( $plural, 'sliced-invoices' );
		$opts['labels']['choose_from_most_used'] 		= __( "Choose from most used {$plural}", 'sliced-invoices' );
		$opts['labels']['edit_item'] 					= __( "Edit {$single}" , 'sliced-invoices');
		$opts['labels']['menu_name'] 					= __( $plural, 'sliced-invoices' );
		$opts['labels']['name'] 						= __( $plural, 'sliced-invoices' );
		$opts['labels']['new_item_name'] 				= __( "New {$single} Name", 'sliced-invoices' );
		$opts['labels']['not_found'] 					= __( "No {$plural} Found", 'sliced-invoices' );
		$opts['labels']['parent_item'] 					= __( "Parent {$single}", 'sliced-invoices' );
		$opts['labels']['parent_item_colon'] 			= __( "Parent {$single}:", 'sliced-invoices' );
		$opts['labels']['popular_items'] 				= __( "Popular {$plural}", 'sliced-invoices' );
		$opts['labels']['search_items'] 				= __( "Search {$plural}", 'sliced-invoices' );
		$opts['labels']['separate_items_with_commas'] 	= __( "Separate {$plural} with commas", 'sliced-invoices' );
		$opts['labels']['singular_name'] 				= __( $single, 'sliced-invoices' );
		$opts['labels']['update_item'] 					= __( "Update {$single}", 'sliced-invoices' );
		$opts['labels']['view_item'] 					= __( "View {$single}", 'sliced-invoices' );

		$opts['rewrite']['slug']						= __( strtolower( $tax_name ), 'sliced-invoices' );

		$opts = apply_filters( 'sliced_quote_status_params', $opts );

		register_taxonomy( $tax_name, 'sliced_quote', $opts );

	}

	/**
	 * Creates a new taxonomy for a custom post type
	 *
	 * @since 	2.0.0
	 */
	public function new_taxonomy_invoice_status() {

	    $plural 	= 'Statuses';
		$single 	= 'Status';
		$tax_name 	= 'invoice_status';

		$opts['hierarchical']							= TRUE;
		$opts['public']									= TRUE;
		$opts['query_var']								= $tax_name;
		$opts['show_admin_column'] 						= TRUE;
		$opts['show_in_nav_menus']						= FALSE;
		$opts['show_tag_cloud'] 						= FALSE;
		$opts['show_ui']								= FALSE;
		$opts['sort'] 									= '';

		$opts['capabilities']['assign_terms'] 			= 'edit_posts';
		$opts['capabilities']['delete_terms'] 			= 'manage_categories';
		$opts['capabilities']['edit_terms'] 			= 'manage_categories';
		$opts['capabilities']['manage_terms'] 			= 'manage_categories';

		$opts['labels']['add_new_item'] 				= __( "Add New {$single}", 'sliced-invoices' );
		$opts['labels']['add_or_remove_items'] 			= __( "Add or remove {$plural}", 'sliced-invoices' );
		$opts['labels']['all_items'] 					= __( $plural, 'sliced-invoices' );
		$opts['labels']['choose_from_most_used'] 		= __( "Choose from most used {$plural}", 'sliced-invoices' );
		$opts['labels']['edit_item'] 					= __( "Edit {$single}" , 'sliced-invoices');
		$opts['labels']['menu_name'] 					= __( $plural, 'sliced-invoices' );
		$opts['labels']['name'] 						= __( $plural, 'sliced-invoices' );
		$opts['labels']['new_item_name'] 				= __( "New {$single} Name", 'sliced-invoices' );
		$opts['labels']['not_found'] 					= __( "No {$plural} Found", 'sliced-invoices' );
		$opts['labels']['parent_item'] 					= __( "Parent {$single}", 'sliced-invoices' );
		$opts['labels']['parent_item_colon'] 			= __( "Parent {$single}:", 'sliced-invoices' );
		$opts['labels']['popular_items'] 				= __( "Popular {$plural}", 'sliced-invoices' );
		$opts['labels']['search_items'] 				= __( "Search {$plural}", 'sliced-invoices' );
		$opts['labels']['separate_items_with_commas'] 	= __( "Separate {$plural} with commas", 'sliced-invoices' );
		$opts['labels']['singular_name'] 				= __( $single, 'sliced-invoices' );
		$opts['labels']['update_item'] 					= __( "Update {$single}", 'sliced-invoices' );
		$opts['labels']['view_item'] 					= __( "View {$single}", 'sliced-invoices' );

		$opts['rewrite']['slug']						= __( strtolower( $tax_name ), 'sliced-invoices' );

		$opts = apply_filters( 'sliced_invoice_status_params', $opts );

		register_taxonomy( $tax_name, 'sliced_invoice', $opts );

	}
	
	/**
	 * Creates a new taxonomy for a custom post type
	 *
	 * @since 	3.0.0
	 */
	public function new_taxonomy_terms() {
	
		$bypass = get_transient( 'sliced_taxonomy_terms_check' );
		if ( $bypass ) {
			return;
		}
		
		$flush_needed = false;
	
		$quote_status = array(
			'quote_status' => array(
				'Draft',
				'Sent',
				'Accepted',
				'Declined',
				'Cancelled',
				'Expired',
			)
		);

		foreach ($quote_status as $taxonomy => $terms) {
			foreach ($terms as $term) {
				if (! get_term_by('slug', sanitize_title($term), $taxonomy)) {
					$result = wp_insert_term($term, $taxonomy);
					$flush_needed = true;
				}
			}
		}

		$invoice_status = array(
			'invoice_status' => array(
				'Draft',
				'Paid',
				'Unpaid',
				'Overdue',
				'Cancelled',
			)
		);

		foreach ($invoice_status as $taxonomy => $terms) {
			foreach ($terms as $term) {
				if (! get_term_by('slug', sanitize_title($term), $taxonomy)) {
					$result = wp_insert_term($term, $taxonomy);
					$flush_needed = true;
				}
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
	 * @since 	2.0.0
	 */
	public function custom_admin_notices( $post_states ) {

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
		if ( $pagenow == 'post.php' && isset($_GET['converted']) && $_GET['converted'] == 'invoice' ) {
			echo '<div class="updated">
				<p>' . sprintf( __( 'Successfully converted %1s to %2s', 'sliced-invoices' ), sliced_get_quote_label(), sliced_get_invoice_label() ) . '</p>
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

		$links[] = '<a href="'. esc_url( get_admin_url( null, 'admin.php?page=sliced_general' ) ) .'">' . __( 'Settings', 'sliced-invoices' ) . '</a>';
		$links[] = '<a href="https://slicedinvoices.com/extensions/?utm_source=Plugin&utm_medium=Plugins-Page&utm_content=Extensions&utm_campaign=Free" target="_blank">' . __( 'Extensions', 'sliced-invoices' ) . '</a>';
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

			$footer_text = sprintf( __( 'If you like <strong>Sliced Invoices</strong> please leave us a %s&#9733;&#9733;&#9733;&#9733;&#9733;%s rating. A huge thank you in advance!', 'sliced-invoices' ), '<a href="https://wordpress.org/support/view/plugin-reviews/sliced-invoices?filter=5#postform" target="_blank" class="">', '</a>' );

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
	 * Convert from quote to invoice button.
	 *
	 * @since 	2.0.0
	 */
	public static function get_convert_invoice_button() {

		/*
		 * Only show in quotes
		 */
		$type = sliced_get_the_type();
		if ( $type != 'quote' )
			return;

		$output = admin_url( 'admin.php?action=convert_quote_to_invoice&amp;post=' . (int) $_GET['post'] );

		$button = '<a id="convert_quote" title="' . sprintf( __( 'Convert %1s to %2s', 'sliced-invoices' ), sliced_get_quote_label(), sliced_get_invoice_label() ) . '" class="button ui-tip" href="' . esc_url( wp_nonce_url( $output, 'convert', 'sliced_convert_quote' ) ) . '"><span class="dashicons dashicons-controls-repeat"></span> ' . sprintf( __( 'Convert to %s', 'sliced-invoices' ), sliced_get_invoice_label() ) . '</a>';

		return $button;

	}


	/**
    * Work out the date format
    *
    * @since   2.0.0
    */
	private function work_out_date_format( $date ) {

		$format = get_option( 'date_format' );

		if (strpos( $format, 'd/m') !== false) {
			$date = str_replace("/", ".", $date);
		}

		$date = date("Y-m-d H:i:s", strtotime( $date ) );

		// final check if we get a weird data
		if( $date == '1970-01-01 00:00:00' ) {
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
		// If this is a revision, get real post ID
		if ( $parent_id = wp_is_post_revision( $post_id ) )
			$post_id = $parent_id;

		if ( ! $_POST )
			return;

		// Check if this post is in default category
		if ( sliced_get_the_type( $post_id ) ) {

			// unhook this function so it doesn't loop infinitely
			remove_action( 'save_post', array( $this, 'set_published_date_as_created' ) );

			// update the post, which calls save_post again
			$type = sliced_get_the_type($post_id);

			if( isset( $_POST['sliced_created'] ) )	{
				$created = $_POST['sliced_created'];
			} elseif ( isset( $_POST['_sliced_' . $type . '_created'] ) ) {
				$created = $_POST['_sliced_' . $type . '_created'];
			} else {
				$created = current_time( 'mysql' ); 
			}
			// change the format if we have slashes
			$created = $this->work_out_date_format( $created );
			
			wp_update_post( array( 'ID' => $post_id, 'post_date' => $created ) );

			// re-hook this function
			add_action( 'save_post', array( $this, 'set_published_date_as_created' ) );
		}
	}



	/**
	 * Convert from quote to invoice action.
	 *
	 * @since 	2.0.0
	 */
	public function convert_quote_to_invoice() {

		/*
		 * Do the checks
		 */
		if ( ! ( isset( $_GET['post'] ) || isset( $_POST['post'] )  || ( isset( $_REQUEST['action'] ) && 'convert_quote_to_invoice' == $_REQUEST['action'] ) ) ) {
			wp_die('No quote to convert!');
		}

		if ( ! isset( $_GET['sliced_convert_quote'] ) || ! wp_verify_nonce( $_GET['sliced_convert_quote'], 'convert') )
			wp_die( 'Ooops, something went wrong, please try again later.' );

		$id = (int) $_GET['post'];
		
		
		/*
		 * Do the appropriate action(s) upon quote acceptance
		 */
		$quotes = get_option( 'sliced_quotes' );
		$invoice = get_option( 'sliced_invoices' );
		$new_post_id = false;
		
		if ( $quotes['accepted_quote_action'] === 'convert' || $quotes['accepted_quote_action'] === 'convert_send' || empty( $quotes['accepted_quote_action'] ) || ( isset( $_REQUEST['action'] ) && 'convert_quote_to_invoice' == $_REQUEST['action'] ) ) {
			
			/*
			 * Convert
			 */
			$new_slug = '';
			$old_post = get_post( $id );
			if ( $old_post ) {
				$new_slug = sanitize_title( $old_post->post_title );
			}
			wp_update_post( array(
				'ID' => $id,
				'post_type' => 'sliced_invoice',
				'post_name' => $new_slug,
			) );

			/*
			 * Update the appropriate post meta
			 */
			$payment = sliced_get_accepted_payment_methods();
			update_post_meta( $id, '_sliced_invoice_terms', $invoice['terms'] );
			update_post_meta( $id, '_sliced_invoice_created', current_time( 'timestamp' ) );
			update_post_meta( $id, '_sliced_invoice_number', sliced_get_next_invoice_number() );
			update_post_meta( $id, '_sliced_invoice_prefix', sliced_get_invoice_prefix() );
			update_post_meta( $id, '_sliced_invoice_suffix', sliced_get_invoice_suffix() );
			update_post_meta( $id, '_sliced_payment_methods', array_keys($payment) );

			delete_post_meta( $id, '_sliced_quote_created' );
			delete_post_meta( $id, '_sliced_quote_number' );
			delete_post_meta( $id, '_sliced_quote_prefix' );
			delete_post_meta( $id, '_sliced_quote_suffix' );
			delete_post_meta( $id, '_sliced_quote_terms' );

			// update the invoice number
			Sliced_Invoice::update_invoice_number( $id );

			// Set the status as draft
			Sliced_Invoice::set_as_draft( $id );
		
		} elseif ( $quotes['accepted_quote_action'] === 'duplicate' || $quotes['accepted_quote_action'] === 'duplicate_send' ) {
		
			/*
			 * Duplicate
			 */
			global $wpdb;
		 
			$post = get_post( $id );
				
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
				'post_title'     => $post->post_title,
				'post_type'      => 'sliced_invoice',
				'to_ping'        => $post->to_ping,
				'menu_order'     => $post->menu_order
			);
	 
			$new_post_id = wp_insert_post( $args );
	 
			/*
			 * get all current post terms and set them to the new post draft
			 */
			$taxonomies = get_object_taxonomies($post->post_type); // returns array of taxonomy names for post type, ex array("category", "post_tag");
			foreach ($taxonomies as $taxonomy) {
				$post_terms = wp_get_object_terms($id, $taxonomy, array('fields' => 'slugs'));
				wp_set_object_terms($new_post_id, $post_terms, $taxonomy, false);
			}
	 
			/*
			 * duplicate all post meta just in two SQL queries
			 */
			$post_meta_infos = $wpdb->get_results("SELECT meta_key, meta_value FROM $wpdb->postmeta WHERE post_id=$id");
			if (count($post_meta_infos)!=0) {
				$sql_query = "INSERT INTO $wpdb->postmeta (post_id, meta_key, meta_value) ";
				foreach ($post_meta_infos as $meta_info) {
					$meta_key = $meta_info->meta_key;
					$meta_value = addslashes($meta_info->meta_value);
					$sql_query_sel[]= "SELECT $new_post_id, '$meta_key', '$meta_value'";
				}
				$sql_query.= implode(" UNION ALL ", $sql_query_sel);
				$wpdb->query($sql_query);
			}
			
			/*
			 * Update the appropriate post meta on the new post
			 */
			$payment = sliced_get_accepted_payment_methods();
			update_post_meta( $new_post_id, '_sliced_invoice_terms', $invoice['terms'] );
			update_post_meta( $new_post_id, '_sliced_invoice_created', current_time( 'timestamp' ) );
			update_post_meta( $new_post_id, '_sliced_invoice_number', sliced_get_next_invoice_number() );
			update_post_meta( $new_post_id, '_sliced_invoice_prefix', sliced_get_invoice_prefix() );
			update_post_meta( $new_post_id, '_sliced_invoice_suffix', sliced_get_invoice_suffix() );
			update_post_meta( $new_post_id, '_sliced_payment_methods', array_keys($payment) );

			delete_post_meta( $new_post_id, '_sliced_quote_created' );
			delete_post_meta( $new_post_id, '_sliced_quote_number' );
			delete_post_meta( $new_post_id, '_sliced_quote_prefix' );
			delete_post_meta( $new_post_id, '_sliced_quote_suffix' );
			delete_post_meta( $new_post_id, '_sliced_quote_terms' );

			// update the invoice number and set as draft
			Sliced_Invoice::update_invoice_number( $new_post_id );
			Sliced_Invoice::set_as_draft( $new_post_id );
		
		}
		
		
		/*
		 * The following applies to all accepted quote actions, including "do nothing"
		 */
		do_action( 'sliced_manual_convert_quote_to_invoice', $id );
		

		/*
		 * redirect to the edit invoice screen and add query args to display the success message
		 */
		$redirect = add_query_arg( array( 'post' => ( $new_post_id ? $new_post_id : $id ), 'action' => 'edit', 'converted' => 'invoice' ), admin_url( 'post.php' ) );
		wp_redirect( $redirect );

		exit;

	}



	/**
	 * Mark an invoice as overdue if it has unpaid as it's status.
	 *
	 * @since 	2.0.0
	 */
	public function mark_invoice_overdue() {

		/**
		 * Some explanation of the timezone maths, because it gets confusing quickly:
		 * 
		 * 1) Due dates are stored in _sliced_invoice_due as the unix timestamp
		 * representing the date, at precisely 12:00 AM UTC.  For example: a due date
		 * of 25 January 2017 would be stored as the timestamp equivalent of
		 * '2017-01-25 00:00:00' (which is 1485302400).
		 * 
		 * 
		 * 2) current_time( 'timestamp' ) returns the unix timestamp of the current time,
		 * localized to the timezone set in the WordPress settings.
		 * 
		 * For example:
		 * - let's say the current time in UTC is 1485334800 (2017-01-25 09:00:00)
		 * - and let's say the WordPress timezone is set to UTC+11 (Melbourne)
		 * - in this case, current_time( 'timestamp' ) will return 1485374400
		 * (2017-01-25 20:00:00), which is the current time in Melbourne.
		 *    
		 * 
		 * 3) So, you ask, why are we comparing a UTC timestamp (due date) with a
		 * localized timestamp (current_time('timestamp'))?  Shouldn't we be comparing
		 * UTC with UTC?  I.e. shouldn't we be using current_time('timestamp', true)?
		 * 
		 * You'd think so, but here's why we don't.  If our due date is
		 * 2017-01-25 00:00:00 UTC, and our site is in Melbourne, comparing UTC to UTC
		 * would mean our invoices become "overdue" at 2017-01-25 11:00:00 Melbourne
		 * time.  Kind of odd to become "overdue" in the middle of the day, don't you
		 * think?
		 * 
		 * To solve this, we could localize the due date.  For sake of argument, let's
		 * consider the scenario using UTC with current_time('timestamp',true).  If we
		 * roll back the due date by the 11 hour offset, we get: 2017-01-24 13:00:00 UTC,
		 * which is the same as 2017-01-25 00:00:00 Melbourne time.  The formula would
		 * thus be:
		 * 
		 *         due_date - offset < current_time('timestamp',true)
		 * 		
		 *     ...which can also be written as:
		 * 	
		 * 	       due_date < current_time('timestamp',true) + offset
		 * 		
		 *     ...but, guess what, current_time('timestamp',true) + offset is the exact
		 *     same thing as current_time('timestamp') (the localized time)!
		 * 	
		 *     So we just use:
		 * 	
		 * 	       due_date < current_time('timestamp')
		 * 	
		 * Confused yet?  Here's one final complication:
		 *    
		 * 
		 * 4) Even though the due dates are stored with the time 00:00:00 (due to issues
		 * we had between CMB2 and the Datepicker script), we don't really want to
		 * consider an invoice "overdue" until the entire day has passed.  So we have to
		 * advance the due date by 23:59:59, or 86399 seconds.  This makes our comparison
		 * formula like this:
		 * 
		 *         due_date + 86399 < current_time('timestamp')
		 * 		
		 *     ...which can also be written as:
		 * 	
		 *         due_date < current_time('timestamp') - 86399
		 * 		
		 *     ...hence what you see below.
		 *
		 */
		
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
					'value' 	=>  current_time( 'timestamp' ) - 86399, // see explanation above
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

		/*
		 * If a post exists, mark it as overdue.
		 */
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

		/**
		 * for extended discussion of the timezone maths, see mark_invoice_overdue() above.
		 */
		
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
					'value' 	=>  current_time( 'timestamp' ) - 86399, // see explanation above
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

		/*
		 * If a post exists, mark it as expired.
		 */
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
	 * @since 	2.0.0
	 */
	public static function get_clients() {

		global $current_user;

		if( ! function_exists('wp_get_current_user')) {
			include(ABSPATH . "wp-includes/pluggable.php");
		}

		$current_user = wp_get_current_user();

		$args = array(
			'orderby'   => 'meta_value',
			'order'     => 'ASC',
			'exclude'   => $current_user->ID,
			'meta_key'  => '_sliced_client_business',
			'compare'   => 'EXISTS',
		);

		$user_query = new WP_User_Query( apply_filters( 'sliced_client_query', $args ) );

		$user_options = array( '' => __( 'Choose client', 'sliced-invoices' ) );

		if ( ! empty( $user_query->results ) ) {
			foreach ( $user_query->results as $user ) {
				$user_options[$user->ID] = get_user_meta( $user->ID, '_sliced_client_business', true );
			}
		}

		return $user_options;

	}


	/**
	 * Get the list of users who are not yet clients, for the add client dialog box
	 *
	 * @since 	3.6.0
	 */
	public static function get_non_client_users() {

		global $current_user;

		if( ! function_exists('wp_get_current_user')) {
			include(ABSPATH . "wp-includes/pluggable.php");
		}

		$current_user = wp_get_current_user();

		$args = array(
			'orderby'    => 'meta_value',
			'order'      => 'ASC',
			'exclude'    => $current_user->ID,
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
	 * @since 	2.0.0
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

				<p><span class="description"><?php printf( __( 'To create a new client, choose either an existing WordPress user to associate with the client, or create a new user.  For help see our support page about <a href="%s" target="_blank">Clients</a>.', 'sliced-invoices' ), 'https://slicedinvoices.com/support/clients/' ); ?></span></p>

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

					<?php submit_button( __( 'Add New Client ', 'sliced-invoices' ), 'primary', 'sliced-update-user', true, array( 'id' => 'sliced-update-user-submit', 'class' => 'submit button button-primary button-large' ) ); ?>

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
							<th scope="row"><label for="user_login"><?php _e('Username'); ?>*</label></th>
							<td><input name="user_login" type="text" id="user_login" value="<?php echo esc_attr( $new_user_login ); ?>" aria-required="true" autocapitalize="none" autocorrect="off" /></td>
						</tr>
						<tr class="form-field form-required">
							<th scope="row"><label for="email"><?php _e('E-mail'); ?>*</label></th>
							<td><input name="email" type="email" id="email" value="<?php echo esc_attr( $new_user_email ); ?>" /></td>
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

						<tr class="form-field form-required user-pass1-wrap">
							<th scope="row">
								<label for="pass1">
									<?php _e( 'Password' ); ?>
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

					</tbody>
					</table>

					<?php submit_button( __( 'Add New Client ', 'sliced-invoices' ), 'primary', 'sliced-create-user', true, array( 'id' => 'sliced-create-user-submit', 'class' => 'submit button button-primary button-large' ) ); ?>

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
					<span class="description"><?php _e( 'NOTE: Username cannot be changed here.', 'sliced-invoices' ); ?></span></p>

					<form action="" method="post" name="sliced-update-client" id="sliced-update-client" class="validate sliced-update-client" novalidate="novalidate">

						<input name="action" type="hidden" value="sliced-update-client" />
						<?php wp_nonce_field( 'sliced-update-client', '_wpnonce_sliced-update-client' ); ?>

						<table class="form-table popup-form">

						<tbody>
							<tr class="form-field form-required">
								<th scope="row"><label for="user_login"><?php _e('Username'); ?>*</label></th>
								<td><input name="user_login" type="text" value="" aria-required="true" autocapitalize="none" autocorrect="off" readonly="readonly" /></td>
							</tr>
							<tr class="form-field form-required">
								<th scope="row"><label for="email"><?php _e('E-mail'); ?>*</label></th>
								<td><input name="user_email" type="email" value="" /></td>
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
								$("#_sliced_client").html(response);
								tb_remove();
								$('<span class="updated"><?php _e( 'New Client Successfully Added', 'sliced-invoices' ); ?></span>').insertAfter('select#_sliced_client');
							} else {
								$('.result-message').addClass('form-invalid error notice notice-error inline');
								$('.result-message').show();
								$('.result-message').html('<p><?php _e( 'Please check that all required fields are filled in.', 'sliced-invoices' ); ?></p>');
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
								$("#_sliced_client").html(response);
								tb_remove();
								$('<span class="updated"><?php _e( 'New Client Successfully Added', 'sliced-invoices' ); ?></span>').insertAfter('select#_sliced_client');
							} else {
								$('.result-message').addClass('form-invalid error notice notice-error inline');
								$('.result-message').show();
								$('.result-message').html('<p><?php _e( 'Please check that all required fields are filled in, and that this user does not already exist.', 'sliced-invoices' ); ?></p>');
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
							alert( '<?php _e( 'No client selected', 'sliced-invoices' ); ?>' );
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
								console.log(errorThrown);
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
								$("#_sliced_client").html(response);
								tb_remove();
								$('<span class="updated"><?php _e( 'Client successfully updated', 'sliced-invoices' ); ?></span>').insertAfter('select#_sliced_client');
							} else {
								$('.result-message').addClass('form-invalid error notice notice-error inline');
								$('.result-message').show();
								$('.result-message').html('<p><?php _e( 'Please check that all required fields are filled in.', 'sliced-invoices' ); ?></p>');
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
	 * @since 	2.0.0
	 */
	public function create_user() {

		/*
		 * Verify the nonce
		 */
		if ( ! current_user_can('create_users') )
			wp_die( __( 'Cheatin&#8217; uh?' ), 403 );

		if( !isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'sliced-create-user' ) )
			wp_die( 'Ooops, something went wrong, please try again later.' );

		if( empty( $_POST['business'] ) ) {
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
			wp_die( __( 'Cheatin&#8217; uh?' ), 403 );
		}

		if( !isset( $_GET['nonce'] ) || ! wp_verify_nonce( $_GET['nonce'], 'sliced-update-client' ) ) {
			wp_die( 'Ooops, something went wrong, please try again later.' );
		}
		
		if( ! isset( $_GET['client_id'] ) || empty( $_GET['client_id'] ) ) {
			wp_die( 'No client selected.' );
		}
		
		$client = get_userdata( intval( $_GET['client_id'] ) );
		
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
			wp_die( __( 'Cheatin&#8217; uh?' ), 403 );
		}

		if( !isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'sliced-update-client' ) ) {
			wp_die( 'Ooops, something went wrong, please try again later.' );
		}
		
		if( ! isset( $_POST['client_id'] ) || empty( $_POST['client_id'] ) ) {
			wp_die( 'No client selected.' );
		}
		
		/*
		 * Do the updates
		 */
		$client_id = intval( $_POST['client_id'] );
		
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

			$output = admin_url( 'admin.php?action=duplicate_quote_invoice&amp;post=' . $post->ID );
			$actions['duplicate'] = '<a href="' . esc_url( $output ) . '" title="'. __( 'Clone this item', 'sliced-invoices' ) .'" rel="permalink">' . __( 'Clone', 'sliced-invoices' ) . '</a>';

		}

		return $actions;
	}


	/**
	 * Function creates post duplicate and redirects then to the edit post screen
	 *
	 * @since 	2.0.0
	 */
	public function duplicate_quote_invoice() {

		global $wpdb;

		if ( ! ( isset( $_GET['post'] ) || isset( $_POST['post'] )  || ( isset( $_REQUEST['action'] ) && 'duplicate_quote_invoice' == $_REQUEST['action'] ) ) ) {
			wp_die('No quote or invoice to duplicate!');
		}

		/*
		 * get the original post id
		 */
		$post_id = (isset($_GET['post']) ? $_GET['post'] : $_POST['post']);

		/*
		 * and all the original post data then
		 */
		$post = get_post( $post_id );

		/*
		 * if post data exists, create the post duplicate
		 */
		if (isset( $post ) && $post != null) {

			/*
			 * new post data array
			 */
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

			/*
			 * insert the post by wp_insert_post() function
			 */
			$new_post_id = wp_insert_post( $args );

			/*
			 * get all current post terms ad set them to the new post draft
			 */
			$taxonomies = get_object_taxonomies($post->post_type); // returns array of taxonomy names for post type, ex array("category", "post_tag");
			foreach ($taxonomies as $taxonomy) {
				$post_terms = wp_get_object_terms($post_id, $taxonomy, array('fields' => 'slugs'));
				wp_set_object_terms($new_post_id, $post_terms, $taxonomy, false);
			}

			/*
			 * duplicate all post meta
			 */
			$post_meta_infos = $wpdb->get_results("SELECT meta_key, meta_value FROM $wpdb->postmeta WHERE post_id=$post_id");
			if (count($post_meta_infos)!=0) {
				$sql_query = "INSERT INTO $wpdb->postmeta (post_id, meta_key, meta_value) ";
				foreach ($post_meta_infos as $meta_info) {
					$meta_key        = $meta_info->meta_key;
					$meta_value      = addslashes($meta_info->meta_value);
					$sql_query_sel[] = "SELECT $new_post_id, '$meta_key', '$meta_value'";
				}
				$sql_query.= implode(" UNION ALL ", $sql_query_sel);
				$wpdb->query($sql_query);
			}

			/*
			 * increment the number
			 */
			if ( $post->post_type == 'sliced_invoice' ) {
				$number = sliced_get_next_invoice_number();
			} else {
				$number = sliced_get_next_quote_number();
			}
			update_post_meta( $new_post_id, '_' . $post->post_type . '_number', (string)$number);

			/*
			 * finally, redirect to the current(ish) url
			 */
			$current_url = admin_url( 'edit.php?post_type=' . $post->post_type . '' );
			wp_redirect( $current_url );
			exit;

		} else {
			wp_die('Creation failed, could not find original invoice or quote: ' . $post_id);
		}
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
	 * export to csv
	 *
	 * @since 	2.0.0
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

		// Query the posts
		$args 	= array (
			'post_type'     => $post_type,
			'posts_per_page'=> -1,
			'post_status'   => 'publish',
			);
		$the_query = new WP_Query( apply_filters( 'sliced_export_csv_query', $args ) );

		// Filter the query if they are active/
		if ( isset( $_GET['sliced_client'] ) && $_GET['sliced_client'] ) {
			$the_query->query_vars['meta_query'] = array(
				array(
					'key'      => '_sliced_client',
					'value'    => (int)$_GET['sliced_client']
				)
			);
		}

		if ( isset( $_GET['m'] ) && $_GET['m'] ) {
			$date  = isset( $_GET['m'] ) ? $_GET['m'] : null;
			$year  = $date ? substr($date, 0, 4) : null;
			$month = $date ? substr($date, -2) : null;
			$the_query->query_vars['date_query'] = array(
				array(
					'year'  => $year,
					'month' => $month,
				),
			);
		}

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
			$row[1]  = wp_kses_decode_entities( get_the_title() );
			$row[2]  = sliced_get_client_business();
			$row[3]  = sliced_get_client_email();
			$row[4]  = sliced_get_client_address();
			$row[5]  = sliced_get_client_extra_info();
			$row[6]  = rtrim( implode( ',', $status_array ), ',' );
			$row[7]  = date_i18n( get_option( 'date_format' ), (int) sliced_get_created() );
			$row[8]  = sliced_get_sub_total();
			$row[9]  = sliced_get_tax_total();
			$row[10] = sliced_get_total();
			
			$row = apply_filters( 'sliced_export_csv_row', $row, get_the_ID() );
			$data_rows[] = $row;

			endwhile;
		endif;
		
		$header_row = apply_filters( 'sliced_export_csv_headers', $header_row );
		$data_rows = apply_filters( 'sliced_export_csv_data', $data_rows );

		// Create the filename
		$filename = sanitize_file_name( $type . '-export-' . date( 'Y-m-d' ) . '.csv' );

		$this->set_csv_headers( $filename );

		$fh = @fopen( 'php://output', 'w' );
		fprintf( $fh, chr(0xEF) . chr(0xBB) . chr(0xBF) );
		fputcsv( $fh, $header_row );

		foreach ( $data_rows as $data_row ) {
			fputcsv( $fh, $data_row );
		}

		fclose( $fh );
		die();

	}


	/**
	 * export full data to csv (for Tools -> Export CSV)
	 *
	 * @since 	3.6.0
	 */
	public function export_csv_full() {

		// Do the checks
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
				$row[0]  = wp_kses_decode_entities( get_the_title() );
				$row[1]  = wp_kses_decode_entities( get_the_content() );
				$row[2]  = sliced_get_client_business();
				$row[3]  = empty( $client ) ? sliced_get_client_email() : $client->user_login;
				$row[4]  = sliced_get_client_email();
				$row[5]  = sliced_get_client_address();
				$row[6]  = sliced_get_client_extra_info();
				$row[7]  = rtrim( implode( ',', $status_array ), ',' );
				$row[8]  = date_i18n( get_option( 'date_format' ), (int) sliced_get_created() );
				
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

		// Create the filename
		$filename = sanitize_file_name( $type . '-export-' . date( 'Y-m-d' ) . '.csv' );

		$this->set_csv_headers( $filename );

		$fh = @fopen( 'php://output', 'w' );
		fprintf( $fh, chr(0xEF) . chr(0xBB) . chr(0xBF) );
		fputcsv( $fh, $header_row );

		foreach ( $data_rows as $data_row ) {
			fputcsv( $fh, $data_row );
		}

		fclose( $fh );
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
				$payments = array( 'payment_page' => $_POST['payment_page'] );
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
