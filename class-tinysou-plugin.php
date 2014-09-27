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
		$this->api_authorized = get_option( 'tinysou_api_authorized' );

		add_action( 'admin_menu', array( $this, 'tinysou_menu' ) );
		add_action( 'admin_init', array( $this, 'initialize_admin_screen' ) );

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

	/**
	* Initialize tinysou API client 
	*/
	public function initialize_api_client() {
		$this->api_key = get_option( 'tinysou_api_key' );
		$this->engine_slug = get_option( 'tinysou_engine_slug' );
		$this->engine_key = get_option( 'tinysou_engine_key' );

		$this->client = new TinysouClient();
		$this->client->set_api_key( $this->api_key );
	}

	public function initialize_admin_screen() {

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'admin_menu', array( $this, 'tinysou_menu') );
		add_action( 'wp_ajax_refresh_num_indexed_documents', array( $this, 'async_refresh_num_indexed_documents') );
		add_action( 'wp_ajax_index_batch_of_posts', array( $this, 'async_index_batch_of_posts' ) );
		add_action( 'wp_ajax_delete_batch_of_trashed_posts', array( $this, 'async_delete_batch_of_trashed_posts' ) );
		add_action( 'admin_notices', array( $this, 'error_notice' ) );

		if ( current_user_can ('manage_options' ) ) {
			if ( isset( $_POST['action'] ) ) {
				check_admin_referer( 'tinysou-nonce' );
				switch ( $_POST['action'] ) {
					case 'tinysou_set_api_key':
						$api_key = sanitize_text_field( $_POST['api_key'] );
						update_option( 'tinysou_api_key', $api_key );
						delete_option( 'tinysou_engine_slug' );
						delete_option( 'tinysou_num_indexed_documents' );
						break;

					case 'tinysou_create_engine':
						$engine_name = sanitize_text_field( $_POST['engine_name'] );
						update_option( 'tinysou-create-engine', $engine_name );
						break;
					
					case 'tinysou_clear_config':
						$this -> chear_config();
						break;

					default:
						break;
				}
			}
		}
	}
	/**
	* Display an error message in the dashboard if there was an error in plugin
	*/
	public function error_notice() {
		if( ! is_admin() ) {
			return;
		}

		if( isset( $this->error ) && ! empty( $this->error ) ) {
			echo '<dic class="error"><p>' . $this->error . '</p></div>';
		}
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

	public function clear_config() {
		delete_option( 'tinysou_create_engine' );
		delete_option( 'tinysou_api_key' );
		delete_option( 'tinysou_api_authorized' );
		delete_option( 'tinysou_engine_slug' );
		delete_option( 'tinysou_engine_name' );
		delete_option( 'tinysou_engine_key' );
		delete_option( 'tinysou_engine_intialized' );
		delete_option( 'tinysou_create_engine' );
		delete_option( 'tinysou_num_indexed_documents' );
	}

	public function enqueue_admin_assets($hook) {
		if( 'toplevel_page_tinysou' != $hook ) {
			return;
		}

		wp_enqueue_style( 'admin_styles', plugins_url( 'assets/admin_style.css', __FILE__ ) );
	}

	public function async_refresh_num_indexed_documents() {
		check_ajax_referer( 'tinysou-ajax-nonce' );
		$this->engine_slug = get_option( 'tinysou_engine_slug' );
		$this->engine_name = get_option( 'tinysou_engine_name' );
		$this->engin_key = get_option( 'tinysou_engine_key' );

		try{
			$document_type = $this->client->find_docuemnt_type( $this->engine_slug, $this->document_type_slug );
		} catch ( TinysouError $e ) {
			header('HTTP/1.1 500 Internal Server Error');
			die();
		}

		$this->num_indexed_documents = $document_type['document_counts'];
		update_option( 'tinysou_num_indexed_documents', $this->num_indexed_documents );
		header( 'Content-Type: application/json' );
		print( json_encode( array( 'num_indexed_documents' => $this->num_indexed_documents ) ) ); 
		die();
	}

	public function async_index_batch_of_posts() {
		check_ajax_referer( 'tinysou-ajax-nonce' );
		$offset = isset( $_POST['offset'] ) ? intval( $_POST['offset'] ) : 0;
		$batch_size = isset( $_POST['batch_size'] ) ? intval( $_POST['batch_size'] ) : 10;

		try {
			list( $num_written, $total_posts ) = $this->index_batch_of_posts( $offset, $batch_size );

			header( 'Content-Type: application/json' );
			print( json_encode( array( 'num_written' => $num_written, 'total' => $total_posts ) ) );
			die();

		} catch ( SwiftypeError $e ) {
			header( 'HTTP/1.1 500 Internal Server Error' );
			print( "Error in Create or Update Documents. " );
			print( "Offset: " . $offset . " " );
			print( "Batch Size: " . $batch_size . " " );
			print( "Retries: " . $retries . " " );
			print_r( $e );
			die();
		}
	}

	public function index_batch_of_posts( $offset, $batch_size ) {
		$posts_query = array(
			'numberposts' => $batch_size,
			'offset' => $offset,
			'orderby' => 'id',
			'order' => 'ASC',
			'post_status' => 'publish',
			'post_type' => $this->allowed_post_types()
		);
		$posts = get_posts( $posts_query );
		$total_posts = count( $posts );
		$retries = 0;
		$resp = NULL;
		$num_written = 0;

		if( $total_posts > 0 ) {
			$documents = array();
			foreach( $posts as $post ) {
				if( $this->should_index_post( $post ) ) {
					$document = $this->convert_post_to_document( $post );

					if ( $document ) {
						$documents[] = $document;
					}
				}
			}
			if( count( $documents ) > 0 ) {
				while( is_null( $resp ) ) {
					try {
						$resp = $this->client->create_or_update_documents( $this->engine_slug, $this->document_type_slug, $documents );
					} catch( SwiftypeError $e ) {
						if( $retries >= $this->max_retries ) {
							throw $e;
						} else {
							$retries++;
							sleep( $this->retry_delay );
						}
					}
				}

				foreach( $resp as $record ) {
					if( $record ) {
						$num_written += 1;
					}
				}
			}
		}

		return array( $num_written, $total_posts );
	}

	public function async_delete_batch_of_trashed_posts() {
		check_ajax_referer( 'swiftype-ajax-nonce' );

		$offset = isset( $_POST['offset'] ) ? intval( $_POST['offset'] ) : 0;
		$batch_size = isset( $_POST['batch_size'] ) ? intval( $_POST['batch_size'] ) : 10;
		$total_posts = 0;

		try {
			$total_posts = $this->delete_batch_of_trashed_posts( $offset, $batch_size );
		} catch ( SwiftypeError $e ) {
			header( 'HTTP/1.1 500 Internal Server Error' );
			print( 'Error in Delete all Trashed Posts.' );
			print_r( $e );
			die();
		}

		header( "Content-Type: application/json" );
		print( json_encode( array( 'total' => $total_posts ) ) );
		die();
	}
}