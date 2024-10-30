(function( $ ) {
	'use strict';
	
	if ( typeof window.sliced_invoices === "undefined" ) {
		window.sliced_invoices = {};
	}
	
	
	/**
	 * Hooks
	 */
	sliced_invoices.hooks = {
		'sliced_invoice_totals': []
	};
	
	
	/**
	 * Utils
	 */
	sliced_invoices.utils = {
		symbol:       sliced_payments.currency_symbol,
		position:     sliced_payments.currency_pos,
		thousand_sep: sliced_payments.thousand_sep,
		decimal_sep:  sliced_payments.decimal_sep,
		decimals:     parseInt( sliced_payments.decimals )
	}
	
	// sorts out the number to enable calculations
	sliced_invoices.utils.rawNumber = function (x) {
		if ( typeof x === "undefined" ) {
			return 0;
		}
		// remove currency symbol, if any
		if ( typeof x.replace !== "undefined" ) {
			x = x.replace( sliced_invoices.utils.symbol, '' );
		}
		// removes the thousand seperator
		if ( sliced_invoices.utils.thousand_sep > '' ) {
			var parts = x.toString().split(sliced_invoices.utils.thousand_sep);
			parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, '');
			var amount = parts.join('');
		} else {
			var amount = x.toString();
		}
		// makes the decimal seperator a period
		var output = amount.toString().replace(/\,/g, '.');
		output = parseFloat( output );
		if ( isNaN( output ) ) {
			output = 0;
		}
		return output;
	}
	

	// formats number into users format
	sliced_invoices.utils.formattedNumber = function (nStr) {
		var num = nStr.split('.');
		var x1 = num[0];
		var x2 = num.length > 1 ? sliced_invoices.utils.decimal_sep + num[1] : '';
		if ( sliced_invoices.utils.thousand_sep > '' ) {
			var rgx = /(\d+)(\d{3})/;
			while (rgx.test(x1)) {
				x1 = x1.replace(rgx, '$1' + sliced_invoices.utils.thousand_sep + '$2');
			}
		}
		return x1 + x2;
	}


	// format the amounts
	sliced_invoices.utils.formattedAmount = function (amount) {
		// do the symbol position formatting
		var formatted = 0;
		var amount = new Decimal( amount );
		amount = amount.toFixed( sliced_invoices.utils.decimals );
		switch (sliced_invoices.utils.position) {
			case 'left':
				formatted = sliced_invoices.utils.symbol + sliced_invoices.utils.formattedNumber( amount );
				break;
			case 'right':
				formatted = sliced_invoices.utils.formattedNumber( amount ) + sliced_invoices.utils.symbol;
				break;
			case 'left_space':
				formatted = sliced_invoices.utils.symbol + ' ' + sliced_invoices.utils.formattedNumber( amount );
				break;
			case 'right_space':
				formatted = sliced_invoices.utils.formattedNumber( amount ) + ' ' + sliced_invoices.utils.symbol;
				break;
			default:
				formatted = sliced_invoices.utils.symbol + sliced_invoices.utils.formattedNumber( amount );
				break;
		}
		return formatted;
	}
	

    /**
	 * Totals
	 */
	function setupNewLine( $newRow ){
	
		var lastRow = $($newRow.target).children('.cmb-repeatable-grouping').last();
		
		// clear line total
        $(lastRow).find('.line_total').html( sliced_invoices.utils.formattedAmount(0) );
		
		// set checkbox defaults
		$(lastRow).find('input[type="checkbox"]').attr('checked',true);

    };
    $(document).on( 'cmb2_add_row', setupNewLine );


	// calculate the totals on the fly when editing or adding a quote or invoice
 	function workOutTotals(){

		sliced_invoices.totals = {
			'sub_total':         new Decimal( 0 ),
			'sub_total_taxable': new Decimal( 0 ),
			'tax':               new Decimal( 0 ),
			'discounts':         new Decimal( 0 ),
			'payments':          new Decimal( 0 ),
			'total':             new Decimal( 0 ),
			'total_due':         new Decimal( 0 )
		};

        // work out the line item totals
        $('.sliced input.item_amount').each( function() {

            var group = $(this).parents('.cmb-repeatable-grouping');
            var index = group.data('iterator');
			
			var qty = new Decimal( sliced_invoices.utils.rawNumber( $( group ).find( '[name="_sliced_items[' + index + '][qty]"]' ).val() ) );
			var amt = new Decimal( sliced_invoices.utils.rawNumber( $(this).val() ) );
			
			// for historical reasons, the "adjust" field is named "tax" internally,
			// but it is unrelated to the actual tax field(s) in use today.
			var adj = new Decimal( sliced_invoices.utils.rawNumber( $( group ).find( '[name="_sliced_items[' + index + '][tax]"]' ).val() ) );
			
			var taxable = $( group ).find( '[name="_sliced_items[' + index + '][taxable]"]' ).is(":checked");
			
            // work out the line totals and taxes/discounts
            var line_adj        = adj.equals( 0 ) ? adj : adj.div( 100 ); // 0.10
            var line_sub_total  = qty.times( amt ); // 100
            var line_adj_amt    = line_sub_total.times( line_adj ); // 10
            var line_total      = line_sub_total.plus( line_adj_amt ); // 110

            // display the calculated amount
            $(this).parents('.cmb-type-text-money').find('.line_total').html( sliced_invoices.utils.formattedAmount( line_total.toNumber() ) );
            
			sliced_invoices.totals.sub_total = sliced_invoices.totals.sub_total.plus( line_total );
			if ( taxable ) {
				sliced_invoices.totals.sub_total_taxable = sliced_invoices.totals.sub_total_taxable.plus( line_total );
			}

	    });
		
		// add discounts, if any (part 1 of 2 -- before tax)
		var discountValue = sliced_invoices.utils.rawNumber( $( '#_sliced_discount' ).val() );
		if ( $( 'input[name="_sliced_discount_type"]:checked' ).val() === 'percentage' ) {
			var discountPercentage = new Decimal( discountValue );
			discountPercentage = discountPercentage.div( 100 );
		}
		
		if ( $( 'input[name="_sliced_discount_tax_treatment"]:checked' ).val() === 'before' ) {
			if ( $( 'input[name="_sliced_discount_type"]:checked' ).val() === 'percentage' ) {
				sliced_invoices.totals.discounts = sliced_invoices.totals.sub_total.times( discountPercentage ).toDecimalPlaces( sliced_invoices.utils.decimals );
			} else {
				sliced_invoices.totals.discounts = new Decimal( discountValue );
			}
			// sliced_invoices.totals.sub_total = sliced_invoices.totals.sub_total.minus( sliced_invoices.totals.discounts );
			sliced_invoices.totals.sub_total_taxable = sliced_invoices.totals.sub_total_taxable.minus( sliced_invoices.totals.discounts );
			if ( sliced_invoices.totals.sub_total_taxable.lessThan( 0 ) ) {
				sliced_invoices.totals.sub_total_taxable = new Decimal( 0 );
			}
		}
		
        // add tax, if any
		var tax_percentage = new Decimal( 0 );
        if ( sliced_payments.tax != 0 ) {
			tax_percentage = new Decimal( sliced_payments.tax ); // don't filter it here. tax_percentage is saved as a real number internally.  The on.change handler already converts any formatted number to a real one.
			tax_percentage = tax_percentage.div( 100 );
		}
		
        if ( ! tax_percentage.equals( 0 ) ) {
			if ( sliced_payments.tax_calc_method === 'inclusive' ) {
				// europe:
				var tax_percentage_1 = tax_percentage.plus( 1 );
				var tax_amount_1 = sliced_invoices.totals.sub_total_taxable.div( tax_percentage_1 );
				sliced_invoices.totals.tax = sliced_invoices.totals.sub_total_taxable.minus( tax_amount_1 ).toDecimalPlaces( sliced_invoices.utils.decimals );
				sliced_invoices.totals.total = sliced_invoices.totals.sub_total;
			} else {
				// everybody else:
				sliced_invoices.totals.tax = sliced_invoices.totals.sub_total_taxable.times( tax_percentage ).toDecimalPlaces( sliced_invoices.utils.decimals );
				sliced_invoices.totals.total = sliced_invoices.totals.sub_total.plus( sliced_invoices.totals.tax );
			}
        } else {
            sliced_invoices.totals.total = sliced_invoices.totals.sub_total;
        }
		
		// add discounts, if any (part 2 of 2 -- after tax)
		if ( $( 'input[name="_sliced_discount_tax_treatment"]:checked' ).val() !== 'before' ) {
			if ( $( 'input[name="_sliced_discount_type"]:checked' ).val() === 'percentage' ) {
				sliced_invoices.totals.discounts = sliced_invoices.totals.total.times( discountPercentage ).toDecimalPlaces( sliced_invoices.utils.decimals );
			} else {
				sliced_invoices.totals.discounts = new Decimal( discountValue );
			}
		}
		if ( ! sliced_invoices.totals.discounts.equals( 0 ) ) {
			sliced_invoices.totals.total = sliced_invoices.totals.total.minus( sliced_invoices.totals.discounts );
		}
		
		// work out the payments totals
        $('.sliced input.payment_amount').each( function() {

            var group = $(this).parents('.cmb-repeatable-grouping');
            var index = group.data('iterator');
			
	    	var amt = new Decimal( sliced_invoices.utils.rawNumber( $(this).val() ) );
			var status = $(group).find('#_sliced_payment_' + index + '_status').val();
			
            if ( status === 'success' ) {
				sliced_invoices.totals.payments = sliced_invoices.totals.payments.plus( amt );
			}

	    });
		
		// execute hooks from external add-ons, if any
		$(sliced_invoices.hooks.sliced_invoice_totals).each( function( key, val ) {
			val();
		});
		
		// process any adjustments from external add-ons here
		// (avoids any potential race condition by doing this only here)
		if ( typeof sliced_invoices.totals.addons !== "undefined" ) {
			$.each( sliced_invoices.totals.addons, function( key, addon ) {
				if ( typeof addon._adjustments !== "undefined" ) {
					$.each( addon._adjustments, function( key, adjustment ) {
						//var adjustment = $(this);
						var type   = typeof adjustment.type !== "undefined" ? adjustment.type : false;
						var source = typeof adjustment.source !== "undefined" ? adjustment.source : false;
						var target = typeof adjustment.target !== "undefined" ? adjustment.target : false;
						if ( ! type || ! source || ! target ) {
							return; // if missing required fields, skip
						}
						if ( typeof addon[ source ] === "undefined" ) {
							return; // if can't map source, skip
						}
						if ( typeof sliced_invoices.totals[ target ] === "undefined" ) {
							return; // if can't map target, skip
						}
						// we go on...
						switch ( type ) {
							case 'add':
								sliced_invoices.totals[ target ] = sliced_invoices.totals[ target ].plus( addon[ source ] );
								break;
							case 'subtract':
								sliced_invoices.totals[ target ] = sliced_invoices.totals[ target ].minus( addon[ source ] );
								break;
						}
					});
				}
			});
		}
		
		// save this for last
		sliced_invoices.totals.total_due = sliced_invoices.totals.total.minus( sliced_invoices.totals.payments );
		
		// display
        $("#_sliced_line_items #sliced_sub_total").html( sliced_invoices.utils.formattedAmount( sliced_invoices.totals.sub_total.toNumber() ) );
        $("#_sliced_line_items #sliced_tax").html( sliced_invoices.utils.formattedAmount( sliced_invoices.totals.tax.toNumber() ) );
        $("#_sliced_line_items #sliced_payments").html( '-' + sliced_invoices.utils.formattedAmount( sliced_invoices.totals.payments.toNumber() ) );
		$("#_sliced_line_items #sliced_discounts").html( '-' + sliced_invoices.utils.formattedAmount( sliced_invoices.totals.discounts.toNumber() ) );
		$("#_sliced_line_items #sliced_total").html( sliced_invoices.utils.formattedAmount( sliced_invoices.totals.total_due.toNumber() ) );
        $("input#_sliced_totals_for_ordering").val( sliced_invoices.utils.formattedAmount( sliced_invoices.totals.total_due.toNumber() ) );

    };

	// bind events
	$(document).on('keyup change', '.sliced_discount, .sliced input.item_amount, .sliced input.item_qty, .sliced input.item_tax, .sliced input.item_taxable, .sliced select.pre_defined_products, .sliced input.payment_amount, .sliced select.payment_status', function () {
		workOutTotals();
	});
	
	$(document).on('keyup change', '#_sliced_tax', function() {
		sliced_payments.tax = sliced_invoices.utils.rawNumber( $(this).val() );
		workOutTotals();
	});
	
	$(document).on('change', '#_sliced_tax_calc_method', function() {
		sliced_payments.tax_calc_method = $(this).val();
		workOutTotals();
	});
	
	$( document ).on( 'click', '.cmb-remove-group-row-button, .cmb-shift-rows', function(){
		workOutTotals();
	});
	
	// add pre-defined items from select into the empty line item fields
	$( document ).on( 'change', 'select.pre_defined_products', function(){
		
		var title = $( this ).find( ':selected' ).data( 'title' );
		var price = $( this ).find( ':selected' ).data( 'price' );
		var qty   = $( this ).find( ':selected' ).data( 'qty' );
		var desc  = $( this ).find( ':selected' ).data( 'desc' );
		
		var group = $( this ).parents( '.cmb-repeatable-grouping' );
		var index = group.data( 'iterator' );
		
		$( 'input[name="_sliced_items[' + index + '][title]"]' ).val( title );
		$( 'input[name="_sliced_items[' + index + '][amount]"]' ).val( price );
		$( 'input[name="_sliced_items[' + index + '][qty]"]' ).val( qty );
		$( 'textarea[name="_sliced_items[' + index + '][description]"]' ).val( desc );
		
		workOutTotals();
	});
	
	
	/**
	 * totals editors
	 */
	
	function hideDiscountBox() {
		$( '#sliced-totals-discounts-edit' ).show();
		$( '#sliced-totals-discount-adder' ).hide();
	}
	
	function showDiscountBox() {
		$( '#sliced-totals-discounts-edit' ).hide();
		$( '#sliced-totals-discount-adder' ).css( 'display', 'inline-block' );
	}
	
	$(document).on('click', '#sliced-totals-payments-edit', function(e){
		e.preventDefault();
		var paymentsMetabox = $('#_sliced_payments');
		if ( $(paymentsMetabox).hasClass('closed') ) {
			$(paymentsMetabox).find('.handlediv').click();
			$('html, body').animate({
				scrollTop: $(paymentsMetabox).offset().top
			}, 2000);
		}
		$('.sliced input.payment_amount:last').focus();
	});
	
	$(document).on('click', '#sliced-totals-discounts-edit', function(e){
		e.preventDefault();
		showDiscountBox();
	});
	
	$(document).on( 'click', '#sliced-totals-discount-adder button', function(e) {
		hideDiscountBox();
	});
	
	$(document).on('keydown', '.sliced_discount', function(e) {
		if ( e.keyCode === 13 ) {
			e.preventDefault();
			hideDiscountBox();
		}
	});
	
	
	/**
	 * Client Select Box
	 */
	$(document).ready(function() {
		
		$('#_sliced_client').filter( ':not(.enhanced)' ).each( function() {
			var select2_args = {
				allowClear:  $( this ).data( 'allow_clear' ) ? true : false,
				placeholder: sliced_invoices_i18n.select_placeholder,
				minimumInputLength: $( this ).data( 'minimum_input_length' ) ? $( this ).data( 'minimum_input_length' ) : '1',
				escapeMarkup: function( m ) {
					return m;
				},
				ajax: {
					url:         ajaxurl,
					dataType:    'json',
					delay:       1000,
					data:        function( params ) {
						return {
							term:     params.term,
							action:   'sliced-search-clients',
							exclude:  $( this ).data( 'exclude' ),
							nonce:    sliced_invoices.ajax_nonce
						};
					},
					processResults: function( data ) {
						var terms = [];
						if ( data ) {
							$.each( data, function( id, text ) {
								terms.push({
									id: id,
									text: text
								});
							});
						}
						return {
							results: terms
						};
					},
					cache: true
				},
				language: {
					errorLoading: function() {
						return sliced_invoices_i18n.select_searching;
					},
					inputTooLong: function( args ) {
						var overChars = args.input.length - args.maximum;

						if ( 1 === overChars ) {
							return sliced_invoices_i18n.select_max_single;
						}

						return sliced_invoices_i18n.select_max.replace( '%qty%', overChars );
					},
					inputTooShort: function( args ) {
						var remainingChars = args.minimum - args.input.length;

						if ( 1 === remainingChars ) {
							return sliced_invoices_i18n.select_input_too_short_single;
						}

						return sliced_invoices_i18n.select_input_too_short.replace( '%qty%', remainingChars );
					},
					loadingMore: function() {
						return sliced_invoices_i18n.select_loading_more;
					},
					maximumSelected: function( args ) {
						if ( args.maximum === 1 ) {
							return sliced_invoices_i18n.select_max_single;
						}

						return sliced_invoices_i18n.select_max.replace( '%qty%', overChars );
					},
					noResults: function() {
						return sliced_invoices_i18n.select_no_matches + ' <span style="float:right;"><a href="#" onclick="jQuery(\'.cmb2-id--sliced-client\').find(\'a.sliced-add-client-button\').click();">' + sliced_invoices_i18n.select_create_new_client + '</a></span>';
					},
					searching: function() {
						return sliced_invoices_i18n.select_searching;
					}
				}
			};

			$( this ).selectWoo( select2_args ).addClass( 'enhanced' );

		});
		
		
		$('#sliced_update_user_user').filter( ':not(.enhanced)' ).each( function() {
			var select2_args = {
				allowClear:  $( this ).data( 'allow_clear' ) ? true : false,
				placeholder: sliced_invoices_i18n.select_placeholder,
				minimumInputLength: $( this ).data( 'minimum_input_length' ) ? $( this ).data( 'minimum_input_length' ) : '1',
				escapeMarkup: function( m ) {
					return m;
				},
				ajax: {
					url:         ajaxurl,
					dataType:    'json',
					delay:       1000,
					data:        function( params ) {
						return {
							term:     params.term,
							action:   'sliced-search-non-clients',
							exclude:  $( this ).data( 'exclude' ),
							nonce:    sliced_invoices.ajax_nonce
						};
					},
					processResults: function( data ) {
						var terms = [];
						if ( data ) {
							$.each( data, function( id, text ) {
								terms.push({
									id: id,
									text: text
								});
							});
						}
						return {
							results: terms
						};
					},
					cache: true
				},
				language: {
					errorLoading: function() {
						return sliced_invoices_i18n.select_searching;
					},
					inputTooLong: function( args ) {
						var overChars = args.input.length - args.maximum;

						if ( 1 === overChars ) {
							return sliced_invoices_i18n.select_max_single;
						}

						return sliced_invoices_i18n.select_max.replace( '%qty%', overChars );
					},
					inputTooShort: function( args ) {
						var remainingChars = args.minimum - args.input.length;

						if ( 1 === remainingChars ) {
							return sliced_invoices_i18n.select_input_too_short_single;
						}

						return sliced_invoices_i18n.select_input_too_short.replace( '%qty%', remainingChars );
					},
					loadingMore: function() {
						return sliced_invoices_i18n.select_loading_more;
					},
					maximumSelected: function( args ) {
						if ( args.maximum === 1 ) {
							return sliced_invoices_i18n.select_max_single;
						}

						return sliced_invoices_i18n.select_max.replace( '%qty%', overChars );
					},
					noResults: function() {
						return sliced_invoices_i18n.select_no_matches;
					},
					searching: function() {
						return sliced_invoices_i18n.select_searching;
					}
				}
			};

			$( this ).selectWoo( select2_args ).addClass( 'enhanced' );

		});
		
	});
	
	
	/**
	 * CMB2 datepicker modifications
	 */
	$(function(){
		
		if ( ! $('body').hasClass('sliced') ) {
			return;
		}
		
		if ( ! ( $('body').hasClass('post-php') || $('body').hasClass('post-new-php') ) ) {
			return;
		}
		
		if ( typeof $.datepicker === "undefined" ) {
			// shouldn't happen here, but just in case
			return;
		}
		
		var attachHandlers = $.datepicker._attachHandlers;
		var generateHTML = $.datepicker._generateHTML;
		var clearText = window.sliced_invoices_i18n.datepicker_clear;

		$.datepicker._attachHandlers = function (inst) {

			// call the cached function in scope of $.datepicker object
			attachHandlers.call($.datepicker, inst);

			// add custom stuff 
			inst.dpDiv.find("[data-handler]").map(function () { 
				var handler = { 
					clear: function () {
						var id = "#" + inst.id.replace(/\\\\/g, "\\");
						$.datepicker._clearDate(id);
						$.datepicker._hideDatepicker();
					} 
				};
				if (handler[this.getAttribute("data-handler")]) {
					$(this).bind(this.getAttribute("data-event"), handler[this.getAttribute("data-handler")]);
				} 
			});
		};
		
		$.datepicker._generateHTML = function (inst) {

			//call the cached function in scope of $.datepicker object
			var html = generateHTML.call($.datepicker, inst);
			var $html = $(html);
			var $buttonPane = $html.filter("div.ui-datepicker-buttonpane.ui-widget-content");

			$buttonPane.append($("<button />")
				.text(clearText)
				.attr("type", "button")
				.attr("data-handler", "clear")
				.attr("data-event", "click")
				.addClass("ui-datepicker-clear ui-state-default ui-priority-secondary ui-corner-all"));

			return $html;
		};
		
		var options = {};
		options.altFormat = 'yy-mm-dd';
		options.beforeShow = function( input, inst ) {
			$( '#ui-datepicker-div' ).addClass( 'cmb2-element' );
		};
		options.changeMonth = true;
		options.changeYear = true;
		options.closeText = sliced_invoices_i18n.datepicker_close;
		options.currentText = sliced_invoices_i18n.datepicker_today;
		options.dateFormat = sliced_invoices_i18n.datepicker_dateFormat;
		options.dayNames = sliced_invoices_i18n.datepicker_dayNames;
		options.dayNamesMin = sliced_invoices_i18n.datepicker_dayNamesMin;
		options.dayNamesShort = sliced_invoices_i18n.datepicker_dayNamesShort;
		options.monthNames = sliced_invoices_i18n.datepicker_monthNames;
		options.monthNamesShort = sliced_invoices_i18n.datepicker_monthNamesShort;
		options.showButtonPanel = true;
		
		options.altField = '#_sliced_invoice_created';
		$( '#_sliced_invoice_created_i18n' ).datepicker( options );
		
		options.altField = '#_sliced_invoice_due';
		$( '#_sliced_invoice_due_i18n' ).datepicker( options );
		
		options.altField = '#_sliced_quote_created';
		$( '#_sliced_quote_created_i18n' ).datepicker( options );
		
		options.altField = '#_sliced_quote_valid_until';
		$( '#_sliced_quote_valid_until_i18n' ).datepicker( options );
		
	});
	
	
	/**
	 * collapsible toggler
	 */
	$(function(){
		$('.sliced-collapsible-group-header').click(function(){
			var settingsElem = $(this).parent().find('.sliced-collapsible-group-settings');
			var toggleElem = $(this).parent().find('.sliced-collapsible-group-settings-toggle');
			if ( $(settingsElem).is(':visible') ) {
				$(settingsElem).slideUp();
				$(toggleElem).removeClass('dashicons-arrow-up').addClass('dashicons-arrow-down');
			} else {
				$(settingsElem).slideDown();
				$(toggleElem).removeClass('dashicons-arrow-down').addClass('dashicons-arrow-up');
			}
		});
	});


    /**
     * on page load
     */
    $(function(){
		
		// move hidden inputs where they need to be (CMB2 workaround)
		var $discountAdder = $( '#sliced-totals-discount-adder' );
		$discountAdder.prepend( $( '#_sliced_discount' ).attr( 'type', 'text' ) );
		$discountAdder.append( $( '#sliced_discount_type_wrapper' ).show() );
		$discountAdder.append( $( '#sliced_discount_tax_treatment_wrapper' ).show() );
		
		// update totals
		workOutTotals();
		
    });


    /**
     * email preview
     */
    $(function(){

    	sliced_invoices.sliced_email_cache = {};
		sliced_invoices.sliced_email_cache.$previewDiv = $('.sliced-email-preview');
		sliced_invoices.sliced_email_cache.placeholder = $(sliced_invoices.sliced_email_cache.$previewDiv).html();
		
		// handler for loading email previews
		sliced_invoices.sliced_email_preview = function( id ){
			sliced_invoices.sliced_email_preview_id = id;
			var currentTime = new Date().valueOf();
			var nonce = sliced_invoices.ajax_nonce;
			$(sliced_invoices.sliced_email_cache.$previewDiv).append('<iframe id="sliced-preview-' + currentTime + '" src="' + ajaxurl + '?action=sliced_sure_to_email&id=' + id + '&nonce=' + nonce + '"></iframe>');
			$('#sliced-preview-'+currentTime).on( 'load', function() {
				$(sliced_invoices.sliced_email_cache.$previewDiv).children('.sliced-email-preview-loading').remove();
				$(sliced_invoices.sliced_email_cache.$previewDiv).children('.sliced-email-preview-menu').show();
				$(this).show();
			});
		};
		
		// handler for unloading email previews
		// hacky solution to do this only once, even though tb_unload fires twice...
		var tb_unload_count = 1;
		jQuery(window).bind('tb_unload', function () {
			if (tb_unload_count > 1) {
				tb_unload_count = 1;
			} else {
				// restore placeholder
				$(sliced_invoices.sliced_email_cache.$previewDiv).html(sliced_invoices.sliced_email_cache.placeholder);
				tb_unload_count = tb_unload_count + 1;
			}
		});
		
		// handler for switching email templates
		sliced_invoices.sliced_email_preview_switch = function( template ){
			var id = sliced_invoices.sliced_email_preview_id;
			// restore placeholder
			$(sliced_invoices.sliced_email_cache.$previewDiv).html(sliced_invoices.sliced_email_cache.placeholder);
			// load new preview
			var currentTime = new Date().valueOf();
			var nonce = sliced_invoices.ajax_nonce;
			$(sliced_invoices.sliced_email_cache.$previewDiv).append('<iframe id="sliced-preview-' + currentTime + '" src="' + ajaxurl + '?action=sliced_sure_to_email&id=' + id + '&template=' + template + '&nonce=' + nonce + '"></iframe>');
			$('#sliced-preview-'+currentTime).on( 'load', function() {
				$(sliced_invoices.sliced_email_cache.$previewDiv).children('.sliced-email-preview-loading').remove();
				$(sliced_invoices.sliced_email_cache.$previewDiv).children('.sliced-email-preview-menu').show()
					.find('.nav-tab').removeClass('nav-tab-active');
				$(sliced_invoices.sliced_email_cache.$previewDiv).children('.sliced-email-preview-menu')
					.find('[data-sliced-email-template="'+template+'"]').addClass('nav-tab-active');
				$(this).show();
			});
		};

    });

	/**
	 * special actions for quotes
	 */
    $(function(){
		
		$( '#sliced-invoices-convert-quote-to-invoice' ).click( function(){
			return confirm( sliced_invoices_i18n.convert_quote_to_invoice );
		});
		
		$( '#sliced-invoices-create-invoice-from-quote' ).click( function(){
			return confirm( sliced_invoices_i18n.create_invoice_from_quote );
		});
		
    });
	
    /**
     * stop recurring confirm
     */
    $(function(){

        $( "#stop_recurring" ).click(function() {
            if( ! confirm( sliced_stop_recurring.stop_recurring ) ) {
                return false;
            }
        });

    });


})( jQuery );
