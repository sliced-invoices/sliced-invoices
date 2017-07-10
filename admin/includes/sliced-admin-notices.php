<?php

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The Class.
 */
class Sliced_Admin_Notices {

	/**
	 * Stores notices.
	 * @var array
	 */
	private static $notices = array();

	/**
	 * Array of notices - name => callback.
	 * @var array
	 */
	private static $core_notices = array(
		'invalid_payment_page' => 'invalid_payment_page_notice',
	);

	/**
	 * Constructor.
	 */
	public static function init() {
		self::$notices = get_option( 'sliced_admin_notices', array() );

		add_action( 'switch_theme', array( __CLASS__, 'reset_admin_notices' ) );
		add_action( 'sliced_activated', array( __CLASS__, 'reset_admin_notices' ) );
		add_action( 'wp_loaded', array( __CLASS__, 'hide_notices' ) );
		add_action( 'shutdown', array( __CLASS__, 'store_notices' ) );

		if ( current_user_can( 'manage_options' ) ) {
			add_action( 'admin_print_styles', array( __CLASS__, 'add_notices' ) );
		}
	}
	

	/**
	 * Store notices to DB
	 */
	public static function store_notices() {
		update_option( 'sliced_admin_notices', self::get_notices() );
	}
	

	/**
	 * Get notices
	 * @return array
	 */
	public static function get_notices() {
		return self::$notices;
	}
	

	/**
	 * Remove all notices.
	 */
	public static function remove_all_notices() {
		self::$notices = array();
	}
	

	/**
	 * Reset notices for themes when switched or a new version of Sliced Invoices is installed.
	 */
	public static function reset_admin_notices() {
		// nothing yet to do here...
	}
	

	/**
	 * Show a notice.
	 * @param string $name
	 */
	public static function add_notice( $name ) {
		self::$notices = array_unique( array_merge( self::get_notices(), array( $name ) ) );
	}
	

	/**
	 * Remove a notice from being displayed.
	 * @param  string $name
	 */
	public static function remove_notice( $name ) {
		self::$notices = array_diff( self::get_notices(), array( $name ) );
		delete_option( 'sliced_admin_notice_' . $name );
	}
	

	/**
	 * See if a notice is being shown.
	 * @param  string  $name
	 * @return boolean
	 */
	public static function has_notice( $name ) {
		return in_array( $name, self::get_notices() );
	}
	

	/**
	 * Hide a notice if the GET variable is set.
	 */
	public static function hide_notices() {
		if ( isset( $_GET['sliced-hide-notice'] ) && isset( $_GET['_sliced_notice_nonce'] ) ) {
			if ( ! wp_verify_nonce( $_GET['_sliced_notice_nonce'], 'sliced_hide_notices_nonce' ) ) {
				wp_die( __( 'Action failed. Please refresh the page and retry.', 'sliced-invoices' ) );
			}

			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( __( 'Cheatin&#8217; huh?', 'sliced-invoices' ) );
			}

			$hide_notice = sanitize_text_field( $_GET['sliced-hide-notice'] );
			self::remove_notice( $hide_notice );
			
			if ( isset( $_GET['sliced-hide-transient'] ) && (int)$_GET['sliced-hide-transient'] > 0 ) {
				set_transient( 'sliced_hide_' . $hide_notice . '_notice', 1, intval( $_GET['sliced-hide-transient'] ) );
			}
			
			do_action( 'sliced_hide_' . $hide_notice . '_notice' );
		}
	}
	

	/**
	 * Add notices + styles if needed.
	 */
	public static function add_notices() {
	
		$notices = self::get_notices();
		
		if ( ! empty( $notices ) ) {
			foreach ( $notices as $notice ) {
				if ( ! empty( self::$core_notices[ $notice ] ) && apply_filters( 'sliced_show_admin_notice', true, $notice ) ) {
					add_action( 'admin_notices', array( __CLASS__, self::$core_notices[ $notice ] ) );
				} else {
					add_action( 'admin_notices', array( __CLASS__, 'output_custom_notices' ) );
				}
			}
		}
	}
	

	/**
	 * Add a custom notice.
	 * @param string $name
	 * @param string $notice_html
	 */
	public static function add_custom_notice( $name, $notice_html ) {
		self::add_notice( $name );
		update_option( 'sliced_admin_notice_' . $name, wp_kses_post( $notice_html ) );
	}
	

	/**
	 * Output any stored custom notices.
	 */
	public static function output_custom_notices() {
		$notices = self::get_notices();
		if ( ! empty( $notices ) ) {
			foreach ( $notices as $notice ) {
				if ( empty( self::$core_notices[ $notice ] ) ) {
					if ( ! get_transient( 'sliced_hide_' . $notice . '_notice' ) ) {
						$notice_html = get_option( 'sliced_admin_notice_' . $notice );
						if ( $notice_html ) {
							echo $notice_html;
						}
					}
				}
			}
		}
	}
	
	
	/**
	 * @since 3.5.0
	 */
	public static function invalid_payment_page_notice() {
		?>
		<div class="error sliced-message">
			<?php /* let's not make this one dismissable for now: <a class="sliced-message-close notice-dismiss" href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'sliced-hide-notice', 'invalid_payment_page' ), 'sliced_hide_notices_nonce', '_sliced_notice_nonce' ) ); ?>"><?php _e( 'Dismiss', 'sliced-invoices' ); ?></a> */ ?>
			<p><?php printf( __( '<strong>Your Confirmation Page setting is invalid.</strong> Please choose a valid Confirmation Page on the %sPayment Settings page%s. You will not be able to process payments until you do this.', 'sliced-invoices' ), '<a href="' . esc_url( admin_url( 'admin.php?page=sliced_payments' ) ) . '">', '</a>' ); ?></p>
		</div>
		<?php
	}
	
	
}

// Call the class
Sliced_Admin_Notices::init();
