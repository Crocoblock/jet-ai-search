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

		if ( ! $search ) {
			return false;
		}

		$search_results = Plugin::instance()->dispatcher->search_by_embeddings( $search );
		$result_ids     = [];
		$limit          = Plugin::instance()->settings->get( 'limit' );

		for ( $i = 0; $i < count( $search_results ); $i++ ) {
			$result_ids[] = $search_results[ $i ]['post_id'];
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
