<?php
/**
 * Gestion des leads (capture avant tirage).
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class Wheel_Game_Leads {

    public function create( $campaign_id, array $data ) {
        $c = Wheel_Game_Campaign::get( $campaign_id );
        $required = $c['lead_fields'];

        foreach ( $required as $field ) {
            if ( empty( $data[ $field ] ) ) {
                return new WP_Error( 'missing_field', sprintf(
                    __( 'Champ manquant : %s', 'wheel-game' ), $field ) );
            }
        }

        if ( in_array( 'email', $required, true ) && ! is_email( $data['email'] ) ) {
            return new WP_Error( 'bad_email', __( 'Adresse email invalide', 'wheel-game' ) );
        }

        if ( in_array( 'phone', $required, true ) ) {
            $digits = preg_replace( '/\D/', '', $data['phone'] );
            if ( strlen( $digits ) < 6 ) {
                return new WP_Error( 'bad_phone', __( 'Numéro de téléphone invalide', 'wheel-game' ) );
            }
        }

        global $wpdb;
        $table = $wpdb->prefix . 'wheel_leads';

        if ( ! empty( $data['email'] ) ) {
            $existing = $wpdb->get_var( $wpdb->prepare(
                "SELECT id FROM {$table} WHERE campaign_id = %d AND email = %s LIMIT 1",
                $campaign_id, $data['email'] ) );
            if ( $existing ) return (int) $existing;
        }

        $wpdb->insert( $table, [
            'campaign_id' => $campaign_id,
            'first_name'  => $data['first_name'] ?? '',
            'last_name'   => $data['last_name'] ?? '',
            'email'       => $data['email'] ?? '',
            'phone'       => $data['phone'] ?? '',
            'consent'     => ! empty( $data['consent'] ) ? 1 : 0,
            'ip_hash'     => Wheel_Game_Security::ip_hash(),
            'user_agent'  => substr( $_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255 ),
            'created_at'  => current_time( 'mysql' ),
        ] );

        $lead_id = (int) $wpdb->insert_id;
        do_action( 'wheel_game_after_create_lead', $lead_id, $campaign_id, $data );
        return $lead_id;
    }

    public function get_all( $campaign_id = 0, $limit = 500 ) {
        global $wpdb;
        $t = $wpdb->prefix . 'wheel_leads';
        if ( $campaign_id ) {
            return $wpdb->get_results( $wpdb->prepare(
                "SELECT * FROM {$t} WHERE campaign_id = %d ORDER BY created_at DESC LIMIT %d",
                $campaign_id, $limit ) );
        }
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$t} ORDER BY created_at DESC LIMIT %d", $limit ) );
    }

    public function count( $campaign_id = 0 ) {
        global $wpdb;
        $t = $wpdb->prefix . 'wheel_leads';
        if ( $campaign_id ) {
            return (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$t} WHERE campaign_id = %d", $campaign_id ) );
        }
        return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t}" );
    }
}
