# Wheel Game — Roue des cadeaux (v2.0.0)

Plugin WordPress pour héberger des jeux de roue personnalisés, capturer des leads et booster les avis Google de vos clients commerçants.

Utilisé en production sur [boostezvotrereputation.fr](https://boostezvotrereputation.fr).

## Fonctionnalités

### 🎡 Roue personnalisable
- 2 à N prix par campagne, pondérés en % (probabilités libres)
- Couleurs, emojis, textes libres
- Personnalisation visuelle : fond, police, couleur d'accent, son de célébration
- Canvas HTML5 responsive, animations fluides

### 👤 Capture de leads (v2)
- Formulaire optionnel **avant tirage** (prénom, nom, email, téléphone, consentement RGPD)
- Dédoublonnage par email par campagne
- Export CSV par campagne ou global

### 📊 Suivi ROI & conversion (v2)
- Dashboard avec KPIs globaux : tirages, leads, clics Google, taux conversion
- Tracking non-bloquant du clic sur bouton Google Review (`sendBeacon`)
- Historique des notes Google avec delta "avant/après roue"
- Distribution réelle des prix vs probabilité théorique

### 🔒 Sécurité (v2)
- Cookies signés HMAC-SHA256 (anti-forgery)
- Anti-replay multi-couches : cookie + hash IP + user-agent (fenêtre 30j)
- Tokens à durée de vie limitée
- Nonces WordPress sur toutes les actions AJAX
- Sanitization typée par champ (url, email, int, bool, color, date, csv)
- `SameSite=Lax` + `HttpOnly` + `Secure` si HTTPS
- Clé API Google masquée (`type=password`)

### 🛠️ Admin avancé (v2)
- Meta box en 8 onglets
- Simulateur Monte-Carlo intégré (1 000 à 100 000 itérations)
- Templates préfaits : restaurant, coiffeur, boulangerie, garage, institut, café, fleuriste
- Date de fin de campagne + quota maximum de tirages
- Notification email au commerçant quand un gros lot est gagné
- QR Code téléchargeable en PNG 600/1200/2000 et SVG vectoriel

### 📈 Google Places
- Cache transient 1h
- Cron quotidien à 03:17 avec retry exponentiel + logs

### 🌍 i18n
- Text domain `wheel-game`, POT fourni

## Architecture

```
wp-wheel-game/
├─ wp-wheel-game.php        # Bootstrap + autoload
├─ uninstall.php            # Drop tables + options + posts
├─ README.md / CHANGELOG.md / readme.txt
├─ includes/
│  ├─ class-plugin.php      # Orchestrateur
│  ├─ class-activator.php   # DB install/uninstall
│  ├─ class-cpt.php         # CPT wheel_campaign
│  ├─ class-campaign.php    # Helper config (1 get_post_meta)
│  ├─ class-router.php      # Dispatch templates
│  ├─ class-assets.php      # Enqueue
│  ├─ class-admin.php       # Meta boxes + pages + sauvegarde
│  ├─ class-ajax.php        # Tous les endpoints AJAX
│  ├─ class-security.php    # HMAC + cookies + anti-replay
│  ├─ class-leads.php
│  ├─ class-analytics.php
│  ├─ class-google-api.php  # + cache transient
│  ├─ class-cron.php        # + retry + logs
│  ├─ class-monte-carlo.php
│  ├─ class-templates-library.php
│  ├─ class-csv.php
│  └─ views/  (meta boxes + pages admin)
├─ assets/
│  ├─ css/ (admin.css, wheel.css, reward.css)
│  └─ js/  (admin.js, list.js, wheel.js)
├─ templates/
│  ├─ wheel.php   # Roue + lead form
│  └─ reward.php  # Page cadeau + tracking clic
└─ languages/
   └─ wheel-game.pot
```

## Hooks

```php
do_action( 'wheel_game_after_save_campaign', $post_id );
do_action( 'wheel_game_after_create_lead', $lead_id, $campaign_id, $data );
do_action( 'wheel_game_after_play', $play_id, $campaign_id, $prize_index );
```

## Tables BDD

| Table                     | Rôle |
|---------------------------|------|
| `wp_wheel_plays`          | Participations + clic Google |
| `wp_wheel_leads`          | Leads capturés |
| `wp_wheel_google_stats`   | Snapshots rating/avis |
| `wp_wheel_cron_log`       | Journal cron (purge 30j) |

## Installation

1. Uploader le zip via Extensions → Ajouter
2. Activer
3. Configurer la clé API Google Places dans **Roue des cadeaux → ⚙️ Réglages**
4. Créer une campagne

## Requis

- WordPress 6.0+
- PHP 8.0+

## Licence

GPL v2 or later.
