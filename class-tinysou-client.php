<?php
/**
	* Client for the Tinysou API
	*
	* This class encapsulates all remote communication via HTTP with the Tinysou API.
	* If the client encounters any errors, it throws an instance of the TinysouError exception class.
	*
	* @author  Tairy <tairyguo@gmail.com>
	*
	* @since 1.0
	*
	*/
class TinysouClient {
	
	private $endpoint = 'http://api.tinysou.com/v1/';

	private $api_key  = NULL;

	public function __construct() { }

	public function get_api_key() {
		return $this->api_key;
	}

	public function set_api_key($api_key) {
		$this->api_key = $api_key;
	}

	public function authorized() {
		$url = $this->endpoint;
		try {
			$response = $this->call_api( 'GET', $url );
			return( 200 == $response['code'] );
		} catch( TinysouError $e) {
			return false;
		}
	}

	/**
	* Create an engine
	*
	* @param array $params An array of engine parameters
	* @return array The engine that was created
	*/
	public function create_engine( $params ) {
		$engine = $params;
		$url = $this->endpoint . "engines";
		$response = $this->call_api( 'POST', $url, $engine );
		return json_decode( $response['body'], true );
	}

/**
	* Create a create_collection within an engine
	*
	* @param string $engine_id The engine_id of the engine in which to create the document_type
	* @param string $document_type_name The name of the document_type to be created
	* @return array An array representing the document_type that was created
	*/

	public function create_collection( $engine_id, $collection_name ) {
		$field_types = array(
			'title' => 'string', 
			'content' => 'text', 
			'publish_date' => 'date'
		);

		$params = array( 'name' => $collection_name , 'field_types' => $field_types);
		$url = $this->endpoint . 'engines/' . $engine_id . '/collections';
		$response = $this->call_api( 'POST', $url, $params );
		return json_decode( $response['body'], true );
	}

	public function create_or_update_documents( $engine_name, $collection_name, $document ) {
		error_log($document['id']);
		$url = $this->endpoint . 'engines/' . $engine_name . '/collections/' . $collection_name . '/documents/'.md5('posts_'.$document['id']);
		$response = $this->call_api( 'PUT', $url, $document );
		return json_decode( $response['body'], true );
	}

	public function delete_documents( $engine_name, $collection_name, $document_id) {
		$url = $this->endpoint . 'engines/' . $engine_name . '/collections/' . $collection_name . '/documents/'.$document_id;
		$response = $this->call_api( 'DELETE', $url );
		return json_decode( $response['body'], true );
	}

	public function search($engine_name, $collection_name, $query, $params = array()) {
		$params = array_merge( array( 'q' => $query, 'page' => 1), $params);
		$url = $this->endpoint . 'engines/' . $engine_name . '/collections/' . $collection_name . '/search';
		$response = $this->call_api( 'GET', $url, $params );
		return json_decode( $response['body'], true );
	}

	public function get_searchable_post_num( $engine_name ) {
		$url = $this->endpoint . 'engines/' .$engine_name;
		$response = $this -> call_api( 'GET', $url );
		return json_decode( $response['body'], true );
	}

	private function call_api( $method, $url, $params = array() ) {
		if( $this->api_key ) {
			$params['auth_token'] = $this->api_key;
		} else {
			throw new TinysouError( 'Unauthorized', 403 );
		}

		$headers = array(
			'User-Agent' => 'Tinysou Wordpress Plugin/' . TINYSOU_VERSION,
			'Content-Type' => 'applocation/json',
			'Authorization:' => 'token ' . $this->api_key
		);

		$args = array(
			'method' => '',
			'timeout' => 10,
			'reidrection' => 5,
			'httpversion' => '1.0',
			'blocking' => true,
			'headers' => $headers,
			'coolies' => array()
		);

		if( 'GET' == $method || 'DELETE' == $method ) {
			$url .= '?' . $this -> serialize_params( $params );
			$args['method'] = $method;
			$args['body'] = array();
		} else if( 'POST' == $method || 'PUT' == $method ) {
			$args['method'] = $method;
			$args['body'] = json_encode( $params );
		} 

		$response = wp_remote_request( $url, $args );

		if( ! is_wp_error( $response ) ) {
			$response_code = wp_remote_retrieve_response_code( $response );
			$response_message = wp_remote_retrieve_response_message( $response );
			if( $response_code >= 200 && $response_code < 300 ) {
				$response_body = wp_remote_retrieve_body( $response );
				return array( 'code' => $response_code, 'body' => $response_body );
			} elseif( !empty( $response_message) ) {
				$response_body = wp_remote_retrieve_body( $response );
				throw new TinysouError( $response_body, $response_code );
			} else {
				throw new TinysouError( 'Unknown Error', $response_code );
			}
		} else {
			throw new TinysouError( $response->get_error_message(), 500 );
		}
	}

	private function serialize_params( $params ) {
		$query_string = http_build_query( $params );
		return preg_replace( '/%5B(?:[0-9]+)%5D=/', '%5B%5D=', $query_string );
	}
}