jQuery(document).ready(function($) {

    /**
     * When user clicks on button...
     *
     */
    $('.sliced-new-client #submit').click( function(event) {

        /**
         * Prevent default action, so when user clicks button he doesn't navigate away from page
         *
         */
        if (event.preventDefault) {
            event.preventDefault();
        } else {
            event.returnValue = false;
        }

        var ajax_url = sliced_new_client.sliced_ajax_url;

        // $("#swd_billing_client").empty();
        $('#_sliced_client .cmb2-metabox-description span').remove();
        // Show 'Please wait' loader to user, so she/he knows something is going on
        $('.indicator').show();
        // If for some reason result field is visible hide it
        $('.result-message').hide();

        // Collect data from inputs
        var the_nonce   = $('#_wpnonce_create-user').val();
        var user_login  = $('#user_login').val();
        var password    = $('#pass1').val();
        var email       = $('#email').val();
        var first_name  = $('#first_name').val();
        var last_name   = $('#last_name').val();
        var website     = $('#url').val();

        var business    = $('#_sliced_client_business').val();
        var address     = $('#_sliced_client_address').val();
        var extra_info  = $('#_sliced_client_extra_info').val();

        /**
         * AJAX URL where to send data
         * (from localize_script)
         */
        data = {
            action: 'create-user',
            nonce: the_nonce,
            user_login: user_login,
            password: password,
            email: email,
            first_name: first_name,
            last_name: last_name,
            website: website,
            business: business,
            address: address,
            extra_info: extra_info,

        };

        // Do AJAX request
        $.post( ajax_url, data, function(response) {

            // Hide 'Please wait' indicator
            $('.indicator').hide();

            if( response != 'Error adding the new user.' ) {

                // If user is created
                $("#_sliced_client").html(response);

                tb_remove();

                $('<span class="updated">New Client Successfully Added</span>').insertAfter('select#_sliced_client'); // Add success message to results div

            } else {

                $('.result-message').addClass('form-invalid error');
                $('.result-message').show();
                $('.result-message').html('Please check that all required fields are filled in and that this users does not already exist.');

                $('.form-required').addClass('form-invalid'); // Add class failed to results div

            }

        });

    });
});
