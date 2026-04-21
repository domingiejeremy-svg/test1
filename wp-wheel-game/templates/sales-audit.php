<?php
/**
 * Espace commercial — Audit concurrentiel PDF imprimable.
 * Variables : $user, $print_mode
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$nonce = wp_create_nonce( 'wheel_sales' );
$ajax  = admin_url( 'admin-ajax.php' );

// Si mode print : design sobre, prêt à imprimer en PDF
$print_class = $print_mode ? 'sc-print-mode' : '';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex,nofollow">
  <title><?php esc_html_e( 'Audit concurrentiel', 'wheel-game' ); ?></title>
  <link rel="stylesheet" href="<?php echo esc_url( WHEEL_GAME_URL . 'assets/css/sales.css?v=' . WHEEL_GAME_VERSION ); ?>">
</head>
<body class="<?php echo esc_attr( $print_class ); ?>">

<?php if ( ! $print_mode ) : ?>
<header class="sc-header">
  <div class="sc-header-inner">
    <div class="sc-brand">💼 <strong><?php esc_html_e( 'Espace commercial', 'wheel-game' ); ?></strong></div>
    <nav class="sc-nav">
      <a href="<?php echo esc_url( home_url( '/espace-commercial/' ) ); ?>">📊 Dashboard</a>
      <a href="<?php echo esc_url( home_url( '/espace-commercial/audit/' ) ); ?>" class="is-active">🎯 Audit concurrentiel</a>
      <a href="<?php echo esc_url( home_url( '/espace-commercial/ranking/' ) ); ?>">🏆 Classement local</a>
    </nav>
    <div class="sc-user"><?php echo esc_html( $user->display_name ); ?> <a href="<?php echo esc_url( wp_logout_url( home_url() ) ); ?>">Déconnexion</a></div>
  </div>
</header>
<?php endif; ?>

<main class="sc-main">

<?php if ( ! $print_mode ) : ?>
  <h1>🎯 <?php esc_html_e( 'Audit concurrentiel', 'wheel-game' ); ?></h1>
  <p><?php esc_html_e( 'Entrez le Place ID du prospect + les Place IDs de 2 à 5 concurrents locaux. Le rapport compare leurs notes et volumes d\'avis, classement, écart.', 'wheel-game' ); ?>
    <a href="https://developers.google.com/maps/documentation/places/web-service/place-id" target="_blank"><?php esc_html_e( 'Trouver un Place ID →', 'wheel-game' ); ?></a>
  </p>

  <div class="sc-audit-form">
    <div class="sc-field">
      <label><?php esc_html_e( 'Nom du prospect', 'wheel-game' ); ?></label>
      <input type="text" id="audit-prospect-name" placeholder="Ex: Boulangerie Martin, Paris 3e">
    </div>
    <div class="sc-field">
      <label><?php esc_html_e( 'Place ID du prospect *', 'wheel-game' ); ?></label>
      <input type="text" id="audit-prospect-id" placeholder="ChIJxxxxxxxx">
    </div>
    <div class="sc-field">
      <label><?php esc_html_e( 'Place IDs des concurrents (2 à 5)', 'wheel-game' ); ?></label>
      <textarea id="audit-competitors" rows="5" placeholder="Un Place ID par ligne&#10;ChIJaaaaaaa&#10;ChIJbbbbbbb&#10;ChIJccccccc"></textarea>
    </div>
    <button type="button" class="sc-btn-primary" id="audit-run">🎯 <?php esc_html_e( 'Générer l\'audit', 'wheel-game' ); ?></button>
    <div class="sc-loading" id="audit-loading" style="display:none"><?php esc_html_e( 'Récupération des données Google…', 'wheel-game' ); ?></div>
    <div class="sc-error" id="audit-error" style="display:none"></div>
  </div>
<?php endif; ?>

  <div id="audit-report" <?php echo $print_mode ? '' : 'style="display:none"'; ?>>
    <div class="sc-report-header">
      <h1 id="audit-title">📊 <?php esc_html_e( 'Audit concurrentiel', 'wheel-game' ); ?></h1>
      <p id="audit-subtitle"></p>
      <div class="sc-report-brand">
        <strong>Boostez Votre Réputation</strong>
        <span><?php echo esc_html( gmdate( 'd/m/Y' ) ); ?></span>
      </div>
    </div>

    <h2>🏢 <?php esc_html_e( 'Votre établissement', 'wheel-game' ); ?></h2>
    <div id="audit-prospect-card"></div>

    <h2>🥊 <?php esc_html_e( 'Classement local vs concurrents', 'wheel-game' ); ?></h2>
    <table class="sc-report-table">
      <thead>
        <tr>
          <th>#</th><th>Établissement</th><th>Note Google</th><th>Nombre d'avis</th><th>Visualisation</th>
        </tr>
      </thead>
      <tbody id="audit-ranking-body"></tbody>
    </table>

    <h2>📈 <?php esc_html_e( 'Écarts à combler', 'wheel-game' ); ?></h2>
    <div id="audit-gap"></div>

    <?php if ( ! $print_mode ) : ?>
      <div class="sc-report-actions">
        <button type="button" class="sc-btn-primary" onclick="window.print()">🖨️ <?php esc_html_e( 'Imprimer / Enregistrer en PDF', 'wheel-game' ); ?></button>
      </div>
    <?php endif; ?>

    <footer class="sc-report-footer">
      <p><?php esc_html_e( 'Données en temps réel fournies par Google Places API. Snapshot généré le', 'wheel-game' ); ?> <?php echo esc_html( wp_date( 'd/m/Y à H:i' ) ); ?>.</p>
      <p><?php esc_html_e( 'Rapport établi par Boostez Votre Réputation.', 'wheel-game' ); ?></p>
    </footer>
  </div>

</main>

<script>
window.SALES_AUDIT = {
    ajaxUrl: <?php echo wp_json_encode( $ajax ); ?>,
    nonce:   <?php echo wp_json_encode( $nonce ); ?>,
    printMode: <?php echo $print_mode ? 'true' : 'false'; ?>,
};
</script>
<script src="<?php echo esc_url( WHEEL_GAME_URL . 'assets/js/sales-audit.js?v=' . WHEEL_GAME_VERSION ); ?>"></script>

</body>
</html>
