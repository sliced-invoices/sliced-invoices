<?php

// Exit if accessed directly
if ( ! defined('ABSPATH') ) { exit; }

/**
 * CMB Tabbed Theme Options
 *
 * @author    Arushad Ahmed <@dash8x, contact@arushad.org>
 * @link      http://arushad.org/how-to-create-a-tabbed-options-page-for-your-wordpress-theme-using-cmb
 * @version   0.1.0
 */
class Sliced_Options {

	/**
	 * Default Option key
	 * @var string
	 */
	private $key = 'sliced_options';

	/**
	 * Array of metaboxes/fields
	 * @var array
	 */
	public $option_metabox = array();

	/**
	 * Options Page title
	 * @var string
	 */
	protected $title = '';

	/**
	 * Options Page title
	 * @var string
	 */
	protected $menu_title = '';

	/**
	 * Options Tab Pages
	 * @var array
	 */
	public $options_pages = array();

	/**
	 * Constructor
	 * @since 0.1.0
	 */
	public function __construct() {
		// Set our title
		$this->menu_title = __( 'Sliced Invoices', 'sliced-invoices' );
		$this->title = __( 'Sliced Invoices', 'sliced-invoices' );
	}

	/**
	 * Initiate our hooks
	 * @since 0.1.0
	 */
	public function hooks() {
		add_action( 'admin_init', array( $this, 'init' ), 999 );
		add_action( 'admin_menu', array( $this, 'add_options_page' ), 999 ); //create tab pages
	}

	/**
	 * Register our setting tabs to WP
	 * @since  0.1.0
	 */
	public function init() {
		$option_tabs = self::option_fields();
		foreach ($option_tabs as $index => $option_tab) {
			register_setting( $option_tab['id'], $option_tab['id'] );
		}

	}

	/**
	 * Add menu options page
	 * @since 0.1.0
	 */
	public function add_options_page() {

		$option_tabs = self::option_fields();
		foreach ($option_tabs as $index => $option_tab) {
			if ( $index == 0) {
				$this->options_pages[] = add_menu_page( $this->title, $this->menu_title, 'manage_options', $option_tab['id'], array( $this, 'admin_page_display' ), 'dashicons-sliced'
				); //Link admin menu to first tab

				add_submenu_page( $option_tabs[0]['id'], $this->menu_title, $option_tab['menu_title'], 'manage_options', $option_tab['id'], array( $this, 'admin_page_display' ) ); //Duplicate menu link for first submenu page
			} else {
				$this->options_pages[] = add_submenu_page( $option_tabs[0]['id'], $this->menu_title, $option_tab['menu_title'], 'manage_options', $option_tab['id'], array( $this, 'admin_page_display' ) );
			}
		}

		// add the extra page
		$plugin_reports = new Sliced_Reports();
		$plugin_tools = new Sliced_Tools();

		$reports_page = add_submenu_page( $option_tabs[0]['id'], 'Reports', 'Reports', 'manage_options', 'sliced_reports', array( $plugin_reports, 'display_reports_page' )  );
		$tools_page = add_submenu_page( $option_tabs[0]['id'], 'Tools', 'Tools', 'manage_options', 'sliced_tools', array( $plugin_tools, 'display_tools_page' )  );

		$this->options_pages[] = $reports_page;
		$this->options_pages[] = $tools_page;

		foreach ( $this->options_pages as $page ) {
			// Include CMB CSS in the head to avoid FOUC
			add_action( "admin_print_styles-{$page}", array( 'CMB2_hookup', 'enqueue_cmb_css' ) );
		}
	}


