/**
 * Outil Audit concurrentiel.
 */
(function () {
    'use strict';
    const S = window.SALES_AUDIT;
    if (!S) return;

    if (S.printMode) { tryAutoPrint(); return; }

    document.getElementById('audit-run').addEventListener('click', runAudit);

    function runAudit() {
        const prospectName = document.getElementById('audit-prospect-name').value.trim();
        const prospectId   = document.getElementById('audit-prospect-id').value.trim();
        const competitorsRaw = document.getElementById('audit-competitors').value.trim();
        const competitors = competitorsRaw.split(/[\n,]/).map(s => s.trim()).filter(Boolean);

        if (!prospectId) {
            showError('Le Place ID du prospect est requis.');
            return;
        }
        if (competitors.length < 2) {
            showError('Entrez au moins 2 concurrents.');
            return;
        }
        if (competitors.length > 5) {
            showError('Maximum 5 concurrents.');
            return;
        }

        hideError();
        document.getElementById('audit-loading').style.display = 'block';
        document.getElementById('audit-report').style.display = 'none';
        document.getElementById('audit-run').disabled = true;

        const placeIds = [prospectId].concat(competitors);

        fetch(S.ajaxUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                action: 'wheel_sales_audit',
                nonce:  S.nonce,
                place_ids: JSON.stringify(placeIds),
            }),
        })
        .then(r => r.json())
        .then(res => {
            document.getElementById('audit-loading').style.display = 'none';
            document.getElementById('audit-run').disabled = false;
            if (!res.success) {
                showError(res.data || 'Erreur');
                return;
            }
            renderReport(prospectName, prospectId, res.data);
        })
        .catch(err => {
            document.getElementById('audit-loading').style.display = 'none';
            document.getElementById('audit-run').disabled = false;
            showError('Erreur réseau : ' + err.message);
        });
    }

    function renderReport(prospectName, prospectId, results) {
        const report = document.getElementById('audit-report');
        const prospect = results[0];
        const competitors = results.slice(1).filter(r => !r.error);

        const displayName = prospectName || prospect.name || '(prospect)';
        document.getElementById('audit-title').textContent = `📊 Audit concurrentiel — ${displayName}`;
        document.getElementById('audit-subtitle').textContent = `Comparaison avec ${competitors.length} concurrent(s) local(s)`;

        // Prospect card
        if (prospect.error) {
            document.getElementById('audit-prospect-card').innerHTML =
                `<div class="sc-error">⚠️ Prospect introuvable : ${escapeHtml(prospect.error)}</div>`;
        } else {
            document.getElementById('audit-prospect-card').innerHTML = `
                <div class="name">${escapeHtml(prospect.name)}</div>
                <div style="color:#6b7280;font-size:0.9rem">${escapeHtml(prospect.address)}</div>
                <div class="stats">
                    <div><strong>${prospect.rating}</strong> ⭐ Note Google</div>
                    <div><strong>${prospect.review_count}</strong> avis au total</div>
                </div>
            `;
        }

        // Ranking
        const all = results.filter(r => !r.error);
        // Tri : d'abord par note décroissante, puis par nombre d'avis
        const sorted = all.slice().sort((a, b) => (b.rating - a.rating) || (b.review_count - a.review_count));
        const prospectRank = sorted.findIndex(r => r.place_id === prospectId) + 1;
        const maxReviews = Math.max(...sorted.map(r => r.review_count), 1);

        const rankingHtml = sorted.map((r, idx) => {
            const isProspect = r.place_id === prospectId;
            const barWidth = (r.review_count / maxReviews * 100).toFixed(0);
            return `<tr class="${isProspect ? 'is-prospect' : ''}">
                <td><strong>#${idx + 1}</strong></td>
                <td>
                    <strong>${escapeHtml(r.name || r.place_id)}</strong>${isProspect ? ' <em>(vous)</em>' : ''}
                    <div style="font-size:0.8rem;color:#6b7280">${escapeHtml(r.address || '')}</div>
                </td>
                <td><strong>${r.rating}</strong> ⭐</td>
                <td>
                    <span class="sc-rating-bar"><span class="sc-rating-bar-fill" style="width:${barWidth}%"></span></span>
                    <strong>${r.review_count}</strong>
                </td>
                <td>${getRankBadge(idx + 1)}</td>
            </tr>`;
        }).join('');
        document.getElementById('audit-ranking-body').innerHTML = rankingHtml;

        // Écart
        renderGap(sorted, prospect, prospectRank);

        report.style.display = 'block';
        report.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function renderGap(sorted, prospect, prospectRank) {
        const best = sorted[0];
        const avgRating = sorted.reduce((s, r) => s + r.rating, 0) / sorted.length;
        const avgReviews = Math.round(sorted.reduce((s, r) => s + r.review_count, 0) / sorted.length);

        const points = [];
        if (prospectRank === 1) {
            points.push(`🏆 <strong>Vous êtes leader local</strong> avec ${prospect.rating} ⭐ et ${prospect.review_count} avis. L'objectif est de creuser l'écart pour rester hors d'atteinte.`);
        } else {
            if (best.rating > prospect.rating) {
                const deltaRating = (best.rating - prospect.rating).toFixed(1);
                points.push(`📉 <strong>Écart de note :</strong> ${deltaRating} étoiles avec le leader (${escapeHtml(best.name)} à ${best.rating} ⭐).`);
            }
            if (best.review_count > prospect.review_count) {
                const deltaReviews = best.review_count - prospect.review_count;
                points.push(`📊 <strong>Écart de volume :</strong> ${deltaReviews} avis de retard sur le leader. À raison de <strong>+1 avis par jour</strong>, vous rattrapez en ${Math.ceil(deltaReviews / 30)} mois.`);
            }
        }

        if (prospect.rating < avgRating) {
            points.push(`⚠️ Votre note (${prospect.rating} ⭐) est sous la moyenne de la zone (${avgRating.toFixed(1)} ⭐).`);
        }
        if (prospect.review_count < avgReviews) {
            points.push(`⚠️ Vous avez moins d'avis que la moyenne du quartier (${prospect.review_count} vs ${avgReviews} avis).`);
        }

        points.push(`🎯 <strong>Solution Boostez Votre Réputation :</strong> transformer vos clients en ambassadeurs via une roue cadeaux gamifiée. Nos clients gagnent en moyenne <strong>+30 avis en 90 jours</strong>.`);

        document.getElementById('audit-gap').innerHTML = '<ul><li>' + points.join('</li><li>') + '</li></ul>';
    }

    function getRankBadge(rank) {
        if (rank === 1) return '🥇 <strong>Leader</strong>';
        if (rank === 2) return '🥈 2e';
        if (rank === 3) return '🥉 3e';
        return `${rank}e`;
    }

    function showError(msg) {
        const el = document.getElementById('audit-error');
        el.textContent = msg;
        el.style.display = 'block';
    }
    function hideError() {
        document.getElementById('audit-error').style.display = 'none';
    }
    function escapeHtml(s) {
        const d = document.createElement('div');
        d.textContent = String(s || '');
        return d.innerHTML;
    }
    function tryAutoPrint() {
        setTimeout(() => window.print(), 600);
    }
})();
