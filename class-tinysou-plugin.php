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
	private $collection_name = 'posts';

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
		add_action( 'publish_post', array( $this, 'sync_single_post' ));

		if ( ! is_admin() ){
			add_action( 'wp_enqueuie_scripts', array( $this, 'enqueue_tinysou_assets' ) );
			add_action( 'pre_get_posts', array( $this, 'get_posts_from_tinysou') );
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
		$this->engine_key = get_option( 'tinysou_engine_key' );
		$this->engine_name = get_option( 'tinysou_engine_name' );

		$this->client = new TinysouClient();
		$this->client->set_api_key( $this->api_key );
	}

	public function initialize_admin_screen() {
		if ( current_user_can ('manage_options' ) ) {
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
			add_action( 'admin_menu', array( $this, 'tinysou_menu') );
			add_action( 'wp_ajax_index_batch_of_posts', array( $this, 'async_index_batch_of_posts' ) );
			add_action( 'wp_ajax_sync_posts', array($this, 'async_posts'));
			add_action( 'admin_notices', array( $this, 'error_notice' ) );

			if ( isset( $_POST['action'] ) ) {
				switch ( $_POST['action'] ) {
					case 'tinysou_set_api_key':
						check_admin_referer( 'tinysou-nonce' );
						$api_key = sanitize_text_field( $_POST['api_key'] );
						update_option( 'tinysou_api_key', $api_key );
						delete_option( 'tinysou_engine_slug' );
						delete_option( 'tinysou_num_indexed_documents' );
						break;

					case 'tinysou_create_engine':
						check_admin_referer( 'tinysou-nonce' );
						$engine_name = sanitize_text_field( $_POST['engine_name'] );
						update_option( 'tinysou_create_engine', $engine_name );
						break;
					
					case 'tinysou_clear_config':
						check_admin_referer( 'tinysou-nonce' );
						$this -> clear_config();
						break;

					default:
						break;
				}
			}
		}

		if ( current_user_can( 'edit_post', '' ) ) {
			$this->initialize_api_client();
			$this->check_api_authorized();
			
			if( ! $this->api_authorized )
				return;

			$this->engine_name = get_option( 'tinysou_engine_name' );
			$this->engine_key = get_option( 'tinysou_engine_key' );
			$this->engine_initialized = get_option( 'tinysou_engine_initialized' );
			$this->error = $this->check_engine_initialized();
			if(!empty($this->engine_name)){
				update_option( 'tinysou_searchable_num', $this->get_tinysou_posts_num() );
			}
			if( ! $this->engine_initialized )
				return;

		}
	}

	public function get_posts_from_tinysou( $wp_query ) {
		$this->search_successful = false;
		if( function_exists( 'is_main_query' ) && ! $wp_query->is_main_query() ) {
			return;
		}

		if( is_search() && ! is_admin() && $this->engine_name && strlen( $this->engine_name ) > 0) {
			$query_string = apply_filters( 'tinysou_search_query_string', stripslashes( get_search_query( false ) ) );
			$page = ( get_query_var( 'paged' ) ) ? get_query_var( 'paged' ) : 1;

			$params = array( 'page' => $page );
			if ( isset( $_GET['st-cat'] ) && ! empty( $_GET['st-cat'] ) ) {
				$params['filters[posts][category]'] = sanitize_text_field( $_GET['st-cat'] );
			}

			if ( isset( $_GET['st-facet-field'] ) && isset( $_GET['st-facet-term'] ) ) {
				$params['filters[posts][' . $_GET['st-facet-field'] . ']'] = $_GET['st-facet-term'];
			}

			$params = apply_filters( 'tinysou_search_params', $params );
			try {
				$this->results = $this->client->search( $this->engine_name, $this->collection_name, $query_string, $params );
			} catch( TinysouError $e ) {
				$this->results = NULL;
				$this->search_successful = false;
			}

			if( ! isset( $this->results ) ) {
				$this->search_successful = false;
				return;
			}

			$this->post_ids = array();
			$records = $this->results['records']['posts'];

			foreach( $records as $record ) {
				$this->post_ids[] = $record['external_id'];
			}

			$result_info = $this->results['info']['posts'];
			$this->per_page = $result_info['per_page'];

			$this->total_result_count = $result_info['total_result_count'];
			$this->num_pages = $result_info['num_pages'];
			set_query_var( 'post__in', $this->post_ids);
			$this->search_successful = true;

			add_filter( 'post_class', array( $this, 'tinysou_post_class' ) );
		}
	}

	public function tinysou_post_class( $classes ) {
		global $post;

		$classes[] = 'tinysou-result';
		$classes[] = 'tinysou-result-' . $post->ID;

		return $classes;
	}

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
		if( $this->api_authorized ){
			return;
		}
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

	public function check_engine_initialized() {
		$engine = NULL;

		if( ! is_admin() ) {
			return;
		}
		if( $this->engine_initialized )
			return;

		$engine_name = get_option( 'tinysou_create_engine' );
		if( $engine_name == false ) {
			return;
		}

		try {
			$this->initialize_engine( $engine_name );
			update_option('tinysou_engine_initialized',true);
			// update_option( 'tinysou_searchable_num', $this->get_tinysou_posts_num() );
		} catch (TinysouError $e ) {
			$error_message = json_decode( $e->getMessage() );
			return "<b>Engine 配置失败，微搜索服务器发生故障。</b>错误原因：". $error_message->message;
		}
	}

	public function initialize_engine( $engine_name ) {
		$engine = $this->client->create_engine( array( 'name' => $engine_name ) );
		$this->engine_name = $engine['name'];
		$this->engine_key = $engine['key'];

		$collection = $this->client->create_collection( $this->engine_name, $this->collection_name );
		if( $collection ) {
			$this->engine_initialized = true;
		}

		delete_option( 'tinysou_create_engine' );
		update_option( 'tinysou_engine_name', $this->engine_name );
		update_option( 'tinysou_engine_key', $this->engine_key );
		update_option( 'tinysou_engine_initialized', $this->engine_initialized );
	}

	public function clear_config() {
		delete_option( 'tinysou_create_engine' );
		delete_option( 'tinysou_api_key' );
		delete_option( 'tinysou_api_authorized' );
		delete_option( 'tinysou_engine_name' );
		delete_option( 'tinysou_engine_key' );
		delete_option( 'tinysou_engine_initialized' );
		delete_option( 'tinysou_searchable_num' );
		delete_option( 'tinysou_create_engine' );
	}

	public function enqueue_admin_assets($hook) {
		if( 'toplevel_page_tinysou' != $hook ) {
			return;
		}

		wp_enqueue_style( 'admin_styles', plugins_url( 'assets/admin_style.css', __FILE__ ) );
	}

	public function async_posts() {
		check_ajax_referer( 'tinysou-ajax-nonce' );
		try {
			list( $num_written, $total_posts ) = $this->send_posts_to_tinysou();
			header( 'Content-Type: application/json' );
			print( json_encode( array( 'num_written' => $num_written, 'total' => $total_posts ) ) );
			die();
		} catch ( TinysouError $e ){
			header( 'HTTP/1.1 500 Internal Server Error' );
			print( "Error in Create or Update Documents. " );
			print_r( $e );
			die();
		}
	}

	public function sync_single_post($post_ID) {
		$post = get_post( $post_ID );
		$document = $this->convert_post_to_document( $post );
		while( is_null( $resp ) ) {
			try {
				$resp = $this->client->create_or_update_documents( $this->engine_name, $this->collection_name, $document );
			} catch( TinysouError $e ) {
				if( $retries >= $this->max_retries ) {
					throw $e;
				} else {
					$retries++;
					sleep( $this->retry_delay );
				}
			}
		}
	}

	public function send_posts_to_tinysou() {
		$count_posts = wp_count_posts();
		$published_posts = $count_posts->publish;
		$posts_query = array(
			'numberposts' => $published_posts,
			'offset' => 0,
			'orderby' => 'id',
			'order' => 'ASC',
			'post_status' => 'publish',
			'post_type' => $this->allowed_post_types()
		);
		$posts = get_posts( $posts_query );
		$total_posts = count( $posts );
	
		if( $total_posts > 0 ) {
			foreach( $posts as $post ) {
				if( $this->should_index_post( $post ) ) {
					$retries = 0;
					$resp = NULL;
					$num_written = 0;

					$document = $this->convert_post_to_document( $post );
					// error_log($document['title']);
					while( is_null( $resp ) ) {
						try {
							$resp = $this->client->create_or_update_documents( $this->engine_name, $this->collection_name, $document );
						} catch( TinysouError $e ) {
							if( $retries >= $this->max_retries ) {
								throw $e;
							} else {
								$retries++;
								sleep( $this->retry_delay );
							}
						}
					}
					// if($resp){
					// 	$num_written += 1;
					// }
				}
			}
		}
		return array( $total_posts );
	}

	public function get_tinysou_posts_num() {
		try{
			$resp = $this->client->get_searchable_post_num($this->engine_name);
			return $resp['doc_count'];
		} catch( TinysouError $e ) {
			throw $e;
		}
	}

	private function convert_post_to_document( $somepost ) {
		global $post;
		$post = $somepost;

		$nickname = get_the_author_meta( 'nickname', $post->post_author );
		$first_name = get_the_author_meta( 'first_name', $post->post_author );
		$last_name = get_the_author_meta( 'last_name', $post->post_author );
		$name = $first_name . " " . $last_name;

		$tags = get_the_tags( $post->ID );
		$tag_strings = array();
		if( is_array( $tags ) ) {
			foreach( $tags as $tag ) {
				$tag_strings[] = $tag->name;
			}
		}

		$document = array();
		$document['id'] = $post->ID;
		$document['title'] = html_entity_decode( strip_tags( $post->post_title ));
		$document['content'] = html_entity_decode( strip_tags( $this->strip_shortcodes_retain_contents( $post->post_content ) ));
		$document['publish_data'] = $post->post_date_gmt;
		$document['author'] =array( $nickname, $name );

		$document = apply_filters( "tinysou_document_builder", $document, $post );

		return $document;
	}

	public function enqueue_tinysou_assets() {
		if( is_admin() ) {
			return;
		}
		wp_enqueue_style( 'tinysou', plugins_url('assets/autocomplete.css', __FILE__ ) );
		wp_enqueue_script( 'tinysou', plugins_url('assets/install_tinysou.min.js', __FILE__ ) );
		wp_localize_script( 'tinysou', 'tinysouParams', array( 'engineKey' => $this->engine_key ) );
	}

	private function should_index_post( $post ) {
	  return ( in_array( $post->post_type, $this->allowed_post_types() ) && ! empty( $post->post_title ) );
	}


	private function allowed_post_types() {
		$allowed_post_types = array( 'post', 'page' );
		if ( function_exists( 'get_post_types' ) ) {
			$allowed_post_types = array_merge( get_post_types( array( 'exclude_from_search' => '0' ) ), get_post_types( array( 'exclude_from_search' => false ) ) );
		}
		return $allowed_post_types;
	}

	private function strip_shortcodes_retain_contents( $content ) {
		global $shortcode_tags;

		if ( empty($shortcode_tags) || !is_array($shortcode_tags) )
			return $content;

		$pattern = get_shortcode_regex();

		# Replace the short code with its content (the 5th capture group) surrounded by spaces
		return preg_replace("/$pattern/s", ' $5 ', $content);
	}
}