<?php
/**
 * Template : Page de récompense (standalone, sans thème WordPress)
 */
if ( ! defined( 'ABSPATH' ) ) exit;

global $post;
$campaign_id = get_the_ID();

// Récupérer le prix depuis l'URL
$prize = sanitize_text_field( wp_unslash( $_GET['prize'] ?? '🎁 Cadeau surprise' ) );

// Vérifier que le joueur a bien une participation enregistrée
$cookie_key = 'wheel_played_' . $campaign_id;
if ( ! isset( $_COOKIE[ $cookie_key ] ) ) {
    // Accès direct sans avoir joué → retour à la roue
    wp_redirect( get_permalink( $campaign_id ) );
    exit;
}

// Récupérer la config
$google_url = get_post_meta( $campaign_id, '_reward_google_url',      true ) ?: '#';
$r_valid    = get_post_meta( $campaign_id, '_reward_validity',        true ) ?: 'Valable lors de votre prochaine visite · Présentez cette page';
$r_rtit     = get_post_meta( $campaign_id, '_reward_review_title',    true ) ?: 'Laissez-nous un avis Google ⭐';
$r_rsub     = get_post_meta( $campaign_id, '_reward_review_subtitle', true ) ?: "Votre avis nous aide énormément à nous faire connaître.\nÇa ne prend que 30 secondes et cela compte vraiment !";
$r_s1       = get_post_meta( $campaign_id, '_reward_step1',           true ) ?: 'Cliquez sur le bouton ci-dessous';
$r_s2       = get_post_meta( $campaign_id, '_reward_step2',           true ) ?: 'Donnez-nous 5 étoiles et laissez un petit commentaire';
$r_s3       = get_post_meta( $campaign_id, '_reward_step3',           true ) ?: 'Revenez récupérer votre cadeau en montrant cette page !';
$r_bm       = get_post_meta( $campaign_id, '_reward_btn_main',        true ) ?: 'Laisser un avis Google';
$r_bs       = get_post_meta( $campaign_id, '_reward_btn_sub',         true ) ?: 'Ouvre la page Google de notre établissement';
$r_urg      = get_post_meta( $campaign_id, '_reward_urgency',         true ) ?: 'Votre cadeau sera validé par notre équipe après vérification de votre avis.';
$r_foot     = get_post_meta( $campaign_id, '_reward_footer',          true ) ?: "En laissant un avis, vous acceptez les conditions d'utilisation de Google.\nCadeau non échangeable contre de l'argent · Une utilisation par personne.";

