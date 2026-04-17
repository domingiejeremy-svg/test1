<?php
/**
 * Template public : Roue + formulaire lead.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$campaign_id = get_the_ID();
$c = Wheel_Game_Campaign::get( $campaign_id );

if ( ! $c['active'] ) { wp_redirect( home_url() ); exit; }

$ip_hash       = Wheel_Game_Security::ip_hash();
$is_admin      = is_user_logged_in() && current_user_can( 'manage_options' );
$preview_mode  = $is_admin && ( $_GET['preview_as_user'] ?? '' ) === '1';
$is_admin_test = $is_admin && ! $preview_mode;
$already_played = false;
$played_data    = null;

if ( ! $is_admin_test ) {
    $cookie = $_COOKIE[ 'wheel_played_' . $campaign_id ] ?? '';
    if ( $cookie ) {
        $decoded = Wheel_Game_Security::verify_token( $cookie );
        if ( is_array( $decoded ) && (int) ( $decoded['campaign_id'] ?? 0 ) === $campaign_id ) {
            $already_played = true;
            $played_data = $decoded;
        }
    }
    if ( ! $already_played && Wheel_Game_Security::has_played( $campaign_id, $ip_hash ) ) {
        $already_played = true;
    }
}

$blocked_reason = '';
if ( $c['expired'] )            $blocked_reason = __( 'Cette campagne est terminée.', 'wheel-game' );
elseif ( $c['quota_reached'] )  $blocked_reason = __( 'Le nombre maximum de participations est atteint.', 'wheel-game' );

$has_lead_cookie = false;
if ( $c['lead_required'] ) {
    $lead_cookie = $_COOKIE[ 'wheel_lead_' . $campaign_id ] ?? '';
    if ( $lead_cookie ) {
        $decoded = Wheel_Game_Security::verify_token( $lead_cookie );
        if ( is_array( $decoded ) && (int) ( $decoded['campaign_id'] ?? 0 ) === $campaign_id ) {
            $has_lead_cookie = true;
        }
    }
}
$show_lead_form = $c['lead_required'] && ! $has_lead_cookie && ! $is_admin_test && ! $already_played && ! $blocked_reason;

$js_data = [
    'campaignId'    => $campaign_id,
    'prizes'        => $c['prizes'],
    'playNonce'     => wp_create_nonce( 'wheel_play' ),
    'leadNonce'     => wp_create_nonce( 'wheel_lead' ),
    'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
    'rewardUrl'     => get_permalink( $campaign_id ) . '?step=reward',
    'alreadyPlayed' => $already_played && ! $is_admin_test,
    'playedData'    => $played_data,
    'isAdminTest'   => $is_admin_test,
    'previewMode'   => $preview_mode,
    'leadRequired'  => $c['lead_required'] && ! $has_lead_cookie,
    'showLeadForm'  => $show_lead_form,
    'leadFields'    => $c['lead_fields'],
    'colors'        => [
        'bg1'    => $c['bg_color_1'],
        'bg2'    => $c['bg_color_2'],
        'accent' => $c['accent_color'],
    ],
    'sound'         => $c['sound_enabled'],
    'blocked'       => $blocked_reason,
];

$wheel_url = get_permalink( $campaign_id );
$toggle_test_url = $is_admin_test ? add_query_arg( 'preview_as_user', '1', $wheel_url ) : $wheel_url;
$font_family = esc_attr( $c['font_family'] );

$lead_labels = [
    'first_name' => [ __( 'Prénom', 'wheel-game' ),    'text',  'given-name' ],
    'last_name'  => [ __( 'Nom', 'wheel-game' ),       'text',  'family-name' ],
    'email'      => [ __( 'Email', 'wheel-game' ),     'email', 'email' ],
    'phone'      => [ __( 'Téléphone', 'wheel-game' ), 'tel',   'tel' ],
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex,nofollow">
  <title><?php echo esc_html( $c['title'] ); ?> — <?php echo esc_html( get_bloginfo( 'name' ) ); ?></title>
  <link rel="stylesheet" href="<?php echo esc_url( WHEEL_GAME_URL . 'assets/css/wheel.css?v=' . WHEEL_GAME_VERSION ); ?>">
  <style>
    :root {
      --wg-bg1: <?php echo esc_attr( $c['bg_color_1'] ); ?>;
      --wg-bg2: <?php echo esc_attr( $c['bg_color_2'] ); ?>;
      --wg-accent: <?php echo esc_attr( $c['accent_color'] ); ?>;
      --wg-font: '<?php echo $font_family; ?>', 'Segoe UI', Tahoma, sans-serif;
    }
  </style>
</head>
<body>
<div class="confetti-container" id="confettiContainer"></div>

<div class="container">

  <?php if ( $is_admin_test ) : ?>
    <div style="text-align:center">
      <div class="test-badge">
        <span class="dot"></span><?php esc_html_e( 'Mode test admin', 'wheel-game' ); ?>
        <a href="<?php echo esc_url( $toggle_test_url ); ?>">→ <?php esc_html_e( 'Voir comme visiteur', 'wheel-game' ); ?></a>
      </div>
    </div>
  <?php elseif ( $preview_mode ) : ?>
    <div style="text-align:center">
      <div class="preview-badge">
        👁️ <?php esc_html_e( 'Aperçu visiteur', 'wheel-game' ); ?>
        <a href="<?php echo esc_url( $wheel_url ); ?>">↩ <?php esc_html_e( 'Retour test', 'wheel-game' ); ?></a>
      </div>
    </div>
  <?php endif; ?>

  <?php if ( $blocked_reason ) : ?>
    <div class="wheel-card" style="text-align:center">
      <div style="font-size:50px;margin-bottom:10px">⛔</div>
      <h1 style="color:#fff"><?php echo esc_html( $blocked_reason ); ?></h1>
      <p class="subtitle"><?php esc_html_e( 'Revenez bientôt pour une nouvelle campagne !', 'wheel-game' ); ?></p>
    </div>
  <?php elseif ( $show_lead_form ) : ?>
    <div class="wheel-card lead-card">
      <span class="gift-icon">🎁</span>
      <h1><?php echo esc_html( $c['lead_title'] ); ?></h1>
      <p class="subtitle"><?php echo nl2br( esc_html( $c['lead_subtitle'] ) ); ?></p>

      <form id="lead-form" autocomplete="on">
        <?php foreach ( $c['lead_fields'] as $field ) :
          if ( ! isset( $lead_labels[ $field ] ) ) continue;
          [ $label, $type, $auto ] = $lead_labels[ $field ]; ?>
          <label class="lead-label">
            <span><?php echo esc_html( $label ); ?> *</span>
            <input type="<?php echo esc_attr( $type ); ?>" name="<?php echo esc_attr( $field ); ?>"
                   autocomplete="<?php echo esc_attr( $auto ); ?>" required>
          </label>
        <?php endforeach; ?>

        <label class="lead-consent">
          <input type="checkbox" name="consent" value="1">
          <span><?php echo esc_html( $c['lead_consent_text'] ); ?></span>
        </label>

        <div class="lead-error" id="lead-error" style="display:none"></div>
        <button type="submit" class="lead-submit"><?php echo esc_html( $c['lead_button'] ); ?></button>
      </form>
    </div>
  <?php else : ?>
    <div class="wheel-card">
      <div class="header">
        <span class="gift-icon">🎁</span>
        <h1><?php echo esc_html( $c['title'] ); ?></h1>
        <p class="subtitle"><?php echo esc_html( $c['subtitle'] ); ?></p>
      </div>

      <div class="wheel-wrapper">
        <div class="arrow"></div>
        <canvas id="wheel" width="330" height="330"></canvas>
        <button class="spin-btn" id="spinBtn" type="button">GO</button>
      </div>

      <div class="result-banner" id="resultBanner">
        <div class="label"><?php esc_html_e( 'Vous avez gagné', 'wheel-game' ); ?></div>
        <div class="prize-name" id="prizeLabel"></div>
      </div>

      <div class="redirect-countdown" id="redirectCountdown">
        ✨ <?php esc_html_e( 'Votre cadeau vous attend', 'wheel-game' ); ?>
        <span class="dots"><span class="dot-anim"></span><span class="dot-anim"></span><span class="dot-anim"></span></span>
      </div>

      <button class="replay-btn" id="replayBtn" type="button">🔄 <?php esc_html_e( 'Rejouer (test)', 'wheel-game' ); ?></button>

      <?php if ( $already_played && ! $is_admin_test ) : ?>
        <p class="already-played-msg"><?php esc_html_e( 'Vous avez déjà participé à ce tirage.', 'wheel-game' ); ?></p>
      <?php endif; ?>

      <?php if ( $is_admin_test ) : ?>
        <div class="test-stats" id="testStats">
          <h4>📊 <?php esc_html_e( 'Stats session de test', 'wheel-game' ); ?></h4>
          <div id="statsRows"></div>
          <div class="total-line" id="statsTotal">0 <?php esc_html_e( 'tirage(s)', 'wheel-game' ); ?></div>
        </div>
      <?php endif; ?>

      <p class="footer-note"><?php echo esc_html( $c['footer'] ); ?></p>
    </div>
  <?php endif; ?>
</div>

<script>window.WHEEL_DATA = <?php echo wp_json_encode( $js_data ); ?>;</script>
<script src="<?php echo esc_url( WHEEL_GAME_URL . 'assets/js/wheel.js?v=' . WHEEL_GAME_VERSION ); ?>"></script>
</body>
</html>
