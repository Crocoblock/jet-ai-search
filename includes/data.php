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

	public function fetch_post( $post ) {

		$post_parser = new Post_Parser();

		$GLOBALS['post'] = $post;
		setup_postdata( $post );
		$post_content = apply_filters( 'the_content', $post->post_content );
		Plugin::instance()->db->delete( [ 'post_id' => $post->ID ] );

		$all_fragmets = array_merge( $post_parser->get_post_fragments( 
			$post->ID, 
			$post->post_title, 
			$post->guid,
			$post_content,
			$post->post_type,
		), $post_parser->get_post_fragments( 
			$post->ID, 
			$post->post_title, 
			$post->guid,
			$post->post_excerpt,
			$post->post_type,
		) );

		// Last attempt to get something
		if ( empty( $all_fragmets ) ) {
			
			$post_parser->set_stack_defaults( [
				'post_id'    => $post->ID,
				'post_url'   => $post->guid,
				'post_title' => $post->post_title,
				'source'     => $post->post_type,
			] );
			
			$title    = $post_parser->prepare_heading( $post->post_title );
			$fragment = $post_parser->prepare_fragment( $post_content );

			$post_parser->stack_result( [
				'fragment' => $title . $fragment
			], true );

			$all_fragmets = $post_parser->get_result();
		}

		/**
		 * Filter 'jet-ai-search/post-fragments' allows to add custom fragments related to the given post.
		 */
		$all_fragmets = apply_filters( 'jet-ai-search/post-fragments', $all_fragmets, $post, $post_parser );

		wp_reset_postdata();

		return $all_fragmets;

	}

	public function write_embeddings( $embeddings = [] ) {
		
		Plugin::instance()->db->create_table();

		$open_ai = new Open_AI( Plugin::instance()->settings->get( 'api_key' ) );

		$result = $open_ai->request( 'embeddings', json_encode( [
			'model' => 'text-embedding-ada-002',
			'input' => array_map( function( $item ) {
				return $item['fragment'];
			}, $embeddings ),
		] ) );

		$error = $open_ai->get_error();

		if ( $error ) {
			throw new \Exception( 'Open AI API Error: ' . $error );
		}

		if ( ! empty( $result['data'] ) ) {
			foreach ( $result['data'] as $index => $item ) {
				$result_embeddings[] = $item['embedding'];
				$embeddings[ $index ]['embedding'] = json_encode( $item['embedding'] );
				Plugin::instance()->db->insert( $embeddings[ $index ] );
			}
		}

	}

	public function dispatch_fetch( $request, $dispatcher ) {

		if ( ! $dispatcher->verify_nonce( $request ) ) {
			wp_send_json_error( 'Link is expired. Reload page and try again' );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Access denied' );
		}

		try {
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

			$embeddings = [];
			$done = $per_chunk * ( $chunk - 1 );

			foreach ( $posts as $post ) {
				
				$post_fragments = $this->fetch_post( $post );
				$embeddings = array_merge( $embeddings, $post_fragments );
				$done++;
			}

			$this->write_embeddings( $embeddings );

			wp_send_json_success( [
				'done'     => $done,
				'total'    => $counts->publish,
				'has_next' => ( $chunk < $chunks ? true : false ),
			] );
		} catch ( \Exception $e ) {
			wp_send_json_error( $e->getMessage() );
		}

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