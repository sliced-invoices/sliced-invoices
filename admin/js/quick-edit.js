/*global ajaxurl, inlineEditPost, inlineEditL10n */
jQuery(function( $ ) {

    $( '#the-list' ).on( 'click', '.editinline', function() {

        inlineEditPost.revert();

        var post_id = $( this ).closest( 'tr' ).attr( 'id' );

        post_id = post_id.replace( 'post-', '' );

        var $sliced_inline_data = $( '#sliced_inline_' + post_id );
        var created             = $sliced_inline_data.find( '.created' ).text();
        var due                 = $sliced_inline_data.find( '.due' ).text();
        var valid               = $sliced_inline_data.find( '.valid' ).text();
        var order_number        = $sliced_inline_data.find( '.order_number ').text();
        var terms               = $sliced_inline_data.find( '.terms' ).text();
        var number              = $sliced_inline_data.find( '.number' ).text();
        var client              = $sliced_inline_data.find( '.client' ).text();
        var status              = $sliced_inline_data.find( '.status' ).text();


        $( 'input[name="sliced_created"]', '.inline-edit-row' ).val( created );
        $( 'input[name="sliced_due"]', '.inline-edit-row' ).val( due );
        $( 'input[name="sliced_valid"]', '.inline-edit-row' ).val( valid );
        $( 'input[name="sliced_order_number"]', '.inline-edit-row' ).val( order_number );
        $( 'textarea[name="sliced_terms"]', '.inline-edit-row' ).text( terms );
        $( 'input[name="sliced_number"]', '.inline-edit-row' ).val( number );

        // clear the dropdowns that are selected before adding new value
        $('select[name="sliced_client"] option:selected').removeAttr('selected');
        $('select[name="sliced_status"] option:selected').removeAttr('selected');

        $( 'select[name="sliced_client"] option[value="' + client + '"]', '.inline-edit-row' ).attr( 'selected', 'selected' );
        $( 'select[name="sliced_status"] option[value="' + status + '"]', '.inline-edit-row' ).attr( 'selected', 'selected' );

    });

});
