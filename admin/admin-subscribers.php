<?php
/**
 * Admin subscriber management page.
 *
 * Loaded as a submenu page under the itinerary CPT menu.
 *
 * @package Protest_Itinerary_Map
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PIM_Admin_Subscribers {

    /**
     * Hook into WordPress.
     */
    public static function init() {
        add_action( 'admin_menu', array( __CLASS__, 'add_submenu' ) );
        add_action( 'admin_init', array( __CLASS__, 'handle_actions' ) );
    }

    /**
     * Add a submenu page under Protest Itineraries.
     */
    public static function add_submenu() {
        add_submenu_page(
            'edit.php?post_type=' . PIM_Post_Type::POST_TYPE,
            __( 'Subscribers', 'protest-itinerary-map' ),
            __( 'Subscribers', 'protest-itinerary-map' ),
            'manage_options',
            'pim-subscribers',
            array( __CLASS__, 'render_page' )
        );
    }

    /**
     * Handle admin actions (CSV export, purge, send notification).
     */
    public static function handle_actions() {
        if ( ! isset( $_GET['page'] ) || 'pim-subscribers' !== $_GET['page'] ) {
            return;
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        // CSV Export.
        if ( isset( $_GET['pim_action'] ) && 'export_csv' === $_GET['pim_action'] ) {
            check_admin_referer( 'pim_export_csv' );
            $post_id = isset( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : 0;
            if ( ! $post_id ) {
                return;
            }
            self::export_csv( $post_id );
            exit;
        }

        // Purge all.
        if ( isset( $_POST['pim_action'] ) && 'purge_all' === $_POST['pim_action'] ) {
            check_admin_referer( 'pim_purge_subscribers' );
            $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
            if ( $post_id ) {
                PIM_Subscriber::delete_all_for_post( $post_id );
                add_settings_error( 'pim_subscribers', 'purged', __( 'All subscribers have been removed.', 'protest-itinerary-map' ), 'updated' );
            }
        }

        // Send notification.
        if ( isset( $_POST['pim_action'] ) && 'send_notification' === $_POST['pim_action'] ) {
            check_admin_referer( 'pim_send_notification' );
            $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
            $message = isset( $_POST['pim_notification_message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['pim_notification_message'] ) ) : '';
            if ( $post_id && $message ) {
                $protest_count = PIM_Subscriber::count_subscribers( $post_id, true, 'protest' );
                $sent = PIM_Notification::send_protest_update( $post_id, $message );
                if ( $sent > 0 ) {
                    add_settings_error( 'pim_subscribers', 'sent',
                        sprintf(
                            /* translators: %d: number of emails */
                            _n( 'Notification sent to %d subscriber.', 'Notification sent to %d subscribers.', $sent, 'protest-itinerary-map' ),
                            $sent
                        ),
                        'updated'
                    );
                } elseif ( $protest_count === 0 ) {
                    add_settings_error( 'pim_subscribers', 'sent',
                        __( 'No subscribers with protest notifications enabled.', 'protest-itinerary-map' ),
                        'error'
                    );
                } else {
                    add_settings_error( 'pim_subscribers', 'sent',
                        __( 'Email sending failed. Please check your server mail configuration.', 'protest-itinerary-map' ),
                        'error'
                    );
                }
            }
        }
    }

    /**
     * Export CSV of confirmed subscribers for a post.
     *
     * @param int $post_id Post ID.
     */
    private static function export_csv( $post_id ) {
        $event_name = get_post_meta( $post_id, '_pim_event_name', true );
        $filename   = sanitize_file_name( 'subscribers-' . ( $event_name ?: $post_id ) . '-' . gmdate( 'Y-m-d' ) ) . '.csv';

        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );

        $output = fopen( 'php://output', 'w' );
        fputcsv( $output, array( 'Email', 'Notify Protest', 'Notify Union', 'Confirmed', 'Date' ) );

        $subscribers = PIM_Subscriber::get_for_export( $post_id );
        foreach ( $subscribers as $sub ) {
            fputcsv( $output, array(
                $sub->email,
                $sub->notify_protest ? 'Yes' : 'No',
                $sub->notify_union ? 'Yes' : 'No',
                $sub->confirmed ? 'Yes' : 'No',
                $sub->created_at,
            ) );
        }

        fclose( $output );
    }

    /**
     * Render the subscribers page.
     */
    public static function render_page() {
        $post_id = isset( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : 0;

        // Get all itineraries for the dropdown.
        $itineraries = get_posts( array(
            'post_type'   => PIM_Post_Type::POST_TYPE,
            'post_status' => 'any',
            'numberposts' => 100,
            'orderby'     => 'date',
            'order'       => 'DESC',
        ) );
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Subscribers', 'protest-itinerary-map' ); ?></h1>

            <?php settings_errors( 'pim_subscribers' ); ?>

            <form method="get" style="margin-bottom: 16px;">
                <input type="hidden" name="post_type" value="<?php echo esc_attr( PIM_Post_Type::POST_TYPE ); ?>">
                <input type="hidden" name="page" value="pim-subscribers">
                <label for="pim-select-itinerary"><strong><?php esc_html_e( 'Select itinerary:', 'protest-itinerary-map' ); ?></strong></label>
                <select name="post_id" id="pim-select-itinerary">
                    <option value=""><?php esc_html_e( '— Choose —', 'protest-itinerary-map' ); ?></option>
                    <?php foreach ( $itineraries as $it ) : ?>
                        <option value="<?php echo esc_attr( $it->ID ); ?>" <?php selected( $post_id, $it->ID ); ?>>
                            <?php echo esc_html( $it->post_title ? $it->post_title : '#' . $it->ID ); ?>
                            (<?php echo esc_html( get_post_meta( $it->ID, '_pim_event_name', true ) ); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php submit_button( __( 'View', 'protest-itinerary-map' ), 'secondary', '', false ); ?>
            </form>

            <?php if ( $post_id ) : ?>
                <?php
                $subscribers     = PIM_Subscriber::get_subscribers( $post_id, 'all' );
                $sub_count       = count( $subscribers );
                $export_url      = wp_nonce_url(
                    add_query_arg( array(
                        'post_type'  => PIM_Post_Type::POST_TYPE,
                        'page'       => 'pim-subscribers',
                        'post_id'    => $post_id,
                        'pim_action' => 'export_csv',
                    ), admin_url( 'edit.php' ) ),
                    'pim_export_csv'
                );
                ?>

                <h2>
                    <?php
                    printf(
                        /* translators: %d: number of subscribers */
                        esc_html( _n( '%d subscriber', '%d subscribers', $sub_count, 'protest-itinerary-map' ) ),
                        $sub_count
                    );
                    ?>
                </h2>

                <?php if ( $sub_count > 0 ) : ?>
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Email', 'protest-itinerary-map' ); ?></th>
                            <th><?php esc_html_e( 'Protest', 'protest-itinerary-map' ); ?></th>
                            <th><?php esc_html_e( 'Union', 'protest-itinerary-map' ); ?></th>
                            <th><?php esc_html_e( 'Confirmed', 'protest-itinerary-map' ); ?></th>
                            <th><?php esc_html_e( 'Date', 'protest-itinerary-map' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $subscribers as $sub ) : ?>
                        <tr>
                            <td><?php echo esc_html( $sub->email ); ?></td>
                            <td><?php echo $sub->notify_protest ? '✓' : '—'; ?></td>
                            <td><?php echo $sub->notify_union ? '✓' : '—'; ?></td>
                            <td><?php echo $sub->confirmed ? '✓' : '⏳'; ?></td>
                            <td><?php echo esc_html( $sub->created_at ); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>

                <div style="margin-top: 16px; display: flex; gap: 12px; align-items: flex-start;">
                    <?php if ( $sub_count > 0 ) : ?>
                    <a href="<?php echo esc_url( $export_url ); ?>" class="button">
                        <?php esc_html_e( 'Export CSV', 'protest-itinerary-map' ); ?>
                    </a>

                    <form method="post" style="display: inline;" onsubmit="return confirm('<?php echo esc_js( __( 'Delete ALL subscribers for this itinerary?', 'protest-itinerary-map' ) ); ?>');">
                        <?php wp_nonce_field( 'pim_purge_subscribers' ); ?>
                        <input type="hidden" name="post_id" value="<?php echo esc_attr( $post_id ); ?>">
                        <input type="hidden" name="pim_action" value="purge_all">
                        <button type="submit" class="button button-link-delete">
                            <?php esc_html_e( 'Purge All Subscribers', 'protest-itinerary-map' ); ?>
                        </button>
                    </form>
                    <?php endif; ?>
                </div>

                <?php
                $confirmed_protest = PIM_Subscriber::count_subscribers( $post_id, true, 'protest' );
                if ( $confirmed_protest > 0 ) : ?>
                <div style="margin-top: 24px; max-width: 600px;">
                    <h3><?php esc_html_e( 'Send Notification', 'protest-itinerary-map' ); ?></h3>
                    <form method="post">
                        <?php wp_nonce_field( 'pim_send_notification' ); ?>
                        <input type="hidden" name="post_id" value="<?php echo esc_attr( $post_id ); ?>">
                        <input type="hidden" name="pim_action" value="send_notification">
                        <textarea name="pim_notification_message" rows="5" class="large-text" placeholder="<?php esc_attr_e( 'Write your update message here…', 'protest-itinerary-map' ); ?>" required></textarea>
                        <p class="description">
                            <?php
                            printf(
                                /* translators: %d: number of subscribers */
                                esc_html( _n( 'Will be sent to %d confirmed protest subscriber.', 'Will be sent to %d confirmed protest subscribers.', $confirmed_protest, 'protest-itinerary-map' ) ),
                                $confirmed_protest
                            );
                            ?>
                        </p>
                        <?php submit_button( __( 'Send Notification', 'protest-itinerary-map' ), 'primary', '', false ); ?>
                    </form>
                </div>
                <?php endif; ?>

            <?php endif; ?>
        </div>
        <?php
    }
}
