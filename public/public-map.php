<?php
/**
 * Frontend map template for the [protest_map] shortcode.
 *
 * Variables available via the shortcode render context:
 *   $container_id  — unique HTML id for this map instance
 *   $height        — CSS height value
 *   $show_sidebar  — boolean
 *   $waypoints_arr — array of waypoint data
 *   $instance      — numeric instance id
 *   $event_name    — itinerary event name
 *
 * @package Protest_Itinerary_Map
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="pim-container" id="<?php echo esc_attr( $container_id ); ?>-wrap">

    <?php if ( $event_name ) : ?>
        <h3 class="pim-event-title"><?php echo esc_html( $event_name ); ?></h3>
    <?php endif; ?>

    <div class="pim-layout <?php echo $show_sidebar ? 'pim-has-sidebar' : ''; ?>">

        <div class="pim-map-col">
            <div id="<?php echo esc_attr( $container_id ); ?>" class="pim-map" style="height: <?php echo esc_attr( $height ); ?>;">
                <button type="button" class="pim-btn-metro" title="<?php esc_attr_e( 'Show metro lines', 'protest-itinerary-map' ); ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="4" y="3" width="16" height="16" rx="2" />
                        <circle cx="9" cy="15" r="1" />
                        <circle cx="15" cy="15" r="1" />
                        <path d="M4 11h16" />
                        <path d="M12 3v8" />
                    </svg>
                </button>
            </div>
        </div>

        <?php if ( $show_sidebar && ! empty( $waypoints_arr ) ) : ?>
            <div class="pim-sidebar" id="<?php echo esc_attr( $container_id ); ?>-sidebar">
                <ul class="pim-stop-list">
                    <?php foreach ( $waypoints_arr as $i => $wp_item ) : ?>
                        <li class="pim-stop-item" data-wp-id="<?php echo esc_attr( $wp_item['id'] ); ?>" data-instance="<?php echo esc_attr( $instance ); ?>">
                            <img class="pim-stop-icon" src="<?php echo esc_url( PIM_PLUGIN_URL . 'assets/icons/' . $wp_item['icon'] . '.svg' ); ?>" alt="<?php echo esc_attr( $wp_item['type'] ); ?>" width="20" height="20">
                            <div class="pim-stop-content">
                                <span class="pim-stop-type"><?php
                                    $type_map = array(
                                        'start'         => __( 'Start', 'protest-itinerary-map' ),
                                        'end'           => __( 'End', 'protest-itinerary-map' ),
                                        'checkpoint'    => __( 'Checkpoint', 'protest-itinerary-map' ),
                                        'meeting-point' => __( 'Meeting Point', 'protest-itinerary-map' ),
                                        'rest-stop'     => __( 'Rest Stop', 'protest-itinerary-map' ),
                                    );
                                    echo esc_html( isset( $type_map[ $wp_item['type'] ] ) ? $type_map[ $wp_item['type'] ] : ucwords( str_replace( '-', ' ', $wp_item['type'] ) ) );
                                ?></span>
                                <span class="pim-stop-label"><?php echo esc_html( $wp_item['label'] ); ?></span>
                                <?php if ( ! empty( $wp_item['info'] ) ) : ?>
                                    <div class="pim-stop-info"><?php echo wp_kses_post( $wp_item['info'] ); ?></div>
                                <?php endif; ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

    </div>
</div>
