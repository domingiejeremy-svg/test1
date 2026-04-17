<?php
/**
 * Export CSV (leads et tirages).
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class Wheel_Game_Csv {

    public static function export_leads( $campaign_id = 0 ) {
        global $wpdb;
        $t = $wpdb->prefix . 'wheel_leads';

        if ( $campaign_id ) {
            $rows = $wpdb->get_results( $wpdb->prepare(
                "SELECT * FROM {$t} WHERE campaign_id = %d ORDER BY created_at DESC",
                $campaign_id ), ARRAY_A );
            $filename = sprintf( 'leads-campagne-%d-%s.csv', $campaign_id, gmdate( 'Y-m-d' ) );
        } else {
            $rows = $wpdb->get_results( "SELECT * FROM {$t} ORDER BY created_at DESC", ARRAY_A );
            $filename = sprintf( 'leads-all-%s.csv', gmdate( 'Y-m-d' ) );
        }

        self::send_headers( $filename );
        $out = fopen( 'php://output', 'w' );
        fwrite( $out, "\xEF\xBB\xBF" );

        fputcsv( $out, [ 'ID', 'Campagne', 'Nom campagne', 'Prénom', 'Nom', 'Email', 'Téléphone', 'Consentement', 'Créé le' ], ';' );
        foreach ( $rows as $r ) {
            fputcsv( $out, [
                $r['id'], $r['campaign_id'], get_the_title( $r['campaign_id'] ),
                $r['first_name'], $r['last_name'], $r['email'], $r['phone'],
                $r['consent'] ? 'OUI' : 'NON', $r['created_at'],
            ], ';' );
        }
        fclose( $out );
        exit;
    }

    public static function export_plays( $campaign_id = 0 ) {
        global $wpdb;
        $p = $wpdb->prefix;

        $sql = "SELECT pl.*, l.email AS lead_email, l.first_name, l.last_name, l.phone
                FROM {$p}wheel_plays pl
                LEFT JOIN {$p}wheel_leads l ON l.id = pl.lead_id";
        if ( $campaign_id ) {
            $sql .= $wpdb->prepare( ' WHERE pl.campaign_id = %d', $campaign_id );
            $filename = sprintf( 'tirages-campagne-%d-%s.csv', $campaign_id, gmdate( 'Y-m-d' ) );
        } else {
            $filename = sprintf( 'tirages-all-%s.csv', gmdate( 'Y-m-d' ) );
        }
        $sql .= ' ORDER BY pl.played_at DESC';
        $rows = $wpdb->get_results( $sql, ARRAY_A );

        self::send_headers( $filename );
        $out = fopen( 'php://output', 'w' );
        fwrite( $out, "\xEF\xBB\xBF" );

        fputcsv( $out, [ 'ID', 'Campagne', 'Nom campagne', 'Prix', 'Joué le', 'Email lead', 'Prénom', 'Nom', 'Téléphone', 'Clic Google', 'Cliqué le' ], ';' );
        foreach ( $rows as $r ) {
            fputcsv( $out, [
                $r['id'], $r['campaign_id'], get_the_title( $r['campaign_id'] ),
                $r['prize_label'], $r['played_at'],
                $r['lead_email'] ?? '', $r['first_name'] ?? '', $r['last_name'] ?? '', $r['phone'] ?? '',
                $r['clicked_google'] ? 'OUI' : 'NON', $r['clicked_at'] ?? '',
            ], ';' );
        }
        fclose( $out );
        exit;
    }

    private static function send_headers( $filename ) {
        nocache_headers();
        header( 'Content-Type: text/csv; charset=UTF-8' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
    }
}
