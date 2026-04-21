<?php
/**
 * Espace commercial — dashboard.
 * Variables : $user, $stats, $orders, $coupon, $commission
 */
if ( ! defined( 'ABSPATH' ) ) exit;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex,nofollow">
  <title><?php esc_html_e( 'Espace commercial', 'wheel-game' ); ?> — <?php echo esc_html( get_bloginfo( 'name' ) ); ?></title>
  <link rel="stylesheet" href="<?php echo esc_url( WHEEL_GAME_URL . 'assets/css/sales.css?v=' . WHEEL_GAME_VERSION ); ?>">
</head>
<body>

<header class="sc-header">
  <div class="sc-header-inner">
    <div class="sc-brand">💼 <strong><?php esc_html_e( 'Espace commercial', 'wheel-game' ); ?></strong></div>
    <nav class="sc-nav">
      <a href="<?php echo esc_url( home_url( '/espace-commercial/' ) ); ?>" class="is-active">📊 Dashboard</a>
      <a href="<?php echo esc_url( home_url( '/espace-commercial/audit/' ) ); ?>">🎯 Audit concurrentiel</a>
      <a href="<?php echo esc_url( home_url( '/espace-commercial/ranking/' ) ); ?>">🏆 Classement local</a>
    </nav>
    <div class="sc-user"><?php echo esc_html( $user->display_name ); ?> <a href="<?php echo esc_url( wp_logout_url( home_url() ) ); ?>">Déconnexion</a></div>
  </div>
</header>

<main class="sc-main">
  <h1><?php printf( esc_html__( 'Bonjour %s 👋', 'wheel-game' ), esc_html( $user->first_name ?: $user->display_name ) ); ?></h1>

  <div class="sc-kpi-grid">
    <div class="sc-kpi"><div class="val"><?php echo (int) $stats['total_orders']; ?></div><div class="lbl">Ventes totales</div></div>
    <div class="sc-kpi"><div class="val"><?php echo wc_price( $stats['total_revenue'] ); ?></div><div class="lbl">CA généré</div></div>
    <div class="sc-kpi sc-kpi-highlight"><div class="val"><?php echo wc_price( $stats['total_commission'] ); ?></div><div class="lbl">Commissions totales</div></div>
    <div class="sc-kpi"><div class="val"><?php echo (int) $stats['orders_last_period']; ?></div><div class="lbl">Ventes 30 derniers jours</div></div>
    <div class="sc-kpi"><div class="val"><?php echo wc_price( $stats['commission_last_period'] ); ?></div><div class="lbl">Commission 30j</div></div>
  </div>

  <div class="sc-info">
    <div>
      <strong>🎟️ Votre code promo :</strong>
      <?php if ( $coupon ) : ?>
        <code class="sc-coupon"><?php echo esc_html( $coupon ); ?></code>
      <?php else : ?>
        <em><?php esc_html_e( 'Aucun code attribué — contactez l\'administrateur.', 'wheel-game' ); ?></em>
      <?php endif; ?>
    </div>
    <div>
      <strong>💰 Commission :</strong> <?php echo esc_html( number_format( $commission, 1 ) ); ?> %
    </div>
  </div>

  <h2><?php esc_html_e( 'Mes ventes récentes', 'wheel-game' ); ?></h2>

  <?php if ( empty( $orders ) ) : ?>
    <p class="sc-empty"><?php esc_html_e( 'Aucune vente enregistrée pour le moment. Dès qu\'un client utilisera votre code, la vente apparaîtra ici.', 'wheel-game' ); ?></p>
  <?php else : ?>
    <table class="sc-table">
      <thead><tr>
        <th>Date</th><th>Commande</th><th>Client</th><th>Montant HT</th><th>Commission</th><th>Statut</th>
      </tr></thead>
      <tbody>
      <?php foreach ( $orders as $o ) :
        $amt  = (float) $o->get_subtotal() - (float) $o->get_total_discount();
        $comm = (float) $o->get_meta( Wheel_Game_Sales_Rep::ORDER_META_COMMISSION ); ?>
      <tr>
        <td><?php echo esc_html( $o->get_date_created()->format( 'd/m/Y' ) ); ?></td>
        <td>#<?php echo esc_html( $o->get_order_number() ); ?></td>
        <td><?php echo esc_html( $o->get_formatted_billing_full_name() ); ?></td>
        <td><?php echo wc_price( $amt ); ?></td>
        <td class="sc-comm"><?php echo wc_price( $comm ); ?></td>
        <td><span class="sc-status sc-status-<?php echo esc_attr( $o->get_status() ); ?>"><?php echo esc_html( wc_get_order_status_name( $o->get_status() ) ); ?></span></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</main>

</body>
</html>
