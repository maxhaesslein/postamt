<?php

class Session {

	public $me;
	public $canonical_me;
	public $me_folder;
	public $scope;
	private $access_token;

	function __construct( $postamt ) {

		// TODO: rate-limit

		$access_token = $this->get_bearer_token();

		if( ! $access_token && isset($_REQUEST['access_token'])) {
			$access_token = $_REQUEST['access_token'];
		}

		if( ! $access_token ) {
			$postamt->error( 'unauthorized', 'no access token was provided' );
		}

		if( ! isset($_REQUEST['me']) ) {
			$postamt->error( 'unauthorized', 'no me parameter was provided' );
		}
		$me = $_REQUEST['me'];

		$this->me = $me;
		$this->canonical_me = un_trailing_slash_it($me); // NOTE: for our purposes, https://www.example.com/ and https://www.example.com are the same user

		$indieauth = new IndieAuth();
		$url = $indieauth->normalize_url( $me );
		$token_endpoint = $indieauth->discover_endpoint( 'token_endpoint', $url );
		if( ! $token_endpoint ) {
			$postamt->error( 'unauthorized', 'could not find token endpoint (me url does not provide a token_endpoint)' );
		}

		$this->token_endpoint = $token_endpoint;

		$token_endpoint_request = new Request();
		$token_verify = $token_endpoint_request->get( $token_endpoint, false, [ 'Authorization: Bearer '.$access_token ] );

		$token_verify = explode( '&', $token_verify );
		$token_response = [];
		foreach( $token_verify as $url_part ) {
			$url_part = explode( '=', $url_part );
			$token_response[$url_part[0]] = urldecode($url_part[1]);
		}

		if( isset($token_response['active']) && ! $token_response['active'] ) {
			$postamt->error( 'unauthorized', 'could not verify via token endpoint (access_token responded with active=false)' );
		}

		if( ! isset($token_response['me']) || ! isset($token_response['scope']) ) {
			$postamt->error( 'unauthorized', 'could not verify via token endpoint (access_token did not provide me and/or scope parameter)' );
		}

		if( un_trailing_slash_it($token_response['me']) != $this->canonical_me ) {
			$postamt->error( 'forbidden', 'The authenticated user does not have permission to perform this request (access_token me does not match provided me)', 403 );
		}

		$allowed_users = $postamt->config->get('allowed_urls');
		$cleaned_allowed_users = array_map( 'un_trailing_slash_it', $allowed_users );
		if( ! in_array( $this->canonical_me, $cleaned_allowed_users ) ) {
			$postamt->error( 'forbidden', 'The authenticated user does not have permission to perform this request (this user does not exist in the system)', 403 );
		}

		$this->access_token = $access_token;
		
		$scope = explode( ' ', $token_response['scope'] );

		$this->scope = $scope;


		$this->check_content_folder();

	}


	function check_scope( $expected_scope ) {

		$scope_valid = true;

		if( is_array($expected_scope) ) {
			foreach( $expected_scope as $expected_sub_scope ) {
				if( ! $this->check_scope($expected_sub_scope) ) {
					$scope_valid = false;
				}
			}
		} else {

			if( ! $this->scope ) {
				$scope_valid = false;
			}

			if( ! in_array( $expected_scope, $this->scope ) ) {
				$scope_valid = false;
			}

		}

		return $scope_valid;
	}


	function check_content_folder(){

		global $postamt;

		$me = $this->canonical_me;

		if( ! $me ) return;

		$me_folder = str_replace( array('https://www.', 'http://www.', 'https://', 'http://'), '', $me );

		$me_folder = sanitize_folder_name( $me_folder );

		if( ! $me_folder ) return;

		$this->me_foldername = $me_folder;

		$this->me_folder = 'content/'.$me_folder.'/';

		if( ! is_dir($postamt->abspath.$this->me_folder) ) {
			if( mkdir( $postamt->abspath.$this->me_folder, 0777, true ) === false ) {
				$postamt->error( 'internal_server_error', 'could not create user folder', 500 );
			}
		}

		return $this;
	}


	function get_authorization_header(){
		$headers = null;
		if( isset($_SERVER['Authorization']) ){
			$headers = trim($_SERVER["Authorization"]);
		} elseif( isset($_SERVER['HTTP_AUTHORIZATION']) ){ //Nginx or fast CGI
			$headers = trim($_SERVER["HTTP_AUTHORIZATION"]);
		} elseif( function_exists('apache_request_headers') ){
			$requestHeaders = apache_request_headers();
			// Server-side fix for bug in old Android versions (a nice side-effect of this fix means we don't care about capitalization for Authorization)
			$requestHeaders = array_combine(array_map('ucwords', array_keys($requestHeaders)), array_values($requestHeaders));
			if( isset($requestHeaders['Authorization']) ){
				$headers = trim($requestHeaders['Authorization']);
			}
		}

		return $headers;
	}

	
	function get_bearer_token() {

		$headers = $this->get_authorization_header();
		if( empty($headers) ) return false;

		if( ! preg_match('/Bearer\s(\S+)/', $headers, $matches) ) return false;

		return $matches[1];
	}


};
