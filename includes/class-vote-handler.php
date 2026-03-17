<?php
/**
 * Vote handler — AJAX endpoint, IP hashing, rate limiting.
 *
 * @package Protest_Itinerary_Map
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PIM_Vote_Handler {

    /**
     * Rate-limit window in seconds (24 hours).
     */
    const RATE_LIMIT_WINDOW = 86400;

    /**
     * Cookie name prefix.
     */
    const COOKIE_PREFIX = 'pim_voted_';

    /**
     * Hook AJAX endpoints.
     */
    public static function init() {
        add_action( 'wp_ajax_pim_cast_vote', array( __CLASS__, 'handle_vote' ) );
        add_action( 'wp_ajax_nopriv_pim_cast_vote', array( __CLASS__, 'handle_vote' ) );
        add_action( 'wp_ajax_pim_submit_email', array( __CLASS__, 'handle_email' ) );
        add_action( 'wp_ajax_nopriv_pim_submit_email', array( __CLASS__, 'handle_email' ) );
    }

    /**
     * Get the IP hash salt.
     *
     * @return string
     */
    private static function get_salt() {
        if ( defined( 'PIM_IP_SALT' ) && PIM_IP_SALT ) {
            return PIM_IP_SALT;
        }
        // Fallback: use WordPress auth salt.
        return defined( 'AUTH_SALT' ) ? AUTH_SALT : 'pim-default-salt';
    }

    /**
     * Hash an IP address for a specific post.
     *
     * @param string $ip      Client IP.
     * @param int    $post_id Itinerary post ID.
     * @return string SHA-256 hex hash.
     */
    private static function hash_ip( $ip, $post_id ) {
        return hash( 'sha256', $ip . self::get_salt() . $post_id );
    }

    /**
     * Get the client IP address.
     *
     * @return string
     */
    private static function get_client_ip() {
        // Trust only REMOTE_ADDR to avoid header spoofing.
        return isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( $_SERVER['REMOTE_ADDR'] ) : '0.0.0.0';
    }

    /**
     * Handle the vote AJAX request.
     */
    public static function handle_vote() {
        check_ajax_referer( 'pim_public_nonce', '_nonce' );

        $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
        if ( ! $post_id ) {
            wp_send_json_error( array( 'reason' => 'invalid_post', 'count' => 0 ) );
        }

        $post = get_post( $post_id );
        if ( ! $post || PIM_Post_Type::POST_TYPE !== $post->post_type || 'publish' !== $post->post_status ) {
            wp_send_json_error( array( 'reason' => 'invalid_post', 'count' => 0 ) );
        }

        // Check attendance enabled.
        if ( ! get_post_meta( $post_id, '_pim_attendance_enabled', true ) ) {
            wp_send_json_error( array( 'reason' => 'disabled', 'count' => 0 ) );
        }

        $current_count = (int) get_post_meta( $post_id, '_pim_attendance_count', true );

        // Layer 2: Cookie check.
        $cookie_name = self::COOKIE_PREFIX . $post_id;
        if ( isset( $_COOKIE[ $cookie_name ] ) ) {
            wp_send_json_error( array(
                'reason' => 'already_voted',
                'count'  => $current_count,
            ) );
        }

        // Layer 3: IP rate limiting.
        $ip      = self::get_client_ip();
        $ip_hash = self::hash_ip( $ip, $post_id );
        $ip_log  = get_post_meta( $post_id, '_pim_attendance_ip_log', true );
        $ip_log  = $ip_log ? json_decode( $ip_log, true ) : array();
        $now     = time();

        // Prune expired entries.
        $ip_log = array_filter( $ip_log, function ( $entry ) use ( $now ) {
            return isset( $entry['timestamp'] ) && ( $now - $entry['timestamp'] ) < self::RATE_LIMIT_WINDOW;
        } );

        // Check for existing hash.
        foreach ( $ip_log as $entry ) {
            if ( isset( $entry['hash'] ) && $entry['hash'] === $ip_hash ) {
                wp_send_json_error( array(
                    'reason' => 'already_voted',
                    'count'  => $current_count,
                ) );
            }
        }

        // Vote is valid — increment.
        $new_count = $current_count + 1;
        update_post_meta( $post_id, '_pim_attendance_count', $new_count );

        // Append hash to log.
        $ip_log[] = array(
            'hash'      => $ip_hash,
            'timestamp' => $now,
        );
        update_post_meta( $post_id, '_pim_attendance_ip_log', wp_slash( wp_json_encode( array_values( $ip_log ) ) ) );

        // Set Layer 2 cookie.
        setcookie(
            $cookie_name,
            '1',
            array(
                'expires'  => $now + self::RATE_LIMIT_WINDOW,
                'path'     => '/',
                'httponly'  => true,
                'samesite' => 'Strict',
                'secure'   => is_ssl(),
            )
        );

        wp_send_json_success( array(
            'count'       => $new_count,
            'union_label' => get_post_meta( $post_id, '_pim_union_optin_label', true ),
        ) );
    }

    /**
     * Handle the email submission AJAX request.
     */
    public static function handle_email() {
        check_ajax_referer( 'pim_public_nonce', '_nonce' );

        $post_id        = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
        $email          = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
        $notify_protest = isset( $_POST['notify_protest'] ) && '1' === $_POST['notify_protest'];
        $notify_union   = isset( $_POST['notify_union'] ) && '1' === $_POST['notify_union'];

        if ( ! $post_id || ! is_email( $email ) ) {
            wp_send_json_error( array( 'reason' => 'invalid_input' ) );
        }

        $post = get_post( $post_id );
        if ( ! $post || PIM_Post_Type::POST_TYPE !== $post->post_type || 'publish' !== $post->post_status ) {
            wp_send_json_error( array( 'reason' => 'invalid_post' ) );
        }

        if ( ! get_post_meta( $post_id, '_pim_attendance_enabled', true ) ) {
            wp_send_json_error( array( 'reason' => 'disabled' ) );
        }

        if ( ! $notify_protest && ! $notify_union ) {
            wp_send_json_error( array( 'reason' => 'no_consent' ) );
        }

        $result = PIM_Subscriber::subscribe( $post_id, $email, $notify_protest, $notify_union );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'reason' => $result->get_error_message() ) );
        }

        wp_send_json_success();
    }
}
