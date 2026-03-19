<?php
defined( 'ABSPATH' ) || exit;

register_activation_hook( defined( 'WOOCO_LITE' ) ? WOOCO_LITE : WOOCO_FILE, 'wooco_activate' );
register_deactivation_hook( defined( 'WOOCO_LITE' ) ? WOOCO_LITE : WOOCO_FILE, 'wooco_deactivate' );
add_action( 'admin_init', 'wooco_check_version' );

function wooco_check_version() {
	if ( ! empty( get_option( 'wooco_version' ) ) && ( get_option( 'wooco_version' ) < WOOCO_VERSION ) ) {
		wpc_log( 'wooco', 'upgraded' );
		update_option( 'wooco_version', WOOCO_VERSION, false );
	}
}

function wooco_activate() {
	wpc_log( 'wooco', 'installed' );
	update_option( 'wooco_version', WOOCO_VERSION, false );
}

function wooco_deactivate() {
	wpc_log( 'wooco', 'deactivated' );
}

if ( ! function_exists( 'wpc_log' ) ) {
	function wpc_log( $prefix, $action ) {
		$logs = get_option( 'wpc_logs', [] );
		$user = wp_get_current_user();

		if ( ! isset( $logs[ $prefix ] ) ) {
			$logs[ $prefix ] = [];
		}

		$logs[ $prefix ][] = [
			'time'   => current_time( 'mysql' ),
			'user'   => $user->display_name . ' (ID: ' . $user->ID . ')',
			'action' => $action
		];

		update_option( 'wpc_logs', $logs, false );
	}
}