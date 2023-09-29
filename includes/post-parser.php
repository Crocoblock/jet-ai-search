<?php
namespace JET_AI_Search;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Parse post to fragments
 */
class Post_Parser {

	private $strip_tags = false;
	private $result = [];
	private $defaults = [];
	private $source = '';

	public function set_stack_defaults( $defaults = [] ) {
		$this->defaults = array_merge( [ 'source' => $this->source ], $defaults );
	}

	public function stack_result( $item = [] ) {
		if ( ! empty( $item['fragment'] ) && $this->is_valid_fragment( $item['fragment'] ) ) {
			$this->result[] = array_merge( $this->defaults, $item );
			//var_dump( $item );
		}
	}

	/**
	 * Parse gieven piece of content
	 * 
	 * @param  [type] $ID      ID of given content
	 * @param  [type] $title   title of given content
	 * @param  [type] $link    link to given content
	 * @param  [type] $content content itself
	 * @param  [type] $type    post_type or different content type identefication
	 * @return [type]          framents of content
	 */
	public function get_post_fragments( $ID, $title, $link, $content, $type ) {

		$this->result = [];

		$this->set_stack_defaults( [
			'post_id'    => $ID,
			'post_url'   => $title,
			'post_title' => $link,
			'source'     => $type,
		] );

		preg_match_all( '/<h[1-4].*>.*<\/h[1-4]>/i', $content, $headings );

		if ( ! empty( $headings ) && ! empty( $headings[0] ) ) {

			$heading_to_stack = '';

			foreach( $headings[0] as $i => $heading ) {

				$pos = strpos( $content, $heading );

				if ( false !== $pos ) {
					$part = substr( $content, 0, $pos );
				}

				$prev_heading = ( 0 !== $i ) ? rtrim( trim( wp_strip_all_tags( $headings[0][ $i-1 ] ) ), '.:' ) : $title;

				$prev_heading = $this->prepare_heading( $prev_heading );
				$fragment     = $this->prepare_fragment( $part );

				if ( 0 === $i && false === strpos( $prev_heading, $title ) ) {
					$prev_heading = $title . '. ' . $prev_heading;
				}

				if ( ! $fragment ) {
					$heading_to_stack .= $prev_heading;
					continue;
				}

				if ( $heading_to_stack ) {
					$prev_heading = $heading_to_stack . $prev_heading;
					$heading_to_stack = '';
				}

				$this->stack_result( [
					'fragment' => $prev_heading . $fragment
				] );

				$content = str_replace( $part . $heading, '', $content );

			}

			if ( $content ) {

				$prev_heading = end( $headings[0] );
				$prev_heading = $this->prepare_heading( $prev_heading );
				$fragment     = $this->prepare_fragment( $content );

				$this->stack_result( [
					'fragment' => $prev_heading . $fragment
				] );

			}
		} else {

			$title = $this->prepare_heading( $title );
			$prepared_content = explode( "\n", $this->prepare_fragment( $content ) );
			$stacked_fragment = '';

			foreach( $prepared_content as $raw_fragment ) {

				if ( 1000 <= strlen( $raw_fragment ) ) {
					$fragments = str_split( $raw_fragment, 500 );
				} else {
					$fragments = [ $raw_fragment ];
				}

				foreach ( $fragments as $fragment ) {
					$new_fragment = $title . $fragment;
					$title = '';

					if ( $stacked_fragment ) {
						$new_fragment = $stacked_fragment . ' ' . $new_fragment;
					}

					if ( ! $this->is_valid_fragment( $new_fragment ) ) {
						$stacked_fragment = $new_fragment;
						continue;
					}

					$stacked_fragment = '';

					$this->stack_result( [
						'fragment' => $new_fragment
					] );

				}

			}

		}

		$this->set_stack_defaults();

		return $this->result;

	}

	public function is_valid_fragment( $fragment ) {
		return 110 <= strlen( $fragment );
	}

	public function prepare_heading( $input_heading ) {
		
		$result = rtrim( trim( wp_strip_all_tags( $input_heading ) ), '.' );

		if ( $result ) {
			$result .= '. ';
		}

		return $result;

	}

	public function prepare_fragment( $input_part ) {

		$fragment    = $input_part;
		$pattern     = '/(?<=\>)([^<>]+)(?=\<\/[^>]+\>)/';
		$replacement = '${0} ';
		$fragment    = preg_replace( $pattern, $replacement, $fragment );

		$fragment = str_replace( '  ', ' ', $fragment );
		$fragment = wp_strip_all_tags( $fragment );

		$pattern     = "/\n{2,}/";
		$replacement = "\n";
		$fragment    = preg_replace( $pattern, $replacement, $fragment );

		return trim( $fragment );

	}
}
