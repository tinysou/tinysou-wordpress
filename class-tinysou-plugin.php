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
		//add_action( 'future_to_publish', array( $this, 'handle_future_to_publish') );

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
		$this->engine_slug = get_option( 'tinysou_engine_slug' );
		$this->engine_key = get_option( 'tinysou_engine_key' );

		$this->client = new TinysouClient();
		$this->client->set_api_key( $this->api_key );
	}

	public function initialize_admin_screen() {
		if ( current_user_can ('manage_options' ) ) {
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
			add_action( 'admin_menu', array( $this, 'tinysou_menu') );
			add_action( 'wp_ajax_index_batch_of_posts', array( $this, 'async_index_batch_of_posts' ) );
			add_action( 'wp_ajax_sync_posts', array($this, 'async_posts'));
			// add_action( 'wp_ajax_refresh_num_indexed_documents', array( $this, 'async_refresh_num_indexed_documents') );
			add_action( 'wp_ajax_delete_batch_of_trashed_posts', array( $this, 'async_delete_batch_of_trashed_posts' ) );
			// add_action( 'admin_notices', array( $this, 'error_notice' ) );

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

		if ( current_user_can( 'edit_post' ) ) {
			add_action( 'save_post', array( $this, 'handle_save_post' ), 99, 1);
			add_action( 'transition_post_status', array( $this, 'handle_transition_post_status'), 99, 3 );
			add_action( 'trashed_post', array( $this, 'delete_post') );

			$this->initialize_api_client();
			$this->check_api_authorized();
			if( ! $this->api_authorized )
				return;

			$this->num_indexed_documents = get_option( 'tinysou_num_indexed_documents' );
			//$this->engine_slug = get_option( 'tinysou_engine_slug' );
			$this->engine_name = get_option( 'tinysou_engine_name' );
			$this->engine_key = get_option( 'tinysou_engine_key' );
			$this->engine_initialized = get_option( 'tinysou_engine_intialized' );
			$this->error = $this->check_engine_initialized();
			if( ! $this->engine_initialized )
				return;
		}
	}

	public function tinysou_post_class( $classes ) {
		global $post;

		$classes[] = 'tinysou-result';
		$classes[] = 'tinysou-result-' . $post->ID;

		return $classes;
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
		} catch (TinysouError $e ) {
			$error_message = json_decode( $e->getMessage() );
			return "<b>Engine 配置失败，微搜索服务器发生故障。</b>错误原因：". $error_message->message;
		}
	}

	public function initialize_engine( $engine_name ) {
		$engine = $this->client->create_engine( array( 'name' => $engine_name ) );
		$this->engine_name = $engine['name'];
		$this->engine_key = $engine['key'];

		// create a collection named posts
		$collection = $this->client->create_collection( $this->engine_name, $this->collection_name );
		//$this->engine_initialized = true;
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
		delete_option( 'tinysou_engine_slug' );
		delete_option( 'tinysou_engine_name' );
		delete_option( 'tinysou_engine_key' );
		delete_option( 'tinysou_engine_initialized' );
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

		} catch ( TinysouError $e ) {
			header( 'HTTP/1.1 500 Internal Server Error' );
			print( "Error in Create or Update Documents. " );
			print( "Offset: " . $offset . " " );
			print( "Batch Size: " . $batch_size . " " );
			print( "Retries: " . $retries . " " );
			print_r( $e );
			die();
		}
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
			// print( "Offset: " . $offset . " " );
			// print( "Batch Size: " . $batch_size . " " );
			// print( "Retries: " . $retries . " " );
			print_r( $e );
			die();
		}
		// $offset = isset( $_POST['offset'] ) ? intval( $_POST['offset'] ) : 0;
		// $batch_size = isset( $_POST['batch_size'] ) ? intval( $_POST['batch_size'] ) : 10;
		// try {
		// 	list( $num_written, $total_posts ) = $this->index_batch_of_posts( $offset, $batch_size );
		// 	header( 'Content-Type: application/json' );
		// 	print( json_encode( array( 'num_written' => $num_written, 'total' => $total_posts ) ) );
		// 	die();

		// } catch ( TinysouError $e ) {
		// 	header( 'HTTP/1.1 500 Internal Server Error' );
		// 	print( "Error in Create or Update Documents. " );
		// 	print( "Offset: " . $offset . " " );
		// 	print( "Batch Size: " . $batch_size . " " );
		// 	print( "Retries: " . $retries . " " );
		// 	print_r( $e );
		// 	die();
		// }	
	}

	public function send_posts_to_tinysou() {
		$posts_query = array(
			// 'numberposts' => $batch_size,
			// 'offset' => $offset,
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
			// /$documents = array();
			foreach( $posts as $post ) {
				if( $this->should_index_post( $post ) ) {
					$document = $this->convert_post_to_document( $post );
					while( is_null( $resp ) ) {
						try {
							$resp = $this->client->create_or_update_documents( $this->engine_name, $this->collection_name, $document );
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
		}
		return array( $num_written, $total_posts );
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

					while( is_null( $resp ) ) {
						try {
							error_log($document['id']);
							$resp = $this->client->create_or_update_documents( $this->engine_name, $this->collection_name, $document );
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
		}
		return array( $num_written, $total_posts );
	}

	public function async_delete_batch_of_trashed_posts() {
		check_ajax_referer( 'tinysou-ajax-nonce' );

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

	public function delete_batch_of_trashed_posts( $offset, $batch_size ) {
		$document_ids = array();

		$posts_query = array(
			'numberposts' => $batch_size,
			'offset' => $offset,
			'orderby' => 'id',
			'order' => 'ASC',
			'post_status' => array_diff( get_post_stati(), array( 'publish' ) ),
			'post_type' => $this->allowed_post_types(),
			'fields' => 'ids'
		);

		$posts = get_posts( $posts_query );
		$total_posts = count( $posts );
		$retries = 0;
		$resp = NULL;

		if( $total_posts ) {
			foreach( $posts as $post_id ) {
				while( is_null( $resp ) ) {
					try {
						$resp = $this->client->delete_documents( $this->engine_name, $this->collection_name, $post_id );
					} catch( SwiftypeError $e ) {
						if( $retries >= $this->max_retries ) {
							throw $e;
						} else {
							$retries++;
							sleep( $this->retry_delay );
						}
					}
				}
			}
		}
	}

	/**
		* Sends a post to Tinysou for indexing as long as the status of the post is 'publish'
		*
		* @param int $post_id The ID of the post to be indexed.
		*/

	public function handle_save_post( $post_id ) {
		$post = get_post( $posy_id );
		if( "publish" == $post->post_status ) {
			$this->index_post( $post_id );
		}
	}

	public function index_post( $post_id ) {
		$post = get_post( $post_id );
		if( ! $this->should_index_post( $post ) ) {
			return;
		}

		$document = $this->convert_post_to_document( $post );

		try {
			$this->client->create_or_update_document ( $this->engine_slug, $this->document_type_slug, $document );
			$this->num_indexed_documents += 1;
			update_option( 'tinysou_num_indexed_documents', $this->num_indexed_documents );
		} catch( TinysouError $e) {
			return;
		}
	}

	/**
		* Deletes a post from Tinysou's search index any time the post's status transitions from 'publish' to anything else.
		*
		* @param int $new_status The new status of the post
		* @param int $old_status The old status of the post
		* @param int $post The post
		*/

	public function handle_transition_post_status( $new_status, $old_status, $post ) {
		if ( "publish" == $old_status && "publish" != $new_status ) {
			$this->delete_post( $post->ID );
		}
	}

	/**
		* Sends a request to the Swiftype API remove a specific post from the server-side search engine.
		*
		* @param int $post_id The ID of the post to be deleted.
		*/
	public function delete_post( $post_id ){
		try {
			$this->client->delete_document( $this->engine_slug, $this->document_type_slug, $post_id );
			$this->num_indexed_documents -= 1;
			update_option( 'swiftype_num_indexed_documents', $this->num_indexed_documents );
		} catch( SwiftypeError $e ) {
			return;
		}
	}

	/**
		* Converts a post into an array that can be sent to the Swiftype API to be indexed in the server-side engine.
		*
		* @param object $somepost The post that is to be converted
		* @return array An array representing the post that is suitable for sending to the Swiftype API
		*/
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
		// $document['external_id'] = $post->ID;
		// $document['post_id'] = $post->ID;
		// $document['fields'] = array();
		// $document['fields'][] = array( 'name' => 'object_type', 'type' => 'enum', 'value' => $post->post_type );
		// $document['fields'][] = array( 'name' => 'url', 'type' => 'enum', 'value' => get_permalink( $post->ID ) );
		// $document['fields'][] = array( 'name' => 'timestamp', 'type' => 'date', 'value' => $post->post_date_gmt );
		// $document['fields'][] = array( 'name' => 'title', 'type' => 'string', 'value' => html_entity_decode( strip_tags( $post->post_title ), ENT_QUOTES, "UTF-8" ) );
		// $document['fields'][] = array( 'name' => 'body', 'type' => 'text', 'value' => html_entity_decode( strip_tags( $this->strip_shortcodes_retain_contents( $post->post_content ) ), ENT_QUOTES, "UTF-8" ) );
		// $document['fields'][] = array( 'name' => 'excerpt', 'type' => 'text', 'value' => html_entity_decode( strip_tags( $post->post_excerpt ), ENT_QUOTES, "UTF-8" ) );
		// $document['fields'][] = array( 'name' => 'author', 'type' => 'string', 'value' => array( $nickname, $name ) );
		// $document['fields'][] = array( 'name' => 'tags', 'type' => 'string', 'value' => $tag_strings );
		// $document['fields'][] = array( 'name' => 'category', 'type' => 'enum', 'value' => wp_get_post_categories( $post->ID ) );
		$document['id'] = $post->ID;
		$document['title'] = html_entity_decode( strip_tags( $post->post_title ));
		$document['content'] = html_entity_decode( strip_tags( $this->strip_shortcodes_retain_contents( $post->post_content ) ));
		$document['publish_data'] = $post->post_date_gmt;
		$document['author'] =array( $nickname, $name );
		// $image = NULL;

		// if ( current_theme_supports( 'post-thumbnails' ) && has_post_thumbnail( $post->ID ) ) {
		// 	// NOTE: returns false on failure
		// 	$image = wp_get_attachment_url( get_post_thumbnail_id( $post->ID ) );
		// }

		// if ( $image ) {
		// 	$document['fields'][] = array( 'name' => 'image', 'type' => 'enum', 'value' => $image );
		// } else {
		// 	$document['fields'][] = array( 'name' => 'image', 'type' => 'enum', 'value' => NULL );
		// }

		$document = apply_filters( "tinysou_document_builder", $document, $post );

		return $document;
	}

	/**
		* Index a post when it transitions from the 'future' state to the 'publish' state
		*
		* @param int $post The post
		*/
	public function handle_future_to_publish( $post ) {
		if( "publish" == $post->post_status ) {
			$this->index_post( $post->ID );
		}
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