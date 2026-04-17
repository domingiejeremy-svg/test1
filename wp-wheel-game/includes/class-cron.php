<?php
/**
 * Cron quotidien avec retry + logs.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class Wheel_Game_Cron {

    public function init() {
        add_action( 'wheel_daily_google_fetch', [ $this, 'daily_fetch' ] );
    }

    public function daily_fetch() {
        $api_key = get_option( 'wheel_game_google_api_key', '' );
        if ( ! $api_key ) {
            self::log( 0, 'daily_fetch', 'skipped', 'No API key configured' );
            return;
        }

        $ids = get_posts( [
            'post_type'   => Wheel_Game_Cpt::POST_TYPE,
            'post_status' => 'publish',
            'numberposts' => -1,
            'fields'      => 'ids',
        ] );

        global $wpdb;
        $gtable = $wpdb->prefix . 'wheel_google_stats';
        $today  = current_time( 'Y-m-d' );
        $google = Wheel_Game_Plugin::get_instance()->google;

        $ok = 0; $fail = 0; $skip = 0;
        $last_error = '';

        foreach ( $ids as $campaign_id ) {
            $place_id = get_post_meta( $campaign_id, '_google_place_id', true );
            if ( ! $place_id ) { $skip++; continue; }

            $exists = $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$gtable} WHERE campaign_id = %d AND recorded_at = %s",
                $campaign_id, $today ) );
            if ( $exists ) { $skip++; continue; }

            $stats = null;
            $result = null;
            for ( $attempt = 1; $attempt <= 3; $attempt++ ) {
                $result = $google->fetch( $place_id, $api_key, true );
                if ( ! is_wp_error( $result ) ) { $stats = $result; break; }
                if ( $attempt < 3 ) sleep( $attempt * 2 );
            }

            if ( ! $stats ) {
                $fail++;
                $last_error = is_wp_error( $result ) ? $result->get_error_message() : 'Unknown';
                self::log( $campaign_id, 'fetch_google', 'error', $last_error );
                continue;
            }

            $wpdb->insert( $gtable, [
                'campaign_id'  => $campaign_id,
                'rating'       => $stats['rating'],
                'review_count' => $stats['review_count'],
                'recorded_at'  => $today,
            ] );
            $ok++;
            usleep( 500000 );
        }

        self::log( 0, 'daily_fetch', 'done', sprintf( 'ok=%d fail=%d skip=%d', $ok, $fail, $skip ) );
    }

    public static function log( $campaign_id, $action, $status, $message = '' ) {
        global $wpdb;
        $wpdb->insert( $wpdb->prefix . 'wheel_cron_log', [
            'campaign_id' => $campaign_id,
            'action'      => $action,
            'status'      => $status,
            'message'     => substr( $message, 0, 1000 ),
            'created_at'  => current_time( 'mysql' ),
        ] );

        if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
            error_log( sprintf( '[wheel-game] %s/%s campaign=%d : %s', $action, $status, $campaign_id, $message ) );
        }

        $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}wheel_cron_log WHERE created_at < %s",
            gmdate( 'Y-m-d H:i:s', time() - 30 * DAY_IN_SECONDS ) ) );
    }
}
