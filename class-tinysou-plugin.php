<?php

/**
* 
*
* @author Tairy <tairyguo@gmail.com>
* 
* @since 1.0
*/

class TinysouPlugin {
	private $client = NULL;
	private $document_type_slug = 'posts';

	private $api_key = NULL;
	private $engine_slug = NULL;
	private $engine_name = NULL;
	private $engine_key = NULL;
	private $num_indexed_documents = 0;
	private $api_authorized = false;
	private $engine_initialized = false;

	private $post_ids = NULL;
	private $total_result_count = 0;
	private $num_pages = 0;
	private $per_page = 0;
	private $search_successful = false;
	private $error = NULL;
	private $results = NULL;

	private $max_retries = 5;
	private $retry_delay = 2;

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'tinysou_menu' ) );

		if ( ! is_admin() ){
			$this->initialize_api_client();
		}
	}

	public function tinysou_admin_page(){
		include( 'tinysou-admin-page.php' );
	}

	public function tinysou_menu() {
		add_menu_page( '微搜索', '微搜索', 'manage_options', "tinysou", array( $this, 'tinysou_admin_page' ), plugins_url( 'assets/logo.png', __FILE__ ));
	}

	public function initialize_api_client() {
		$this->api_key = get_option( 'tinysou_api_key' );
		$this->engine_slug = get_option( 'tinysou_engine_slug' );
		$this->engine_key = get_option( 'tinysou_engine_key' );

		$this->client = new TinysouClient();
		$this->client->set_api_key( $this->api_key );
	}

	public function check_api_authorized(){
		if( ! is_admin() )
			return;
		if( $this->api_authorized )
			return;

		if( $this->api_key && strlen( $this->api_key ) > 0 ) {
			try {
				$this->api_authorized = $this->client->authorized();
			} catch( TinysouError $e ) {
				$this->api_authorized = false;
			} 
		} else {
				$this->api_authorized = false;
		}

		update_option( 'tinysou_api_authorized', $this->api_authorized );
	}
}