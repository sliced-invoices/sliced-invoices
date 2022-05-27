<?php
// Exit if accessed directly
if ( ! defined('ABSPATH') ) { exit; }

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @version 3.9.0
 * @since   2.0.0
 */
class Sliced_Activator {


	public static function activate() {

		global $wpdb, $wp_version;

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

		/**
		 * if new install, add default options
		 */
		$business_exists = get_option('sliced_business');
		$general_exists  = get_option('sliced_general');
		$payment_exists  = get_option('sliced_payments');
		$tax_exists      = get_option('sliced_tax');
		$invoices_exists = get_option('sliced_invoices');
		$quotes_exists   = get_option('sliced_quotes');
		$email           = get_option('sliced_emails');

		if( ! $business_exists ) {

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

		if( ! $general_exists ) {

			$general_array = array(
				'year_start'    => '07',
				'year_end'      => '06',
				'pre_defined'   => '
1 | Web Design | 85 | Design work on the website
1 | Web Development | 95 | Back end development of website',
				'db_version'    => SLICED_DB_VERSION,
			);

			update_option('sliced_general', $general_array);

		}

		if( ! $payment_exists ) {

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
				'payment_page'      => $payment_id,
			);

			update_option('sliced_payments', $payment_array);

		}
		
		if( ! $tax_exists ) {

			$tax_array = array(
				'tax_calc_method'   => 'exclusive',
				'tax'               => '10',
				'tax_name'          => 'Tax',
			);

			update_option( 'sliced_tax' , $tax_array );

		}

		if( ! $invoices_exists ) {

			$invoice_array = array(
				'terms'         => 'Payment is due within 30 days from date of invoice. Late payment is subject to fees of 5% per month.',
				'footer'        => 'Thanks for choosing <a href="' . get_bloginfo('url') . '">' . get_bloginfo('site_name') . '</a> | <a href="mailto:' . get_bloginfo('admin_email') . '">' . get_bloginfo('admin_email') . '</a>',
				'css'           => 'body {}',
				'number'        => '0001',
				'prefix'        => 'INV-',
				'increment'     => 'on',
				'template'      => 'template1',
			);

			update_option('sliced_invoices', $invoice_array);

		}

		if( ! $quotes_exists ) {

			$quote_array = array(
				'terms'         => 'This is a fixed price quote. If accepted, we require a 25% deposit upfront before work commences.',
				'footer'        => 'Thanks for choosing <a href="' . get_bloginfo('url') . '">' . get_bloginfo('site_name') . '</a> | <a href="mailto:' . get_bloginfo('admin_email') . '">' . get_bloginfo('admin_email') . '</a>',
				'css'               => 'body {}',
				'number'            => '0001',
				'prefix'            => 'QUO-',
				'increment'         => 'on',
				'template'          => 'template1',
				'accept_quote'      => 'on',
				'accept_quote_text' => sprintf( __( '**Please Note: After accepting this %1s an %2s will be automatically generated. This will then become a legally binding contract.', 'sliced-invoices' ), 'Quote', 'Invoice' ),
				'decline_reason_required' => 'on',
			);

			update_option('sliced_quotes', $quote_array);

		}

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

Your recent payment for %last_payment% on invoice %number% has been successful.<br>';
			$email['payment_reminder_content'] = 'Hi %client_first_name%,

Just a friendly reminder that your invoice %number% for %total% %is_was% due on %due_date%.';

			$email['quote_available_button'] = 'View this quote online';
			$email['invoice_available_button'] = 'View this invoice online';

			update_option('sliced_emails', $email);

		}
		
		/**
		 * everything else
		 */
		
		// register our custom post types & taxonomies
		require_once( plugin_dir_path( __FILE__ ) . '../admin/class-sliced-admin.php' );
		require_once( plugin_dir_path( __FILE__ ) . '../includes/template-tags/sliced-tags-quote.php' );
		require_once( plugin_dir_path( __FILE__ ) . '../includes/template-tags/sliced-tags-invoice.php' );
		Sliced_Admin::new_cpt_quote();
		Sliced_Admin::new_cpt_invoice();
		Sliced_Admin::new_taxonomy_quote_status();
		Sliced_Admin::new_taxonomy_invoice_status();
		Sliced_Admin::new_taxonomy_terms( true );
		
		flush_rewrite_rules();
		
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
		
		// Sliced Recurring Tasks
		if ( ! wp_next_scheduled ( 'sliced_invoices_hourly_tasks' ) ) {
			wp_schedule_event( time(), 'hourly', 'sliced_invoices_hourly_tasks' );
		}
		
		// Done
		do_action( 'sliced_activated' );

	}


}
