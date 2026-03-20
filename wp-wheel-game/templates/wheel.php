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
        [ 'emoji' => '☕', 'line1' => 'Café',      'line2' => 'offert',           'color' => '#e74c3c' ],
        [ 'emoji' => '💜', 'line1' => '10%',       'line2' => 'de réduction',     'color' => '#8e44ad' ],
        [ 'emoji' => '🍰', 'line1' => 'Dessert',   'line2' => 'offert',           'color' => '#2980b9' ],
        [ 'emoji' => '💰', 'line1' => '-5€',       'line2' => 'prochaine visite', 'color' => '#16a085' ],
        [ 'emoji' => '🚚', 'line1' => 'Livraison', 'line2' => 'gratuite',         'color' => '#d35400' ],
        [ 'emoji' => '🔥', 'line1' => '15%',       'line2' => 'de réduction',     'color' => '#c0392b' ],
        [ 'emoji' => '🥗', 'line1' => 'Entrée',    'line2' => 'offerte',          'color' => '#27ae60' ],
        [ 'emoji' => '🥤', 'line1' => 'Boisson',   'line2' => 'offerte',          'color' => '#2471a3' ],
    ];
}

$w_title = get_post_meta( $campaign_id, '_wheel_title',    true ) ?: 'Tentez votre chance !';
$w_sub   = get_post_meta( $campaign_id, '_wheel_subtitle', true ) ?: 'Tournez la roue et gagnez un cadeau exclusif';
$w_foot  = get_post_meta( $campaign_id, '_wheel_footer',   true ) ?: '1 participation par client · Offre non cumulable';

