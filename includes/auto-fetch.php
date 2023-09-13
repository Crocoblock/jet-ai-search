<?php
namespace JET_AI_Search;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Auto fetch manager
 */
class Auto_Fetch {

	private $post_types = [];

	public function __construct( array $post_types = [] ) {

		$this->post_types = $post_types;

		if ( empty( $this->post_types ) ) {
			return;
		}

		foreach ( $this->post_types as $post_type ) {
			add_action( 'save_post_' . $post_type, [ $this, 'fetch_post' ], 10, 2 );
		}

		add_action( 'delete_post', [ $this, 'clear_auto_fetched' ], 10, 2 );

	}

	public function fetch_post( $post_id, $post ) {
		Plugin::instance()->data->write_embeddings( Plugin::instance()->data->fetch_post( $post ) );
	}

	public function clear_auto_fetched( $post_id, $post ) {
		if ( in_array( $post->post_type, $this->post_types ) ) {
			Plugin::instance()->db->delete( [ 'post_id' => $post_id ] );
		}
	}

}
