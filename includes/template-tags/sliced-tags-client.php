<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( ! function_exists( 'sliced_get_client_id' ) ):
	
	function sliced_get_client_id( $id = 0 ) {
		$client_id = 0;
		$client = Sliced_Shared::get_client_details( $id );
		if ( isset( $client['id'] ) ) {
			$client_id = $client['id'];
		}
		return apply_filters( 'sliced_get_client_id', $client_id, $client, $id );
	}
	
endif;

if ( ! function_exists( 'sliced_get_client_first_name' ) ):
	
	function sliced_get_client_first_name( $id = 0 ) {
		$client_first_name = '';
		$client = Sliced_Shared::get_client_details( $id );
		if ( isset( $client['first_name'] ) ) {
			$client_first_name = $client['first_name'];
		}
		return apply_filters( 'sliced_get_client_first_name', $client_first_name, $client, $id );
	}
	
endif;

if ( ! function_exists( 'sliced_get_client_last_name' ) ):
	
	function sliced_get_client_last_name( $id = 0 ) {
		$client_last_name = '';
		$client = Sliced_Shared::get_client_details( $id );
		if ( isset( $client['last_name'] ) ) {
			$client_last_name = $client['last_name'];
		}
		return apply_filters( 'sliced_get_client_last_name', $client_last_name, $client, $id );
	}
	
endif;

if ( ! function_exists( 'sliced_get_client_business' ) ):
	
	function sliced_get_client_business( $id = 0 ) {
		$client_business = '';
		$client = Sliced_Shared::get_client_details( $id );
		if ( isset( $client['business'] ) ) {
			$client_business = $client['business'];
		}
		return apply_filters( 'sliced_get_client_business', $client_business, $client, $id );
	}
	
endif;

if ( ! function_exists( 'sliced_get_client_address' ) ):
	
	function sliced_get_client_address( $id = 0 ) {
		$client_address = '';
		$client = Sliced_Shared::get_client_details( $id );
		if ( isset( $client['address'] ) ) {
			$client_address = $client['address'];
		}
		return apply_filters( 'sliced_get_client_address', $client_address, $client, $id );
	}
	
endif;

if ( ! function_exists( 'sliced_get_client_extra_info' ) ):
	
	function sliced_get_client_extra_info( $id = 0 ) {
		$client_extra_info = '';
		$client = Sliced_Shared::get_client_details( $id );
		if ( isset( $client['extra_info'] ) ) {
			$client_extra_info = $client['extra_info'];
		}
		return apply_filters( 'sliced_get_client_extra_info', $client_extra_info, $client, $id );
	}
	
endif;

if ( ! function_exists( 'sliced_get_client_email' ) ):
	
	function sliced_get_client_email( $id = 0 ) {
		$client_email = '';
		$client = Sliced_Shared::get_client_details( $id );
		if ( isset( $client['email'] ) ) {
			$client_email = $client['email'];
		}
		return apply_filters( 'sliced_get_client_email', $client_email, $client, $id );
	}
	
endif;

if ( ! function_exists( 'sliced_get_client_website' ) ):
	
	function sliced_get_client_website( $id = 0 ) {
		$client_website = '';
		$client = Sliced_Shared::get_client_details( $id );
		if ( isset( $client['website'] ) ) {
			$client_website = $client['website'];
		}
		return apply_filters( 'sliced_get_client_website', $client_website, $client, $id );
	}
	
endif;
