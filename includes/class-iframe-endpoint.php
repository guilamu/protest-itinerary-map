<?php
/**
 * Iframe embed endpoint for protest itineraries.
 *
 * Registers a rewrite rule: /protest-map/embed/{post_id}/
 * Renders a standalone page without the site theme.
 *
 * @package Protest_Itinerary_Map
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PIM_Iframe_Endpoint {

    /**
     * Hook into WordPress.
     */
    public static function init() {
        add_action( 'init', array( __CLASS__, 'register_rewrite_rules' ) );
        add_filter( 'query_vars', array( __CLASS__, 'add_query_vars' ) );
        add_action( 'template_redirect', array( __CLASS__, 'handle_request' ) );
    }

    /**
     * Register the rewrite rule for the embed endpoint.
     */
    public static function register_rewrite_rules() {
        add_rewrite_rule(
            '^protest-map/embed/([0-9]+)/?$',
            'index.php?pim_embed_id=$matches[1]',
            'top'
        );
    }

    /**
     * Register query vars.
     *
     * @param array $vars Existing query vars.
     * @return array Modified query vars.
     */
    public static function add_query_vars( $vars ) {
        $vars[] = 'pim_embed_id';
        return $vars;
    }

    /**
     * Handle embed requests.
     */
    public static function handle_request() {
        $post_id = absint( get_query_var( 'pim_embed_id', 0 ) );
        if ( ! $post_id ) {
            return;
        }

        // Check global embed toggle.
        if ( ! get_option( 'pim_embed_global', false ) ) {
            status_header( 403 );
            wp_die(
                esc_html__( 'Embedding is disabled.', 'protest-itinerary-map' ),
                esc_html__( 'Forbidden', 'protest-itinerary-map' ),
                array( 'response' => 403 )
            );
        }

        // Validate post.
        $post = get_post( $post_id );
        if ( ! $post || PIM_Post_Type::POST_TYPE !== $post->post_type || 'publish' !== $post->post_status ) {
            status_header( 404 );
            wp_die(
                esc_html__( 'Itinerary not found.', 'protest-itinerary-map' ),
                esc_html__( 'Not Found', 'protest-itinerary-map' ),
                array( 'response' => 404 )
            );
        }

        // Check per-itinerary embed flag.
        if ( ! get_post_meta( $post_id, '_pim_embed_enabled', true ) ) {
            status_header( 403 );
            wp_die(
                esc_html__( 'Embedding is not enabled for this itinerary.', 'protest-itinerary-map' ),
                esc_html__( 'Forbidden', 'protest-itinerary-map' ),
                array( 'response' => 403 )
            );
        }

        // Load data.
        $waypoints     = get_post_meta( $post_id, '_pim_waypoints', true );
        $route_geojson = get_post_meta( $post_id, '_pim_route_geojson', true );
        $route_legs    = get_post_meta( $post_id, '_pim_route_legs', true );
        $route_summary = get_post_meta( $post_id, '_pim_route_summary', true );
        $event_name    = get_post_meta( $post_id, '_pim_event_name', true );

        $waypoints_arr = $waypoints ? json_decode( $waypoints, true ) : array();
        if ( empty( $waypoints_arr ) ) {
            status_header( 404 );
            wp_die(
                esc_html__( 'No waypoints found for this itinerary.', 'protest-itinerary-map' ),
                esc_html__( 'Not Found', 'protest-itinerary-map' ),
                array( 'response' => 404 )
            );
        }

        // Prepare JS data.
        $attendance_enabled     = (bool) get_post_meta( $post_id, '_pim_attendance_enabled', true );
        $attendance_count       = (int) get_post_meta( $post_id, '_pim_attendance_count', true );
        $union_label            = get_post_meta( $post_id, '_pim_union_optin_label', true );

        $js_data = array(
            'containerId'             => 'pim-embed-map',
            'postId'                  => $post_id,
            'waypoints'               => $waypoints_arr,
            'routeGeoJSON'            => $route_geojson ? json_decode( $route_geojson, true ) : null,
            'routeLegs'               => $route_legs ? json_decode( $route_legs, true ) : array(),
            'routeSummary'            => $route_summary ? json_decode( $route_summary, true ) : null,
            'eventName'               => $event_name,
            'showSidebar'             => true,
            'iconsUrl'                => PIM_PLUGIN_URL . 'assets/icons/',
            'attendanceEnabled'       => $attendance_enabled,
            'attendanceCount'         => $attendance_count,
            'unionLabel'              => $union_label ? $union_label : '',
            'ajaxUrl'                 => admin_url( 'admin-ajax.php' ),
            'nonce'                   => wp_create_nonce( 'pim_public_nonce' ),
            'i18n'                    => array(
                'personAttend'  => __( 'person plans to attend', 'protest-itinerary-map' ),
                'peopleAttend'  => __( 'people plan to attend', 'protest-itinerary-map' ),
                'youComing'     => __( "You're coming!", 'protest-itinerary-map' ),
                'illBeThere'    => __( "I'll be there!", 'protest-itinerary-map' ),
                'stayInformed'  => __( 'Want to stay informed?', 'protest-itinerary-map' ),
                'notifyMe'      => __( 'Notify me of updates about this protest', 'protest-itinerary-map' ),
                'noThanks'      => __( 'No thanks', 'protest-itinerary-map' ),
                'confirm'       => __( 'Confirm', 'protest-itinerary-map' ),
                'notified'      => __( 'You will be notified of any updates.', 'protest-itinerary-map' ),
                'alsoSubscribe' => __( 'Also subscribe to %s\'s newsletter.', 'protest-itinerary-map' ),
                'yesPlease'     => __( 'Yes, sign me up', 'protest-itinerary-map' ),
                'emailDeleted'  => __( 'Your email will be deleted the day after the protest date.', 'protest-itinerary-map' ),
                'walk'          => __( 'walk', 'protest-itinerary-map' ),
                'typeStart'     => __( 'Start', 'protest-itinerary-map' ),
                'typeEnd'       => __( 'End', 'protest-itinerary-map' ),
                'typeCheckpoint' => __( 'Checkpoint', 'protest-itinerary-map' ),
                'typeMeetingPoint' => __( 'Meeting Point', 'protest-itinerary-map' ),
                'typeRestStop'  => __( 'Rest Stop', 'protest-itinerary-map' ),
            ),
        );

        include PIM_PLUGIN_DIR . 'iframe/iframe-map.php';
        exit;
    }
}
