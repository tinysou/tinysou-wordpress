<?php

/**
* 
*
* @author Tairy <tairyguo@gmail.com>
* 
* @since 1.0
*/

class TinysouPlugin {
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'tinysou_menu' ) );
	}

	public function tinysou_admin_page(){
		//include( 'tinysou-admin-page.php' );
		echo "Admin Page Test";
	}

	public function tinysou_menu() {
		add_menu_page( '微搜索', '微搜索', 'manage_options', "tinysou", array( $this, 'tinysou_admin_page' ), plugins_url( 'assets/logo.png', __FILE__ ));
	}
}