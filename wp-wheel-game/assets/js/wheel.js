/**
 * Wheel Game — Front roue + formulaire lead.
 */
(function () {
    'use strict';
    const D = window.WHEEL_DATA || {};

    // FORMULAIRE LEAD
    const leadForm = document.getElementById('lead-form');
    if (leadForm) {
        leadForm.addEventListener('submit', function (e) {
            e.preventDefault();
            const err = document.getElementById('lead-error');
            const btn = leadForm.querySelector('.lead-submit');
            err.style.display = 'none';
            btn.disabled = true;
            const origLabel = btn.textContent;
            btn.textContent = '…';

            const fd = new FormData(leadForm);
            const body = new URLSearchParams({
                action: 'wheel_submit_lead',
                nonce:  D.leadNonce,
                campaign_id: D.campaignId,
            });
            fd.forEach((v, k) => body.append(k, v));

            fetch(D.ajaxUrl, { method: 'POST', body })
                .then(r => r.json())
                .then(res => {
                    if (res.success) window.location.reload();
                    else {
                        err.textContent = res.data || 'Erreur';
                        err.style.display = 'block';
                        btn.disabled = false;
                        btn.textContent = origLabel;
                    }
                })
                .catch(() => {
                    err.textContent = 'Erreur réseau';
                    err.style.display = 'block';
                    btn.disabled = false;
                    btn.textContent = origLabel;
                });
        });
        return;
    }

    // ROUE
    const canvas = document.getElementById('wheel');
    if (!canvas) return;

    const ctx = canvas.getContext('2d');
    const ACCENT = (D.colors && D.colors.accent) || '#ffd700';

    const PRIZES = (D.prizes || []).map(p => ({
        label:   p.line2 ? `${p.line1}\n${p.line2}` : p.line1,
        color:   p.color || '#1a1a2e',
        emoji:   p.emoji || '🎁',
        percent: p.percent !== undefined ? parseFloat(p.percent) : 10,
    }));

    const N = PRIZES.length;
    const arc = (2 * Math.PI) / N;
    const R = canvas.width / 2;

    let currentAngle = 0, isSpinning = false, wonIndex = null;

    function drawWheel(angle) {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        ctx.save();
        ctx.translate(R, R);
        ctx.rotate(angle);

        PRIZES.forEach((prize, i) => {
            const start = i * arc;
            const end = start + arc;

            ctx.beginPath(); ctx.moveTo(0, 0); ctx.arc(0, 0, R - 10, start, end); ctx.closePath();
            ctx.fillStyle = prize.color; ctx.fill();

            ctx.beginPath(); ctx.moveTo(0, 0); ctx.arc(0, 0, R - 10, start, end); ctx.closePath();
            ctx.strokeStyle = hexRgba(ACCENT, 0.45); ctx.lineWidth = 1.5; ctx.stroke();

            ctx.save();
            ctx.rotate(start + arc / 2);
            ctx.textAlign = 'right';
            const lum = luminance(prize.color);
            ctx.fillStyle = lum < 0.4 ? ACCENT : '#fff';
            ctx.shadowColor = 'rgba(0,0,0,0.85)';
            ctx.shadowBlur = 5; ctx.shadowOffsetX = 1; ctx.shadowOffsetY = 1;

            const lines = prize.label.split('\n');
            if (lines.length === 1) {
                ctx.font = 'bold 14px inherit';
                ctx.fillText(prize.emoji + ' ' + lines[0], R - 18, 5);
            } else {
                ctx.font = 'bold 14px inherit';
                ctx.fillText(prize.emoji + ' ' + lines[0], R - 18, -5);
                ctx.font = '11px inherit';
                ctx.shadowBlur = 3;
                ctx.fillText(lines[1], R - 18, 11);
            }
            ctx.restore();
        });

        ctx.beginPath(); ctx.arc(0, 0, R - 2, 0, 2 * Math.PI);
        ctx.strokeStyle = '#0d1b2a'; ctx.lineWidth = 16; ctx.stroke();
        ctx.beginPath(); ctx.arc(0, 0, R - 2, 0, 2 * Math.PI);
        ctx.strokeStyle = hexRgba(ACCENT, 0.8); ctx.lineWidth = 3; ctx.stroke();
        ctx.restore();

        ctx.save(); ctx.translate(R, R);
        const cr = 44;
        ctx.beginPath(); ctx.arc(0, 0, cr + 7, 0, 2 * Math.PI);
        ctx.fillStyle = '#0d1b2a'; ctx.fill();

        ctx.shadowColor = hexRgba(ACCENT, 0.6); ctx.shadowBlur = 18;
        ctx.beginPath(); ctx.arc(0, 0, cr, 0, 2 * Math.PI);
        const grad = ctx.createRadialGradient(-12, -12, 2, 0, 0, cr);
        grad.addColorStop(0, lighten(ACCENT, 0.3));
        grad.addColorStop(0.65, ACCENT);
        grad.addColorStop(1, darken(ACCENT, 0.4));
        ctx.fillStyle = grad; ctx.fill();
        ctx.shadowBlur = 0;

        ctx.beginPath(); ctx.arc(0, 0, cr, 0, 2 * Math.PI);
        ctx.strokeStyle = 'rgba(255,255,255,0.55)'; ctx.lineWidth = 2.5; ctx.stroke();

        ctx.fillStyle = '#1a1a2e';
        ctx.font = 'bold 26px inherit';
        ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
        ctx.fillText('GO', 0, 0);
        ctx.restore();
    }

    drawWheel(currentAngle);

    const testCounts = new Array(PRIZES.length).fill(0);

    if (D.alreadyPlayed && D.playedData) {
        const spinBtn = document.getElementById('spinBtn');
        if (spinBtn) spinBtn.disabled = true;
        wonIndex = D.playedData.index;
        showResult(false);
    }

    function weightedRandom() {
        const eligible = PRIZES.map((p, i) => ({ i, w: p.percent })).filter(x => x.w > 0);
        if (eligible.length === 0) return 0;
        const total = eligible.reduce((s, x) => s + x.w, 0);
        let r = Math.random() * total;
        for (const { i, w } of eligible) { r -= w; if (r <= 0) return i; }
        return eligible[eligible.length - 1].i;
    }

    function spin() {
        if (isSpinning) return;
        isSpinning = true;
        const spinBtn = document.getElementById('spinBtn');
        spinBtn.disabled = true;

        wonIndex = weightedRandom();
        const segCenter = wonIndex * arc + arc / 2;
        const randOffset = (Math.random() - 0.5) * arc * 0.7;
        const baseFinal = -Math.PI / 2 - segCenter + randOffset;
        const minFinal = currentAngle + 5 * 2 * Math.PI;
        const k = Math.ceil((minFinal - baseFinal) / (2 * Math.PI));
        const finalAngle = baseFinal + k * 2 * Math.PI;

        const duration = 4500 + Math.random() * 1000;
        const startTime = performance.now();
        const startAngle = currentAngle;
        const easeOut = t => 1 - Math.pow(1 - t, 4);

        function animate(now) {
            const t = Math.min((now - startTime) / duration, 1);
            currentAngle = startAngle + (finalAngle - startAngle) * easeOut(t);
            drawWheel(currentAngle);
            if (t < 1) requestAnimationFrame(animate);
            else { currentAngle = finalAngle; drawWheel(currentAngle); isSpinning = false; savePlay(wonIndex); }
        }
        requestAnimationFrame(animate);
    }

    const spinBtnEl = document.getElementById('spinBtn');
    if (spinBtnEl) spinBtnEl.addEventListener('click', spin);

    function savePlay(index) {
        const prize = PRIZES[index];
        const prizeLabel = prize.emoji + ' ' + prize.label.replace('\n', ' ');

        if (D.isAdminTest) {
            testCounts[index]++;
            showResult(true);
            return;
        }

        fetch(D.ajaxUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                action: 'wheel_save_play',
                nonce: D.playNonce,
                campaign_id: D.campaignId,
                prize_index: index,
                prize_label: prizeLabel,
            }),
        })
        .then(r => r.json())
        .then(res => {
            if (res.success && res.data && res.data.play_token) {
                try { sessionStorage.setItem('wheel_play_token_' + D.campaignId, res.data.play_token); } catch(e) {}
            }
            showResult(true);
        })
        .catch(() => showResult(true));
    }

    function showResult(withConfetti) {
        const prize = PRIZES[wonIndex];
        const prizeText = prize.emoji + ' ' + prize.label.replace('\n', ' ');
        document.getElementById('prizeLabel').textContent = prizeText;
        document.getElementById('resultBanner').style.display = 'block';
        if (D.sound && withConfetti) playBeep();
        if (D.isAdminTest) {
            document.getElementById('replayBtn').style.display = 'block';
            updateTestStats();
        } else {
            document.getElementById('redirectCountdown').style.display = 'block';
            setTimeout(() => { window.location.href = D.rewardUrl + '&prize=' + encodeURIComponent(prizeText); }, 1200);
        }
        if (withConfetti) launchConfetti();
    }

    const replayBtn = document.getElementById('replayBtn');
    if (replayBtn) replayBtn.addEventListener('click', () => {
        document.getElementById('spinBtn').disabled = false;
        document.getElementById('resultBanner').style.display = 'none';
        document.getElementById('replayBtn').style.display = 'none';
        wonIndex = null;
    });

    function updateTestStats() {
        const el = document.getElementById('testStats');
        if (!el) return;
        el.style.display = 'block';
        const total = testCounts.reduce((a, b) => a + b, 0);
        const maxCount = Math.max(...testCounts, 1);
        document.getElementById('statsRows').innerHTML = PRIZES.map((p, i) => {
            const count = testCounts[i];
            const pct = total > 0 ? (count / total * 100).toFixed(1) : '0.0';
            const bw = (count / maxCount * 100).toFixed(1);
            const label = p.emoji + ' ' + p.label.replace('\n', ' ');
            return `<div class="stat-row">
                <span class="stat-label" title="Probabilité : ${p.percent.toFixed(2)}%">${label}</span>
                <div class="stat-bar-wrap"><div class="stat-bar" style="width:${bw}%;background:${p.color}"></div></div>
                <span class="stat-count">${count}</span>
                <span class="stat-pct">${pct}%</span>
            </div>`;
        }).join('');
        document.getElementById('statsTotal').textContent = `${total} tirage(s) · % cible affichés en hover`;
    }

    function launchConfetti() {
        const c = document.getElementById('confettiContainer');
        const palette = [ACCENT, lighten(ACCENT, 0.3), '#ffffff', '#ff8c00', lighten(ACCENT, 0.5)];
        for (let i = 0; i < 90; i++) {
            const el = document.createElement('div');
            el.className = 'confetti-piece';
            el.style.left = Math.random() * 100 + 'vw';
            el.style.background = palette[Math.floor(Math.random() * palette.length)];
            el.style.borderRadius = Math.random() > 0.5 ? '50%' : '2px';
            el.style.width = (5 + Math.random() * 8) + 'px';
            el.style.height = (5 + Math.random() * 8) + 'px';
            el.style.animationDuration = (1.5 + Math.random() * 2) + 's';
            el.style.animationDelay = (Math.random() * 0.8) + 's';
            c.appendChild(el);
            setTimeout(() => el.remove(), 4000);
        }
    }

    function playBeep() {
        try {
            const AC = window.AudioContext || window.webkitAudioContext;
            if (!AC) return;
            const ac = new AC();
            const o = ac.createOscillator();
            const g = ac.createGain();
            o.type = 'sine'; o.frequency.value = 900;
            g.gain.setValueAtTime(0.18, ac.currentTime);
            g.gain.exponentialRampToValueAtTime(0.001, ac.currentTime + 0.4);
            o.connect(g); g.connect(ac.destination);
            o.start(); o.stop(ac.currentTime + 0.4);
        } catch (e) {}
    }

    function hexRgba(hex, a) {
        const h = hex.replace('#', '');
        const full = h.length === 3 ? h.split('').map(c => c + c).join('') : h;
        return `rgba(${parseInt(full.slice(0,2),16)},${parseInt(full.slice(2,4),16)},${parseInt(full.slice(4,6),16)},${a})`;
    }
    function luminance(hex) {
        const h = hex.replace('#', '');
        const full = h.length === 3 ? h.split('').map(c => c + c).join('') : h;
        const r = parseInt(full.slice(0,2),16)/255, g = parseInt(full.slice(2,4),16)/255, b = parseInt(full.slice(4,6),16)/255;
        return 0.2126*r + 0.7152*g + 0.0722*b;
    }
    function lighten(hex, amt) { return mix(hex, '#ffffff', amt); }
    function darken(hex, amt) { return mix(hex, '#000000', amt); }
    function mix(c1, c2, t) {
        const a = c1.replace('#',''), b = c2.replace('#','');
        const ra = parseInt(a.slice(0,2),16), ga = parseInt(a.slice(2,4),16), ba = parseInt(a.slice(4,6),16);
        const rb = parseInt(b.slice(0,2),16), gb = parseInt(b.slice(2,4),16), bb = parseInt(b.slice(4,6),16);
        const r = Math.round(ra + (rb-ra)*t), g = Math.round(ga + (gb-ga)*t), bl = Math.round(ba + (bb-ba)*t);
        return '#' + [r,g,bl].map(v => v.toString(16).padStart(2,'0')).join('');
    }
})();
