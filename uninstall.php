<?php
/**
 * Uninstall
 *
 * Removes plugin data from the database if the user opted in.
 *
 * @package BloatBuster
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Delete plugin data for the current site if the user opted in.
 *
 * @return void
 *
 * @since 1.0.0
 */
function bloatbuster_uninstall_data() {
	$options = \get_option( 'bloatbuster_performance_asset_cleaner_options' );

	if ( empty( $options['delete_data_on_uninstall'] ) ) {
		return;
	}

	\delete_option( 'bloatbuster_performance_asset_cleaner_options' );
}

if ( \is_multisite() ) {
	foreach ( \get_sites( array( 'fields' => 'ids' ) ) as $bloatbuster_site_id ) {
		\switch_to_blog( $bloatbuster_site_id );
		bloatbuster_uninstall_data();
		\restore_current_blog();
	}
} else {
	bloatbuster_uninstall_data();
}
