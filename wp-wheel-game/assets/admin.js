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
        updateWeightBar();
    }

    // ── Créer une ligne ───────────────────────────────────────────────────────
    function createRow(p, i) {
        const row = document.createElement('div');
        row.className = 'prize-row';

        // Emoji
        const emoji = inp('text', p.emoji || '🎁', 'Emoji');
        emoji.style.textAlign = 'center';
        emoji.style.fontSize  = '1.2rem';
        emoji.addEventListener('input', () => { prizes[i].emoji = emoji.value; syncJson(); updateWeightBar(); });

        // Ligne 1
        const line1 = inp('text', p.line1 || '', 'Texte principal');
        line1.addEventListener('input', () => { prizes[i].line1 = line1.value; syncJson(); updateWeightBar(); });

        // Ligne 2
        const line2 = inp('text', p.line2 || '', 'Ligne 2 (optionnel)');
        line2.addEventListener('input', () => { prizes[i].line2 = line2.value; syncJson(); });

        // Poids
        const weight = document.createElement('input');
        weight.type        = 'number';
        weight.value       = p.weight || 10;
        weight.min         = 1;
        weight.max         = 9999;
        weight.placeholder = 'Poids';
        weight.addEventListener('input', () => {
            prizes[i].weight = Math.max(1, parseInt(weight.value) || 1);
            syncJson();
            updateWeightBar();
        });

        // Couleur
        const color = document.createElement('input');
        color.type  = 'color';
        color.value = p.color || '#6c5ce7';
        color.addEventListener('input', () => { prizes[i].color = color.value; syncJson(); updateWeightBar(); });

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
        row.appendChild(weight);
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
    function updateWeightBar() {
        const bar = document.getElementById('weight-bar');
        if (!bar) return;

        const total = prizes.reduce((sum, p) => sum + (p.weight || 10), 0);

        bar.innerHTML = prizes.map(p => {
            const w   = p.weight || 10;
            const pct = (w / total * 100).toFixed(1);
            const label = p.emoji
                ? `${p.emoji} ${pct}%`
                : pct + '%';
            return `<div style="flex:${w};background:${p.color || '#6c5ce7'}"
                         title="${(p.emoji || '') + ' ' + (p.line1 || '')} → ${pct}% de chances">
                        ${parseFloat(pct) >= 6 ? label : (parseFloat(pct) >= 3 ? pct + '%' : '')}
                    </div>`;
        }).join('');
    }

    // ── Ajouter ───────────────────────────────────────────────────────────────
    function addPrize() {
        prizes.push({ emoji: '🎁', line1: 'Nouveau prix', line2: '', color: '#6c5ce7', weight: 10 });
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
