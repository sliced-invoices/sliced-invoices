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

		global $pagenow;
		
		add_action( 'cmb2_admin_init', array( $this, 'quote_side' ) );
		add_action( 'cmb2_admin_init', array( $this, 'invoice_side' ) );
		add_action( 'cmb2_admin_init', array( $this, 'main_section' ) );
		add_action( 'cmb2_admin_init', array( $this, 'users_new' ) );
		add_action( 'cmb2_admin_init', array( $this, 'users_existing' ) );
		add_action( 'do_meta_boxes', array( $this, 'metabox_order' ), 10, 3 );

		if( $pagenow == 'edit.php' || ( $pagenow == 'post.php' && ( sliced_get_the_type() === 'invoice' || sliced_get_the_type() === 'quote' ) ) ) {
			add_action( 'post_submitbox_misc_actions', array( $this, 'add_to_publish_box' ) );
		}
		
		// allow translating of status taxonomies
		add_filter( 'get_terms', array( $this, 'pre_get_terms' ), 10, 4 );

	}
	
	
	/**
	 * Only return default value if we don't have a post ID (in the 'post' query variable)
	 *
	 * @param  bool  $default On/Off (true/false)
	 * @return mixed          Returns true or '', the blank default
	 */
	public static function cmb2_set_checkbox_default_for_new_post( $default ) {
		return isset( $_GET['post'] ) ? '' : ( $default ? (string) $default : '' );
	}
	
	public static function collapsible_group_before( $field_args, $field ) {
		?>
		<table class="widefat sliced-collapsible-group-wrapper">
			<tr class="sliced-collapsible-group-header">
				<th class="row-title"><span><?php echo $field_args['collapsible_group_title']; ?></span></th>
				<th class="row-toggle"><span class="dashicons dashicons-arrow-down sliced-collapsible-group-settings-toggle"></span></th>
			</tr>
			<tr class="sliced-collapsible-group-settings" style="display:none;">
				<td colspan="2">
		<?php
	}
	
	public static function collapsible_group_after() {
		?>
				</td>
			</tr>
		</table>
		<?php
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
		
		do_action( 'sliced_after_description', $description );
		

		$line_items = new_cmb2_box( array(
			'id'           => $prefix . 'line_items',
			'title'        => __( 'Line Items', 'sliced-invoices' ),
			'object_types' => array( 'sliced_quote', 'sliced_invoice' ),
		) );

		$line_items_group_id = $line_items->add_field( array(
			'id'          => $prefix . 'items',
			'type'        => 'group',
			'options'     => array(
				'group_title'   => __( 'Item {#}', 'sliced-invoices' ), // {#} gets replaced by row number
				'add_button'    => __( 'Add Another Item', 'sliced-invoices' ),
				'remove_button' => __( 'Remove Item', 'sliced-invoices' ),
				'sortable'      => true, // beta
			),
		) );

		$line_items->add_group_field( $line_items_group_id, array(
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
		
		$line_items->add_group_field( $line_items_group_id, array(
			'name' => __( 'Item Title', 'sliced-invoices' ),
			'id'   => 'title',
			'type' => 'text',
		) );

		//if ( sliced_hide_adjust_field() === false ) {
			$line_items->add_group_field( $line_items_group_id, array(
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

		$line_items->add_group_field( $line_items_group_id, array(
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

		$line_items->add_group_field( $line_items_group_id, array(
			'name'        => __( 'Description', 'sliced-invoices' ),
			'id'          => 'description',
			'type'        => 'textarea_small',
			'attributes'  => array(
				'placeholder' => __( 'Brief description of the work carried out for this line item (optional)', 'sliced-invoices' ),
				'cols' => 80,
			),
			
		) );
		
		$line_items->add_group_field( $line_items_group_id, array(
			'name'        => __( 'Taxable', 'sliced-invoices' ),
			'id'          => 'taxable',
			'type'        => 'checkbox',
			'default'     => $this->cmb2_set_checkbox_default_for_new_post( true ),
			'attributes'  => array(
				'class'       => 'item_taxable',
			),
		) );

		do_action( 'sliced_after_line_items', $line_items_group_id, $line_items );
		
		
		$line_items->add_group_field( $line_items_group_id, array(
			'name'          => 'pre_defined_items',
			'id'            => 'pre_defined_items',
			'type'          => 'text',
			'render_row_cb' => 'sliced_get_pre_defined_items',
		) );

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
		$line_items->add_field( array(
			'id'         => 'sliced_invoice_discount',
			'type'       => 'hidden',
			'attributes' => array(
				'class' => 'sliced_discount_value',
				'step'  => 'any',
			),
		) );
		
		do_action( 'sliced_after_line_items_totals', $line_items_group_id, $line_items );
		
		
		$payments = new_cmb2_box( array(
			'id'           => $prefix . 'payments',
			'title'        => __( 'Payments', 'sliced-invoices' ),
			'object_types' => array( 'sliced_invoice' ),
			'closed'       => true,
		) );
		
		$payments_group_id = $payments->add_field( array(
			'id'          => $prefix . 'payment',
			'type'        => 'group',
			'options'     => array(
				'group_title'   => __( 'Payment {#}', 'sliced-invoices' ), // {#} gets replaced by row number
				'add_button'    => __( 'Add Another Payment', 'sliced-invoices' ),
				'remove_button' => __( 'Remove Payment', 'sliced-invoices' ),
				'sortable'      => true, // beta
			),
		) );

		$payments->add_group_field( $payments_group_id, array(
			'name'       => __( 'Date', 'sliced-invoices' ),
			'id'         => 'date',
			'type'        => 'text_date_timestamp',
			'date_format' => 'Y-m-d',
			//'default'     => array( 'Sliced_Shared', 'get_todays_date_iso8601' ),
			'attributes'  => array(
				//'required'  => 'required',
			),
		) );
		
		$payments->add_group_field( $payments_group_id, array(
			'name'            => sprintf( __( 'Amount (%s)', 'sliced-invoices' ), sliced_get_currency_symbol() ),
			'id'              => 'amount',
			'type'            => 'text_money',
			'sanitization_cb' => array( $this, 'money_sanitization' ),
			'before_field'    => ' ',
			//'after_field'     => '<span class="line_total_wrap"><span class="line_total">0.00</span></span>',
			'attributes'      => array(
				//'placeholder' => '0.00',
				'maxlength'   => '10',
				'class'       => 'payment_amount',
				//'required'    => 'required',
			),
		) );
		
		$payment_methods = array_merge( array( '' => '' ), sliced_get_accepted_payment_methods() );
		$payments->add_group_field( $payments_group_id, array(
			'name'       => __( 'Payment Method', 'sliced-invoices' ),
			'id'         => 'gateway',
			'type'       => 'select',
			'options'    => $payment_methods,
		) );
		
		$payments->add_group_field( $payments_group_id, array(
			'name'       => __( 'Payment ID', 'sliced-invoices' ),
			'id'         => 'payment_id',
			'type'       => 'text',
		) );
		
		$payment_statuses = array_merge( array( '' => '' ), Sliced_Shared::get_payment_statuses() );
		$payments->add_group_field( $payments_group_id, array(
			'name'       => __( 'Status', 'sliced-invoices' ),
			'id'         => 'status',
			'type'       => 'select',
			'options'    => $payment_statuses,
			'attributes'      => array(
				'class'       => 'payment_status',
			),
		) );
		
		$payments->add_group_field( $payments_group_id, array(
			'name'        => __( 'Memo', 'sliced-invoices' ),
			'id'          => 'memo',
			'type'        => 'textarea_small',
			'attributes'  => array(
				'cols' => 140,
			),
		) );
		
		$payments->add_group_field( $payments_group_id, array(
			'name' => 'currency',
			'id'   => 'currency',
			'type' => 'hidden',
		) );
		
		$payments->add_group_field( $payments_group_id, array(
			'name' => 'extra_data',
			'id'   => 'extra_data',
			'type' => 'hidden',
		) );
		
		do_action( 'sliced_after_payments', $payments_group_id, $payments );
		

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
			 'default'       => $this->get_quote_terms(),
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
		
		do_action( 'sliced_after_quote_terms', $quote_terms );


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
			'default' => $this->get_invoice_terms(),
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
		
		do_action( 'sliced_after_invoice_terms', $invoice_terms );

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
				'title'        => sprintf( __( '%s History', 'sliced-invoices' ), sliced_get_quote_label() ),
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
			'name'       => __( 'Client', 'sliced-invoices' ) . '<a href="#TB_inline?width=960&height=700&inlineId=add-ajax-user" title="Add New Client" class="thickbox button button-small pull-right">' . __( 'Add New Client', 'sliced-invoices' ) . '</a> <a href="#TB_inline?width=960&height=650&inlineId=sliced-ajax-update-client" title="Edit Client" class="thickbox button button-small pull-right">' . __( 'Edit Client', 'sliced-invoices' ) . '</a>',
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
			'default' => $this->get_quote_prefix(),
		) );
		$info->add_field( array(
			'name'    => sprintf( __( '%s Number', 'sliced-invoices' ), '<span class="i18n-multilingual-display">'.sliced_get_quote_label(),'</span>' ),
			'id'      => $prefix . 'quote_number',
			'type'    => 'text',
			'default' => sliced_get_next_quote_number(),
			'before'  => array( $this, 'quote_prefix' ),
			'after'   => array( $this, 'after_quote_number' ),
		) );
		$info->add_field( array(
			'id'      => $prefix . 'quote_suffix',
			'type'    => 'hidden',
			'default' => $this->get_quote_suffix(),
		) );
		$info->add_field( array(
			'name'        => __( 'Created Date', 'sliced-invoices' ),
			'desc'        => '',
			'id'          => $prefix . 'quote_created',
			'type'        => 'text_date_timestamp',
			'date_format' => 'Y-m-d',
			'default'     => Sliced_Shared::get_todays_date_iso8601(),
			'attributes'  => array(
				'data-datepicker' => json_encode( array(
					'closeText'       => __( 'Close', 'sliced-invoices' ),
					'currentText'     => __( 'Today', 'sliced-invoices' ),
					'showButtonPanel' => true,
				) ),
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
			'default'     => Sliced_Quote::get_auto_valid_until_date(),
			'attributes'  => array(
				'data-datepicker' => json_encode( array(
					'closeText'       => __( 'Close', 'sliced-invoices' ),
					'currentText'     => __( 'Today', 'sliced-invoices' ),
					'showButtonPanel' => true,
				) ),
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
			'before_row' => array( $this, 'collapsible_group_before' ),
			'collapsible_group_title' => __( 'Payment Settings', 'sliced-invoices' ),
		) );
		$info->add_field( array(
			'name'       => __( 'Currency Symbol', 'sliced-invoices' ),
			'desc'       => '',
			'id'         => $prefix . 'currency_symbol',
			'type'       => 'text',
			'default'    => sliced_get_currency_symbol(),
			'attributes' => array(
				'placeholder'   => '$',
			),
			'after_row' => array( $this, 'collapsible_group_after' ),
		) );
		$info->add_field( array(
			'name'       => __( 'Prices entered with tax', 'sliced-invoices' ),
			'desc'       => '',
			'id'         => $prefix . 'tax_calc_method',
			'type'       => 'select',
			'default'    => Sliced_Shared::get_tax_calc_method(),
			'options'    => array(
				'inclusive' => __( 'Yes, I will enter prices inclusive of tax', 'sliced-invoices' ),
				'exclusive' => __( 'No, I will enter prices exclusive of tax', 'sliced-invoices' ) . ' ' . __( '(default)', 'sliced-invoices' ),
			),
			'before_row' => array( $this, 'collapsible_group_before' ),
			'collapsible_group_title' => __( 'Tax Settings', 'sliced-invoices' ),
		) );
		$info->add_field( array(
			'name'       => __( 'Tax Rate (%)', 'sliced-invoices' ),
			'desc'       => '',
			'id'         => $prefix . 'tax',
			'type'       => 'text',
			'default'    => sliced_get_tax_amount_formatted(),
			'attributes' => array(
				'placeholder' => '10',
				'maxlength'   => '6',
				//'type'      => 'number',
				//'step'      => 'any',
			),
			'after_row' => array( $this, 'collapsible_group_after' ),
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
		$payment_methods = sliced_get_accepted_payment_methods() ? '' : __( '<a target="_blank" class="button" href="' . esc_url( admin_url( 'admin.php?page=sliced_invoices_settings&tab=payments' ) ) . '">Enable Payment Method</a>', 'sliced-invoices' );

		if ( isset( $_GET['post'] ) ) :

			$this->initiate_logs();

			$notes = new_cmb2_box( array(
				'id'           => $prefix . 'invoice_notes',
				'title'        => sprintf( __( '%s History', 'sliced-invoices' ), sliced_get_invoice_label() ),
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
			'name'       => __( 'Client', 'sliced-invoices' ) . '<a href="#TB_inline?width=960&height=700&inlineId=add-ajax-user" title="Add New Client" class="thickbox button button-small pull-right">' . __( 'Add New Client', 'sliced-invoices' ) . '</a> <a href="#TB_inline?width=960&height=650&inlineId=sliced-ajax-update-client" title="Edit Client" class="thickbox button button-small pull-right">' . __( 'Edit Client', 'sliced-invoices' ) . '</a>',
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
			'default' => $this->get_invoice_prefix(),
		) );
		$info->add_field( array(
			'name'    => sprintf( __( '%s Number', 'sliced-invoices' ), '<span class="i18n-multilingual-display">'.sliced_get_invoice_label().'</span>' ),
			'id'      => $prefix . 'invoice_number',
			'type'    => 'text',
			'default' => sliced_get_next_invoice_number(),
			'before'  => array( $this, 'invoice_prefix' ),
			'after'   => array( $this, 'after_invoice_number' ),
		) );
		$info->add_field( array(
			'id'      => $prefix . 'invoice_suffix',
			'type'    => 'hidden',
			'default' => $this->get_invoice_suffix(),
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
			'default'     => Sliced_Shared::get_todays_date_iso8601(),
			'attributes'  => array(
				'data-datepicker' => json_encode( array(
					'closeText'       => __( 'Close', 'sliced-invoices' ),
					'currentText'     => __( 'Today', 'sliced-invoices' ),
					'showButtonPanel' => true,
				) ),
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
			'default'     => Sliced_Invoice::get_auto_due_date(),
			'attributes'  => array(
				'data-datepicker' => json_encode( array(
					'closeText'       => __( 'Close', 'sliced-invoices' ),
					'currentText'     => __( 'Today', 'sliced-invoices' ),
					'showButtonPanel' => true,
				) ),
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
			'before_row' => array( $this, 'collapsible_group_before' ),
			'collapsible_group_title' => __( 'Payment Settings', 'sliced-invoices' ),
		) );
		$info->add_field( array(
			'name'       => __( 'Currency Symbol', 'sliced-invoices' ),
			'desc'       => '',
			'id'         => $prefix . 'currency_symbol',
			'type'       => 'text',
			'default'    => sliced_get_currency_symbol(),
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
			'default'           => $this->accepted_payment_method_keys(),
			'options_cb'        => 'sliced_get_accepted_payment_methods',
			'after_row' => array( $this, 'collapsible_group_after' ),
		) );
		$info->add_field( array(
			'name'       => __( 'Prices entered with tax', 'sliced-invoices' ),
			'desc'       => '',
			'id'         => $prefix . 'tax_calc_method',
			'type'       => 'select',
			'default'    => Sliced_Shared::get_tax_calc_method(),
			'options'    => array(
				'inclusive' => __( 'Yes, I will enter prices inclusive of tax', 'sliced-invoices' ),
				'exclusive' => __( 'No, I will enter prices exclusive of tax', 'sliced-invoices' ) . ' ' . __( '(default)', 'sliced-invoices' ),
			),
			'before_row' => array( $this, 'collapsible_group_before' ),
			'collapsible_group_title' => __( 'Tax Settings', 'sliced-invoices' ),
		) );
		$info->add_field( array(
			'name'       => __( 'Tax Rate (%)', 'sliced-invoices' ),
			'desc'       => '',
			'id'         => $prefix . 'tax',
			'type'       => 'text',
			'default'    => sliced_get_tax_amount_formatted(),
			'attributes' => array(
				'placeholder'   => '10',
				'maxlength'     => '6',
				//'type'          => 'number',
				//'step'          => 'any',
			),
			'after_row' => array( $this, 'collapsible_group_after' ),
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
	 * Add buttons in WP Publish metabox.
	 *
	 * @since   3.4.1
	 */
	public function add_to_publish_box() {
		
		if ( get_post_status() === 'publish' ):
		?>
		<div class="misc-pub-section sliced-publish-box-buttons">
			<span class="sliced-publish-box-label dashicons-before dashicons-sliced"> Sliced Invoices: </span>
			<?php
			$button = '<a target="_blank" title="' . __( 'View or download as a PDF (extension required)', 'sliced-invoices' ) . '" class="button ui-tip sliced-pdf-button" href="https://slicedinvoices.com/extensions/pdf-email?utm_campaign=Free&utm_medium=link&utm_source=plugin&utm_content=pdf-button"><span class="dashicons dashicons-media-default"></span></a>';
			$button = apply_filters( 'sliced_actions_column', $button );
			echo $button;
			?>
		</div>
		<?php
		endif;
		
	}
	
	
	/**
	 * Ensure Sliced Invoices metaboxes are added in the correct order
	 *
	 * @since   3.7.0
	 */
	public function metabox_order( $screen, $context, $object ) {
		
		global $wp_meta_boxes;
		$sliced_post_types = array( 'sliced_quote', 'sliced_invoice' );
		$protected_metaboxes = array( 'submitdiv', 'pageparentdiv', 'commentsdiv', 'commentstatusdiv', 'quote_statusdiv', 'invoice_statusdiv', 'slugdiv' );
		
		if ( ! is_array( $wp_meta_boxes ) ) {
			return;
		}
		if ( ! in_array( $screen, $sliced_post_types ) ) {
			return;
		}
		
		foreach ( $sliced_post_types as $sliced_post_type ) {
			if ( isset( $wp_meta_boxes[ $sliced_post_type ] ) ) {
				$new_array = array();
				foreach ( $wp_meta_boxes[ $sliced_post_type ] as $context => $priorities ) {
					foreach ( $priorities as $priority => $metaboxes ) {
						foreach ( $metaboxes as $id => $metabox ) {
							if (
								in_array( $id, $protected_metaboxes ) ||
								substr( $id, 0, 6 ) === 'sliced' ||
								substr( $id, 0, 7 ) === '_sliced'
							) {
								// add all core and sliced metaboxes first, as is
								$new_array[$context][$priority][$id] = $metabox;
							} else {
								// add all the non-sliced metaboxes second
								if ( $context === 'side' ) {
									$new_array[$context]['low'][$id] = $metabox;
								} elseif ( $context === 'normal' ) {
									$new_array['advanced']['high'][$id] = $metabox;
								} else {
									$new_array['advanced']['low'][$id] = $metabox;
								}
							}
						}
					}
				}
				$wp_meta_boxes[ $sliced_post_type ] = $new_array;
			}
		}
	}
	
	
	/**
	 * Translate statuses on quote/invoice edit pages (for CMB2)
	 * see also Sliced_Columns::pre_get_the_terms()
	 *
	 * @since   3.7.3
	 */
	public function pre_get_terms( $terms, $taxonomies, $args, $term_query ) {
		
		if ( ! is_array( $taxonomies ) ) {
			// $taxonomies should ALWAYS be an array, but apparently some
			// shitty plugins out there fuck with $taxonomies so now we have
			// to check it first
			return $terms;
		}
		
		if ( in_array( 'invoice_status', $taxonomies ) || in_array( 'quote_status', $taxonomies ) ) {
		
			$translate = get_option( 'sliced_translate' );
			
			foreach ( $terms as &$term ) {
				if ( ! is_object( $term ) ) {
					continue;
				}
				$term->name = ( isset( $translate[$term->slug] ) && class_exists( 'Sliced_Translate' ) ) ? $translate[$term->slug] : __( ucfirst( $term->name ), 'sliced-invoices' );
			}
			
		}
		
		return $terms;
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
	
		$type = Sliced_Shared::get_type();
		if ( ! $type ) {
			return;
		}

		$output = '<div class="alignright sliced_totals">';
		
		$part = apply_filters( 'sliced_admin_display_totals_header', ''
					. '<h3>' . sprintf( __( '%s Totals', 'sliced-invoices' ), esc_html( sliced_get_label() ) ) .'</h3>' );
		
		$output .= $part;
		
		$part = apply_filters( 'sliced_admin_display_totals_subtotal', ''
					. '<div class="sub">' . __( 'Sub Total', 'sliced-invoices' ) . ' <span class="alignright"><span id="sliced_sub_total">0.00</span></span></div>' );
		
		$output .= $part;
		
		$part = apply_filters( 'sliced_admin_display_totals_tax', ''
					. '<div class="tax">' . sliced_get_tax_name() . ' <span class="alignright"><span id="sliced_tax">0.00</span></span></div>' );
		
		$output .= $part;
		
		// 2017-10-20 filter 'sliced_admin_display_totals_after_tax' will be removed in the near future
		// it is currently used by the following extension: Additional Tax v1.1.0,
		$output = apply_filters( 'sliced_admin_display_totals_after_tax', $output );
		
		$part = apply_filters( 'sliced_admin_display_totals_discounts', '
					<div class="discounts">' . __( 'Discount', 'sliced-invoices' ) .'
						<a id="sliced-totals-discounts-edit" href="#"><small>' . __( 'edit', 'sliced-invoices' ) . '</small></a>
						<span class="discount-adder hidden">
							<button type="button" class="button">' . __( 'Apply', 'sliced-invoices' ) . '</button>
						</span>
						<span class="alignright"><span id="sliced_discounts">0.00</span></span>
					</div>' );
		
		$output .= $part;
		
		if ( $type === 'invoice' ) {
			$part = apply_filters( 'sliced_admin_display_totals_payments', ''
						. '<div class="payments">' . __( 'Paid', 'sliced-invoices' )
						. ' <a id="sliced-totals-payments-edit" href="#"><small>' . __( 'edit', 'sliced-invoices' ) . '</small></a>'
						. ' <span class="alignright"><span id="sliced_payments">0.00</span></span></div>' );
			
			$output .= $part;
		}
		
		$part = apply_filters( 'sliced_admin_display_totals_total', ''
					. '<div class="total">' . __( 'Total Due', 'sliced-invoices' ) . ' <span class="alignright"><span id="sliced_total">0.00</span></span></div>' );
		
		$output .= $part;
		
		$output .= '</div>';

		// 2017-10-15 filter 'sliced_get_line_item_totals' may be removed in the near future
		// it is currently used by the following extensions: Additional Tax (<=1.0.0), Discounts and Partial Payments
		$output = apply_filters( 'sliced_get_line_item_totals', $output );
		
		// 2017-10-15 filter 'sliced_display_the_line_totals' may be renamed in the near future
		// it is currently used by the following extensions: Woo Invoices, Deposit Invoices
		// proposed new name: 'sliced_admin_display_totals'
		return apply_filters( 'sliced_display_the_line_totals', $output );
		
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

	public function get_quote_suffix() {
		return sliced_get_quote_suffix();
	}

	public function get_invoice_prefix() {
		return sliced_get_invoice_prefix();
	}

	public function get_invoice_suffix() {
		return sliced_get_invoice_suffix();
	}

	public function quote_prefix() {
		return '<span class="prefix">' . sliced_get_quote_prefix() . '</span>';
	}

	public function quote_suffix() {
		return '<span class="suffix">' . sliced_get_quote_suffix() . '</span>';
	}

	public function invoice_prefix() {
		return '<span class="prefix">' . sliced_get_invoice_prefix() . '</span>';
	}

	public function invoice_suffix() {
		return '<span class="suffix">' . sliced_get_invoice_suffix() . '</span>';
	}
	
	public function is_duplicate_quote_number() {
		if ( isset( $_GET['post'] ) && Sliced_Quote::is_duplicate_quote_number( (int) $_GET['post'] ) ) {
			return '<span class="warning">' . __( 'Warning: duplicate quote number', 'sliced-invoices' ) . '</span>';
		}
	}
	
	public function is_duplicate_invoice_number() {
		if ( isset( $_GET['post'] ) && Sliced_Invoice::is_duplicate_invoice_number( (int) $_GET['post'] ) ) {
			return '<span class="warning">' . __( 'Warning: duplicate invoice number', 'sliced-invoices' ) . '</span>';
		}
	}
	
	public function after_quote_number() {
		$output = $this->quote_suffix() . $this->is_duplicate_quote_number();
		return $output;
	}
	
	public function after_invoice_number() {
		$output = $this->invoice_suffix() . $this->is_duplicate_invoice_number();
		return $output;
	}

}
