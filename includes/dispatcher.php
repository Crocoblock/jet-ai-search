<?php
namespace JET_AI_Search;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Run plugin actions
 */
class Dispatcher {

	public $result = '';
	public $nonce_action = 'jet-ai-search';

	public function __construct() {
		
		if ( ! empty( $_GET['dispatch'] ) ) {
			$this->dispatch_action();
		}

		add_action( 'wp_ajax_jet_ai_search_dispatch', [ $this, 'dispatch_action' ] );

	}

	public function create_nonce() {
		return wp_create_nonce( $this->nonce_action );
	}

	public function verify_nonce() {
		$nonce = ! empty( $_GET['nonce'] ) ? $_GET['nonce'] : false;
		return ( $nonce && wp_verify_nonce( $nonce, $this->nonce_action ) ) ? true : false;
	}

	public function dispatch_action( $action = '' ) {

		if ( ! $action ) {
			$action = ! empty( $_REQUEST['dispatch'] ) ? $_REQUEST['dispatch'] : false;
		}

		$request = $_REQUEST;

		if ( ! $action ) {
			$request = json_decode( file_get_contents( 'php://input' ), true );
			$action  = ( $request && ! empty( $request['dispatch'] ) ) ? $request['dispatch'] : false;
		}

		if ( ! $action ) {
			return;
		}

		$action_data = explode( '.', $action );

		if ( 2 !== count( $action_data ) ) {
			return;
		}

		$object = $action_data[0];
		$method = $action_data[1];

		if ( false === strpos( $method, 'dispatch_' ) ) {
			return;
		}

		if ( ! isset( Plugin::instance()->$object ) || ! is_callable( [ Plugin::instance()->$object, $method ] ) ) {
			return;
		}

		call_user_func( [ Plugin::instance()->$object, $method ], $request, $this );

	}

	public function validate() {

		$open_ai = new Open_AI();
		$result = $open_ai->request( 'fine-tunes', [], [], 'GET' );

		die( 'Done!' );
	}

	public function fetch_embeddings() {

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Error!' );
		}

		$embeddings_manager = new Embeddings();

		$url = ! empty( $_GET['url'] ) ? $_GET['url'] : false;

		if ( $url ) {
			$embeddings_manager->fetch( [
				'remote' => [ $url ],
			] );
		}

		wp_send_json_success( [ 'done' => $embeddings_manager->inserted_fragments ] );

	}

	public function search_by_embeddings( $search_query = '', $limit = 10 ) {

		$search_results = [];

		if ( empty( $search_query ) ) {
			return $search_results;
		}

		$embeddings_manager = new Embeddings();
		$embeddings         = $embeddings_manager->get();

		$open_ai = new Open_AI();

		$query_embedding = [];

		$query_result = $open_ai->request( 'embeddings', json_encode( [
			'model' => 'text-embedding-ada-002',
			'input' => $search_query,
		] ) );

		if ( ! empty( $query_result['data'] ) ) {
			$query_embedding = $query_result['data'][0]['embedding'];
		}
		
		for ( $i = 0; $i < count( $embeddings ); $i++ ) {

			$similarity = $this->similarity( json_decode( $embeddings[ $i ]->embedding ), $query_embedding );

			if ( 0.6 > $similarity ) {
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

		$result = [];
		$result_ids = [];

		for ( $i = 0; $i < count( $search_results ); $i++ ) {
		//for ( $i = count( $search_results ) - 1; $i >= 0; $i-- ) {

			$item = $embeddings[ $search_results[ $i ]['index'] ];

			if ( ! in_array( $item->post_id, $result_ids ) ) {
				$result_ids[] = $item->post_id;
				$result[]     = $item->ID;
			}

			if ( $limit === count( $result_ids ) ) {
				break;
			}
		}

		return $embeddings_manager->get_public_data( $result );

	}

	public function embeddings() {

		$search_query = ! empty( $_GET['search_query'] ) ? $_GET['search_query'] : '';
		$search_results = $this->search_by_embeddings( $search_query );

		foreach( $search_results as $item ) {
			$this->result .= '<b>Fragment:</b> ' . wp_trim_words( $item['fragment'], 20, '...' ) . '<br/>';
			$this->result .= '<b>Link to post:</b> <a href="' . $item['post_url'] . '" target="_blank">' . $item['post_title'] . '</a><br/><br/>';
		}

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

	public function dotp( $arr1, $arr2 ) {
		return array_sum(array_map( function( $a, $b ) { return $a * $b; }, $arr1, $arr2));
	}

	public function dispatch() {

		$file_content = [];

		foreach ( [ 'https://jetformbuilder.com/wp-json/wp/v2/posts/' ] as $remote_url ) {
			$data = new Remote_Data( $remote_url );
			$file_content = array_merge( $file_content, $data->fetch() );
		}

		add_filter( 'upload_mimes', function( $mimes ) {
			$mimes['jsonl'] = 'application/jsonl';
			return $mimes;
		} );
		
		$file = wp_upload_bits( 
			'fine-tuning-snapshot.jsonl', 
			null, 
			implode( "\n", $file_content )
		);

		$cf = new \CurlFile( $file['file'], 'application/json', "file.jsonl" );

		$open_ai = new Open_AI();

		$result = $open_ai->request( 'files', [
			'file'    => $cf,
			'purpose' => 'fine-tune',
		], [ 'Content-Type' => 'multipart/form-data' ] );

		$file_id = $result['id'];
		
		var_dump( $result );
		var_dump( $file_id );

		$result = $open_ai->request( 'fine-tunes', json_encode( [
			'training_file' => $file_id,
			'model' => 'davinci',
			'suffix' => 'my-model',
		] ), [ 'Content-Type'  => 'application/json' ] );

		var_dump( $result );

		die( 'Done!' );

	}
}
