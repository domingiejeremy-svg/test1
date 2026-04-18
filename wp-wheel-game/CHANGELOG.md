# Changelog

## [2.1.0] — 2026-04-18

### Onboarding client automatisé

- 🆕 **Auto-création de la roue à la commande WooCommerce** — dès paiement reçu, la campagne est créée en brouillon avec le template générique. Le client reçoit un email avec un lien unique pour paramétrer sa roue.
- 🆕 **Champ "Offre BVR"** sur les produits WooCommerce — dropdown Starter / Booster / All Inclusive. Détermine le quota de modifs et les features débloquées.
- 🆕 **Page publique `/configurer-ma-roue/`** — interface premium, standalone (pas dans wp-admin) avec :
  - Formulaire complet : nom, prix/probas, couleurs, logo
  - Upload logo par drag-and-drop
  - Preview live de la roue pendant la saisie
  - Écran de récap avant validation finale
  - Modal de succès avec URL à copier
- 🆕 **Redirection automatique** de la page "Merci" WooCommerce vers `/configurer-ma-roue/`
- 🆕 **Classe `Wheel_Game_Offer`** — gère les règles par tier :
  - Starter : 1 modif/an, 500 tirages/mois, stats de base
  - Booster : 5 modifs/an, 2000 tirages/mois, stats avancées, capture lead, rapport mensuel
  - All Inclusive : modifs illimitées, tirages illimités, + alertes avis, roues saisonnières, sync Mailchimp, dashboard partagé
- 🆕 **Compteur de modifications** automatique — décompte à chaque publication après la 1ère, blocage si quota atteint avec message de contact.
- 🆕 **Token de configuration unique** — permet au client d'accéder à sa page de config sans login (via lien email).
- 🆕 **Rôle WP `wheel_merchant`** — créé à l'activation, préparé pour l'espace client restreint (v2.2).
- 🆕 **Création automatique du compte WP** pour les guest checkouts.

### Corrections

- 🐛 Détection de l'offre via les line items de la commande (priorité à la plus haute si plusieurs produits).

## [2.0.0] — 2026-04-17

### Refonte architecturale complète

- Architecture : 1 fichier de 944 lignes → 15+ classes modulaires + views
- Assets : ~1500 lignes de CSS/JS inline → fichiers séparés
- Config lecture : ~20 `get_post_meta` dispersés → 1 appel via `Wheel_Game_Campaign::get()`

### Ajouts

- 🆕 Capture de leads avant tirage (prénom, nom, email, téléphone, consentement RGPD)
- 🆕 Dashboard ROI + KPIs par campagne
- 🆕 Tracking clic bouton Google via `sendBeacon`
- 🆕 7 templates préfaits (restaurant, coiffeur, boulangerie, garage, institut, café, fleuriste)
- 🆕 Simulateur Monte-Carlo (1k à 100k itérations)
- 🆕 Date de fin + quota max de tirages
- 🆕 Notification email gros lot
- 🆕 Export CSV (leads + tirages) avec BOM UTF-8
- 🆕 Personnalisation visuelle (couleurs, police, son)
- 🆕 Meta box en 8 onglets
- 🆕 QR Code multi-format (PNG 600/1200/2000 + SVG)
- 🆕 Page Réglages avec journal cron
- 🆕 Hooks `wheel_game_after_*`
- 🆕 i18n complet (text domain `wheel-game`)

### Sécurité

- 🔒 Tokens HMAC-SHA256 signés
- 🔒 Anti-replay multi-couches : cookie + hash IP + user-agent (fenêtre 30j)
- 🔒 Cookies `SameSite=Lax` + `HttpOnly`
- 🔒 Clé API Google masquée
- 🔒 Sanitization typée par champ
- 🔒 Validation index prize côté serveur
- 🔒 `uninstall.php` propre

### Performance

- ⚡ Cache transient 1h sur Google Places API
- ⚡ Cron avec retry exponentiel + throttle 500ms
- ⚡ Heure cron fixe (03:17)
- ⚡ Enqueue conditionnel des assets admin
- ⚡ Index BDD sur `ip_hash`, `played_at`, `lead_id`

### Corrections

- 🐛 Prix sur page cadeau : source fiable (cookie signé), fallback URL
- 🐛 Cookie anti-replay base64 non-signé → HMAC signé
- 🐛 Absence `uninstall.php` en v1.x → ajouté

## [1.6.1] — 2025

- Suivi avis Google + cron quotidien
- Mode preview admin

## [1.6.0]

- Première version avec suivi Google
