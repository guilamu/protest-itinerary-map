<?php
/**
 * Custom Post Type registration for protest itineraries.
 *
 * @package Protest_Itinerary_Map
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PIM_Post_Type {

    /**
     * Post type slug.
     */
    const POST_TYPE = 'protest_itinerary';

    /**
     * Allowed waypoint types.
     */
    const WAYPOINT_TYPES = array( 'start', 'end', 'checkpoint', 'meeting-point', 'rest-stop' );

    /**
     * Hook into WordPress.
     */
    public static function init() {
        add_action( 'init', array( __CLASS__, 'register_post_type' ) );
        add_action( 'init', array( __CLASS__, 'register_meta' ) );
        add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( __CLASS__, 'add_columns' ) );
        add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( __CLASS__, 'render_column' ), 10, 2 );
    }

    /**
     * Add custom columns to the post listing.
     *
     * @param array $columns Existing columns.
     * @return array Modified columns.
     */
    public static function add_columns( $columns ) {
        $new = array();
        foreach ( $columns as $key => $label ) {
            $new[ $key ] = $label;
            if ( 'title' === $key ) {
                $new['pim_shortcode'] = __( 'Shortcode', 'protest-itinerary-map' );
                $new['pim_event_date'] = __( 'Event Date', 'protest-itinerary-map' );
                $new['pim_attendance'] = __( 'Attendance', 'protest-itinerary-map' );
            }
        }
        return $new;
    }

    /**
     * Render custom column values.
     *
     * @param string $column  Column name.
     * @param int    $post_id Post ID.
     */
    public static function render_column( $column, $post_id ) {
        switch ( $column ) {
            case 'pim_shortcode':
                echo '<code>[protest_map id="' . esc_html( $post_id ) . '"]</code>';
                break;
            case 'pim_event_date':
                $date = get_post_meta( $post_id, '_pim_event_date', true );
                echo $date ? esc_html( $date ) : '—';
                break;
            case 'pim_attendance':
                $count = (int) get_post_meta( $post_id, '_pim_attendance_count', true );
                echo esc_html( $count );
                break;
        }
    }

    /**
     * Register the custom post type.
     */
    public static function register_post_type() {
        $labels = array(
            'name'               => __( 'Itineraries', 'protest-itinerary-map' ),
            'singular_name'      => __( 'Itinerary', 'protest-itinerary-map' ),
            'add_new'            => __( 'Add New', 'protest-itinerary-map' ),
            'add_new_item'       => __( 'Add New Itinerary', 'protest-itinerary-map' ),
            'edit_item'          => __( 'Edit Itinerary', 'protest-itinerary-map' ),
            'new_item'           => __( 'New Itinerary', 'protest-itinerary-map' ),
            'view_item'          => __( 'View Itinerary', 'protest-itinerary-map' ),
            'search_items'       => __( 'Search Itineraries', 'protest-itinerary-map' ),
            'not_found'          => __( 'No itineraries found.', 'protest-itinerary-map' ),
            'not_found_in_trash' => __( 'No itineraries found in Trash.', 'protest-itinerary-map' ),
            'all_items'          => __( 'All Itineraries', 'protest-itinerary-map' ),
            'menu_name'          => __( 'Protest Maps', 'protest-itinerary-map' ),
        );

        $args = array(
            'labels'              => $labels,
            'public'              => false,
            'publicly_queryable'  => false,
            'show_ui'             => true,
            'show_in_menu'        => true,
            'show_in_rest'        => false,
            'capability_type'     => 'post',
            'capabilities'        => array(
                'edit_post'          => 'manage_options',
                'read_post'          => 'manage_options',
                'delete_post'        => 'manage_options',
                'edit_posts'         => 'manage_options',
                'edit_others_posts'  => 'manage_options',
                'publish_posts'      => 'manage_options',
                'read_private_posts' => 'manage_options',
                'delete_posts'       => 'manage_options',
            ),
            'map_meta_cap'        => false,
            'supports'            => array( 'title' ),
            'has_archive'         => false,
            'rewrite'             => false,
            'menu_icon'           => 'dashicons-groups',
        );

        register_post_type( self::POST_TYPE, $args );
    }

    /**
     * Register post meta fields with sanitize callbacks.
     */
    public static function register_meta() {
        $text_fields = array(
            '_pim_event_name'        => 'sanitize_text_field',
            '_pim_event_date'        => array( __CLASS__, 'sanitize_date' ),
            '_pim_event_description' => 'sanitize_textarea_field',
            '_pim_organizer_contact' => 'sanitize_text_field',
        );

        foreach ( $text_fields as $key => $sanitize_cb ) {
            register_post_meta( self::POST_TYPE, $key, array(
                'type'              => 'string',
                'single'            => true,
                'sanitize_callback' => $sanitize_cb,
                'auth_callback'     => function () {
                    return current_user_can( 'manage_options' );
                },
                'show_in_rest'      => false,
            ) );
        }

        register_post_meta( self::POST_TYPE, '_pim_embed_enabled', array(
            'type'              => 'boolean',
            'single'            => true,
            'default'           => false,
            'sanitize_callback' => 'rest_sanitize_boolean',
            'auth_callback'     => function () {
                return current_user_can( 'manage_options' );
            },
            'show_in_rest'      => false,
        ) );

        $json_fields = array(
            '_pim_waypoints',
            '_pim_route_geojson',
            '_pim_route_legs',
            '_pim_route_summary',
        );

        foreach ( $json_fields as $key ) {
            register_post_meta( self::POST_TYPE, $key, array(
                'type'              => 'string',
                'single'            => true,
                'sanitize_callback' => 'sanitize_text_field',
                'auth_callback'     => function () {
                    return current_user_can( 'manage_options' );
                },
                'show_in_rest'      => false,
            ) );
        }

        register_post_meta( self::POST_TYPE, '_pim_route_cached_at', array(
            'type'              => 'string',
            'single'            => true,
            'sanitize_callback' => 'sanitize_text_field',
            'auth_callback'     => function () {
                return current_user_can( 'manage_options' );
            },
            'show_in_rest'      => false,
        ) );
    }

    /**
     * Sanitize a YYYY-MM-DD date string.
     *
     * @param string $value Raw input.
     * @return string Sanitized date or empty string.
     */
    public static function sanitize_date( $value ) {
        $value = sanitize_text_field( $value );
        if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) {
            $parts = explode( '-', $value );
            if ( checkdate( (int) $parts[1], (int) $parts[2], (int) $parts[0] ) ) {
                return $value;
            }
        }
        return '';
    }

    /**
     * Validate and sanitize waypoints JSON.
     *
     * @param string $json Raw JSON string.
     * @return array Sanitized waypoints array.
     */
    public static function sanitize_waypoints( $json ) {
        $waypoints = json_decode( $json, true );
        if ( ! is_array( $waypoints ) ) {
            return array();
        }

        $clean = array();
        $order = 0;

        foreach ( $waypoints as $wp_item ) {
            if ( ! is_array( $wp_item ) ) {
                continue;
            }

            $lat = isset( $wp_item['lat'] ) ? floatval( $wp_item['lat'] ) : null;
            $lng = isset( $wp_item['lng'] ) ? floatval( $wp_item['lng'] ) : null;

            if ( null === $lat || null === $lng || $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180 ) {
                continue;
            }

            $type = isset( $wp_item['type'] ) && in_array( $wp_item['type'], self::WAYPOINT_TYPES, true )
                ? $wp_item['type']
                : 'checkpoint';

            $clean[] = array(
                'id'    => isset( $wp_item['id'] ) ? sanitize_text_field( $wp_item['id'] ) : wp_generate_uuid4(),
                'lat'   => $lat,
                'lng'   => $lng,
                'label' => isset( $wp_item['label'] ) ? sanitize_text_field( $wp_item['label'] ) : '',
                'type'  => $type,
                'icon'  => $type, // Icon matches type.
                'info'  => isset( $wp_item['info'] ) ? wp_kses_post( $wp_item['info'] ) : '',
                'order' => $order,
            );
            $order++;
        }

        return $clean;
    }
}
