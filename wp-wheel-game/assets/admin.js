/**
 * Admin JS — Wheel Game Plugin
 * Gestion dynamique de la liste des prix dans la meta box WordPress.
 */
(function () {
    'use strict';

    let prizes = [];

    // ── Init ─────────────────────────────────────────────────────────────────
    function init() {
        const input = document.getElementById('wheel-prizes-json');
        if (!input) return;

        try { prizes = JSON.parse(input.value) || []; }
        catch (e) { prizes = []; }

        renderAll();

        document.getElementById('add-prize-btn')
            .addEventListener('click', addPrize);
    }

    // ── Rendu complet ────────────────────────────────────────────────────────
    function renderAll() {
        const list = document.getElementById('prizes-list');
        list.innerHTML = '';
        prizes.forEach((p, i) => list.appendChild(createRow(p, i)));
        syncJson();
        updatePercentBar();
    }

    // ── Créer une ligne ───────────────────────────────────────────────────────
    function createRow(p, i) {
        const row = document.createElement('div');
        row.className = 'prize-row';

        // Emoji
        const emoji = inp('text', p.emoji || '🎁', 'Emoji');
        emoji.style.textAlign = 'center';
        emoji.style.fontSize  = '1.2rem';
        emoji.addEventListener('input', () => { prizes[i].emoji = emoji.value; syncJson(); updatePercentBar(); });

        // Ligne 1
        const line1 = inp('text', p.line1 || '', 'Texte principal');
        line1.addEventListener('input', () => { prizes[i].line1 = line1.value; syncJson(); updatePercentBar(); });

        // Ligne 2
        const line2 = inp('text', p.line2 || '', 'Ligne 2 (optionnel)');
        line2.addEventListener('input', () => { prizes[i].line2 = line2.value; syncJson(); });

        // Pourcentage
        const pct = document.createElement('input');
        pct.type        = 'number';
        pct.value       = (p.percent !== undefined ? parseFloat(p.percent) : 10).toFixed(2);
        pct.min         = 0;
        pct.max         = 100;
        pct.step        = 0.01;
        pct.placeholder = '%';
        pct.addEventListener('input', () => {
            const v = pct.value.trim();
            prizes[i].percent = v === '' ? 0 : Math.max(0, parseFloat(v));
            syncJson();
            updatePercentBar();
        });

        // Couleur
        const color = document.createElement('input');
        color.type  = 'color';
        color.value = p.color || '#6c5ce7';
        color.addEventListener('input', () => { prizes[i].color = color.value; syncJson(); updatePercentBar(); });

        // Supprimer
        const del = document.createElement('button');
        del.type      = 'button';
        del.className = 'del-btn';
        del.textContent = '✕';
        del.title     = 'Supprimer ce prix';
        del.addEventListener('click', () => deletePrize(i));

        row.appendChild(emoji);
        row.appendChild(line1);
        row.appendChild(line2);
        row.appendChild(pct);
        row.appendChild(color);
        row.appendChild(del);

        return row;
    }

    function inp(type, value, placeholder) {
        const el = document.createElement('input');
        el.type        = type;
        el.value       = value;
        el.placeholder = placeholder;
        return el;
    }

    // ── Barre de probabilité visuelle ─────────────────────────────────────────
    function updatePercentBar() {
        const bar = document.getElementById('weight-bar');
        if (!bar) return;

        const total = prizes.reduce((sum, p) => sum + (parseFloat(p.percent) || 0), 0);

        // Indicateur de total
        const totalEl = document.getElementById('percent-total');
        if (totalEl) {
            const rounded = Math.round(total * 100) / 100;
            const ok = Math.abs(total - 100) < 0.1;
            totalEl.textContent = 'Total : ' + rounded.toFixed(2) + '%';
            totalEl.style.color = ok ? '#00b894' : '#e74c3c';
            totalEl.style.fontWeight = '700';
        }

        bar.innerHTML = prizes.map(p => {
            const w   = parseFloat(p.percent) || 0;
            const pct = total > 0 ? (w / total * 100).toFixed(1) : '0';
            const label = p.emoji
                ? `${p.emoji} ${w.toFixed(2)}%`
                : w.toFixed(2) + '%';
            return `<div style="flex:${w};background:${p.color || '#6c5ce7'}"
                         title="${(p.emoji || '') + ' ' + (p.line1 || '')} → ${w.toFixed(2)}% de chances">
                        ${w >= 6 ? label : (w >= 2 ? w.toFixed(2) + '%' : '')}
                    </div>`;
        }).join('');
    }

    // ── Ajouter ───────────────────────────────────────────────────────────────
    function addPrize() {
        prizes.push({ emoji: '🎁', line1: 'Nouveau prix', line2: '', color: '#6c5ce7', percent: 10 });
        renderAll();
        document.getElementById('prizes-list').lastChild
            ?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    // ── Supprimer ─────────────────────────────────────────────────────────────
    function deletePrize(i) {
        if (prizes.length <= 2) {
            alert('Minimum 2 prix requis sur la roue.');
            return;
        }
        prizes.splice(i, 1);
        renderAll();
    }

    // ── Sync JSON caché ───────────────────────────────────────────────────────
    function syncJson() {
        const input = document.getElementById('wheel-prizes-json');
        if (input) input.value = JSON.stringify(prizes);
    }

    // ── Démarrage ─────────────────────────────────────────────────────────────
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