	/**
	 * Admin page markup. Mostly handled by CMB
	 * @since  0.1.0
	 */
	public function admin_page_display() {

		global $pagenow;

		// check we are on the network settings page
		if( $pagenow != 'admin.php' )
			return;

		$option_tabs = self::option_fields(); //get all option tabs
		$tab_forms = array();

		?>
		<div class="wrap cmb2_sliced_options_page <?php echo esc_attr($this->key); ?>">

			<h2><?php esc_html_e( $this->title, 'sliced-invoices' ) ?></h2>

			<!-- Options Page Nav Tabs -->
			<h2 class="nav-tab-wrapper">
				<?php foreach ($option_tabs as $option_tab) :
					$tab_slug = $option_tab['id'];
					$nav_class = 'nav-tab';
					if ( $tab_slug == $_GET['page'] ) {
						$nav_class .= ' nav-tab-active'; //add active class to current tab
						$tab_forms[] = $option_tab; //add current tab to forms to be rendered
					}
				?>
				<a class="<?php echo esc_attr( $nav_class ); ?>" href="<?php esc_url( menu_page_url( $tab_slug ) ); ?>"><?php esc_attr_e( $option_tab['title'], 'sliced-invoices' ); ?></a>
				<?php endforeach; ?>
			</h2>
			<!-- End of Nav Tabs -->

			<?php foreach ($tab_forms as $tab_form) : //render all tab forms (normaly just 1 form) ?>
			<div id="<?php esc_attr_e($tab_form['id']); ?>" class="cmb-form group">
				<div class="metabox-holder">
					<div class="postbox">
						<h3 class="title"><?php esc_html_e($tab_form['title'], 'sliced-invoices'); ?></h3>
						<div class="desc"><?php echo $tab_form['desc'] ?></div>
						<?php cmb2_metabox_form( $tab_form, $tab_form['id'] ); ?>
					</div>
				</div>
			</div>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * Defines the theme option metabox and field configuration
	 * @since  0.1.0
	 * @return array
	 */
	public function option_fields() {

		// Only need to initiate the array once per page-load
		if ( ! empty( $this->option_metabox ) ) {
			return $this->option_metabox;
		}

		$prefix = 'sliced_';
		$payments = get_option('sliced_payments');
		$quote_label = sliced_get_quote_label();
		$quote_label_plural = sliced_get_quote_label_plural();
		$invoice_label = sliced_get_invoice_label();
		$invoice_label_plural = sliced_get_invoice_label_plural();

		$this->option_metabox[] = apply_filters( 'sliced_general_option_fields', array(
			'id'         => $prefix . 'general',
			'title'      => __( 'General', 'sliced-invoices' ),
			'menu_title' => __( 'General Settings', 'sliced-invoices' ),
			'desc'       => __( 'Just some general options.', 'sliced-invoices' ),
			'show_on'    => array( 'key' => 'options-page', 'value' => array( 'general' ), ),
			'show_names' => true,
			'fields'     => array(

				array(
					'name'      => __( 'Year Start', 'sliced-invoices' ),
					'id'        => 'year_start',
					'desc'      => __( 'The start date of the fiscal year', 'sliced-invoices' ),
					'type'      => 'select',
					'options'   => array(
						'01' => __( '01 Jan', 'sliced-invoices' ),
						'02' => __( '01 Feb', 'sliced-invoices' ),
						'03' => __( '01 Mar', 'sliced-invoices' ),
						'04' => __( '01 Apr', 'sliced-invoices' ),
						'05' => __( '01 May', 'sliced-invoices' ),
						'06' => __( '01 Jun', 'sliced-invoices' ),
						'07' => __( '01 Jul', 'sliced-invoices' ),
						'08' => __( '01 Aug', 'sliced-invoices' ),
						'09' => __( '01 Sep', 'sliced-invoices' ),
						'10' => __( '01 Oct', 'sliced-invoices' ),
						'11' => __( '01 Nov', 'sliced-invoices' ),
						'12' => __( '01 Dec', 'sliced-invoices' ),
					),
				),
				array(
					'name'      => __( 'Year End', 'sliced-invoices' ),
					'id'        => 'year_end',
					'desc'      => __( 'The end date of the fiscal year', 'sliced-invoices' ),
					'type'      => 'select',
					'options'   => array(
						'01' => __( '31 Jan', 'sliced-invoices' ),
						'02' => __( '28 Feb', 'sliced-invoices' ),
						'03' => __( '31 Mar', 'sliced-invoices' ),
						'04' => __( '30 Apr', 'sliced-invoices' ),
						'05' => __( '31 May', 'sliced-invoices' ),
						'06' => __( '30 Jun', 'sliced-invoices' ),
						'07' => __( '31 Jul', 'sliced-invoices' ),
						'08' => __( '31 Aug', 'sliced-invoices' ),
						'09' => __( '30 Sep', 'sliced-invoices' ),
						'10' => __( '31 Oct', 'sliced-invoices' ),
						'11' => __( '30 Nov', 'sliced-invoices' ),
						'12' => __( '31 Dec', 'sliced-invoices' ),
					),
				),
				array(
					'name'      => __( 'Pre-Defined Line Items', 'sliced-invoices' ),
					'desc'      => __( 'Add 1 line item per line in this format: Qty | Title | Price | Description. Each field separated with a | symbol. <br>Price should be numbers only, no currency symbol.<br>If you prefer to have an item blank, you still need the | symbol like so: 1 | Web Design | | Designing the web', 'sliced-invoices' ),
					'default'   => '',
					'id'        => 'pre_defined',
					'type'      => 'textarea_small',
					'attributes' => array(
						'placeholder' => '1 | Title goes here | 85 | Description goes here and basic HTML is allowed.',
					)
				),
				array(
					'name'      => __( 'Footer<br><small>' . sprintf( __( 'Displayed on %1s & %2s', 'sliced-invoices' ), $quote_label_plural, $invoice_label_plural ) . '</small>', 'sliced-invoices' ),
					'desc'      => __( 'The footer will be displayed at the bottom of each ' . sprintf( __( '%1s & %2s', 'sliced-invoices' ), $quote_label, $invoice_label ) . '. Basic HTML is allowed.', 'sliced-invoices' ),
					'default'   => '',
					'id'        => 'footer',
					'type'      => 'textarea_small',
				),


			) )
		);

		$this->option_metabox[] = apply_filters( 'sliced_business_option_fields', array(
			'id'         => $prefix . 'business',
			'title'      => __( 'Business', 'sliced-invoices' ),
			'menu_title' => __( 'Business Settings', 'sliced-invoices' ),
			'desc'       => sprintf( __( 'All of the Business Details below will be displayed on the %1s & %2s.', 'sliced-invoices' ), $quote_label_plural, $invoice_label_plural ),
			'show_on'    => array( 'key' => 'options-page', 'value' => array( 'business' ), ),
			'show_names' => true,
			'fields'     => array(

				array(
					'name'      => __( 'Logo', 'sliced-invoices' ),
					'desc'      => __( 'Logo of your business. If no logo is added, the name of your business will be used instead.', 'sliced-invoices' ),
					'id'        => 'logo',
					'type'      => 'file',
					'allow'     => array( 'url', 'attachment' )
				),
				array(
					'name'      => __( 'Business Name', 'sliced-invoices' ),
					'desc'      => __( '', 'sliced-invoices' ),
					'default'   => '',
					'id'        => 'name',
					'type'      => 'text',
				),
				array(
					'name'      => __( 'Address', 'sliced-invoices' ),
					'desc'      => __( 'Add your full address and format it anyway you like. Basic HTML is allowed.', 'sliced-invoices' ),
					'default'   => '',
					'id'        => 'address',
					'type'      => 'textarea_small',
				),
				array(
					'name'      => __( 'Extra Business Info', 'sliced-invoices' ),
					'desc'      => __( 'Extra business info such as Business Number, phone number or email address and format it anyway you like. Basic HTML is allowed.<br>You can add your VAT number or ABN here.', 'sliced-invoices' ),
					'default'   => '',
					'id'        => 'extra_info',
					'type'      => 'textarea_small'
				),
				array(
					'name'      => __( 'Website', 'sliced-invoices' ),
					'default'   => 'This will add a link on your logo and your business name.',
					'id'        => 'website',
					'type'      => 'text'
				),
			) )
		);

		$this->option_metabox[] = apply_filters( 'sliced_quote_option_fields', array(
			'id'         => $prefix . 'quotes',
			'title'      => $quote_label_plural,
			'menu_title' => sprintf( __( '%s Settings', 'sliced-invoices' ), $quote_label ),
			'desc'       => __( 'Here you will find all of the settings for ' . sprintf( __( '%s', 'sliced-invoices' ), $quote_label_plural ), 'sliced-invoices' ),
			'show_on'    => array( 'key' => 'options-page', 'value' => array( 'quotes' ), ),
			'show_names' => true,
			'fields'     => array(

				array(
					'name'      => __( 'Prefix', 'sliced-invoices' ),
					'desc'      => sprintf( __( 'Prefix of each %s. Can be left blank if you don\'t need a prefix.', 'sliced-invoices' ), $quote_label ),
					'default'   => '',
					'id'        => 'prefix',
					'type'      => 'text',
				),
				array(
					'name'      => __( 'Auto Increment', 'sliced-invoices' ),
					'desc'      => __( 'Yes, increment quote numbers by one. Recommended.', 'sliced-invoices' ),
					'id'        => 'increment',
					'type'      => 'checkbox',
				),
				array(
					'name'      => __( 'Next Number', 'sliced-invoices' ),
					'desc'      => __( 'The next number to use for auto incrementing. Can use leading zeros.', 'sliced-invoices' ),
					'default'   => '',
					'id'        => 'number',
					'type'      => 'text',
				),
				array(
					'name'      => __( 'Hide Adjust Field', 'sliced-invoices' ),
					'desc'      => __( 'Yes, hide the Adjust field on line items, I won\'t need this field', 'sliced-invoices' ),
					'id'        => 'adjustment',
					'type'      => 'checkbox',
				),
				array(
					'name'      => sprintf( __( '%s Valid For', 'sliced-invoices' ), $quote_label_plural ),
					'desc'      => sprintf( __( 'Number of days each %1s is valid for. This will automatically set the date in the \'Valid Until\' field.<br>Can be overriden on individual %2s.', 'sliced-invoices' ), $quote_label, $quote_label_plural ),
					'default'   => '',
					'id'        => 'valid_until',
					'type'      => 'text',
					'attributes' => array(
						'type' => 'number',
						'placeholder' => '30',
					)
				),
				array(
					'name'      => __( 'Terms & Conditions', 'sliced-invoices' ),
					'desc'      => __( 'Terms and conditions displayed on the quote..<br>Can be overriden on individual quotes.', 'sliced-invoices' ),
					'default'   => '',
					'id'        => 'terms',
					'type'      => 'textarea_small'
				),
				array(
					'name'      => __( 'Accept Quotes', 'sliced-invoices' ),
					'desc'      => '',
					'id'        => 'accept_quote_title',
					'type'      => 'title',
				),
				array(
					'name'      => __( 'Accept Quote Button', 'sliced-invoices' ),
					'desc'      => __( 'Yes, show the \'Accept Quote\' button on quotes. Invoice will be auto created.', 'sliced-invoices' ),
					'id'        => 'accept_quote',
					'type'      => 'checkbox',
				),
				array(
					'name'      => __( 'Accept Quote Text', 'sliced-invoices' ),
					'desc'      => __( 'Text to add on the \'Accept Quote\' popup.<br />Should provide some indication to your client of what happens after accepting the quote.', 'sliced-invoices' ),
					'default'   => '',
					'id'        => 'accept_quote_text',
					'type'      => 'textarea_small'
				),
				array(
					'name'      => __( 'Template Design', 'sliced-invoices' ),
					'desc'      => 'For information on customizing your templates, please see our guide <a target="_blank" href="https://slicedinvoices.com/support/quote-invoice-templates/?utm_source=Plugin&utm_medium=Quote-Design&utm_content=Support&utm_campaign=Free" title="Opens in new window">here</a>.',
					'id'        => 'quote_design_title',
					'type'      => 'title',
				),
				array(
					'name'      => __( 'Template', 'sliced-invoices' ),
					'desc'      => __( '', 'sliced-invoices' ),
					'id'        => 'template',
					'type'      => 'radio',
					'default'   => 'template1',
					'options'   => apply_filters( 'sliced_quote_template_options', array(
						'template1' => '<img src="' . plugin_dir_url( dirname( __FILE__ ) ) . '/img/template1.png" width="200" />',
						'template2' => '<img src="' . plugin_dir_url( dirname( __FILE__ ) ) . '/img/template2.png" width="200" />',
						'template3' => '<img src="' . plugin_dir_url( dirname( __FILE__ ) ) . '/img/template3.png" width="200" />',
					) ),
				),
				array(
					'name'      => __( 'Custom CSS', 'sliced-invoices' ),
					'desc'      => __( 'Add custom CSS to your quotes', 'sliced-invoices' ),
					'default'   => '',
					'id'        => 'css',
					'type'      => 'textarea_small',
				),

			) )
		);


		$this->option_metabox[] = apply_filters( 'sliced_invoice_option_fields', array(
			'id'         => $prefix . 'invoices',
			'title'      => $invoice_label_plural,
			'menu_title' => sprintf( __( '%s Settings', 'sliced-invoices' ), $invoice_label ),
			'desc'       => __( 'Here you will find all of the settings for ' . sprintf( __( '%s', 'sliced-invoices' ), $invoice_label_plural ), 'sliced-invoices' ),
			'show_on'    => array( 'key' => 'options-page', 'value' => array( 'invoices' ), ),
			'show_names' => true,
			'fields'     => array(

				array(
					'name'      => __( 'Prefix', 'sliced-invoices' ),
					'desc'      => sprintf( __( 'Prefix of each %s. Can be left blank if you don\'t need a prefix.', 'sliced-invoices' ), $invoice_label ),
					'default'   => '',
					'id'        => 'prefix',
					'type'      => 'text',
				),
				array(
					'name'      => __( 'Auto Increment', 'sliced-invoices' ),
					'desc'      => __( 'Yes, increment invoice numbers by one. Recommended.', 'sliced-invoices' ),
					'id'        => 'increment',
					'type'      => 'checkbox',
				),
				array(
					'name'      => __( 'Next Number', 'sliced-invoices' ),
					'desc'      => __( 'The next number to use for auto incrementing. Can use leading zeros', 'sliced-invoices' ),
					'default'   => '',
					'id'        => 'number',
					'type'      => 'text',
				),
				array(
					'name'      => __( 'Due Date', 'sliced-invoices' ),
					'desc'      => sprintf( __( 'Number of days each %1s is due after the created date. This will automatically set the date in the \'Due Date\' field.<br>Can be overriden on individual %2s.', 'sliced-invoices' ), $invoice_label, $invoice_label_plural ),
					'default'   => '',
					'id'        => 'due_date',
					'type'      => 'text',
					'attributes' => array(
						'type' => 'number',
						'placeholder' => '14',
					)
				),
				array(
					'name'      => __( 'Hide Adjust Field', 'sliced-invoices' ),
					'desc'      => __( 'Yes, hide the Adjust field on line items, I won\'t need this field', 'sliced-invoices' ),
					'id'        => 'adjustment',
					'type'      => 'checkbox',
				),
				array(
					'name'      => __( 'Terms & Conditions', 'sliced-invoices' ),
					'desc'      => __( 'Terms and conditions displayed on the invoice.<br>Can be overriden on individual invoices.', 'sliced-invoices' ),
					'default'   => '',
					'id'        => 'terms',
					'type'      => 'textarea_small'
				),
				array(
					'name'      => __( 'Template Design', 'sliced-invoices' ),
					'desc'      => 'For information on customizing your templates, please see our guide <a target="_blank" href="https://slicedinvoices.com/support/quote-invoice-templates/?utm_source=Plugin&utm_medium=Invoice-Design&utm_content=Support&utm_campaign=Free" title="Opens in new window">here</a>.',
					'id'        => 'invoice_design_title',
					'type'      => 'title',
				),
				array(
					'name'      => __( 'Template', 'sliced-invoices' ),
					'desc'      => __( '', 'sliced-invoices' ),
					'id'        => 'template',
					'type'      => 'radio',
					'default'   => 'template1',
					'options'   => apply_filters( 'sliced_invoice_template_options', array(
						'template1' => '<img src="' . plugin_dir_url( dirname( __FILE__ ) ) . '/img/template1.png" width="200" />',
						'template2' => '<img src="' . plugin_dir_url( dirname( __FILE__ ) ) . '/img/template2.png" width="200" />',
						'template3' => '<img src="' . plugin_dir_url( dirname( __FILE__ ) ) . '/img/template3.png" width="200" />',
					) ),
				),
				array(
					'name'      => __( 'Custom CSS', 'sliced-invoices' ),
					'desc'      => __( 'Add custom CSS to your invoices.', 'sliced-invoices' ),
					'default'   => '',
					'id'        => 'css',
					'type'      => 'textarea_small',
				),


			) )
		);


		$this->option_metabox[] = apply_filters( 'sliced_payment_option_fields', array(
			'id'         => $prefix . 'payments',
			'title'      => __( 'Payments', 'sliced-invoices' ),
			'menu_title' => __( 'Payment Settings', 'sliced-invoices' ),
			'desc'       => __( 'Here you will find all of the Payment related settings.', 'sliced-invoices' ),
			'show_on'    => array( 'key' => 'options-page', 'value' => array( 'payments' ), ),
			'show_names' => true,
			'fields'     => array(

				array(
					'name'      => __( 'Currency Symbol', 'sliced-invoices' ),
					'desc'      => __( '', 'sliced-invoices' ),
					'default'   => '',
					'type'      => 'text',
					'id'        => 'currency_symbol',
					'attributes' => array(
						'placeholder' => '$',
					)
				),
				array(
					'name'      => __( 'Currency Position', 'sliced-invoices' ),
					'desc'      => __( '', 'sliced-invoices' ),
					'type'      => 'select',
					'id'        => 'currency_pos',
					'options'   => array(
						'left'          => 'Left ($100.00)',
						'right'         => 'Right (100.00$)',
						'left_space'    => 'Left with space ($ 100.00)',
						'right_space'   => 'Right with space(100.00 $)',
					),
				),
				array(
					'name'      => __( 'Thousand Separator', 'sliced-invoices' ),
					'desc'      => __( '', 'sliced-invoices' ),
					'default'   => ',',
					'type'      => 'text',
					'id'        => 'thousand_sep',
					'attributes' => array(
						'required' => 'required',
						'placeholder' => ',',
					)
				),
				array(
					'name'      => __( 'Decimal Separator', 'sliced-invoices' ),
					'desc'      => __( '', 'sliced-invoices' ),
					'default'   => '.',
					'type'      => 'text',
					'id'        => 'decimal_sep',
					'attributes' => array(
						'required' => 'required',
						'placeholder' => '.',
					)
				),
				array(
					'name'      => __( 'Number of Decimals', 'sliced-invoices' ),
					'desc'      => __( '', 'sliced-invoices' ),
					'default'   => '2',
					'type'      => 'text',
					'id'        => 'decimals',
					'attributes' => array(
						'required' => 'required',
						'placeholder' => '2',
						'maxlength' => 1,
						'type' => 'number',
					)
				),
				array(
					'name'      => __( 'Tax Percentage', 'sliced-invoices' ),
					'desc'      => __( 'Global tax percentage. Set to 0 or leave blank for no tax.', 'sliced-invoices' ),
					'default'   => '10',
					'id'        => 'tax',
					'type'      => 'text',
					'attributes'    => array(
						'placeholder'   => '10',
						'maxlength'     => '6',
						'type'          => 'number',
						'step'          => 'any',
					),
				),
				array(
					'name'      => __( 'Tax Name', 'sliced-invoices' ),
					'desc'      => __( 'The name of the tax for your country/region. GST, VAT, Tax etc', 'sliced-invoices' ),
					'default'   => '',
					'id'        => 'tax_name',
					'type'      => 'text',
					'attributes' => array(
						'maxlength' => 10,
					)
				),
				array(
					'name'      => __( 'Confirmation Page', 'sliced-invoices' ),
					'desc'      => __( 'Choose a page to use for PayPal and other <a target="_blank" href="https://slicedinvoices.com/extensions/">available payment gateway</a> messages and other confirmations.<br>A blank page named Confirmation would be perfect.', 'sliced-invoices' ),
					'default'   => $payments['payment_page'],
					'type'      => 'select',
					'id'        => 'payment_page',
					'options'   => $this->get_the_pages(),
					'attributes' => array(
						'required' => 'required',
					)
				),
				array(
					'name'      => __( 'Payment Methods', 'sliced-invoices' ),
					'desc'      => __( '', 'sliced-invoices' ),
					'default'   => '',
					'id'        => 'title_payment_methods',
					'type'      => 'title',
				),
				array(
					'name'      => __( 'Bank', 'sliced-invoices' ) . '<br><small>' . sprintf( __( 'Displayed on the %s', 'sliced-invoices' ), $invoice_label ) . '</small>',
					'desc'      => __( 'Add your bank account details if you wish to allow direct bank deposits. HTML is allowed.', 'sliced-invoices' ),
					'default'   => '',
					'id'        => 'bank',
					'type'      => 'textarea_small',
				),
				array(
					'name'      => __( 'Generic Payment', 'sliced-invoices' ) . '<br><small>' . sprintf( __( 'Displayed on the %s', 'sliced-invoices' ), $invoice_label ) . '</small>',
					'desc'      => __( 'Set a generic message or include further instructions for the user on how to pay. HTML is allowed.', 'sliced-invoices' ),
					'default'   => '',
					'id'        => 'generic_pay',
					'type'      => 'textarea_small',
				),


			)
		) );

		$this->option_metabox[] = apply_filters( 'sliced_email_option_fields', array(
			'id'         => $prefix . 'emails',
			'title'      => __( 'Emails', 'sliced-invoices' ),
			'menu_title' => __( 'Email Settings', 'sliced-invoices' ),
			'desc'          => __( 'Here you will find all of the Email related settings. The <a target="_blank" href="https://slicedinvoices.com/extensions/pdf-email">PDF & Email extension</a> will add extra options to customize the emails.', 'sliced-invoices' ),
			'show_on'    => array( 'key' => 'options-page', 'value' => array( 'emails' ), ),
			'show_names' => true,
			'fields'     => array(

				array(
					'name'      => __( 'Email Address', 'sliced-invoices' ),
					'desc'      => __( 'The email address to send and receive notifications (probably your business email).', 'sliced-invoices' ),
					'default'   => '',
					'type'      => 'text',
					'id'        => 'from'
				),
				array(
					'name'      => __( 'Email Name', 'sliced-invoices' ),
					'desc'      => __( 'The name on emails to send and receive notifications (probably your business name).', 'sliced-invoices' ),
					'default'   => '',
					'type'      => 'text',
					'id'        => 'name',
				),
				array(
					'name'      => __( 'Bcc on Client Emails', 'sliced-invoices' ),
					'desc'      => __( 'Yes, send myself a copy of all client emails (Bcc). Recommended.<br><span class="description"><small>This ensures you have a copy of the email on record</small></span>', 'sliced-invoices' ),
					'id'        => 'bcc',
					'type'      => 'checkbox',
				),
				array(
					'name'      => __( 'Quote Available', 'sliced-invoices' ),
					'desc'      => 'Sent to the client manually, when you click the email button.',
					'id'        => 'quote_available_title',
					'type'      => 'title',
				),
				array(
					'name'      => __( 'Subject', 'sliced-invoices' ),
					'desc'      => __( 'The subject of the email (wildcards are allowed).', 'sliced-invoices' ),
					'default'   => '',
					'type'      => 'text',
					'id'        => 'quote_available_subject',
				),
				array(
					'name'      => __( 'Content', 'sliced-invoices' ),
					'desc'      => __( 'The content of the email (wildcards and HTML are allowed).', 'sliced-invoices' ),
					'type'      => 'wysiwyg',
					'default'   => '',
					'id'        => 'quote_available_content',
					'sanitization_cb' => false,
					'options' => array(
						'media_buttons' => false,
						'textarea_rows' => get_option('default_post_edit_rows', 7),
						'teeny' => true,
						'tinymce' => true,
						'quicktags' => true
					),
				),

				array(
					'name'      => __( 'Invoice Available', 'sliced-invoices' ),
					'desc'      => 'Sent to the client manually, when you click the email button.',
					'id'        => 'invoice_available_title',
					'type'      => 'title',
				),
				array(
					'name'      => __( 'Subject', 'sliced-invoices' ),
					'desc'      => __( 'The subject of the email (wildcards are allowed).', 'sliced-invoices' ),
					'default'   => '',
					'type'      => 'text',
					'id'        => 'invoice_available_subject',
				),
				array(
					'name'      => __( 'Content', 'sliced-invoices' ),
					'desc'      => __( 'The content of the email (wildcards and HTML are allowed).', 'sliced-invoices' ),
					'type'      => 'wysiwyg',
					'default'   => '',
					'id'        => 'invoice_available_content',
					'sanitization_cb' => false,
					'options' => array(
						'media_buttons' => false,
						'textarea_rows' => get_option('default_post_edit_rows', 7),
						'teeny' => true,
						'tinymce' => true,
						'quicktags' => true
					),
				),

				array(
					'name'      => __( 'Payment Received', 'sliced-invoices' ),
					'desc'      => 'Sent to the client automatically, when they make a payment.',
					'id'        => 'payment_received_client_title',
					'type'      => 'title',
				),
				array(
					'name'      => __( 'Subject', 'sliced-invoices' ),
					'desc'      => __( 'The subject of the email (wildcards are allowed).', 'sliced-invoices' ),
					'default'   => '',
					'type'      => 'text',
					'id'        => 'payment_received_client_subject',
				),
				array(
					'name'      => __( 'Content', 'sliced-invoices' ),
					'desc'      => __( 'The content of the email (wildcards and HTML are allowed).', 'sliced-invoices' ),
					'type'      => 'wysiwyg',
					'default'   => '',
					'id'        => 'payment_received_client_content',
					'sanitization_cb' => false,
					'options' => array(
						'media_buttons' => false,
						'textarea_rows' => get_option('default_post_edit_rows', 7),
						'teeny' => true,
						'tinymce' => true,
						'quicktags' => true
					),
				),

				array(
					'name'      => __( 'Payment Reminder', 'sliced-invoices' ),
					'desc'      => 'Sent to the client automatically on the chosen days.',
					'id'        => 'payment_reminder_title',
					'type'      => 'title',
				),
				array(
					'name'      => __( 'When to Send', 'sliced-invoices' ),
					'desc'      => __( 'Check when you would like payment reminders sent out.', 'sliced-invoices' ),
					'default'   => '',
					'type'    => 'multicheck',
					'options' => array(
						'-7'     => '7 days before Due Date',
						'-1'     => '1 day before Due Date',
						'+0'      => 'On the Due Date',
						'+1'      => '1 day after Due Date',
						'+7'      => '7 days after Due Date',
						'+14'     => '14 days after Due Date',
						'+21'     => '21 days after Due Date',
						'+30'     => '30 days after Due Date',
					),
					'id'        => 'payment_reminder_days',
				),
				array(
					'name'      => __( 'Subject', 'sliced-invoices' ),
					'desc'      => __( 'The subject of the email (wildcards are allowed).', 'sliced-invoices' ),
					'default'   => '',
					'type'      => 'text',
					'id'        => 'payment_reminder_subject',
				),
				array(
					'name'      => __( 'Content', 'sliced-invoices' ),
					'desc'      => __( 'The content of the email (wildcards and HTML are allowed).', 'sliced-invoices' ),
					'type'      => 'wysiwyg',
					'default'   => '',
					'id'        => 'payment_reminder_content',
					'sanitization_cb' => false,
					'options' => array(
						'media_buttons' => false,
						'textarea_rows' => get_option('default_post_edit_rows', 7),
						'teeny' => true,
						'tinymce' => true,
						'quicktags' => true
					),
				),
				array(
					'name'      => __( 'Wildcards For Emails', 'sliced-invoices' ),
					'desc'      => __( 'The following wildcards can be used in email subjects and email content:<br>
						%client_first_name% : Clients first name<br>
						%client_last_name% : Clients last name<br>
						%client_business% : Clients business<br>
						%client_email% : Clients email address<br>
						%link% : URL to the quote<br>
						%number% : The quote or invoice number<br>
						%total% : The quote or invoice total<br>
						%created% : The quote or invoice created date<br>
						%valid_until% : The date the quote is valid until<br>
						%due_date% : The date the invoice is due<br>
						%date% : Todays date. Useful on Payment emails<br>
						%order_number% : The order number of the invoice<br>
						%is_was% : If due date of invoice is past, displays "was" otherwise displays "is"<br>
						', 'sliced-invoices-pdf-email' ),
					'id'        => 'wildcard_title',
					'type'      => 'title',
				),


			)
		) );

		$this->option_metabox[] = apply_filters( 'sliced_translate_option_fields', array(
			'id'         => $prefix . 'translate',
			'title'      => __( 'Translate', 'sliced-invoices' ),
			'menu_title' => __( 'Translate Settings', 'sliced-invoices' ),
			'desc'       => __( 'Here you will find all of the Translation related settings. The <a target="_blank" href="https://slicedinvoices.com/extensions/easy-translate/">Easy Translate extension</a> will allow you to translate every piece of text shown on invoices and quotes.', 'sliced-invoices' ),
			'show_on'    => array( 'key' => 'options-page', 'value' => array( 'translate' ), ),
			'show_names' => true,
			'fields'     => array(
				array(
					'name'      => __( 'Quote Label', 'sliced-invoices' ),
					'desc'      => __( 'You can change this from Quote to Estimate or Proposal (or any other word you like).', 'sliced-invoices' ),
					'default'   => 'Quote',
					'id'        => 'quote-label',
					'type'      => 'text',
				),
				array(
					'name'      => __( 'Quote Label Plural', 'sliced-invoices' ),
					'desc'      => __( 'The plural of the above', 'sliced-invoices' ),
					'default'   => 'Quotes',
					'id'        => 'quote-label-plural',
					'type'      => 'text',
				),
				array(
					'name'      => __( 'Invoice Label', 'sliced-invoices' ),
					'desc'      => __( 'You can change this from Invoice to Tax Invoice (or any other word you like).', 'sliced-invoices' ),
					'default'   => 'Invoice',
					'id'        => 'invoice-label',
					'type'      => 'text',
				),
				array(
					'name'      => __( 'Invoice Label Plural', 'sliced-invoices' ),
					'desc'      => __( 'The plural of the above', 'sliced-invoices' ),
					'default'   => 'Invoices',
					'id'        => 'invoice-label-plural',
					'type'      => 'text',
				),
			)
		) );

		$this->option_metabox[] = apply_filters( 'sliced_extras_option_fields', array(
			'id'         => $prefix . 'extras',
			'title'      => __( 'Extras', 'sliced-invoices' ),
			'menu_title' => __( 'Extras', 'sliced-invoices' ),
			'desc'       => __( 'Just a page with some advertising and a cry for help ;-)', 'sliced-invoices' ),
			'show_on'    => array( 'key' => 'options-page', 'value' => array( 'extras' ), ),
			'show_names' => true,
			'fields'     => array(
				array(
					'name'      => __( 'We\'d Love Your Support', 'sliced-invoices' ),
					'desc'      => __( '', 'sliced-invoices' ),
					'id'        => 'help_us',
					'type'      => 'title',
					'after_field'  => '
					<span style="float: left; margin: 15px 50px 20px 0px; font-size: 38px;" class="dashicons dashicons-wordpress"></span>
					<p>Thanks for using Sliced Invoices, we hope that you enjoy using it.<br>We\'d love it if you could take a minute and give the plugin a rating over on the <a target="_blank" href="https://wordpress.org/support/view/plugin-reviews/sliced-invoices?rate=5#postform" title="Opens in new window">Sliced Invoices WordPress page</a>.<br />This will help to continue the development of the free plugin. </p>
					',
				),
				array(
					'name'      => __( 'Extend Sliced Invoices', 'sliced-invoices' ),
					'desc'      => __( '', 'sliced-invoices' ),
					'id'        => 'extend',
					'type'      => 'title',
					'after_field'  => '

					<img style="margin:15px 0 5px;" src="' . plugin_dir_url( dirname( __FILE__ ) ) . '/img/sliced-invoices-logo.png" width="250" /><br>

					<p style="clear: both;">Check out the <strong>free and premium extensions</strong> that are available for Sliced Invoices at the <a target="_blank" href="https://slicedinvoices.com/extensions/?utm_source=Plugin&utm_medium=Extras-Page&utm_content=Extensions&utm_campaign=Free" title="Opens in new window">extensions marketplace</a>.<br>
						There are also <a target="_blank" href="https://slicedinvoices.com/bundles/?utm_source=Plugin&utm_medium=Extras-Page&utm_content=Bundles&utm_campaign=Free" title="Opens in new window">extension bundles</a> available that include <a target="_blank" href="https://slicedinvoices.com/support/priority-support/?utm_source=Plugin&utm_medium=Extras-Page&utm_content=Priority-Support&utm_campaign=Free" title="Opens in new window">priority support</a> options.</p>

					<ul class="sliced-extras">

						<li><a target="_blank" href="https://slicedinvoices.com/extensions/client-area/?utm_source=Plugin&utm_medium=Extras-Page&utm_content=Client-Area&utm_campaign=Free" title="Opens in new window">Client Area</a><br>
						<span class="description">A secure area for your clients to view, print and export their list of Quotes and Invoices as well as edit their business details.</span></li>

						<li><a target="_blank" href="https://slicedinvoices.com/extensions/pdf-email/?utm_source=Plugin&utm_medium=Extras-Page&utm_content=PDF-Email&utm_campaign=Free" title="Opens in new window">PDF & Email</a><br>
						<span class="description">Print quotes and invoices to PDF, email direct to clients and style the HTML emails and notifications.</span></li>

						<li><a target="_blank" href="https://slicedinvoices.com/extensions/stripe-payment-gateway/?utm_source=Plugin&utm_medium=Extras-Page&utm_content=Stripe&utm_campaign=Free" title="Opens in new window">Stripe Payment Gateway</a><br>
						<span class="description">The Stripe Payment Gateway extension allows you to accept credit card payments for your invoices securely.</span></li>

						<li><a target="_blank" href="https://slicedinvoices.com/extensions/better-urls/?utm_source=Plugin&utm_medium=Extras-Page&utm_content=Better-URLs&utm_campaign=Free" title="Opens in new window">Better URL\'s</a><br>
						<span class="description">Change the URL\'s of quotes and invoice to suit your business. Change it from \'sliced_invoice\' to \'bobs_invoice\' for example.</span></li>

						<li><a target="_blank" href="https://slicedinvoices.com/extensions/easy-translate/?utm_source=Plugin&utm_medium=Extras-Page&utm_content=Easy-Translate&utm_campaign=Free" title="Opens in new window">Easy Translate</a><br>
						<span class="description">Translate or modify the text that is displayed on the standard invoice and quote templates, without touching any code.</span></li>

						<li><a target="_blank" href="https://slicedinvoices.com/extensions/secure-invoices/?utm_source=Plugin&utm_medium=Extras-Page&utm_content=Secure-Invoices&utm_campaign=Free" title="Opens in new window">Secure Invoices</a><br>
						<span class="description">Secure your invoices and only allow access to people who have been sent a secure link.</span></li>

						<li><a target="_blank" href="https://slicedinvoices.com/extensions/recurring-invoices/?utm_source=Plugin&utm_medium=Extras-Page&utm_content=Recurring-Invoices&utm_campaign=Free" title="Opens in new window">Recurring Invoices</a><br>
						<span class="description">Easily create recurring invoices with the click of a button. </span></li>

						<li><a target="_blank" href="https://slicedinvoices.com/extensions/deposit-invoices/?utm_source=Plugin&utm_medium=Extras-Page&utm_content=Deposit-Invoices&utm_campaign=Free" title="Opens in new window">Deposit Invoices</a><br>
						<span class="description">Easily create deposit invoices with the click of a button. </span></li>

					</ul>
					<br />
					<br />
					',
				),

			)
		) );


		$this->option_metabox[] = apply_filters( 'sliced_licenses_option_fields', array(
			'id'         => $prefix . 'licenses',
			'title'      => __( 'Licenses', 'sliced-invoices' ),
			'menu_title' => __( 'Licenses', 'sliced-invoices' ),
			'desc'       => __( 'Licenses allow for one-click updates of extensions.', 'sliced-invoices' ),
			'show_on'    => array( 'key' => 'options-page', 'value' => array( 'licenses' ), ),
			'show_names' => true,
			'fields'     => array(
				array(
					'name' => __( 'Instructions', 'sliced-invoices' ),
					'id'   => 'licenses_instructions',
					'type' => 'title',
					'desc' => 'You should have received a Purchase Receipt email that contains the license key for each extension you have purchased from <a target="_blank" href="https://slicedinvoices.com">Sliced Invoices</a>.<br>
					If you have lost the email, you can login to your account at Sliced Invoices <a target="_blank" href="https://slicedinvoices.com/login/">here</a> to get your license key(s).<br><br>
					1. Copy the license key for your extension(s) and paste it into the field(s) below <strong>and then hit Save.</strong><br>
					2. <strong>After</strong> hitting the Save button, you can now hit the <strong>Activate License</strong> button for your extension(s).<br>
					3. Once the license is activated, an update notification will appear whenever there is an update.<br><br>',
				),
			)
		) );

		return $this->option_metabox;

	}



	/**
	 * Get the list of pages to add to dropdowns in the settings.
	 *
	 * @since   2.0.4
	 */
	public function get_the_pages() {

		$pages = get_pages();
		if( $pages ) {
			foreach ( $pages as $page ) {
				$the_pages[$page->ID] = $page->post_title;
			}
		}

		return $the_pages;

	}

	/**
	 * Returns the option key for a given field id
	 * @since  0.1.0
	 * @return array
	 */
	public function get_option_key($field_id) {
		$option_tabs = $this->option_fields();
		foreach ($option_tabs as $option_tab) { //search all tabs
			foreach ($option_tab['fields'] as $field) { //search all fields
				if ($field['id'] == $field_id) {
					return $option_tab['id'];
				}
			}
		}
		return $this->key; //return default key if field id not found
	}

	/**
	 * Public getter method for retrieving protected/private variables
	 * @since  0.1.0
	 * @param  string  $field Field to retrieve
	 * @return mixed          Field value or exception is thrown
	 */
	public function __get( $field ) {

		// Allowed fields to retrieve
		if ( in_array( $field, array( 'key', 'fields', 'title', 'options_pages' ), true ) ) {
			return $this->{$field};
		}
		if ( 'option_metabox' === $field ) {
			return $this->option_fields();
		}

		throw new Exception( 'Invalid property: ' . $field );
	}

}

// Get it started
$Sliced_Options = new Sliced_Options();
$Sliced_Options->hooks();

/**
 * Wrapper function around cmb_get_option
 * @since  0.1.0
 * @param  string  $key Options array key
 * @return mixed        Option value
 */
function sliced_admin_option( $key = '' ) {
	global $Sliced_Options;
	return cmb2_get_option( $Sliced_Options->get_option_key($key), $key );
}
