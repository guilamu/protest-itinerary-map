<?php
/**
 * Plugin Name: Protest Itinerary Map
 * Plugin URI: https://github.com/guilamu/protest-itinerary-map
 * Description: Create and display protest itineraries on interactive OpenStreetMap-based maps with OpenRouteService routing.
 * Version: 1.0.0
 * Author: Guilamu
 * Author URI: https://github.com/guilamu
 * Text Domain: protest-itinerary-map
 * Domain Path: /languages
 * Update URI: https://github.com/guilamu/protest-itinerary-map/
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * License: GPL-2.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'PIM_VERSION', '1.0.0' );
define( 'PIM_PLUGIN_FILE', __FILE__ );
define( 'PIM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'PIM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'PIM_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// Include core classes.
require_once PIM_PLUGIN_DIR . 'includes/class-post-type.php';
require_once PIM_PLUGIN_DIR . 'includes/class-settings.php';
require_once PIM_PLUGIN_DIR . 'includes/class-meta-box.php';
require_once PIM_PLUGIN_DIR . 'includes/class-shortcode.php';
require_once PIM_PLUGIN_DIR . 'includes/class-iframe-endpoint.php';
require_once PIM_PLUGIN_DIR . 'includes/class-github-updater.php';
require_once PIM_PLUGIN_DIR . 'includes/class-subscriber.php';
require_once PIM_PLUGIN_DIR . 'includes/class-vote-handler.php';
require_once PIM_PLUGIN_DIR . 'includes/class-notification.php';
require_once PIM_PLUGIN_DIR . 'admin/admin-subscribers.php';

/**
 * Load plugin textdomain for i18n.
 */
add_action( 'init', function () {
    load_plugin_textdomain( 'protest-itinerary-map', false, dirname( PIM_PLUGIN_BASENAME ) . '/languages' );
} );

/**
 * Register the Gutenberg block.
 */
add_action( 'init', function () {
    register_block_type( PIM_PLUGIN_DIR . 'block/block.json' );
} );

/**
 * REST API endpoint for the Gutenberg block's itinerary selector.
 */
add_action( 'rest_api_init', function () {
    register_rest_route( 'pim/v1', '/itineraries', array(
        'methods'             => 'GET',
        'callback'            => function () {
            $posts = get_posts( array(
                'post_type'   => PIM_Post_Type::POST_TYPE,
                'post_status' => 'publish',
                'numberposts' => 200,
                'orderby'     => 'date',
                'order'       => 'DESC',
            ) );

            $data = array();
            foreach ( $posts as $p ) {
                $data[] = array(
                    'id'         => $p->ID,
                    'title'      => $p->post_title,
                    'event_name' => get_post_meta( $p->ID, '_pim_event_name', true ),
                );
            }
            return rest_ensure_response( $data );
        },
        'permission_callback' => function () {
            return current_user_can( 'edit_posts' );
        },
    ) );
} );

/**
 * Ensure database tables exist (handles manual file replacement without reactivation).
 */
add_action( 'plugins_loaded', function () {
    $db_version = get_option( 'pim_db_version', '0' );
    if ( version_compare( $db_version, PIM_VERSION, '<' ) ) {
        PIM_Subscriber::create_table();
        update_option( 'pim_db_version', PIM_VERSION );
    }
}, 5 );

/**
 * Initialize all plugin components.
 */
add_action( 'plugins_loaded', function () {
    PIM_Post_Type::init();
    PIM_Settings::init();
    PIM_Meta_Box::init();
    PIM_Shortcode::init();
    PIM_Iframe_Endpoint::init();
    PIM_Vote_Handler::init();
    PIM_Admin_Subscribers::init();
}, 10 );

/**
 * Activation: flush rewrite rules so the iframe endpoint works immediately.
 */
