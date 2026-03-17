<?php
/**
 * Admin meta box template — Itinerary Info fields.
 *
 * @package Protest_Itinerary_Map
 * @var WP_Post $post Current post object (provided by WordPress meta box callback).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$event_name   = get_post_meta( $post->ID, '_pim_event_name', true );
$event_date   = get_post_meta( $post->ID, '_pim_event_date', true );
$description  = get_post_meta( $post->ID, '_pim_event_description', true );
$contact      = get_post_meta( $post->ID, '_pim_organizer_contact', true );
?>
<table class="form-table pim-info-table">
    <tr>
        <th><label for="pim_event_name"><?php esc_html_e( 'Event Name', 'protest-itinerary-map' ); ?></label></th>
        <td><input type="text" id="pim_event_name" name="pim_event_name" value="<?php echo esc_attr( $event_name ); ?>" class="large-text"></td>
    </tr>
    <tr>
        <th><label for="pim_event_date"><?php esc_html_e( 'Event Date', 'protest-itinerary-map' ); ?></label></th>
        <td><input type="date" id="pim_event_date" name="pim_event_date" value="<?php echo esc_attr( $event_date ); ?>" min="<?php echo esc_attr( wp_date( 'Y-m-d' ) ); ?>"></td>
    </tr>
    <tr>
        <th><label for="pim_event_description"><?php esc_html_e( 'Description', 'protest-itinerary-map' ); ?></label></th>
        <td><textarea id="pim_event_description" name="pim_event_description" rows="3" class="large-text"><?php echo esc_textarea( $description ); ?></textarea></td>
    </tr>
    <tr>
        <th><label for="pim_organizer_contact"><?php esc_html_e( 'Organizer Contact', 'protest-itinerary-map' ); ?></label></th>
        <td><input type="text" id="pim_organizer_contact" name="pim_organizer_contact" value="<?php echo esc_attr( $contact ); ?>" class="large-text"></td>
    </tr>
</table>
