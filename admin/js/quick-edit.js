/*global ajaxurl, inlineEditPost, inlineEditL10n */
jQuery(function( $ ) {

    $( '#the-list' ).on( 'click', '.editinline', function() {

        inlineEditPost.revert();

        var post_id = $( this ).closest( 'tr' ).attr( 'id' );

        post_id = post_id.replace( 'post-', '' );

        var $sliced_inline_data = $( '#sliced_inline_' + post_id );
		
        var created_d           = $sliced_inline_data.find( '.created_d' ).text();
		var created_m           = $sliced_inline_data.find( '.created_m' ).text();
		var created_Y           = $sliced_inline_data.find( '.created_Y' ).text();
		var created_H           = $sliced_inline_data.find( '.created_H' ).text();
		var created_i           = $sliced_inline_data.find( '.created_i' ).text();
		var created_s           = $sliced_inline_data.find( '.created_s' ).text();
        var due_d               = $sliced_inline_data.find( '.due_d' ).text();
		var due_m               = $sliced_inline_data.find( '.due_m' ).text();
		var due_Y               = $sliced_inline_data.find( '.due_Y' ).text();
		var due_H               = $sliced_inline_data.find( '.due_H' ).text();
		var due_i               = $sliced_inline_data.find( '.due_i' ).text();
		var due_s               = $sliced_inline_data.find( '.due_s' ).text();
        var valid_d             = $sliced_inline_data.find( '.valid_d' ).text();
		var valid_m             = $sliced_inline_data.find( '.valid_m' ).text();
		var valid_Y             = $sliced_inline_data.find( '.valid_Y' ).text();
		var valid_H             = $sliced_inline_data.find( '.valid_H' ).text();
		var valid_i             = $sliced_inline_data.find( '.valid_i' ).text();
		var valid_s             = $sliced_inline_data.find( '.valid_s' ).text();
        var order_number        = $sliced_inline_data.find( '.order_number ').text();
        var terms               = $sliced_inline_data.find( '.terms' ).html();
        var number              = $sliced_inline_data.find( '.number' ).text();
        var client              = $sliced_inline_data.find( '.client' ).text();
        var status              = $sliced_inline_data.find( '.status' ).text();

        $( 'input[name="sliced_created_d"]', '.inline-edit-row' ).val( created_d );
		$( 'input[name="sliced_created_Y"]', '.inline-edit-row' ).val( created_Y );
		$( 'input[name="sliced_created_H"]', '.inline-edit-row' ).val( created_H );
		$( 'input[name="sliced_created_i"]', '.inline-edit-row' ).val( created_i );
		$( 'input[name="sliced_created_s"]', '.inline-edit-row' ).val( created_s );
        $( 'input[name="sliced_due_d"]', '.inline-edit-row' ).val( due_d );
		$( 'input[name="sliced_due_Y"]', '.inline-edit-row' ).val( due_Y );
		$( 'input[name="sliced_due_H"]', '.inline-edit-row' ).val( due_H );
		$( 'input[name="sliced_due_i"]', '.inline-edit-row' ).val( due_i );
		$( 'input[name="sliced_due_s"]', '.inline-edit-row' ).val( due_s );
        $( 'input[name="sliced_valid_d"]', '.inline-edit-row' ).val( valid_d );
		$( 'input[name="sliced_valid_Y"]', '.inline-edit-row' ).val( valid_Y );
		$( 'input[name="sliced_valid_H"]', '.inline-edit-row' ).val( valid_H );
		$( 'input[name="sliced_valid_i"]', '.inline-edit-row' ).val( valid_i );
		$( 'input[name="sliced_valid_s"]', '.inline-edit-row' ).val( valid_s );
        $( 'input[name="sliced_order_number"]', '.inline-edit-row' ).val( order_number );
        $( 'textarea[name="sliced_terms"]', '.inline-edit-row' ).text( terms );
        $( 'input[name="sliced_number"]', '.inline-edit-row' ).val( number );

        // clear the dropdowns that are selected before adding new value
        $( 'select[name="sliced_client"] option:selected' ).removeAttr('selected');
        $( 'select[name="sliced_status"] option:selected' ).removeAttr('selected');
		$( 'select[name="sliced_created_m"] option:selected' ).removeAttr('selected');
		$( 'select[name="sliced_due_m"] option:selected' ).removeAttr('selected');
		$( 'select[name="sliced_valid_m"] option:selected' ).removeAttr('selected');

        $( 'select[name="sliced_client"] option[value="' + client + '"]', '.inline-edit-row' ).attr( 'selected', 'selected' );
        $( 'select[name="sliced_status"] option[value="' + status + '"]', '.inline-edit-row' ).attr( 'selected', 'selected' );
		$( 'select[name="sliced_created_m"] option[value="' + created_m + '"]', '.inline-edit-row' ).attr( 'selected', 'selected' );
		$( 'select[name="sliced_due_m"] option[value="' + due_m + '"]', '.inline-edit-row' ).attr( 'selected', 'selected' );
		$( 'select[name="sliced_valid_m"] option[value="' + valid_m + '"]', '.inline-edit-row' ).attr( 'selected', 'selected' );

    });

});
