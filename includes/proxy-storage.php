<?php
namespace JET_AI_Search;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Proxy class to easier control real storage implementation
 */
class Proxy_Storage {

	protected $storage;

	public function set_storage( $storage ) {
		$this->storage = $storage;
	}

	public function write() {
		$this->storage->write();
	}

	public function insert( $item ) {
		$this->storage->insert( $item );
	}

	public function truncate() {

	}

	public function get_match( $query ) {
		return $this->storage->get_match( $query );
	}

	public function get_last_response() {
		return isset( $this->storage->last_response ) ? $this->storage->last_response : false;
	}
}
