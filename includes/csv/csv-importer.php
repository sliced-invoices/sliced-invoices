<?php

// Exit if accessed directly
if ( ! defined('ABSPATH') ) { exit; }


class Sliced_Csv_Importer {


	var $defaults = array(
		'sliced_title'           => null, // required
		'sliced_description'     => null,
		'sliced_author_id'       => null,
		'sliced_number'          => null,
		'sliced_created'         => null,
		'sliced_due'             => null,
		'sliced_valid'           => null,
		'sliced_items'           => null, // recommended
		'sliced_status'          => null, // recommended
		'sliced_client_email'    => null, // required
		'sliced_client_name'     => null, // recommended
		'sliced_client_business' => null,
		'sliced_client_address'  => null,
		'sliced_client_extra'    => null,
	);

	var $log = array();

	/**
	 * Determine value of option $name from database, $default value or $params,
	 * save it to the db if needed and return it.
	 *
	 * @param string $name
	 * @param mixed  $default
	 * @param array  $params
	 * @return string
	 */
	function process_option($name, $default, $params) {
		if (array_key_exists($name, $params)) {
			$value = stripslashes($params[$name]);
		} elseif (array_key_exists('_'.$name, $params)) {
			// unchecked checkbox value
			$value = stripslashes($params['_'.$name]);
		} else {
			$value = null;
		}
		$stored_value = get_option($name);
		if ($value == null) {
			if ($stored_value === false) {
				if (is_callable($default) &&
					method_exists($default[0], $default[1])) {
					$value = call_user_func($default);
				} else {
					$value = $default;
				}
				add_option($name, $value);
			} else {
				$value = $stored_value;
			}
		} else {
			if ($stored_value === false) {
				add_option($name, $value);
			} elseif ($stored_value != $value) {
				update_option($name, $value);
			}
		}
		return $value;
	}

