<?php
/**
 * Meta box logic and save handler for protest itineraries.
 *
 * @package Protest_Itinerary_Map
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PIM_Meta_Box {

    const NONCE_ACTION = 'pim_save_itinerary';
    const NONCE_FIELD  = 'pim_itinerary_nonce';

    /**
     * Hook into WordPress.
     */
    public static function init() {
        add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_boxes' ) );
        add_action( 'save_post_' . PIM_Post_Type::POST_TYPE, array( __CLASS__, 'save_post' ), 10, 2 );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );
    }

    /**
     * Register meta boxes.
     */
    public static function add_meta_boxes() {
        add_meta_box(
            'pim-itinerary-info',
            __( 'Itinerary Info', 'protest-itinerary-map' ),
            array( __CLASS__, 'render_info_box' ),
            PIM_Post_Type::POST_TYPE,
            'normal',
            'high'
        );

        add_meta_box(
            'pim-map-builder',
            __( 'Map Builder', 'protest-itinerary-map' ),
            array( __CLASS__, 'render_map_builder_box' ),
            PIM_Post_Type::POST_TYPE,
            'normal',
            'high'
        );

        add_meta_box(
            'pim-embed-options',
            __( 'Embed Options', 'protest-itinerary-map' ),
            array( __CLASS__, 'render_embed_box' ),
            PIM_Post_Type::POST_TYPE,
            'side',
            'default'
        );

        add_meta_box(
            'pim-attendance',
            __( 'Attendance & Notifications', 'protest-itinerary-map' ),
            array( __CLASS__, 'render_attendance_box' ),
            PIM_Post_Type::POST_TYPE,
            'side',
            'default'
        );
    }

    /**
     * Enqueue admin assets on the itinerary edit screen.
     *
     * @param string $hook Current admin page hook.
     */
    public static function enqueue_admin_assets( $hook ) {
        if ( 'post.php' !== $hook && 'post-new.php' !== $hook ) {
            return;
        }

        $screen = get_current_screen();
        if ( ! $screen || PIM_Post_Type::POST_TYPE !== $screen->post_type ) {
            return;
        }

        // Leaflet CSS & JS from CDN.
        wp_enqueue_style(
            'leaflet',
            'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css',
            array(),
            '1.9.4'
        );
        wp_enqueue_script(
            'leaflet',
            'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js',
            array(),
            '1.9.4',
            true
        );

        // Admin CSS.
        wp_enqueue_style(
            'pim-admin',
            PIM_PLUGIN_URL . 'admin/admin.css',
            array( 'leaflet' ),
            PIM_VERSION
        );

        // Admin JS.
        wp_enqueue_script(
            'pim-admin',
            PIM_PLUGIN_URL . 'admin/admin.js',
            array( 'leaflet', 'jquery', 'jquery-ui-sortable' ),
            PIM_VERSION,
            true
        );

        global $post;
        $waypoints     = get_post_meta( $post->ID, '_pim_waypoints', true );
        $route_geojson = get_post_meta( $post->ID, '_pim_route_geojson', true );

        wp_localize_script( 'pim-admin', 'pimAdmin', array(
            'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
            'nonce'          => wp_create_nonce( 'pim_admin_nonce' ),
            'defaultLat'     => floatval( get_option( 'pim_default_lat', 48.8566 ) ),
            'defaultLng'     => floatval( get_option( 'pim_default_lng', 2.3522 ) ),
            'defaultZoom'    => intval( get_option( 'pim_default_zoom', 13 ) ),
            'waypoints'      => $waypoints ? json_decode( $waypoints, true ) : array(),
            'routeGeoJSON'   => $route_geojson ? json_decode( $route_geojson, true ) : null,
            'waypointTypes'  => PIM_Post_Type::WAYPOINT_TYPES,
            'iconsUrl'       => PIM_PLUGIN_URL . 'assets/icons/',
            'i18n'           => array(
                'confirmRemove'    => __( 'Remove this waypoint?', 'protest-itinerary-map' ),
                'routeError'       => __( 'Route preview failed. The route will be retried on save.', 'protest-itinerary-map' ),
                'searchPlaceholder' => __( 'Search address…', 'protest-itinerary-map' ),
                'noResults'        => __( 'No results found.', 'protest-itinerary-map' ),
                'maxWaypoints'     => __( 'Maximum 50 waypoints allowed (ORS limit).', 'protest-itinerary-map' ),
            ),
        ) );
    }

    /**
     * Render the Itinerary Info meta box.
     *
     * @param WP_Post $post Current post.
     */
    public static function render_info_box( $post ) {
        wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD );
        include PIM_PLUGIN_DIR . 'admin/admin-meta-box.php';
    }

    /**
     * Render the Map Builder meta box.
     *
     * @param WP_Post $post Current post.
     */
    public static function render_map_builder_box( $post ) {
        $route_summary = get_post_meta( $post->ID, '_pim_route_summary', true );
        $route_legs    = get_post_meta( $post->ID, '_pim_route_legs', true );
        $cached_at     = get_post_meta( $post->ID, '_pim_route_cached_at', true );
        $route_error   = get_post_meta( $post->ID, '_pim_route_error', true );
        ?>
        <div id="pim-map-builder-wrap">
            <div id="pim-admin-toolbar">
                <input type="text" id="pim-search-input" placeholder="<?php esc_attr_e( 'Search address…', 'protest-itinerary-map' ); ?>" autocomplete="off">
                <div id="pim-search-results"></div>
                <p class="description"><?php esc_html_e( 'Click the map to add a waypoint, or use the search bar above.', 'protest-itinerary-map' ); ?></p>
            </div>
            <div id="pim-admin-map" style="height: 450px; margin-bottom: 15px;"></div>

            <?php if ( $route_error ) : ?>
                <div class="notice notice-warning inline"><p><?php echo esc_html( $route_error ); ?></p></div>
            <?php endif; ?>

            <div id="pim-waypoints-panel">
                <h4><?php esc_html_e( 'Waypoints', 'protest-itinerary-map' ); ?></h4>
                <div id="pim-waypoints-list"></div>
            </div>

            <div id="pim-route-summary">
                <?php if ( $route_summary ) :
                    $summary = json_decode( $route_summary, true );
                    if ( $summary ) : ?>
                        <p>
                            <strong><?php esc_html_e( 'Total distance:', 'protest-itinerary-map' ); ?></strong>
                            <?php echo esc_html( round( $summary['distance'] / 1000, 2 ) ); ?> km
                            &mdash;
                            <strong><?php esc_html_e( 'Total duration:', 'protest-itinerary-map' ); ?></strong>
                            <?php echo esc_html( round( $summary['duration'] / 60 ) ); ?> min
                        </p>
                    <?php endif; ?>
                    <?php if ( $cached_at ) : ?>
                        <p class="description">
                            <?php
                            printf(
                                /* translators: %s: date/time string */
                                esc_html__( 'Route cached at: %s', 'protest-itinerary-map' ),
                                esc_html( $cached_at )
                            );
                            ?>
                        </p>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <textarea name="pim_waypoints_json" id="pim-waypoints-json" style="display:none;"></textarea>
        </div>
        <?php
    }

    /**
     * Render the Embed Options meta box.
     *
     * @param WP_Post $post Current post.
     */
    public static function render_embed_box( $post ) {
        $embed_enabled = (bool) get_post_meta( $post->ID, '_pim_embed_enabled', true );
        $global_embed  = (bool) get_option( 'pim_embed_global', false );
        $embed_url     = home_url( '/protest-map/embed/' . $post->ID . '/' );
        ?>
        <label>
            <input type="checkbox" name="pim_embed_enabled" value="1" <?php checked( $embed_enabled ); ?>
                   <?php disabled( ! $global_embed ); ?>>
            <?php esc_html_e( 'Enable iframe embed for this itinerary', 'protest-itinerary-map' ); ?>
        </label>
        <?php if ( ! $global_embed ) : ?>
            <p class="description">
                <?php
                printf(
                    /* translators: %s: settings page URL */
                    esc_html__( 'Global embedding is disabled. Enable it in %s first.', 'protest-itinerary-map' ),
                    '<a href="' . esc_url( admin_url( 'edit.php?post_type=protest_itinerary&page=protest-itinerary-map' ) ) . '">' . esc_html__( 'Settings', 'protest-itinerary-map' ) . '</a>'
                );
                ?>
            </p>
        <?php endif; ?>

        <div style="margin-top: 8px;">
            <label><?php esc_html_e( 'Shortcode:', 'protest-itinerary-map' ); ?></label>
            <input type="text" readonly class="large-text" value='[protest_map id="<?php echo esc_attr( $post->ID ); ?>"]' onclick="this.select();">
        </div>

        <?php if ( $embed_enabled && $global_embed && 'publish' === $post->post_status ) : ?>
            <div style="margin-top: 10px;">
                <label><?php esc_html_e( 'Embed code:', 'protest-itinerary-map' ); ?></label>
                <textarea readonly rows="3" class="large-text" onclick="this.select();">&lt;iframe src="<?php echo esc_url( $embed_url ); ?>" width="100%" height="550" allowfullscreen&gt;&lt;/iframe&gt;</textarea>
            </div>
        <?php endif; ?>
        <?php
    }

    /**
     * Render the Attendance & Notifications meta box.
     *
     * @param WP_Post $post Current post.
     */
    public static function render_attendance_box( $post ) {
        $attendance_enabled     = get_post_meta( $post->ID, '_pim_attendance_enabled', true );
        $attendance_count       = (int) get_post_meta( $post->ID, '_pim_attendance_count', true );
        $union_label            = get_post_meta( $post->ID, '_pim_union_optin_label', true );
        if ( ! $union_label ) {
            $union_label = get_option( 'pim_union_name', '' );
        }
        $subscriber_count       = PIM_Subscriber::count_subscribers( $post->ID );

        // Default attendance to enabled for new posts.
        if ( '' === $attendance_enabled ) {
            $attendance_enabled = true;
        }
        ?>
        <p>
            <label>
                <input type="checkbox" name="pim_attendance_enabled" value="1" <?php checked( $attendance_enabled ); ?>>
                <?php esc_html_e( 'Enable "I\'ll be there" counter', 'protest-itinerary-map' ); ?>
            </label>
        </p>
        <p>
            <strong><?php esc_html_e( 'Current count:', 'protest-itinerary-map' ); ?></strong>
            <?php echo esc_html( $attendance_count ); ?>
            <button type="button" class="button button-small" id="pim-reset-count" style="margin-left: 8px;">
                <?php esc_html_e( 'Reset', 'protest-itinerary-map' ); ?>
            </button>
            <input type="hidden" name="pim_attendance_count_reset" id="pim-attendance-count-reset" value="0">
        </p>
        <hr>
        <p>
            <label for="pim_union_optin_label"><?php esc_html_e( 'Union name:', 'protest-itinerary-map' ); ?></label>
            <input type="text" id="pim_union_optin_label" name="pim_union_optin_label" value="<?php echo esc_attr( $union_label ); ?>" class="large-text" placeholder="<?php esc_attr_e( 'e.g. CGT, SNUDI FO 93', 'protest-itinerary-map' ); ?>">
        </p>

        <?php if ( $subscriber_count > 0 ) : ?>
        <hr>
        <p class="description">
            <?php
            printf(
                /* translators: %d: number of subscribers */
                esc_html( _n( '%d confirmed subscriber', '%d confirmed subscribers', $subscriber_count, 'protest-itinerary-map' ) ),
                $subscriber_count
            );
            ?>
        </p>
        <?php endif; ?>
        <script>
        (function(){
            var btn = document.getElementById('pim-reset-count');
            if (btn) {
                btn.addEventListener('click', function() {
                    if (confirm('<?php echo esc_js( __( 'Reset the attendance count to 0?', 'protest-itinerary-map' ) ); ?>')) {
                        document.getElementById('pim-attendance-count-reset').value = '1';
                        btn.textContent = '<?php echo esc_js( __( 'Will reset on save', 'protest-itinerary-map' ) ); ?>';
                        btn.disabled = true;
                    }
                });
            }
        })();
        </script>
        <?php
    }

    /**
     * Save itinerary data on post save.
     *
     * @param int     $post_id Post ID.
     * @param WP_Post $post    Post object.
     */
    public static function save_post( $post_id, $post ) {
        // Bail for autosaves and revisions.
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }
        if ( wp_is_post_revision( $post_id ) ) {
            return;
        }

        // Verify nonce.
        if ( ! isset( $_POST[ self::NONCE_FIELD ] ) || ! wp_verify_nonce( $_POST[ self::NONCE_FIELD ], self::NONCE_ACTION ) ) {
            return;
        }

        // Verify capability.
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        // Save text fields.
        $text_fields = array(
            'pim_event_name'        => '_pim_event_name',
            'pim_event_date'        => '_pim_event_date',
            'pim_event_description' => '_pim_event_description',
            'pim_organizer_contact' => '_pim_organizer_contact',
        );

        foreach ( $text_fields as $form_key => $meta_key ) {
            if ( isset( $_POST[ $form_key ] ) ) {
                $value = sanitize_text_field( wp_unslash( $_POST[ $form_key ] ) );
                if ( '_pim_event_date' === $meta_key ) {
                    $value = PIM_Post_Type::sanitize_date( $value );
                }
                update_post_meta( $post_id, $meta_key, $value );
            }
        }

        // Save embed toggle.
        $embed_enabled = isset( $_POST['pim_embed_enabled'] ) ? true : false;
        update_post_meta( $post_id, '_pim_embed_enabled', $embed_enabled );

        // Save attendance fields.
        $attendance_enabled = isset( $_POST['pim_attendance_enabled'] ) ? true : false;
        update_post_meta( $post_id, '_pim_attendance_enabled', $attendance_enabled );

        if ( ! empty( $_POST['pim_attendance_count_reset'] ) ) {
            update_post_meta( $post_id, '_pim_attendance_count', 0 );
            delete_post_meta( $post_id, '_pim_attendance_ip_log' );
        }

        if ( isset( $_POST['pim_union_optin_label'] ) ) {
            update_post_meta( $post_id, '_pim_union_optin_label', sanitize_text_field( wp_unslash( $_POST['pim_union_optin_label'] ) ) );
        }

        // Save waypoints.
        $waypoints_raw = isset( $_POST['pim_waypoints_json'] ) ? wp_unslash( $_POST['pim_waypoints_json'] ) : '[]';
        $waypoints     = PIM_Post_Type::sanitize_waypoints( $waypoints_raw );
        update_post_meta( $post_id, '_pim_waypoints', wp_slash( wp_json_encode( $waypoints ) ) );

        // Clear any previous route error.
        delete_post_meta( $post_id, '_pim_route_error' );

        $wp_count = count( $waypoints );

        if ( $wp_count < 2 ) {
            // Not enough waypoints — clear cached route data.
            delete_post_meta( $post_id, '_pim_route_geojson' );
            delete_post_meta( $post_id, '_pim_route_legs' );
            delete_post_meta( $post_id, '_pim_route_summary' );
            delete_post_meta( $post_id, '_pim_route_cached_at' );
            return;
        }

        if ( $wp_count > 50 ) {
            update_post_meta( $post_id, '_pim_route_error',
                __( 'Too many waypoints (max 50). Route was not calculated.', 'protest-itinerary-map' )
            );
            return;
        }

        // Build coordinates [lng, lat] for ORS.
        $coordinates = array();
        foreach ( $waypoints as $wp_item ) {
            $coordinates[] = array( $wp_item['lng'], $wp_item['lat'] );
        }

        $result = pim_call_ors( $coordinates );

        if ( is_wp_error( $result ) ) {
            update_post_meta( $post_id, '_pim_route_error', $result->get_error_message() );
            return;
        }

        // Extract and cache route data.
        $geojson = $result;
        $summary = isset( $result['features'][0]['properties']['summary'] ) ? $result['features'][0]['properties']['summary'] : null;
        $legs    = array();

        if ( isset( $result['features'][0]['properties']['segments'] ) ) {
            foreach ( $result['features'][0]['properties']['segments'] as $segment ) {
                $legs[] = array(
                    'distance' => $segment['distance'],
                    'duration' => $segment['duration'],
                );
            }
        }

        update_post_meta( $post_id, '_pim_route_geojson', wp_slash( wp_json_encode( $geojson ) ) );
        update_post_meta( $post_id, '_pim_route_legs', wp_slash( wp_json_encode( $legs ) ) );

        if ( $summary ) {
            update_post_meta( $post_id, '_pim_route_summary', wp_slash( wp_json_encode( $summary ) ) );
        }

        update_post_meta( $post_id, '_pim_route_cached_at', gmdate( 'c' ) );
    }
}