// Données passées au JS
$js_data = [
    'campaignId'    => $campaign_id,
    'prizes'        => $prizes,
    'nonce'         => wp_create_nonce( 'wheel_play' ),
    'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
    'rewardUrl'     => get_permalink( $campaign_id ) . '?step=reward',
    'alreadyPlayed' => $already_played,
    'playedData'    => $played_data,
];
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
      background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      overflow: hidden;
      padding: 20px;
    }

    body::before {
      content: '';
      position: fixed;
      top: 0; left: 0; right: 0; bottom: 0;
      background-image:
        radial-gradient(1px 1px at 20% 30%, rgba(255,255,255,0.6) 0%, transparent 100%),
        radial-gradient(1px 1px at 80% 10%, rgba(255,255,255,0.5) 0%, transparent 100%),
        radial-gradient(1px 1px at 50% 60%, rgba(255,255,255,0.4) 0%, transparent 100%),
        radial-gradient(1px 1px at 10% 80%, rgba(255,255,255,0.6) 0%, transparent 100%),
        radial-gradient(1px 1px at 90% 70%, rgba(255,255,255,0.5) 0%, transparent 100%);
      pointer-events: none;
      z-index: 0;
    }

    .container {
      position: relative;
      z-index: 1;
      text-align: center;
      max-width: 480px;
      width: 100%;
    }

    .gift-icon {
      font-size: 48px;
      animation: bounce 2s infinite;
      display: block;
      margin-bottom: 8px;
    }

    @keyframes bounce {
      0%, 100% { transform: translateY(0); }
      50%       { transform: translateY(-10px); }
    }

    h1 {
      font-size: 2rem;
      font-weight: 800;
      background: linear-gradient(90deg, #ffd700, #ff6b35, #ffd700);
      background-size: 200% auto;
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
      animation: shine 3s linear infinite;
      line-height: 1.2;
    }

    @keyframes shine { to { background-position: 200% center; } }

    .subtitle {
      color: rgba(255,255,255,0.75);
      font-size: 1rem;
      margin-top: 8px;
    }

    .wheel-wrapper {
      position: relative;
      display: inline-block;
      margin: 16px auto;
    }

    .arrow {
      position: absolute;
      top: -18px;
      left: 50%;
      transform: translateX(-50%);
      width: 0; height: 0;
      border-left: 16px solid transparent;
      border-right: 16px solid transparent;
      border-top: 32px solid #ffd700;
      filter: drop-shadow(0 4px 8px rgba(255,215,0,0.8));
      z-index: 10;
    }

    .arrow::after {
      content: '';
      position: absolute;
      top: -36px; left: -10px;
      width: 20px; height: 12px;
      background: #ffd700;
      border-radius: 4px 4px 0 0;
    }

    canvas#wheel {
      border-radius: 50%;
      box-shadow:
        0 0 0 6px rgba(255,215,0,0.4),
        0 0 0 12px rgba(255,215,0,0.15),
        0 20px 60px rgba(0,0,0,0.5);
      display: block;
    }

    .spin-btn {
      position: absolute;
      top: 50%; left: 50%;
      transform: translate(-50%, -50%);
      width: 68px; height: 68px;
      border-radius: 50%;
      background: linear-gradient(135deg, #ffd700, #ff8c00);
      border: 4px solid #fff;
      box-shadow: 0 4px 20px rgba(255,140,0,0.7);
      font-size: 0.65rem;
      font-weight: 900;
      color: #1a1a2e;
      cursor: pointer;
      letter-spacing: 0.5px;
      text-transform: uppercase;
      transition: transform 0.15s, box-shadow 0.15s;
      z-index: 10;
    }

    .spin-btn:hover:not(:disabled) {
      transform: translate(-50%, -50%) scale(1.08);
      box-shadow: 0 6px 28px rgba(255,140,0,0.9);
    }

    .spin-btn:disabled { opacity: 0.7; cursor: not-allowed; }

    .claim-btn {
      display: none;
      margin: 20px auto 0;
      padding: 16px 40px;
      background: linear-gradient(135deg, #00c851, #007e33);
      color: #fff;
      font-size: 1.15rem;
      font-weight: 800;
      border: none;
      border-radius: 50px;
      cursor: pointer;
      box-shadow: 0 6px 24px rgba(0,200,81,0.5);
      text-transform: uppercase;
      letter-spacing: 1px;
      animation: pulse-green 1.5s infinite;
      transition: transform 0.15s;
    }

    .claim-btn:hover { transform: scale(1.05); }

    @keyframes pulse-green {
      0%, 100% { box-shadow: 0 6px 24px rgba(0,200,81,0.5); }
      50%       { box-shadow: 0 6px 36px rgba(0,200,81,0.9); }
    }

    .result-banner {
      display: none;
      background: linear-gradient(135deg, rgba(255,215,0,0.2), rgba(255,107,53,0.2));
      border: 2px solid rgba(255,215,0,0.5);
      border-radius: 16px;
      padding: 14px 24px;
      margin-top: 16px;
      color: #fff;
    }

    .result-banner .label {
      font-size: 0.85rem;
      color: rgba(255,255,255,0.7);
      text-transform: uppercase;
      letter-spacing: 1px;
    }

    .result-banner .prize-name {
      font-size: 1.4rem;
      font-weight: 800;
      color: #ffd700;
      margin-top: 4px;
    }

    .already-played-msg {
      color: rgba(255,255,255,0.6);
      font-size: 0.88rem;
      margin-top: 10px;
    }

    .confetti-container {
      position: fixed;
      top: 0; left: 0;
      width: 100%; height: 100%;
      pointer-events: none;
      z-index: 100;
    }

    .confetti-piece {
      position: absolute;
      width: 10px; height: 10px;
      top: -20px;
      animation: confetti-fall linear forwards;
      opacity: 0;
    }

    @keyframes confetti-fall {
      0%   { opacity: 1; transform: translateY(0) rotate(0deg); }
      100% { opacity: 0; transform: translateY(100vh) rotate(720deg); }
    }

    .footer-note {
      color: rgba(255,255,255,0.35);
      font-size: 0.72rem;
      margin-top: 20px;
    }
  </style>
</head>
<body>

<div class="confetti-container" id="confettiContainer"></div>

<div class="container">
  <div class="header">
    <span class="gift-icon">🎁</span>
    <h1><?php echo esc_html( $w_title ); ?></h1>
    <p class="subtitle"><?php echo esc_html( $w_sub ); ?></p>
  </div>

  <div class="wheel-wrapper">
    <div class="arrow"></div>
    <canvas id="wheel" width="320" height="320"></canvas>
    <button class="spin-btn" id="spinBtn" onclick="spin()">TOURNER</button>
  </div>

  <div class="result-banner" id="resultBanner">
    <div class="label">Vous avez gagné</div>
    <div class="prize-name" id="prizeLabel"></div>
  </div>

  <button class="claim-btn" id="claimBtn">🎉 Récupérer mon cadeau</button>

  <?php if ( $already_played ) : ?>
  <p class="already-played-msg">Vous avez déjà participé à ce tirage.</p>
  <?php endif; ?>

  <p class="footer-note"><?php echo esc_html( $w_foot ); ?></p>
</div>

<script>
const WHEEL_DATA = <?php echo wp_json_encode( $js_data ); ?>;

// ── Config ──────────────────────────────────────────────────────────────────
const PRIZES = WHEEL_DATA.prizes.map( p => ({
    label: p.line2 ? `${p.line1}\n${p.line2}` : p.line1,
    color: p.color || '#6c5ce7',
    emoji: p.emoji || '🎁',
}));

// ── Canvas ───────────────────────────────────────────────────────────────────
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
    ctx.save();
    ctx.translate(R, R);
    ctx.rotate(angle);

    PRIZES.forEach((prize, i) => {
        const start = i * arc;
        const end   = start + arc;

        ctx.beginPath();
        ctx.moveTo(0, 0);
        ctx.arc(0, 0, R - 4, start, end);
        ctx.closePath();
        ctx.fillStyle = prize.color;
        ctx.fill();
        ctx.strokeStyle = 'rgba(255,255,255,0.25)';
        ctx.lineWidth = 2;
        ctx.stroke();

        ctx.save();
        ctx.rotate(start + arc / 2);
        ctx.textAlign = 'right';
        ctx.fillStyle = '#fff';
        ctx.font = 'bold 13px Segoe UI, sans-serif';
        ctx.shadowColor = 'rgba(0,0,0,0.5)';
        ctx.shadowBlur = 4;

        const lines = prize.label.split('\n');
        if (lines.length === 1) {
            ctx.fillText(prize.emoji + ' ' + lines[0], R - 14, 5);
        } else {
            ctx.fillText(prize.emoji + ' ' + lines[0], R - 14, -5);
            ctx.font = '12px Segoe UI, sans-serif';
            ctx.fillText(lines[1], R - 14, 11);
        }
        ctx.restore();
    });

    // Centre
    ctx.beginPath();
    ctx.arc(0, 0, 22, 0, 2 * Math.PI);
    const grad = ctx.createRadialGradient(0, 0, 4, 0, 0, 22);
    grad.addColorStop(0, '#fff9e0');
    grad.addColorStop(1, '#ffd700');
    ctx.fillStyle = grad;
    ctx.fill();
    ctx.strokeStyle = 'rgba(255,255,255,0.8)';
    ctx.lineWidth = 2;
    ctx.stroke();

    ctx.restore();
}

function getWinnerFromAngle(angle) {
    const a = ((-Math.PI / 2 - angle) % (2 * Math.PI) + 2 * Math.PI) % (2 * Math.PI);
    return Math.floor(a / arc) % N;
}

drawWheel(currentAngle);

// ── Déjà joué : restituer l'état ────────────────────────────────────────────
if (WHEEL_DATA.alreadyPlayed && WHEEL_DATA.playedData) {
    document.getElementById('spinBtn').disabled    = true;
    document.getElementById('spinBtn').textContent = 'JOUÉ ✓';
    wonIndex = WHEEL_DATA.playedData.index;
    showResult(false);
}

// ── Spin ─────────────────────────────────────────────────────────────────────
function spin() {
    if (isSpinning) return;
    isSpinning = true;
    document.getElementById('spinBtn').disabled = true;

    const totalRotation = (5 + Math.random() * 3) * 2 * Math.PI + Math.random() * 2 * Math.PI;
    const finalAngle    = currentAngle + totalRotation;
    const duration      = 4500 + Math.random() * 1000;
    const startTime     = performance.now();
    const startAngle    = currentAngle;

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
            isSpinning   = false;
            wonIndex     = getWinnerFromAngle(finalAngle);
            savePlay(wonIndex);
        }
    }

    requestAnimationFrame(animate);
}

