<?php
/**
 * Template : Page de la roue (standalone, sans thème WordPress)
 */
if ( ! defined( 'ABSPATH' ) ) exit;

global $post;
$campaign_id = get_the_ID();

// Campagne inactive → redirection
if ( get_post_meta( $campaign_id, '_wheel_active', true ) === '0' ) {
    wp_redirect( home_url() );
    exit;
}

// Anti-replay : vérifier le cookie serveur AVANT tout output HTML
$cookie_key    = 'wheel_played_' . $campaign_id;
$already_played = false;
$played_data    = null;

if ( isset( $_COOKIE[ $cookie_key ] ) ) {
    $decoded = json_decode( base64_decode( $_COOKIE[ $cookie_key ] ), true );
    if ( is_array( $decoded ) ) {
        $already_played = true;
        $played_data    = $decoded;
    }
}

// Récupérer la configuration de la campagne
$prizes  = get_post_meta( $campaign_id, '_wheel_prizes', true ) ?: [];
if ( empty( $prizes ) ) {
    $prizes = [
        [ 'emoji' => '👑', 'line1' => 'VIP',      'line2' => '',                 'color' => '#ffd700' ],
        [ 'emoji' => '🍽️', 'line1' => 'Entrée',   'line2' => 'offerte',          'color' => '#1a1a2e' ],
        [ 'emoji' => '☕', 'line1' => 'Café',      'line2' => 'offert',           'color' => '#ffd700' ],
        [ 'emoji' => '💸', 'line1' => '-10%',      'line2' => 'de réduction',     'color' => '#1a1a2e' ],
        [ 'emoji' => '🍰', 'line1' => 'Dessert',   'line2' => 'offert',           'color' => '#ffd700' ],
        [ 'emoji' => '💰', 'line1' => '-15%',      'line2' => 'prochaine visite', 'color' => '#1a1a2e' ],
        [ 'emoji' => '🥤', 'line1' => 'Boisson',   'line2' => 'offerte',          'color' => '#ffd700' ],
        [ 'emoji' => '🔥', 'line1' => '-20%',      'line2' => 'de réduction',     'color' => '#1a1a2e' ],
    ];
}

$w_title = get_post_meta( $campaign_id, '_wheel_title',    true ) ?: 'Tentez votre chance !';
$w_sub   = get_post_meta( $campaign_id, '_wheel_subtitle', true ) ?: 'Tournez la roue et gagnez un cadeau exclusif';
$w_foot  = get_post_meta( $campaign_id, '_wheel_footer',   true ) ?: '1 participation par client · Offre non cumulable';

// Mode test : admin connecté → roue illimitée, aucune donnée sauvegardée
// Paramètre ?preview_as_user=1 → désactive le mode test pour prévisualiser comme un vrai utilisateur
$is_admin      = is_user_logged_in() && current_user_can( 'manage_options' );
$preview_mode  = $is_admin && isset( $_GET['preview_as_user'] ) && $_GET['preview_as_user'] === '1';
$is_admin_test = $is_admin && ! $preview_mode;

// Données passées au JS
$js_data = [
    'campaignId'    => $campaign_id,
    'prizes'        => $prizes,
    'nonce'         => wp_create_nonce( 'wheel_play' ),
    'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
    'rewardUrl'     => get_permalink( $campaign_id ) . '?step=reward',
    'alreadyPlayed' => $already_played && ! $is_admin_test,
    'playedData'    => $played_data,
    'isAdminTest'   => $is_admin_test,
    'previewMode'   => $preview_mode,
];

