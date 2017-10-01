(function( $ ) {
	'use strict';
	
	if ( typeof window.sliced_invoices === "undefined" ) {
		window.sliced_invoices = {};
	}
	

    function clearLineTotal(){

        var lastRow = $(this).closest('.cmb-row').prev();
        var index   = $(lastRow).data('iterator');

        $(lastRow).find('.line_total').html('0.00');

    };
    $(document).on( 'click', '.cmb-add-group-row', clearLineTotal );


	/**
     * calculate the totals on the fly when editing or adding a quote or invoice
     */
 	function workOutTotals(){

        var global_tax      = sliced_payments.tax != 0 ? sliced_payments.tax / 100 : 0;
        var symbol          = sliced_payments.currency_symbol;
        var position        = sliced_payments.currency_pos;
        var thousand_sep    = sliced_payments.thousand_sep;
        var decimal_sep     = sliced_payments.decimal_sep;
        var decimals        = parseInt( sliced_payments.decimals );

        // sorts out the number to enable calculations
        function rawNumber(x) {
            // removes the thousand seperator
            var parts = x.toString().split(thousand_sep);
            parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, '');
            var amount = parts.join('');
            // makes the decimal seperator a period
            var output = amount.toString().replace(/\,/g, '.');
            return parseFloat( output );
        }

        // formats number into users format
        function formattedNumber(nStr) {
            var num = nStr.split('.');
            var x1 = num[0];
            var x2 = num.length > 1 ? decimal_sep + num[1] : '';
            var rgx = /(\d+)(\d{3})/;
            while (rgx.test(x1)) {
                x1 = x1.replace(rgx, '$1' + thousand_sep + '$2');
            }
            return x1 + x2;
		}


		// format the amounts
        function formattedAmount(amount) {
            // do the symbol position formatting
            var formatted = 0;
            var amount = new Decimal( amount );
			amount = amount.toFixed( decimals );
            switch (position) {
                case 'left':
                    formatted = symbol + formattedNumber( amount );
                    break;
                case 'right':
                    formatted = formattedNumber( amount ) + symbol;
                    break;
                case 'left_space':
                    formatted = symbol + ' ' + formattedNumber( amount );
                    break;
                case 'right_space':
                    formatted = formattedNumber( amount ) + ' ' + symbol;
                    break;
                default:
                    formatted = symbol + formattedNumber( amount );
                    break;
            }
            return formatted;
        }

        // work out the line total
        var sum = $.map($('.sliced input.item_amount'), function(item) {

            var group       = $(item).parents('.cmb-repeatable-grouping');
            var index       = group.data('iterator');

	    	var amount      = rawNumber( item.value );
            var tax_perc    = rawNumber( $(group).find('#_sliced_items_' + index + '_tax').val() );
            var qty         = rawNumber( $(group).find('#_sliced_items_' + index + '_qty').val() );

            if( isNaN( tax_perc ) ) { tax_perc = 0; }

            // work out the line totals and taxes/discounts
            var line_tax_perc   = tax_perc != 0 ? tax_perc / 100 : 0; // 0.10
            var line_sub_total  = qty * amount; // 100
            var line_tax_amt    = line_sub_total * line_tax_perc; // 10
            var line_total      = line_sub_total + line_tax_amt; // 110

            // display 0 instead of NaN
            if( isNaN( line_total ) ) { line_total = 0; }

            // display the calculated amount
            $( item ).parents('.cmb-type-text-money').find('.line_total').html( formattedAmount( line_total ) );
            // console.log(parseFloat(line_total));
	        return parseFloat( line_total );

	    }).reduce(function(a, b) {
	        return a + b;
	    }, 0);

        // display 0 instead of NaN
	    if( isNaN( sum ) ) { sum = 0; }

        // add global tax if any
        if ( global_tax > 0 ) {
            var raw_tax = sum * global_tax;
            var raw_total = sum + raw_tax;
        } else {
            var raw_tax = 0;
            var raw_total = sum;
        }
		
		/* Begin DAPP rounding fix.  Temporary pending new DAPP update */
		if ( typeof window.SlicedDiscountsAndPartialPayment !== "undefined" ) {
			$('.sliced_discount_value').each( function() {
				var val = $( this ).val();
				if ( val > 0 ) {
					var temp_val = new Decimal(raw_total);
					raw_total = temp_val.minus(val);
				}
			});
		}
		/* End DAPP rounding fix */
		
        $("#_sliced_line_items #sliced_sub_total").html( formattedAmount( sum ) );
        $("#_sliced_line_items #sliced_tax").html( formattedAmount( raw_tax ) );
        $("#_sliced_line_items #sliced_total").html( formattedAmount( raw_total ) );
        $("input#_sliced_totals_for_ordering").val( formattedAmount( raw_total ) );

    };

	/* DAPP rounding fix, part 2.  Temporary pending new DAPP update */
	//$(document).on('keyup change', '.sliced input.item_amount, .sliced input.item_qty, .sliced input.item_tax', function () {
	//	workOutTotals();
	//});
	$(document).on('keyup change', '.sliced_discount_value, .sliced input.item_amount, .sliced input.item_qty, .sliced input.item_tax, select.pre_defined_products', function () {
		setTimeout( function(){ 
			workOutTotals();
		}, 2 );
	});
	/* End DAPP rounding fix, part 2 */

    /**
     * add pre-defined items from select into the empty line item fields
     */
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
		/* DAPP rounding fix, part 3.  Temporary pending new DAPP update */
        //workOutTotals();
		setTimeout( function(){ 
			workOutTotals();
		}, 2 );
		/* End DAPP rounding fix, part 3 */
    });


    /**
     * fetch email preview
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
