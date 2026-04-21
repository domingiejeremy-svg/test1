<?php
/**
 * Page admin : gestion des commerciaux.
 */
if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! current_user_can( 'manage_options' ) ) return;

$action = isset( $_GET['bvr_action'] ) ? sanitize_key( $_GET['bvr_action'] ) : '';
$edit_id = absint( $_GET['rep_id'] ?? 0 );

// Traitement POST
$notice = '';
if ( isset( $_POST['wheel_rep_nonce'] ) && wp_verify_nonce( $_POST['wheel_rep_nonce'], 'wheel_rep' ) ) {

    if ( isset( $_POST['create_rep'] ) ) {
        $email   = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
        $fname   = sanitize_text_field( wp_unslash( $_POST['first_name'] ?? '' ) );
        $lname   = sanitize_text_field( wp_unslash( $_POST['last_name'] ?? '' ) );
        $phone   = sanitize_text_field( wp_unslash( $_POST['phone'] ?? '' ) );
        $coupon  = sanitize_text_field( wp_unslash( $_POST['coupon'] ?? '' ) );
        $commission = (float) wp_unslash( $_POST['commission'] ?? 0 );

        if ( ! is_email( $email ) ) {
            $notice = '<div class="notice notice-error"><p>' . esc_html__( 'Email invalide.', 'wheel-game' ) . '</p></div>';
        } elseif ( email_exists( $email ) ) {
            $notice = '<div class="notice notice-error"><p>' . esc_html__( 'Un utilisateur avec cet email existe déjà.', 'wheel-game' ) . '</p></div>';
        } else {
            $user_id = wp_create_user( $email, wp_generate_password( 16, true, true ), $email );
            if ( is_wp_error( $user_id ) ) {
                $notice = '<div class="notice notice-error"><p>' . esc_html( $user_id->get_error_message() ) . '</p></div>';
            } else {
                $user = new WP_User( $user_id );
                $user->set_role( Wheel_Game_Sales_Rep::ROLE );
                wp_update_user( [
                    'ID'           => $user_id,
                    'first_name'   => $fname,
                    'last_name'    => $lname,
                    'display_name' => trim( $fname . ' ' . $lname ) ?: $email,
                ] );
                update_user_meta( $user_id, Wheel_Game_Sales_Rep::META_PHONE, $phone );
                update_user_meta( $user_id, Wheel_Game_Sales_Rep::META_COMMISSION, max( 0, min( 100, $commission ) ) );
                if ( $coupon ) Wheel_Game_Sales_Rep::assign_coupon( $user_id, $coupon );

                // Envoi du lien de réinitialisation de mot de passe
                wp_new_user_notification( $user_id, null, 'user' );

                $notice = '<div class="notice notice-success"><p>' . esc_html__( 'Commercial créé. Un email lui a été envoyé pour définir son mot de passe.', 'wheel-game' ) . '</p></div>';
            }
        }
    }

    if ( isset( $_POST['update_rep'] ) ) {
        $user_id    = absint( $_POST['rep_id'] );
        $phone      = sanitize_text_field( wp_unslash( $_POST['phone'] ?? '' ) );
        $coupon     = sanitize_text_field( wp_unslash( $_POST['coupon'] ?? '' ) );
        $commission = (float) wp_unslash( $_POST['commission'] ?? 0 );

        update_user_meta( $user_id, Wheel_Game_Sales_Rep::META_PHONE, $phone );
        update_user_meta( $user_id, Wheel_Game_Sales_Rep::META_COMMISSION, max( 0, min( 100, $commission ) ) );
        $result = Wheel_Game_Sales_Rep::assign_coupon( $user_id, $coupon );
        if ( is_wp_error( $result ) ) {
            $notice = '<div class="notice notice-error"><p>' . esc_html( $result->get_error_message() ) . '</p></div>';
        } else {
            $notice = '<div class="notice notice-success"><p>' . esc_html__( 'Mise à jour effectuée.', 'wheel-game' ) . '</p></div>';
        }
    }
}

echo $notice;

