<?php
// Exit if accessed directly
if ( ! defined('ABSPATH') ) { exit; }

if ( ! function_exists( 'sliced_get_client_id' ) ) :

	function sliced_get_client_id() {
		$client = Sliced_Shared::get_client_details();
		return apply_filters( 'sliced_get_client_id', $client['id'], $client );
	}

endif;

if ( ! function_exists( 'sliced_get_client_first_name' ) ) :

	function sliced_get_client_first_name( $id = 0 ) {
		$client = Sliced_Shared::get_client_details( $id );
		return apply_filters( 'sliced_get_client_first_name', $client['first_name'], $client, $id );
	}

endif;

if ( ! function_exists( 'sliced_get_client_last_name' ) ) :

	function sliced_get_client_last_name( $id = 0 ) {
		$client = Sliced_Shared::get_client_details( $id);
		return apply_filters( 'sliced_get_client_last_name', $client['last_name'], $client, $id );
	}

endif;

if ( ! function_exists( 'sliced_get_client_business' ) ) :

	function sliced_get_client_business( $id = 0 ) {
		$client = Sliced_Shared::get_client_details( $id );
		return apply_filters( 'sliced_get_client_business', $client['business'], $client, $id );
	}

endif;

if ( ! function_exists( 'sliced_get_client_address' ) ) :

	function sliced_get_client_address( $id = 0 ) {
		$client = Sliced_Shared::get_client_details( $id );
		return apply_filters( 'sliced_get_client_address', $client['address'], $client, $id );
	}

endif;


if ( ! function_exists( 'sliced_get_client_extra_info' ) ) :

	function sliced_get_client_extra_info( $id = 0 ) {
		$client = Sliced_Shared::get_client_details( $id );
		return apply_filters( 'sliced_get_client_extra_info', $client['extra_info'], $client, $id );
	}

endif;


if ( ! function_exists( 'sliced_get_client_email' ) ) :

	function sliced_get_client_email( $id = 0 ) {
		$client = Sliced_Shared::get_client_details( $id );
		return apply_filters( 'sliced_get_client_email', $client['email'], $client, $id );
	}

endif;

if ( ! function_exists( 'sliced_get_client_website' ) ) :

	function sliced_get_client_website( $id = 0 ) {
		$client = Sliced_Shared::get_client_details( $id );
		return apply_filters( 'sliced_get_client_website', $client['website'], $client, $id );
	}

endif;
