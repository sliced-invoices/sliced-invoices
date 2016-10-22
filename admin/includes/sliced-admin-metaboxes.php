<?php

// Exit if accessed directly
if ( ! defined('ABSPATH') ) { exit; }


/**
 * Calls the class.
 */
function sliced_call_metaboxes_class() {
	// just a double check
	if( ! is_admin() )
			return;
	new Sliced_Metaboxes();
}
add_action('sliced_loaded', 'sliced_call_metaboxes_class');


/**
 * The Class.
 */
class Sliced_Metaboxes {

	/**
	 * Sliced_Logs
	 *
	 * @var Sliced_Logs
	 */
	protected $logs;

	/**
	 * Hook into the appropriate actions when the class is constructed.
	 */
	public function __construct() {

		add_action( 'cmb2_admin_init', array( $this, 'quote_side' ) );
		add_action( 'cmb2_admin_init', array( $this, 'invoice_side' ) );
		add_action( 'cmb2_admin_init', array( $this, 'main_section' ) );
		add_action( 'cmb2_admin_init', array( $this, 'users_new' ) );
		add_action( 'cmb2_admin_init', array( $this, 'users_existing' ) );

	}

	/**
	 * Main section metaboxes
	 *
	 * @since   2.0.0
	 */
	public function main_section() {

		// Start with an underscore to hide fields from custom fields list
		$prefix = '_sliced_';

		$description = new_cmb2_box( array(
			'id'           => $prefix . 'the_description',
			'title'        => __( 'Description', 'sliced-invoices' ),
			'object_types' => array( 'sliced_quote', 'sliced_invoice' ),
			'context'      => 'normal',
			'priority'     => 'high',
		) );
		$description->add_field( array(
			'name'    => '',
			'id'      => $prefix . 'description',
			'type'    => 'wysiwyg',
			'options' => array(
				'wpautop' => true, // use wpautop?
				'media_buttons' => true, // show insert/upload button(s)
				'textarea_rows' => get_option('default_post_edit_rows', 5), // rows="..."
				'teeny' => true, // output the minimal editor config used in Press This
				'dfw' => false, // replace the default fullscreen with DFW (needs specific css)
				'tinymce' => true, // load TinyMCE, can be used to pass settings directly to TinyMCE using an array()
				'quicktags' => true // load Quicktags, can be used to pass settings directly to Quicktags using an array()
			),
		) );

		$line_items = new_cmb2_box( array(
			'id'           => $prefix . 'line_items',
			'title'        => __( 'Line Items', 'sliced-invoices' ),
			'object_types' => array( 'sliced_quote', 'sliced_invoice' ),
		) );

		$group_field_id = $line_items->add_field( array(
			'id'          => $prefix . 'items',
			'type'        => 'group',
			'options'     => array(
				'group_title'   => __( 'Item {#}', 'sliced-invoices' ), // {#} gets replaced by row number
				'add_button'    => __( 'Add Another Item', 'sliced-invoices' ),
				'remove_button' => __( 'Remove Item', 'sliced-invoices' ),
				'sortable'      => true, // beta
			),
		) );

		$line_items->add_group_field( $group_field_id, array(
			'name'       => __( 'Qty', 'sliced-invoices' ),
			'id'         => 'qty',
			'type'       => 'text_small',
			'attributes' => array(
				'placeholder' => '1',
				'maxlength'   => '8',
				'class'       => 'item_qty',
				//'type'      => 'number',
				'required'    => 'required',
				'step'        => 'any',
				'min'         => '0'
			),
		) );
		$line_items->add_group_field( $group_field_id, array(
			'name' => __( 'Item Title', 'sliced-invoices' ),
			'id'   => 'title',
			'type' => 'text',
		) );

		//if ( sliced_hide_adjust_field() === false ) {
			$line_items->add_group_field( $group_field_id, array(
				'name'       => __( 'Adjust (%)', 'sliced-invoices' ),
				'id'         => 'tax',
				'type'       => 'text_small',
				'attributes' => array(
					'placeholder' => '0',
					'maxlength'   => '5',
					'class'       => 'item_tax',
					//'type'      => 'number',
					'step'        => 'any',
				),
			) );
		//}

		$line_items->add_group_field( $group_field_id, array(
			'name'            => '<span class="pull-left">' . sprintf( __( 'Rate (%s)', 'sliced-invoices' ), sliced_get_currency_symbol() ) . '</span><span class="pull-right">' . sprintf( __( 'Amount (%s)', 'sliced-invoices' ), sliced_get_currency_symbol() ) . '</span>',
			'id'              => 'amount',
			'type'            => 'text_money',
			'sanitization_cb' => array( $this, 'money_sanitization' ),
			'before_field'    => ' ',
			'after_field'     => '<span class="line_total_wrap"><span class="line_total">0.00</span></span>',
			'attributes'      => array(
				'placeholder' => '0.00',
				'maxlength'   => '10',
				'class'       => 'item_amount',
				'required'    => 'required'
			),
		) );

		$line_items->add_group_field( $group_field_id, array(
			'name'        => __( 'Description', 'sliced-invoices' ),
			'id'          => 'description',
			'type'        => 'textarea_small',
			'attributes'  => array(
				'placeholder' => __( 'Brief description of the work carried out for this line item (optional)', 'sliced-invoices' ),
				'cols' => 140,
			),
			'after_row' => apply_filters( 'sliced_after_item_row', sliced_get_pre_defined_items() ),
		) );

		do_action( 'sliced_after_line_items', $group_field_id, $line_items );

		$line_items->add_field( array(
			'name'  => '',
			'desc'  => '',
			'id'    => $prefix . 'calc_total',
			'type'  => 'title',
			'after' => array( $this, 'display_the_line_totals' ),
		) );
		$line_items->add_field( array(
			'id'   => $prefix . 'totals_for_ordering',
			'type' => 'hidden',
		) );

		$quote_terms = new_cmb2_box( array(
			'id'           => $prefix . 'the_quote_terms',
			'title'        => __( 'Terms & Conditions', 'sliced-invoices' ),
			'object_types' => array( 'sliced_quote' ), // Post type
			'context'      => 'normal',
			'priority'     => 'high',
			'closed'       => true,
		) );
		$quote_terms->add_field( array(
			 'name'          => '',
			 'desc'          => '',
			 'id'            => $prefix . 'quote_terms',
			 'default'       => array( $this, 'get_quote_terms' ),
			 'type'          => 'wysiwyg',
			 'options' => array(
				'wpautop' => true, // use wpautop?
				'media_buttons' => false, // show insert/upload button(s)
				'textarea_rows' => get_option('default_post_edit_rows', 5), // rows="..."
				'teeny' => true, // output the minimal editor config used in Press This
				'dfw' => false, // replace the default fullscreen with DFW (needs specific css)
				'tinymce' => true, // load TinyMCE, can be used to pass settings directly to TinyMCE using an array()
				'quicktags' => true // load Quicktags, can be used to pass settings directly to Quicktags using an array()
			),
		) );


		$invoice_terms = new_cmb2_box( array(
			'id'           => $prefix . 'the_invoice_terms',
			'title'        => __( 'Terms & Conditions', 'sliced-invoices' ),
			'object_types' => array( 'sliced_invoice' ), // Post type
			'context'      => 'normal',
			'priority'     => 'high',
			'closed'       => true,
		) );
		$invoice_terms->add_field( array(
			'name'    => '',
			'id'      => $prefix . 'invoice_terms',
			'default' => array( $this, 'get_invoice_terms' ),
			'type'    => 'wysiwyg',
			'options' => array(
				'wpautop' => true, // use wpautop?
				'media_buttons' => false, // show insert/upload button(s)
				'textarea_rows' => get_option('default_post_edit_rows', 5), // rows="..."
				'teeny' => true, // output the minimal editor config used in Press This
				'dfw' => false, // replace the default fullscreen with DFW (needs specific css)
				'tinymce' => true, // load TinyMCE, can be used to pass settings directly to TinyMCE using an array()
				'quicktags' => true // load Quicktags, can be used to pass settings directly to Quicktags using an array()
			),
		) );

	}

