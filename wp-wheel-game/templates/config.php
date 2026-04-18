<?php
/**
 * Page publique : configuration de la roue par le client.
 * Variables : $campaign, $c, $offer_slug, $offer, $is_first, $is_locked, $mods_remaining, $campaign_url, $token
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$nonce = wp_create_nonce( 'wheel_config' );
$ajax  = admin_url( 'admin-ajax.php' );
$prizes_json = esc_attr( wp_json_encode( $c['prizes'] ) );
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex,nofollow">
  <title><?php esc_html_e( 'Configurer ma roue', 'wheel-game' ); ?> — <?php echo esc_html( get_bloginfo( 'name' ) ); ?></title>
  <link rel="stylesheet" href="<?php echo esc_url( WHEEL_GAME_URL . 'assets/css/config.css?v=' . WHEEL_GAME_VERSION ); ?>">
</head>
<body>

<header class="cfg-header">
  <div class="cfg-header-inner">
    <div class="cfg-brand">🎡 <strong><?php echo esc_html( get_bloginfo( 'name' ) ); ?></strong></div>
    <div class="cfg-badge cfg-badge-<?php echo esc_attr( $offer_slug ); ?>">
      <?php echo esc_html( $offer['emoji'] . ' ' . $offer['label'] ); ?>
    </div>
  </div>
</header>

<main class="cfg-main">

  <?php if ( $is_locked ) : ?>
    <div class="cfg-locked">
      <h2>🔒 <?php esc_html_e( 'Quota de modifications atteint', 'wheel-game' ); ?></h2>
      <p><?php printf(
        esc_html__( 'Votre offre %s inclut %d modifications par an. Vous les avez toutes utilisées.', 'wheel-game' ),
        esc_html( $offer['label'] ),
        (int) $offer['mods_per_year']
      ); ?></p>
      <p><?php esc_html_e( 'Contactez-nous pour augmenter votre quota ou upgrader votre offre.', 'wheel-game' ); ?></p>
      <a class="cfg-btn" href="mailto:contact@boostezvotrereputation.fr">📧 <?php esc_html_e( 'Nous contacter', 'wheel-game' ); ?></a>
    </div>
  <?php else : ?>

  <div class="cfg-hero">
    <h1><?php echo $is_first
      ? esc_html__( 'Bienvenue ! Paramétrez votre roue en 3 minutes', 'wheel-game' )
      : esc_html__( 'Modifier votre roue', 'wheel-game' ); ?></h1>
    <?php if ( $is_first ) : ?>
      <p><?php esc_html_e( 'Personnalisez vos cadeaux, vos couleurs et ajoutez votre logo. Quand c\'est prêt, cliquez sur « Valider et publier ».', 'wheel-game' ); ?></p>
    <?php else : ?>
      <p>
        <?php if ( $offer['mods_per_year'] === -1 ) : ?>
          <?php esc_html_e( 'Modifications illimitées incluses dans votre offre.', 'wheel-game' ); ?>
        <?php else : ?>
          <?php printf(
            esc_html__( 'Il vous reste %d modification(s) sur %d cette année.', 'wheel-game' ),
            (int) $mods_remaining, (int) $offer['mods_per_year']
          ); ?>
        <?php endif; ?>
      </p>
    <?php endif; ?>
  </div>

  <div class="cfg-layout">

    <!-- LEFT : FORM -->
    <section class="cfg-form">

      <div class="cfg-block">
        <h2>🏷️ <?php esc_html_e( 'Nom de votre roue', 'wheel-game' ); ?></h2>
        <input type="text" id="cfg-title" value="<?php echo esc_attr( $campaign->post_title ); ?>"
               placeholder="<?php esc_attr_e( 'Ex : Boulangerie Martin', 'wheel-game' ); ?>">
        <p class="cfg-hint"><?php esc_html_e( 'Nom interne — visible seulement dans votre dashboard.', 'wheel-game' ); ?></p>
      </div>

      <div class="cfg-block">
        <h2>🎁 <?php esc_html_e( 'Vos cadeaux et probabilités', 'wheel-game' ); ?></h2>
        <div class="cfg-prizes-hdr">
          <span>Emoji</span><span>Ligne 1</span><span>Ligne 2</span><span>%</span><span>Couleur</span><span></span>
        </div>
        <div id="cfg-prizes-list"></div>
        <div id="cfg-weight-bar"></div>
        <input type="hidden" id="cfg-prizes-json" value="<?php echo $prizes_json; ?>">
        <button type="button" id="cfg-add-prize">+ <?php esc_html_e( 'Ajouter un cadeau', 'wheel-game' ); ?></button>
        <p class="cfg-hint" style="display:flex;justify-content:space-between;gap:12px;margin-top:10px">
          <span><?php esc_html_e( 'La somme idéale est 100%. Vous pouvez mettre moins (la roue compensera).', 'wheel-game' ); ?></span>
          <span id="cfg-percent-total" style="white-space:nowrap;font-weight:700"><?php esc_html_e( 'Total : —', 'wheel-game' ); ?></span>
        </p>
      </div>

      <div class="cfg-block">
        <h2>🎨 <?php esc_html_e( 'Couleurs', 'wheel-game' ); ?></h2>
        <div class="cfg-colors">
          <label>
            <span><?php esc_html_e( 'Fond principal', 'wheel-game' ); ?></span>
            <input type="color" id="cfg-bg1" value="<?php echo esc_attr( $c['bg_color_1'] ); ?>">
          </label>
          <label>
            <span><?php esc_html_e( 'Fond secondaire', 'wheel-game' ); ?></span>
            <input type="color" id="cfg-bg2" value="<?php echo esc_attr( $c['bg_color_2'] ); ?>">
          </label>
          <label>
            <span><?php esc_html_e( 'Couleur d\'accent', 'wheel-game' ); ?></span>
            <input type="color" id="cfg-accent" value="<?php echo esc_attr( $c['accent_color'] ); ?>">
          </label>
        </div>
        <p class="cfg-hint"><?php esc_html_e( 'La couleur d\'accent est utilisée pour le titre, la flèche et le bouton Google.', 'wheel-game' ); ?></p>
      </div>

      <div class="cfg-block">
        <h2>🖼️ <?php esc_html_e( 'Votre logo', 'wheel-game' ); ?></h2>
        <div class="cfg-logo-zone" id="cfg-logo-zone">
          <img id="cfg-logo-preview" src="<?php echo esc_url( $c['logo'] ); ?>"
               style="<?php echo $c['logo'] ? '' : 'display:none;'; ?>"
               alt="Logo">
          <div class="cfg-logo-empty" id="cfg-logo-empty" <?php echo $c['logo'] ? 'style="display:none"' : ''; ?>>
            <span>🖼️</span>
            <p><?php esc_html_e( 'Glissez votre logo ici ou cliquez pour sélectionner', 'wheel-game' ); ?></p>
            <p class="cfg-hint"><?php esc_html_e( 'JPG, PNG, WebP, SVG — 2 Mo max, carré de préférence', 'wheel-game' ); ?></p>
          </div>
          <input type="file" id="cfg-logo-input" accept="image/jpeg,image/png,image/webp,image/svg+xml" style="display:none">
        </div>
        <input type="hidden" id="cfg-logo-url" value="<?php echo esc_attr( $c['logo'] ); ?>">
        <div style="display:flex;gap:8px;margin-top:8px">
          <button type="button" class="cfg-btn-mini" id="cfg-logo-btn"><?php esc_html_e( 'Choisir un logo', 'wheel-game' ); ?></button>
          <button type="button" class="cfg-btn-mini cfg-btn-danger" id="cfg-logo-remove" <?php echo $c['logo'] ? '' : 'style="display:none"'; ?>><?php esc_html_e( 'Supprimer', 'wheel-game' ); ?></button>
        </div>
        <p class="cfg-hint"><?php esc_html_e( 'Pas de logo sous la main ? Pas de problème, on pourra vous aider à l\'uploader plus tard.', 'wheel-game' ); ?></p>
      </div>

    </section>

    <!-- RIGHT : PREVIEW -->
    <aside class="cfg-preview-col">
      <div class="cfg-preview-sticky">
        <h3>👁️ <?php esc_html_e( 'Aperçu en temps réel', 'wheel-game' ); ?></h3>
        <div class="cfg-preview-box" id="cfg-preview-box">
          <canvas id="cfg-preview-canvas" width="280" height="280"></canvas>
        </div>

        <button type="button" class="cfg-btn cfg-btn-primary" id="cfg-validate">
          <?php echo $is_first ? esc_html__( '✨ Valider et publier ma roue', 'wheel-game' ) : esc_html__( '💾 Enregistrer les modifications', 'wheel-game' ); ?>
        </button>
        <p class="cfg-hint" style="text-align:center;margin-top:8px">
          <?php if ( $is_first ) : ?>
            <?php esc_html_e( 'Une fois validée, votre roue sera publiée et accessible par votre lien public.', 'wheel-game' ); ?>
          <?php else : ?>
            <?php esc_html_e( 'Cela décomptera une modification de votre quota.', 'wheel-game' ); ?>
          <?php endif; ?>
        </p>

        <div id="cfg-save-status" style="text-align:center;font-size:0.85rem;color:#666;margin-top:6px"></div>
      </div>
    </aside>

  </div>
  <?php endif; ?>

</main>

<!-- MODAL de récap -->
<div class="cfg-modal" id="cfg-modal-confirm" style="display:none">
  <div class="cfg-modal-box">
    <h2><?php esc_html_e( 'Vérifiez avant publication', 'wheel-game' ); ?></h2>
    <p class="cfg-modal-sub"><?php esc_html_e( 'Une fois validée, votre roue sera accessible au public. Vous pourrez la modifier plus tard selon votre offre.', 'wheel-game' ); ?></p>

    <div class="cfg-recap" id="cfg-recap"></div>

    <div class="cfg-modal-actions">
      <button type="button" class="cfg-btn-secondary" id="cfg-modal-cancel"><?php esc_html_e( 'Retour modifier', 'wheel-game' ); ?></button>
      <button type="button" class="cfg-btn cfg-btn-primary" id="cfg-modal-confirm-btn">
        <?php echo $is_first ? esc_html__( '🚀 Publier ma roue', 'wheel-game' ) : esc_html__( '✓ Valider la modification', 'wheel-game' ); ?>
      </button>
    </div>
  </div>
</div>

<!-- MODAL succès -->
<div class="cfg-modal" id="cfg-modal-success" style="display:none">
  <div class="cfg-modal-box">
    <div class="cfg-success-icon">🎉</div>
    <h2 id="cfg-success-title"><?php esc_html_e( 'Votre roue est en ligne !', 'wheel-game' ); ?></h2>
    <p id="cfg-success-msg"><?php esc_html_e( 'Voici votre lien public — gardez-le précieusement.', 'wheel-game' ); ?></p>
    <div class="cfg-success-url">
      <input type="text" id="cfg-success-url-input" readonly>
      <button type="button" id="cfg-copy-url"><?php esc_html_e( 'Copier', 'wheel-game' ); ?></button>
    </div>
    <a class="cfg-btn cfg-btn-primary" id="cfg-goto-wheel" href="#" target="_blank">
      🎡 <?php esc_html_e( 'Voir ma roue en ligne', 'wheel-game' ); ?>
    </a>
    <p class="cfg-hint" style="text-align:center;margin-top:16px">
      <?php esc_html_e( 'Nous allons maintenant créer vos supports visuels (flyer, chevalet, vinyle). Nous vous contactons dans les prochaines heures.', 'wheel-game' ); ?>
    </p>
  </div>
</div>

<script>
window.WHEEL_CONFIG = {
    campaignId:  <?php echo (int) $campaign->ID; ?>,
    token:       <?php echo wp_json_encode( $token ); ?>,
    nonce:       <?php echo wp_json_encode( $nonce ); ?>,
    ajaxUrl:     <?php echo wp_json_encode( $ajax ); ?>,
    isFirst:     <?php echo $is_first ? 'true' : 'false'; ?>,
    offerLabel:  <?php echo wp_json_encode( $offer['label'] ); ?>,
    campaignUrl: <?php echo wp_json_encode( $campaign_url ); ?>,
    defaultPrizes: <?php echo wp_json_encode( Wheel_Game_Campaign::default_prizes() ); ?>,
};
</script>
<script src="<?php echo esc_url( WHEEL_GAME_URL . 'assets/js/config.js?v=' . WHEEL_GAME_VERSION ); ?>"></script>
</body>
</html>