$blog_name  = get_bloginfo( 'name' );
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Votre cadeau vous attend ! — <?php echo esc_html( $blog_name ); ?></title>
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }

    body {
      min-height: 100vh;
      background: linear-gradient(135deg, #0f2027, #203a43, #2c5364);
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      padding: 24px 16px;
      overflow-x: hidden;
    }

    body::before {
      content: '';
      position: fixed;
      inset: 0;
      background:
        radial-gradient(circle at 20% 20%, rgba(255,215,0,0.08) 0%, transparent 50%),
        radial-gradient(circle at 80% 80%, rgba(0,200,81,0.08) 0%, transparent 50%);
      pointer-events: none;
    }

    .container {
      position: relative;
      z-index: 1;
      max-width: 460px;
      width: 100%;
    }

    .prize-card {
      background: linear-gradient(135deg, rgba(255,255,255,0.1), rgba(255,255,255,0.04));
      backdrop-filter: blur(12px);
      border: 2px solid rgba(255,215,0,0.4);
      border-radius: 24px;
      padding: 32px 28px;
      text-align: center;
      box-shadow: 0 20px 60px rgba(0,0,0,0.4), inset 0 1px 0 rgba(255,255,255,0.1);
      animation: card-in 0.6s cubic-bezier(0.34, 1.56, 0.64, 1) forwards;
    }

    @keyframes card-in {
      from { opacity: 0; transform: scale(0.8) translateY(30px); }
      to   { opacity: 1; transform: scale(1) translateY(0); }
    }

    .trophy {
      font-size: 64px;
      animation: trophy-bounce 1.2s cubic-bezier(0.34, 1.56, 0.64, 1) forwards;
      display: block;
      margin-bottom: 12px;
    }

    @keyframes trophy-bounce {
      from { transform: scale(0) rotate(-20deg); opacity: 0; }
      to   { transform: scale(1) rotate(0deg);   opacity: 1; }
    }

    .congrats {
      font-size: 0.85rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 3px;
      color: rgba(255,255,255,0.6);
      margin-bottom: 6px;
    }

    h1 {
      font-size: 1.7rem;
      font-weight: 900;
      color: #fff;
      line-height: 1.2;
      margin-bottom: 20px;
    }

    .prize-badge {
      display: inline-block;
      background: linear-gradient(135deg, #ffd700, #ff8c00);
      border-radius: 50px;
      padding: 14px 32px;
      font-size: 1.35rem;
      font-weight: 900;
      color: #1a1a2e;
      box-shadow: 0 6px 24px rgba(255,140,0,0.5);
      margin-bottom: 20px;
      animation: prize-pop 0.5s 0.4s cubic-bezier(0.34, 1.56, 0.64, 1) both;
    }

    @keyframes prize-pop {
      from { transform: scale(0.5); opacity: 0; }
      to   { transform: scale(1);   opacity: 1; }
    }

    .validity {
      font-size: 0.8rem;
      color: rgba(255,255,255,0.5);
      margin-bottom: 4px;
    }

    .separator {
      display: flex;
      align-items: center;
      gap: 12px;
      margin: 28px 0 22px;
    }

    .separator::before, .separator::after {
      content: '';
      flex: 1;
      height: 1px;
      background: rgba(255,255,255,0.15);
    }

    .separator span {
      color: rgba(255,255,255,0.5);
      font-size: 0.8rem;
      text-transform: uppercase;
      letter-spacing: 1.5px;
      white-space: nowrap;
    }

    .review-section { text-align: center; }

    .review-title {
      font-size: 1.25rem;
      font-weight: 800;
      color: #fff;
      margin-bottom: 8px;
    }

    .review-subtitle {
      font-size: 0.92rem;
      color: rgba(255,255,255,0.65);
      line-height: 1.5;
      margin-bottom: 20px;
    }

    .stars {
      font-size: 2rem;
      letter-spacing: 4px;
      margin-bottom: 20px;
      display: block;
    }

    .stars span {
      display: inline-block;
      animation: star-pop 0.4s cubic-bezier(0.34, 1.56, 0.64, 1) both;
    }

    .stars span:nth-child(1) { animation-delay: 0.7s; }
    .stars span:nth-child(2) { animation-delay: 0.85s; }
    .stars span:nth-child(3) { animation-delay: 1.0s; }
    .stars span:nth-child(4) { animation-delay: 1.15s; }
    .stars span:nth-child(5) { animation-delay: 1.3s; }

    @keyframes star-pop {
      from { transform: scale(0) rotate(-30deg); opacity: 0; }
      to   { transform: scale(1) rotate(0deg);   opacity: 1; }
    }

    .steps {
      display: flex;
      flex-direction: column;
      gap: 10px;
      margin: 16px 0 20px;
      text-align: left;
      animation: btn-in 0.5s 1.2s both;
    }

    .step { display: flex; align-items: flex-start; gap: 12px; }

    .step-num {
      width: 26px; height: 26px;
      border-radius: 50%;
      background: linear-gradient(135deg, #ffd700, #ff8c00);
      color: #1a1a2e;
      font-weight: 900;
      font-size: 0.8rem;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
    }

    .step-text {
      font-size: 0.88rem;
      color: rgba(255,255,255,0.8);
      padding-top: 3px;
      line-height: 1.4;
    }

    .google-btn {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 12px;
      width: 100%;
      padding: 18px 24px;
      background: #fff;
      color: #1a1a2e;
      font-size: 1.05rem;
      font-weight: 800;
      border: none;
      border-radius: 16px;
      cursor: pointer;
      text-decoration: none;
      box-shadow: 0 8px 32px rgba(0,0,0,0.3);
      transition: transform 0.15s, box-shadow 0.15s;
      animation: btn-in 0.5s 1.4s cubic-bezier(0.34, 1.56, 0.64, 1) both;
    }

    @keyframes btn-in {
      from { transform: translateY(20px); opacity: 0; }
      to   { transform: translateY(0);    opacity: 1; }
    }

    .google-btn:hover {
      transform: translateY(-2px) scale(1.02);
      box-shadow: 0 12px 40px rgba(0,0,0,0.4);
    }

    .google-logo { width: 28px; height: 28px; flex-shrink: 0; }

    .btn-text { display: flex; flex-direction: column; align-items: flex-start; line-height: 1.2; }
    .btn-text .main { font-size: 1rem; font-weight: 900; color: #1a1a2e; }
    .btn-text .sub  { font-size: 0.73rem; color: #555; font-weight: 500; }

    .urgency-note {
      margin-top: 14px;
      background: rgba(255,59,48,0.15);
      border: 1px solid rgba(255,59,48,0.4);
      border-radius: 10px;
      padding: 10px 14px;
      font-size: 0.82rem;
      color: #ff7b72;
      animation: btn-in 0.5s 1.6s both;
    }

    .footer-note {
      text-align: center;
      color: rgba(255,255,255,0.3);
      font-size: 0.7rem;
      margin-top: 20px;
      line-height: 1.6;
    }
  </style>
</head>
<body>

<div class="container">
  <div class="prize-card">

    <span class="trophy">🏆</span>
    <p class="congrats">Félicitations !</p>
    <h1>Vous avez gagné</h1>
    <div class="prize-badge"><?php echo esc_html( $prize ); ?></div>
    <p class="validity"><?php echo esc_html( $r_valid ); ?></p>

    <div class="separator"><span>une petite faveur en échange ?</span></div>

    <div class="review-section">
      <p class="review-title"><?php echo esc_html( $r_rtit ); ?></p>
      <p class="review-subtitle">
        <?php echo nl2br( esc_html( $r_rsub ) ); ?>
      </p>

      <span class="stars">
        <span>⭐</span><span>⭐</span><span>⭐</span><span>⭐</span><span>⭐</span>
      </span>

      <div class="steps">
        <div class="step">
          <div class="step-num">1</div>
          <div class="step-text"><?php echo esc_html( $r_s1 ); ?></div>
        </div>
        <div class="step">
          <div class="step-num">2</div>
          <div class="step-text"><?php echo esc_html( $r_s2 ); ?></div>
        </div>
        <div class="step">
          <div class="step-num">3</div>
          <div class="step-text"><?php echo esc_html( $r_s3 ); ?></div>
        </div>
      </div>

      <a href="<?php echo esc_url( $google_url ); ?>" class="google-btn" target="_blank" rel="noopener">
        <svg class="google-logo" viewBox="0 0 48 48" xmlns="http://www.w3.org/2000/svg">
          <path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/>
          <path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/>
          <path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/>
          <path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.18 1.48-4.97 2.36-8.16 2.36-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/>
        </svg>
        <div class="btn-text">
          <span class="main"><?php echo esc_html( $r_bm ); ?></span>
          <span class="sub"><?php echo esc_html( $r_bs ); ?></span>
        </div>
      </a>

      <div class="urgency-note">
        ⏳ <strong>Important :</strong> <?php echo esc_html( $r_urg ); ?>
      </div>
    </div>
  </div>

  <p class="footer-note">
    <?php echo nl2br( esc_html( $r_foot ) ); ?>
  </p>
</div>

</body>
</html>
