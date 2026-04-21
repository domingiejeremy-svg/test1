<?php
/**
 * Espace commercial — Classement local.
 * Même principe que l'audit mais focalisé sur le rang sans les écarts détaillés.
 * Variables : $user
 */
if ( ! defined( 'ABSPATH' ) ) exit;
$nonce = wp_create_nonce( 'wheel_sales' );
$ajax  = admin_url( 'admin-ajax.php' );
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex,nofollow">
  <title><?php esc_html_e( 'Classement local', 'wheel-game' ); ?></title>
  <link rel="stylesheet" href="<?php echo esc_url( WHEEL_GAME_URL . 'assets/css/sales.css?v=' . WHEEL_GAME_VERSION ); ?>">
</head>
<body>

<header class="sc-header">
  <div class="sc-header-inner">
    <div class="sc-brand">💼 <strong><?php esc_html_e( 'Espace commercial', 'wheel-game' ); ?></strong></div>
    <nav class="sc-nav">
      <a href="<?php echo esc_url( home_url( '/espace-commercial/' ) ); ?>">📊 Dashboard</a>
      <a href="<?php echo esc_url( home_url( '/espace-commercial/audit/' ) ); ?>">🎯 Audit concurrentiel</a>
      <a href="<?php echo esc_url( home_url( '/espace-commercial/ranking/' ) ); ?>" class="is-active">🏆 Classement local</a>
    </nav>
    <div class="sc-user"><?php echo esc_html( $user->display_name ); ?> <a href="<?php echo esc_url( wp_logout_url( home_url() ) ); ?>">Déconnexion</a></div>
  </div>
</header>

<main class="sc-main">
  <h1>🏆 <?php esc_html_e( 'Classement local', 'wheel-game' ); ?></h1>
  <p><?php esc_html_e( 'Même logique que l\'audit mais en mode simplifié — parfait pour une prise de contact rapide. Entrez le prospect + ses concurrents du même quartier.', 'wheel-game' ); ?></p>

  <div class="sc-audit-form">
    <div class="sc-field">
      <label><?php esc_html_e( 'Place ID du prospect *', 'wheel-game' ); ?></label>
      <input type="text" id="rank-prospect-id" placeholder="ChIJxxxxxxxx">
    </div>
    <div class="sc-field">
      <label><?php esc_html_e( 'Place IDs des concurrents à comparer', 'wheel-game' ); ?></label>
      <textarea id="rank-competitors" rows="4" placeholder="Un Place ID par ligne"></textarea>
    </div>
    <button type="button" class="sc-btn-primary" id="rank-run">🏆 <?php esc_html_e( 'Afficher le classement', 'wheel-game' ); ?></button>
    <div class="sc-loading" id="rank-loading" style="display:none"><?php esc_html_e( 'Chargement…', 'wheel-game' ); ?></div>
    <div class="sc-error" id="rank-error" style="display:none"></div>
  </div>

  <div id="rank-result" style="display:none">
    <div class="sc-rank-hero">
      <div class="sc-rank-position" id="rank-position">#?</div>
      <div>
        <h2 id="rank-title"></h2>
        <p id="rank-subtitle"></p>
      </div>
    </div>

    <table class="sc-report-table">
      <thead><tr><th>#</th><th>Établissement</th><th>Note</th><th>Avis</th></tr></thead>
      <tbody id="rank-list-body"></tbody>
    </table>
  </div>
</main>

<script>
window.SALES_RANKING = {
    ajaxUrl: <?php echo wp_json_encode( $ajax ); ?>,
    nonce:   <?php echo wp_json_encode( $nonce ); ?>,
};
</script>
<script src="<?php echo esc_url( WHEEL_GAME_URL . 'assets/js/sales-ranking.js?v=' . WHEEL_GAME_VERSION ); ?>"></script>
</body>
</html>
