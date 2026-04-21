<?php
/**
 * Espace public /espace-commercial/ — dashboard du commercial + outils.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class Wheel_Game_Sales_Space {

    const SLUG = 'espace-commercial';

    public function init() {
        add_action( 'init',              [ $this, 'add_rewrite' ] );
        add_filter( 'query_vars',        [ $this, 'add_query_var' ] );
        add_action( 'template_redirect', [ $this, 'maybe_render' ] );

        // AJAX outils
        add_action( 'wp_ajax_wheel_sales_audit',   [ $this, 'ajax_audit' ] );
        add_action( 'wp_ajax_wheel_sales_ranking', [ $this, 'ajax_ranking' ] );
    }

    public function add_rewrite() {
        add_rewrite_rule( '^' . self::SLUG . '/?$', 'index.php?wheel_sales_space=1', 'top' );
        add_rewrite_rule( '^' . self::SLUG . '/([^/]+)/?$', 'index.php?wheel_sales_space=$matches[1]', 'top' );
    }

    public function add_query_var( $vars ) {
        $vars[] = 'wheel_sales_space';
        return $vars;
    }

    public function maybe_render() {
        $page = get_query_var( 'wheel_sales_space' );
        if ( ! $page ) return;

        // Authentification
        if ( ! is_user_logged_in() ) {
            $login_url = wp_login_url( home_url( '/' . self::SLUG . '/' ) );
            wp_safe_redirect( $login_url );
            exit;
        }
        $user = wp_get_current_user();
        $is_admin = current_user_can( 'manage_options' );
        $is_sales = in_array( Wheel_Game_Sales_Rep::ROLE, (array) $user->roles, true );
        if ( ! $is_admin && ! $is_sales ) {
            wp_die( esc_html__( 'Accès réservé aux commerciaux.', 'wheel-game' ), 403 );
        }

        // Route
        $route = is_string( $page ) && $page !== '1' ? $page : 'dashboard';
        switch ( $route ) {
            case 'audit':     $this->render_audit( $user ); break;
            case 'ranking':   $this->render_ranking( $user ); break;
            case 'dashboard':
            default:          $this->render_dashboard( $user ); break;
        }
        exit;
    }

    private function render_dashboard( $user ) {
        $stats  = Wheel_Game_Sales_Rep::stats_for_rep( $user->ID );
        $orders = Wheel_Game_Sales_Rep::get_orders_for_rep( $user->ID, 20 );
        $coupon     = get_user_meta( $user->ID, Wheel_Game_Sales_Rep::META_COUPON, true );
        $commission = (float) get_user_meta( $user->ID, Wheel_Game_Sales_Rep::META_COMMISSION, true );
        include WHEEL_GAME_DIR . 'templates/sales-dashboard.php';
    }

    private function render_audit( $user ) {
        // Si ?print=1 → on affiche juste le PDF imprimable
        $print_mode = isset( $_GET['print'] ) && $_GET['print'] === '1';
        include WHEEL_GAME_DIR . 'templates/sales-audit.php';
    }

    private function render_ranking( $user ) {
        include WHEEL_GAME_DIR . 'templates/sales-ranking.php';
    }

    /**
     * AJAX : récupérer les stats d'un Place ID (prospect + concurrents).
     */
    public function ajax_audit() {
        check_ajax_referer( 'wheel_sales', 'nonce' );
        if ( ! $this->can_use_tools() ) wp_send_json_error( 'Unauthorized' );

        $place_ids = isset( $_POST['place_ids'] ) ? (array) json_decode( wp_unslash( $_POST['place_ids'] ), true ) : [];
        $place_ids = array_map( 'sanitize_text_field', $place_ids );
        $place_ids = array_filter( $place_ids );
        if ( empty( $place_ids ) ) wp_send_json_error( 'No place IDs provided' );
        if ( count( $place_ids ) > 6 ) wp_send_json_error( 'Max 6 place IDs' );

        $api_key = get_option( 'wheel_game_google_api_key', '' );
        if ( ! $api_key ) wp_send_json_error( __( 'Clé API Google non configurée. Demandez à l\'administrateur.', 'wheel-game' ) );

        $google = Wheel_Game_Plugin::get_instance()->google;
        $results = [];
        foreach ( $place_ids as $pid ) {
            $details = $this->fetch_place_details( $pid, $api_key );
            if ( is_wp_error( $details ) ) {
                $results[] = [ 'place_id' => $pid, 'error' => $details->get_error_message() ];
                continue;
            }
            $results[] = array_merge( [ 'place_id' => $pid ], $details );

            // Active le tracking automatique pour ce place_id
            $this->start_tracking( $pid, $details );
        }

        wp_send_json_success( $results );
    }

    /**
     * AJAX : nearby search désactivée pour économiser les appels API.
     * Classement local = entrée manuelle des concurrents.
     */
    public function ajax_ranking() {
        $this->ajax_audit();
    }

    /**
     * Récupère note + nb avis + nom + adresse (étendu vs la classe google_api).
     */
    private function fetch_place_details( $place_id, $api_key ) {
        $cache_key = 'wheel_place_' . md5( $place_id );
        $cached = get_transient( $cache_key );
        if ( $cached && is_array( $cached ) ) return $cached;

        $url = add_query_arg( [
            'place_id' => $place_id,
            'fields'   => 'name,formatted_address,rating,user_ratings_total,types',
            'key'      => $api_key,
        ], 'https://maps.googleapis.com/maps/api/place/details/json' );

        $response = wp_remote_get( $url, [ 'timeout' => 10 ] );
        if ( is_wp_error( $response ) ) return $response;

        $code = wp_remote_retrieve_response_code( $response );
        if ( $code !== 200 ) return new WP_Error( 'http', 'HTTP ' . $code );

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( ( $body['status'] ?? '' ) !== 'OK' ) {
            return new WP_Error( 'google_err', $body['error_message'] ?? $body['status'] ?? 'Unknown' );
        }

        $r = $body['result'];
        $data = [
            'name'          => $r['name'] ?? '',
            'address'       => $r['formatted_address'] ?? '',
            'rating'        => round( floatval( $r['rating'] ?? 0 ), 1 ),
            'review_count'  => intval( $r['user_ratings_total'] ?? 0 ),
            'types'         => (array) ( $r['types'] ?? [] ),
        ];
        set_transient( $cache_key, $data, HOUR_IN_SECONDS );
        return $data;
    }

    /**
     * Démarre le tracking background d'un Place ID (pour relances 1-3 mois plus tard).
     */
    private function start_tracking( $place_id, $details ) {
        global $wpdb;
        $t = $wpdb->prefix . 'wheel_prospect_tracking';
        // Table optionnelle — créée lazy si pas déjà là
        $wpdb->query( "CREATE TABLE IF NOT EXISTS {$t} (
            id           bigint(20) NOT NULL AUTO_INCREMENT,
            place_id     varchar(120) NOT NULL,
            name         varchar(255) NOT NULL DEFAULT '',
            rating       decimal(3,1) NOT NULL DEFAULT 0.0,
            review_count int(11) NOT NULL DEFAULT 0,
            recorded_at  date NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY place_date (place_id, recorded_at),
            KEY place_id (place_id)
        )" );

        $wpdb->replace( $t, [
            'place_id'     => $place_id,
            'name'         => $details['name'] ?? '',
            'rating'       => $details['rating'] ?? 0,
            'review_count' => $details['review_count'] ?? 0,
            'recorded_at'  => current_time( 'Y-m-d' ),
        ] );
    }

    private function can_use_tools() {
        if ( ! is_user_logged_in() ) return false;
        $user = wp_get_current_user();
        return current_user_can( 'manage_options' )
            || in_array( Wheel_Game_Sales_Rep::ROLE, (array) $user->roles, true );
    }
}