register_activation_hook( __FILE__, function () {
    PIM_Post_Type::register_post_type();
    PIM_Iframe_Endpoint::register_rewrite_rules();
    pim_register_attendance_rewrite_rules();
    PIM_Subscriber::create_table();
    flush_rewrite_rules();

    // Schedule cron jobs.
    if ( ! wp_next_scheduled( 'pim_purge_unconfirmed' ) ) {
        wp_schedule_event( time(), 'daily', 'pim_purge_unconfirmed' );
    }
    if ( ! wp_next_scheduled( 'pim_purge_post_event' ) ) {
        wp_schedule_event( time(), 'daily', 'pim_purge_post_event' );
    }
} );

/**
 * Deactivation: clean up rewrite rules.
 */
register_deactivation_hook( __FILE__, function () {
    wp_clear_scheduled_hook( 'pim_purge_unconfirmed' );
    wp_clear_scheduled_hook( 'pim_purge_post_event' );
    flush_rewrite_rules();
} );

/**
 * Cron callbacks.
 */
add_action( 'pim_purge_unconfirmed', array( 'PIM_Subscriber', 'purge_unconfirmed' ) );
add_action( 'pim_purge_post_event', array( 'PIM_Subscriber', 'purge_post_event' ) );

/**
 * Register rewrite rules for confirm/unsubscribe endpoints.
 */
function pim_register_attendance_rewrite_rules() {
    add_rewrite_rule(
        '^protest-map/unsubscribe/([a-f0-9]{64})/?$',
        'index.php?pim_unsubscribe_token=$matches[1]',
        'top'
    );
}

add_action( 'init', 'pim_register_attendance_rewrite_rules' );

add_filter( 'query_vars', function ( $vars ) {
    $vars[] = 'pim_unsubscribe_token';
    return $vars;
} );

/**
 * Handle confirm/unsubscribe URL requests.
 */
add_action( 'template_redirect', function () {
    $unsub_token = get_query_var( 'pim_unsubscribe_token', '' );
    if ( $unsub_token ) {
        PIM_Subscriber::unsubscribe( $unsub_token );
        $title   = __( 'Unsubscribed', 'protest-itinerary-map' );
        $message = __( 'You have been unsubscribed. You will no longer receive emails about this protest.', 'protest-itinerary-map' );
        wp_die( esc_html( $message ), esc_html( $title ), array( 'response' => 200 ) );
    }
} );



/**
 * Show a persistent admin notice when the ORS API key is not configured.
 */
add_action( 'admin_notices', function () {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $screen = get_current_screen();
    if ( $screen && 'protest_itinerary' === $screen->post_type && 'post' === $screen->base ) {
        // Don't double-warn on the edit screen; the meta box handles this.
        return;
    }

    $api_key = get_option( 'pim_ors_api_key', '' );
    if ( ! empty( $api_key ) ) {
        return;
    }

    $settings_url = admin_url( 'edit.php?post_type=protest_itinerary&page=protest-itinerary-map' );
    printf(
        '<div class="notice notice-warning"><p>%s <a href="%s">%s</a></p></div>',
        esc_html__( 'Protest Itinerary Map: No OpenRouteService API key configured. Routing will not work.', 'protest-itinerary-map' ),
        esc_url( $settings_url ),
        esc_html__( 'Configure now &rarr;', 'protest-itinerary-map' )
    );
} );

/**
 * AJAX handler: proxy ORS routing requests (admin preview).
 */
add_action( 'wp_ajax_pim_route_preview', 'pim_ajax_route_preview' );

