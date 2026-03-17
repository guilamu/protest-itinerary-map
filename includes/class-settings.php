<?php
/**
 * Plugin settings page.
 *
 * @package Protest_Itinerary_Map
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PIM_Settings {

    /**
     * Hook into WordPress.
     */
    public static function init() {
        add_action( 'admin_menu', array( __CLASS__, 'add_menu_page' ) );
        add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
    }

    /**
     * Add the settings page under the Protest Itinerary CPT menu.
     */
    public static function add_menu_page() {
        add_submenu_page(
            'edit.php?post_type=protest_itinerary',
            __( 'Protest Map Settings', 'protest-itinerary-map' ),
            __( 'Settings', 'protest-itinerary-map' ),
            'manage_options',
            'protest-itinerary-map',
            array( __CLASS__, 'render_page' )
        );
    }

    /**
     * Register plugin settings.
     */
    public static function register_settings() {
        register_setting( 'pim_settings', 'pim_ors_api_key', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '',
        ) );

        register_setting( 'pim_settings', 'pim_default_lat', array(
            'type'              => 'number',
            'sanitize_callback' => 'floatval',
            'default'           => 48.8566,
        ) );

        register_setting( 'pim_settings', 'pim_default_lng', array(
            'type'              => 'number',
            'sanitize_callback' => 'floatval',
            'default'           => 2.3522,
        ) );

        register_setting( 'pim_settings', 'pim_default_zoom', array(
            'type'              => 'integer',
            'sanitize_callback' => 'absint',
            'default'           => 13,
        ) );

        register_setting( 'pim_settings', 'pim_default_address', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '',
        ) );

        register_setting( 'pim_settings', 'pim_embed_global', array(
            'type'              => 'boolean',
            'sanitize_callback' => 'rest_sanitize_boolean',
            'default'           => false,
        ) );

        register_setting( 'pim_settings', 'pim_union_name', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '',
        ) );

        // Section: API.
        add_settings_section(
            'pim_section_api',
            __( 'API Settings', 'protest-itinerary-map' ),
            function () {
                echo '<p>' . esc_html__( 'Configure the OpenRouteService API key used for route calculations.', 'protest-itinerary-map' ) . '</p>';
            },
            'protest-itinerary-map'
        );

        add_settings_field(
            'pim_ors_api_key',
            __( 'ORS API Key', 'protest-itinerary-map' ),
            array( __CLASS__, 'render_api_key_field' ),
            'protest-itinerary-map',
            'pim_section_api'
        );

        // Section: Map defaults.
        add_settings_section(
            'pim_section_map',
            __( 'Map Defaults', 'protest-itinerary-map' ),
            function () {
                echo '<p>' . esc_html__( 'Default center for the admin map builder when no waypoints exist. Type an address below.', 'protest-itinerary-map' ) . '</p>';
            },
            'protest-itinerary-map'
        );

        add_settings_field(
            'pim_default_address',
            __( 'Default Location', 'protest-itinerary-map' ),
            array( __CLASS__, 'render_address_field' ),
            'protest-itinerary-map',
            'pim_section_map'
        );

        // Section: Embed.
        add_settings_section(
            'pim_section_embed',
            __( 'Embed Settings', 'protest-itinerary-map' ),
            function () {
                echo '<p>' . esc_html__( 'Global toggle for iframe embedding. Individual itineraries can also be toggled independently.', 'protest-itinerary-map' ) . '</p>';
            },
            'protest-itinerary-map'
        );

        add_settings_field(
            'pim_embed_global',
            __( 'Enable Iframe Embedding', 'protest-itinerary-map' ),
            function () {
                $val = get_option( 'pim_embed_global', false );
                printf(
                    '<label><input type="checkbox" name="pim_embed_global" value="1" %s> %s</label>',
                    checked( $val, true, false ),
                    esc_html__( 'Allow itineraries to be embedded via iframe', 'protest-itinerary-map' )
                );
            },
            'protest-itinerary-map',
            'pim_section_embed'
        );

        // Section: Union.
        add_settings_section(
            'pim_section_union',
            __( 'Union', 'protest-itinerary-map' ),
            function () {
                echo '<p>' . esc_html__( 'Default union name used when creating new itineraries. Can be overridden per itinerary.', 'protest-itinerary-map' ) . '</p>';
            },
            'protest-itinerary-map'
        );

        add_settings_field(
            'pim_union_name',
            __( 'Union Name', 'protest-itinerary-map' ),
            function () {
                $val = get_option( 'pim_union_name', '' );
                printf(
                    '<input type="text" name="pim_union_name" value="%s" class="regular-text" placeholder="%s">',
                    esc_attr( $val ),
                    esc_attr__( 'e.g. CGT, SNUDI FO 93', 'protest-itinerary-map' )
                );
            },
            'protest-itinerary-map',
            'pim_section_union'
        );
    }

    /**
     * Render the API key field with a test button.
     */
    public static function render_api_key_field() {
        $val = get_option( 'pim_ors_api_key', '' );
        ?>
        <input type="text" name="pim_ors_api_key" value="<?php echo esc_attr( $val ); ?>" class="regular-text" id="pim-ors-api-key">
        <button type="button" class="button button-secondary" id="pim-test-api-key">
            <?php esc_html_e( 'Test API Key', 'protest-itinerary-map' ); ?>
        </button>
        <span id="pim-api-key-result" style="margin-left: 10px;"></span>
        <p class="description">
            <?php
            printf(
                /* translators: %s: ORS signup URL */
                esc_html__( 'Get a free API key at %s', 'protest-itinerary-map' ),
                '<a href="https://openrouteservice.org/dev/#/signup" target="_blank" rel="noopener">openrouteservice.org</a>'
            );
            ?>
        </p>
        <script>
        (function(){
            var btn = document.getElementById('pim-test-api-key');
            var result = document.getElementById('pim-api-key-result');
            if (!btn) return;
            btn.addEventListener('click', function(){
                var key = document.getElementById('pim-ors-api-key').value.trim();
                if (!key) { result.textContent = '<?php echo esc_js( __( 'Enter an API key first.', 'protest-itinerary-map' ) ); ?>'; return; }
                result.textContent = '<?php echo esc_js( __( 'Testing…', 'protest-itinerary-map' ) ); ?>';
                btn.disabled = true;
                var fd = new FormData();
                fd.append('action', 'pim_test_api_key');
                fd.append('_nonce', '<?php echo esc_js( wp_create_nonce( 'pim_admin_nonce' ) ); ?>');
                fd.append('api_key', key);
                fetch(ajaxurl, { method: 'POST', body: fd })
                    .then(function(r){ return r.json(); })
                    .then(function(d){
                        result.textContent = d.success ? '✅ ' + d.data : '❌ ' + d.data;
                        result.style.color = d.success ? 'green' : 'red';
                        btn.disabled = false;
                    })
                    .catch(function(){
                        result.textContent = '❌ <?php echo esc_js( __( 'Request failed.', 'protest-itinerary-map' ) ); ?>';
                        result.style.color = 'red';
                        btn.disabled = false;
                    });
            });
        })();
        </script>
        <?php
    }

    /**
     * Render the address autocomplete field for map defaults.
     */
    public static function render_address_field() {
        $address = get_option( 'pim_default_address', '' );
        $lat     = get_option( 'pim_default_lat', 48.8566 );
        $lng     = get_option( 'pim_default_lng', 2.3522 );
        $zoom    = get_option( 'pim_default_zoom', 13 );
        ?>
        <div style="position:relative;">
            <input type="text" id="pim-default-address" name="pim_default_address" value="<?php echo esc_attr( $address ); ?>" class="regular-text" autocomplete="off" placeholder="<?php esc_attr_e( 'e.g. Place de la République, Paris', 'protest-itinerary-map' ); ?>">
            <div id="pim-address-results" style="display:none; position:absolute; z-index:100; background:#fff; border:1px solid #ddd; max-height:200px; overflow-y:auto; width:100%;"></div>
        </div>
        <input type="hidden" name="pim_default_lat" id="pim-default-lat" value="<?php echo esc_attr( $lat ); ?>">
        <input type="hidden" name="pim_default_lng" id="pim-default-lng" value="<?php echo esc_attr( $lng ); ?>">
        <input type="hidden" name="pim_default_zoom" id="pim-default-zoom" value="<?php echo esc_attr( $zoom ); ?>">
        <p class="description">
            <?php
            if ( $address ) {
                printf(
                    /* translators: %1$s: latitude, %2$s: longitude */
                    esc_html__( 'Current: %1$s, %2$s', 'protest-itinerary-map' ),
                    esc_html( number_format( (float) $lat, 5 ) ),
                    esc_html( number_format( (float) $lng, 5 ) )
                );
            } else {
                esc_html_e( 'Default: Paris (48.8566, 2.3522)', 'protest-itinerary-map' );
            }
            ?>
        </p>
        <script>
        (function(){
            var input = document.getElementById('pim-default-address');
            var results = document.getElementById('pim-address-results');
            var latField = document.getElementById('pim-default-lat');
            var lngField = document.getElementById('pim-default-lng');
            var zoomField = document.getElementById('pim-default-zoom');
            var timer = null;

            input.addEventListener('input', function(){
                var q = input.value.trim();
                if (q.length < 3) { results.style.display = 'none'; results.innerHTML = ''; return; }
                if (timer) clearTimeout(timer);
                timer = setTimeout(function(){ searchAddress(q); }, 400);
            });

            input.addEventListener('blur', function(){
                setTimeout(function(){ results.style.display = 'none'; }, 200);
            });

            function searchAddress(q) {
                var fd = new FormData();
                fd.append('action', 'pim_geocode_search');
                fd.append('_nonce', '<?php echo esc_js( wp_create_nonce( 'pim_admin_nonce' ) ); ?>');
                var url = ajaxurl + '?action=pim_geocode_search&_nonce=<?php echo esc_js( wp_create_nonce( 'pim_admin_nonce' ) ); ?>&q=' + encodeURIComponent(q);
                fetch(url)
                    .then(function(r){ return r.json(); })
                    .then(function(d){
                        results.innerHTML = '';
                        if (!d.success || !d.data || !d.data.length) {
                            results.innerHTML = '<div style="padding:6px 10px;color:#999;"><?php echo esc_js( __( 'No results found.', 'protest-itinerary-map' ) ); ?></div>';
                            results.style.display = 'block';
                            return;
                        }
                        d.data.forEach(function(item){
                            var div = document.createElement('div');
                            div.style.cssText = 'padding:6px 10px;cursor:pointer;border-bottom:1px solid #eee;';
                            div.textContent = item.display_name;
                            div.addEventListener('mouseenter', function(){ div.style.background = '#f0f0f0'; });
                            div.addEventListener('mouseleave', function(){ div.style.background = ''; });
                            div.addEventListener('mousedown', function(e){
                                e.preventDefault();
                                input.value = item.display_name;
                                latField.value = item.lat;
                                lngField.value = item.lon;
                                zoomField.value = 13;
                                results.style.display = 'none';
                            });
                            results.appendChild(div);
                        });
                        results.style.display = 'block';
                    })
                    .catch(function(){
                        results.style.display = 'none';
                    });
            }
        })();
        </script>
        <?php
    }

    /**
     * Render the settings page.
     */
    public static function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Protest Itinerary Map Settings', 'protest-itinerary-map' ); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields( 'pim_settings' );
                do_settings_sections( 'protest-itinerary-map' );
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }
}