// ── Sauvegarder via AJAX (WordPress) ─────────────────────────────────────────
function savePlay(index) {
    const prize      = PRIZES[index];
    const prizeLabel = prize.emoji + ' ' + prize.label.replace('\n', ' ');

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
    .catch(() => showResult(true)); // Afficher quand même en cas d'erreur réseau
}

// ── Résultat ──────────────────────────────────────────────────────────────────
function showResult(withConfetti) {
    const prize     = PRIZES[wonIndex];
    const prizeText = prize.emoji + ' ' + prize.label.replace('\n', ' ');

    document.getElementById('prizeLabel').textContent = prizeText;
    document.getElementById('resultBanner').style.display = 'block';

    const claimBtn = document.getElementById('claimBtn');
    claimBtn.style.display = 'block';
    claimBtn.onclick = () => {
        window.location.href = WHEEL_DATA.rewardUrl
            + '&prize=' + encodeURIComponent(prizeText);
    };

    if (withConfetti) launchConfetti();
}

// ── Confettis ─────────────────────────────────────────────────────────────────
function launchConfetti() {
    const c      = document.getElementById('confettiContainer');
    const colors = ['#ffd700','#ff6b35','#00c851','#2196f3','#e91e63','#9c27b0'];
    for (let i = 0; i < 80; i++) {
        const el = document.createElement('div');
        el.className = 'confetti-piece';
        el.style.left              = Math.random() * 100 + 'vw';
        el.style.background        = colors[Math.floor(Math.random() * colors.length)];
        el.style.borderRadius      = Math.random() > 0.5 ? '50%' : '2px';
        el.style.width             = (6 + Math.random() * 8) + 'px';
        el.style.height            = (6 + Math.random() * 8) + 'px';
        el.style.animationDuration = (1.5 + Math.random() * 2) + 's';
        el.style.animationDelay    = (Math.random() * 0.8) + 's';
        c.appendChild(el);
        setTimeout(() => el.remove(), 4000);
    }
}
</script>
</body>
</html>