	/**
	 * Quote sidebar metaboxes
	 *
	 * @since   2.0.0
	 */
	public function quote_side() {

		// Start with an underscore to hide fields from custom fields list
		$prefix = '_sliced_';

		if ( isset( $_GET['post'] ) ) :

			$info = new_cmb2_box( array(
				'id'           => $prefix . 'convert',
				'title'        => sprintf( __( 'Convert %s', 'sliced-invoices' ), sliced_get_quote_label() ),
				'object_types' => array( 'sliced_quote' ), // Post type
				'context'      => 'side',
				'priority'     => 'default'
			) );
			$info->add_field( array(
				'name'   => '',
				'desc'   => '',
				'id'     => $prefix . 'convert_quote',
				'type'   => 'title',
				'before' => '',
				'after'  => array( 'Sliced_Admin', 'get_convert_invoice_button' ),
			) );

			$this->initiate_logs();

			$info = new_cmb2_box( array(
				'id'           => $prefix . 'quote_notes',
				'title'        => sprintf( __( '%s Notes', 'sliced-invoices' ), sliced_get_quote_label() ),
				'object_types' => array( 'sliced_quote' ), // Post type
				'context'      => 'side',
				'priority'     => 'low'
			) );
			$info->add_field( array(
				'name'  => '',
				'desc'  => '',
				'id'    => $prefix . 'notes',
				'type'  => 'title',
				'after' => array( $this, 'display_logs' ),
			) );

		endif;

		$info = new_cmb2_box( array(
			'id'           => $prefix . 'quote_info',
			'title'        => sprintf( __( '%s Details', 'sliced-invoices' ), sliced_get_quote_label() ),
			'object_types' => array( 'sliced_quote' ), // Post type
			'context'      => 'side',
			'priority'     => 'default'
		) );
		$info->add_field( array(
			'name'       => __( 'Client', 'sliced-invoices' ) . '<a href="#TB_inline?width=960&height=650&inlineId=add-ajax-user" title="Add New Client" class="thickbox button button-small pull-right">' . __( 'Add New Client', 'sliced-invoices' ) . '</a>',
			'desc'       => '',
			'id'         => $prefix . 'client',
			'type'       => 'select',
			'options_cb' => array( 'Sliced_Admin', 'get_clients' ),
			'attributes' => array(
				'required'  => 'required',
			),
		) );
		$info->add_field( array(
			'name'       => __( 'Status', 'sliced-invoices' ),
			'desc'       => '',
			'id'         => $prefix . 'quote_status',
			'type'       => 'taxonomy_select',
			'default'    => 'draft',
			'taxonomy'   => 'quote_status',
			'attributes' => array(
				'required'  => 'required',
			),
		) );
		$info->add_field( array(
			'id'      => $prefix . 'quote_prefix',
			'type'    => 'hidden',
			'default' => array( $this, 'get_quote_prefix' ),
		) );
		$info->add_field( array(
			'name'    => sprintf( __( '%s Number', 'sliced-invoices' ), '<span class="i18n-multilingual-display">'.sliced_get_quote_label(),'</span>' ),
			'id'      => $prefix . 'quote_number',
			'type'    => 'text',
			'default' => 'sliced_get_next_quote_number',
			'before'  => array( $this, 'quote_prefix' )
		) );
		$info->add_field( array(
			'name'        => __( 'Created Date', 'sliced-invoices' ),
			'desc'        => '',
			'id'          => $prefix . 'quote_created',
			'type'        => 'text_date_timestamp',
			'date_format' => 'Y-m-d',
			'default'     => array( 'Sliced_Shared', 'get_todays_date_iso8601' ),
			'attributes'  => array(
				'required'  => 'required',
				'readonly'  => 'readonly',
			),
		) );
		$info->add_field( array(
			'name'        => __( 'Valid Until Date', 'sliced-invoices' ),
			'desc'        => '',
			'id'          => $prefix . 'quote_valid_until',
			'type'        => 'text_date_timestamp',
			'date_format' => 'Y-m-d',
			'default'     => array( 'Sliced_Quote', 'get_auto_valid_until_date' ),
			'attributes'  => array(
				'readonly'  => 'readonly',
			),
		) );
		$info->add_field( array(
			'name'       => __( 'Currency', 'sliced-invoices' ),
			'desc'       => '',
			'id'         => $prefix . 'currency',
			'type'       => 'select',
			'default'    => 'default',
			'options'    => Sliced_Shared::currency_options(),
		) );
		$info->add_field( array(
			'name'       => __( 'Currency Symbol', 'sliced-invoices' ),
			'desc'       => '',
			'id'         => $prefix . 'currency_symbol',
			'type'       => 'text',
			'default'    => 'sliced_get_currency_symbol',
			'attributes' => array(
				'placeholder'   => '$',
			),
		) );
		$info->add_field( array(
			'name'       => __( 'Tax Rate', 'sliced-invoices' ),
			'desc'       => '',
			'id'         => $prefix . 'tax',
			'type'       => 'text',
			'default'    => 'sliced_get_tax_amount_formatted',
			'attributes' => array(
				'placeholder' => '10',
				'maxlength'   => '6',
				//'type'      => 'number',
				//'step'      => 'any',
			),
		) );


	}


