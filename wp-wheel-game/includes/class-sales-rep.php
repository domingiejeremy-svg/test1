<?php
/**
 * Gestion des commerciaux (sales reps).
 * - Rôle wheel_sales
 * - Lien user ↔ coupon WooCommerce
 * - Tracking automatique des commandes utilisant le coupon
 * - Calcul commissions
 * - Liste/filtrage des ventes par commercial
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class Wheel_Game_Sales_Rep {

    const ROLE = 'wheel_sales';

    // Métas user
    const META_COUPON     = '_bvr_sales_coupon';     // code coupon WC assigné
    const META_COMMISSION = '_bvr_sales_commission'; // % de commission (ex: 10.00)
    const META_PHONE      = '_bvr_sales_phone';

    // Métas commande
    const ORDER_META_SALES_REP_ID = '_bvr_sales_rep_id';
    const ORDER_META_COMMISSION   = '_bvr_sales_commission_amount';

    public function init() {
        // Hook commande : attribuer au commercial
        add_action( 'woocommerce_order_status_processing', [ $this, 'maybe_attribute_order' ], 20, 1 );
        add_action( 'woocommerce_order_status_completed',  [ $this, 'maybe_attribute_order' ], 20, 1 );
    }

    /**
     * Crée le rôle à l'activation.
     */
    public static function create_role() {
        if ( get_role( self::ROLE ) ) return;
        add_role( self::ROLE, __( 'Commercial BVR', 'wheel-game' ), [
            'read' => true,
        ] );
    }

    public static function remove_role() {
        if ( get_role( self::ROLE ) ) remove_role( self::ROLE );
    }

    /**
     * Liste tous les commerciaux (users avec le rôle wheel_sales).
     */
    public static function get_all() {
        return get_users( [
            'role'    => self::ROLE,
            'orderby' => 'display_name',
            'order'   => 'ASC',
        ] );
    }

    /**
     * Assigner un coupon à un commercial (et nettoyer les autres associations).
     */
    public static function assign_coupon( $user_id, $coupon_code ) {
        $coupon_code = wc_format_coupon_code( $coupon_code );
        if ( ! $coupon_code ) {
            delete_user_meta( $user_id, self::META_COUPON );
            return true;
        }
        // Vérifie que le coupon existe dans WC
        $coupon = new WC_Coupon( $coupon_code );
        if ( ! $coupon->get_id() ) {
            return new WP_Error( 'invalid_coupon', __( 'Ce coupon n\'existe pas dans WooCommerce', 'wheel-game' ) );
        }

        // Un coupon ne peut être assigné qu'à un seul commercial : on nettoie d'abord
        $others = get_users( [
            'role'       => self::ROLE,
            'meta_key'   => self::META_COUPON,
            'meta_value' => $coupon_code,
            'exclude'    => [ $user_id ],
        ] );
        foreach ( $others as $u ) {
            delete_user_meta( $u->ID, self::META_COUPON );
        }

        update_user_meta( $user_id, self::META_COUPON, $coupon_code );
        return true;
    }

    /**
     * Récupère le commercial associé à un coupon (ou null).
     */
    public static function find_by_coupon( $coupon_code ) {
        $coupon_code = wc_format_coupon_code( $coupon_code );
        if ( ! $coupon_code ) return null;
        $users = get_users( [
            'role'       => self::ROLE,
            'meta_key'   => self::META_COUPON,
            'meta_value' => $coupon_code,
            'number'     => 1,
        ] );
        return $users[0] ?? null;
    }

    /**
     * Attribue une commande au commercial si un coupon reconnu est utilisé.
     */
    public function maybe_attribute_order( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) return;
        if ( $order->get_meta( self::ORDER_META_SALES_REP_ID ) ) return; // déjà attribué

        $codes = $order->get_coupon_codes();
        foreach ( $codes as $code ) {
            $rep = self::find_by_coupon( $code );
            if ( $rep ) {
                $commission_rate = (float) get_user_meta( $rep->ID, self::META_COMMISSION, true );
                $subtotal        = (float) $order->get_subtotal() - (float) $order->get_total_discount();
                $commission_amt  = round( $subtotal * $commission_rate / 100, 2 );

                $order->update_meta_data( self::ORDER_META_SALES_REP_ID, $rep->ID );
                $order->update_meta_data( self::ORDER_META_COMMISSION, $commission_amt );
                $order->save();

                do_action( 'wheel_game_order_attributed_to_rep', $order, $rep, $commission_amt );
                self::notify_rep( $rep, $order, $commission_amt );
                return; // un seul commercial par commande
            }
        }
    }

    /**
     * Envoie un email au commercial pour sa nouvelle vente.
     */
    public static function notify_rep( $rep, $order, $commission_amt ) {
        $to = $rep->user_email;
        if ( ! $to ) return;
        $subject = sprintf(
            __( '🎉 Nouvelle vente — %s', 'wheel-game' ),
            $order->get_formatted_billing_full_name()
        );
        $body  = sprintf( __( "Bonjour %s,\n\n", 'wheel-game' ), $rep->display_name ) .
            __( "Une nouvelle commande vient d'être passée avec votre code :\n\n", 'wheel-game' ) .
            sprintf( __( "Commande : #%s\n", 'wheel-game' ), $order->get_order_number() ) .
            sprintf( __( "Client : %s\n", 'wheel-game' ), $order->get_formatted_billing_full_name() ) .
            sprintf( __( "Montant HT : %s\n", 'wheel-game' ), wc_price( $order->get_subtotal() - $order->get_total_discount() ) ) .
            sprintf( __( "Votre commission (%.2f%%) : %s\n\n", 'wheel-game' ),
                (float) get_user_meta( $rep->ID, self::META_COMMISSION, true ),
                wc_price( $commission_amt )
            ) .
            __( "Retrouvez tous vos détails dans votre espace :\n", 'wheel-game' ) .
            home_url( '/espace-commercial/' ) . "\n\n" .
            __( "Bonne journée !\nL'équipe BVR", 'wheel-game' );

        $headers = [ 'Content-Type: text/plain; charset=UTF-8' ];
        wp_mail( $to, wp_strip_all_tags( $subject ), wp_strip_all_tags( $body ), $headers );
    }

    /**
     * Liste des commandes attribuées à un commercial.
     */
    public static function get_orders_for_rep( $rep_id, $limit = 50 ) {
        $args = [
            'limit'      => $limit,
            'meta_key'   => self::ORDER_META_SALES_REP_ID,
            'meta_value' => $rep_id,
            'orderby'    => 'date',
            'order'      => 'DESC',
            'return'     => 'objects',
            'status'     => [ 'processing', 'completed' ],
        ];
        return wc_get_orders( $args );
    }

    /**
     * Stats agrégées d'un commercial.
     */
    public static function stats_for_rep( $rep_id, $days = 30 ) {
        $orders = self::get_orders_for_rep( $rep_id, 500 );
        $since  = time() - $days * DAY_IN_SECONDS;

        $stats = [
            'total_orders'        => 0,
            'total_revenue'       => 0,
            'total_commission'    => 0,
            'orders_last_period'  => 0,
            'revenue_last_period' => 0,
            'commission_last_period' => 0,
        ];
        foreach ( $orders as $order ) {
            $amt = (float) $order->get_subtotal() - (float) $order->get_total_discount();
            $comm = (float) $order->get_meta( self::ORDER_META_COMMISSION );
            $stats['total_orders']++;
            $stats['total_revenue']    += $amt;
            $stats['total_commission'] += $comm;

            $ts = $order->get_date_created() ? $order->get_date_created()->getTimestamp() : 0;
            if ( $ts >= $since ) {
                $stats['orders_last_period']++;
                $stats['revenue_last_period']    += $amt;
                $stats['commission_last_period'] += $comm;
            }
        }
        return $stats;
    }
}
