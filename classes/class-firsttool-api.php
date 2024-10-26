<?php
/**
 * Manages API EndPoint request process
 *
 * This file is used for sending API request to 1Tool API endpoints and returns
 * the response received from request API.
 *
 * @package FIRSTTOOL_API
 * @since 1.0.0
 */

namespace FIRSTTOOL_API;

/**
 * Used for making 1Tool API Calls
 *
 * Use this class to send POST or GET request to 1Tool API with provided data to be sent.
 *
 * @since 1.0.0
 */
class FirstTool_API{

	/**
	 * Response code of API EndPoint request sent.
	 *
	 * @since 1.0.0
	 * @var int $response_code HTTP response code of API request
	 */
	var $response_code;

	/**
	 * Response message of API EndPoint request sent.
	 *
	 * @since 1.0.0
	 * @var int $response_message Response message that is received from API
	 * request or blank if no message from API request.
	 */
	var $response_message;
	
	/**
	 * Sends POST request to 1Tool API
	 *
	 * Send POST request with provided data in parameter on requested EndPoint.
	 *
	 * @since 1.0.0
	 *
	 * @see wp_remote_get()
	 *
	 * @param string $end_point Name of API EndPoint to send POST request to.
	 * @param array $data Optional. Data to be sent in POST request to EndPoint. array()
	 * @return array data returned from API EndPoint is returned as it is.
	 */
	public function km_post_request( $end_point, $data = array() ) {
		$api_url = 'https://api.kundenmeister.com/v13/';
		$km_accessToken = get_option( 'km_accessToken', true );
		
		$body = json_encode( $data );
		$response_hooks = wp_remote_post( $api_url.$end_point, array(
			'body'		=> $body,
			'timeout'	=> 120,
			'headers'	=> array(
				'content-type' => 'application/json',
				'token' => $km_accessToken,
			),
		) );
		
		$this->response_code = wp_remote_retrieve_response_code( $response_hooks );
		$response_data = json_decode( wp_remote_retrieve_body( $response_hooks ), true );
		
		if ( $this->response_code !== 200 ) {
			$this->response_message = isset( $response_data["message"] ) ? $response_data["message"] : "";
			return $response_data;
		}
		
		return $response_data;
	}
	
	/**
	 * Sends PUT request to 1Tool API
	 *
	 * Send PUT request with provided data in parameter on requested EndPoint.
	 *
	 * @since 1.1.0
	 *
	 * @see wp_remote_request()
	 *
	 * @param string $end_point Name of API EndPoint to send POST request to.
	 * @param array $data Optional. Data to be sent in POST request to EndPoint. array()
	 * @return array data returned from API EndPoint is returned as it is.
	 */
	public function km_put_request( $end_point, $data = array() ) {
		$api_url = 'https://api.kundenmeister.com/v13/';
		$km_accessToken = get_option( 'km_accessToken', true );
		
		$body = json_encode( $data );
		$response_hooks = wp_remote_request( $api_url.$end_point, array(
			'body'		=> $body,
			'method'	=> 'PUT',
			'timeout'	=> 120,
			'headers'	=> array(
				'content-type' => 'application/json',
				'token' => $km_accessToken,
			),
		) );
		
		$this->response_code = wp_remote_retrieve_response_code( $response_hooks );
		$response_data = json_decode( wp_remote_retrieve_body( $response_hooks ), true );
		
		if ( $this->response_code !== 200 ) {
			$this->response_message = isset( $response_data["message"] ) ? $response_data["message"] : "";
			return $response_data;
		}
		
		return $response_data;
	}
	
	/**
	 * Sends GET request to 1Tool API
	 *
	 * Send a GET request to 1Tool API EndPoint which is provided in parameter
	 *
	 * @since 1.0.0
	 *
	 * @param string $end_point Name of API EndPoint to send POST request to.
	 * @param array $data Optional. Data to be sent in POST request to EndPoint. array()
	 * @return array data returned from API EndPoint is returned as it is.
	 */
	public function km_get_request( $end_point, $data = array() ) {
		$api_url = 'https://api.kundenmeister.com/v13/';
		$km_accessToken = get_option( 'km_accessToken', true );
		
		$body = $data;
		$response_hooks = wp_remote_get( $api_url.$end_point, array(
			'body'		=> $body,
			'timeout'	=> 120,
			'headers' => array(
				'content-type' => 'application/json',
				'token' => $km_accessToken,
			),
		) );
		
		$this->response_code = wp_remote_retrieve_response_code( $response_hooks );
		$response_data = json_decode( wp_remote_retrieve_body( $response_hooks ), true );
		if ( $this->response_code !== 200 ) {
			$this->response_message = isset( $response_data["message"] ) ? $response_data["message"] : "";
			return $response_data;
		}
		
		return $response_data;
	}
}
?>