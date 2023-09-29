<?php
namespace JET_AI_Search;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Admin page manager
 */
class Admin_Page {
	
	/**
	 * Register admin page
	 * 
	 * @return [type] [description]
	 */
	public function register() {
		add_action( 'admin_menu', [ $this, 'add_menu_page' ] );
	}

	/**
	 * Page & menu title
	 * 
	 * @return [type] [description]
	 */
	public function page_title() {
		return __( 'AI Search', 'jet-ai-search' );
	}

	/**
	 * Returns plugin slug
	 * 
	 * @return [type] [description]
	 */
	public function slug() {
		return Plugin::instance()->slug();
	}

	/**
	 * Menu page
	 */
	public function add_menu_page() {

		add_options_page( 
			$this->page_title(),
			$this->page_title(),
			'manage_options',
			$this->slug(),
			[ $this, 'render_page' ]
		);

	}

	/**
	 * Assets + JS data.
	 * 
	 * @return [type] [description]
	 */
	public function assets() {

		wp_enqueue_style( 'wp-components' );
		
		wp_enqueue_script(
			'jet-ai-search',
			JET_AI_SEARCH_URL . 'assets/js/admin.js',
			[ 'wp-api-fetch', 'wp-components', 'wp-element', 'lodash' ],
			JET_AI_SEARCH_VERSION . time(),
			true
		);

		wp_localize_script( 'jet-ai-search', 'JetAISearchData', [
			'settings'   => Plugin::instance()->settings->get(),
			'nonce'      => Plugin::instance()->dispatcher->create_nonce(),
			'stats'      => Plugin::instance()->data->get_stats(),
			'post_types' => Plugin::instance()->data->get_fetchable_post_types(),
		] );

	}

	/**
	 * Render page
	 * 
	 * @return [type] [description]
	 */
	public function render_page() {
		$this->assets();
		echo '<div id="jet_ai_search_app"></div>';
	}

}