	/**
	 * Invoice sidebar metaboxes
	 *
	 * @since   2.0.0
	 */
	public function invoice_side() {

		// Start with an underscore to hide fields from custom fields list
		$prefix = '_sliced_';
		$payment_methods = sliced_get_accepted_payment_methods() ? '' : __( '<a target="_blank" class="button" href="' . esc_url( admin_url( 'admin.php?page=sliced_payments' ) ) . '">Enable Payment Method</a>', 'sliced-invoices' );

		if ( isset( $_GET['post'] ) ) :

			$this->initiate_logs();

			$notes = new_cmb2_box( array(
				'id'           => $prefix . 'invoice_notes',
				'title'        => sprintf( __( '%s Notes', 'sliced-invoices' ), sliced_get_invoice_label() ),
				'object_types' => array( 'sliced_invoice' ), // Post type
				'context'      => 'side',
				'priority'     => 'low'
			) );

			$notes->add_field( array(
				'name'  => '',
				'desc'  => '',
				'id'    => $prefix . 'notes',
				'type'  => 'title',
				'after' => array( $this, 'display_logs' ),
			) );

		endif;

		$info = new_cmb2_box( array(
			'id'           => $prefix . 'invoice_info',
			'title'        => sprintf( __( '%s Details', 'sliced-invoices' ), sliced_get_invoice_label() ),
			'object_types' => array( 'sliced_invoice' ), // Post type
			'context'      => 'side',
			'priority'     => 'default'
		) );

		$info->add_field( array(
			'name'       => __( 'Client', 'sliced-invoices' ) . '<a href="#TB_inline?width=960&height=650&inlineId=add-ajax-user" title="Add New Client" class="thickbox button button-small pull-right">' . __( 'Add New Client', 'sliced-invoices' ) . '</a>',
			'desc'       => '',
			'id'         => $prefix . 'client',
			'type'       => 'select',
			'options_cb' => array( 'Sliced_Admin', 'get_clients' ),
			'attributes' => array(
				'required'  => 'required',
			),
		) );
		$info->add_field( array(
			'name'       => __( 'Status', 'sliced-invoices' ),
			'desc'       => '',
			'id'         => $prefix . 'invoice_status',
			'type'       => 'taxonomy_select',
			'default'    => 'draft',
			'taxonomy'   => 'invoice_status',
			'attributes' => array(
				'required'  => 'required',
			),
		) );
		$info->add_field( array(
			'id'      => $prefix . 'invoice_prefix',
			'type'    => 'hidden',
			'default' => array( $this, 'get_invoice_prefix' ),
		) );
		$info->add_field( array(
			'name'    => sprintf( __( '%s Number', 'sliced-invoices' ), '<span class="i18n-multilingual-display">'.sliced_get_invoice_label().'</span>' ),
			'id'      => $prefix . 'invoice_number',
			'type'    => 'text',
			'default' => 'sliced_get_next_invoice_number',
			'before'  => array( $this, 'invoice_prefix' ),
		) );
		$info->add_field( array(
			'name' => __( 'Order Number', 'sliced-invoices' ),
			'desc' => '',
			'id'   => $prefix . 'order_number',
			'type' => 'text',
		) );
		$info->add_field( array(
			'name'        => __( 'Created Date', 'sliced-invoices' ),
			'desc'        => '',
			'id'          => $prefix . 'invoice_created',
			'type'        => 'text_date_timestamp',
			'date_format' => 'Y-m-d',
			'default'     => array( 'Sliced_Shared', 'get_todays_date_iso8601' ),
			'attributes'  => array(
				'required'  => 'required',
				'readonly'  => 'readonly',
			),
		) );
		$info->add_field( array(
			'name'        => __( 'Due Date', 'sliced-invoices' ),
			'desc'        => '',
			'id'          => $prefix . 'invoice_due',
			'type'        => 'text_date_timestamp',
			'date_format' => 'Y-m-d',
			'default'     => array( 'Sliced_Invoice', 'get_auto_due_date' ),
			'attributes'  => array(
				'readonly'  => 'readonly',
			),
		) );
		$info->add_field( array(
			'name'       => __( 'Currency', 'sliced-invoices' ),
			'desc'       => '',
			'id'         => $prefix . 'currency',
			'type'       => 'select',
			'default'    => 'default',
			'options'    => Sliced_Shared::currency_options(),
		) );
		$info->add_field( array(
			'name'       => __( 'Currency Symbol', 'sliced-invoices' ),
			'desc'       => '',
			'id'         => $prefix . 'currency_symbol',
			'type'       => 'text',
			'default'    => 'sliced_get_currency_symbol',
			'attributes' => array(
				'placeholder'   => '$',
			),
		) );
		$info->add_field( array(
			'name'              => __( 'Payment Methods', 'sliced-invoices' ),
			'desc'              => $payment_methods,
			'id'                => $prefix . 'payment_methods',
			'type'              => 'multicheck',
			'select_all_button' => false,
			'default'           => array( $this, 'accepted_payment_method_keys' ),
			'options_cb'        => 'sliced_get_accepted_payment_methods',
		) );
		$info->add_field( array(
			'name'       => __( 'Tax Rate', 'sliced-invoices' ),
			'desc'       => '',
			'id'         => $prefix . 'tax',
			'type'       => 'text',
			'default'    => 'sliced_get_tax_amount_formatted',
			'attributes' => array(
				'placeholder'   => '10',
				'maxlength'     => '6',
				//'type'          => 'number',
				//'step'          => 'any',
			),
		) );

	}



