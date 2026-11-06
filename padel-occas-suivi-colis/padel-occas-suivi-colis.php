<?php
/**
 * Plugin Name: Padel Occas — Suivi colis pour le client
 * Description: Ajoute le numéro et le lien de suivi Sendcloud au mail « Commande terminée ». Si le suivi arrive après ce mail, l'envoie au client dans une note de commande. Une seule fois par commande.
 * Version:     1.0.0
 * Author:      Padel Occas
 * License:     GPL v2 or later
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/* ════════════════════════════════════════════════════════════════════════════
   CLASSE PRINCIPALE
   ════════════════════════════════════════════════════════════════════════════ */
class POC_Suivi_Colis {

    const META_SENT = '_poc_suivi_envoye';

    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        add_action( 'woocommerce_email_before_order_table', [ $this, 'add_to_completed_email' ], 10, 4 );
        add_action( 'woocommerce_order_note_added',         [ $this, 'on_note_added' ], 10, 2 );
    }

    /**
     * Lit la note privée de Sendcloud :
     * « The Mondial Relay tracking number for this SendCloud shipment is: 75264616 and can be traced at: https://… »
     */
    public static function parse_note( $text ) {
        $text = html_entity_decode( wp_strip_all_tags( (string) $text ), ENT_QUOTES, 'UTF-8' );
        if ( ! preg_match( '/The (.+?) tracking number for this SendCloud shipment is:\s*(\S+)\s+and can be traced at:\s*(https?:\/\/\S+)/i', $text, $m ) ) {
            return null;
        }
        return [ 'carrier' => trim( $m[1] ), 'number' => rtrim( $m[2], '.,' ), 'url' => rtrim( $m[3], '.,' ) ];
    }

    /** Dernier suivi Sendcloud trouvé dans les notes de la commande */
    public static function get_tracking( $order ) {
        $notes = wc_get_order_notes( [ 'order_id' => $order->get_id(), 'type' => 'internal' ] );
        foreach ( $notes as $note ) { // les plus récentes d'abord
            $t = self::parse_note( $note->content );
            if ( $t ) return $t;
        }
        return null;
    }

    /* ── 1. Dans le mail « Commande terminée » ───────────────────────────── */

    public function add_to_completed_email( $order, $sent_to_admin, $plain_text, $email = null ) {
        if ( $sent_to_admin || ! $email || 'customer_completed_order' !== $email->id ) return;
        $t = self::get_tracking( $order );
        if ( ! $t ) return;

        if ( $plain_text ) {
            echo "\n" . sprintf( "Votre colis %s : n° %s\nSuivre mon colis : %s", $t['carrier'], $t['number'], $t['url'] ) . "\n\n";
        } else {
            printf(
                '<div style="margin:0 0 24px;padding:16px 18px;border:1px solid #DFE4E9;border-radius:8px;background:#F5F8FC">'
                . '<p style="margin:0 0 6px;font-weight:600">Votre colis est en route</p>'
                . '<p style="margin:0 0 12px">%s, n° de suivi <strong>%s</strong>.</p>'
                . '<a href="%s" style="display:inline-block;padding:10px 18px;border-radius:6px;background:#0D124B;color:#ffffff;text-decoration:none;font-weight:600">Suivre mon colis</a>'
                . '</div>',
                esc_html( $t['carrier'] ),
                esc_html( $t['number'] ),
                esc_url( $t['url'] )
            );
        }
        $order->update_meta_data( self::META_SENT, $t['number'] );
        $order->save_meta_data();
    }

    /* ── 2. Si le suivi arrive après le mail : note client (envoyée par mail) ── */

    public function on_note_added( $note_id, $order ) {
        if ( ! $order instanceof WC_Order ) $order = wc_get_order( $order );
        if ( ! $order ) return;

        $note = wc_get_order_note( $note_id );
        if ( ! $note || $note->customer_note ) return;
        $t = self::parse_note( $note->content );
        if ( ! $t ) return;

        // Commande pas encore terminée : le mail « Commande terminée » portera le suivi
        if ( ! $order->has_status( 'completed' ) ) return;
        // Déjà transmis (dans le mail ou par une note)
        if ( $order->get_meta( self::META_SENT ) === $t['number'] ) return;

        $order->update_meta_data( self::META_SENT, $t['number'] );
        $order->save_meta_data();
        $order->add_order_note(
            sprintf(
                "Votre colis est en route !\n%s, n° de suivi %s.\nSuivre mon colis : %s",
                $t['carrier'], $t['number'], $t['url']
            ),
            true // note client : WooCommerce l'envoie par mail
        );
    }
}

POC_Suivi_Colis::get_instance();
