<?php
/**
 * Template public : Récompense après tirage.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$campaign_id = get_the_ID();
$c = Wheel_Game_Campaign::get( $campaign_id );

$is_admin_rw = is_user_logged_in() && current_user_can( 'manage_options' );
$cookie      = $_COOKIE[ 'wheel_played_' . $campaign_id ] ?? '';
$play_token  = '';
$decoded     = null;
if ( $cookie ) {
    $decoded = Wheel_Game_Security::verify_token( $cookie );
    if ( is_array( $decoded ) && (int) ( $decoded['campaign_id'] ?? 0 ) === $campaign_id ) {
        $play_token = $cookie;
    }
}

if ( ! $play_token && ! $is_admin_rw ) {
    wp_redirect( get_permalink( $campaign_id ) ); exit;
}

$prize = '';
if ( is_array( $decoded ) && ! empty( $decoded['label'] ) ) {
    $prize = sanitize_text_field( $decoded['label'] );
} else {
    $prize = sanitize_text_field( wp_unslash( $_GET['prize'] ?? '🎁 Cadeau surprise' ) );
}

$nonce_click  = wp_create_nonce( 'wheel_click' );
$font_family  = esc_attr( $c['font_family'] );
$wheel_url_rw = get_permalink( $campaign_id );
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex,nofollow">
  <title><?php esc_html_e( 'Votre cadeau vous attend !', 'wheel-game' ); ?> — <?php echo esc_html( get_bloginfo( 'name' ) ); ?></title>
  <link rel="stylesheet" href="<?php echo esc_url( WHEEL_GAME_URL . 'assets/css/reward.css?v=' . WHEEL_GAME_VERSION ); ?>">
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
<div class="container">

  <?php if ( $is_admin_rw ) : ?>
    <div style="text-align:center;margin-bottom:12px">
      <div class="preview-badge">
        👁️ <?php esc_html_e( 'Aperçu admin — page cadeau', 'wheel-game' ); ?>
        <a href="<?php echo esc_url( $wheel_url_rw ); ?>">↩ <?php esc_html_e( 'Retour roue', 'wheel-game' ); ?></a>
      </div>
    </div>
  <?php endif; ?>

  <div class="prize-card">
    <?php if ( $c['logo'] ) : ?>
      <img src="<?php echo esc_url( $c['logo'] ); ?>" class="brand-logo" alt="">
    <?php endif; ?>

    <p class="congrats"><?php esc_html_e( 'Félicitations !', 'wheel-game' ); ?></p>
    <h1><?php esc_html_e( 'Vous avez gagné', 'wheel-game' ); ?></h1>
    <div class="prize-badge"><?php echo esc_html( $prize ); ?></div>
    <p class="validity"><?php echo esc_html( $c['validity'] ); ?></p>

    <div class="separator"><span><?php esc_html_e( 'on vous demande juste une faveur 🙏', 'wheel-game' ); ?></span></div>

    <div class="review-section">
      <p class="review-title"><?php echo esc_html( $c['review_title'] ); ?></p>
      <p class="review-subtitle"><?php echo nl2br( esc_html( $c['review_subtitle'] ) ); ?></p>

      <span class="stars">
        <span>⭐</span><span>⭐</span><span>⭐</span><span>⭐</span><span>⭐</span>
      </span>

      <div class="steps">
        <div class="step"><div class="step-num">1</div><div class="step-text"><?php echo esc_html( $c['step1'] ); ?></div></div>
        <div class="step"><div class="step-num">2</div><div class="step-text"><?php echo esc_html( $c['step2'] ); ?></div></div>
        <div class="step"><div class="step-num">3</div><div class="step-text"><?php echo esc_html( $c['step3'] ); ?></div></div>
      </div>

      <a href="<?php echo esc_url( $c['google_url'] ?: '#' ); ?>" class="google-btn" id="google-btn" target="_blank" rel="noopener">
        <svg class="google-logo" viewBox="0 0 48 48" xmlns="http://www.w3.org/2000/svg">
          <path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/>
          <path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/>
          <path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/>
          <path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.18 1.48-4.97 2.36-8.16 2.36-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/>
        </svg>
        <div class="btn-text">
          <span class="main"><?php echo esc_html( $c['btn_main'] ); ?></span>
          <span class="sub"><?php echo esc_html( $c['btn_sub'] ); ?></span>
        </div>
      </a>

      <div class="urgency-note"><?php echo esc_html( $c['urgency'] ); ?></div>
    </div>
  </div>

  <p class="footer-note"><?php echo nl2br( esc_html( $c['reward_footer'] ) ); ?></p>
</div>

<script>
(function () {
    const btn = document.getElementById('google-btn');
    if (!btn) return;
    btn.addEventListener('click', function () {
        var data = new URLSearchParams({
            action: 'wheel_track_click',
            nonce:  <?php echo wp_json_encode( $nonce_click ); ?>,
            campaign_id: <?php echo (int) $campaign_id; ?>,
            play_token:  <?php echo wp_json_encode( $play_token ); ?>,
        });
        if (navigator.sendBeacon) {
            navigator.sendBeacon(<?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>,
                new Blob([data.toString()], { type: 'application/x-www-form-urlencoded' }));
        } else {
            fetch(<?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>, {
                method: 'POST', body: data, keepalive: true,
            }).catch(() => {});
        }
    });
})();
</script>
</body>
</html>
