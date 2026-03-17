/**
 * Protest Itinerary Map — Frontend Map Rendering
 *
 * Reads localized data (one object per shortcode instance) and initializes
 * Leaflet maps with the cached route and waypoints. No external API calls.
 *
 * @package Protest_Itinerary_Map
 */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        // Find all pimPublic_* localized data objects.
        var instances = [];
        for (var key in window) {
            if (window.hasOwnProperty(key) && key.indexOf('pimPublic_') === 0) {
                instances.push(window[key]);
            }
        }

        instances.forEach(function (data) {
            initInstance(data);
        });
    });

    function initInstance(data) {
        var container = document.getElementById(data.containerId);
        if (!container) return;

        var map = L.map(data.containerId, {
            scrollWheelZoom: false,
        });

        L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors &copy; <a href="https://carto.com/">CARTO</a>',
            subdomains: 'abcd',
            maxZoom: 20,
        }).addTo(map);

        // Metro overlay state.
        var metroState = {
            linesLayer: null,
            stationsLayer: null,
            dataLoaded: false,
            visible: false,
        };

        // Create panes for metro layers.
        map.createPane('metroPane');
        map.getPane('metroPane').style.zIndex = 320;
        map.createPane('metroStationPane');
        map.getPane('metroStationPane').style.zIndex = 450;

        var bounds = [];
        var markerMap = {};
        var waypoints = data.waypoints || [];
        var legs = data.routeLegs || [];
        var summary = data.routeSummary || null;
        var postId = data.postId || 0;
        var attendanceEnabled = data.attendanceEnabled || false;
        var currentCount = data.attendanceCount || 0;
        var unionLabel = data.unionLabel || '';
        var ajaxUrl = data.ajaxUrl || '';
        var nonce = data.nonce || '';
        var i18n = data.i18n || {};

        // Track vote state for this instance.
        var lsKey = 'pim_voted_' + postId;
        var hasVoted = false;
        try { hasVoted = !!localStorage.getItem(lsKey); } catch (e) {}

        // Draw route polyline.
        if (data.routeGeoJSON) {
            try {
                L.geoJSON(data.routeGeoJSON, {
                    style: {
                        color: '#d63384',
                        weight: 4,
                        opacity: 0.8,
                    },
                    pointToLayer: function () { return null; },
                }).addTo(map);
            } catch (e) {
                // Skip malformed GeoJSON.
            }
        }

        // Place markers.
        waypoints.forEach(function (wp, i) {
            var icon = L.icon({
                iconUrl:     data.iconsUrl + wp.icon + '.svg',
                iconSize:    [32, 32],
                iconAnchor:  [16, 32],
                popupAnchor: [0, -32],
            });

            var popupContent = buildPopupContent(wp, i, legs, summary, {
                attendanceEnabled: attendanceEnabled,
                currentCount: currentCount,
                hasVoted: hasVoted,
                postId: postId,
                i18n: i18n,
            });

            var marker = L.marker([wp.lat, wp.lng], { icon: icon })
                .bindPopup(popupContent, { maxWidth: 320, minWidth: 220 })
                .addTo(map);

            markerMap[wp.id] = marker;
            bounds.push([wp.lat, wp.lng]);

            // Attach vote event handlers on popup open.
            if (i === 0 && attendanceEnabled) {
                marker.on('popupopen', function () {
                    bindVoteEvents(marker, wp, i, legs, summary, {
                        attendanceEnabled: attendanceEnabled,
                        currentCount: currentCount,
                        hasVoted: hasVoted,
                        postId: postId,
                        ajaxUrl: ajaxUrl,
                        nonce: nonce,
                        unionLabel: unionLabel,
                        lsKey: lsKey,
                        i18n: i18n,
                        setVoted: function (count) {
                            hasVoted = true;
                            currentCount = count;
                        },
                        setCount: function (count) {
                            currentCount = count;
                        },
                    });
                });
            }
        });

        // Fit bounds.
        if (bounds.length > 0) {
            map.fitBounds(L.latLngBounds(bounds).pad(0.1));
        } else {
            map.setView([48.8566, 2.3522], 13);
        }

        // Sidebar click interaction.
        if (data.showSidebar) {
            var sidebarItems = document.querySelectorAll(
                '#' + data.containerId + '-sidebar .pim-stop-item'
            );
            sidebarItems.forEach(function (el) {
                el.addEventListener('click', function () {
                    var wpId = el.getAttribute('data-wp-id');
                    if (markerMap[wpId]) {
                        var latlng = markerMap[wpId].getLatLng();
                        map.setView(latlng, 16, { animate: true });
                        markerMap[wpId].openPopup();
                    }

                    // Highlight active.
                    sidebarItems.forEach(function (s) { s.classList.remove('pim-active'); });
                    el.classList.add('pim-active');
                });
            });
        }

        // Enable scroll-zoom on focus.
        map.on('click', function () {
            map.scrollWheelZoom.enable();
        });

        // Metro toggle button.
        var metroBtn = container.querySelector('.pim-btn-metro');
        if (metroBtn) {
            metroBtn.addEventListener('click', function (e) {
                e.stopPropagation();
                if (metroState.visible) {
                    hideMetroLayers(map, metroState);
                    metroState.visible = false;
                    metroBtn.classList.remove('pim-btn-active');
                    metroBtn.title = i18n.showMetro || 'Show metro lines';
                } else {
                    metroState.visible = true;
                    metroBtn.classList.add('pim-btn-active');
                    metroBtn.title = i18n.hideMetro || 'Hide metro lines';
                    if (!metroState.dataLoaded) {
                        loadMetroData(map, metroState);
                    } else {
                        showMetroLayers(map, metroState);
                    }
                }
            });
        }
    }

    /**
     * Build the popup HTML content for a waypoint.
     */
    function buildPopupContent(wp, i, legs, summary, state) {
        var html = '<div class="pim-popup">';
        var typeLabels = {
            'start': state.i18n.typeStart || 'Start',
            'end': state.i18n.typeEnd || 'End',
            'checkpoint': state.i18n.typeCheckpoint || 'Checkpoint',
            'meeting-point': state.i18n.typeMeetingPoint || 'Meeting Point',
            'rest-stop': state.i18n.typeRestStop || 'Rest Stop'
        };
        html += '<strong>' + escapeHtml(typeLabels[wp.type] || ucwords(wp.type.replace(/-/g, ' '))) + '</strong>';
        html += '<br>' + escapeHtml(wp.label);

        if (wp.info) {
            html += '<div class="pim-popup-info">' + wp.info + '</div>';
        }

        // Show leg distance/duration to next point.
        if (i === 0 && summary) {
            // Start marker: show total route summary.
            html += '<div class="pim-popup-leg">';
            html += '→ ' + formatDistance(summary.distance) + ' · ' + formatDuration(summary.duration) + ' ' + escapeHtml(state.i18n.walk || 'walk');
            html += '</div>';
        } else if (legs[i]) {
            html += '<div class="pim-popup-leg">';
            html += '→ ' + formatDistance(legs[i].distance) + ' · ' + formatDuration(legs[i].duration);
            html += '</div>';
        }

        // Attendance section on starting point.
        if (i === 0 && state.attendanceEnabled) {
            html += '<div class="pim-attendance" data-post-id="' + state.postId + '">';
            html += '<div class="pim-attendance-divider"></div>';
            html += '<div class="pim-attendance-count">👋 <span class="pim-count-number">' + state.currentCount + '</span> ' + escapeHtml(state.currentCount === 1 ? (state.i18n.personAttend || 'person plans to attend') : (state.i18n.peopleAttend || 'people plan to attend')) + '</div>';

            if (state.hasVoted) {
                html += '<div class="pim-vote-status pim-voted">✅ ' + escapeHtml(state.i18n.youComing || "You're coming!") + '</div>';
            } else {
                html += '<button type="button" class="pim-vote-btn">👋 ' + escapeHtml(state.i18n.illBeThere || "I'll be there!") + '</button>';
            }

            // Containers for email form and confirmation.
            html += '<div class="pim-email-section"></div>';
            html += '</div>';
        }

        html += '</div>';
        return html;
    }

    /**
     * Bind click/submit events inside a popup for the vote button and email form.
     */
    function bindVoteEvents(marker, wp, i, legs, summary, ctx) {
        var popup = marker.getPopup();
        var el = popup.getElement();
        if (!el) return;

        var voteBtn = el.querySelector('.pim-vote-btn');
        if (voteBtn && !ctx.hasVoted) {
            voteBtn.addEventListener('click', function (e) {
                e.stopPropagation();
                voteBtn.disabled = true;
                voteBtn.textContent = '…';

                var formData = new FormData();
                formData.append('action', 'pim_cast_vote');
                formData.append('post_id', ctx.postId);
                formData.append('_nonce', ctx.nonce);

                fetch(ctx.ajaxUrl, { method: 'POST', body: formData, credentials: 'same-origin' })
                    .then(function (r) { return r.json(); })
                    .then(function (resp) {
                        if (resp.success) {
                            ctx.setVoted(resp.data.count);
                            try { localStorage.setItem(ctx.lsKey, '1'); } catch (e) {}

                            // Refresh popup content.
                            var newContent = buildPopupContent(wp, i, legs, summary, {
                                attendanceEnabled: true,
                                currentCount: resp.data.count,
                                hasVoted: true,
                                postId: ctx.postId,
                                i18n: ctx.i18n,
                            });
                            marker.setPopupContent(newContent);
                            marker.openPopup();

                            // Always show email form after voting (defer to let Leaflet finish rendering).
                            setTimeout(function () {
                                showEmailForm(marker, wp, i, legs, summary, ctx, resp.data.union_label || ctx.unionLabel);
                            }, 50);
                        } else {
                            // Already voted or error — update count.
                            if (resp.data && resp.data.count !== undefined) {
                                ctx.setCount(resp.data.count);
                            }
                            if (resp.data && resp.data.reason === 'already_voted') {
                                ctx.setVoted(resp.data.count);
                                try { localStorage.setItem(ctx.lsKey, '1'); } catch (e) {}
                            }
                            var newContent = buildPopupContent(wp, i, legs, summary, {
                                attendanceEnabled: true,
                                currentCount: resp.data ? resp.data.count : ctx.currentCount,
                                hasVoted: true,
                                postId: ctx.postId,
                                i18n: ctx.i18n,
                            });
                            marker.setPopupContent(newContent);
                            marker.openPopup();
                        }
                    })
                    .catch(function () {
                        voteBtn.disabled = false;
                        voteBtn.textContent = '👋 ' + (ctx.i18n.illBeThere || "I'll be there!");
                    });
            });
        }
    }

    /**
     * Inject the email collection form inside the popup (two-step flow).
     * Step 1: Ask for email (protest opt-in is implicit).
     * Step 2: If union label exists, ask about union subscription.
     */
    function showEmailForm(marker, wp, i, legs, summary, ctx, unionLabel) {
        var popup = marker.getPopup();
        var el = popup.getElement();
        if (!el) return;

        var section = el.querySelector('.pim-email-section');
        if (!section) return;

        // Step 1: email input.
        var html = '<div class="pim-email-form">';
        html += '<div class="pim-attendance-divider"></div>';
        html += '<p class="pim-email-sub-prompt">' + escapeHtml(ctx.i18n.notifyMe || 'Notify me of updates about this protest') + '</p>';
        html += '<div class="pim-email-input-row">';
        html += '<input type="email" class="pim-email-input" placeholder="your@email.com">';
        html += '</div>';
        html += '<p class="pim-email-privacy" style="font-size:11px;color:#888;margin:4px 0 8px;">' + escapeHtml(ctx.i18n.emailDeleted || 'Your email will be deleted the day after the protest date.') + '</p>';
        html += '<div class="pim-email-actions">';
        html += '<button type="button" class="pim-email-confirm-btn">' + escapeHtml(ctx.i18n.confirm || 'Confirm') + '</button>';
        html += '<button type="button" class="pim-email-dismiss-btn">' + escapeHtml(ctx.i18n.noThanks || 'No thanks') + '</button>';
        html += '</div>';
        html += '</div>';

        section.innerHTML = html;
        pimPopupReflow(marker);

        var emailInput = section.querySelector('.pim-email-input');
        var confirmBtn = section.querySelector('.pim-email-confirm-btn');
        var dismissBtn = section.querySelector('.pim-email-dismiss-btn');

        dismissBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            section.innerHTML = '';
            pimPopupReflow(marker);
        });

        confirmBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            var email = emailInput ? emailInput.value.trim() : '';
            if (!email || email.indexOf('@') < 1) {
                emailInput.style.borderColor = '#d63384';
                return;
            }

            if (unionLabel) {
                // Step 2: ask about union.
                showUnionStep(section, marker, ctx, email, unionLabel);
            } else {
                // No union — submit immediately.
                submitSubscription(section, marker, ctx, email, true, false);
            }
        });
    }

    /**
     * Step 2: Ask about union subscription (email already captured).
     */
    function showUnionStep(section, marker, ctx, email, unionLabel) {
        var html = '<div class="pim-email-form">';
        html += '<div class="pim-attendance-divider"></div>';
        html += '<p class="pim-email-prompt">' + escapeHtml((ctx.i18n.alsoSubscribe || 'Also subscribe to %s\'s newsletter.').replace('%s', unionLabel)) + '</p>';
        html += '<div class="pim-email-actions">';
        html += '<button type="button" class="pim-union-yes-btn">' + escapeHtml(ctx.i18n.yesPlease || 'Yes, sign me up') + '</button>';
        html += '<button type="button" class="pim-union-no-btn">' + escapeHtml(ctx.i18n.noThanks || 'No thanks') + '</button>';
        html += '</div>';
        html += '</div>';

        section.innerHTML = html;
        pimPopupReflow(marker);

        section.querySelector('.pim-union-yes-btn').addEventListener('click', function (e) {
            e.stopPropagation();
            submitSubscription(section, marker, ctx, email, true, true);
        });

        section.querySelector('.pim-union-no-btn').addEventListener('click', function (e) {
            e.stopPropagation();
            submitSubscription(section, marker, ctx, email, true, false);
        });
    }

    /**
     * Send the subscription AJAX request and show result.
     */
    function submitSubscription(section, marker, ctx, email, notifyProtest, notifyUnion) {
        section.innerHTML = '<div class="pim-email-form"><p style="text-align:center;">…</p></div>';

        var formData = new FormData();
        formData.append('action', 'pim_submit_email');
        formData.append('post_id', ctx.postId);
        formData.append('email', email);
        formData.append('notify_protest', notifyProtest ? '1' : '0');
        formData.append('notify_union', notifyUnion ? '1' : '0');
        formData.append('_nonce', ctx.nonce);

        fetch(ctx.ajaxUrl, { method: 'POST', body: formData, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (resp) {
                if (resp.success) {
                    section.innerHTML = '<div class="pim-email-confirmed">✅ ' + escapeHtml(ctx.i18n.notified || 'You will be notified of any updates.') + '</div>';
                } else {
                    section.innerHTML = '<div class="pim-email-confirmed">⚠️ ' + escapeHtml(resp.data && resp.data.reason ? resp.data.reason : 'Error') + '</div>';
                }
                pimPopupReflow(marker);
            })
            .catch(function () {
                section.innerHTML = '<div class="pim-email-confirmed">⚠️ Error</div>';
                pimPopupReflow(marker);
            });
    }

    /**
     * Recalculate popup layout without re-rendering from cached content.
     */
    function pimPopupReflow(marker) {
        try {
            var p = marker.getPopup();
            p._updateLayout();
            p._updatePosition();
        } catch (e) {}
    }

    function formatDistance(meters) {
        if (meters >= 1000) {
            return (meters / 1000).toFixed(1) + ' km';
        }
        return Math.round(meters) + ' m';
    }

    function formatDuration(seconds) {
        var mins = Math.round(seconds / 60);
        if (mins >= 60) {
            var h = Math.floor(mins / 60);
            var m = mins % 60;
            return h + 'h' + (m > 0 ? ' ' + m + 'min' : '');
        }
        return mins + ' min';
    }

    function ucwords(str) {
        return str.replace(/\b\w/g, function (l) { return l.toUpperCase(); });
    }

    function escapeHtml(str) {
        if (!str) return '';
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

    /* ======================================================================
       Paris Metro Overlay (IDFM Open Data)
       ====================================================================== */

    var METRO_LINE_COLORS = {
        '1': '#FFCE00', '2': '#0064B0', '3': '#9F9825', '3B': '#98D4E2',
        '4': '#C04191', '5': '#F28E42', '6': '#83C491', '7': '#F3A4BA',
        '7B': '#83C491', '8': '#CEADD2', '9': '#D5C900', '10': '#E3B32A',
        '11': '#8D5E2A', '12': '#00814F', '13': '#98D4E2', '14': '#662483',
        '15': '#B90845', '16': '#F3A4BA', '17': '#D5C900', '18': '#00A88F',
    };

    function showMetroLayers(map, state) {
        if (state.linesLayer) state.linesLayer.addTo(map);
        if (state.stationsLayer) state.stationsLayer.addTo(map);
    }

    function hideMetroLayers(map, state) {
        if (state.linesLayer) map.removeLayer(state.linesLayer);
        if (state.stationsLayer) map.removeLayer(state.stationsLayer);
    }

    function loadMetroData(map, state) {
        if (state.dataLoaded) {
            showMetroLayers(map, state);
            return;
        }

        var baseUrl = 'https://data.iledefrance-mobilites.fr/api/explore/v2.1/catalog/datasets/';

        var linesUrl = baseUrl
            + 'traces-du-reseau-ferre-idf/records'
            + '?limit=100&offset=0'
            + '&select=geo_shape,mode,indice_lig,colourweb_hexa,res_com'
            + '&where=mode%3D%22METRO%22';

        var stationsUrl = baseUrl
            + 'emplacement-des-gares-idf/records'
            + '?limit=100&offset=0'
            + '&select=geo_point_2d,nom_gares,mode,indice_lig'
            + '&where=mode%3D%22METRO%22';

        var allLines = [];
        var allStations = [];

        function fetchAllPages(url, accumulator) {
            return fetch(url).then(function (r) { return r.json(); }).then(function (data) {
                if (data.results) {
                    accumulator.push.apply(accumulator, data.results);
                }
                if (data.results && data.results.length === 100 && accumulator.length < data.total_count) {
                    var nextUrl = url.replace(/offset=\d+/, 'offset=' + accumulator.length);
                    return fetchAllPages(nextUrl, accumulator);
                }
                return accumulator;
            });
        }

        Promise.all([
            fetchAllPages(linesUrl, allLines),
            fetchAllPages(stationsUrl, allStations),
        ]).then(function (results) {
            var lines = results[0];
            var stations = results[1];

            state.dataLoaded = true;

            // Build line GeoJSON features.
            var lineFeatures = [];
            for (var i = 0; i < lines.length; i++) {
                var rec = lines[i];
                if (!rec.geo_shape || !rec.geo_shape.geometry) continue;
                var lineId = (rec.indice_lig || '').toUpperCase().replace('BIS', 'B');
                lineFeatures.push({
                    type: 'Feature',
                    geometry: rec.geo_shape.geometry,
                    properties: {
                        line: lineId,
                        color: METRO_LINE_COLORS[lineId] || (rec.colourweb_hexa ? '#' + rec.colourweb_hexa : '#003CA6'),
                        name: rec.res_com || '',
                    },
                });
            }

            state.linesLayer = L.geoJSON({ type: 'FeatureCollection', features: lineFeatures }, {
                pane: 'metroPane',
                style: function (feature) {
                    return {
                        color: feature.properties.color,
                        weight: 4,
                        opacity: 0.85,
                        lineJoin: 'round',
                        lineCap: 'round',
                    };
                },
                onEachFeature: function (feature, layer) {
                    var p = feature.properties;
                    layer.bindTooltip(
                        '<strong>Métro ' + escapeHtml(p.line) + '</strong>',
                        { sticky: true, className: 'pim-metro-tooltip' }
                    );
                },
                attribution: '&copy; <a href="https://data.iledefrance-mobilites.fr/">IDFM</a>',
            });

            // Build station markers.
            var stationMap = {};
            for (var j = 0; j < stations.length; j++) {
                var st = stations[j];
                if (!st.geo_point_2d || !st.nom_gares) continue;
                var key = st.nom_gares;
                if (!stationMap[key]) {
                    stationMap[key] = { lat: st.geo_point_2d.lat, lon: st.geo_point_2d.lon, lines: [] };
                }
                var lid = (st.indice_lig || '').toUpperCase().replace('BIS', 'B');
                if (stationMap[key].lines.indexOf(lid) === -1) {
                    stationMap[key].lines.push(lid);
                }
            }

            state.stationsLayer = L.layerGroup([], { pane: 'metroStationPane' });
            var names = Object.keys(stationMap);
            for (var k = 0; k < names.length; k++) {
                var info = stationMap[names[k]];
                var firstLine = info.lines[0] || '';
                var mColor = METRO_LINE_COLORS[firstLine] || '#003CA6';
                var marker = L.circleMarker(
                    [info.lat, info.lon],
                    {
                        pane: 'metroStationPane',
                        radius: 4,
                        color: '#fff',
                        weight: 1.5,
                        fillColor: mColor,
                        fillOpacity: 0.9,
                    }
                );
                marker.bindTooltip(
                    '<strong>' + escapeHtml(names[k]) + '</strong><br>Métro ' + info.lines.join(', '),
                    { className: 'pim-metro-tooltip' }
                );
                state.stationsLayer.addLayer(marker);
            }

            if (state.visible) {
                showMetroLayers(map, state);
            }
        }).catch(function (err) {
            console.warn('PIM: failed to load metro data from IDFM', err);
        });
    }

})();