	/**
	 * Plugin's interface
	 *
	 * @return void
	 */
	function display_importer_page() {

		$post_type = $this->process_option('csv_importer_type', 0, $_POST);

		if ('POST' == $_SERVER['REQUEST_METHOD']) {
			$this->post(compact('post_type'));
		}
		
		$exporter_url = add_query_arg( array(
			'tab' => 'exporter'
		) );
		$exporter_url = remove_query_arg( array(
			'sliced-message'
		), $exporter_url );
		
		
		// form HTML {{{
		?>

		<div class="wrap">
			<h2>Import CSV</h2>
			<p>The CSV Importer allows you to bulk import quotes and invoices from a standard CSV file.  The CSV file must be saved with UTF-8 encoding, and follow a specific format.  Please see Instructions below.<br>
			<a href="<?php echo esc_url( plugin_dir_url( __FILE__ ) ) ?>examples/sample.csv">Download sample CSV file</a></p>
			<p>You can also import CSV files created by the Sliced Invoices <a href="<?php echo esc_url( $exporter_url ); ?>">Export CSV</a> feature.</p>

			<form class="add:the-list: validate sliced_import_form" method="post" enctype="multipart/form-data">
				<!-- Import as draft -->
<!--                 <p>
				<input name="_csv_importer_import_as_draft" type="hidden" value="publish" />
				<label><input name="csv_importer_import_as_draft" type="checkbox" <?php /*if ('draft' == $opt_draft) { echo 'checked="checked"'; } */ ?> value="draft" /> Import posts as drafts</label>
				</p> -->

				<!-- Parent category -->
				<p>Quote or Invoice &nbsp;
				<select class="postform" id="csv_importer_type" name="csv_importer_type">
					<option selected="selected" value="sliced_quote">Quote</option>
					<option selected="selected" value="sliced_invoice">Invoice</option>
				</select>
				</p>

				<!-- File input -->
				<p><label for="csv_import">Upload file:</label><br/>
					<input name="csv_import" id="csv_import" type="file" value="" aria-required="true" /></p>
				<p class="submit"><input type="submit" class="button-primary" name="submit" value="Import" /></p>
			</form>

			<h2 style="font-size:1.5em">Instructions</h2>
			<h3>Line Items</h3>
			<p>To get a dollar value on your invoice or quotes, you need to fill in the 'sliced_items' field which will then add the line items. This field uses the pipe symbol "|" as a seperator. The format for this field is qty|title|description|amount. You can leave title and description blank but you still need to use the seperators like so qty|||amount.
			There should be only one line item per line (pressing alt+enter in a cell will create a new line).</p>
			<p><strong>Example:</strong></p>
<pre>
2|Web design|Designing the new site|120
1|Logo design|Create new logo for google.com|450
4.5|Web development||110
1.5|||2990
</pre>


			<h3>Assign item to existing client</h3>
			<p>To assign an invoice or quote to an existing user, simply add their email address in the 'sliced_client_email' field and leave all other client fields blank for that row.</p>

			<h3>Creating a new client</h3>
			<p>To create a new client, the 'sliced_client_email' field and 'sliced_client_name' field must be filled in as a minimum, We also recommend filling in the 'sliced_client_address' and 'sliced_client_business'.</p>

			<h3>List of Fields</h3>
			<p>You can use any of the below field names in the header row of the CSV file.</p>
			<ul style="padding: 0 0 0 20px">
				<li><strong>'sliced_title'</strong> - the title of the quote or invoice (required)  </li>
				<li><strong>'sliced_description'</strong> - the description of the quote or invoice   </li>
				<li><strong>'sliced_author_id'</strong> - the id of the author. Leave blank for current user</li>
				<li><strong>'sliced_number'</strong> - invoice or quote number. Leave blank if auto increment is turned on</li>
				<li><strong>'sliced_created'</strong> - created date. Leave blank for today</li>
				<li><strong>'sliced_due'</strong> - invoice due date</li>
				<li><strong>'sliced_valid'</strong> - quote valid until date</li>
				<li><strong>'sliced_items'</strong> - individual line items</li>
				<li><strong>'sliced_status'</strong> - invoice or quote status. ie sent, unpaid, paid, overdue. Defaults to Draft if left blank</li>
				<li><strong>'sliced_client_email'</strong> - email of the client (required)</li>
				<li><strong>'sliced_client_name'</strong> - name of the client (only use if client does not already exist)</li>
				<li><strong>'sliced_client_business'</strong> - clients business name</li>
				<li><strong>'sliced_client_address'</strong> - clients adress</li>
				<li><strong>'sliced_client_extra'</strong> - clients extra info (phone number, business number etc)</li>
			</ul>
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
	function post($options) {

		if (empty($_FILES['csv_import']['tmp_name'])) {
			$this->log['error'][] = 'No file uploaded, aborting.';
			$this->print_messages();
			return;
		}

		if (!current_user_can('publish_pages') || !current_user_can('publish_posts')) {
			$this->log['error'][] = 'You don\'t have the permissions to publish posts and pages. Please contact the blog\'s administrator.';
			$this->print_messages();
			return;
		}

		require_once 'File_CSV_DataSource/DataSource.php';

		$time_start = microtime(true);
		$csv = new File_CSV_DataSource;
		
		// we can't do sanitize_file_name( $_FILES['csv_import']['tmp_name'] ) here,
		// because it strips all slashes from the file's path, rendering it useless.
		// also, tmp_name is created by the server... it is not a user-generated input
		// field, so there's no security risk here.
		$file = $_FILES['csv_import']['tmp_name'];
		
		$this->stripBOM($file);

		if (!$csv->load($file)) {
			$this->log['error'][] = 'Failed to load file, aborting.';
			$this->print_messages();
			return;
		}
		
		// pad shorter rows with empty values
		$csv->symmetrize();
		
		// check and validate the file before commiting to inserting posts
		$validate = $this->validate_the_entries( $csv->connect(), $options );
		if( $validate !== 'ok' ) {
			$this->print_messages();
			return;
		}

		// WordPress sets the correct timezone for date functions somewhere
		// in the bowels of wp_insert_post(). We need strtotime() to return
		// correct time before the call to wp_insert_post().
		$tz = get_option('timezone_string');
		if ($tz && function_exists('date_default_timezone_set')) {
			date_default_timezone_set($tz);
		}

		$imported = 0;
		$skipped = 0;
		
		foreach ($csv->connect() as $csv) {
			if ($post_id = $this->create_post( $csv, $options )) {
				$this->update_number( $post_id, $options );
				$imported++;
			} else {
				$skipped++;
			}
		}

		if (file_exists($file)) {
			@unlink($file);
		}

		$exec_time = microtime(true) - $time_start;

		if ($skipped) {
			$this->log['notice'][] = "<b>Skipped {$skipped} items (most likely due to empty title).</b>";
		}
		$this->log['notice'][] = sprintf("<b>Imported {$imported} items in %.2f seconds.</b>", $exec_time);
		$this->print_messages();
	}


	/**
	 * Check and validate each entry in the CSV file
	 *
	 * @param array $options
	 * @return void
	 */
	function validate_the_entries( $csv_data, $options ) {

		$post_type = $this->set_post_type( $options );
		
		$count = 1;
		foreach ($csv_data as $csv) {
			if ( isset( $csv['__quote_title'] ) ) {
				// this is a full quote export, bypass
				return 'ok';
			}		
			if ( isset( $csv['__invoice_title'] ) ) {
				// this is a full invoice export, bypass
				return 'ok';
			}

			if( empty( $csv['sliced_client_email'] ) ) {
				$this->log['error'][$count] = "Client email address is missing in row {$count}";
			}
			if( empty( $csv['sliced_title'] ) ) {
				$this->log['error'][$count] = "Title is missing in row {$count}";
			}

			// validate the email field
			if( $csv['sliced_client_email'] ) {
				$email = trim( convert_chars( $csv['sliced_client_email'] ) );
				if( ! is_email( $email ) ) {
					$this->log['error'][$count] = "Email address appears invalid in row {$count}";
				}
				if( ! email_exists( $email ) && empty( $csv['sliced_client_name'] ) ) {
					$this->log['error'][$count] = "Adding a new email address requires a new name in row {$count}";
				}
				if( ! email_exists( $email ) && username_exists( $csv['sliced_client_name'] ) ) {
					$this->log['error'][$count] = "The client name in row {$count} already exists and does not match the emai. Change the client name to create a new user or remove the name and use the correct email for that user.";
				}
			}

			// validate the items field
			if( !empty( $csv['sliced_items'] ) ) {
				$items  = explode( "\n", convert_chars( $csv['sliced_items'] ) );
				$items  = array_filter( $items ); // remove any empty items
				if( $items ) :
					foreach ( $items as $item ) {
						$pipes = substr_count( $item, '|');
						if( $pipes != 3) {
							$this->log['error'][$count] = "There needs to be 3 pipe symbols (|) per line item in row {$count}. There is currently {$pipes}.";
						}
					}
				endif;
			}

			if( !empty( $csv['sliced_number'] ) ) {
				$check = get_option( $post_type . 's' );
				if ( isset( $check['increment'] ) && $check['increment'] == 'on' ) {
					$this->log['error'][$count] = "You have Auto Increment turned on in the Settings but you have entered a number in row {$count}. Either turn off auto increment or leave sliced_number field blank (recommend leaving blank).";
				}
			}

			$count++;

		}

		if( !empty( $this->log ) ) {
			return $this->log;
		}

		return 'ok';

	}



	function create_post($data, $options) {

		$post_type = $this->set_post_type( $options );

		if ( isset( $data['__quote_title'] ) ) {
			// source is a full quote export
			$new_post = array(
				'post_title'   => convert_chars($data['__quote_title']),
				'post_content' => convert_chars($data['__quote_description']),
				'post_status'  => 'publish',
				'post_type'    => 'sliced_quote',
				'post_author'  => $this->get_auth_id(),
			);
		} elseif ( isset( $data['__invoice_title'] ) ) {
			// source is a full invoice export
			$new_post = array(
				'post_title'   => convert_chars($data['__invoice_title']),
				'post_content' => convert_chars($data['__invoice_description']),
				'post_status'  => 'publish',
				'post_type'    => 'sliced_invoice',
				'post_author'  => $this->get_auth_id(),
			);
		} else {
			// source is a manual file
			$data = array_merge($this->defaults, $data);
			$new_post = array(
				'post_title'   => convert_chars($data['sliced_title']),
				'post_content' => '',
				'post_status'  => 'publish',
				'post_type'    => $post_type,
				'post_author'  => $this->get_auth_id($data['sliced_author_id']),
			);
		}

		// create!
		$id = wp_insert_post($new_post);

		// add the status
		$this->add_the_status( $id, $data, $post_type );

		// add the custom field data
		$this->add_custom_fields( $id, $data, $post_type );

		// add the client or use existing
		$this->maybe_add_client( $id, $data, $post_type );

		return $id;

	}

	
	function set_post_type( $options ) {
		$post_type = isset($options['post_type']) ? $options['post_type'] : 'sliced_quote';
		return $post_type;
	}
	

	function add_the_status( $id, $data, $post_type ) {

		$status = array();
		$terms = false;
		
		if ( isset( $data['__quote_status'] ) ) {
			
			// source is a full quote export
			$taxonomy = 'quote_status';
			$terms = explode( ',', $data['__quote_status'] );
			if ( is_array( $terms ) ) {
				foreach ( $terms as $term ) {
					$result = term_exists( trim( strtolower( convert_chars($data['__quote_status'] ) ) ), $taxonomy );
					if ( $result !== 0 && $result !== null ) {
						$status[] = (int)$result['term_id'];
					}
				}
				
			}
			
		} elseif ( isset( $data['__invoice_status'] ) ) {
			
			// source is a full invoice export
			$taxonomy = 'invoice_status';
			$terms = explode( ',', $data['__invoice_status'] );
			if ( is_array( $terms ) ) {
				foreach ( $terms as $term ) {
					$result = term_exists( trim( strtolower( convert_chars($data['__invoice_status'] ) ) ), $taxonomy );
					if ( $result !== 0 && $result !== null ) {
						$status[] = (int)$result['term_id'];
					}
				}
				
			}
			
		} else {
		
			// source is manual file
			$taxonomy = str_replace( 'sliced_', '', $post_type ) . '_status';
			if ( ! empty( $data['sliced_status'] ) ) {
				$status = term_exists( trim( strtolower( convert_chars($data['sliced_status'] ) ) ), $taxonomy );
				if ( $status !== 0 && $status !== null ) {
					$status[] = (int)$status['term_id'];
				} else {
					$status = array( 'draft' );
				}
			}
			
		}
		
		// set to a draft if status field is blank
		if ( empty( $status ) ) {
			$status = array( 'draft' );
		}
		
		wp_set_object_terms( $id, $status, $taxonomy );

	}


	function add_custom_fields( $id, $data, $post_type ) {
	
		if ( isset( $data['__quote_status'] ) || isset( $data['__invoice_status'] ) ) {
		
			// source is a full export file, add all fields
			global $wpdb;
			
			foreach ( $data as $key => $value ) {
				if ( ( substr( $key, 0, 7 ) === '_sliced' || substr( $key, 0, 6 ) === 'sliced' ) && ! empty( $value ) ) {
					// $value may be serialized data. If we use:
					// update_post_meta( $id, $key, $value );
					// ...it will encode the data again, giving us an unusable double-encoded mess.
					// so we do this instead, which allows us to insert $value verbatim:
					$wpdb->query(
						$wpdb->prepare(
							"INSERT INTO $wpdb->postmeta (post_id, meta_key, meta_value) VALUES(%s, %s, %s)",
							$id,
							$key,
							$value
						)
					);
				}
			}
			
			if( !empty( $data['sliced_items'] ) ) {
				// the line items
				$items  = explode( "\n", convert_chars( $data['sliced_items'] ) );
				$items  = array_filter( $items ); // remove any empty items

				if( $items ) :
					foreach ( $items as $item ) {
						list( $qty, $title, $desc, $amount ) = explode( '|', $item );
						$qty    = ! empty($qty) ? trim( $qty ) : '1';
						$title  = trim( $title );
						$desc   = trim( $desc );
						$amount = ! empty($amount) ? trim( $amount ) : '0';

						$items_array[] = array(
							'qty'           => esc_html( $qty ),
							'title'         => esc_html( $title ),
							'description'   => esc_html( $desc ),
							'amount'        => esc_html( $amount ),
						);
					}
				endif;

				add_post_meta( $id, '_sliced_items', $items_array );
			}
		
		} else {
		
			// source is a manual file, process accordingly
		
			// check for data and update if exists
			foreach ($data as $k => $v) {

				// if the number field is blank, it will look to add these sequentially if auto increment is on
				// if it is not on and there is no number, it remains blank
				if( $k == 'sliced_number' ) {
					if( empty($v) ) {
						$number = $post_type == 'sliced_invoice' ? sliced_get_next_invoice_number( $id ) : sliced_get_next_quote_number( $id );
					} else {
						$number = $v;
					}
					add_post_meta( $id, '_' . $post_type . '_number', $number );
				}

				if( $k == 'sliced_description' && !empty($v) ) {
					add_post_meta( $id, '_sliced_description', wpautop( convert_chars( $v ) ) );
				}

				if( $k == 'sliced_created' ) {
					if( empty($v) ) { // if column is empty, use time()
						$v = current_time( 'timestamp' );
					} else {
						$v = strtotime($v);
					}
					add_post_meta( $id, '_' . $post_type . '_created', $v );
				}

				if( $k == 'sliced_due' && !empty($v) ) {
					add_post_meta( $id, '_' . $post_type . '_due', strtotime($v) );
				}

				if( $k == 'sliced_valid' && !empty($v) ) {
					add_post_meta( $id, '_' . $post_type . '_valid', strtotime($v) );
				}

			}

			if( !empty( $data['sliced_items'] ) ) {
				// the line items
				$items  = explode( "\n", convert_chars( $data['sliced_items'] ) );
				$items  = array_filter( $items ); // remove any empty items

				if( $items ) :
					foreach ( $items as $item ) {
						list( $qty, $title, $desc, $amount ) = explode( '|', $item );
						$qty    = ! empty($qty) ? trim( $qty ) : '1';
						$title  = trim( $title );
						$desc   = trim( $desc );
						$amount = ! empty($amount) ? trim( $amount ) : '0';

						$items_array[] = array(
							'qty'           => esc_html( $qty ),
							'title'         => esc_html( $title ),
							'description'   => esc_html( $desc ),
							'amount'        => esc_html( $amount ),
						);
					}
				endif;

				add_post_meta( $id, '_sliced_items', $items_array );
			}

			// insert the prefix and the number
			$prefix = $post_type == 'sliced_invoice' ? sliced_get_invoice_prefix() : sliced_get_quote_prefix();
			add_post_meta( $id, '_' . $post_type . '_prefix', $prefix );
			
			// insert the suffix
			$suffix = $post_type == 'sliced_invoice' ? sliced_get_invoice_suffix() : sliced_get_quote_suffix();
			add_post_meta( $id, '_' . $post_type . '_suffix', $suffix );
			
		}

	}



	/**
	 * Check for existing client and add new one if does not exist.
	 * @since  1.0.0
	 */
	public function maybe_add_client( $id, $data, $post_type ) {

		if ( isset( $data['__quote_client'] ) ) {
			// source is a full quote export
			$client_array = array(
				'name'       => trim( convert_chars( $data['__quote_client_name'] ) ),
				'email'      => trim( convert_chars( $data['__quote_client_email'] ) ),
				'business'   => convert_chars( $data['__quote_client'] ),
				'address'    => convert_chars( $data['__quote_client_address'] ),
				'extra_info' => convert_chars( $data['__quote_client_extra_info'] ),
				'post_id'    => $id,
			);
		} elseif ( isset( $data['__invoice_client'] ) ) {
			// source is a full invoice export
			$client_array = array(
				'name'       => trim( convert_chars( $data['__invoice_client_name'] ) ),
				'email'      => trim( convert_chars( $data['__invoice_client_email'] ) ),
				'business'   => convert_chars( $data['__invoice_client'] ),
				'address'    => convert_chars( $data['__invoice_client_address'] ),
				'extra_info' => convert_chars( $data['__invoice_client_extra_info'] ),
				'post_id'    => $id,
			);
		} else {
			$client_array = array(
				'name'       => trim( convert_chars( $data['sliced_client_name'] ) ),
				'email'      => trim( convert_chars( $data['sliced_client_email'] ) ),
				'business'   => convert_chars( $data['sliced_client_business'] ),
				'address'    => wpautop( convert_chars( $data['sliced_client_address'] ) ),
				'extra_info' => wpautop( convert_chars( $data['sliced_client_extra'] ) ),
				'post_id'    => $id,
			);
		}

		// if client does not exist, create one
		if( ! email_exists( $client_array['email'] ) ) {

			// generate random password
			$password = wp_generate_password( 10, true, true );
			// create the user
			$client_id = wp_create_user( $client_array['name'], $password, $client_array['email'] );
			// add the user meta
			add_user_meta( $client_id, '_sliced_client_business', $client_array['business'] );
			add_user_meta( $client_id, '_sliced_client_address', $client_array['address'] );
			add_user_meta( $client_id, '_sliced_client_extra_info', $client_array['extra_info'] );

		} else {
			// get the user
			$client = get_user_by( 'email', $client_array['email'] );
			$client_id = $client->ID;
		}

		// add the user to the post
		update_post_meta( $client_array['post_id'], '_sliced_client', $client_id );

		return $client_id;
	}




	function update_number( $id, $csv_options ) {

		$options       = get_option( $csv_options['post_type'] . 's' );
		$last_number   = sliced_get_number( $id );

		if( (int)$options['number'] <= (int)$last_number ) {

			// clean up the number
			$length         = strlen( (string)$options['number'] ); // get the length of the number
			$new_number     = (int)$last_number + 1; // increment number
			$number         = zeroise( $new_number, $length ); // return the new number, ensuring correct length (if using leading zeros)

			// set the number in the options as the new, next number and update it.
			$options['number'] = (string)$number;
			update_option( $csv_options['post_type'] . 's', $options);

		}

	}


	function get_auth_id( $author_id = false ) {
		
		if (is_numeric($author_id)) {
			return $author_id;
		}

		$current_user = wp_get_current_user();
		$author_id = $current_user->ID;

		return ($author_id) ? $author_id : 1;
	}
	

	/**
	 * Convert date in CSV file to 1999-12-31 23:52:00 format
	 *
	 * @param string $data
	 * @return string
	 */
	function parse_date($data) {
		$timestamp = strtotime($data);
		if (false === $timestamp) {
			return '';
		} else {
			return date('Y-m-d H:i:s', $timestamp);
		}
	}



	/**
	 * Try to split lines of text correctly regardless of the platform the text
	 * is coming from.
	 */
	function split_lines($text) {
		$lines = preg_split("/(\r\n|\n|\r)/", $text);
		return $lines;
	}

	/**
	 * Delete BOM from UTF-8 file.
	 *
	 * @param string $fname
	 * @return void
	 */
	function stripBOM($fname) {
		$res = fopen($fname, 'rb');
		if (false !== $res) {
			$bytes = fread($res, 3);
			if ($bytes == pack('CCC', 0xef, 0xbb, 0xbf)) {
				$this->log['notice'][] = 'Getting rid of byte order mark...';
				fclose($res);

				$contents = file_get_contents($fname);
				if (false === $contents) {
					trigger_error('Failed to get file contents.', E_USER_WARNING);
				}
				$contents = substr($contents, 3);
				$success = file_put_contents($fname, $contents);
				if (false === $success) {
					trigger_error('Failed to put file contents.', E_USER_WARNING);
				}
			} else {
				fclose($res);
			}
		} else {
			$this->log['error'][] = 'Failed to open file, aborting.';
		}
	}



}