// Édition
if ( $action === 'edit' && $edit_id ) :
    $rep = get_user_by( 'id', $edit_id );
    if ( ! $rep || ! in_array( Wheel_Game_Sales_Rep::ROLE, $rep->roles, true ) ) :
        echo '<div class="notice notice-error"><p>Commercial introuvable.</p></div>';
    else :
        $phone      = get_user_meta( $rep->ID, Wheel_Game_Sales_Rep::META_PHONE, true );
        $coupon     = get_user_meta( $rep->ID, Wheel_Game_Sales_Rep::META_COUPON, true );
        $commission = get_user_meta( $rep->ID, Wheel_Game_Sales_Rep::META_COMMISSION, true );
        $stats      = Wheel_Game_Sales_Rep::stats_for_rep( $rep->ID );
        $orders     = Wheel_Game_Sales_Rep::get_orders_for_rep( $rep->ID, 30 );
?>
<div class="wrap">
  <h1>💼 <?php echo esc_html( $rep->display_name ); ?>
    <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . Wheel_Game_Cpt::POST_TYPE . '&page=wheel-game-sales' ) ); ?>" class="page-title-action">↩ Retour</a>
  </h1>

  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin:20px 0">
    <div class="wg-kpi"><div class="val"><?php echo (int) $stats['total_orders']; ?></div><div class="lbl">Ventes totales</div></div>
    <div class="wg-kpi"><div class="val"><?php echo wc_price( $stats['total_revenue'] ); ?></div><div class="lbl">CA généré</div></div>
    <div class="wg-kpi" style="border-left:4px solid #00b894"><div class="val"><?php echo wc_price( $stats['total_commission'] ); ?></div><div class="lbl">Commissions totales</div></div>
    <div class="wg-kpi"><div class="val"><?php echo (int) $stats['orders_last_period']; ?></div><div class="lbl">Ventes (30j)</div></div>
    <div class="wg-kpi"><div class="val"><?php echo wc_price( $stats['commission_last_period'] ); ?></div><div class="lbl">Commission (30j)</div></div>
  </div>

  <h2>Paramètres</h2>
  <form method="post" style="max-width:600px">
    <?php wp_nonce_field( 'wheel_rep', 'wheel_rep_nonce' ); ?>
    <input type="hidden" name="rep_id" value="<?php echo esc_attr( $rep->ID ); ?>">
    <table class="form-table">
      <tr><th>Email</th><td><code><?php echo esc_html( $rep->user_email ); ?></code></td></tr>
      <tr><th>Téléphone</th><td><input type="text" name="phone" value="<?php echo esc_attr( $phone ); ?>" class="regular-text"></td></tr>
      <tr><th>Code coupon WC</th><td>
        <input type="text" name="coupon" value="<?php echo esc_attr( $coupon ); ?>" class="regular-text" placeholder="JEAN30">
        <p class="description"><?php esc_html_e( 'Doit exister dans WooCommerce → Marketing → Coupons. Un coupon = un commercial.', 'wheel-game' ); ?></p>
      </td></tr>
      <tr><th>Commission (%)</th><td>
        <input type="number" name="commission" value="<?php echo esc_attr( $commission ); ?>" step="0.1" min="0" max="100" class="small-text"> %
        <p class="description"><?php esc_html_e( 'Calculée sur le sous-total HT après remise du coupon.', 'wheel-game' ); ?></p>
      </td></tr>
    </table>
    <?php submit_button( __( 'Enregistrer', 'wheel-game' ), 'primary', 'update_rep' ); ?>
  </form>

  <h2>Ventes récentes (30 dernières)</h2>
  <?php if ( empty( $orders ) ) : ?>
    <p><?php esc_html_e( 'Aucune vente pour le moment.', 'wheel-game' ); ?></p>
  <?php else : ?>
  <table class="wp-list-table widefat striped">
    <thead><tr>
      <th>Commande</th><th>Date</th><th>Client</th><th>Montant HT</th><th>Commission</th><th>Statut</th>
    </tr></thead>
    <tbody>
    <?php foreach ( $orders as $o ) :
      $amt  = (float) $o->get_subtotal() - (float) $o->get_total_discount();
      $comm = (float) $o->get_meta( Wheel_Game_Sales_Rep::ORDER_META_COMMISSION ); ?>
    <tr>
      <td><a href="<?php echo esc_url( $o->get_edit_order_url() ); ?>">#<?php echo esc_html( $o->get_order_number() ); ?></a></td>
      <td><?php echo esc_html( $o->get_date_created()->format( 'Y-m-d H:i' ) ); ?></td>
      <td><?php echo esc_html( $o->get_formatted_billing_full_name() ); ?></td>
      <td><?php echo wc_price( $amt ); ?></td>
      <td style="color:#00b894;font-weight:700"><?php echo wc_price( $comm ); ?></td>
      <td><?php echo esc_html( wc_get_order_status_name( $o->get_status() ) ); ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>
