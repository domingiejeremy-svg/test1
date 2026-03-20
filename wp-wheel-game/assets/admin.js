/**
 * Admin JS — Wheel Game Plugin
 * Gestion dynamique de la liste des prix dans la meta box WordPress.
 */
(function () {
    'use strict';

    let prizes = [];

    // ── Init : lire le JSON sauvegardé et rendre les lignes ─────────────────
    function init() {
        const input = document.getElementById('wheel-prizes-json');
        if (!input) return;

        try {
            prizes = JSON.parse(input.value) || [];
        } catch (e) {
            prizes = [];
        }

        renderAll();

        document.getElementById('add-prize-btn')
            .addEventListener('click', addPrize);
    }

    // ── Rendu complet de la liste ────────────────────────────────────────────
    function renderAll() {
        const list = document.getElementById('prizes-list');
        list.innerHTML = '';
        prizes.forEach((p, i) => list.appendChild(createRow(p, i)));
        syncJson();
    }

    // ── Créer une ligne de prix ──────────────────────────────────────────────
    function createRow(p, i) {
        const row = document.createElement('div');
        row.className = 'prize-row';

        // Emoji
        const emoji = input('text', p.emoji || '🎁', 'Emoji');
        emoji.style.textAlign = 'center';
        emoji.style.fontSize  = '1.2rem';
        emoji.addEventListener('input', () => { prizes[i].emoji = emoji.value; syncJson(); });

        // Ligne 1
        const line1 = input('text', p.line1 || '', 'Texte ligne 1');
        line1.addEventListener('input', () => { prizes[i].line1 = line1.value; syncJson(); });

        // Ligne 2
        const line2 = input('text', p.line2 || '', 'Ligne 2 (optionnel)');
        line2.addEventListener('input', () => { prizes[i].line2 = line2.value; syncJson(); });

        // Couleur
        const color = document.createElement('input');
        color.type  = 'color';
        color.value = p.color || '#6c5ce7';
        color.addEventListener('input', () => { prizes[i].color = color.value; syncJson(); });

        // Bouton supprimer
        const del = document.createElement('button');
        del.type      = 'button';
        del.className = 'del-btn';
        del.textContent = '✕';
        del.title     = 'Supprimer ce prix';
        del.addEventListener('click', () => deletePrize(i));

        row.appendChild(emoji);
        row.appendChild(line1);
        row.appendChild(line2);
        row.appendChild(color);
        row.appendChild(del);

        return row;
    }

    function input(type, value, placeholder) {
        const el = document.createElement('input');
        el.type        = type;
        el.value       = value;
        el.placeholder = placeholder;
        return el;
    }

    // ── Ajouter un prix ──────────────────────────────────────────────────────
    function addPrize() {
        prizes.push({ emoji: '🎁', line1: 'Nouveau', line2: 'prix', color: '#6c5ce7' });
        renderAll();
        // Scroller vers le bas de la liste
        const list = document.getElementById('prizes-list');
        list.lastChild && list.lastChild.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    // ── Supprimer un prix ────────────────────────────────────────────────────
    function deletePrize(i) {
        if (prizes.length <= 2) {
            alert('Minimum 2 prix requis sur la roue.');
            return;
        }
        prizes.splice(i, 1);
        renderAll();
    }

    // ── Synchroniser le champ JSON caché ────────────────────────────────────
    function syncJson() {
        const input = document.getElementById('wheel-prizes-json');
        if (input) input.value = JSON.stringify(prizes);
    }

    // ── Démarrage ────────────────────────────────────────────────────────────
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