	/**
	 * User metaboxes.
	 *
	 * @since   2.0.0
	 */
	public function users_new() {

		// Start with an underscore to hide fields from custom fields list
		$prefix = '_sliced_client_';
		/**
		 * Metabox for the user profile screen
		 */
		$cmb_user = new_cmb2_box( array(
			'id'               => $prefix . 'edit',
			'title'            => __( 'Client Information', 'sliced-invoices' ),
			'object_types'     => array( 'user' ), // Tells CMB2 to use user_meta vs post_meta
			'show_names'       => true,
			'new_user_section' => 'add-new-user',// where form will show on new user page. 'add-existing-user' is only other valid option.
		) );
		$cmb_user->add_field( array(
			'name' => __( 'Sliced Invoices Client', 'sliced-invoices' ),
			'desc' => __( 'Add a Business/Client Name below to activate this user as a Client.', 'sliced-invoices' ),
			'id'   => $prefix . 'title',
			'type' => 'title',
		) );
		$cmb_user->add_field( array(
			'name' => __( 'Business/Client Name', 'sliced-invoices' ),
			'desc' => '',
			'id'   => $prefix . 'business',
			'type' => 'text',
		) );
		$cmb_user->add_field( array(
			'name'       => __( 'Address', 'sliced-invoices' ),
			'desc'       => '',
			'id'         => $prefix . 'address',
			'type'       => 'textarea',
			'attributes' => array(
				'rows' => 3
			),
		) );
		$cmb_user->add_field( array(
			'name'       => __( 'Extra Info', 'sliced-invoices' ),
			'desc'       => '',
			'id'         => $prefix . 'extra_info',
			'type'       => 'textarea',
			'attributes' => array(
				'rows' => 3
			),
		) );

	}