<?php
    endif;
    return;
endif;

// Liste + formulaire de création
$reps = Wheel_Game_Sales_Rep::get_all();
?>
<div class="wrap">
  <h1>💼 <?php esc_html_e( 'Commerciaux', 'wheel-game' ); ?></h1>

  <h2><?php esc_html_e( 'Créer un nouveau commercial', 'wheel-game' ); ?></h2>
  <form method="post" style="max-width:700px;background:#fff;padding:20px;border:1px solid #e5e7eb;border-radius:8px;margin-bottom:30px">
    <?php wp_nonce_field( 'wheel_rep', 'wheel_rep_nonce' ); ?>
    <table class="form-table">
      <tr>
        <th>Prénom</th><td><input type="text" name="first_name" class="regular-text" required></td>
        <th>Nom</th><td><input type="text" name="last_name" class="regular-text" required></td>
      </tr>
      <tr>
        <th>Email *</th><td><input type="email" name="email" class="regular-text" required></td>
        <th>Téléphone</th><td><input type="text" name="phone" class="regular-text"></td>
      </tr>
      <tr>
        <th>Coupon WC *</th><td><input type="text" name="coupon" class="regular-text" placeholder="JEAN30" required></td>
        <th>Commission (%)</th><td><input type="number" name="commission" step="0.1" min="0" max="100" value="10" class="small-text"> %</td>
      </tr>
    </table>
    <?php submit_button( __( '👥 Créer le commercial', 'wheel-game' ), 'primary', 'create_rep' ); ?>
    <p class="description"><?php esc_html_e( 'Le commercial recevra un email pour définir son mot de passe et accéder à son espace sur', 'wheel-game' ); ?>
      <code><?php echo esc_url( home_url( '/espace-commercial/' ) ); ?></code>.</p>
  </form>

  <h2><?php esc_html_e( 'Liste des commerciaux', 'wheel-game' ); ?></h2>
  <?php if ( empty( $reps ) ) : ?>
    <p><?php esc_html_e( 'Aucun commercial créé pour le moment.', 'wheel-game' ); ?></p>
  <?php else : ?>
  <table class="wp-list-table widefat striped">
    <thead><tr>
      <th>Nom</th><th>Email</th><th>Coupon</th><th>Commission</th><th>CA total</th><th>Ventes (30j)</th><th></th>
    </tr></thead>
    <tbody>
    <?php foreach ( $reps as $rep ) :
      $coupon = get_user_meta( $rep->ID, Wheel_Game_Sales_Rep::META_COUPON, true );
      $commission = (float) get_user_meta( $rep->ID, Wheel_Game_Sales_Rep::META_COMMISSION, true );
      $stats = Wheel_Game_Sales_Rep::stats_for_rep( $rep->ID ); ?>
    <tr>
      <td><strong><?php echo esc_html( $rep->display_name ); ?></strong></td>
      <td><a href="mailto:<?php echo esc_attr( $rep->user_email ); ?>"><?php echo esc_html( $rep->user_email ); ?></a></td>
      <td><?php echo $coupon ? '<code>' . esc_html( $coupon ) . '</code>' : '<em>—</em>'; ?></td>
      <td><?php echo esc_html( number_format( $commission, 1 ) ); ?> %</td>
      <td><strong><?php echo wc_price( $stats['total_revenue'] ); ?></strong></td>
      <td><?php echo (int) $stats['orders_last_period']; ?></td>
      <td>
        <a href="<?php echo esc_url( add_query_arg( [ 'bvr_action' => 'edit', 'rep_id' => $rep->ID ] ) ); ?>" class="button button-small"><?php esc_html_e( 'Éditer', 'wheel-game' ); ?></a>
      </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>
