<?php

// Exit if accessed directly
if ( ! defined('ABSPATH') ) { exit; }


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
		add_action( 'network_admin_menu', array( $this, 'add_options_page' ), 999 ); //for WP Multisite support
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
	 * Add menu options page.
	 * 
	 * @version 3.9.0
	 * @since 0.1.0
	 */
	public function add_options_page() {

		$option_tabs = self::option_fields();
		
		// Link admin menu to first tab
		$this->options_pages[] = add_menu_page( $this->title, $this->menu_title, 'manage_options', 'sliced_invoices_settings', array( $this, 'admin_page_display' ), 'dashicons-sliced' );
		
		// Duplicate menu link for first submenu page
		add_submenu_page( 'sliced_invoices_settings', $this->menu_title, __( 'Settings', 'sliced-invoices' ), 'manage_options', 'sliced_invoices_settings', array( $this, 'admin_page_display' ) );
		
		// add special pages
		$plugin_reports = new Sliced_Reports();
		$plugin_tools = new Sliced_Tools();
		$this->options_pages[] = add_submenu_page( 'sliced_invoices_settings', 'Reports', 'Reports', 'manage_options', 'sliced_reports', array( $plugin_reports, 'display_reports_page' )  );
		$this->options_pages[] = add_submenu_page( 'sliced_invoices_settings', 'Tools', 'Tools', 'manage_options', 'sliced_tools', array( $plugin_tools, 'display_tools_page' )  );
		
		// add "pinned" settings pages
		$this->options_pages[] = add_submenu_page( 'sliced_invoices_settings', $this->menu_title, 'Extras', 'manage_options', 'sliced_extras', array( $this, 'admin_page_display' ) );
		$this->options_pages[] = add_submenu_page( 'sliced_invoices_settings', $this->menu_title, 'Licenses', 'manage_options', 'sliced_licenses', array( $this, 'admin_page_display' ) );
		
		// for backwards compatibility (will be removed at some point in the future...)
		// $this->options_pages[] = add_submenu_page( null, $this->menu_title, 'General Settings', 'manage_options', 'sliced_general', array( $this, 'admin_page_display' ) );
		// $this->options_pages[] = add_submenu_page( null, $this->menu_title, 'Business Settings', 'manage_options', 'sliced_business', array( $this, 'admin_page_display' ) );
		// $this->options_pages[] = add_submenu_page( null, $this->menu_title, 'Quotes Settings', 'manage_options', 'sliced_quotes', array( $this, 'admin_page_display' ) );
		// $this->options_pages[] = add_submenu_page( null, $this->menu_title, 'Invoices Settings', 'manage_options', 'sliced_invoices', array( $this, 'admin_page_display' ) );
		// $this->options_pages[] = add_submenu_page( null, $this->menu_title, 'Payments Settings', 'manage_options', 'sliced_payments', array( $this, 'admin_page_display' ) );
		// $this->options_pages[] = add_submenu_page( null, $this->menu_title, 'Email Settings', 'manage_options', 'sliced_emails', array( $this, 'admin_page_display' ) );
		// $this->options_pages[] = add_submenu_page( null, $this->menu_title, 'PDF Settings', 'manage_options', 'sliced_pdf', array( $this, 'admin_page_display' ) );
		// $this->options_pages[] = add_submenu_page( null, $this->menu_title, 'Translate Settings', 'manage_options', 'sliced_translate', array( $this, 'admin_page_display' ) );
		
		// Include CMB CSS in the head to avoid FOUC
		foreach ( $this->options_pages as $page ) {
			add_action( "admin_print_styles-{$page}", array( 'CMB2_Hookup', 'enqueue_cmb_css' ) );
		}
		
	}


	/**
	 * Admin page markup.
	 * Mostly handled by CMB2.
	 * 
	 * @version 3.9.0
	 * @since   0.1.0
	 */
	public function admin_page_display() {

		global $pagenow;

		// check we are on the network settings page
		if( $pagenow != 'admin.php' ) {
			return;
		}
		
		if ( isset( $_GET['page'] ) && $_GET['page'] === 'sliced_extras' ) {
			$current_tab = 'extras';
		} elseif ( isset( $_GET['page'] ) && $_GET['page'] === 'sliced_licenses' ) {
			$current_tab = 'licenses';
		} else {
			$current_tab = empty( $_GET['tab'] ) ? 'general' : sanitize_title( $_GET['tab'] );
		}
		
		$option_tabs = self::option_fields(); //get all option tabs
		$tab_forms = array();

		?>
		<div class="wrap cmb2_sliced_options_page <?php echo esc_attr($this->key); ?>">

			<h2><?php _e( 'Sliced Invoices Settings', 'sliced-invoices' ) ?></h2>

			<!-- Options Page Nav Tabs -->
			<h2 class="nav-tab-wrapper">
				<?php foreach ($option_tabs as $option_tab) :
					$tab_slug = $option_tab['id'];
					$nav_class = 'i18n-multilingual-display nav-tab';
					if ( $tab_slug === 'sliced_'.$current_tab ) {
						$nav_class .= ' nav-tab-active'; //add active class to current tab
						$tab_forms[] = $option_tab; //add current tab to forms to be rendered
					}
					if ( $tab_slug === 'sliced_extras' || $tab_slug === 'sliced_licenses' ) {
						$admin_url = admin_url( 'admin.php?page='.$tab_slug );
					} else {
						$admin_url = admin_url( 'admin.php?page=sliced_invoices_settings&tab=' . str_replace( 'sliced_', '', $tab_slug ) );
					}
					?>
					<a class="<?php echo esc_attr( $nav_class ); ?>" href="<?php echo $admin_url; ?>"><?php esc_attr_e( $option_tab['menu_title'], 'sliced-invoices' ); ?></a>
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
	 * Defines the contents of the plugin settings pages.
	 * 
	 * @version 3.9.0
	 * @since   0.1.0
	 * 
	 * @return array
	 */
	public function option_fields() {
		
		// Only need to initiate the array once per page-load
		if ( ! empty( $this->option_metabox ) ) {
			return $this->option_metabox;
		}
		
		$payments             = get_option( 'sliced_payments' );
		$quote_label          = sliced_get_quote_label();
		$quote_label_plural   = sliced_get_quote_label_plural();
		$invoice_label        = sliced_get_invoice_label();
		$invoice_label_plural = sliced_get_invoice_label_plural();
		$current_user         = wp_get_current_user();
		
		$this->option_metabox[] = apply_filters( 'sliced_general_option_fields', array(
			'id'         => 'sliced_general',
			'title'      => __( 'General Settings', 'sliced-invoices' ),
			'menu_title' => __( 'General', 'sliced-invoices' ),
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
			)
		) );
		
		$this->option_metabox[] = apply_filters( 'sliced_business_option_fields', array(
			'id'         => 'sliced_business',
			'title'      => __( 'Business Settings', 'sliced-invoices' ),
			'menu_title' => __( 'Business', 'sliced-invoices' ),
			'desc'       => sprintf( __( 'All of the Business Details below will be displayed on the %1s & %2s.', 'sliced-invoices' ), '<span class="i18n-multilingual-display">'.$quote_label_plural.'</span>', '<span class="i18n-multilingual-display">'.$invoice_label_plural.'</span>' ),
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
			)
		) );
		
		// Quotes
		$this->option_metabox[] = apply_filters( 'sliced_quote_option_fields', array(
			'id'         => 'sliced_quotes',
			'title'      => sprintf(
				/* translators: %s is a placeholder for the localized word "Quote" (singular) */
				__( '%s Settings', 'sliced-invoices' ),
				$quote_label
			),
			'menu_title' => $quote_label_plural,
			'desc'       => sprintf(
				/* translators: %s is a placeholder for the localized word "Quotes" (plural) */
				__( 'Here you will find all the settings for %s.', 'sliced-invoices' ),
				$quote_label_plural
			),
			'show_on'    => array( 'key' => 'options-page', 'value' => array( 'quotes' ), ),
			'show_names' => true,
			'fields'     => array(
				array(
					'name'       => __( 'Prefix', 'sliced-invoices' ),
					'desc'       => sprintf(
						/* translators: %s is a placeholder for the localized word "Quote" (singular) */
						__( 'Prefix before each %s number. Can be left blank if you don\'t need a prefix.', 'sliced-invoices' ),
						$quote_label
					),
					'default'    => '',
					'id'         => 'prefix',
					'type'       => 'text',
				),
				array(
					'name'       => __( 'Suffix', 'sliced-invoices' ),
					'desc'       => sprintf(
						/* translators: %s is a placeholder for the localized word "Quote" (singular) */
						__( 'Suffix after each %s number. Can be left blank if you don\'t need a suffix.', 'sliced-invoices' ),
						$quote_label
					),
					'default'    => '',
					'id'         => 'suffix',
					'type'       => 'text',
				),
				array(
					'name'       => __( 'Auto Increment', 'sliced-invoices' ),
					'desc'       => sprintf(
						/* translators: %s is a placeholder for the localized word "Quote" (singular) */
						__( 'Yes, increment %s numbers by one. Recommended.', 'sliced-invoices' ),
						$quote_label
					),
					'id'         => 'increment',
					'type'       => 'checkbox',
				),
				array(
					'name'       => __( 'Next Number', 'sliced-invoices' ),
					'desc'       => __( 'The next number to use for auto incrementing. Can use leading zeros.', 'sliced-invoices' ),
					'default'    => '',
					'id'         => 'number',
					'type'       => 'text',
				),
				array(
					'name'       => sprintf(
						/* translators: %s is a placeholder for the localized word "Quotes" (plural) */
						__( '%s Valid For', 'sliced-invoices' ),
						$quote_label_plural
					),
					'desc'       => sprintf(
						/* translators: %1s is a placeholder for the word "Quote" (singular); %2s is a placeholder for "Quotes" (plural) */
						__( 'Number of days each %1s is valid for. This will automatically set the date in the \'Valid Until\' field.<br>Can be overriden on individual %2s.', 'sliced-invoices' ),
						$quote_label,
						$quote_label_plural
					),
					'default'    => '',
					'id'         => 'valid_until',
					'type'       => 'text',
					'attributes' => array(
						'type'        => 'number',
						'placeholder' => '30',
					)
				),
				array(
					'name'       => __( 'Hide Adjust Field', 'sliced-invoices' ),
					'desc'       => __( 'Yes, hide the Adjust field on line items, I won\'t need this field', 'sliced-invoices' ),
					'id'         => 'adjustment',
					'type'       => 'checkbox',
				),
				array(
					'name'       => __( 'Terms & Conditions', 'sliced-invoices' ),
					'desc'       => sprintf(
						/* translators: %1s is a placeholder for the word "Quote" (singular); %2s is a placeholder for "Quotes" (plural) */
						__( 'Terms and conditions displayed on the %1s.<br>Can be overriden on individual %2s.', 'sliced-invoices' ),
						$quote_label,
						$quote_label_plural
					),
					'default'    => '',
					'id'         => 'terms',
					'type'       => 'textarea_small'
				),
				array(
					'name'       => __( 'Footer', 'sliced-invoices' ),
					'desc'       => sprintf(
						/* translators: %1s is a placeholder for the localized word "Quote" (singular) */
						__( 'The footer will be displayed at the bottom of each %1s. Basic HTML is allowed.', 'sliced-invoices' ),
						$quote_label
					),
					'default'    => '',
					'id'         => 'footer',
					'type'       => 'textarea_small',
				),
				array(
					'name'       => sprintf(
						/* translators: %s is a placeholder for the localized word "Quotes" (plural) */
						__( 'Accepting %s', 'sliced-invoices' ),
						$quote_label_plural
					),
					'id'         => 'accept_quote_title',
					'type'       => 'title',
				),
				array(
					'name'       => sprintf(
						/* translators: %s is a placeholder for the localized word "Quote" (singular) */
						__( 'Accept %s Button', 'sliced-invoices' ),
						$quote_label
					),
					'desc'       => sprintf(
						/* translators: %1s is a placeholder for the word "Quote" (singular); %2s is a placeholder for "Quotes" (plural) */
						__( 'Yes, show the "Accept %1s" button on %2s.', 'sliced-invoices' ),
						$quote_label,
						$quote_label_plural
					),
					'id'         => 'accept_quote',
					'type'       => 'checkbox',
				),
				array(
					'name'       => sprintf(
						/* translators: %s is a placeholder for the localized word "Quote" (singular) */
						__( 'Accepted %s Action', 'sliced-invoices' ),
						$quote_label
					),
					'desc'       => sprintf(
						/* translators: %s is a placeholder for the localized word "Quote" (singular) */
						__( 'Actions to perform automatically when client accepts a %s.', 'sliced-invoices' ),
						$quote_label
					),
					'id'         => 'accepted_quote_action',
					'type'       => 'select',
					'default'    => 'convert',
					'options'    => array(
						'convert'        => sprintf(
							/* translators: %1s is a placeholder for the word "Quote" (singular); %2s is a placeholder for "Invoice" (singular) */
							__( 'Convert %1s to %2s', 'sliced-invoices' ),
							$quote_label,
							$invoice_label
						),
						'convert_send'   => sprintf(
							/* translators: %1s is a placeholder for the word "Quote" (singular); %2s is a placeholder for "Invoice" (singular) */
							__( 'Convert %1s to %2s and send to client', 'sliced-invoices' ),
							$quote_label,
							$invoice_label
						),
						'duplicate'      => sprintf(
							/* translators: %1s is a placeholder for the word "Invoice" (singular); %2s is a placeholder for "Quote" (singular) */
							__( 'Create new %1s, keep %2s as-is', 'sliced-invoices' ),
							$invoice_label,
							$quote_label
						),
						'duplicate_send' => sprintf(
							/* translators: %1s is a placeholder for the word "Invoice" (singular); %2s is a placeholder for "Quote" (singular) */
							__( 'Create new %1s and send to client, keep %2s as-is', 'sliced-invoices' ),
							$invoice_label,
							$quote_label
						),
						'do_nothing'     => __( 'Do nothing', 'sliced-invoices' ),
					),
				),
				array(
					'name'       => sprintf(
						/* translators: %s is a placeholder for the localized word "Quote" (singular) */
						__( 'Accept %s Text', 'sliced-invoices' ),
						$quote_label
					),
					'desc'       => sprintf(
						/* translators: %1s and %2s are placeholders for the localized word "Quote" (singular) */
						__( 'Text to add on the "Accept %1s" popup. Basic HTML is allowed.<br>This should provide some indication to your client of what happens after accepting the %2s.', 'sliced-invoices' ),
						$quote_label,
						$quote_label
					),
					'default'    => '',
					'id'         => 'accept_quote_text',
					'type'       => 'textarea_small'
				),
				array(
					'name'       => sprintf(
						/* translators: %s is a placeholder for the localized word "Quote" (singular) */
						__( 'Accepted %s Message', 'sliced-invoices' ),
						$quote_label
					),
					'desc'       => sprintf(
						/* translators: %s is a placeholder for the localized word "Quote" (singular) */
						__( 'Message to display if client accepts the %s. Basic HTML is allowed.<br>Leave blank for the default message.', 'sliced-invoices' ),
						$quote_label
					),
					'default'    => '',
					'id'         => 'accepted_quote_message',
					'type'       => 'textarea_small',
					'attributes' => array(
						'placeholder' => sprintf(
							/* translators: %s is a placeholder for the localized word "Quote" (singular) */
							__( 'You have accepted the %s.<br>We will be in touch shortly.', 'sliced-invoices' ),
							$quote_label
						),
					)
				),
				array(
					'name'       => __( 'Decline Reason Required', 'sliced-invoices' ),
					'desc'       => __( 'Yes, make the "Reason for declining" field required.', 'sliced-invoices' ),
					'id'         => 'decline_reason_required',
					'type'       => 'checkbox',
				),
				array(
					'name'       => sprintf(
						/* translators: %s is a placeholder for the localized word "Quote" (singular) */
						__( 'Declined %s Message', 'sliced-invoices' ),
						$quote_label
					),
					'desc'       => sprintf(
						/* translators: %s is a placeholder for the localized word "Quote" (singular) */
						__( 'Message to display if client declines the %s. Basic HTML is allowed.<br>Leave blank for the default message.', 'sliced-invoices' ),
						$quote_label
					),
					'default'    => '',
					'id'         => 'declined_quote_message',
					'type'       => 'textarea_small',
					'attributes' => array(
						'placeholder' => sprintf(
							/* translators: %s is a placeholder for the localized word "Quote" (singular) */
							__( 'You have declined the %s.<br>We will be in touch shortly.', 'sliced-invoices' ),
							$quote_label
						),
					)
				),
				array(
					'name'       => __( 'Admin Notices', 'sliced-invoices' ),
					'desc'       => sprintf(
						__( 'These settings allow you to choose which notices may be displayed in your WordPress Admin area. (Note: this is different from admin emails, which you can configure on the <a href="%s">Email Settings</a> tab.', 'sliced-invoices' ),
						admin_url( 'admin.php?page=sliced_invoices_settings&tab=emails' )
					),
					'id'         => 'quote_admin_notices_title',
					'type'       => 'title',
				),
				array(
					'name'       => __( 'Show me notices when', 'sliced-invoices' ),
					'id'         => 'quote_admin_notices',
					'type'       => 'multicheck',
					'options'    => array(
						'quote_viewed'   => sprintf(
							/* translators: %s is a placeholder for the localized word "Quote" (singular) */
							__( '%s Viewed', 'sliced-invoices' ),
							$quote_label
						),
						'quote_accepted' => sprintf(
							/* translators: %s is a placeholder for the localized word "Quote" (singular) */
							__( '%s Accepted', 'sliced-invoices' ),
							$quote_label
						),
					),
				),
				array(
					'name'       => __( 'Template Design', 'sliced-invoices' ),
					'desc'       => sprintf(
						/* translators: %s: URL */
						__( 'For information on customizing your templates, please see our guide <a target="_blank" href="%s">here</a>.', 'sliced-invoices' ),
						'https://slicedinvoices.com/support/quote-invoice-templates/?utm_source=quote_settings_page_templates&utm_campaign=free&utm_medium=sliced_invoices'
					),
					'id'         => 'quote_design_title',
					'type'       => 'title',
				),
				array(
					'name'       => __( 'Template', 'sliced-invoices' ),
					'id'         => 'template',
					'type'       => 'radio',
					'default'    => 'template1',
					'options'    => apply_filters( 'sliced_quote_template_options', array(
						'template1' => '<img src="' . plugin_dir_url( dirname( __FILE__ ) ) . '/img/template1.png" width="200" />',
						'template2' => '<img src="' . plugin_dir_url( dirname( __FILE__ ) ) . '/img/template2.png" width="200" />',
						'template3' => '<img src="' . plugin_dir_url( dirname( __FILE__ ) ) . '/img/template3.png" width="200" />',
					) ),
				),
				array(
					'name'       => __( 'Custom CSS', 'sliced-invoices' ),
					'desc'       => sprintf(
						/* translators: %s is a placeholder for the localized word "Quotes" (plural) */
						__( 'Add custom CSS to your %s', 'sliced-invoices' ),
						$quote_label_plural
					),
					'default'    => '',
					'id'         => 'css',
					'type'       => 'textarea_small',
				),
			)
		) );
		
		// Invoices
		$this->option_metabox[] = apply_filters( 'sliced_invoice_option_fields', array(
			'id'         => 'sliced_invoices',
			'title'      => sprintf(
				/* translators: %s is a placeholder for the localized word "Invoice" (singular) */
				__( '%s Settings', 'sliced-invoices' ),
				$invoice_label
			),
			'menu_title' => $invoice_label_plural,
			'desc'       => sprintf(
				/* translators: %s is a placeholder for the localized word "Invoices" (plural) */
				__( 'Here you will find all the settings for %s.', 'sliced-invoices' ),
				$invoice_label_plural
			),
			'show_on'    => array( 'key' => 'options-page', 'value' => array( 'invoices' ), ),
			'show_names' => true,
			'fields'     => array(
				array(
					'name'       => __( 'Prefix', 'sliced-invoices' ),
					'desc'       => sprintf(
						/* translators: %s is a placeholder for the localized word "Invoice" (singular) */
						__( 'Prefix before each %s number. Can be left blank if you don\'t need a prefix.', 'sliced-invoices' ),
						$invoice_label
					),
					'default'    => '',
					'id'         => 'prefix',
					'type'       => 'text',
				),
				array(
					'name'       => __( 'Suffix', 'sliced-invoices' ),
					'desc'       => sprintf(
						/* translators: %s is a placeholder for the localized word "Invoice" (singular) */
						__( 'Suffix after each %s number. Can be left blank if you don\'t need a suffix.', 'sliced-invoices' ),
						$invoice_label
					),
					'default'    => '',
					'id'         => 'suffix',
					'type'       => 'text',
				),
				array(
					'name'       => __( 'Auto Increment', 'sliced-invoices' ),
					'desc'       => sprintf(
						/* translators: %s is a placeholder for the localized word "Invoice" (singular) */
						__( 'Yes, increment %s numbers by one. Recommended.', 'sliced-invoices' ),
						$invoice_label
					),
					'id'         => 'increment',
					'type'       => 'checkbox',
				),
				array(
					'name'       => __( 'Next Number', 'sliced-invoices' ),
					'desc'       => __( 'The next number to use for auto incrementing. Can use leading zeros', 'sliced-invoices' ),
					'default'    => '',
					'id'         => 'number',
					'type'       => 'text',
				),
				array(
					'name'       => __( 'Due Date', 'sliced-invoices' ),
					'desc'       => sprintf(
						/* translators: %1s is a placeholder for the word "Invoice" (singular); %2s is a placeholder for "Invoices" (plural) */
						__( 'Number of days each %1s is due after the created date. This will automatically set the date in the \'Due Date\' field.<br>Can be overriden on individual %2s.', 'sliced-invoices' ),
						$invoice_label,
						$invoice_label_plural
					),
					'default'    => '',
					'id'         => 'due_date',
					'type'       => 'text',
					'attributes' => array(
						'type'        => 'number',
						'placeholder' => '14',
					)
				),
				array(
					'name'       => __( 'Hide Adjust Field', 'sliced-invoices' ),
					'desc'       => __( 'Yes, hide the Adjust field on line items, I won\'t need this field', 'sliced-invoices' ),
					'id'         => 'adjustment',
					'type'       => 'checkbox',
				),
				array(
					'name'       => __( 'Terms & Conditions', 'sliced-invoices' ),
					'desc'       => sprintf(
						/* translators: %1s is a placeholder for the word "Invoice" (singular); %2s is a placeholder for "Invoices" (plural) */
						__( 'Terms and conditions displayed on the %1s.<br>Can be overriden on individual %2s.', 'sliced-invoices' ),
						$invoice_label,
						$invoice_label_plural
					),
					'default'    => '',
					'id'         => 'terms',
					'type'       => 'textarea_small'
				),
				array(
					'name'       => __( 'Footer', 'sliced-invoices' ),
					'desc'       => sprintf(
						/* translators: %1s is a placeholder for the localized word "Invoice" (singular) */
						__( 'The footer will be displayed at the bottom of each %1s. Basic HTML is allowed.', 'sliced-invoices' ),
						$invoice_label
					),
					'default'    => '',
					'id'         => 'footer',
					'type'       => 'textarea_small',
				),
				array(
					'name'       => __( 'Admin Notices', 'sliced-invoices' ),
					'desc'       => sprintf(
						__( 'These settings allow you to choose which notices may be displayed in your WordPress Admin area. (Note: this is different from admin emails, which you can configure on the <a href="%s">Email Settings</a> tab.', 'sliced-invoices' ),
						admin_url( 'admin.php?page=sliced_invoices_settings&tab=emails' )
					),
					'id'         => 'invoice_admin_notices_title',
					'type'       => 'title',
				),
				array(
					'name'       => __( 'Show me notices when', 'sliced-invoices' ),
					'id'         => 'invoice_admin_notices',
					'type'       => 'multicheck',
					'options'    => array(
						'invoice_viewed' => sprintf(
							/* translators: %s is a placeholder for the localized word "Invoice" (singular) */
							__( '%s Viewed', 'sliced-invoices' ),
							$invoice_label
						),
						'invoice_paid'   => sprintf(
							/* translators: %s is a placeholder for the localized word "Invoice" (singular) */
							__( '%s Paid', 'sliced-invoices' ),
							$invoice_label
						),
					),
				),
				array(
					'name'       => __( 'Template Design', 'sliced-invoices' ),
					'desc'       => sprintf(
						/* translators: %s: URL */
						__( 'For information on customizing your templates, please see our guide <a target="_blank" href="%s">here</a>.', 'sliced-invoices' ),
						'https://slicedinvoices.com/support/quote-invoice-templates/?utm_source=invoice_settings_page_templates&utm_campaign=free&utm_medium=sliced_invoices'
					),
					'id'         => 'invoice_design_title',
					'type'       => 'title',
				),
				array(
					'name'       => __( 'Template', 'sliced-invoices' ),
					'id'         => 'template',
					'type'       => 'radio',
					'default'    => 'template1',
					'options'    => apply_filters( 'sliced_invoice_template_options', array(
						'template1' => '<img src="' . plugin_dir_url( dirname( __FILE__ ) ) . '/img/template1.png" width="200" />',
						'template2' => '<img src="' . plugin_dir_url( dirname( __FILE__ ) ) . '/img/template2.png" width="200" />',
						'template3' => '<img src="' . plugin_dir_url( dirname( __FILE__ ) ) . '/img/template3.png" width="200" />',
					) ),
				),
				array(
					'name'       => __( 'Custom CSS', 'sliced-invoices' ),
					'desc'       => sprintf(
						/* translators: %s is a placeholder for the localized word "Invoices" (plural) */
						__( 'Add custom CSS to your %s', 'sliced-invoices' ),
						$invoice_label_plural
					),
					'default'    => '',
					'id'         => 'css',
					'type'       => 'textarea_small',
				),
			)
		) );
		
		$this->option_metabox[] = apply_filters( 'sliced_payment_option_fields', array(
			'id'         => 'sliced_payments',
			'title'      => __( 'Payment Settings', 'sliced-invoices' ),
			'menu_title' => __( 'Payments', 'sliced-invoices' ),
			'desc'       => __( 'Here you will find all of the Payment related settings.', 'sliced-invoices' ),
			'show_on'    => array( 'key' => 'options-page', 'value' => array( 'payments' ), ),
			'show_names' => true,
			'fields'     => array(
				array(
					'name'      => __( 'Currency Symbol', 'sliced-invoices' ),
					'default'   => '',
					'type'      => 'text',
					'id'        => 'currency_symbol',
					'attributes' => array(
						'placeholder' => '$',
					)
				),
				array(
					'name'      => __( 'Currency Position', 'sliced-invoices' ),
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
					//'default'   => ',',
					'type'      => 'text',
					'id'        => 'thousand_sep',
					'attributes' => array(
						//'required' => 'required', // allow empty
						'placeholder' => ',',
					),
					'sanitization_cb' => false, // allow whitespace
				),
				array(
					'name'      => __( 'Decimal Separator', 'sliced-invoices' ),
					'default'   => '.',
					'type'      => 'text',
					'id'        => 'decimal_sep',
					'attributes' => array(
						'required' => 'required',
						'placeholder' => '.',
					),
				),
				array(
					'name'      => __( 'Number of Decimals', 'sliced-invoices' ),
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
					'name'       => __( 'Payment Page', 'sliced-invoices' ),
					'desc'       => sprintf(
						/* translators: %s: URL */
						__( 'Choose a page to use for PayPal and other <a target="_blank" href="%s">available payment gateway</a> messages and other confirmations.<br>A blank page named Payment would be perfect.', 'sliced-invoices' ),
						'https://slicedinvoices.com/extensions/?utm_source=payment_settings_page&utm_campaign=free&utm_medium=sliced_invoices'
					),
					'default'    => $payments['payment_page'],
					'type'       => 'select',
					'id'         => 'payment_page',
					'options'    => $this->get_the_pages(),
					'attributes' => array(
						'required' => 'required',
					)
				),
				array(
					'name'      => __( 'Payment Page Footer', 'sliced-invoices' ),
					'desc'      => __( 'The footer will be displayed at the bottom of the payment page. Basic HTML is allowed.<br>Use this to provide additional payment instructions, if desired.', 'sliced-invoices' ),
					'default'   => '',
					'id'        => 'footer',
					'type'      => 'textarea_small',
				),
				array(
					'name'      => __( 'Payment Methods', 'sliced-invoices' ),
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
		
		$this->option_metabox[] = apply_filters( 'sliced_tax_option_fields', array(
			'id'         => 'sliced_tax',
			'title'      => __( 'Tax Settings', 'sliced-invoices' ),
			'menu_title' => __( 'Tax', 'sliced-invoices' ),
			'desc'       => __( 'Here you will find all Tax-related settings.', 'sliced-invoices' ),
			'show_on'    => array( 'key' => 'options-page', 'value' => array( 'tax' ), ),
			'show_names' => true,
			'fields'     => array(
				array(
					'name'      => __( 'Prices entered with tax', 'sliced-invoices' ),
					'default'   => 'exclusive',
					'type'      => 'radio',
					'id'        => 'tax_calc_method',
					'options'   => array(
						'inclusive' => __( 'Yes, I will enter prices inclusive of tax', 'sliced-invoices' ),
						'exclusive' => __( 'No, I will enter prices exclusive of tax', 'sliced-invoices' ),
					)
				),
				array(
					'name'      => __( 'Tax Percentage', 'sliced-invoices' ),
					'desc'      => __( 'Default tax percentage. Set to 0 or leave blank for no tax.', 'sliced-invoices' ),
					'default'   => '',
					'id'        => 'tax',
					'type'      => 'text',
					'attributes'    => array(
						'placeholder'   => '10',
						'maxlength'     => '6',
						// 'type'          => 'number',
						// 'step'          => 'any',
					),
				),
				array(
					'name'      => __( 'Tax Name', 'sliced-invoices' ),
					'desc'      => __( 'The name of the tax for your country/region. GST, VAT, Tax etc', 'sliced-invoices' ),
					'default'   => '',
					'id'        => 'tax_name',
					'type'      => 'text',
					'attributes' => array(
						'maxlength' => 100,
					)
				),
			)
		) );
		
		$email_option_fields = array(
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
				'name'      => __( 'Button text', 'sliced-invoices' ),
				'desc'      => __( 'The "view this quote online" button.', 'sliced-invoices' ),
				'default'   => '',
				'type'      => 'text',
				'id'        => 'quote_available_button',
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
				'name'      => __( 'Button text', 'sliced-invoices' ),
				'desc'      => __( 'The "view this invoice online" button.', 'sliced-invoices' ),
				'default'   => '',
				'type'      => 'text',
				'id'        => 'invoice_available_button',
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
					%last_payment% : The amount of the last payment. (returns "N/A" if no payments)<br>
					%balance% : The balance outstanding on the quote or invoice<br>
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
		);
		if ( ! class_exists( 'Sliced_Pdf_Email' ) ) {
			$email_option_fields[] = array(
				'name'      => __( 'Footer Text', 'sliced-invoices' ),
				'type'      => 'wysiwyg',
				'default'   => '',
				'id'        => 'footer',
				'sanitization_cb' => false,
				'options' => array(
					'media_buttons' => false, // show insert/upload button(s)
					'textarea_rows' => get_option('default_post_edit_rows', 5), // rows="..."
					'teeny' => true, // output the minimal editor config used in Press This
					'tinymce' => true, // load TinyMCE, can be used to pass settings directly to TinyMCE using an array()
					'quicktags' => true // load Quicktags, can be used to pass settings directly to Quicktags using an array()
				),
			);
		}
		$this->option_metabox[] = apply_filters( 'sliced_email_option_fields', array(
			'id'         => 'sliced_emails',
			'title'      => __( 'Email Settings', 'sliced-invoices' ),
			'menu_title' => __( 'Emails', 'sliced-invoices' ),
			'desc'       => __( 'Here you will find all of the Email-related settings.', 'sliced-invoices' )
				. '<br /><br />' . sprintf(
					/* translators: %s: URL */
					__( '(PRO) The <a target="_blank" href="%s">PDF & Email Extension</a> adds a few extra options here for customizing emails.', 'sliced-invoices' ),
					'https://slicedinvoices.com/extensions/pdf-email/?utm_source=email_settings_page&utm_campaign=free&utm_medium=sliced_invoices'
				),
			'show_on'    => array( 'key' => 'options-page', 'value' => array( 'emails' ), ),
			'show_names' => true,
			'fields'     => $email_option_fields,
		) );
		
		$this->option_metabox[] = apply_filters( 'sliced_pdf_option_fields', array(
			'id'         => 'sliced_pdf',
			'title'      => __( 'PDF Settings', 'sliced-invoices' ),
			'menu_title' => __( 'PDF', 'sliced-invoices' ),
			'desc'       => __( 'Here you will find all of the PDF-related settings.', 'sliced-invoices' )
				. '<br /><br />' . sprintf(
					/* translators: %s: URL */
					__( '(PRO) The <a target="_blank" href="%s">PDF & Email Extension</a> is required for this feature.', 'sliced-invoices' ),
					'https://slicedinvoices.com/extensions/pdf-email/?utm_source=pdf_settings_page&utm_campaign=free&utm_medium=sliced_invoices'
				),
			'show_on'    => array( 'key' => 'options-page', 'value' => array( 'emails' ), ),
			'show_names' => true,
			'fields'     => array(),
		) );
		
		$this->option_metabox[] = apply_filters( 'sliced_translate_option_fields', array(
			'id'         => 'sliced_translate',
			'title'      => __( 'Translate Settings', 'sliced-invoices' ),
			'menu_title' => __( 'Translate', 'sliced-invoices' ),
			'desc'       => __( 'Here you can translate strings into your own language, or simply change the text to suit your needs.', 'sliced-invoices' )
				. '<br /><br />' . sprintf(
					/* translators: %s: URL */
					__( '(PRO) The <a target="_blank" href="%s">Easy Translate Extension</a> adds many more fields here, allowing you to translate every piece of text your client sees on your quotes and invoices.', 'sliced-invoices' ),
					'https://slicedinvoices.com/extensions/easy-translate/?utm_source=translate_settings_page&utm_campaign=free&utm_medium=sliced_invoices'
				),
			'show_on'    => array( 'key' => 'options-page', 'value' => array( 'translate' ), ),
			'show_names' => true,
			'fields'     => array(
				array(
					'name'      => __( 'Quote Label', 'sliced-invoices' ),
					'desc'      => __( 'You can change this from Quote to Estimate or Proposal (or any other word you like).', 'sliced-invoices' ),
					'default'   => 'Quote',
					'id'        => 'quote-label',
					'type'      => 'text',
					'attributes' => array(
						'class'      => 'i18n-multilingual regular-text',
					),
				),
				array(
					'name'      => __( 'Quote Label Plural', 'sliced-invoices' ),
					'desc'      => __( 'The plural of the above', 'sliced-invoices' ),
					'default'   => 'Quotes',
					'id'        => 'quote-label-plural',
					'type'      => 'text',
					'attributes' => array(
						'class'      => 'i18n-multilingual regular-text',
					),
				),
				array(
					'name'      => __( 'Invoice Label', 'sliced-invoices' ),
					'desc'      => __( 'You can change this from Invoice to Tax Invoice (or any other word you like).', 'sliced-invoices' ),
					'default'   => 'Invoice',
					'id'        => 'invoice-label',
					'type'      => 'text',
					'attributes' => array(
						'class'      => 'i18n-multilingual regular-text',
					),
				),
				array(
					'name'      => __( 'Invoice Label Plural', 'sliced-invoices' ),
					'desc'      => __( 'The plural of the above', 'sliced-invoices' ),
					'default'   => 'Invoices',
					'id'        => 'invoice-label-plural',
					'type'      => 'text',
					'attributes' => array(
						'class'      => 'i18n-multilingual regular-text',
					),
				),
				array(
					'name'      => __( 'Hrs/Qty', 'sliced-invoices' ),
					'default'   => 'Hrs/Qty',
					'id'        => 'hrs_qty',
					'type'      => 'text',
					'attributes' => array(
						'class'      => 'i18n-multilingual regular-text',
					),
				),
				array(
					'name'      => __( 'Service', 'sliced-invoices' ),
					'default'   => 'Service',
					'id'        => 'service',
					'type'      => 'text',
					'attributes' => array(
						'class'      => 'i18n-multilingual regular-text',
					),
				),
				array(
					'name'      => __( 'Rate/Price', 'sliced-invoices' ),
					'default'   => 'Rate/Price',
					'id'        => 'rate_price',
					'type'      => 'text',
					'attributes' => array(
						'class'      => 'i18n-multilingual regular-text',
					),
				),
				array(
					'name'      => __( 'Adjust', 'sliced-invoices' ),
					'default'   => 'Adjust',
					'id'        => 'adjust',
					'type'      => 'text',
					'attributes' => array(
						'class'      => 'i18n-multilingual regular-text',
					),
				),
				array(
					'name'      => __( 'Sub Total', 'sliced-invoices' ),
					'default'   => 'Sub Total',
					'id'        => 'sub_total',
					'type'      => 'text',
					'attributes' => array(
						'class'      => 'i18n-multilingual regular-text',
					),
				),
				array(
					'name'      => __( 'Discount', 'sliced-invoices' ),
					'default'   => 'Discount',
					'id'        => 'discount',
					'type'      => 'text',
					'attributes' => array(
						'class'      => 'i18n-multilingual regular-text',
					),
				),
				array(
					'name'      => __( 'Total', 'sliced-invoices' ),
					'default'   => 'Total',
					'id'        => 'total',
					'type'      => 'text',
					'attributes' => array(
						'class'      => 'i18n-multilingual regular-text',
					),
				),
				array(
					'name'      => __( 'Total Due', 'sliced-invoices' ),
					'default'   => 'Total Due',
					'id'        => 'total_due',
					'type'      => 'text',
					'attributes' => array(
						'class'      => 'i18n-multilingual regular-text',
					),
				),
			)
		) );
		
		$this->option_metabox[] = apply_filters( 'sliced_extras_option_fields', array(
			'id'         => 'sliced_extras',
			'title'      => __( 'Extras', 'sliced-invoices' ),
			'menu_title' => __( 'Extras', 'sliced-invoices' ),
			'desc'       => __( 'Just a page with some advertising and a cry for help ;-)', 'sliced-invoices' ),
			'show_on'    => array( 'key' => 'options-page', 'value' => array( 'extras' ), ),
			'show_names' => true,
			'fields'     => array(
				array(
					'name'      => __( 'We\'d Love Your Support', 'sliced-invoices' ),
					'id'        => 'help_us',
					'type'      => 'title',
					'after_field'  => '
					<span style="float: left; margin: 15px 50px 20px 0px; font-size: 38px;" class="dashicons dashicons-wordpress"></span>
					<p>Thanks for using Sliced Invoices, we hope that you enjoy using it.<br>We\'d love it if you could take a minute and give the plugin a rating over on the <a target="_blank" href="https://wordpress.org/support/plugin/sliced-invoices/reviews/?rate=5#new-post" title="Opens in new window">Sliced Invoices WordPress page</a>.<br />This will help to continue the development of the free plugin. </p>
					',
				),
				array(
					'name'      => __( 'Sign up for our newsletter', 'sliced-invoices' ),
					'id'        => 'newsletter',
					'type'      => 'title',
					'after_field'  => '
					<p>Sign up for our newsletter to receive occasional updates and announcements related to Sliced Invoices.  (We won\'t sell your information to anyone, we promise!)</p>
					<input id="sliced_newsletter_email" type="email" value="'.$current_user->user_email.'" />
					<button class="button" id="sliced_newsletter_submit" type="button">Submit</button>
					<span class="" id="sliced_newsletter_success" style="display:none;">Thanks! Please check your inbox to confirm your subscription.</span>
					<script type="text/javascript">
						jQuery( "#sliced_newsletter_submit" ).click(function(){
							var email_address = jQuery("#sliced_newsletter_email").val();
							if ( email_address > "" ) {
								jQuery.post(
									"https://slicedinvoices.com/wp-admin/admin-ajax.php",
									{ "action": "maillist_signup", "email_address": email_address },
									function(response) {
										if ( response > 1 ) {
											jQuery("#sliced_newsletter_submit").fadeOut();
											jQuery("#sliced_newsletter_success").fadeIn();
										}
									}
								);
							}
						});
					</script>
					',
				),
				array(
					'name'      => __( 'Extend Sliced Invoices', 'sliced-invoices' ),
					'id'        => 'extend',
					'type'      => 'title',
					'after_field'  => '

					<img style="margin:15px 0 5px;" src="' . plugin_dir_url( dirname( __FILE__ ) ) . '/img/sliced-invoices-logo.png" width="250" /><br>

					<p style="clear: both;">Check out the <strong>free and premium extensions</strong> that are available for Sliced Invoices at the <a target="_blank" href="https://slicedinvoices.com/extensions/?utm_source=extras_page&utm_campaign=free&utm_medium=sliced_invoices&utm_content=extensions" title="Opens in new window">extensions marketplace</a>.<br>
						There are also <a target="_blank" href="https://slicedinvoices.com/bundles/?utm_source=extras_page&utm_campaign=free&utm_medium=sliced_invoices&utm_content=bundles" title="Opens in new window">extension bundles</a> available where you can get our most popular plugins for one great price!</p>

					<ul class="sliced-extras">

						<li><a target="_blank" href="https://slicedinvoices.com/extensions/better-urls/?utm_source=extras_page&utm_campaign=free&utm_medium=sliced_invoices&utm_content=better_urls" title="Opens in new window">Better URL\'s</a> (free!)<br>
						<span class="description">Change the URL\'s of quotes and invoice to suit your business. Change it from \'sliced_invoice\' to \'bobs_invoice\' for example.</span></li>
						
						<li><a target="_blank" href="https://slicedinvoices.com/extensions/braintree-payment-gateway/?utm_source=extras_page&utm_campaign=free&utm_medium=sliced_invoices&utm_content=braintree" title="Opens in new window">Braintree Payment Gateway</a><br>
						<span class="description">The Braintree Payment Gateway extension allows you to accept credit card payments for your invoices securely.</span></li>
						
						<li><a target="_blank" href="https://slicedinvoices.com/extensions/client-area/?utm_source=extras_page&utm_campaign=free&utm_medium=sliced_invoices&utm_content=client_area" title="Opens in new window">Client Area</a><br>
						<span class="description">A secure area for your clients to view, print and export their list of Quotes and Invoices as well as edit their business details.</span></li>

						<li><a target="_blank" href="https://slicedinvoices.com/extensions/deposit-invoices/?utm_source=extras_page&utm_campaign=free&utm_medium=sliced_invoices&utm_content=deposit_invoices" title="Opens in new window">Deposit Invoices</a><br>
						<span class="description">Easily create deposit invoices with the click of a button. </span></li>

						<li><a target="_blank" href="https://slicedinvoices.com/extensions/easy-translate/?utm_source=extras_page&utm_campaign=free&utm_medium=sliced_invoices&utm_content=easy_translate" title="Opens in new window">Easy Translate</a><br>
						<span class="description">Translate or modify the text that is displayed on the standard invoice and quote templates, without touching any code.</span></li>
					
						<li><a target="_blank" href="https://slicedinvoices.com/extensions/partial-payments/?utm_source=extras_page&utm_campaign=free&utm_medium=sliced_invoices&utm_content=partial_payments" title="Opens in new window">Partial Payments</a><br>
						<span class="description">Allow your customers to make partial payments towards invoices.</span></li>

						<li><a target="_blank" href="https://slicedinvoices.com/extensions/pdf-email/?utm_source=extras_page&utm_campaign=free&utm_medium=sliced_invoices&utm_content=pdf_email" title="Opens in new window">PDF & Email</a><br>
						<span class="description">Print quotes and invoices to PDF, email direct to clients and style the HTML emails and notifications.</span></li>
						
						<li><a target="_blank" href="https://slicedinvoices.com/extensions/recurring-invoices/?utm_source=extras_page&utm_campaign=free&utm_medium=sliced_invoices&utm_content=recurring_invoices" title="Opens in new window">Recurring Invoices</a><br>
						<span class="description">Easily create recurring invoices with the click of a button. </span></li>
						
						<li><a target="_blank" href="https://slicedinvoices.com/extensions/secure-invoices/?utm_source=extras_page&utm_campaign=free&utm_medium=sliced_invoices&utm_content=secure_invoices" title="Opens in new window">Secure Invoices</a><br>
						<span class="description">Secure your invoices and only allow access to people who have been sent a secure link.</span></li>

						<li><a target="_blank" href="https://slicedinvoices.com/extensions/stripe-payment-gateway/?utm_source=extras_page&utm_campaign=free&utm_medium=sliced_invoices&utm_content=stripe" title="Opens in new window">Stripe Payment Gateway</a><br>
						<span class="description">The Stripe Payment Gateway extension allows you to accept credit card payments for your invoices securely.</span></li>
						
						<li><a target="_blank" href="https://slicedinvoices.com/extensions/subscription-invoices/?utm_source=extras_page&utm_campaign=free&utm_medium=sliced_invoices&utm_content=subscription_invoices" title="Opens in new window">Subscription Invoices</a><br>
						<span class="description">This extension allows you to easily charge your clients with automatic recurring payments.</span></li>

					</ul>
					<br />
					<br />
					',
				),
			)
		) );
		
		$this->option_metabox[] = apply_filters( 'sliced_licenses_option_fields', array(
			'id'         => 'sliced_licenses',
			'title'      => __( 'Licenses', 'sliced-invoices' ),
			'menu_title' => __( 'Licenses', 'sliced-invoices' ),
			'desc'       => sprintf(
				/* translators: %s: URL */
				__( 'This page is where you enter any license keys for extensions you have purchased from <a target="_blank" href="%s">Sliced Invoices</a>.', 'sliced-invoices' ),
				'https://slicedinvoices.com/?utm_source=licenses_page&utm_campaign=free&utm_medium=sliced_invoices'
			),
			'show_on'    => array( 'key' => 'options-page', 'value' => array( 'licenses' ), ),
			'show_names' => true,
			'fields'     => array(
				array(
					'name' => __( 'Instructions', 'sliced-invoices' ),
					'id'   => 'licenses_instructions',
					'type' => 'title',
					'desc' => sprintf(
							/* translators: %s: URL */
							__( 'Before you can enter your license keys here, you must install and activate the extensions first.  You can do this from your <a href="%s" target="_blank">Plugins</a> page.', 'sliced-invoices' ),
							admin_url( 'plugins.php' )
						)
						. '<br />'
						. sprintf(
							/* translators: %s: URL */
							__( 'For step-by-step instructions, please read our FAQ page: <a href="%s" target="_blank">How do I install and activate Sliced Invoices extensions?</a>', 'sliced-invoices' ),
							'https://slicedinvoices.com/question/install-activate-extensions/?utm_source=licenses_page&utm_campaign=free&utm_medium=sliced_invoices'
						)
						. '<br /><br />'
						. __( 'Once your extensions are installed and activated, you can activate your license keys by doing the following:', 'sliced-invoices' )
						. '<br /><br />'
						. __( '1. Copy the license key for your extension(s) and paste it into the field(s) below <strong>and then hit Save.</strong>', 'sliced-invoices' ) . '<br />'
						. __( '2. <strong>After</strong> hitting the Save button, you can now hit the <strong>Activate License</strong> button for your extension(s)', 'sliced-invoices' ) . '<br />'
						. __( '3. That\'s it!  Be sure to watch for any new updates for your extensions', 'sliced-invoices' ) . '<br /><br />',
				),
				array(
					'name' => __( 'Where can I find my license keys?', 'sliced-invoices' ),
					'id'   => 'licenses_where_to_find',
					'type' => 'title',
					'desc' => sprintf(
						/* translators: %s: URL */
						__( 'You should have received a Purchase Receipt email that contains the license key for each extension you have purchased from Sliced Invoices.<br>If you have lost the email, you can login to your account at Sliced Invoices <a target="_blank" href="%s">here</a> to get your license key(s).', 'sliced-invoices' ) . '<br /><br />',
						'https://slicedinvoices.com/login/?utm_source=licenses_page&utm_campaign=free&utm_medium=sliced_invoices'
					),
				),
				array(
					'name' => '',
					'id'   => 'licenses_divider',
					'type' => 'title',
				),
			)
		) );
		
		$this->option_metabox = apply_filters( 'sliced_invoices_admin_options', $this->option_metabox );
		
		return $this->option_metabox;
		
	}


	/**
	 * Get the list of pages to add to dropdowns in the settings.
	 *
	 * @since   2.0.4
	 */
	public function get_the_pages() {

		$pages = get_pages();
		$the_pages = array( '0' => '----' );
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
