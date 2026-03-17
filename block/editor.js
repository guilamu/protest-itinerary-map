/**
 * Protest Itinerary Map — Gutenberg Block (Editor)
 *
 * No build step required. Uses wp.element.createElement directly.
 *
 * @package Protest_Itinerary_Map
 */
( function () {
    'use strict';

    var el             = wp.element.createElement;
    var registerBlock  = wp.blocks.registerBlockType;
    var InspectorControls = wp.blockEditor.InspectorControls;
    var PanelBody      = wp.components.PanelBody;
    var SelectControl  = wp.components.SelectControl;
    var ToggleControl  = wp.components.ToggleControl;
    var TextControl    = wp.components.TextControl;
    var Placeholder    = wp.components.Placeholder;
    var Spinner        = wp.components.Spinner;
    var useEffect      = wp.element.useEffect;
    var useState       = wp.element.useState;

    registerBlock( 'protest-itinerary-map/protest-map', {
        edit: function ( props ) {
            var attributes   = props.attributes;
            var setAttributes = props.setAttributes;
            var itineraryId  = attributes.id;
            var sidebar      = attributes.sidebar;
            var height       = attributes.height;

            var _state       = useState( null );
            var itineraries  = _state[0];
            var setItineraries = _state[1];

            var _loading     = useState( true );
            var loading      = _loading[0];
            var setLoading   = _loading[1];

            useEffect( function () {
                wp.apiFetch( { path: '/pim/v1/itineraries' } )
                    .then( function ( data ) {
                        setItineraries( data );
                        setLoading( false );
                    } )
                    .catch( function () {
                        setItineraries( [] );
                        setLoading( false );
                    } );
            }, [] );

            // Loading state.
            if ( loading ) {
                return el( Placeholder, {
                    icon: 'location-alt',
                    label: 'Protest Itinerary Map',
                }, el( Spinner ) );
            }

            // No itinerary selected — show picker.
            if ( ! itineraryId ) {
                var options = [ { label: '— Select an itinerary —', value: 0 } ];
                if ( itineraries ) {
                    itineraries.forEach( function ( it ) {
                        var label = it.title;
                        if ( it.event_name ) {
                            label += ' — ' + it.event_name;
                        }
                        options.push( { label: label, value: it.id } );
                    } );
                }

                return el( Placeholder, {
                    icon: 'location-alt',
                    label: 'Protest Itinerary Map',
                    instructions: 'Select a protest itinerary to display.',
                }, el( SelectControl, {
                    options: options,
                    value: itineraryId,
                    onChange: function ( val ) {
                        setAttributes( { id: parseInt( val, 10 ) } );
                    },
                } ) );
            }

            // Itinerary selected — show preview card.
            var selected = null;
            if ( itineraries ) {
                for ( var i = 0; i < itineraries.length; i++ ) {
                    if ( itineraries[i].id === itineraryId ) {
                        selected = itineraries[i];
                        break;
                    }
                }
            }

            var options2 = [ { label: '— Select an itinerary —', value: 0 } ];
            if ( itineraries ) {
                itineraries.forEach( function ( it ) {
                    var label = it.title;
                    if ( it.event_name ) label += ' — ' + it.event_name;
                    options2.push( { label: label, value: it.id } );
                } );
            }

            return el( 'div', { className: 'pim-block-preview' },
                el( InspectorControls, {},
                    el( PanelBody, { title: 'Map Settings', initialOpen: true },
                        el( SelectControl, {
                            label: 'Itinerary',
                            value: itineraryId,
                            options: options2,
                            onChange: function ( val ) {
                                setAttributes( { id: parseInt( val, 10 ) } );
                            },
                        } ),
                        el( ToggleControl, {
                            label: 'Show sidebar',
                            checked: sidebar,
                            onChange: function ( val ) {
                                setAttributes( { sidebar: val } );
                            },
                        } ),
                        el( TextControl, {
                            label: 'Map height',
                            value: height,
                            onChange: function ( val ) {
                                setAttributes( { height: val } );
                            },
                        } )
                    )
                ),
                el( 'div', {
                    style: {
                        border: '1px solid #ddd',
                        borderRadius: '4px',
                        padding: '20px',
                        textAlign: 'center',
                        background: '#f9f9f9',
                    },
                },
                    el( 'span', {
                        className: 'dashicons dashicons-location-alt',
                        style: { fontSize: '36px', width: '36px', height: '36px', marginBottom: '10px', color: '#d63384' },
                    } ),
                    el( 'div', { style: { fontWeight: 'bold', fontSize: '14px' } },
                        selected ? selected.title : ( 'Itinerary #' + itineraryId )
                    ),
                    selected && selected.event_name ? el( 'div', { style: { color: '#666', marginTop: '4px' } }, selected.event_name ) : null,
                    el( 'div', { style: { marginTop: '8px', fontSize: '12px', color: '#999' } },
                        'Shortcode: [protest_map id="' + itineraryId + '"' + ( sidebar ? '' : ' sidebar="no"' ) + ( height !== '500px' ? ' height="' + height + '"' : '' ) + ']'
                    )
                )
            );
        },
    } );
} )();
