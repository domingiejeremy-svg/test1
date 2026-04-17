# Changelog

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
