<?php
namespace JET_AI_Search;

/**
 * Database manager class
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Define Open_AI api class
 */
class Open_AI {

	private $url;
	private $api_key;

	private $error = false;

	public function __construct( $api_key ) {
		$this->url         = 'https://api.openai.com/v1/';
		$this->api_key     = $api_key;
	}

	private function prepare_body( $body = [], $headers = [], $method = 'POST' ) {

		return [
			'body'    => $body,
			'headers' => array_merge( [
				'Content-Type'  => 'application/json',
				'Authorization' => 'Bearer ' . $this->api_key,
				'user-agent'    => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . get_bloginfo( 'url' ),
			], $headers ),
			'method'  => $method,
			'data_format' => 'body',
			'timeout' => 60,
			'stream' => false,
			'filename' => '',
			'decompress' => false,
		];

	}

	public function request( $path, $data = [], $headers = [], $method = 'POST' ) {

		$curl = new \WP_Http_Curl();

		$response = $curl->request( 
			$this->url . $path, 
			$this->prepare_body( $data, $headers, $method )
		);

		$result = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! empty( $result['error'] ) && ! empty( $result['error']['message'] ) ) {
			$this->error = $result['error']['message'];
		}

		return $result;
	}

	public function get() {

		$body     = $this->prepare_body();
		$response = wp_remote_post( $this->url, $body );

		if ( is_wp_error( $response ) ) {
			$this->error = $response->get_error_message();
			return false;
		} else {

			$response_data = json_decode( wp_remote_retrieve_body( $response ), true );
			$response = isset( $response_data['choices'][0]['message']['content'] ) ? $response_data['choices'][0]['message']['content'] : false;

			if ( ! $response ) {
				$this->error = 'Internal error. Please try again later.';
			}

			if ( ! empty( $response_data['error'] ) && ! empty( $response_data['error']['message'] ) ) {
				$this->error = $response_data['error']['message'];
			}

			return $response;

		}

	}

	public function get_error() {
		return $this->error;
	}
}
