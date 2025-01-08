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
		'license_token' => '',
		'api_key'       => '',
		'mode'          => 'all',
		'strictness'    => 0.7,
		'limit'         => 10,
		'auto_fetch'    => [],
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

	public function dispatch_license_activation( $request, $dispatcher ) {

		if ( ! $dispatcher->verify_nonce( $request ) ) {
			wp_send_json_error( 'Link is expired. Reload page and try again' );
		}

		if ( ! current_user_can( 'manage_options' ) || empty( $request['settings'] ) ) {
			wp_send_json_error( 'Access denied' );
		}

		$license = ! empty( $request['settings']['license'] ) ? esc_attr( $request['settings']['license'] ) : false;

		if ( ! $license ) {
			wp_send_json_error( 'license not found in the request' );
		}

		$response = wp_remote_post( 'https://www.ai-search.omstore.in.ua/register', [
			'headers' => [
				'Content-Type' => 'application/json',
			],
			'body' => json_encode( [
				'license' => $license,
				'refer'   => home_url( '/' ),
			] )
		] );

		$body = json_decode( wp_remote_retrieve_body( $response ) );

		if ( ! $body && ! isset( $body->token ) ) {
			wp_send_json_success( 'Can`t activate license. Please contact oursupport' );
		} else {

			$this->update_settings( [
				'license_token' => $body->token,
			] );

			wp_send_json_success( $body );
		}
	}

	public function dispatch_license_deactivation( $request, $dispatcher ) {

		if ( ! $dispatcher->verify_nonce( $request ) ) {
			wp_send_json_error( 'Link is expired. Reload page and try again' );
		}

		if ( ! current_user_can( 'manage_options' ) || empty( $request['settings'] ) ) {
			wp_send_json_error( 'Access denied' );
		}

		$this->update_settings( [
			'license_token' => '',
		] );

		wp_send_json_success();
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
		$current  = get_option( Plugin::instance()->slug(), $this->defaults );

		foreach ( $this->defaults as $key => $default ) {
			$default = isset( $current[ $key ] ) ? $current[ $key ] : $default;
			$prepared[ $key ] = isset( $settings[ $key ] ) ? $settings[ $key ] : $default;
		}

		update_option( Plugin::instance()->slug(), $prepared, false );
	}

}
