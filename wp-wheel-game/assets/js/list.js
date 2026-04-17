/**
 * Liste des campagnes — bouton copier URL.
 */
(function () {
    'use strict';
    document.querySelectorAll('.wg-copy-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const url = btn.dataset.url;
            if (!url || !navigator.clipboard) return;
            navigator.clipboard.writeText(url).then(() => {
                const orig = btn.textContent;
                btn.textContent = '✓ Copié';
                setTimeout(() => { btn.textContent = orig; }, 2000);
            });
        });
    });
})();