function pim_ajax_route_preview() {
    check_ajax_referer( 'pim_admin_nonce', '_nonce' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( __( 'Unauthorized.', 'protest-itinerary-map' ), 403 );
    }

    $coordinates = isset( $_POST['coordinates'] ) ? $_POST['coordinates'] : '';
    $coordinates = json_decode( wp_unslash( $coordinates ), true );

    if ( ! is_array( $coordinates ) || count( $coordinates ) < 2 ) {
        wp_send_json_error( __( 'At least 2 coordinates are required.', 'protest-itinerary-map' ) );
    }

    if ( count( $coordinates ) > 50 ) {
        wp_send_json_error( __( 'Maximum 50 waypoints allowed (ORS limit).', 'protest-itinerary-map' ) );
    }

    // Validate each coordinate is [lng, lat] with numeric values.
    foreach ( $coordinates as $coord ) {
        if ( ! is_array( $coord ) || count( $coord ) !== 2
             || ! is_numeric( $coord[0] ) || ! is_numeric( $coord[1] ) ) {
            wp_send_json_error( __( 'Invalid coordinate format.', 'protest-itinerary-map' ) );
        }
    }

    $result = pim_call_ors( $coordinates );

    if ( is_wp_error( $result ) ) {
        wp_send_json_error( $result->get_error_message() );
    }

    wp_send_json_success( $result );
}

/**
 * AJAX handler: proxy Nominatim search requests (admin geocoding).
 */
add_action( 'wp_ajax_pim_geocode_search', 'pim_ajax_geocode_search' );

function pim_ajax_geocode_search() {
    check_ajax_referer( 'pim_admin_nonce', '_nonce' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( __( 'Unauthorized.', 'protest-itinerary-map' ), 403 );
    }

    $query = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';
    if ( empty( $query ) ) {
        wp_send_json_error( __( 'Empty search query.', 'protest-itinerary-map' ) );
    }

    $response = wp_remote_get(
        add_query_arg(
            array(
                'q'              => $query,
                'format'         => 'json',
                'addressdetails' => 1,
                'limit'          => 5,
            ),
            'https://nominatim.openstreetmap.org/search'
        ),
        array(
            'user-agent' => 'ProtestItineraryMap/' . PIM_VERSION . ' (' . home_url() . ')',
            'timeout'    => 10,
        )
    );

    if ( is_wp_error( $response ) ) {
        wp_send_json_error( $response->get_error_message() );
    }

    $body = json_decode( wp_remote_retrieve_body( $response ), true );
    wp_send_json_success( is_array( $body ) ? $body : array() );
}

/**
 * AJAX handler: proxy Nominatim reverse geocoding (admin map click).
 */
add_action( 'wp_ajax_pim_geocode_reverse', 'pim_ajax_geocode_reverse' );

function pim_ajax_geocode_reverse() {
    check_ajax_referer( 'pim_admin_nonce', '_nonce' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( __( 'Unauthorized.', 'protest-itinerary-map' ), 403 );
    }

    $lat = isset( $_GET['lat'] ) ? floatval( $_GET['lat'] ) : 0;
    $lng = isset( $_GET['lng'] ) ? floatval( $_GET['lng'] ) : 0;

    if ( 0 === $lat && 0 === $lng ) {
        wp_send_json_error( __( 'Invalid coordinates.', 'protest-itinerary-map' ) );
    }

    $response = wp_remote_get(
        add_query_arg(
            array(
                'lat'    => $lat,
                'lon'    => $lng,
                'format' => 'json',
            ),
            'https://nominatim.openstreetmap.org/reverse'
        ),
        array(
            'user-agent' => 'ProtestItineraryMap/' . PIM_VERSION . ' (' . home_url() . ')',
            'timeout'    => 10,
        )
    );

    if ( is_wp_error( $response ) ) {
        wp_send_json_error( $response->get_error_message() );
    }

    $body = json_decode( wp_remote_retrieve_body( $response ), true );
    wp_send_json_success( is_array( $body ) ? $body : array() );
}

/**
 * AJAX handler: test the ORS API key from settings page.
 */
add_action( 'wp_ajax_pim_test_api_key', 'pim_ajax_test_api_key' );

