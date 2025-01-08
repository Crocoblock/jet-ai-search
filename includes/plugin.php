<?php
namespace JET_AI_Search;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * @property $settings JET_AI_Search\Settings
 * @property $admin_page JET_AI_Search\Admin_Page
 *
 * Main file
 */
class Plugin {

	/**
	 * Instance.
	 *
	 * Holds the plugin instance.
	 *
	 * @since 1.0.0
	 * @access public
	 * @static
	 *
	 * @var Plugin
	 */
	public static $instance = null;

	public $settings;
	public $admin_page;
	public $data;
	public $db;
	public $dispatcher;
	public $search_handler;
	public $storage;

	/**
	 * Plugin constructor.
	 */
	private function __construct() {

		$this->register_autoloader();
		
		$this->db             = new DB();
		$this->admin_page     = new Admin_Page();
		$this->settings       = new Settings();
		$this->data           = new Data();
		$this->dispatcher     = new Dispatcher();
		$this->search_handler = new Handle_Search();
		$this->storage        = new Proxy_Storage();

		$this->storage->set_storage( new Remote_Storage() );

		$this->admin_page->register();

		add_action( 'init', function() {
			new Auto_Fetch( $this->settings->get( 'auto_fetch' ) );
		} );

		add_action( 'init', function() {

			if ( ! empty( $_GET['test_fetch_post'] ) ) {
				$post_id        = absint( $_GET['test_fetch_post'] );
				$post_fragments = $this->data->fetch_post( get_post( $post_id ) );
				$this->data->write_embeddings( $post_fragments );
				var_dump( $this->storage->get_last_response() );
				die();
			}

			if ( ! empty( $_GET['test_ai_search'] ) ) {
				timer_start();
				var_dump( $this->dispatcher->search_by_embeddings( $_GET['test_ai_search'] ) );
				var_dump( timer_stop(0, 10) );
				die();
			}

		}, 99 );

	}

	/**
	 * Plugin slug
	 * 
	 * @return [type] [description]
	 */
	public function slug() {
		return 'jet-ai-search';
	}

	/**
	 * Register autoloader.
	 */
	private function register_autoloader() {
		require JET_AI_SEARCH_PATH . 'includes/autoloader.php';
		Autoloader::run();
	}

	/**
	 * Instance.
	 *
	 * Ensures only one instance of the plugin class is loaded or can be loaded.
	 *
	 * @since 1.0.0
	 * @access public
	 * @static
	 *
	 * @return Plugin An instance of the class.
	 */
	public static function instance() {

		if ( is_null( self::$instance ) ) {

			self::$instance = new self();

		}

		return self::$instance;
	}
}

Plugin::instance();
