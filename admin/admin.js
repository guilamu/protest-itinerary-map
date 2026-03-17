/**
 * Protest Itinerary Map — Admin Map Builder
 *
 * Handles: map initialization, waypoint management, address search,
 * reverse geocoding, drag-to-reorder, and route preview via AJAX proxy.
 *
 * @package Protest_Itinerary_Map
 */
(function ($) {
    'use strict';

    /* ======================================================================
       State
       ====================================================================== */
    var map, routeLayer, markersGroup;
    var waypoints = pimAdmin.waypoints || [];
    var markers   = {};          // keyed by waypoint id
    var previewXHR = null;       // only one preview in flight
    var debounceTimer = null;
    var searchTimer = null;
    var searchCache = {};        // in-memory geocode cache
    var reverseCache = {};

    /* ======================================================================
       Icon factory
       ====================================================================== */
    function waypointIcon(type) {
        return L.icon({
            iconUrl:    pimAdmin.iconsUrl + type + '.svg',
            iconSize:   [32, 32],
            iconAnchor: [16, 32],
            popupAnchor:[0, -32],
        });
    }

    /* ======================================================================
       Initialization
       ====================================================================== */
    $(document).ready(function () {
        initMap();
        renderWaypoints();
        renderWaypointList();
        if (pimAdmin.routeGeoJSON) {
            drawRoute(pimAdmin.routeGeoJSON);
        }
        fitBounds();
        initSearch();
        initSortable();
        syncHiddenField();
    });

    function initMap() {
        var center = [pimAdmin.defaultLat, pimAdmin.defaultLng];
        if (waypoints.length > 0) {
            center = [waypoints[0].lat, waypoints[0].lng];
        }

        map = L.map('pim-admin-map', {
            center: center,
            zoom: pimAdmin.defaultZoom,
        });

        L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors &copy; <a href="https://carto.com/">CARTO</a>',
            subdomains: 'abcd',
            maxZoom: 20,
        }).addTo(map);

        markersGroup = L.layerGroup().addTo(map);
        routeLayer   = L.layerGroup().addTo(map);

        // Click map to add waypoint.
        map.on('click', function (e) {
            if (waypoints.length >= 50) {
                alert(pimAdmin.i18n.maxWaypoints);
                return;
            }
            reverseGeocode(e.latlng.lat, e.latlng.lng, function (label) {
                addWaypoint(e.latlng.lat, e.latlng.lng, label);
            });
        });
    }

    /* ======================================================================
       Waypoint CRUD
       ====================================================================== */
    function addWaypoint(lat, lng, label) {
        var type = 'checkpoint';
        if (waypoints.length === 0) type = 'start';

        var wp = {
            id:    crypto.randomUUID(),
            lat:   lat,
            lng:   lng,
            label: label || (lat.toFixed(5) + ', ' + lng.toFixed(5)),
            type:  type,
            icon:  type,
            info:  '',
            order: waypoints.length,
        };

        waypoints.push(wp);
        addMarker(wp);
        renderWaypointList();
        schedulePreview();
        syncHiddenField();
    }

    function removeWaypoint(id) {
        if (!confirm(pimAdmin.i18n.confirmRemove)) return;
        waypoints = waypoints.filter(function (wp) { return wp.id !== id; });
        reindex();
        if (markers[id]) {
            markersGroup.removeLayer(markers[id]);
            delete markers[id];
        }
        renderWaypointList();
        schedulePreview();
        syncHiddenField();
    }

    function updateWaypoint(id, field, value) {
        for (var i = 0; i < waypoints.length; i++) {
            if (waypoints[i].id === id) {
                waypoints[i][field] = value;
                if (field === 'type') {
                    waypoints[i].icon = value;
                    if (markers[id]) {
                        markers[id].setIcon(waypointIcon(value));
                    }
                }
                break;
            }
        }
        syncHiddenField();
    }

    function reindex() {
        for (var i = 0; i < waypoints.length; i++) {
            waypoints[i].order = i;
        }
    }

    /* ======================================================================
       Markers
       ====================================================================== */
    function addMarker(wp) {
        var marker = L.marker([wp.lat, wp.lng], {
            icon: waypointIcon(wp.type),
            draggable: true,
        });

        marker.bindPopup('<strong>' + escapeHtml(wp.label) + '</strong>');

        marker.on('dragend', function (e) {
            var pos = e.target.getLatLng();
            wp.lat = pos.lat;
            wp.lng = pos.lng;
            reverseGeocode(pos.lat, pos.lng, function (label) {
                wp.label = label;
                marker.setPopupContent('<strong>' + escapeHtml(label) + '</strong>');
                renderWaypointList();
                syncHiddenField();
            });
            schedulePreview();
            syncHiddenField();
        });

        markersGroup.addLayer(marker);
        markers[wp.id] = marker;
    }

    function renderWaypoints() {
        markersGroup.clearLayers();
        markers = {};
        for (var i = 0; i < waypoints.length; i++) {
            addMarker(waypoints[i]);
        }
    }

    /* ======================================================================
       Waypoint List UI
       ====================================================================== */
    function renderWaypointList() {
        var $list = $('#pim-waypoints-list');
        $list.attr('data-empty', 'Click the map or search to add waypoints.');
        $list.empty();

        for (var i = 0; i < waypoints.length; i++) {
            var wp = waypoints[i];
            var $card = $(waypointCardHtml(wp));
            $list.append($card);
        }

        // Re-init sortable.
        initSortable();
    }

    function waypointCardHtml(wp) {
        var typesOptions = '';
        for (var t = 0; t < pimAdmin.waypointTypes.length; t++) {
            var sel = wp.type === pimAdmin.waypointTypes[t] ? ' selected' : '';
            typesOptions += '<option value="' + pimAdmin.waypointTypes[t] + '"' + sel + '>' + pimAdmin.waypointTypes[t] + '</option>';
        }

        return '<div class="pim-waypoint-card" data-id="' + wp.id + '">' +
            '<span class="pim-drag-handle" title="Drag to reorder">&#9776;</span>' +
            '<img class="pim-wp-icon" src="' + pimAdmin.iconsUrl + wp.icon + '.svg" alt="">' +
            '<div class="pim-wp-body">' +
                '<div class="pim-wp-label">' + escapeHtml(wp.label) + '</div>' +
                '<div class="pim-wp-coords">' + wp.lat.toFixed(5) + ', ' + wp.lng.toFixed(5) + '</div>' +
                '<div class="pim-wp-fields">' +
                    '<select class="pim-type-select" data-id="' + wp.id + '">' + typesOptions + '</select>' +
                '</div>' +
                '<textarea class="pim-wp-info-editor" data-id="' + wp.id + '" placeholder="Info (HTML allowed)…">' + (wp.info || '') + '</textarea>' +
            '</div>' +
            '<div class="pim-wp-actions">' +
                '<button type="button" class="button-link button-link-delete pim-remove-btn" data-id="' + wp.id + '">&times;</button>' +
            '</div>' +
        '</div>';
    }

    // Event delegation.
    $(document).on('change', '.pim-type-select', function () {
        updateWaypoint($(this).data('id'), 'type', $(this).val());
        renderWaypointList();
    });

    $(document).on('input', '.pim-wp-info-editor', function () {
        updateWaypoint($(this).data('id'), 'info', $(this).val());
    });

    $(document).on('click', '.pim-remove-btn', function () {
        removeWaypoint($(this).data('id'));
    });

    /* ======================================================================
       Sortable
       ====================================================================== */
    function initSortable() {
        $('#pim-waypoints-list').sortable({
            handle: '.pim-drag-handle',
            placeholder: 'ui-sortable-placeholder',
            update: function () {
                var newOrder = [];
                $('#pim-waypoints-list .pim-waypoint-card').each(function () {
                    var id = $(this).data('id');
                    for (var i = 0; i < waypoints.length; i++) {
                        if (waypoints[i].id === id) {
                            newOrder.push(waypoints[i]);
                            break;
                        }
                    }
                });
                waypoints = newOrder;
                reindex();
                schedulePreview();
                syncHiddenField();
            },
        });
    }

    /* ======================================================================
       Route Preview
       ====================================================================== */
    function schedulePreview() {
        if (debounceTimer) clearTimeout(debounceTimer);
        debounceTimer = setTimeout(fetchRoutePreview, 800);
    }

    function fetchRoutePreview() {
        if (waypoints.length < 2) {
            routeLayer.clearLayers();
            return;
        }

        // Abort previous in-flight request.
        if (previewXHR && previewXHR.abort) {
            previewXHR.abort();
        }

        var coordinates = waypoints.map(function (wp) {
            return [wp.lng, wp.lat];
        });

        previewXHR = $.ajax({
            url:  pimAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action:      'pim_route_preview',
                _nonce:      pimAdmin.nonce,
                coordinates: JSON.stringify(coordinates),
            },
            success: function (resp) {
                if (resp.success && resp.data) {
                    drawRoute(resp.data);
                } else {
                    showRouteError(resp.data || pimAdmin.i18n.routeError);
                }
            },
            error: function (xhr, status) {
                if (status !== 'abort') {
                    showRouteError(pimAdmin.i18n.routeError);
                }
            },
            complete: function () {
                previewXHR = null;
            },
        });
    }

    function drawRoute(geojson) {
        routeLayer.clearLayers();
        try {
            var layer = L.geoJSON(geojson, {
                style: {
                    color:   '#d63384',
                    weight:  4,
                    opacity: 0.8,
                },
                pointToLayer: function () { return null; }, // don't draw ORS points
            });
            routeLayer.addLayer(layer);
        } catch (e) {
            // Silently skip malformed GeoJSON.
        }
    }

    function showRouteError(msg) {
        // Show as a transient notice above the map.
        var $wrap = $('#pim-map-builder-wrap');
        $wrap.find('.pim-route-notice').remove();
        $wrap.prepend(
            '<div class="notice notice-warning inline pim-route-notice"><p>' +
            escapeHtml(typeof msg === 'string' ? msg : pimAdmin.i18n.routeError) +
            '</p></div>'
        );
        setTimeout(function () { $wrap.find('.pim-route-notice').fadeOut(400, function () { $(this).remove(); }); }, 6000);
    }

    function fitBounds() {
        if (waypoints.length === 0) return;
        var bounds = L.latLngBounds(waypoints.map(function (wp) { return [wp.lat, wp.lng]; }));
        if (bounds.isValid()) {
            map.fitBounds(bounds.pad(0.1));
        }
    }

    /* ======================================================================
       Address Search
       ====================================================================== */
    function initSearch() {
        var $input   = $('#pim-search-input');
        var $results = $('#pim-search-results');

        $input.on('input', function () {
            var q = $.trim($input.val());
            if (q.length < 3) {
                $results.hide().empty();
                return;
            }

            if (searchTimer) clearTimeout(searchTimer);
            searchTimer = setTimeout(function () { doSearch(q); }, 400);
        });

        $input.on('blur', function () {
            setTimeout(function () { $results.hide(); }, 200);
        });

        $results.on('click', '.pim-search-item', function () {
            var lat   = parseFloat($(this).data('lat'));
            var lng   = parseFloat($(this).data('lng'));
            var label = $(this).text();
            addWaypoint(lat, lng, label);
            map.setView([lat, lng], 15);
            $input.val('');
            $results.hide().empty();
        });
    }

    function doSearch(q) {
        var $results = $('#pim-search-results');

        // Check in-memory cache.
        if (searchCache[q]) {
            renderSearchResults(searchCache[q]);
            return;
        }

        $.ajax({
            url:  pimAdmin.ajaxUrl,
            type: 'GET',
            data: {
                action: 'pim_geocode_search',
                _nonce: pimAdmin.nonce,
                q:      q,
            },
            success: function (resp) {
                if (resp.success && resp.data) {
                    searchCache[q] = resp.data;
                    renderSearchResults(resp.data);
                } else {
                    $results.html('<div class="pim-search-item">' + pimAdmin.i18n.noResults + '</div>').show();
                }
            },
        });
    }

    function renderSearchResults(items) {
        var $results = $('#pim-search-results');
        $results.empty();
        if (!items.length) {
            $results.html('<div class="pim-search-item">' + pimAdmin.i18n.noResults + '</div>').show();
            return;
        }
        for (var i = 0; i < items.length; i++) {
            $results.append(
                '<div class="pim-search-item" data-lat="' + items[i].lat + '" data-lng="' + items[i].lon + '">' +
                escapeHtml(items[i].display_name) +
                '</div>'
            );
        }
        $results.show();
    }

    /* ======================================================================
       Reverse Geocoding
       ====================================================================== */
    function reverseGeocode(lat, lng, callback) {
        var key = lat.toFixed(5) + ',' + lng.toFixed(5);
        if (reverseCache[key]) {
            callback(reverseCache[key]);
            return;
        }

        $.ajax({
            url:  pimAdmin.ajaxUrl,
            type: 'GET',
            data: {
                action: 'pim_geocode_reverse',
                _nonce: pimAdmin.nonce,
                lat:    lat,
                lng:    lng,
            },
            success: function (resp) {
                var label = lat.toFixed(5) + ', ' + lng.toFixed(5);
                if (resp.success && resp.data && resp.data.display_name) {
                    label = resp.data.display_name;
                }
                reverseCache[key] = label;
                callback(label);
            },
            error: function () {
                callback(lat.toFixed(5) + ', ' + lng.toFixed(5));
            },
        });
    }

    /* ======================================================================
       Hidden Field Sync
       ====================================================================== */
    function syncHiddenField() {
        $('#pim-waypoints-json').val(JSON.stringify(waypoints));
    }

    /* ======================================================================
       Utility
       ====================================================================== */
    function escapeHtml(str) {
        if (!str) return '';
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

})(jQuery);
