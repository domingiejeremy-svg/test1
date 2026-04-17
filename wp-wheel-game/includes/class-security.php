<?php
/**
 * Tokens HMAC, hash IP, cookies sécurisés, anti-replay.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class Wheel_Game_Security {

    public static function ip_hash() {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        return hash( 'sha256', $ip . '|' . $ua . '|' . NONCE_SALT );
    }

    public static function has_played( $campaign_id, $ip_hash, $window_days = 30 ) {
        global $wpdb;

        // Admin en test → toujours autorisé
        if ( is_user_logged_in() && current_user_can( 'manage_options' )
            && ! ( isset( $_GET['preview_as_user'] ) && $_GET['preview_as_user'] === '1' ) ) {
            return false;
        }

        $since = gmdate( 'Y-m-d H:i:s', time() - $window_days * DAY_IN_SECONDS );

        $cookie = $_COOKIE[ 'wheel_played_' . $campaign_id ] ?? '';
        if ( $cookie ) {
            $data = self::verify_token( $cookie );
            if ( is_array( $data ) && (int) ( $data['campaign_id'] ?? 0 ) === (int) $campaign_id ) {
                return true;
            }
        }

        $exists = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}wheel_plays
             WHERE campaign_id = %d AND ip_hash = %s AND played_at >= %s LIMIT 1",
            $campaign_id, $ip_hash, $since
        ) );

        return ! empty( $exists );
    }

    public static function make_token( array $payload ) {
        $payload['exp'] = time() + 60 * DAY_IN_SECONDS;
        $json = wp_json_encode( $payload );
        $b64  = self::b64url_encode( $json );
        $sig  = self::b64url_encode( hash_hmac( 'sha256', $b64, self::secret(), true ) );
        return $b64 . '.' . $sig;
    }

    public static function verify_token( $token ) {
        if ( ! is_string( $token ) || substr_count( $token, '.' ) !== 1 ) return false;
        [ $b64, $sig ] = explode( '.', $token );
        $expected = self::b64url_encode( hash_hmac( 'sha256', $b64, self::secret(), true ) );
        if ( ! hash_equals( $expected, $sig ) ) return false;

        $payload = json_decode( self::b64url_decode( $b64 ), true );
        if ( ! is_array( $payload ) ) return false;
        if ( (int) ( $payload['exp'] ?? 0 ) < time() ) return false;
        return $payload;
    }

    public static function set_cookie( $name, $value, $ttl ) {
        $params = [
            'expires'  => time() + $ttl,
            'path'     => '/',
            'domain'   => defined( 'COOKIE_DOMAIN' ) ? COOKIE_DOMAIN : '',
            'secure'   => is_ssl(),
            'httponly' => true,
            'samesite' => 'Lax',
        ];
        if ( PHP_VERSION_ID >= 70300 ) {
            setcookie( $name, $value, $params );
        } else {
            setcookie( $name, $value, $params['expires'],
                $params['path'] . '; samesite=' . $params['samesite'],
                $params['domain'], $params['secure'], $params['httponly'] );
        }
        $_COOKIE[ $name ] = $value;
    }

    private static function secret() {
        $key = get_option( 'wheel_game_hmac_secret' );
        if ( ! $key ) {
            $key = wp_generate_password( 64, true, true );
            update_option( 'wheel_game_hmac_secret', $key, false );
        }
        return $key . NONCE_SALT;
    }

    private static function b64url_encode( $data ) {
        return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' );
    }

    private static function b64url_decode( $data ) {
        $remain = strlen( $data ) % 4;
        if ( $remain ) $data .= str_repeat( '=', 4 - $remain );
        return base64_decode( strtr( $data, '-_', '+/' ) );
    }
}
