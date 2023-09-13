<?php
namespace JET_AI_Search;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Handle search
 */
class Handle_Search {

	public function __construct() {
		add_filter( 'jet-search/ajax-search/query-args', [ $this, 'add_ai_trigger' ] );
		add_action( 'pre_get_posts', [ $this, 'handle_search' ] );
	}

	public function add_ai_trigger( $args ) {
		$args['is_ai'] = true;
		return $args;
	}

	public function handle_search( $query ) {
		
		if ( ! $query->is_search() ) {
			return;
		}

		// Allowed only for JetSearch and is not JetSearch
		if ( 'none' === Plugin::instance()->settings->get( 'mode' ) && ! $query->get( 'is_ai' ) ) {
			return;
		}
		
		// Allowed only by request and there is no `is_ai` paramter in the request
		if ( 'by_request' === Plugin::instance()->settings->get( 'mode' ) && ! isset( $_REQUEST['is_ai'] ) ) {
			return;
		}

		$search            = $query->get( 's' );
		$post_type         = false;
		$queried_post_type = $query->get( 'post_type' );

		if ( ! empty( $queried_post_type ) && 'any' !== $queried_post_type ) {
			$post_type = is_array( $queried_post_type ) ? $queried_post_type : [ $queried_post_type ];
		}

		$result_ids = $this->get_results( $search, $post_type );

		if ( ! empty( $result_ids ) ) {
			$query->set( 'post__in', $result_ids );
			$query->set( 'orderby', 'post__in' );
			add_filter( 'posts_search', [ $this, 'clear_search_sql' ] );
		}

	}

	public function get_results( $search = '', $sources = false ) {

		if ( ! Plugin::instance()->settings->get( 'api_key' ) ) {
			return false;
		}

		if ( ! $search ) {
			return false;
		}
		
		$table = Plugin::instance()->db->table();
		$where = '';

		if ( ! empty( $sources ) ) {
			$sources_str = [];
			foreach ( $sources as $source ) {
				$sources_str[] = "'$source'";
			}

			$sources_str = implode( ', ', $sources_str );

			$where .= "WHERE `source` IN ($sources_str)";

		}

		$embeddings = Plugin::instance()->db->wpdb()->get_results( 
			"SELECT ID, post_id, embedding FROM $table $where"
		);

		$open_ai = new Open_AI( Plugin::instance()->settings->get( 'api_key' ) );

		$query_result = $open_ai->request( 'embeddings', json_encode( [
			'model' => 'text-embedding-ada-002',
			'input' => $search,
		] ) );

		if ( empty( $query_result['data'] ) ) {
			return false;
		}

		$query_embedding = $query_result['data'][0]['embedding'];
		$search_results  = [];
		$strictness      = floatval( Plugin::instance()->settings->get( 'strictness' ) );

		for ( $i = 0; $i < count( $embeddings ); $i++ ) {

			$similarity = $this->similarity( json_decode( $embeddings[ $i ]->embedding ), $query_embedding );

			if ( $strictness > $similarity ) {
				// store the simliarty and index in an array and sort by the similarity
				$search_results[] = [
					'similarity' => $similarity,
					'index' => $i,
				];
			}

		}

		usort( $search_results, function ( $a, $b ) {
			return $a['similarity'] <=> $b['similarity'];
		} );

		$result_ids = [];
		$limit      = Plugin::instance()->settings->get( 'limit' );

		for ( $i = 0; $i < count( $search_results ); $i++ ) {

			$item = $embeddings[ $search_results[ $i ]['index'] ];

			if ( ! in_array( $item->post_id, $result_ids ) ) {
				$result_ids[] = $item->post_id;
			}

			if ( $limit === count( $result_ids ) ) {
				break;
			}
		}

		return $result_ids;

	}

	public function clear_search_sql( $sql ) {
		remove_filter( 'posts_search', [ $this, 'clear_search_sql' ] );
		return '';
	}

	public function similarity( $u, $v ) {
		
		/*$dotProduct = 0;
		$uLength = 0;
		$vLength = 0;

		for ( $i = 0; $i < count($u); $i++ ) {
			$dotProduct += $u[$i] * $v[$i];
			$uLength += $u[$i] * $u[$i];
			$vLength += $v[$i] * $v[$i];
		}
		
		$uLength = sqrt($uLength);
		$vLength = sqrt($vLength);

		return $dotProduct / ($uLength * $vLength);*/
		
		return array_sum(
			array_map(
				function($x, $y) {
					return abs($x - $y) ** 2;
				}, $u, $v
			)
		) ** ( 1/2 );

	}

}