	/**
	 * User metaboxes.
	 *
	 * @since   2.0.0
	 */
	public function users_existing() {

		// Start with an underscore to hide fields from custom fields list
		$prefix = '_sliced_client_';
		/**
		 * Metabox for the user profile screen
		 */
		$cmb_user = new_cmb2_box( array(
			'id'               => $prefix . 'edit',
			'title'            => __( 'Client Information', 'sliced-invoices' ),
			'object_types'     => array( 'user' ), // Tells CMB2 to use user_meta vs post_meta
			'show_names'       => true,
			'new_user_section' => 'add-existing-user',// where form will show on new user page. 'add-existing-user' is only other valid option.
		) );
		$cmb_user->add_field( array(
			'name'     => __( 'Sliced Invoices Client', 'sliced-invoices' ),
			'desc'     => '',
			'id'       => $prefix . 'title',
			'type'     => 'title',
		) );
		$cmb_user->add_field( array(
			'name' => __( 'Business/Client Name', 'sliced-invoices' ),
			'desc' => __( 'Adding a Business/Client Name will activate this user as a Client.', 'sliced-invoices' ),
			'id'   => $prefix . 'business',
			'type' => 'text',
		) );
		$cmb_user->add_field( array(
			'name'       => __( 'Address', 'sliced-invoices' ),
			'desc'       => __( 'Enter the address of the client. Format the Address any way you like. HTML is allowed.', 'sliced-invoices' ),
			'id'         => $prefix . 'address',
			'type'       => 'textarea',
			'attributes' => array(
				'rows' => 3
			),
		) );
		$cmb_user->add_field( array(
			'name'       => __( 'Extra Info', 'sliced-invoices' ),
			'desc'       => __( 'Any extra client info such as phone number or business number. HTML is allowed.', 'sliced-invoices' ),
			'id'         => $prefix . 'extra_info',
			'type'       => 'textarea',
			'attributes' => array(
				'rows' => 3
			),
		) );

	}