function pim_ajax_test_api_key() {
    check_ajax_referer( 'pim_admin_nonce', '_nonce' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( __( 'Unauthorized.', 'protest-itinerary-map' ), 403 );
    }

    $api_key = isset( $_POST['api_key'] ) ? sanitize_text_field( wp_unslash( $_POST['api_key'] ) ) : '';
    if ( empty( $api_key ) ) {
        wp_send_json_error( __( 'No API key provided.', 'protest-itinerary-map' ) );
    }

    // Make a minimal test request: two points in Paris.
    $test_coords = array( array( 2.3522, 48.8566 ), array( 2.3488, 48.8534 ) );

    $response = wp_remote_post(
        'https://api.openrouteservice.org/v2/directions/foot-walking/geojson',
        array(
            'headers' => array(
                'Authorization' => $api_key,
                'Content-Type'  => 'application/json',
            ),
            'body'    => wp_json_encode( array( 'coordinates' => $test_coords ) ),
            'timeout' => 15,
        )
    );

    if ( is_wp_error( $response ) ) {
        wp_send_json_error( $response->get_error_message() );
    }

    $code = wp_remote_retrieve_response_code( $response );
    if ( 200 === $code ) {
        wp_send_json_success( __( 'API key is valid.', 'protest-itinerary-map' ) );
    }

    $body = json_decode( wp_remote_retrieve_body( $response ), true );
    $msg  = isset( $body['error']['message'] ) ? $body['error']['message'] : "HTTP $code";
    wp_send_json_error( sprintf( __( 'ORS API error: %s', 'protest-itinerary-map' ), $msg ) );
}

/**
 * Call OpenRouteService directions API.
 *
 * @param array $coordinates Array of [lng, lat] coordinate pairs.
 * @return array|WP_Error Decoded response body or error.
 */
function pim_call_ors( array $coordinates ) {
    $api_key = get_option( 'pim_ors_api_key', '' );
    if ( empty( $api_key ) ) {
        return new WP_Error( 'pim_no_api_key', __( 'ORS API key is not configured.', 'protest-itinerary-map' ) );
    }

    $response = wp_remote_post(
        'https://api.openrouteservice.org/v2/directions/foot-walking/geojson',
        array(
            'headers' => array(
                'Authorization' => $api_key,
                'Content-Type'  => 'application/json',
            ),
            'body'    => wp_json_encode( array( 'coordinates' => $coordinates ) ),
            'timeout' => 20,
        )
    );

    if ( is_wp_error( $response ) ) {
        return $response;
    }

    $code = wp_remote_retrieve_response_code( $response );
    if ( 200 !== $code ) {
        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        $msg  = isset( $body['error']['message'] ) ? $body['error']['message'] : "HTTP $code";
        return new WP_Error( 'pim_ors_error', $msg );
    }

    return json_decode( wp_remote_retrieve_body( $response ), true );
}

/**
 * Guilamu Bug Reporter integration.
 */
add_action( 'plugins_loaded', function () {
    if ( class_exists( 'Guilamu_Bug_Reporter' ) ) {
        Guilamu_Bug_Reporter::register( array(
            'slug'        => 'protest-itinerary-map',
            'name'        => 'Protest Itinerary Map',
            'version'     => PIM_VERSION,
            'github_repo' => 'guilamu/protest-itinerary-map',
        ) );
    }
}, 20 );

/**
 * Add "Report a Bug" link to plugin row meta.
 */
add_filter( 'plugin_row_meta', 'pim_plugin_row_meta', 10, 2 );

function pim_plugin_row_meta( $links, $file ) {
    if ( PIM_PLUGIN_BASENAME !== $file ) {
        return $links;
    }

    if ( class_exists( 'Guilamu_Bug_Reporter' ) ) {
        $links[] = sprintf(
            '<a href="#" class="guilamu-bug-report-btn" data-plugin-slug="protest-itinerary-map" data-plugin-name="%s">%s</a>',
            esc_attr__( 'Protest Itinerary Map', 'protest-itinerary-map' ),
            esc_html__( '🐛 Report a Bug', 'protest-itinerary-map' )
        );
    } else {
        $links[] = sprintf(
            '<a href="%s" target="_blank">%s</a>',
            'https://github.com/guilamu/guilamu-bug-reporter/releases',
            esc_html__( '🐛 Report a Bug (install Bug Reporter)', 'protest-itinerary-map' )
        );
    }

    return $links;
}
