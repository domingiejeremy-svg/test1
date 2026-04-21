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

    // Métas user (sur le commercial)
    const META_COUPON     = '_bvr_sales_coupon';     // code coupon WC assigné
    const META_COMMISSION = '_bvr_sales_commission'; // % de commission (ex: 10.00)
    const META_PHONE      = '_bvr_sales_phone';

    // Métas user (sur le client)
    const USER_META_ASSIGNED_REP   = '_bvr_assigned_sales_rep';      // ID du commercial assigné à vie
    const USER_META_ASSIGNED_SINCE = '_bvr_assigned_sales_rep_since';// Date de 1ère attribution
    const USER_META_ASSIGNED_VIA   = '_bvr_assigned_via_coupon';     // Code coupon qui a déclenché

    // Métas commande
    const ORDER_META_SALES_REP_ID     = '_bvr_sales_rep_id';
    const ORDER_META_COMMISSION       = '_bvr_sales_commission_amount';
    const ORDER_META_ATTRIBUTION_TYPE = '_bvr_attribution_type';     // 'direct' (coupon) ou 'lifetime' (renouvellement)

    public function init() {
        // Hook commande : attribuer au commercial
        add_action( 'woocommerce_order_status_processing', [ $this, 'maybe_attribute_order' ], 20, 1 );
        add_action( 'woocommerce_order_status_completed',  [ $this, 'maybe_attribute_order' ], 20, 1 );

        // UI admin : assigner un client à un commercial depuis son profil
        add_action( 'edit_user_profile',         [ $this, 'render_user_profile_field' ] );
        add_action( 'edit_user_profile_update',  [ $this, 'save_user_profile_field' ] );
        add_action( 'show_user_profile',         [ $this, 'render_user_profile_field' ] );
        add_action( 'personal_options_update',   [ $this, 'save_user_profile_field' ] );
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
     * Attribue une commande au commercial.
     * 2 mécanismes :
     *   1. Coupon commercial utilisé → attribution "directe" + mémorisation à vie sur le client
     *   2. Pas de coupon mais client déjà assigné à un commercial → attribution "renouvellement"
     */
    public function maybe_attribute_order( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) return;
        if ( $order->get_meta( self::ORDER_META_SALES_REP_ID ) ) return; // déjà attribué

        $rep = null;
        $attribution_type = 'direct';
        $matched_coupon   = '';

        // ── Cas 1 : coupon commercial utilisé ──
        foreach ( $order->get_coupon_codes() as $code ) {
            $found = self::find_by_coupon( $code );
            if ( $found ) {
                $rep = $found;
                $matched_coupon = $code;
                break;
            }
        }

        // ── Cas 2 : pas de coupon → vérifier l'assignation à vie du client ──
        if ( ! $rep ) {
            $user_id = $order->get_user_id();
            if ( $user_id ) {
                $rep_id = (int) get_user_meta( $user_id, self::USER_META_ASSIGNED_REP, true );
                if ( $rep_id ) {
                    $r = get_user_by( 'id', $rep_id );
                    if ( $r && in_array( self::ROLE, (array) $r->roles, true ) ) {
                        $rep = $r;
                        $attribution_type = 'lifetime';
                    }
                }
            }
        }

        if ( ! $rep ) return;

        // Calcul commission
        $commission_rate = (float) get_user_meta( $rep->ID, self::META_COMMISSION, true );
        $subtotal        = (float) $order->get_subtotal() - (float) $order->get_total_discount();
        $commission_amt  = round( $subtotal * $commission_rate / 100, 2 );

        $order->update_meta_data( self::ORDER_META_SALES_REP_ID,     $rep->ID );
        $order->update_meta_data( self::ORDER_META_COMMISSION,       $commission_amt );
        $order->update_meta_data( self::ORDER_META_ATTRIBUTION_TYPE, $attribution_type );
        $order->save();

        // Assignation à vie : dès la 1ère vente avec coupon, on "verrouille" le client
        if ( $attribution_type === 'direct' ) {
            $user_id = $order->get_user_id();
            if ( $user_id && ! get_user_meta( $user_id, self::USER_META_ASSIGNED_REP, true ) ) {
                update_user_meta( $user_id, self::USER_META_ASSIGNED_REP,   $rep->ID );
                update_user_meta( $user_id, self::USER_META_ASSIGNED_SINCE, current_time( 'mysql' ) );
                update_user_meta( $user_id, self::USER_META_ASSIGNED_VIA,   $matched_coupon );
            }
        }

        do_action( 'wheel_game_order_attributed_to_rep', $order, $rep, $commission_amt, $attribution_type );
        self::notify_rep( $rep, $order, $commission_amt, $attribution_type );
    }

    /**
     * Rendu du champ "Commercial assigné" sur le profil utilisateur (admin only).
     */
    public function render_user_profile_field( $user ) {
        if ( ! current_user_can( 'manage_options' ) ) return;
        // Pas de sens pour les commerciaux eux-mêmes
        if ( in_array( self::ROLE, (array) $user->roles, true ) ) return;

        $assigned_id  = (int) get_user_meta( $user->ID, self::USER_META_ASSIGNED_REP, true );
        $assigned_since = get_user_meta( $user->ID, self::USER_META_ASSIGNED_SINCE, true );
        $assigned_via   = get_user_meta( $user->ID, self::USER_META_ASSIGNED_VIA, true );
        $all_reps = self::get_all();
        wp_nonce_field( 'bvr_assign_rep', 'bvr_assign_rep_nonce' );
        ?>
        <h2>💼 <?php esc_html_e( 'Commercial assigné (BVR)', 'wheel-game' ); ?></h2>
        <table class="form-table">
          <tr>
            <th><label for="bvr_assigned_rep"><?php esc_html_e( 'Commercial', 'wheel-game' ); ?></label></th>
            <td>
              <select name="bvr_assigned_rep" id="bvr_assigned_rep">
                <option value=""><?php esc_html_e( '— Aucun —', 'wheel-game' ); ?></option>
                <?php foreach ( $all_reps as $rep ) : ?>
                  <option value="<?php echo esc_attr( $rep->ID ); ?>" <?php selected( $assigned_id, $rep->ID ); ?>>
                    <?php echo esc_html( $rep->display_name . ' (' . $rep->user_email . ')' ); ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <p class="description">
                <?php esc_html_e( 'Toutes les commandes futures de ce client seront attribuées au commercial sélectionné (avec commission).', 'wheel-game' ); ?>
                <?php if ( $assigned_since ) : ?>
                  <br><em><?php printf( esc_html__( 'Assigné depuis le %s', 'wheel-game' ), esc_html( $assigned_since ) ); ?>
                  <?php if ( $assigned_via ) : ?>
                    <?php printf( esc_html__( ' (via coupon %s)', 'wheel-game' ), '<code>' . esc_html( $assigned_via ) . '</code>' ); ?>
                  <?php endif; ?>
                  </em>
                <?php endif; ?>
              </p>
            </td>
          </tr>
        </table>
        <?php
    }

    /**
     * Sauvegarde du champ "Commercial assigné".
     */
    public function save_user_profile_field( $user_id ) {
        if ( ! current_user_can( 'manage_options' ) ) return;
        if ( ! isset( $_POST['bvr_assign_rep_nonce'] ) ) return;
        if ( ! wp_verify_nonce( $_POST['bvr_assign_rep_nonce'], 'bvr_assign_rep' ) ) return;

        $new_id = absint( $_POST['bvr_assigned_rep'] ?? 0 );
        $current = (int) get_user_meta( $user_id, self::USER_META_ASSIGNED_REP, true );

        if ( $new_id === $current ) return;

        if ( $new_id === 0 ) {
            delete_user_meta( $user_id, self::USER_META_ASSIGNED_REP );
            delete_user_meta( $user_id, self::USER_META_ASSIGNED_SINCE );
            delete_user_meta( $user_id, self::USER_META_ASSIGNED_VIA );
        } else {
            // Vérifie que le user est bien un commercial
            $rep = get_user_by( 'id', $new_id );
            if ( ! $rep || ! in_array( self::ROLE, (array) $rep->roles, true ) ) return;

            update_user_meta( $user_id, self::USER_META_ASSIGNED_REP, $new_id );
            update_user_meta( $user_id, self::USER_META_ASSIGNED_SINCE, current_time( 'mysql' ) );
            update_user_meta( $user_id, self::USER_META_ASSIGNED_VIA, 'manual' );
        }
    }

    /**
     * Envoie un email au commercial pour sa nouvelle vente.
     */
    public static function notify_rep( $rep, $order, $commission_amt, $attribution_type = 'direct' ) {
        $to = $rep->user_email;
        if ( ! $to ) return;

        $type_label = $attribution_type === 'lifetime'
            ? __( '🔁 Renouvellement client', 'wheel-game' )
            : __( '🔥 Nouvelle vente directe', 'wheel-game' );

        $subject = sprintf(
            __( '🎉 %s — %s', 'wheel-game' ),
            $attribution_type === 'lifetime' ? 'Renouvellement' : 'Nouvelle vente',
            $order->get_formatted_billing_full_name()
        );
        $intro = $attribution_type === 'lifetime'
            ? __( "Bonne nouvelle ! Un de vos clients vient de renouveler/repasser commande — votre commission tombe automatiquement :\n\n", 'wheel-game' )
            : __( "Une nouvelle commande vient d'être passée avec votre code :\n\n", 'wheel-game' );

        $body  = sprintf( __( "Bonjour %s,\n\n", 'wheel-game' ), $rep->display_name ) .
            $intro .
            sprintf( __( "Type : %s\n", 'wheel-game' ), $type_label ) .
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
     * Retourne le nombre de clients assignés à un commercial (à vie).
     */
    public static function customers_count( $rep_id ) {
        $users = get_users( [
            'meta_key'   => self::USER_META_ASSIGNED_REP,
            'meta_value' => $rep_id,
            'count_total'=> true,
            'fields'     => 'ID',
        ] );
        return count( $users );
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
