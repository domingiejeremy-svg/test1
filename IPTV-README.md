# 📺 Lecteur IPTV navigateur

Un lecteur IPTV **directement dans le navigateur**, sans installer d'application.
Compatible avec les abonnements **Xtream Codes** (URL du serveur + utilisateur + mot de passe).

## Utilisation rapide

1. Ouvre `iptv.html` dans ton navigateur (double-clic, ou dépose-le sur Chrome/Firefox/Safari).
2. Saisis :
   - **URL du serveur** — ex. `http://monserveur.com:8080`
   - **Utilisateur** et **Mot de passe**
3. Clique **Se connecter**. Tu retrouves tes **Chaînes**, **Films** et **Séries**, avec recherche, catégories et favoris (★).

Les identifiants sont stockés uniquement dans ton navigateur (localStorage) et ne partent que vers ton serveur IPTV.

## Si la connexion échoue (« Échec réseau » / écran noir)

C'est presque toujours un problème de **CORS** : par sécurité, le navigateur refuse de parler à un serveur qui n'y autorise pas explicitement les pages web. C'est *la* raison pour laquelle on te demande d'habitude une appli dédiée.

Solution incluse — un mini-proxy local :

```bash
node iptv-proxy.js
```

Puis dans le lecteur → **Options avancées → Préfixe proxy**, mets :

```
http://localhost:8787/
```

Reconnecte-toi : le proxy relaie les requêtes et ajoute les en-têtes CORS manquants.

> ⚠️ Le proxy est à usage **local uniquement**. Ne l'expose pas sur Internet : tes identifiants IPTV transitent par lui.

## Formats de flux

| Contenu            | Lecture                                   |
|--------------------|-------------------------------------------|
| Chaînes en direct  | HLS (`.m3u8`) via hls.js, repli MPEG-TS (`.ts`) via mpegts.js |
| Films (VOD)        | Lecture native (`.mp4`/`.mkv`)            |
| Séries             | Premier épisode disponible                |

Certains flux `.mkv`/`.avi` ne sont pas lisibles nativement par les navigateurs — c'est une limite du navigateur, pas du lecteur.

## Fichiers

- `iptv.html` — le lecteur (tout-en-un, à ouvrir dans le navigateur)
- `iptv-proxy.js` — proxy CORS local optionnel (Node.js, sans dépendance)

## Notes techniques

- hls.js et mpegts.js sont chargés depuis un CDN (jsDelivr) → une connexion Internet est nécessaire au premier chargement.
- Aucune donnée n'est envoyée à un tiers ; tout se passe entre ton navigateur, le proxy local (si utilisé) et ton serveur IPTV.
