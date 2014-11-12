<?php

$api_authorized = get_option( 'tinysou_api_authorized' );
$engine_initialized = get_option( 'tinysou_engine_initialized' );

if( $api_authorized ) {
	if( $engine_initialized ) {
		include( 'tinysou-controls.php' );
	} else {
		include( 'tinysou-choose-engine.php' );
	}
} else {
	include( 'tinysou-authorize.php' );
}