	/**
	 * Initate the Sliced_Logs object
	 *
	 * @since  2.51
	 *
	 * @return Sliced_Logs
	 */
	protected function initiate_logs() {
		$this->logs = new Sliced_Logs;
	}

	/**
	 * Replace the text_money Sanitization method.
	 *
	 * @since  2.51
	 *
	 * @param  mixed  $meta_value Unsanitized meta value.
	 *
	 * @return mixed              Sanitized meta value.
	 */
	public function money_sanitization( $meta_value ) {
		$sanitized_value = is_array( $meta_value )
			? array_map( 'sanitize_text_field', $meta_value )
			: call_user_func( 'sanitize_text_field', $meta_value );

		return $sanitized_value;
	}

	/**
	 * Display sliced log
	 *
	 * @since  2.51
	 *
	 * @return string  Sliced logs
	 */
	public function display_logs() {
		return $this->logs->display_the_logs( (int) $_GET['post'] );
	}

	public function accepted_payment_method_keys() {
		return array_keys( sliced_get_accepted_payment_methods() );
	}

	public function display_the_line_totals() {
		return apply_filters( 'sliced_display_the_line_totals', sliced_display_the_line_totals() );
	}

	public function get_quote_terms() {
		return sliced_get_quote_terms();
	}

	public function get_invoice_terms() {
		return sliced_get_invoice_terms();
	}

	public function get_quote_prefix() {
		return sliced_get_quote_prefix();
	}

	public function get_invoice_prefix() {
		return sliced_get_invoice_prefix();
	}

	public function quote_prefix() {
		return '<span class="prefix">' . sliced_get_quote_prefix() . '</span>';
	}

	public function invoice_prefix() {
		return '<span class="prefix">' . sliced_get_invoice_prefix() . '</span>';
	}

}
