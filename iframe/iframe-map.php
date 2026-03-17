<?php
/**
 * Standalone iframe embed page template.
 *
 * Renders a minimal HTML page (no theme header/footer) displaying the map.
 *
 * Variables available from PIM_Iframe_Endpoint::handle_request():
 *   $js_data       — array of map data for JS
 *   $event_name    — string
 *   $waypoints_arr — array
 *
 * @package Protest_Itinerary_Map
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo esc_html( $event_name ?: __( 'Protest Itinerary Map', 'protest-itinerary-map' ) ); ?></title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <link rel="stylesheet" href="<?php echo esc_url( PIM_PLUGIN_URL . 'public/public.css?v=' . PIM_VERSION ); ?>">
    <style>
        /* Iframe-specific overrides */
        *, *::before, *::after { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen, Ubuntu, sans-serif;
            font-size: 14px;
            background: #fff;
        }
        .pim-container { margin: 0; }
        .pim-map { height: 100vh !important; border: 0; border-radius: 0; }
        .pim-layout.pim-has-sidebar { height: 100vh; }
        .pim-layout.pim-has-sidebar .pim-map-col { height: 100%; }
        .pim-layout.pim-has-sidebar .pim-map-col .pim-map { height: 100% !important; }
        .pim-layout.pim-has-sidebar .pim-sidebar { max-height: 100vh; border: 0; border-left: 1px solid var(--pim-border); border-radius: 0; }
    </style>
</head>
<body>

<div class="pim-container">
    <div class="pim-layout pim-has-sidebar">
        <div class="pim-map-col">
            <div id="pim-embed-map" class="pim-map"></div>
        </div>
        <div class="pim-sidebar" id="pim-embed-map-sidebar">
            <ul class="pim-stop-list">
                <?php foreach ( $waypoints_arr as $wp_item ) : ?>
                    <li class="pim-stop-item" data-wp-id="<?php echo esc_attr( $wp_item['id'] ); ?>" data-instance="embed">
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
    </div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="<?php echo esc_url( PIM_PLUGIN_URL . 'public/public.js?v=' . PIM_VERSION ); ?>"></script>
<script>
    // Provide the data object expected by public.js.
    window.pimPublic_embed = <?php echo wp_json_encode( $js_data ); ?>;
</script>

</body>
</html>
