<?php
/**
 * Email notification helpers.
 *
 * @package Protest_Itinerary_Map
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PIM_Notification {

    /**
     * Send a protest update notification to all confirmed protest subscribers.
     *
     * @param int    $post_id Itinerary post ID.
     * @param string $message Admin-written message body.
     * @return int Number of emails sent.
     */
    public static function send_protest_update( $post_id, $message ) {
        $event_name  = get_post_meta( $post_id, '_pim_event_name', true );
        $event_label = $event_name ? $event_name : __( 'Protest update', 'protest-itinerary-map' );
        $subscribers = PIM_Subscriber::get_subscribers( $post_id, 'protest' );

        if ( empty( $subscribers ) ) {
            return 0;
        }

        $subject = sprintf(
            /* translators: %s: event name */
            __( 'Update: %s', 'protest-itinerary-map' ),
            $event_label
        );

        $sent = 0;
        foreach ( $subscribers as $sub ) {
            $unsub_url = home_url( '/protest-map/unsubscribe/' . $sub->token . '/' );

            $body  = $message . "\n\n";
            $body .= "──────────────────\n";
            $body .= sprintf(
                /* translators: %s: unsubscribe URL */
                __( 'To unsubscribe: %s', 'protest-itinerary-map' ),
                $unsub_url
            ) . "\n";

            if ( wp_mail( $sub->email, $subject, $body ) ) {
                $sent++;
            }
        }

        return $sent;
    }

}
