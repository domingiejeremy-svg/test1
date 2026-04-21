/**
 * Outil Classement local (version simplifiée de l'audit).
 */
(function () {
    'use strict';
    const S = window.SALES_RANKING;
    if (!S) return;

    document.getElementById('rank-run').addEventListener('click', run);

    function run() {
        const prospectId = document.getElementById('rank-prospect-id').value.trim();
        const competitorsRaw = document.getElementById('rank-competitors').value.trim();
        const competitors = competitorsRaw.split(/[\n,]/).map(s => s.trim()).filter(Boolean);

        if (!prospectId) { showError('Le Place ID du prospect est requis.'); return; }
        if (competitors.length < 1) { showError('Entrez au moins 1 concurrent.'); return; }
        if (competitors.length > 5) { showError('Maximum 5 concurrents.'); return; }

        hideError();
        document.getElementById('rank-loading').style.display = 'block';
        document.getElementById('rank-result').style.display = 'none';
        document.getElementById('rank-run').disabled = true;

        fetch(S.ajaxUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                action: 'wheel_sales_ranking',
                nonce:  S.nonce,
                place_ids: JSON.stringify([prospectId].concat(competitors)),
            }),
        })
        .then(r => r.json())
        .then(res => {
            document.getElementById('rank-loading').style.display = 'none';
            document.getElementById('rank-run').disabled = false;
            if (!res.success) { showError(res.data || 'Erreur'); return; }
            render(prospectId, res.data);
        })
        .catch(() => {
            document.getElementById('rank-loading').style.display = 'none';
            document.getElementById('rank-run').disabled = false;
            showError('Erreur réseau');
        });
    }

    function render(prospectId, results) {
        const all = results.filter(r => !r.error);
        const sorted = all.slice().sort((a, b) => (b.rating - a.rating) || (b.review_count - a.review_count));
        const prospect = results[0];
        const rank = sorted.findIndex(r => r.place_id === prospectId) + 1;

        document.getElementById('rank-position').textContent = '#' + rank;
        document.getElementById('rank-title').textContent =
            rank === 1 ? `🏆 ${prospect.name || 'Vous'} — Leader local !`
                       : `${prospect.name || 'Votre établissement'} est ${rank}e / ${sorted.length}`;
        document.getElementById('rank-subtitle').textContent =
            `${prospect.rating} ⭐ · ${prospect.review_count} avis Google`;

        document.getElementById('rank-list-body').innerHTML = sorted.map((r, idx) => {
            const isProspect = r.place_id === prospectId;
            return `<tr class="${isProspect ? 'is-prospect' : ''}">
                <td><strong>${idx + 1}</strong></td>
                <td><strong>${escapeHtml(r.name || r.place_id)}</strong>${isProspect ? ' <em>(vous)</em>' : ''}</td>
                <td>${r.rating} ⭐</td>
                <td>${r.review_count}</td>
            </tr>`;
        }).join('');

        document.getElementById('rank-result').style.display = 'block';
        document.getElementById('rank-result').scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function showError(msg) { const el = document.getElementById('rank-error'); el.textContent = msg; el.style.display = 'block'; }
    function hideError() { document.getElementById('rank-error').style.display = 'none'; }
    function escapeHtml(s) { const d = document.createElement('div'); d.textContent = String(s || ''); return d.innerHTML; }
})();
