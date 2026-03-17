<?php
/**
 * Server-side render callback for the Protest Itinerary Map block.
 *
 * @package Protest_Itinerary_Map
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Block default content.
 * @var WP_Block $block      Block instance.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$id      = isset( $attributes['id'] ) ? absint( $attributes['id'] ) : 0;
$sidebar = isset( $attributes['sidebar'] ) ? $attributes['sidebar'] : true;
$height  = isset( $attributes['height'] ) ? sanitize_text_field( $attributes['height'] ) : '500px';

if ( ! $id ) {
    return '';
}

$shortcode  = '[protest_map id="' . $id . '"';
$shortcode .= ' sidebar="' . ( $sidebar ? 'yes' : 'no' ) . '"';
$shortcode .= ' height="' . esc_attr( $height ) . '"';
$shortcode .= ']';

echo do_shortcode( $shortcode );
