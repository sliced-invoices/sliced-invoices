<?php
// Exit if accessed directly
if ( ! defined('ABSPATH') ) { exit; }

// define the font location for PDF's
if ( ! defined( "_MPDF_SYSTEM_TTFONTS") ) {
	$path = plugin_dir_path( __FILE__ ) . 'fonts/';
	define("_MPDF_SYSTEM_TTFONTS",  $path );
}

/**
 * The public-facing functionality of the plugin.
 *
 * @link       http://slicedinvoices.com
 * @package    Sliced_Invoices
 */

class Sliced_Public {

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
		
		add_filter( 'comment_post_redirect', array( $this, 'redirect_after_comment_in_quote' ) );
		add_filter( 'pre_comment_approved' , array( $this, 'auto_approve_comments_in_quote' ), '99', 2 );

	}
	
	// fix redirect after non-logged in user posts a comment to a quote (i.e. the client)
	public function redirect_after_comment_in_quote($location) {
		return $_SERVER["HTTP_REFERER"];
	}
	
	// auto approve quote comments for non-logged in user
	public function auto_approve_comments_in_quote( $approved, $commentdata ) {
		$post = get_post( $commentdata['comment_post_ID'] );
		if( $post->post_type == 'sliced_quote' ) {
			return 1;
		}
		return $approved;
	}

	/**
	 * Register the stylesheets for the public-facing side of the site.
	 *
	 * @since   2.0.0
	 */
	public function enqueue_styles() {

		wp_register_style( 'fontawesome', plugins_url( $this->plugin_name ) . '/public/css/font-awesome.min.css', null, $this->version );
		wp_register_style( 'bootstrap', plugins_url( $this->plugin_name ) . '/public/css/bootstrap.min.css', null, $this->version );
		wp_register_style( 'style', plugins_url( $this->plugin_name ) . '/public/css/style.css', null, $this->version );

		// only load dashicons on payment
		$payments = get_option( 'sliced_payments' );
        if ( is_page( (int)$payments['payment_page'] ) ) {
			wp_print_styles( 'dashicons' );
		}

		wp_print_styles( 'open-sans' );
		wp_print_styles( 'fontawesome' );
		wp_print_styles( 'bootstrap' );
		wp_print_styles( 'style' );

	}

	/**
	 * Register the stylesheets for the invoices only.
	 *
	 * @since   2.0.0
	 */
	public function enqueue_invoice_styles() {

		// deregister and register again to remove dependency of dashicons as it messes up PDF output
		wp_deregister_style( 'thickbox' );
		wp_register_style( 'thickbox', includes_url( 'js/thickbox/thickbox.css' ), null, $this->version );

		wp_register_style(
			'template',
			apply_filters( 'sliced_invoice_template_css', plugins_url( $this->plugin_name ) . '/public/css/' . esc_html( sliced_get_invoice_template() ) . '.css' ),
			null,
			$this->version
		);

		// add the users custom css - needs to be before printing
		$custom_css = sliced_get_invoice_css();
        wp_add_inline_style( 'template', apply_filters( 'sliced_invoice_template_custom_css', esc_html( $custom_css ) ) );

		wp_print_styles( 'thickbox' );
		wp_print_styles( 'template' );

	}

	/**
	 * Print the scripts for the invoices.
	 *
	 * @since   2.0.0
	 */
	public function enqueue_invoice_scripts() {

		wp_print_scripts( 'thickbox' );

	}



	/**
	 * Register the stylesheets for the quotes only.
	 *
	 * @since   2.0.0
	 */
	public function enqueue_quote_styles() {

		// deregister and register again to remove dependency of dashicons as it messes up PDF output
		wp_deregister_style( 'thickbox' );
		wp_register_style( 'thickbox', includes_url( 'js/thickbox/thickbox.css' ), null, $this->version );

		wp_register_style(
			'template',
			apply_filters( 'sliced_quote_template_css', plugins_url( $this->plugin_name ) . '/public/css/' . esc_html( sliced_get_quote_template() ) . '.css' ),
			null,
			$this->version
		);

		// add the users custom css - needs to be before printing
		$custom_css = sliced_get_quote_css();
        wp_add_inline_style( 'template', apply_filters( 'sliced_quote_template_custom_css', esc_html( $custom_css ) ) );

        wp_print_styles( 'thickbox' );
		wp_print_styles( 'template' );

	}


	/**
	 * Print the scripts for the quotes.
	 *
	 * @since   2.0.0
	 */
	public function enqueue_quote_scripts() {
		wp_print_scripts( 'thickbox' );
	}


	/**
	 * Fix for cloudflare caching scripts
	 *
	 * @since   2.10
	 */
	public function add_defer_attribute($tag) {

		// array of scripts to defer
		$scripts_to_defer = array('thickbox', 'jquery' );

		foreach($scripts_to_defer as $defer_script){
			if(true == strpos($tag, $defer_script ) )
				return str_replace( ' src', ' data-cfasync="false" src', $tag );
		}

		return $tag;
	}

	/**
	 * remove private and protected from page title.
	 *
	 * @since   2.0.0
	 */
	public function title_format( $content ) {
		return '%s';
	}



	/**
	 * Set up the template for the invoice and quote.
	 *
	 * @since   2.0.0
	 */
	public function invoice_quote_template( $template ) {

		if ( get_post_type() == 'sliced_invoice' ) {

			if ( ! post_password_required() ) {

				$template = $this->sliced_get_template_part( 'sliced-invoice-display' );

			}

		} elseif ( get_post_type() == 'sliced_quote' ) {

			if ( ! post_password_required() ) {

				$template = $this->sliced_get_template_part( 'sliced-quote-display' );

			}

		}

		return $template;
	}


	/**
	 * Set up the templates for the payment.
	 *
	 * @since   2.0.0
	 */
	public function payment_templates( $template ) {

		$payments = get_option( 'sliced_payments' );

		if ( is_page( (int)$payments['payment_page'] ) ) {

			$template = $this->sliced_get_template_part( 'sliced-payment-display' );

		}

		return $template;

	}

	/**
	 * Retrieves a template part
	 *
	 * @since   2.0.0
	 */
	private function sliced_get_template_part( $slug ) {
		// Execute code for this part
		do_action( 'get_template_part_' . $slug, $slug );

		$template = $slug . '.php';

		// Allow template parts to be filtered
		$template = apply_filters( 'sliced_get_template_part', $template, $slug );

		// Return the part that is found
		return $this->sliced_locate_template( $template );
	}


    /**
	 * Retrieve the name of the highest priority template file that exists.
	 *
	 * @since   2.0.0
	 */
	private function sliced_locate_template( $template_name ) {
		// No file found yet
		$located = false;

		// Trim off any slashes from the template name
		$template_name = ltrim( $template_name, '/' );

		// Check child theme first
		if ( file_exists( trailingslashit( get_stylesheet_directory() ) . 'sliced/' . $template_name ) ) {
			$located = trailingslashit( get_stylesheet_directory() ) . 'sliced/' . $template_name;

		// Check parent theme next
		} elseif ( file_exists( trailingslashit( get_template_directory() ) . 'sliced/' . $template_name ) ) {
			$located = trailingslashit( get_template_directory() ) . 'sliced/' . $template_name;

		} elseif ( file_exists( plugin_dir_path( dirname( __FILE__ ) ) . 'public/templates/' .  $template_name ) ) {
			$located = plugin_dir_path( dirname( __FILE__ ) ) . 'public/templates/' .  $template_name;

		} elseif ( file_exists( plugin_dir_path( __FILE__ ) . 'public/templates/' .  $template_name ) ) {
			$located = plugin_dir_path( __FILE__ ) . 'public/templates/' .  $template_name;

		}

		$located = apply_filters( 'sliced_locate_new_templates', $located, $template_name );

		return $located;
	}




	/**
	 * Display the top bar on invoices
	 *
	 * @since   2.0.0
	 */
	public function display_invoice_top_bar() {

		if ( get_post_type() != 'sliced_invoice' )
			return;

		?>

		<div class="row sliced-top-bar no-print">
			<div class="container">

				<div class="col-xs-12 col-sm-6">
					<?php do_action( 'sliced_invoice_top_bar_left' ) ?>
				</div>

				<div class="col-xs-12 col-sm-6 text-right">
					<?php do_action( 'sliced_invoice_top_bar_right' ) ?>
				</div>

			</div>
		</div>

		<?php

	}


	/**
	 * Display the top bar on quotes
	 *
	 * @since   2.0.0
	 */
	public function display_quote_top_bar() {

		if ( get_post_type() != 'sliced_quote' )
			return;

		?>

		<div class="row sliced-top-bar no-print">
			<div class="container">

				<div class="col-xs-12 col-sm-6">
					<?php do_action( 'sliced_quote_top_bar_left' ) ?>
				</div>

				<div class="col-xs-12 col-sm-6 text-right">
					<?php do_action( 'sliced_quote_top_bar_right' ) ?>
				</div>

			</div>
		</div>

		<?php

	}

	/**
	 * Display the comments section on quotes
	 *
	 * @since   2.0.0
	 */
	public function display_quote_comments() {

		if ( get_post_type() != 'sliced_quote' )
			return;

		if( ! comments_open( get_the_ID() ) )
			return;
		?>

		<div class="row sliced-comments no-print">
			<div class="container">

				<div class="col-xs-12">

					<div id="comments" class="comments-area">

					<?php 				                //Gather comments for a specific page/post
					$comments = get_comments(array(
						'post_id' => get_the_ID(),
							'status' => 'approve' //Change this to the type of comments to be displayed
							));

							if( $comments ) { ?>

							<h3 class="comments-title">
								<?php
								printf( _nx( 'One comment', '%1$s comments', get_comments_number(), 'comments title', 'sliced-invoices' ),
									number_format_i18n( get_comments_number() ) );
									?>
								</h3>

								<ol class="commentlist">
									<?php
								//Display the list of comments
									wp_list_comments(array(
									'per_page' => 10, //Allow comment pagination
									'reverse_top_level' => false //Show the latest comments at the top of the list
									), $comments);
									?>
								</ol>

								<?php
							}
							// If comments are closed and there are comments, let's leave a little note, shall we?
							if ( ! comments_open() && get_comments_number() && post_type_supports( get_post_type(), 'comments' ) ) :
								?>
							<p class="no-comments"><?php _e( 'Comments are closed.', 'twentyfifteen' ); ?></p>

						<?php endif; ?>


						<?php $user = wp_get_current_user();
						
						$comment_args = array( 'title_reply'=>'Add a comment',

							'fields' => apply_filters( 'comment_form_default_fields', array(
								'author' => '',
								'email'  => '',
								'url'    => ''
								)
							),

							'comment_field' => '<p>' .
							'<textarea id="comment" name="comment" cols="45" rows="8" aria-required="true"></textarea>' .
							( $user->ID > 0 ? '' : '<input type="hidden" name="author" value="'.sliced_get_client_business().'" />' ) .
							( $user->ID > 0 ? '' : '<input type="hidden" name="email" value="'.sliced_get_client_email().'" />' ) .
							'</p>',
							'comment_notes_after' => '',
							);

							comment_form($comment_args); ?>

					</div><!-- .comments-area -->

				</div>

			</div>
		</div>

		<?php

	}

}
