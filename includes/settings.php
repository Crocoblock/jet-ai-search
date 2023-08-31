<?php
namespace JET_AI_Search;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Settings manager
 */
class Settings {

	private $settings = [];

	private $defaults = [
		'api_key'    => '',
		'mode'       => 'all',
		'strictness' => 0.7,
		'limit'      => 10,
	];

	public function get( $setting = '' ) {

		if ( empty( $this->settings ) ) {
			$this->settings = get_option( Plugin::instance()->slug(), $this->defaults );
		}

		$all_settings = array_merge( $this->defaults, $this->settings );

		if ( $setting ) {
			return isset( $all_settings[ $setting ] ) ? $all_settings[ $setting ] : false;
		} else {
			return $all_settings;
		}

	}

	public function dispatch_update( $request, $dispatcher ) {
		
		if ( ! $dispatcher->verify_nonce( $request ) ) {
			wp_send_json_error( 'Link is expired. Reload page and try again' );
		}

		if ( ! current_user_can( 'manage_options' ) || empty( $request['settings'] ) ) {
			wp_send_json_error( 'Access denied' );
		}

		$this->update_settings( $request['settings'] );

		wp_send_json_success();

	}

	public function update_settings( $settings = [] ) {
		
		$prepared = [];

		foreach ( $this->defaults as $key => $default ) {
			$prepared[ $key ] = isset( $settings[ $key ] ) ? $settings[ $key ] : $default;
		}

		update_option( Plugin::instance()->slug(), $prepared, false );

	}

}
