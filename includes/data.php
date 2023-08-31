<?php
namespace JET_AI_Search;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Data manager
 */
class Data {

	private $totals = [];
	
	public function get_stats() {

		$result = [];

		foreach ( $this->get_fetchable_post_types() as $post_type ) {

			$counts = $this->count_posts( $post_type['value'] );

			$result[] = [
				'slug'    => $post_type['value'],
				'label'   => $post_type['label'],
				'total'   => $counts->publish,
				'fetched' => $this->count_fetched_posts( $post_type['value'] ),
			];
		}

		return $result;

	}

	public function count_fetched_posts( $post_type ) {

		global $wpdb;

		$table  = Plugin::instance()->db->table();

		if ( empty( $this->totals ) ) {
			$raw_data = (array) $wpdb->get_results( "SELECT DISTINCT `source`, `post_id` FROM {$table}", ARRAY_A );
			foreach ( $raw_data as $row ) {
				if ( ! isset( $this->totals[ $row['source'] ] ) ) {
					$this->totals[ $row['source'] ] = 0;
				}
				$this->totals[ $row['source'] ]++;
			}
		}

		return isset( $this->totals[ $post_type ] ) ? $this->totals[ $post_type ] : 0;

	}

	public function dispatch_clear( $request, $dispatcher ) {

		if ( ! $dispatcher->verify_nonce( $request ) ) {
			wp_send_json_error( 'Link is expired. Reload page and try again' );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Access denied' );
		}

		Plugin::instance()->db->truncate();

		wp_send_json_success();

	}

	public function dispatch_fetch( $request, $dispatcher ) {

		if ( ! $dispatcher->verify_nonce( $request ) ) {
			wp_send_json_error( 'Link is expired. Reload page and try again' );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Access denied' );
		}

		$chunk = $request['chunk'];
		$per_chunk = 10;
		$counts = $this->count_posts( $request['post_type'] );

		if ( ! $counts->publish ) {
			wp_send_json_success( [
				'done' => 0,
				'total' => 0,
				'has_next' => false,
			] );
		}

		$chunks = ceil( $counts->publish / $per_chunk );
		$posts  = get_posts( [
			'post_type'      => $request['post_type'],
			'post_status'    => 'publish',
			'has_password'   => false,
			'offset'         => $per_chunk * ( $chunk - 1 ),
			'posts_per_page' => $per_chunk,
		] );

		Plugin::instance()->db->create_table();

		$embeddings = [];
		$done = $per_chunk * ( $chunk - 1 );
		$post_parser = new Post_Parser();

		foreach ( $posts as $post ) {
			$GLOBALS['post'] = $post;
			setup_postdata( $post );
			$post_content = apply_filters( 'the_content', $post->post_content );
			Plugin::instance()->db->delete( [ 'post_id' => $post->ID ] );
			$embeddings = array_merge( $embeddings, $post_parser->get_post_fragments( 
				$post->ID, 
				$post->post_title, 
				$post->guid,
				$post_content,
				$request['post_type'],
			) );
		
			$done++;
		}

		$open_ai = new Open_AI( Plugin::instance()->settings->get( 'api_key' ) );
		
		$result = $open_ai->request( 'embeddings', json_encode( [
			'model' => 'text-embedding-ada-002',
			'input' => array_map( function( $item ) {
				return $item['fragment'];
			}, $embeddings ),
		] ) );

		if ( ! empty( $result['data'] ) ) {
			foreach ( $result['data'] as $index => $item ) {
				$result_embeddings[] = $item['embedding'];
				$embeddings[ $index ]['embedding'] = json_encode( $item['embedding'] );
				Plugin::instance()->db->insert( $embeddings[ $index ] );
			}
		}

		wp_send_json_success( [
			'done'     => $done,
			'total'    => $counts->publish,
			'has_next' => ( $chunk < $chunks ? true : false ),
		] );

	}

	/**
	 * Get post type count
	 * 
	 * @param  string $type [description]
	 * @return [type]       [description]
	 */
	public function count_posts( $type = 'post' ) {
		
		global $wpdb;

		if ( ! post_type_exists( $type ) ) {
			return new stdClass();
		}

		$cache_key = 'jet_ai_search_posts_count_' . $type;

		$counts = wp_cache_get( $cache_key, 'counts' );

		if ( false !== $counts ) {
			return $counts;
		}

		$query = "SELECT post_status, COUNT( * ) AS num_posts FROM {$wpdb->posts} WHERE post_type = %s AND post_password = '' GROUP BY post_status";

		$results = (array) $wpdb->get_results( $wpdb->prepare( $query, $type ), ARRAY_A );
		$counts  = array_fill_keys( get_post_stati(), 0 );

		foreach ( $results as $row ) {
			$counts[ $row['post_status'] ] = $row['num_posts'];
		}

		$counts = (object) $counts;
		wp_cache_set( $cache_key, $counts, 'counts' );

		return $counts;

	}

	public function get_fetchable_post_types() {
		
		$post_types = get_post_types( [ 'publicly_queryable' => true ], 'objects' );
		$result = [];

		$exclude = [
			'attachment',
			'revision',
			'nav_menu_item',
			'elementor_library',
			'jet-engine',
			'jet-theme-core',
		];

		foreach ( $post_types as $slug => $cpt ) {
			if ( ! in_array( $slug, $exclude ) ) {
				$result[] = [
					'value' => $slug,
					'label' => $cpt->label
				];
			}
		}

		return $result;

	}

}