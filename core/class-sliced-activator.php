<?php
// Exit if accessed directly
if ( ! defined('ABSPATH') ) { exit; }

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      2.0.0
 */
class Sliced_Activator {


	public static function activate() {

		global $wp_version;

		$plugin_name = 'sliced-invoices';
		$require = array(
			'wordpress' => '4.0',
			'php' => '5.3',
			'curl' => true,
		);

		$wp_version  = $wp_version;
		$php_version = phpversion();
        $extensions     = get_loaded_extensions();
        $curl           = in_array('curl', $extensions);

        $error = array();
        if($wp_version < $require['wordpress']) {
            $error['wp_error'] = 'yes';
        }
        if($php_version < $require['php']) {
            $error['php_error'] = 'yes';
        }
        if($curl != true) {
            $error['curl_error'] = 'yes';
        }

        set_transient( 'sliced_activation_warning', $error, 5 );

		// add default options
		$business_exists = get_option('sliced_business');
		$general_exists  = get_option('sliced_general');
		$payment_exists  = get_option('sliced_payments');
		$invoices_exists = get_option('sliced_invoices');
		$quotes_exists   = get_option('sliced_quotes');
		$email           = get_option('sliced_emails');


		if( ! $business_exists) {

			$business_array = array(
				'name'      => get_bloginfo('name'),
				'address'   => 'Suite 5A-1204
123 Somewhere Street
Your City AZ 12345',
				'extra_info'   => get_bloginfo('admin_email'),
				'website'   => get_bloginfo('url'),
				);

			update_option('sliced_business', $business_array);

		}

		if( ! $general_exists) {

			$general_array = array(
				'year_start'    => '07',
				'year_end'      => '06',
				'pre_defined'   => '
1 | Web Design | 85 | Design work on the website
1 | Web Development | 95 | Back end development of website',
				'footer'        => 'Thanks for choosing <a href="' . get_bloginfo('url') . '">' . get_bloginfo('site_name') . '</a> | <a href="mailto:' . get_bloginfo('admin_email') . '">' . get_bloginfo('admin_email') . '</a>'
				);

			update_option('sliced_general', $general_array);

		}

		if( ! $payment_exists) {

			// Create post object
			$payment_page = array(
				'post_title'      => 'Payment',
				'post_content'    => '',
				'post_status'     => 'publish',
				'post_type'       => 'page',
			);

			// Insert the post into the database
			$payment_id = wp_insert_post( $payment_page );

			$payment_array = array(
				'currency_symbol'   => '$',
				'currency_pos'      => 'left',
				'thousand_sep'      => ',',
				'decimal_sep'       => '.',
				'decimals'          => '2',
				'tax'               => '10',
				'tax_name'          => 'Tax',
				'payment_page'      => $payment_id,
			);

			update_option('sliced_payments', $payment_array);

		}

		if( ! $invoices_exists) {

			$invoice_array = array(
				'terms'         => 'Payment is due within 30 days from date of invoice. Late payment is subject to fees of 5% per month.',
				'css'           => 'body {}',
				'number'        => '0001',
				'prefix'        => 'INV-',
				'increment'     => 'on',
				'template'      => 'template1',
			);

			update_option('sliced_invoices', $invoice_array);

		}

		if( ! $quotes_exists) {

			$quote_array = array(
				'terms'         => 'This is a fixed price quote. If accepted, we require a 25% deposit upfront before work commences.',
				'css'               => 'body {}',
				'number'            => '0001',
				'prefix'            => 'QUO-',
				'increment'         => 'on',
				'template'          => 'template1',
				'accept_quote'      => 'on',
				'accept_quote_text' => sprintf( __( '**Please Note: After accepting this %1s an %2s will be automatically generated. This will then become a legally binding contract.', 'sliced-invoices' ), 'Quote', 'Invoice' ),
			);

			update_option('sliced_quotes', $quote_array);

		}

		// if a new install
		if( ! $email ) {

			$email['from'] = get_option( 'admin_email' );
			$email['name'] = get_option( 'blogname' );
			$email['bcc'] = 'on';
			$email['footer'] = sprintf( 'Copyright %1s. %2s', date('Y'), get_bloginfo('site_name') );

			$email['quote_available_subject'] = 'New quote %number% available';
			$email['invoice_available_subject'] = 'New invoice %number% available';
			$email['payment_received_client_subject'] = 'Thanks for your payment!';
			$email['payment_reminder_subject'] = 'A friendly reminder';

			$email['quote_available_content'] = 'Hi %client_first_name%,

							You have a new quote available ( %number% ) which can be viewed at %link%.<br>';
			$email['invoice_available_content'] = 'Hi %client_first_name%,

							You have a new invoice available ( %number% ) which can be viewed at %link%.<br>';
			$email['payment_received_client_content'] = 'Thanks for your payment, %client_first_name%.

Your recent payment for %total% on invoice %number% has been successful.<br>';
			$email['payment_reminder_content'] = 'Hi %client_first_name%,

Just a friendly reminder that your invoice %number% for %total% %is_was% due on %due_date%.';

			$email['quote_available_button'] = 'View this quote online';
			$email['invoice_available_button'] = 'View this invoice online';

			update_option('sliced_emails', $email);

		}

		// call the custom posts and taxonomnies
		//$admin = new Sliced_Admin( $plugin_name, SLICED_VERSION );
		//$admin->new_cpt_quote();
		//$admin->new_cpt_invoice();
		//$admin->new_taxonomy_quote_status();
		//$admin->new_taxonomy_invoice_status();
		
		/**
		 * for WP Multisite compatibility, do this instead:
		 */
		
		// register CPT sliced_quote
		$opts       = array();
		$cap_type 	= 'post';
		$plural 	= 'Quotes';
		$single 	= 'Quote';
		$cpt_name 	= 'sliced_quote';
		$opts['can_export']								= TRUE;
		$opts['capability_type']						= $cap_type;
		$opts['description']							= '';
		$opts['exclude_from_search']					= TRUE;
		$opts['has_archive']							= FALSE;
		$opts['hierarchical']							= TRUE;
		$opts['map_meta_cap']							= TRUE;
		$opts['menu_icon']								= 'dashicons-sliced';
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
		$opts['labels']['parent_item_colon']			= __( "Parent {$plural} :", 'sliced-invoices' );
		$opts['labels']['search_items']					= __( "Search {$plural}", 'sliced-invoices' );
		$opts['labels']['singular_name']				= __( $single, 'sliced-invoices' );
		$opts['labels']['view_item']					= __( "View {$single}", 'sliced-invoices' );
		$opts['rewrite']['slug']						= FALSE;
		$opts['rewrite']['with_front']					= FALSE;
		$opts['rewrite']['feeds']						= FALSE;
		$opts['rewrite']['pages']						= FALSE;
		register_post_type( 'sliced_quote', $opts );
		
		// register CPT sliced_invoice
		$opts       = array();
		$cap_type 	= 'post';
		$plural 	= 'Invoices';
		$single 	= 'Invoice';
		$cpt_name 	= 'sliced_invoice';
		$opts['can_export']								= TRUE;
		$opts['capability_type']						= $cap_type;
		$opts['description']							= '';
		$opts['exclude_from_search']					= TRUE;
		$opts['has_archive']							= FALSE;
		$opts['hierarchical']							= TRUE;
		$opts['map_meta_cap']							= TRUE;
		$opts['menu_icon']								= 'dashicons-sliced';
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
		$opts['labels']['parent_item_colon']			= __( "Parent {$plural} :", 'sliced-invoices' );
		$opts['labels']['search_items']					= __( "Search {$plural}", 'sliced-invoices' );
		$opts['labels']['singular_name']				= __( $single, 'sliced-invoices' );
		$opts['labels']['view_item']					= __( "View {$single}", 'sliced-invoices' );
		$opts['rewrite']['slug']						= FALSE;
		$opts['rewrite']['with_front']					= FALSE;
		$opts['rewrite']['feeds']						= FALSE;
		$opts['rewrite']['pages']						= FALSE;
		register_post_type( 'sliced_invoice', $opts );
		
		// register taxonomy quote_status
		$opts       = array();
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
		register_taxonomy( $tax_name, 'sliced_quote', $opts );
		
		// Register taxonomy invoice_status
		$opts       = array();
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
		register_taxonomy( $tax_name, 'sliced_invoice', $opts );
		

		$quote_status = array(
			'quote_status' => array(
				'Draft',
				'Sent',
				'Declined',
				'Cancelled',
			)
		);

		foreach ($quote_status as $taxonomy => $terms) {
			foreach ($terms as $term) {
				if (! get_term_by('slug', sanitize_title($term), $taxonomy)) {
					$result = wp_insert_term($term, $taxonomy);
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
				}
			}
		}

		flush_rewrite_rules();

	}


}
