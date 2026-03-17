<?php
/**
 * [protest_map] shortcode.
 *
 * @package Protest_Itinerary_Map
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PIM_Shortcode {

    /**
     * Hook into WordPress.
     */
    public static function init() {
        add_shortcode( 'protest_map', array( __CLASS__, 'render' ) );
    }

    /**
     * Render the shortcode.
     *
     * @param array $atts Shortcode attributes.
     * @return string HTML output.
     */
    public static function render( $atts ) {
        $atts = shortcode_atts( array(
            'id'      => 0,
            'sidebar' => 'true',
            'height'  => '500px',
        ), $atts, 'protest_map' );

        $post_id = absint( $atts['id'] );
        if ( ! $post_id ) {
            return '';
        }

        // Verify the post exists, is the right type, and is published.
        $post = get_post( $post_id );
        if ( ! $post || PIM_Post_Type::POST_TYPE !== $post->post_type || 'publish' !== $post->post_status ) {
            return '';
        }

        $waypoints     = get_post_meta( $post_id, '_pim_waypoints', true );
        $route_geojson = get_post_meta( $post_id, '_pim_route_geojson', true );
        $route_legs    = get_post_meta( $post_id, '_pim_route_legs', true );
        $route_summary = get_post_meta( $post_id, '_pim_route_summary', true );
        $event_name    = get_post_meta( $post_id, '_pim_event_name', true );

        $waypoints_arr = $waypoints ? json_decode( $waypoints, true ) : array();
        if ( empty( $waypoints_arr ) ) {
            return '';
        }

        // Enqueue assets in the footer.
        self::enqueue_assets();

        $show_sidebar = filter_var( $atts['sidebar'], FILTER_VALIDATE_BOOLEAN );
        $height       = sanitize_text_field( $atts['height'] );

        // Build a unique instance ID for multiple shortcodes on one page.
        static $instance = 0;
        $instance++;
        $container_id = 'pim-map-' . $instance;

        // Attendance data.
        $attendance_enabled     = (bool) get_post_meta( $post_id, '_pim_attendance_enabled', true );
        $attendance_count       = (int) get_post_meta( $post_id, '_pim_attendance_count', true );
        $union_label            = get_post_meta( $post_id, '_pim_union_optin_label', true );

        // Localize data for this instance.
        wp_localize_script( 'pim-public', 'pimPublic_' . $instance, array(
            'containerId'             => $container_id,
            'postId'                  => $post_id,
            'waypoints'               => $waypoints_arr,
            'routeGeoJSON'            => $route_geojson ? json_decode( $route_geojson, true ) : null,
            'routeLegs'               => $route_legs ? json_decode( $route_legs, true ) : array(),
            'routeSummary'            => $route_summary ? json_decode( $route_summary, true ) : null,
            'eventName'               => $event_name,
            'showSidebar'             => $show_sidebar,
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
                'showMetro'     => __( 'Show metro lines', 'protest-itinerary-map' ),
                'hideMetro'     => __( 'Hide metro lines', 'protest-itinerary-map' ),
            ),
        ) );

        ob_start();
        include PIM_PLUGIN_DIR . 'public/public-map.php';
        return ob_get_clean();
    }

    /**
     * Enqueue frontend Leaflet and plugin assets.
     */
    private static function enqueue_assets() {
        if ( wp_script_is( 'pim-public', 'enqueued' ) ) {
            return;
        }

        wp_enqueue_style(
            'leaflet',
            'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css',
            array(),
            '1.9.4'
        );

        wp_enqueue_script(
            'leaflet',
            'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js',
            array(),
            '1.9.4',
            true
        );

        wp_enqueue_style(
            'pim-public',
            PIM_PLUGIN_URL . 'public/public.css',
            array( 'leaflet' ),
            PIM_VERSION
        );

        wp_enqueue_script(
            'pim-public',
            PIM_PLUGIN_URL . 'public/public.js',
            array( 'leaflet' ),
            PIM_VERSION,
            true
        );
    }
}
