# Résultats 47 — le bulletin du week-end

Compile automatiquement les résultats du week-end des clubs de Lot-et-Garonne
(et des voisins suivis) en rugby, football et basket, dans un bulletin prêt à
lire à l'antenne le lundi.

```
RESULTATS SPORTIFS — WEEK-END DU 18/09/2026 AU 20/09/2026

🏉 RUGBY

  Nationale 2

- ✅ US MARMANDE 24 - 17 STADE BAGNERAIS (victoire de Marmande a domicile — J3 — dimanche 20/09 15h00)
- ❌ US VALENCE D'AGEN 12 - 28 STADE MONTOIS (defaite de Valence d'Agen a domicile — J3)
```

## Ce que ça fait

- Interroge les trois fédérations pour les clubs listés dans `config.yaml`.
- Ne retient que les rencontres du week-end (vendredi → dimanche).
- Nomme toujours le club qui gagne ou qui perd — jamais un « victoire à
  domicile » sans sujet, et pas de vainqueur annoncé quand deux clubs suivis
  s'affrontent.
- N'invente jamais un score : un match non publié est signalé comme tel.
- Signale les clubs sans rencontre trouvée, pour que rien ne passe à la trappe.

## Installation

```bash
cd resultats-47
python3 -m venv .venv && source .venv/bin/activate
pip install -r requirements.txt
```

## Utilisation

```bash
# Le bulletin du week-end écoulé
python -m resultats47

# En markdown, dans un fichier
python -m resultats47 bulletin --format markdown -o bulletins/2026-09-21.md

# Un week-end précis, ou une période sur mesure
python -m resultats47 bulletin --date 2026-09-21
python -m resultats47 bulletin --du 2026-09-18 --au 2026-09-20

# Un seul sport
python -m resultats47 bulletin --sport rugby

# En JSON, pour alimenter autre chose
python -m resultats47 bulletin --format json
```

Deux commandes utiles au réglage :

```bash
# Vérifier que chaque fédération répond
python -m resultats47 diagnostiquer

# Retrouver la graphie exacte d'un club chez sa fédération
python -m resultats47 chercher --sport football "Marmande"
```

Et un aperçu sans réseau, pour voir la mise en forme :

```bash
python demo_hors_ligne.py
```

## Régler les clubs

Tout se passe dans `config.yaml`. Chaque club a un `nom` (celui que vous dites
à l'antenne) et des `alias` (les graphies des fédérations) :

```yaml
- sport: rugby
  libelle: Federale 3
  slug: federale-3
  poule: "11"
  clubs:
    - nom: Nerac
      alias: [US Nerac, Neracais]
```

Le rapprochement se fait sur les mots significatifs : `Marmande` retrouve
`US MARMANDAISE`. Deux règles à connaître :

- **Le sport arbitre les homonymes.** `Agen Basket Club` ne se confondra pas
  avec `SU AGEN RUGBY`.
- **Un libellé plus pauvre que l'alias est refusé.** `US LAYRAC` ne passe pas
  pour `Layrac-Astaffort` — il faut ajouter explicitement l'alias court si
  vous le voulez. C'est volontaire : mieux vaut un club en « sans rencontre »
  qu'un résultat faux à l'antenne.

Si un club ressort systématiquement en « Sans rencontre trouvée », lancez
`chercher` et recopiez la graphie exacte dans ses `alias`.

## Les trois sources

| Sport | Source | Accès |
|---|---|---|
| Football | `api-dofa.prd-aws.fff.fr` (API DOFA) | JSON-LD, sans clé. Backend des sites de ligues et districts, dont `epreuves.fff.fr`. |
| Basket | `api.ffbb.app` (Directus) | JSON, jeton porteur public résolu automatiquement. Backend de `competitions.ffbb.com`. |
| Rugby | `monclubhouse.ffr.fr` | Pas d'API publique : on lit le JSON que le front React embarque dans la page. |

### ⚠️ À calibrer au premier lancement

Ces connecteurs ont été écrits d'après les clients officiels de ces API et
testés sur des jeux de données réalistes, **mais ils n'ont jamais tourné
contre les serveurs réels** : l'environnement qui a servi à les développer
n'avait pas accès à ces trois domaines.

Attendez-vous donc à un réglage au premier lancement, en particulier sur les
noms de champs. Le code est écrit pour ça : chaque connecteur essaie plusieurs
noms de champs plausibles, et `diagnostiquer` affiche précisément ce qu'il a
reçu. Prévoyez un quart d'heure, un week-end de championnat, et ces deux
commandes :

```bash
python -m resultats47 diagnostiquer -v
python -m resultats47 bulletin --format json | head -50
```

Le rugby est le plus susceptible de demander un ajustement, faute d'API
documentée. Si la structure de la page change, `ids.api_url` permet de pointer
directement un endpoint JSON dans `config.yaml`.

## Automatisation

Le workflow `.github/workflows/resultats-weekend.yml` génère le bulletin
chaque lundi à 7h (heure de Paris) et le dépose en artefact téléchargeable.
Il se lance aussi à la demande depuis l'onglet Actions.

En local, la même chose avec `cron` :

```cron
0 7 * * 1 cd /chemin/vers/resultats-47 && .venv/bin/python -m resultats47 \
  bulletin --format markdown -o bulletins/$(date +\%Y-\%m-\%d).md
```

## Tests

```bash
python -m unittest discover -s tests -v
```

## Limites

- Les fédérations publient parfois les scores avec du retard, surtout en
  division amateur. Un bulletin du lundi matin peut contenir des rencontres
  « score non publié » : relancez plus tard dans la journée.
- Les scores restent à vérifier avant diffusion.
