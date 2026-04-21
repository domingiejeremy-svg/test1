# Changelog

## [2.3.1] — 2026-04-21

### Ajouts
- 🆕 **Colonne "Offre"** dans la liste de toutes les campagnes (avec badge coloré Starter/Booster/Premium). Triable.
- 🆕 **Sélecteur d'offre** dans la meta box "🎛️ Features & exceptions" : permet à l'admin de choisir ou changer l'offre du client depuis la fiche campagne (utile pour les créations manuelles).

### Corrections
- 🐛 **Fix page Commerciaux blanche** : erreur de syntaxe PHP (mix `{ }` et `: endif;`) dans `page-sales-reps.php` — la page s'affiche maintenant correctement.

## [2.3.0] — 2026-04-21

### Commerciaux & outils de vente

- 🆕 **Rôle `wheel_sales`** (Commercial BVR) — créé à l'activation, accès restreint à son espace.
- 🆕 **Page admin `💼 Commerciaux`** (Roue des cadeaux → Commerciaux) :
  - Créer un commercial (email → compte WP auto + mail de définition mot de passe)
  - Assigner un **code coupon WooCommerce** existant (un coupon = un seul commercial)
  - Définir le **% de commission** par commercial
  - Voir ses stats : ventes totales, CA généré, commissions, activité 30j
  - Liste des commandes attribuées avec statut
- 🆕 **Attribution automatique des ventes** : dès qu'une commande WooCommerce utilise un coupon attribué à un commercial, elle est marquée dans les métas + commission calculée sur le sous-total HT (après remise).
- 🆕 **Email de notification** automatique au commercial à chaque nouvelle vente (objet, montant, commission).
- 🆕 **Espace public `/espace-commercial/`** — design premium, accessible par login WP :
  - Dashboard : KPIs (ventes, CA, commissions, activité 30j) + code coupon en évidence
  - Liste des ventes récentes avec statut et commission par ligne
- 🆕 **Outil "🎯 Audit concurrentiel"** (`/espace-commercial/audit/`) :
  - Input : Place ID prospect + 2 à 5 Place IDs concurrents
  - Récupération temps réel via Google Places API
  - Rapport visuel avec : carte du prospect, tableau classement, identification du leader, écarts de note et de volume d'avis
  - Calcul automatique du "temps de rattrapage" (X mois à +1 avis/jour)
  - Bouton "Imprimer / Enregistrer en PDF" via navigateur
  - **Tracking background activé dès la création** de l'audit : on commence à stocker les snapshots Google dans une nouvelle table `wheel_prospect_tracking` pour avoir de l'historique pour les relances
- 🆕 **Outil "🏆 Classement local"** (`/espace-commercial/ranking/`) — version simplifiée : affiche uniquement le rang du prospect dans son marché local avec une visu héro très punchy.
- 🆕 **Table BDD `wheel_prospect_tracking`** — créée dynamiquement au premier audit (historique des snapshots Google des prospects, pour relances).

### Amélioré

- 🔄 Cache transient 1h étendu aux détails de Place ID (nom, adresse, types) pour éviter les doublons d'appel API.
- 🔄 Menu admin réorganisé : Dashboard, Leads, Commerciaux, Templates, Offres & Features, Réglages.

## [2.2.0] — 2026-04-19

### Système de features modulaires

- 🆕 **Registre centralisé des features** (`Wheel_Game_Features`) : `wheel_creation`, `qr_code`, `mods_1/5/unlimited_per_year`, `google_reviews_tracking`, `lead_capture`, `monthly_report`, `conversion_optimization`.
- 🆕 **Page admin "⚙️ Offres & Features"** : matrice cochable offres × features. Active/désactive n'importe quelle feature pour n'importe quelle offre sans toucher au code. Gestion automatique de l'exclusivité entre `mods_1`, `mods_5`, `mods_unlimited`.
- 🆕 **Exceptions par client** : meta box "🎛️ Features & exceptions" sur chaque fiche campagne (admin only). Ajouter ou retirer une feature pour un client spécifique (ex: offrir la capture lead à un client Starter par sympathie).
- 🆕 **Gating dynamique** : tous les checks de features utilisent désormais le registre (`Wheel_Game_Features::has()`). La modification de la matrice s'applique instantanément.
- 🔄 **Renommage "All Inclusive" → "Premium"** partout (avec migration automatique des anciennes campagnes).
- 🔄 **Capture de lead** conditionnée par la feature `lead_capture` : si désactivée sur une offre, le formulaire ne s'affiche plus et l'endpoint AJAX bloque la soumission côté serveur.
- 🔄 **Quota de modifs** piloté par les features `mods_1_per_year`, `mods_5_per_year`, `mods_unlimited` (mutuellement exclusives).

### Corrections

- 🐛 Migration auto du slug `all_inclusive` → `premium` pour les campagnes et produits existants.

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
