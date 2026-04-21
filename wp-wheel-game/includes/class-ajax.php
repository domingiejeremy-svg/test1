<?php
/**
 * Tous les endpoints AJAX (front + admin).
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class Wheel_Game_Ajax {

    public function init() {
        // Front
        add_action( 'wp_ajax_nopriv_wheel_submit_lead', [ $this, 'submit_lead' ] );
        add_action( 'wp_ajax_wheel_submit_lead',        [ $this, 'submit_lead' ] );
        add_action( 'wp_ajax_nopriv_wheel_save_play',   [ $this, 'save_play' ] );
        add_action( 'wp_ajax_wheel_save_play',          [ $this, 'save_play' ] );
        add_action( 'wp_ajax_nopriv_wheel_track_click', [ $this, 'track_click' ] );
        add_action( 'wp_ajax_wheel_track_click',        [ $this, 'track_click' ] );

        // Admin
        add_action( 'wp_ajax_wheel_fetch_google_stats', [ $this, 'fetch_google_stats' ] );
        add_action( 'wp_ajax_wheel_save_api_key',       [ $this, 'save_api_key' ] );
        add_action( 'wp_ajax_wheel_monte_carlo',        [ $this, 'monte_carlo' ] );
        add_action( 'wp_ajax_wheel_apply_template',     [ $this, 'apply_template' ] );
        add_action( 'wp_ajax_wheel_export_leads',       [ $this, 'export_leads' ] );
        add_action( 'wp_ajax_wheel_export_plays',       [ $this, 'export_plays' ] );
    }

    public function submit_lead() {
        check_ajax_referer( 'wheel_lead', 'nonce' );

        $campaign_id = absint( $_POST['campaign_id'] ?? 0 );
        if ( ! $campaign_id || get_post_type( $campaign_id ) !== Wheel_Game_Cpt::POST_TYPE ) {
            wp_send_json_error( __( 'Campagne invalide', 'wheel-game' ) );
        }

        $c = Wheel_Game_Campaign::get( $campaign_id );
        if ( ! $c['available'] ) wp_send_json_error( __( 'Cette campagne n\'est plus disponible', 'wheel-game' ) );
        if ( empty( $c['has_lead'] ) ) wp_send_json_error( __( 'Capture de lead non activée sur cette offre', 'wheel-game' ) );

        $data = [
            'first_name' => sanitize_text_field( wp_unslash( $_POST['first_name'] ?? '' ) ),
            'last_name'  => sanitize_text_field( wp_unslash( $_POST['last_name'] ?? '' ) ),
            'email'      => sanitize_email( wp_unslash( $_POST['email'] ?? '' ) ),
            'phone'      => preg_replace( '/[^\d+\s\-.()]/', '', wp_unslash( $_POST['phone'] ?? '' ) ),
            'consent'    => ! empty( $_POST['consent'] ) ? 1 : 0,
        ];

        $lead_id = Wheel_Game_Plugin::get_instance()->leads->create( $campaign_id, $data );
        if ( is_wp_error( $lead_id ) ) wp_send_json_error( $lead_id->get_error_message() );

        $token = Wheel_Game_Security::make_token( [ 'lead_id' => $lead_id, 'campaign_id' => $campaign_id ] );
        Wheel_Game_Security::set_cookie( 'wheel_lead_' . $campaign_id, $token, 2 * HOUR_IN_SECONDS );

        wp_send_json_success( [ 'lead_id' => $lead_id ] );
    }

    public function save_play() {
        check_ajax_referer( 'wheel_play', 'nonce' );

        $campaign_id = absint( $_POST['campaign_id'] ?? 0 );
        $prize_index = absint( $_POST['prize_index'] ?? 0 );
        $prize_label = sanitize_text_field( wp_unslash( $_POST['prize_label'] ?? '' ) );

        if ( ! $campaign_id || get_post_type( $campaign_id ) !== Wheel_Game_Cpt::POST_TYPE ) {
            wp_send_json_error( __( 'Campagne invalide', 'wheel-game' ) );
        }

        $c = Wheel_Game_Campaign::get( $campaign_id );
        if ( ! $c['available'] ) wp_send_json_error( __( 'Cette campagne n\'est plus disponible', 'wheel-game' ) );

        if ( ! isset( $c['prizes'][ $prize_index ] ) ) wp_send_json_error( __( 'Prix invalide', 'wheel-game' ) );

        $ip_hash = Wheel_Game_Security::ip_hash();
        if ( Wheel_Game_Security::has_played( $campaign_id, $ip_hash ) ) {
            wp_send_json_error( __( 'Vous avez déjà participé', 'wheel-game' ) );
        }

        $lead_id = 0;
        $lead_cookie = $_COOKIE[ 'wheel_lead_' . $campaign_id ] ?? '';
        if ( $lead_cookie ) {
            $decoded = Wheel_Game_Security::verify_token( $lead_cookie );
            if ( is_array( $decoded ) && (int) ( $decoded['campaign_id'] ?? 0 ) === $campaign_id ) {
                $lead_id = (int) ( $decoded['lead_id'] ?? 0 );
            }
        }

        if ( $c['lead_required'] && ! $lead_id ) {
            wp_send_json_error( __( 'Capture lead requise avant tirage', 'wheel-game' ) );
        }

        global $wpdb;
        $wpdb->insert( $wpdb->prefix . 'wheel_plays', [
            'campaign_id' => $campaign_id,
            'lead_id'     => $lead_id,
            'prize_index' => $prize_index,
            'prize_label' => $prize_label,
            'played_at'   => current_time( 'mysql' ),
            'ip_hash'     => $ip_hash,
        ] );
        $play_id = (int) $wpdb->insert_id;

        $token = Wheel_Game_Security::make_token( [
            'play_id'     => $play_id,
            'campaign_id' => $campaign_id,
            'index'       => $prize_index,
            'label'       => $prize_label,
        ] );
        Wheel_Game_Security::set_cookie( 'wheel_played_' . $campaign_id, $token, 30 * DAY_IN_SECONDS );

        do_action( 'wheel_game_after_play', $play_id, $campaign_id, $prize_index );
        Wheel_Game_Plugin::get_instance()->analytics->maybe_notify_big_prize( $campaign_id, $c, $prize_index, $lead_id );

        wp_send_json_success( [ 'prize_label' => $prize_label, 'play_token' => $token ] );
    }

    public function track_click() {
        check_ajax_referer( 'wheel_click', 'nonce' );

        $campaign_id = absint( $_POST['campaign_id'] ?? 0 );
        $play_token  = sanitize_text_field( wp_unslash( $_POST['play_token'] ?? '' ) );

        if ( ! $campaign_id ) wp_send_json_error( 'invalid' );

        $decoded = Wheel_Game_Security::verify_token( $play_token );
        if ( ! is_array( $decoded ) || (int) ( $decoded['campaign_id'] ?? 0 ) !== $campaign_id ) {
            wp_send_json_error( 'invalid_token' );
        }

        $play_id = (int) ( $decoded['play_id'] ?? 0 );
        if ( ! $play_id ) wp_send_json_error( 'no_play' );

        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'wheel_plays',
            [ 'clicked_google' => 1, 'clicked_at' => current_time( 'mysql' ) ],
            [ 'id' => $play_id, 'clicked_google' => 0 ],
            [ '%d', '%s' ],
            [ '%d', '%d' ]
        );
        wp_send_json_success();
    }

    public function fetch_google_stats() {
        check_ajax_referer( 'wheel_google', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

        $campaign_id = absint( $_POST['campaign_id'] ?? 0 );
        $api_key     = sanitize_text_field( wp_unslash( $_POST['api_key'] ?? '' ) );

        if ( ! $campaign_id ) wp_send_json_error( __( 'Campagne invalide', 'wheel-game' ) );
        if ( ! $api_key )     wp_send_json_error( __( 'Clé API manquante', 'wheel-game' ) );

        $place_id = get_post_meta( $campaign_id, '_google_place_id', true );
        if ( ! $place_id ) wp_send_json_error( __( 'Place ID non configuré', 'wheel-game' ) );

        $google = Wheel_Game_Plugin::get_instance()->google;
        $stats  = $google->fetch( $place_id, $api_key, true );
        if ( is_wp_error( $stats ) ) wp_send_json_error( $stats->get_error_message() );

        global $wpdb;
        $wpdb->replace( $wpdb->prefix . 'wheel_google_stats', [
            'campaign_id'  => $campaign_id,
            'rating'       => $stats['rating'],
            'review_count' => $stats['review_count'],
            'recorded_at'  => current_time( 'Y-m-d' ),
        ] );
        update_option( 'wheel_game_google_api_key', $api_key );
        wp_send_json_success( $stats );
    }

    public function save_api_key() {
        check_ajax_referer( 'wheel_google', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );
        update_option( 'wheel_game_google_api_key', sanitize_text_field( wp_unslash( $_POST['api_key'] ?? '' ) ) );
        wp_send_json_success();
    }

    public function monte_carlo() {
        check_ajax_referer( 'wheel_admin', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

        $raw = json_decode( wp_unslash( $_POST['prizes'] ?? '[]' ), true );
        if ( ! is_array( $raw ) ) wp_send_json_error( 'Invalid prizes' );

        $iterations = min( 100000, max( 100, absint( $_POST['iterations'] ?? 10000 ) ) );
        wp_send_json_success( Wheel_Game_Monte_Carlo::simulate( $raw, $iterations ) );
    }

    public function apply_template() {
        check_ajax_referer( 'wheel_admin', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

        $slug     = sanitize_key( $_POST['template'] ?? '' );
        $campaign = absint( $_POST['campaign_id'] ?? 0 );
        if ( ! $slug || ! $campaign ) wp_send_json_error( 'Missing params' );

        $result = Wheel_Game_Templates_Library::apply( $slug, $campaign );
        if ( is_wp_error( $result ) ) wp_send_json_error( $result->get_error_message() );
        wp_send_json_success();
    }

    public function export_leads() {
        check_ajax_referer( 'wheel_admin', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );
        Wheel_Game_Csv::export_leads( absint( $_GET['campaign_id'] ?? 0 ) );
    }

    public function export_plays() {
        check_ajax_referer( 'wheel_admin', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );
        Wheel_Game_Csv::export_plays( absint( $_GET['campaign_id'] ?? 0 ) );
    }
}
