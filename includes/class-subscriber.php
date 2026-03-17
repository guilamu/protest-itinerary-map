<?php
/**
 * Subscriber database table and CRUD operations.
 *
 * @package Protest_Itinerary_Map
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PIM_Subscriber {

    /**
     * Get the full table name.
     *
     * @return string
     */
    public static function table_name() {
        global $wpdb;
        return $wpdb->prefix . 'pim_subscribers';
    }

    /**
     * Create the subscribers table. Called on plugin activation.
     */
    public static function create_table() {
        global $wpdb;

        $table   = self::table_name();
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            post_id          BIGINT UNSIGNED NOT NULL,
            email            VARCHAR(255) NOT NULL,
            notify_protest   TINYINT(1) DEFAULT 0,
            notify_union     TINYINT(1) DEFAULT 0,
            confirmed        TINYINT(1) DEFAULT 0,
            token            VARCHAR(64) NOT NULL,
            created_at       DATETIME NOT NULL,
            INDEX (post_id),
            UNIQUE KEY unique_sub (post_id, email)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    /**
     * Drop the table. Called on uninstall.
     */
    public static function drop_table() {
        global $wpdb;
        $table = self::table_name();
        $wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    }

    /**
     * Generate a cryptographically secure token.
     *
     * @return string 64-char hex string.
     */
    public static function generate_token() {
        return bin2hex( random_bytes( 32 ) );
    }

    /**
     * Insert or update a subscriber (upsert).
     *
     * @param int    $post_id        Itinerary post ID.
     * @param string $email          Subscriber email.
     * @param bool   $notify_protest Protest updates opt-in.
     * @param bool   $notify_union   Union news opt-in.
     * @return array { token: string, is_new: bool }|WP_Error
     */
    public static function subscribe( $post_id, $email, $notify_protest, $notify_union ) {
        global $wpdb;
        $table = self::table_name();

        // Check for existing row.
        $existing = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, confirmed, token FROM {$table} WHERE post_id = %d AND email = %s",
                $post_id,
                $email
            )
        );

        if ( $existing ) {
            if ( $existing->confirmed ) {
                // Already confirmed — update flags in case they changed.
                $wpdb->update(
                    $table,
                    array(
                        'notify_protest' => (int) $notify_protest,
                        'notify_union'   => (int) $notify_union,
                    ),
                    array( 'id' => $existing->id ),
                    array( '%d', '%d' ),
                    array( '%d' )
                );
                return array( 'token' => $existing->token, 'is_new' => false );
            }
            // Update existing unconfirmed row with fresh token and timestamp.
            $token = self::generate_token();
            $wpdb->update(
                $table,
                array(
                    'notify_protest' => (int) $notify_protest,
                    'notify_union'   => (int) $notify_union,
                    'token'          => $token,
                    'created_at'     => current_time( 'mysql', true ),
                ),
                array( 'id' => $existing->id ),
                array( '%d', '%d', '%s', '%s' ),
                array( '%d' )
            );
            return array( 'token' => $token, 'is_new' => true );
        }

        // Insert new row — confirmed immediately (no double opt-in).
        $token = self::generate_token();
        $result = $wpdb->insert(
            $table,
            array(
                'post_id'        => $post_id,
                'email'          => $email,
                'notify_protest' => (int) $notify_protest,
                'notify_union'   => (int) $notify_union,
                'confirmed'      => 1,
                'token'          => $token,
                'created_at'     => current_time( 'mysql', true ),
            ),
            array( '%d', '%s', '%d', '%d', '%d', '%s', '%s' )
        );

        if ( false === $result ) {
            return new WP_Error( 'pim_db_error', __( 'Could not save subscription.', 'protest-itinerary-map' ) );
        }

        return array( 'token' => $token, 'is_new' => true );
    }

    /**
     * Confirm a subscription by token.
     *
     * @param string $token Confirmation token.
     * @return bool True if confirmed, false if not found.
     */
    public static function confirm( $token ) {
        global $wpdb;
        $table = self::table_name();

        $rows = $wpdb->update(
            $table,
            array( 'confirmed' => 1 ),
            array( 'token' => $token, 'confirmed' => 0 ),
            array( '%d' ),
            array( '%s', '%d' )
        );

        return $rows > 0;
    }

    /**
     * Unsubscribe (delete) by token.
     *
     * @param string $token Subscriber token.
     * @return bool True if deleted, false if not found.
     */
    public static function unsubscribe( $token ) {
        global $wpdb;
        $table = self::table_name();

        $rows = $wpdb->delete( $table, array( 'token' => $token ), array( '%s' ) );
        return $rows > 0;
    }

    /**
     * Get all confirmed subscribers for a post.
     *
     * @param int    $post_id Itinerary post ID.
     * @param string $type    'protest' or 'union' or 'all'.
     * @return array Array of subscriber row objects.
     */
    public static function get_subscribers( $post_id, $type = 'all' ) {
        global $wpdb;
        $table = self::table_name();

        $where = $wpdb->prepare( "post_id = %d AND confirmed = 1", $post_id );
        if ( 'protest' === $type ) {
            $where .= ' AND notify_protest = 1';
        } elseif ( 'union' === $type ) {
            $where .= ' AND notify_union = 1';
        }

        return $wpdb->get_results( "SELECT * FROM {$table} WHERE {$where} ORDER BY created_at ASC" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    }

    /**
     * Count subscribers for a post.
     *
     * @param int  $post_id       Itinerary post ID.
     * @param bool $confirmed_only Only count confirmed.
     * @return int
     */
    public static function count_subscribers( $post_id, $confirmed_only = true, $type = 'all' ) {
        global $wpdb;
        $table = self::table_name();

        $where = $wpdb->prepare( "post_id = %d", $post_id );
        if ( $confirmed_only ) {
            $where .= ' AND confirmed = 1';
        }
        if ( 'protest' === $type ) {
            $where .= ' AND notify_protest = 1';
        } elseif ( 'union' === $type ) {
            $where .= ' AND notify_union = 1';
        }

        return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE {$where}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    }

    /**
     * Delete all subscribers for a post.
     *
     * @param int $post_id Itinerary post ID.
     * @return int Number of rows deleted.
     */
    public static function delete_all_for_post( $post_id ) {
        global $wpdb;
        $table = self::table_name();

        return $wpdb->delete( $table, array( 'post_id' => $post_id ), array( '%d' ) );
    }

    /**
     * Purge unconfirmed subscriptions older than 48 hours.
     */
    public static function purge_unconfirmed() {
        global $wpdb;
        $table = self::table_name();

        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$table} WHERE confirmed = 0 AND created_at < %s",
                gmdate( 'Y-m-d H:i:s', time() - 48 * HOUR_IN_SECONDS )
            )
        );
    }

    /**
     * Purge subscribers based on retention policy:
     * - Protest-only subscribers (notify_protest=1, notify_union=0): 24 hours after event date.
     * - Union subscribers (notify_union=1): 1 year after event date.
     */
    public static function purge_post_event() {
        global $wpdb;
        $table = self::table_name();

        // 1. Purge protest-only subscribers 24h after event.
        $cutoff_protest = gmdate( 'Y-m-d', time() - DAY_IN_SECONDS );

        $protest_post_ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT post_id FROM {$wpdb->postmeta}
                 WHERE meta_key = '_pim_event_date' AND meta_value != '' AND meta_value < %s",
                $cutoff_protest
            )
        );

        if ( ! empty( $protest_post_ids ) ) {
            $placeholders = implode( ',', array_fill( 0, count( $protest_post_ids ), '%d' ) );
            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$table} WHERE post_id IN ({$placeholders}) AND notify_protest = 1 AND notify_union = 0", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                    ...$protest_post_ids
                )
            );
        }

        // 2. Purge union subscribers 1 year after event.
        $cutoff_union = gmdate( 'Y-m-d', time() - YEAR_IN_SECONDS );

        $union_post_ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT post_id FROM {$wpdb->postmeta}
                 WHERE meta_key = '_pim_event_date' AND meta_value != '' AND meta_value < %s",
                $cutoff_union
            )
        );

        if ( ! empty( $union_post_ids ) ) {
            $placeholders = implode( ',', array_fill( 0, count( $union_post_ids ), '%d' ) );
            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$table} WHERE post_id IN ({$placeholders}) AND notify_union = 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                    ...$union_post_ids
                )
            );
        }
    }

    /**
     * Get subscribers for CSV export.
     *
     * @param int $post_id Itinerary post ID.
     * @return array
     */
    public static function get_for_export( $post_id ) {
        global $wpdb;
        $table = self::table_name();

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT email, notify_protest, notify_union, confirmed, created_at
                 FROM {$table} WHERE post_id = %d AND confirmed = 1 ORDER BY created_at ASC",
                $post_id
            )
        );
    }
}
