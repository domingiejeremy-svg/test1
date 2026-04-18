/**
 * Page publique de configuration de la roue.
 * Requiert window.WHEEL_CONFIG injecté par le template.
 */
(function () {
    'use strict';
    const C = window.WHEEL_CONFIG;
    if (!C) return;

    let prizes = [];
    let logoUrl = document.getElementById('cfg-logo-url')?.value || '';
    let isPublishing = false;

    function ready(fn) {
        if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', fn);
        else fn();
    }

    ready(function () {
        initPrizes();
        initColors();
        initLogo();
        initValidation();
        redrawPreview();
    });

    // ═══ PRIX ══════════════════════════════════════════════════════════════
    function initPrizes() {
        const input = document.getElementById('cfg-prizes-json');
        try { prizes = JSON.parse(input.value) || []; } catch (e) { prizes = []; }
        if (!prizes.length) prizes = C.defaultPrizes.slice();
        renderPrizes();
        document.getElementById('cfg-add-prize').addEventListener('click', addPrize);
    }

    function renderPrizes() {
        const list = document.getElementById('cfg-prizes-list');
        list.innerHTML = '';
        prizes.forEach((p, i) => list.appendChild(createPrizeRow(p, i)));
        updateWeightBar();
        redrawPreview();
    }

    function createPrizeRow(p, i) {
        const row = document.createElement('div');
        row.className = 'cfg-prize-row';

        const emoji = input('text', p.emoji || '🎁', 'Emoji');
        emoji.style.textAlign = 'center'; emoji.style.fontSize = '1.1rem';
        emoji.addEventListener('input', () => { prizes[i].emoji = emoji.value; update(); });

        const line1 = input('text', p.line1 || '', 'Texte principal');
        line1.addEventListener('input', () => { prizes[i].line1 = line1.value; update(); });

        const line2 = input('text', p.line2 || '', 'Ligne 2 (optionnel)');
        line2.addEventListener('input', () => { prizes[i].line2 = line2.value; update(); });

        const pct = document.createElement('input');
        pct.type = 'number';
        pct.value = (p.percent !== undefined ? parseFloat(p.percent) : 10).toFixed(2);
        pct.min = '0'; pct.max = '100'; pct.step = 'any'; pct.placeholder = '%';
        const onPct = () => {
            const v = pct.value.trim();
            prizes[i].percent = v === '' ? 0 : Math.max(0, parseFloat(v) || 0);
            update();
        };
        pct.addEventListener('input', onPct);
        pct.addEventListener('change', onPct);

        const color = document.createElement('input');
        color.type = 'color'; color.value = p.color || '#6c5ce7';
        color.addEventListener('input', () => { prizes[i].color = color.value; update(); });

        const del = document.createElement('button');
        del.type = 'button'; del.className = 'cfg-del-btn'; del.textContent = '✕';
        del.addEventListener('click', () => {
            if (prizes.length <= 2) { alert('Minimum 2 cadeaux sur la roue.'); return; }
            prizes.splice(i, 1); renderPrizes();
        });

        [emoji, line1, line2, pct, color, del].forEach(el => row.appendChild(el));
        return row;
    }

    function input(type, value, placeholder) {
        const el = document.createElement('input');
        el.type = type; el.value = value; el.placeholder = placeholder;
        return el;
    }

    function addPrize() {
        prizes.push({ emoji: '🎁', line1: 'Nouveau cadeau', line2: '', color: '#6c5ce7', percent: 10 });
        renderPrizes();
    }

    function update() {
        syncJson();
        updateWeightBar();
        redrawPreview();
    }

    function syncJson() {
        const input = document.getElementById('cfg-prizes-json');
        input.value = JSON.stringify(prizes);
    }

    function updateWeightBar() {
        const bar = document.getElementById('cfg-weight-bar');
        const total = prizes.reduce((s, p) => s + (parseFloat(p.percent) || 0), 0);

        const totalEl = document.getElementById('cfg-percent-total');
        const rounded = Math.round(total * 100) / 100;
        const ok = Math.abs(total - 100) < 0.1;
        totalEl.textContent = 'Total : ' + rounded.toFixed(2) + '%';
        totalEl.style.color = ok ? '#10b981' : (total > 100 ? '#dc2626' : '#f59e0b');

        bar.innerHTML = prizes.map(p => {
            const w = parseFloat(p.percent) || 0;
            const label = p.emoji ? `${p.emoji} ${w.toFixed(0)}%` : w.toFixed(0) + '%';
            return `<div style="flex:${w};background:${p.color || '#6c5ce7'}">${w >= 8 ? label : (w >= 3 ? w.toFixed(0) + '%' : '')}</div>`;
        }).join('');
    }

    // ═══ COULEURS ══════════════════════════════════════════════════════════
    function initColors() {
        document.getElementById('cfg-bg1').addEventListener('input', redrawPreview);
        document.getElementById('cfg-bg2').addEventListener('input', redrawPreview);
        document.getElementById('cfg-accent').addEventListener('input', redrawPreview);
    }

    function redrawPreview() {
        const bg1    = document.getElementById('cfg-bg1').value;
        const bg2    = document.getElementById('cfg-bg2').value;
        const accent = document.getElementById('cfg-accent').value;

        const box = document.getElementById('cfg-preview-box');
        box.style.setProperty('--pv-bg1', bg1);
        box.style.setProperty('--pv-bg2', bg2);
        box.style.background = `linear-gradient(160deg, ${bg1} 0%, ${bg2} 50%, ${bg1} 100%)`;

        drawWheel(accent);
    }

    function drawWheel(accent) {
        const canvas = document.getElementById('cfg-preview-canvas');
        if (!canvas) return;
        const ctx = canvas.getContext('2d');
        const N = prizes.length;
        if (N < 2) return;
        const arc = (2 * Math.PI) / N;
        const R = canvas.width / 2;

        ctx.clearRect(0, 0, canvas.width, canvas.height);

        // Flèche (top)
        ctx.save();
        ctx.fillStyle = accent;
        ctx.beginPath();
        ctx.moveTo(R - 12, 6); ctx.lineTo(R + 12, 6); ctx.lineTo(R, 30); ctx.closePath();
        ctx.fill();
        ctx.restore();

        ctx.save();
        ctx.translate(R, R);

        prizes.forEach((prize, i) => {
            const start = i * arc - Math.PI / 2;
            const end = start + arc;
            ctx.beginPath(); ctx.moveTo(0, 0); ctx.arc(0, 0, R - 12, start, end); ctx.closePath();
            ctx.fillStyle = prize.color || '#6c5ce7'; ctx.fill();

            ctx.save();
            ctx.rotate(start + arc / 2);
            ctx.textAlign = 'right';
            const lum = luminance(prize.color || '#6c5ce7');
            ctx.fillStyle = lum < 0.4 ? accent : '#fff';
            ctx.shadowColor = 'rgba(0,0,0,0.6)';
            ctx.shadowBlur = 4;
            ctx.font = 'bold 11px Inter, sans-serif';
            const text = (prize.emoji || '') + ' ' + (prize.line1 || '');
            ctx.fillText(text, R - 18, 4);
            ctx.restore();
        });

        // Anneau extérieur
        ctx.beginPath(); ctx.arc(0, 0, R - 4, 0, 2 * Math.PI);
        ctx.strokeStyle = accent; ctx.lineWidth = 4; ctx.stroke();

        // Centre
        ctx.beginPath(); ctx.arc(0, 0, 28, 0, 2 * Math.PI);
        ctx.fillStyle = '#fff'; ctx.fill();
        ctx.strokeStyle = accent; ctx.lineWidth = 2; ctx.stroke();
        ctx.fillStyle = accent;
        ctx.font = 'bold 14px Inter, sans-serif';
        ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
        ctx.fillText('GO', 0, 0);

        ctx.restore();
    }

    function luminance(hex) {
        const h = hex.replace('#', '');
        const full = h.length === 3 ? h.split('').map(c => c + c).join('') : h;
        const r = parseInt(full.slice(0,2),16)/255, g = parseInt(full.slice(2,4),16)/255, b = parseInt(full.slice(4,6),16)/255;
        return 0.2126*r + 0.7152*g + 0.0722*b;
    }

    // ═══ LOGO ══════════════════════════════════════════════════════════════
    function initLogo() {
        const zone  = document.getElementById('cfg-logo-zone');
        const input = document.getElementById('cfg-logo-input');
        const btn   = document.getElementById('cfg-logo-btn');
        const removeBtn = document.getElementById('cfg-logo-remove');

        zone.addEventListener('click', e => { if (e.target === zone || e.target.tagName === 'P' || e.target.tagName === 'SPAN') input.click(); });
        btn.addEventListener('click', () => input.click());

        zone.addEventListener('dragover', e => { e.preventDefault(); zone.classList.add('drag-over'); });
        zone.addEventListener('dragleave', () => zone.classList.remove('drag-over'));
        zone.addEventListener('drop', e => {
            e.preventDefault(); zone.classList.remove('drag-over');
            if (e.dataTransfer.files.length) uploadLogo(e.dataTransfer.files[0]);
        });

        input.addEventListener('change', () => {
            if (input.files.length) uploadLogo(input.files[0]);
        });

        removeBtn.addEventListener('click', () => {
            logoUrl = '';
            document.getElementById('cfg-logo-url').value = '';
            document.getElementById('cfg-logo-preview').style.display = 'none';
            document.getElementById('cfg-logo-empty').style.display = '';
            removeBtn.style.display = 'none';
        });
    }

    function uploadLogo(file) {
        const fd = new FormData();
        fd.append('action', 'wheel_config_upload_logo');
        fd.append('nonce',  C.nonce);
        fd.append('campaign_id', C.campaignId);
        fd.append('token', C.token);
        fd.append('logo', file);

        const empty = document.getElementById('cfg-logo-empty');
        empty.innerHTML = '<span>⏳</span><p>Upload en cours…</p>';

        fetch(C.ajaxUrl, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(res => {
                if (res.success && res.data.url) {
                    logoUrl = res.data.url;
                    document.getElementById('cfg-logo-url').value = logoUrl;
                    const img = document.getElementById('cfg-logo-preview');
                    img.src = logoUrl; img.style.display = '';
                    empty.style.display = 'none';
                    document.getElementById('cfg-logo-remove').style.display = '';
                } else {
                    alert(res.data || 'Erreur d\'upload');
                    empty.innerHTML = '<span>🖼️</span><p>Cliquez pour réessayer</p>';
                }
            })
            .catch(() => {
                alert('Erreur réseau');
                empty.innerHTML = '<span>🖼️</span><p>Cliquez pour réessayer</p>';
            });
    }

    // ═══ VALIDATION ═══════════════════════════════════════════════════════
    function initValidation() {
        document.getElementById('cfg-validate').addEventListener('click', showConfirmModal);
        document.getElementById('cfg-modal-cancel').addEventListener('click', () => hideModal('cfg-modal-confirm'));
        document.getElementById('cfg-modal-confirm-btn').addEventListener('click', publish);
        document.getElementById('cfg-copy-url').addEventListener('click', copyUrl);
    }

    function showConfirmModal() {
        const recap = document.getElementById('cfg-recap');
        const total = prizes.reduce((s, p) => s + (parseFloat(p.percent) || 0), 0);
        const title = document.getElementById('cfg-title').value.trim() || '(sans titre)';

        recap.innerHTML = `
            <div class="r-row"><span>Nom :</span><strong>${escapeHtml(title)}</strong></div>
            <div class="r-row"><span>Offre :</span><strong>${escapeHtml(C.offerLabel)}</strong></div>
            <div class="r-row"><span>Nombre de cadeaux :</span><strong>${prizes.length}</strong></div>
            <div class="r-row"><span>Total probabilités :</span><strong>${total.toFixed(2)}%</strong></div>
            <div class="r-row"><span>Logo :</span><strong>${logoUrl ? '✓' : '— (à ajouter plus tard)'}</strong></div>
        `;
        showModal('cfg-modal-confirm');
    }

    function showModal(id) { document.getElementById(id).style.display = 'flex'; }
    function hideModal(id) { document.getElementById(id).style.display = 'none'; }

    function publish() {
        if (isPublishing) return;
        isPublishing = true;
        const btn = document.getElementById('cfg-modal-confirm-btn');
        btn.disabled = true;
        btn.textContent = '⏳ Publication…';

        const body = new URLSearchParams({
            action: 'wheel_config_publish',
            nonce:  C.nonce,
            campaign_id: C.campaignId,
            token:  C.token,
            campaign_title: document.getElementById('cfg-title').value,
            prizes: JSON.stringify(prizes),
            bg_color_1:   document.getElementById('cfg-bg1').value,
            bg_color_2:   document.getElementById('cfg-bg2').value,
            accent_color: document.getElementById('cfg-accent').value,
            logo_url: logoUrl,
        });

        fetch(C.ajaxUrl, { method: 'POST', body })
            .then(r => r.json())
            .then(res => {
                isPublishing = false;
                if (res.success) {
                    hideModal('cfg-modal-confirm');
                    const url = res.data.url;
                    document.getElementById('cfg-success-url-input').value = url;
                    document.getElementById('cfg-goto-wheel').href = url;
                    if (!res.data.was_first) {
                        document.getElementById('cfg-success-title').textContent = 'Modification validée !';
                        document.getElementById('cfg-success-msg').textContent =
                            res.data.mods_remaining === -1
                                ? 'Modifications illimitées dans votre offre.'
                                : `Il vous reste ${res.data.mods_remaining} modification(s).`;
                    }
                    showModal('cfg-modal-success');
                } else {
                    btn.disabled = false;
                    btn.textContent = C.isFirst ? '🚀 Publier ma roue' : '✓ Valider la modification';
                    alert(res.data || 'Erreur');
                }
            })
            .catch(() => {
                isPublishing = false;
                btn.disabled = false;
                btn.textContent = C.isFirst ? '🚀 Publier ma roue' : '✓ Valider la modification';
                alert('Erreur réseau');
            });
    }

    function copyUrl() {
        const input = document.getElementById('cfg-success-url-input');
        input.select();
        navigator.clipboard.writeText(input.value).then(() => {
            const btn = document.getElementById('cfg-copy-url');
            btn.textContent = '✓ Copié';
            setTimeout(() => { btn.textContent = 'Copier'; }, 2000);
        });
    }

    function escapeHtml(s) {
        const d = document.createElement('div');
        d.textContent = String(s || '');
        return d.innerHTML;
    }
})();
