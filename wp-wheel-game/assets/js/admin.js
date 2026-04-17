/**
 * Admin JS — Wheel Game v2
 */
(function () {
    'use strict';

    const I18N  = (window.WheelGameAdmin && window.WheelGameAdmin.i18n) || {};
    const AJAX  = (window.WheelGameAdmin && window.WheelGameAdmin.ajaxUrl) || window.ajaxurl;
    const NONCE = (window.WheelGameAdmin && window.WheelGameAdmin.nonce) || '';

    let prizes = [];

    function ready(fn) {
        if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', fn);
        else fn();
    }

    ready(function () {
        initTabs();
        initPrizes();
        initLogoPicker();
        initCopy();
        initMonteCarlo();
        initTemplates();
        initGoogleFetch();
        initHistoryTabs();
    });

    function initTabs() {
        const tabs   = document.querySelectorAll('.wg-tab-btn');
        const panels = document.querySelectorAll('.wg-tab-content');
        if (!tabs.length) return;
        tabs.forEach(btn => btn.addEventListener('click', () => {
            tabs.forEach(t => t.classList.remove('is-active'));
            panels.forEach(p => p.classList.remove('is-active'));
            btn.classList.add('is-active');
            const panel = document.querySelector(`.wg-tab-content[data-panel="${btn.dataset.tab}"]`);
            if (panel) panel.classList.add('is-active');
        }));
    }

    function initCopy() {
        document.querySelectorAll('.wg-copy-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const url = btn.dataset.url;
                if (!url || !navigator.clipboard) return;
                navigator.clipboard.writeText(url).then(() => {
                    const orig = btn.textContent;
                    btn.textContent = I18N.copied || '✓ Copié';
                    setTimeout(() => { btn.textContent = orig; }, 2000);
                });
            });
        });
    }

    function initLogoPicker() {
        const btn     = document.getElementById('reward-logo-btn');
        const remove  = document.getElementById('reward-logo-remove');
        const input   = document.getElementById('reward-logo-input');
        const preview = document.getElementById('reward-logo-preview');
        if (!btn) return;
        let frame;
        btn.addEventListener('click', e => {
            e.preventDefault();
            if (frame) { frame.open(); return; }
            frame = wp.media({
                title: I18N.chooseLogo || 'Choisir le logo',
                button: { text: I18N.useLogo || 'Utiliser' },
                multiple: false, library: { type: 'image' },
            });
            frame.on('select', () => {
                const att = frame.state().get('selection').first().toJSON();
                input.value = att.url;
                preview.src = att.url;
                preview.style.display = '';
                remove.style.display  = '';
            });
            frame.open();
        });
        remove && remove.addEventListener('click', () => {
            input.value = '';
            preview.src = '';
            preview.style.display = 'none';
            remove.style.display  = 'none';
        });
    }

    function initPrizes() {
        const jsonInput = document.getElementById('wheel-prizes-json');
        if (!jsonInput) return;
        try { prizes = JSON.parse(jsonInput.value) || []; } catch (e) { prizes = []; }
        renderAll();
        const addBtn = document.getElementById('add-prize-btn');
        addBtn && addBtn.addEventListener('click', addPrize);
    }

    function renderAll() {
        const list = document.getElementById('prizes-list');
        if (!list) return;
        list.innerHTML = '';
        prizes.forEach((p, i) => list.appendChild(createRow(p, i)));
        syncJson();
        updateBar();
    }

    function createRow(p, i) {
        const row = document.createElement('div');
        row.className = 'prize-row';

        const emoji = inp('text', p.emoji || '🎁', 'Emoji');
        emoji.style.textAlign = 'center'; emoji.style.fontSize = '1.2rem';
        emoji.addEventListener('input', () => { prizes[i].emoji = emoji.value; syncJson(); updateBar(); });

        const line1 = inp('text', p.line1 || '', 'Texte principal');
        line1.addEventListener('input', () => { prizes[i].line1 = line1.value; syncJson(); updateBar(); });

        const line2 = inp('text', p.line2 || '', 'Ligne 2 (optionnel)');
        line2.addEventListener('input', () => { prizes[i].line2 = line2.value; syncJson(); });

        const pct = document.createElement('input');
        pct.type = 'number';
        pct.value = (p.percent !== undefined ? parseFloat(p.percent) : 10).toFixed(2);
        pct.min = '0'; pct.max = '100'; pct.step = 'any'; pct.placeholder = '%';
        const onPct = () => {
            const v = pct.value.trim();
            prizes[i].percent = v === '' ? 0 : Math.max(0, parseFloat(v) || 0);
            syncJson(); updateBar();
        };
        pct.addEventListener('input', onPct);
        pct.addEventListener('change', onPct);

        const color = document.createElement('input');
        color.type = 'color'; color.value = p.color || '#6c5ce7';
        color.addEventListener('input', () => { prizes[i].color = color.value; syncJson(); updateBar(); });

        const del = document.createElement('button');
        del.type = 'button'; del.className = 'del-btn';
        del.textContent = '✕'; del.title = 'Supprimer';
        del.addEventListener('click', () => {
            if (prizes.length <= 2) { alert(I18N.minPrizes || 'Minimum 2 prix.'); return; }
            prizes.splice(i, 1); renderAll();
        });

        [emoji, line1, line2, pct, color, del].forEach(el => row.appendChild(el));
        return row;
    }

    function inp(type, value, placeholder) {
        const el = document.createElement('input');
        el.type = type; el.value = value; el.placeholder = placeholder;
        return el;
    }

    function addPrize() {
        prizes.push({ emoji: '🎁', line1: 'Nouveau prix', line2: '', color: '#6c5ce7', percent: 10 });
        renderAll();
        document.getElementById('prizes-list').lastChild?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    function syncJson() {
        const input = document.getElementById('wheel-prizes-json');
        if (input) input.value = JSON.stringify(prizes);
    }

    function updateBar() {
        const bar = document.getElementById('weight-bar');
        if (!bar) return;
        const total = prizes.reduce((s, p) => s + (parseFloat(p.percent) || 0), 0);

        const totalEl = document.getElementById('percent-total');
        if (totalEl) {
            const rounded = Math.round(total * 100) / 100;
            const ok = Math.abs(total - 100) < 0.1;
            totalEl.textContent = 'Total : ' + rounded.toFixed(2) + '%';
            totalEl.style.color = ok ? '#00b894' : '#e74c3c';
            totalEl.style.fontWeight = '700';
        }

        bar.innerHTML = prizes.map(p => {
            const w = parseFloat(p.percent) || 0;
            const label = p.emoji ? `${p.emoji} ${w.toFixed(2)}%` : w.toFixed(2) + '%';
            return `<div style="flex:${w};background:${p.color || '#6c5ce7'}" title="${(p.emoji || '') + ' ' + (p.line1 || '')} → ${w.toFixed(2)}%">
                ${w >= 6 ? label : (w >= 2 ? w.toFixed(2) + '%' : '')}
            </div>`;
        }).join('');
    }

    function initMonteCarlo() {
        const btn = document.getElementById('mc-run');
        if (!btn) return;
        btn.addEventListener('click', () => {
            const iterSel = document.getElementById('mc-iter');
            const iter = iterSel ? parseInt(iterSel.value, 10) : 10000;
            const out = document.getElementById('mc-results');
            out.innerHTML = `<p style="color:#666">${I18N.simulating || 'Simulation…'}</p>`;

            fetch(AJAX, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'wheel_monte_carlo',
                    nonce: NONCE,
                    iterations: iter,
                    prizes: JSON.stringify(prizes),
                }),
            }).then(r => r.json()).then(res => {
                if (!res.success) { out.innerHTML = `<p style="color:#e74c3c">${res.data || 'Erreur'}</p>`; return; }
                renderMC(out, res.data);
            }).catch(() => { out.innerHTML = `<p style="color:#e74c3c">Erreur réseau</p>`; });
        });
    }

    function renderMC(container, data) {
        const rows = [
            `<div class="mc-row mc-head-row">
                <span>Prix</span><span>Réglé</span><span>Théorique</span><span>Observé</span>
            </div>`
        ];
        data.results.forEach(r => {
            const dev = r.theoretical > 0 ? (r.observed - r.theoretical) : 0;
            const dc = Math.abs(dev) < 0.5 ? '#999' : (dev > 0 ? '#00b894' : '#e74c3c');
            rows.push(`<div class="mc-row">
                <span><span class="mc-color" style="background:${r.color}"></span>${escapeHtml(r.label)}</span>
                <span>${r.percent_set}%</span>
                <span>${r.theoretical}%</span>
                <span>${r.observed}%<span class="mc-deviation" style="color:${dc}">(${dev >= 0 ? '+' : ''}${dev.toFixed(2)})</span></span>
            </div>`);
        });
        container.innerHTML = rows.join('') +
            `<p style="font-size:11px;color:#666;margin-top:8px">${data.iterations.toLocaleString()} tirages simulés · écart théorique/observé en vert/rouge.</p>`;
    }

    function initTemplates() {
        document.querySelectorAll('.wg-tpl-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const slug = btn.dataset.template;
                const campaign = btn.dataset.campaign;
                if (!slug || !campaign) return;
                if (!confirm('Remplacer les prix actuels par le template "' + btn.textContent.trim() + '" ?')) return;
                fetch(AJAX, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        action: 'wheel_apply_template',
                        nonce: NONCE, template: slug, campaign_id: campaign,
                    }),
                }).then(r => r.json()).then(res => {
                    if (res.success) window.location.reload();
                    else alert(res.data || 'Erreur');
                });
            });
        });
    }

    function initGoogleFetch() {
        const btn = document.getElementById('wg-fetch-btn');
        if (!btn) return;
        btn.addEventListener('click', () => {
            const msg = document.getElementById('wg-fetch-msg');
            const key = document.getElementById('wg-api-key').value.trim();
            const id = btn.dataset.campaign;
            const nonce = btn.dataset.nonce;
            if (!key) { msg.style.color = '#e74c3c'; msg.textContent = 'Clé API requise.'; return; }
            btn.disabled = true;
            msg.style.color = '#666';
            msg.textContent = 'Récupération…';
            fetch(AJAX, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'wheel_fetch_google_stats',
                    nonce, campaign_id: id, api_key: key,
                }),
            }).then(r => r.json()).then(data => {
                btn.disabled = false;
                if (data.success) {
                    msg.style.color = '#00b894';
                    msg.textContent = '✓ ' + data.data.rating + ' ⭐ · ' + data.data.review_count + ' avis';
                    setTimeout(() => location.reload(), 1500);
                } else {
                    msg.style.color = '#e74c3c';
                    msg.textContent = '✗ ' + (data.data || 'Erreur');
                }
            }).catch(() => { btn.disabled = false; msg.style.color = '#e74c3c'; msg.textContent = 'Erreur réseau'; });
        });
    }

    function initHistoryTabs() {
        const tabs = document.querySelectorAll('.wg-hist-tab');
        if (!tabs.length) return;
        const apply = tab => {
            tabs.forEach(t => { t.style.background = '#f8f9fb'; t.style.color = '#555'; });
            tab.style.background = '#6c5ce7'; tab.style.color = '#fff';
            const filter = tab.dataset.tab;
            let cutoff = null;
            if (filter !== 'all') {
                const days = filter === '7j' ? 7 : 30;
                const d = new Date(); d.setDate(d.getDate() - days + 1);
                cutoff = d.toISOString().slice(0, 10);
            }
            document.querySelectorAll('#wg-history-body tr').forEach(row => {
                row.style.display = (!cutoff || row.dataset.date >= cutoff) ? '' : 'none';
            });
        };
        tabs.forEach(t => t.addEventListener('click', () => apply(t)));
        apply(tabs[0]);
    }

    function escapeHtml(s) {
        const d = document.createElement('div');
        d.textContent = String(s || '');
        return d.innerHTML;
    }
})();
