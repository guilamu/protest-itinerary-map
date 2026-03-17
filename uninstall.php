<?php
/**
 * Uninstall handler for Protest Itinerary Map.
 *
 * Removes all plugin data: posts, post meta, options, and transients.
 * This file is called by WordPress when the plugin is deleted via the admin UI.
 *
 * @package Protest_Itinerary_Map
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Delete all protest_itinerary posts and their meta.
$posts = get_posts( array(
    'post_type'      => 'protest_itinerary',
    'post_status'    => 'any',
    'numberposts'    => -1,
    'fields'         => 'ids',
    'no_found_rows'  => true,
) );

foreach ( $posts as $post_id ) {
    wp_delete_post( $post_id, true );
}

// Drop the subscribers table.
require_once plugin_dir_path( __FILE__ ) . 'includes/class-subscriber.php';
PIM_Subscriber::drop_table();

// Clear cron jobs.
wp_clear_scheduled_hook( 'pim_purge_unconfirmed' );
wp_clear_scheduled_hook( 'pim_purge_post_event' );

// Delete plugin options.
$options = array(
    'pim_ors_api_key',
    'pim_default_lat',
    'pim_default_lng',
    'pim_default_zoom',
    'pim_embed_global',
);

foreach ( $options as $option ) {
    delete_option( $option );
}

// Delete transients.
delete_transient( 'pim_github_release' );
