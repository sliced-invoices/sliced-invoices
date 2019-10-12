<?php

// Exit if accessed directly
if ( ! defined('ABSPATH') ) { exit; }


class Sliced_Csv_Exporter {

	var $log = array();

	/**
	 * Plugin's interface
	 *
	 * @return void
	 */
	function display_exporter_page() {

		if ('POST' == $_SERVER['REQUEST_METHOD']) {
			//$this->post();
		}

		// form HTML {{{
		?>

		<div class="wrap">
			<h2>Export CSV</h2>
			<p>The CSV Exporter allows you to bulk export quotes and invoices to a standard CSV file.  This is a great way to transfer data between sites, since you can easily import this file to another Sliced Invoices installation.  You can also use this file to feed quote/invoice data into other systems as needed.</p>

			<form class="add:the-list: validate sliced_export_form" method="post" enctype="multipart/form-data">
				<!-- Parent category -->
				<p>Quote or Invoice &nbsp;
				<select class="postform" id="csv_exporter_type" name="csv_exporter_type">
					<option selected="selected" value="sliced_quote">Quote</option>
					<option selected="selected" value="sliced_invoice">Invoice</option>
				</select>
				</p>
				<p class="submit"><input type="submit" class="button-primary" name="submit" value="Export" /></p>
				<?php wp_nonce_field( 'sliced_invoices_export_csv', 'sliced-invoices-export-csv-nonce' ); ?>
			</form>
		</div><!-- end wrap -->

		<?php
		// end form HTML }}}
	}


	function print_messages() {

		if (!empty($this->log)) {

		// messages HTML {{{
		?>

		<div class="wrap">
			<?php if (!empty($this->log['error'])): ?>

			<div class="error">
				<?php foreach ($this->log['error'] as $error): ?>
					<p><?php echo $error; ?></p>
				<?php endforeach; ?>
			</div>

			<?php endif; ?>

			<?php if (!empty($this->log['notice'])): ?>

			<div class="updated fade">
				<?php foreach ($this->log['notice'] as $notice): ?>
					<p><?php echo $notice; ?></p>
				<?php endforeach; ?>
			</div>

			<?php endif; ?>
		</div><!-- end wrap -->

		<?php
		// end messages HTML }}}

			$this->log = array();
		}

	}


	/**
	 * Handle POST submission
	 *
	 * @param array $options
	 * @return void
	 */
	function post( $options = array() ) {
	
		$this->print_messages();
		
	}


}
