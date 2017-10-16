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
		// removes the thousand seperator
		var parts = x.toString().split(sliced_invoices.utils.thousand_sep);
		parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, '');
		var amount = parts.join('');
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
		var rgx = /(\d+)(\d{3})/;
		while (rgx.test(x1)) {
			x1 = x1.replace(rgx, '$1' + sliced_invoices.utils.thousand_sep + '$2');
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
	function clearLineTotal(){

        var lastRow = $(this).closest('.cmb-row').prev();
        var index   = $(lastRow).data('iterator');

        $(lastRow).find('.line_total').html('0.00');

    };
    $(document).on( 'click', '.cmb-add-group-row', clearLineTotal );


	// calculate the totals on the fly when editing or adding a quote or invoice
 	function workOutTotals(){

		sliced_invoices.totals = {
			'sub_total':         new Decimal( 0 ),
			'sub_total_taxable': new Decimal( 0 ),
			'tax':               new Decimal( 0 ),
			'total':             new Decimal( 0 )
		};
		
		var global_tax = new Decimal( 0 );
        if ( sliced_payments.tax != 0 ) {
			global_tax = new Decimal( sliced_payments.tax );
			global_tax = global_tax.div( 100 );
		}

        // work out the totals
        $('.sliced input.item_amount').each( function() {

            var group = $(this).parents('.cmb-repeatable-grouping');
            var index = group.data('iterator');
			
	    	var qty = new Decimal( sliced_invoices.utils.rawNumber( $(group).find('#_sliced_items_' + index + '_qty').val() ) );
			var amt = new Decimal( sliced_invoices.utils.rawNumber( $(this).val() ) );
			
			// for historical reasons, the "adjust" field is named "tax" internally,
			// but it is unrelated to the actual tax field(s) in use today.
            var adj = new Decimal( sliced_invoices.utils.rawNumber( $(group).find('#_sliced_items_' + index + '_tax').val() ) );
            
			var taxable = $(group).find('#_sliced_items_' + index + '_taxable').is(":checked");

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

        // add global tax, if any
        if ( global_tax != 0 ) {
			sliced_invoices.totals.tax = sliced_invoices.totals.sub_total_taxable.times( global_tax );
			sliced_invoices.totals.total = sliced_invoices.totals.sub_total.plus( sliced_invoices.totals.tax );
        } else {
            sliced_invoices.totals.total = sliced_invoices.totals.sub_total;
        }
		
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
		
        $("#_sliced_line_items #sliced_sub_total").html( sliced_invoices.utils.formattedAmount( sliced_invoices.totals.sub_total.toNumber() ) );
        $("#_sliced_line_items #sliced_tax").html( sliced_invoices.utils.formattedAmount( sliced_invoices.totals.tax.toNumber() ) );
        $("#_sliced_line_items #sliced_total").html( sliced_invoices.utils.formattedAmount( sliced_invoices.totals.total.toNumber() ) );
        $("input#_sliced_totals_for_ordering").val( sliced_invoices.utils.formattedAmount( sliced_invoices.totals.total.toNumber() ) );

    };

	$(document).on('keyup change', '.sliced_discount_value, .sliced input.item_amount, .sliced input.item_qty, .sliced input.item_tax, .sliced input.item_taxable, .sliced select.pre_defined_products', function () {
		workOutTotals();
	});
	
	$(document).on('keyup change', '#_sliced_tax', function() {
		sliced_payments.tax = sliced_invoices.utils.rawNumber( $(this).val() );
		workOutTotals();
	});
	

    // add pre-defined items from select into the empty line item fields
    $(document).on('change', 'select.pre_defined_products', function () {

        var title   = $(this).find(':selected').data('title');
        var price   = $(this).find(':selected').data('price');
        var qty     = $(this).find(':selected').data('qty');
        var desc    = $(this).find(':selected').data('desc');

        var group   = $(this).parents('.cmb-repeatable-grouping');
        var index   = group.data('iterator');

        $('#_sliced_items_' + index + '_title').val( title );
        $('#_sliced_items_' + index + '_amount').val( price );
        $('#_sliced_items_' + index + '_qty').val( qty );
        $('#_sliced_items_' + index + '_description').val( desc );

     	workOutTotals();
    });


    /**
     * on page load
     */
    $(function(){
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
			var currentTime = new Date().valueOf();
			$(sliced_invoices.sliced_email_cache.$previewDiv).append('<iframe id="sliced-preview-' + currentTime + '" src="' + ajaxurl + '?action=sliced_sure_to_email&id=' + id + '"></iframe>');
			$('#sliced-preview-'+currentTime).on( 'load', function() {
				$(sliced_invoices.sliced_email_cache.$previewDiv).children('.sliced-email-preview-loading').remove();
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

    });

    /**
     * convert quote confirm
     */
    $(function(){

        $( "#convert_quote" ).click(function() {
            if( ! confirm( sliced_confirm.convert_quote ) ) {
                return false;
            }
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
