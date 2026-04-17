<?php
/**
 * Intégration Google Places API + cache transient.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class Wheel_Game_Google_Api {

    const CACHE_TTL = HOUR_IN_SECONDS;

    public function fetch( $place_id, $api_key, $force = false ) {
        $cache_key = 'wheel_google_' . md5( $place_id );
        if ( ! $force ) {
            $cached = get_transient( $cache_key );
            if ( $cached && is_array( $cached ) ) return $cached;
        }

        $url = add_query_arg( [
            'place_id' => $place_id,
            'fields'   => 'rating,user_ratings_total',
            'key'      => $api_key,
        ], 'https://maps.googleapis.com/maps/api/place/details/json' );

        $response = wp_remote_get( $url, [ 'timeout' => 10 ] );
        if ( is_wp_error( $response ) ) return $response;

        $code = wp_remote_retrieve_response_code( $response );
        if ( $code !== 200 ) {
            return new WP_Error( 'http_error', sprintf( 'HTTP %d de Google', $code ) );
        }

        $body   = json_decode( wp_remote_retrieve_body( $response ), true );
        $status = $body['status'] ?? 'UNKNOWN';
        if ( $status !== 'OK' ) {
            $msg = $body['error_message'] ?? $status;
            return new WP_Error( 'google_error', $msg );
        }

        $stats = [
            'rating'       => round( floatval( $body['result']['rating'] ?? 0 ), 1 ),
            'review_count' => intval( $body['result']['user_ratings_total'] ?? 0 ),
        ];
        set_transient( $cache_key, $stats, self::CACHE_TTL );
        return $stats;
    }
}
