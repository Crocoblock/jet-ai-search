<?php
namespace JET_AI_Search;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Proxy class to easier control real storage implementation
 */
class Remote_Storage {

	protected $_items_stack = [];

	public $last_response = false;

	public function write() {

		$response = wp_remote_post( 'https://www.ai-search.omstore.in.ua/data', [
			'headers' => [
				'Content-Type' => 'application/json',
			],
			'body' => json_encode( [
				'token' => Plugin::instance()->settings->get( 'license_token' ),
				'items' => $this->_items_stack,
			] )
		] );

		$this->last_response = wp_remote_retrieve_body( $response );
	}

	public function insert( $item ) {
		$this->_items_stack[] = $item;
	}

	public function truncate() {

	}

	public function get_match( $query ) {

		$response = wp_remote_post( 'https://www.ai-search.omstore.in.ua/search', [
			'headers' => [
				'Content-Type' => 'application/json',
			],
			'body' => json_encode( [
				'token'        => Plugin::instance()->settings->get( 'license_token' ),
				'search_query' => $query,
			] )
		] );

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $body && isset( $body['items'] ) ) {
			return $body['items'];
		} else {
			return [];
		}
	}

}