$wheel_url        = get_permalink( $campaign_id );
$toggle_test_url  = $is_admin_test
    ? add_query_arg( 'preview_as_user', '1', $wheel_url )
    : $wheel_url;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo esc_html( $w_title ); ?> — <?php echo esc_html( get_bloginfo( 'name' ) ); ?></title>
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }

    body {
      min-height: 100vh;
      background: linear-gradient(160deg, #0d1b2a 0%, #1a1a2e 50%, #0d1b2a 100%);
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      overflow-x: hidden;
      padding: 20px 16px 40px;
    }

    body::before {
      content: '';
      position: fixed;
      inset: 0;
      background:
        radial-gradient(ellipse at 30% 20%, rgba(255,215,0,0.07) 0%, transparent 55%),
        radial-gradient(ellipse at 70% 80%, rgba(255,215,0,0.05) 0%, transparent 55%),
        radial-gradient(1px 1px at 20% 30%, rgba(255,255,255,0.45) 0%, transparent 100%),
        radial-gradient(1px 1px at 80% 15%, rgba(255,255,255,0.35) 0%, transparent 100%),
        radial-gradient(1px 1px at 50% 70%, rgba(255,255,255,0.3)  0%, transparent 100%),
        radial-gradient(1px 1px at 10% 80%, rgba(255,255,255,0.4)  0%, transparent 100%),
        radial-gradient(1px 1px at 92% 55%, rgba(255,255,255,0.35) 0%, transparent 100%),
        radial-gradient(1px 1px at 65% 10%, rgba(255,255,255,0.4)  0%, transparent 100%);
      pointer-events: none;
      z-index: 0;
    }

    .container {
      position: relative;
      z-index: 1;
      max-width: 460px;
      width: 100%;
    }

    /* ── Badge mode test ───────────────────────────────────────────────── */
    .test-badge {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      background: rgba(255,165,0,0.15);
      border: 1.5px solid rgba(255,165,0,0.6);
      border-radius: 50px;
      padding: 7px 16px;
      font-size: 0.76rem;
      font-weight: 700;
      color: #ffb347;
      letter-spacing: 0.8px;
      text-transform: uppercase;
      margin-bottom: 12px;
      text-align: center;
    }

    .test-badge .dot {
      width: 7px; height: 7px;
      border-radius: 50%;
      background: #ffb347;
      animation: blink 1s infinite;
      flex-shrink: 0;
    }

    .test-badge a {
      color: #ffe0a0;
      text-decoration: underline;
      text-decoration-style: dotted;
      cursor: pointer;
      font-size: 0.72rem;
      margin-left: 4px;
      opacity: 0.85;
    }

    .test-badge a:hover { opacity: 1; }

    .preview-badge {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      background: rgba(0,200,100,0.15);
      border: 1.5px solid rgba(0,200,100,0.5);
      border-radius: 50px;
      padding: 7px 16px;
      font-size: 0.76rem;
      font-weight: 700;
      color: #00e676;
      letter-spacing: 0.8px;
      text-transform: uppercase;
      margin-bottom: 12px;
      text-align: center;
    }

    .preview-badge a {
      color: #80ffb4;
      text-decoration: underline;
      text-decoration-style: dotted;
      cursor: pointer;
      font-size: 0.72rem;
      margin-left: 4px;
      opacity: 0.85;
    }

    @keyframes blink {
      0%, 100% { opacity: 1; }
      50%       { opacity: 0.3; }
    }

    /* ── Carte principale ───────────────────────────────────────────────── */
    .wheel-card {
      background: linear-gradient(145deg, rgba(255,255,255,0.07), rgba(255,255,255,0.02));
      backdrop-filter: blur(16px);
      border: 2px solid rgba(255,215,0,0.45);
      border-radius: 28px;
      padding: 28px 20px 22px;
      text-align: center;
      box-shadow:
        0 24px 64px rgba(0,0,0,0.55),
        0 0 0 1px rgba(255,215,0,0.1) inset,
        inset 0 1px 0 rgba(255,255,255,0.1);
      animation: card-in 0.5s cubic-bezier(0.34, 1.56, 0.64, 1) forwards;
    }

    @keyframes card-in {
      from { opacity: 0; transform: scale(0.93) translateY(22px); }
      to   { opacity: 1; transform: scale(1) translateY(0); }
    }

    .gift-icon {
      font-size: 44px;
      animation: float 3s ease-in-out infinite;
      display: block;
      margin-bottom: 8px;
    }

    @keyframes float {
      0%, 100% { transform: translateY(0); }
      50%       { transform: translateY(-8px); }
    }

    h1 {
      font-size: 1.9rem;
      font-weight: 900;
      background: linear-gradient(90deg, #ffd700 0%, #ffe98a 50%, #ffd700 100%);
      background-size: 200% auto;
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
      animation: shimmer 3s linear infinite;
      line-height: 1.2;
    }

    @keyframes shimmer { to { background-position: 200% center; } }

    .subtitle {
      color: rgba(255,255,255,0.6);
      font-size: 0.95rem;
      margin-top: 7px;
    }

    /* ── Roue ─────────────────────────────────────────────────────────── */
    .wheel-wrapper {
      position: relative;
      display: inline-block;
      margin: 18px auto 14px;
    }

    /* Flèche pointer */
    .arrow {
      position: absolute;
      top: -14px;
      left: 50%;
      transform: translateX(-50%);
      width: 0; height: 0;
      border-left: 18px solid transparent;
      border-right: 18px solid transparent;
      border-top: 34px solid #ffd700;
      filter: drop-shadow(0 3px 10px rgba(255,215,0,0.9));
      z-index: 10;
    }

    .arrow::after {
      content: '';
      position: absolute;
      top: -38px;
      left: -10px;
      width: 20px;
      height: 10px;
      background: #ffd700;
      border-radius: 4px 4px 0 0;
    }

    canvas#wheel {
      border-radius: 50%;
      box-shadow:
        0 0 0 4px rgba(255,215,0,0.5),
        0 0 0 8px rgba(255,215,0,0.18),
        0 0 0 14px rgba(255,215,0,0.06),
        0 24px 64px rgba(0,0,0,0.6);
      display: block;
      max-width: min(330px, calc(100vw - 48px));
      max-height: min(330px, calc(100vw - 48px));
    }

    /* Bouton GO transparent (overlay canvas center) */
    .spin-btn {
      position: absolute;
      top: 50%; left: 50%;
      transform: translate(-50%, -50%);
      width: 90px; height: 90px;
      border-radius: 50%;
      background: transparent;
      border: none;
      cursor: pointer;
      z-index: 10;
      font-size: 0;
      color: transparent;
    }

    .spin-btn:disabled { cursor: not-allowed; }

    /* ── Bouton récupérer ────────────────────────────────────────────── */
    .claim-btn {
      display: none;
      margin: 18px auto 0;
      padding: 16px 40px;
      background: linear-gradient(135deg, #ffd700, #cc8800);
      color: #1a1a2e;
      font-size: 1.1rem;
      font-weight: 900;
      border: none;
      border-radius: 50px;
      cursor: pointer;
      box-shadow: 0 6px 28px rgba(255,215,0,0.45);
      text-transform: uppercase;
      letter-spacing: 1px;
      animation: pulse-gold 1.6s infinite;
      transition: transform 0.15s;
    }

    .claim-btn:hover { transform: scale(1.05); }

    @keyframes pulse-gold {
      0%, 100% { box-shadow: 0 6px 28px rgba(255,215,0,0.45); }
      50%       { box-shadow: 0 8px 40px rgba(255,215,0,0.8); }
    }

    /* ── Bannière résultat ──────────────────────────────────────────── */
    .result-banner {
      display: none;
      background: linear-gradient(135deg, rgba(255,215,0,0.14), rgba(204,136,0,0.1));
      border: 1.5px solid rgba(255,215,0,0.45);
      border-radius: 18px;
      padding: 14px 24px;
      margin-top: 14px;
    }

    .result-banner .label {
      font-size: 0.8rem;
      color: rgba(255,255,255,0.55);
      text-transform: uppercase;
      letter-spacing: 1.5px;
    }

    .result-banner .prize-name {
      font-size: 1.35rem;
      font-weight: 900;
      color: #ffd700;
      margin-top: 4px;
    }

    .already-played-msg {
      color: rgba(255,255,255,0.45);
      font-size: 0.85rem;
      margin-top: 10px;
    }

    /* ── Bouton rejouer (test) ──────────────────────────────────────── */
    .replay-btn {
      display: none;
      margin: 14px auto 0;
      padding: 12px 32px;
      background: rgba(255,165,0,0.15);
      color: #ffb347;
      font-size: 0.92rem;
      font-weight: 700;
      border: 2px solid rgba(255,165,0,0.5);
      border-radius: 50px;
      cursor: pointer;
      transition: background 0.15s;
    }

    .replay-btn:hover { background: rgba(255,165,0,0.28); }

    /* ── Lien vers page cadeau (mode preview) ───────────────────────── */
    .preview-claim-btn {
      display: none;
      margin: 14px auto 0;
      padding: 16px 40px;
      background: linear-gradient(135deg, #ffd700, #cc8800);
      color: #1a1a2e;
      font-size: 1.1rem;
      font-weight: 900;
      border: none;
      border-radius: 50px;
      cursor: pointer;
      box-shadow: 0 6px 28px rgba(255,215,0,0.45);
      text-transform: uppercase;
      letter-spacing: 1px;
      animation: pulse-gold 1.6s infinite;
      transition: transform 0.15s;
    }

    .preview-claim-btn:hover { transform: scale(1.05); }

    /* ── Stats de test ──────────────────────────────────────────────── */
    .test-stats {
      display: none;
      margin-top: 18px;
      background: rgba(0,0,0,0.3);
      border: 1px solid rgba(255,255,255,0.08);
      border-radius: 14px;
      padding: 14px 16px;
      text-align: left;
      width: 100%;
    }

    .test-stats h4 {
      font-size: 0.72rem;
      font-weight: 800;
      color: #ffb347;
      text-transform: uppercase;
      letter-spacing: 1px;
      margin-bottom: 10px;
    }

    .test-stats .stat-row { display: flex; align-items: center; gap: 8px; margin-bottom: 6px; }
    .test-stats .stat-bar-wrap { flex: 1; height: 8px; background: rgba(255,255,255,0.08); border-radius: 4px; overflow: hidden; }
    .test-stats .stat-bar { height: 100%; border-radius: 4px; transition: width 0.4s ease; }
    .test-stats .stat-label { font-size: 0.75rem; color: rgba(255,255,255,0.7); min-width: 110px; }
    .test-stats .stat-count { font-size: 0.75rem; font-weight: 700; color: #fff; min-width: 28px; text-align: right; }
    .test-stats .stat-pct   { font-size: 0.7rem; color: rgba(255,255,255,0.45); min-width: 38px; text-align: right; }
    .test-stats .total-line { font-size: 0.73rem; color: rgba(255,255,255,0.35); margin-top: 8px; padding-top: 8px; border-top: 1px solid rgba(255,255,255,0.07); }

    /* ── Footer ─────────────────────────────────────────────────────── */
    .footer-note {
      color: rgba(255,255,255,0.3);
      font-size: 0.7rem;
      margin-top: 18px;
    }

    /* ── Confettis ──────────────────────────────────────────────────── */
    .confetti-container {
      position: fixed;
      top: 0; left: 0;
      width: 100%; height: 100%;
      pointer-events: none;
      z-index: 100;
    }

    .confetti-piece {
      position: absolute;
      top: -20px;
      animation: confetti-fall linear forwards;
      opacity: 0;
    }

    @keyframes confetti-fall {
      0%   { opacity: 1; transform: translateY(0) rotate(0deg); }
      100% { opacity: 0; transform: translateY(100vh) rotate(720deg); }
    }

    /* ── Responsive hauteur ─────────────────────────────────────────── */
    @media (max-height: 680px) {
      .gift-icon  { font-size: 30px; margin-bottom: 4px; }
      h1          { font-size: 1.45rem; }
      .subtitle   { font-size: 0.85rem; margin-top: 4px; }
      .wheel-wrapper { margin: 8px auto 8px; }
      .claim-btn, .preview-claim-btn { padding: 13px 30px; font-size: 1rem; margin-top: 10px; }
    }
  </style>
</head>
<body>

<div class="confetti-container" id="confettiContainer"></div>

<div class="container">

  <?php if ( $is_admin_test ) : ?>
  <div style="text-align:center">
    <div class="test-badge">
      <span class="dot"></span>
      Mode test admin
      <a href="<?php echo esc_url( $toggle_test_url ); ?>">→ Voir comme un visiteur</a>
    </div>
  </div>
  <?php elseif ( $preview_mode ) : ?>
  <div style="text-align:center">
    <div class="preview-badge">
      👁️ Aperçu visiteur
      <a href="<?php echo esc_url( $wheel_url ); ?>">↩ Retour mode test</a>
    </div>
  </div>
  <?php endif; ?>

  <div class="wheel-card">
    <div class="header">
      <span class="gift-icon">🎁</span>
      <h1><?php echo esc_html( $w_title ); ?></h1>
      <p class="subtitle"><?php echo esc_html( $w_sub ); ?></p>
    </div>

    <div class="wheel-wrapper">
      <div class="arrow"></div>
      <canvas id="wheel" width="330" height="330"></canvas>
      <button class="spin-btn" id="spinBtn" onclick="spin()">GO</button>
    </div>

    <div class="result-banner" id="resultBanner">
      <div class="label">Vous avez gagné</div>
      <div class="prize-name" id="prizeLabel"></div>
    </div>

    <button class="claim-btn"         id="claimBtn">🎉 Récupérer mon cadeau</button>
    <button class="replay-btn"        id="replayBtn"       onclick="resetForTest()">🔄 Rejouer (test)</button>
    <button class="preview-claim-btn" id="previewClaimBtn">🎉 Récupérer mon cadeau</button>

    <?php if ( $already_played && ! $is_admin_test ) : ?>
    <p class="already-played-msg">Vous avez déjà participé à ce tirage.</p>
    <?php endif; ?>

    <?php if ( $is_admin_test ) : ?>
    <div class="test-stats" id="testStats">
      <h4>📊 Statistiques de cette session de test</h4>
      <div id="statsRows"></div>
      <div class="total-line" id="statsTotal">0 tirage(s)</div>
    </div>
    <?php endif; ?>

    <p class="footer-note"><?php echo esc_html( $w_foot ); ?></p>
  </div>
</div>

<script>
const WHEEL_DATA = <?php echo wp_json_encode( $js_data ); ?>;

// ── Config ──────────────────────────────────────────────────────────────────
const PRIZES = WHEEL_DATA.prizes.map(p => ({
    label:   p.line2 ? `${p.line1}\n${p.line2}` : p.line1,
    color:   p.color   || '#1a1a2e',
    emoji:   p.emoji   || '🎁',
    percent: p.percent !== undefined ? parseFloat(p.percent) : parseFloat(p.weight || 10),
}));

// ── Canvas ────────────────────────────────────────────────────────────────────
const canvas = document.getElementById('wheel');
const ctx    = canvas.getContext('2d');
const N      = PRIZES.length;
const arc    = (2 * Math.PI) / N;
const R      = canvas.width / 2;

let currentAngle = 0;
let isSpinning   = false;
let wonIndex     = null;

function drawWheel(angle) {
    ctx.clearRect(0, 0, canvas.width, canvas.height);

    // ── Segments (rotatifs) ────────────────────────────────────────────────
    ctx.save();
    ctx.translate(R, R);
    ctx.rotate(angle);

    PRIZES.forEach((prize, i) => {
        const start = i * arc;
        const end   = start + arc;

        // Remplissage segment
        ctx.beginPath();
        ctx.moveTo(0, 0);
        ctx.arc(0, 0, R - 10, start, end);
        ctx.closePath();
        ctx.fillStyle = prize.color;
        ctx.fill();

        // Séparateur doré
        ctx.beginPath();
        ctx.moveTo(0, 0);
        ctx.arc(0, 0, R - 10, start, end);
        ctx.closePath();
        ctx.strokeStyle = 'rgba(255,215,0,0.45)';
        ctx.lineWidth = 1.5;
        ctx.stroke();

        // Texte
        ctx.save();
        ctx.rotate(start + arc / 2);
        ctx.textAlign = 'right';

        const isDark = prize.color === '#1a1a2e' || prize.color === '#0d1b2a' || prize.color === '#0d1117';
        ctx.fillStyle = isDark ? '#ffd700' : '#fff';
        ctx.shadowColor = 'rgba(0,0,0,0.85)';
        ctx.shadowBlur = 5;
        ctx.shadowOffsetX = 1;
        ctx.shadowOffsetY = 1;

        const lines = prize.label.split('\n');
        if (lines.length === 1) {
            ctx.font = 'bold 14px "Segoe UI", sans-serif';
            ctx.fillText(prize.emoji + ' ' + lines[0], R - 18, 5);
        } else {
            ctx.font = 'bold 14px "Segoe UI", sans-serif';
            ctx.fillText(prize.emoji + ' ' + lines[0], R - 18, -5);
            ctx.font = '11px "Segoe UI", sans-serif';
            ctx.shadowBlur = 3;
            ctx.fillText(lines[1], R - 18, 11);
        }
        ctx.restore();
    });

    // Bande extérieure sombre + bordure dorée
    ctx.beginPath();
    ctx.arc(0, 0, R - 2, 0, 2 * Math.PI);
    ctx.strokeStyle = '#0d1b2a';
    ctx.lineWidth = 16;
    ctx.stroke();

    ctx.beginPath();
    ctx.arc(0, 0, R - 2, 0, 2 * Math.PI);
    ctx.strokeStyle = 'rgba(255,215,0,0.8)';
    ctx.lineWidth = 3;
    ctx.stroke();

    // Anneau interne doré
    ctx.beginPath();
    ctx.arc(0, 0, R - 10, 0, 2 * Math.PI);
    ctx.strokeStyle = 'rgba(255,215,0,0.35)';
    ctx.lineWidth = 2;
    ctx.stroke();

    ctx.restore();

    // ── Centre non-rotatif : bouton GO ──────────────────────────────────
    ctx.save();
    ctx.translate(R, R);

    const cr = 44; // rayon centre

    // Anneau sombre autour du centre
    ctx.beginPath();
    ctx.arc(0, 0, cr + 7, 0, 2 * Math.PI);
    ctx.fillStyle = '#0d1b2a';
    ctx.fill();

    // Cercle doré
    ctx.shadowColor = 'rgba(255,215,0,0.6)';
    ctx.shadowBlur  = 18;
    ctx.beginPath();
    ctx.arc(0, 0, cr, 0, 2 * Math.PI);
    const grad = ctx.createRadialGradient(-12, -12, 2, 0, 0, cr);
    grad.addColorStop(0, '#ffe566');
    grad.addColorStop(0.65, '#ffd700');
    grad.addColorStop(1, '#b8860b');
    ctx.fillStyle = grad;
    ctx.fill();

    ctx.shadowBlur = 0;

    // Bordure blanche intérieure
    ctx.beginPath();
    ctx.arc(0, 0, cr, 0, 2 * Math.PI);
    ctx.strokeStyle = 'rgba(255,255,255,0.55)';
    ctx.lineWidth = 2.5;
    ctx.stroke();

    // Texte GO
    ctx.fillStyle   = '#1a1a2e';
    ctx.font        = 'bold 26px "Segoe UI", sans-serif';
    ctx.textAlign   = 'center';
    ctx.textBaseline = 'middle';
    ctx.fillText('GO', 0, 0);

    ctx.restore();
}

drawWheel(currentAngle);

// ── Compteurs de test ─────────────────────────────────────────────────────
const testCounts = new Array(PRIZES.length).fill(0);

// ── Déjà joué : restituer l'état (ignoré en mode test admin) ─────────────
if (WHEEL_DATA.alreadyPlayed && WHEEL_DATA.playedData) {
    document.getElementById('spinBtn').disabled = true;
    wonIndex = WHEEL_DATA.playedData.index;
    showResult(false);
}

// ── Tirage pondéré par pourcentage ─────────────────────────────────────────
function weightedRandom() {
    const eligible = PRIZES.map((p, i) => ({ i, w: p.percent })).filter(x => x.w > 0);
    if (eligible.length === 0) return 0;
    const total = eligible.reduce((sum, x) => sum + x.w, 0);
    let rand = Math.random() * total;
    for (const { i, w } of eligible) {
        rand -= w;
        if (rand <= 0) return i;
    }
    return eligible[eligible.length - 1].i;
}

// ── Spin ──────────────────────────────────────────────────────────────────
function spin() {
    if (isSpinning) return;
    isSpinning = true;
    document.getElementById('spinBtn').disabled = true;

    wonIndex = weightedRandom();

    const segCenter  = wonIndex * arc + arc / 2;
    const randOffset = (Math.random() - 0.5) * arc * 0.7;
    const baseFinal  = -Math.PI / 2 - segCenter + randOffset;
    const minFinal   = currentAngle + 5 * 2 * Math.PI;
    const k          = Math.ceil((minFinal - baseFinal) / (2 * Math.PI));
    const finalAngle = baseFinal + k * 2 * Math.PI;

    const duration   = 4500 + Math.random() * 1000;
    const startTime  = performance.now();
    const startAngle = currentAngle;

    function easeOut(t) { return 1 - Math.pow(1 - t, 4); }

    function animate(now) {
        const t      = Math.min((now - startTime) / duration, 1);
        currentAngle = startAngle + (finalAngle - startAngle) * easeOut(t);
        drawWheel(currentAngle);
        if (t < 1) {
            requestAnimationFrame(animate);
        } else {
            currentAngle = finalAngle;
            drawWheel(currentAngle);
            isSpinning = false;
            savePlay(wonIndex);
        }
    }

    requestAnimationFrame(animate);
}

// ── Sauvegarder via AJAX (WordPress) ────────────────────────────────────
function savePlay(index) {
    const prize      = PRIZES[index];
    const prizeLabel = prize.emoji + ' ' + prize.label.replace('\n', ' ');

    if (WHEEL_DATA.isAdminTest) {
        testCounts[index]++;
        showResult(true);
        return;
    }

    fetch(WHEEL_DATA.ajaxUrl, {
        method:  'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body:    new URLSearchParams({
            action:      'wheel_save_play',
            nonce:       WHEEL_DATA.nonce,
            campaign_id: WHEEL_DATA.campaignId,
            prize_index: index,
            prize_label: prizeLabel,
        }),
    })
    .then(r => r.json())
    .then(() => showResult(true))
    .catch(() => showResult(true));
}

// ── Résultat ──────────────────────────────────────────────────────────────
function showResult(withConfetti) {
    const prize     = PRIZES[wonIndex];
    const prizeText = prize.emoji + ' ' + prize.label.replace('\n', ' ');

    document.getElementById('prizeLabel').textContent = prizeText;
    document.getElementById('resultBanner').style.display = 'block';

    if (WHEEL_DATA.isAdminTest) {
        document.getElementById('replayBtn').style.display = 'block';
        document.getElementById('claimBtn').style.display  = 'none';
        updateTestStats();
    } else if (WHEEL_DATA.previewMode) {
        // Mode aperçu visiteur : bouton cadeau fonctionnel
        const btn = document.getElementById('previewClaimBtn');
        btn.style.display = 'block';
        btn.onclick = () => {
            window.location.href = WHEEL_DATA.rewardUrl + '&prize=' + encodeURIComponent(prizeText);
        };
    } else {
        const claimBtn = document.getElementById('claimBtn');
        claimBtn.style.display = 'block';
        claimBtn.onclick = () => {
            window.location.href = WHEEL_DATA.rewardUrl + '&prize=' + encodeURIComponent(prizeText);
        };
    }

    if (withConfetti) launchConfetti();
}

// ── Rejouer (mode test) ──────────────────────────────────────────────────
function resetForTest() {
    document.getElementById('spinBtn').disabled             = false;
    document.getElementById('resultBanner').style.display   = 'none';
    document.getElementById('replayBtn').style.display      = 'none';
    wonIndex = null;
}

// ── Stats de test ────────────────────────────────────────────────────────
function updateTestStats() {
    const statsEl = document.getElementById('testStats');
    if (!statsEl) return;
    statsEl.style.display = 'block';

    const total    = testCounts.reduce((a, b) => a + b, 0);
    const maxCount = Math.max(...testCounts, 1);

    document.getElementById('statsRows').innerHTML = PRIZES.map((p, i) => {
        const count    = testCounts[i];
        const pct      = total > 0 ? (count / total * 100).toFixed(1) : '0.0';
        const barWidth = (count / maxCount * 100).toFixed(1);
        const label    = p.emoji + ' ' + p.label.replace('\n', ' ');
        const wPct     = p.percent.toFixed(2);
        return `<div class="stat-row">
            <span class="stat-label" title="Probabilité : ${wPct}%">${label}</span>
            <div class="stat-bar-wrap">
                <div class="stat-bar" style="width:${barWidth}%;background:${p.color === '#1a1a2e' || p.color === '#0d1b2a' ? '#ffd700' : p.color}"></div>
            </div>
            <span class="stat-count">${count}</span>
            <span class="stat-pct">${pct}%</span>
        </div>`;
    }).join('');

    document.getElementById('statsTotal').textContent =
        `${total} tirage(s) · Survolez un prix pour voir le % théorique`;
}

// ── Confettis ────────────────────────────────────────────────────────────
function launchConfetti() {
    const c      = document.getElementById('confettiContainer');
    const colors = ['#ffd700','#ffe566','#fff0a0','#ff8c00','#ffffff','#ffb347'];
    for (let i = 0; i < 90; i++) {
        const el = document.createElement('div');
        el.className = 'confetti-piece';
        el.style.left              = Math.random() * 100 + 'vw';
        el.style.background        = colors[Math.floor(Math.random() * colors.length)];
        el.style.borderRadius      = Math.random() > 0.5 ? '50%' : '2px';
        el.style.width             = (5 + Math.random() * 8) + 'px';
        el.style.height            = (5 + Math.random() * 8) + 'px';
        el.style.animationDuration = (1.5 + Math.random() * 2) + 's';
        el.style.animationDelay    = (Math.random() * 0.8) + 's';
        c.appendChild(el);
        setTimeout(() => el.remove(), 4000);
    }
}
</script>
</body>
</html>
