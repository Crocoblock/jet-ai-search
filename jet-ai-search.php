<?php
/**
 * Plugin Name: Jet AI Search
 * Plugin URI:  
 * Description: 
 * Version:     0.1.0
 * Author:      Crocoblock
 * Author URI:  https://crocoblock.com/
 * Text Domain: jet-ai-search
 * License:     GPL-3.0+
 * License URI: http://www.gnu.org/licenses/gpl-3.0.txt
 * Domain Path: /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die();
}

add_action( 'plugins_loaded', 'jet_ai_search' );

function jet_ai_search() {

	define( 'JET_AI_SEARCH_VERSION', '0.1.0' );
	define( 'JET_AI_SEARCH__FILE__', __FILE__ );
	define( 'JET_AI_SEARCH_PLUGIN_BASE', plugin_basename( JET_AI_SEARCH__FILE__ ) );
	define( 'JET_AI_SEARCH_PATH', plugin_dir_path( JET_AI_SEARCH__FILE__ ) );
	define( 'JET_AI_SEARCH_URL', plugins_url( '/', JET_AI_SEARCH__FILE__ ) );

	require JET_AI_SEARCH_PATH . 'includes/plugin.php';

}
