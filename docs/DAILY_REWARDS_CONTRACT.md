# DR-01 — Contrat fédéré des Daily Rewards Faluss

## Statut et frontière

DR-01 définit exclusivement le contrat des futurs Daily Rewards Faluss. Il
verrouille deux décisions PF de classe `earned`, leur période commune, leur
idempotence et le read-model minimal par lequel une card d'app pourra déléguer
au moteur propriétaire.

Ce lot n'implémente aucun reward. Il ne crée aucun plugin, archive ZIP, table,
migration, route, endpoint, cron, interface, widget, shortcode, CSS, JavaScript
Portal, écriture PF, solde, paiement, appel Stripe ou comportement WordPress.
Il ne modifie pas le shell PF-01G ni les cards Apps AP-01 : la sémantique de
card qui suit est seulement une exigence pour une implémentation ultérieure.

Le schéma v1 est
[`contracts/faluss-daily-reward.schema.json`](../contracts/faluss-daily-reward.schema.json).
Il décrit un document de statut entre serveurs ; il ne prescrit aucun transport,
route ou mutation implicite.

Les mots **DOIT**, **NE DOIT PAS** et **PEUT** sont normatifs.

## Période quotidienne commune

Tout Daily Reward v1 utilise le jour calendaire serveur `Europe/Paris`. Cette
politique est fixe : aucun réglage WordPress mutable, fuseau navigateur, URL ou
date client ne peut déplacer la période.

- Un jour manqué ne donne lieu à aucun rattrapage, crédit rétroactif ou cumul.
- `hub.daily_accrual` et `me.profile_daily_claim` sont cumulables le même jour.
- Leur maximum quotidien actuel, lorsqu'ils sont tous deux décidés légalement,
  est `95 PF earned`.
- La conséquence future est unique par moteur propriétaire, `reward_key`,
  `faluss_id` côté serveur, date logique `Europe/Paris` et version de politique.

Le `faluss_id`, la clé d'idempotence complète et toute référence interne restent
serveur. Ils ne figurent jamais dans HTML, URL, attribut client, JavaScript ou
cache public. Un double clic, deux onglets, une délégation répétée ou un retry
réseau retrouvent le résultat déjà décidé par le propriétaire ; ils ne créent
jamais une seconde écriture PF.

## Rewards v1 réservés

### Faluss Hub : `hub.daily_accrual`

| Élément | Contrat |
| --- | --- |
| App / propriétaire | `hub` / moteur Faluss Hub (`faluss-hub`) |
| Catégorie PF future | `daily_accrual` |
| Décision économique | crédit de `20 PF`, classe `earned` |
| Éligibilité | identité Faluss active prouvée côté serveur |
| Période / unicité | une attribution par `faluss_id` et jour `Europe/Paris` |
| Délégation permise | `owner_claim` vers Faluss Hub seulement |

La récupération future est explicite depuis la zone d'action de la card Faluss
Hub dans Mes Apps. Elle ne peut jamais être causée par cron, polling, crédit
invisible à la connexion, chargement de page, JavaScript, navigateur, URL ou
paramètre URL seul. La future interface pourra indiquer « Récupérer +20 PF »
puis « Récupéré » ; DR-01 ne produit pas cette interface.

### Faluss Me : `me.profile_daily_claim`

| Élément | Contrat |
| --- | --- |
| App / propriétaire | `me` / moteur Faluss Me (`faluss-me`) |
| Catégorie PF future | `profile_daily_claim` |
| Décision économique | crédit de `75 PF`, classe `earned` |
| Éligibilité | identité active, carte Faluss.me effectivement publiée et handle public réservé, tous prouvés côté serveur |
| Période / unicité | une attribution par `faluss_id` et jour `Europe/Paris` |
| Délégation permise | `owner_navigation` vers la carte Faluss.me publiée du membre |

Seule la propre route publique `faluss.me/@handle` du membre pourra un jour
porter la récupération effective et seule Faluss.me émettra l'éventuelle
écriture PF. La card Faluss Me ne fait que déléguer la navigation :
`owner_navigation` n'est jamais une attribution réussie. Un membre sans carte
publiée ou sans handle réservé reste éligible au reward Hub de `20 PF`, mais pas
au bonus Faluss Me de `75 PF`.

## Contrat de statut quotidien d'application

Un moteur propriétaire peut fournir au membre courant un document v1 conforme
au schéma. Il est un read-model minimal, versionné, frais et filtré, jamais un
ledger PF ni une source concurrente de décision.

| Champ | Règle |
| --- | --- |
| `app_key` | App Faluss concrète, par exemple `hub` ou `me`. |
| `reward_key` | Clé stable détenue par le propriétaire, par exemple `hub.daily_accrual`. |
| `owner` | Moteur unique qui décide et, plus tard, exécute sa conséquence. |
| `status` | `claimable`, `claimed`, `ineligible`, `unavailable` ou `not_supported`. |
| `reward` | Objet seulement lorsqu'une annonce est légitime : montant PF entier positif, classe économique et libellé. |
| `period` | `daily`, fuseau constant `Europe/Paris`, date logique serveur. |
| `delegation` | `owner_claim`, `owner_navigation` ou `none`, jamais une mutation Portal. |
| `freshness` | Instant de production, durée maximale et décision après expiration. |
| `source` | Read-model minimal, moteur source identique au propriétaire et version de source. |
| `compatibility` | Version, compatibilité descendante, dépréciation et remplacement éventuel. |

Les statuts ont une sémantique fermée :

- `claimable` peut déléguer exclusivement au propriétaire par `owner_claim` ou
  `owner_navigation` ; il ne donne aucune capacité d'écriture au Hub/Portal ;
- `claimed` est déjà décidé pour la période, utilise `none` et ne peut pas
  porter une délégation de claim ;
- `ineligible`, `unavailable` et `not_supported` utilisent `none`, ne portent
  aucune mutation et ne produisent pas de faux bouton de récupération ;
- un document absent signifie **reward non déclaré**, jamais `0 PF`, « déjà
  récupéré », solde ou éligibilité inventée.

Le statut ne contient ni `faluss_id`, e-mail, session, solde PF total, détail
interne d'éligibilité, historique de reward, donnée Stripe, paiement, carte,
Customer, Checkout, Price, facture ou secret. Il est filtré pour le membre
courant, non public et ne devient pas un cache métier durable dans Hub/Portal.
Après expiration, le consommateur omet le document ou le redemande au
propriétaire selon `stale_behavior`; il ne présente jamais un statut expiré
comme actuel.

## Délégation et future sémantique Mes Apps

DR-01 ne modifie aucune card. Lors d'une future implémentation conforme :

1. la card entière reste le point d'accès à l'application ;
2. uniquement `claimable` peut transformer sa zone d'action droite en
   délégation Daily Reward ;
3. `hub.daily_accrual` délègue une action à Faluss Hub ;
4. `me.profile_daily_claim` délègue une navigation à la carte publiée ;
5. après succès réellement confirmé par le propriétaire, le futur client peut
   relire le read-model et rendre `claimed` sans rechargement ;
6. Hub/Portal ne simule jamais un succès, ne crédite jamais localement et ne
   maintient aucun cache métier durable.

Une app future ne peut proposer un Daily Reward qu'après son contrat spécialisé.
Son moteur choisit son `reward_key`, montant, classe PF autorisée, éligibilité,
état, source et idempotence. Hub ne calcule jamais un reward, ne choisit jamais
un montant, ne lit jamais une table dérivée et ne produit jamais une entrée PF.
Deux rewards de moteurs distincts restent deux décisions distinctes : Hub ne
les fusionne ni ne calcule un solde ou score commun.

## Coordination avec Points Faluss

DR-01 remplace le blocage générique PF-02B concernant les règles de `20 PF` /
`75 PF`. PF-02B est autorisé à implémenter **uniquement** les deux
règles ci-dessus, sous réserve de ses garanties transactionnelles et de sécurité
propres : période `Europe/Paris`, éligibilité serveur, clé d'idempotence,
absence de rattrapage et cumul maximal de `95 PF earned`.

Les futures entrées PF conservent les catégories réservées par PF-02A :

- Hub : `daily_accrual`, `earned`, crédit de `20 PF` ;
- Faluss Me : `profile_daily_claim`, `earned`, crédit de `75 PF`.

PF-02B reste bloqué sur le reste : adaptateurs et moteurs d'app runtime, UI,
app registry runtime, packs PF, paiements, Stripe, Fans et retrait créateur.
DR-01 ne modifie pas le schéma `faluss-pf-ledger-entry.schema.json`.

PF-02B a depuis livré le Core transactionnel isolé des deux écritures, dans le
sous-ledger privé Token Engine. Cette livraison ne change pas la délégation
DR-01 : aucun adaptateur Hub ou Faluss Me, aucune card Mes Apps et aucun claim
navigateur ne sont ajoutés. Une app ne pourra déclarer ou déléguer un statut
qu'après son adaptateur propriétaire, avec la preuve serveur correspondante.

## Exemples de statuts valides

Hub récupérable avec délégation de claim vers son propriétaire :

```json
{
  "app_key": "hub",
  "reward_key": "hub.daily_accrual",
  "owner": "faluss-hub",
  "status": "claimable",
  "reward": { "amount_pf": 20, "economic_class": "earned", "label": "Récupérer +20 PF" },
  "period": { "type": "daily", "timezone": "Europe/Paris", "logical_date": "2026-09-11" },
  "delegation": { "type": "owner_claim", "action_key": "claim-daily-reward", "target": null },
  "freshness": { "generated_at": "2026-09-11T10:00:00Z", "max_age_seconds": 60, "stale_behavior": "refresh_from_owner" },
  "source": { "type": "owner_daily_reward_read_model", "engine": "faluss-hub", "read_model": "hub-daily-reward", "source_version": "1.0.0" },
  "compatibility": { "minimum_consumer_version": "1.0.0", "backward_compatible_with": ["1.0.0"], "deprecated": false, "sunset_at": null, "replacement_reward_key": null }
}
```

Faluss Me récupérable avec navigation déléguée :

```json
{
  "app_key": "me",
  "reward_key": "me.profile_daily_claim",
  "owner": "faluss-me",
  "status": "claimable",
  "reward": { "amount_pf": 75, "economic_class": "earned", "label": "Récupérer +75 PF" },
  "period": { "type": "daily", "timezone": "Europe/Paris", "logical_date": "2026-09-11" },
  "delegation": { "type": "owner_navigation", "action_key": "open-published-card", "target": "faluss-me.published-card" },
  "freshness": { "generated_at": "2026-09-11T10:00:00Z", "max_age_seconds": 60, "stale_behavior": "refresh_from_owner" },
  "source": { "type": "owner_daily_reward_read_model", "engine": "faluss-me", "read_model": "me-daily-reward", "source_version": "1.0.0" },
  "compatibility": { "minimum_consumer_version": "1.0.0", "backward_compatible_with": ["1.0.0"], "deprecated": false, "sunset_at": null, "replacement_reward_key": null }
}
```

Un statut Hub `claimed` utilise `none` et peut conserver la description du
reward déjà annoncé. Un moteur futur peut fournir sa clé, propriétaire et source
propres après livraison de son contrat spécialisé, sans fusion avec Hub ou Me.

## Exemples invalides

- deux crédits Hub pour le même `faluss_id`, jour logique et politique ;
- un claim Faluss Me sans carte publiée ou handle public réservé ;
- une attribution Faluss Me directe par Portal ou card Apps ;
- un statut sans `owner`, `source`, `freshness` ou `compatibility` ;
- un statut `claimed` avec `owner_claim` ;
- un document public contenant un `faluss_id`, e-mail, solde PF ou paiement ;
- fusionner deux rewards de moteurs distincts en une décision ;
- dépendre d'un fuseau WordPress mutable ou d'une date navigateur ;
- rattraper un jour manqué ;
- créditer via JavaScript, URL, rechargement, page publique ou navigateur seul ;
- modifier le shell Portal ou les cards AP-01 pour mettre ce contrat en place ;
- créer une seconde écriture PF après retry ou double clic.

## Portée vérifiable de DR-01

DR-01 est limité au présent contrat, son schéma, son test, la mise à jour ciblée
du contrat/test PF et des documents d'architecture, modèle et roadmap. Il ne
touche aucun fichier sous `plugins/`, archive ZIP, table, migration, route,
endpoint, UI Portal, paiement, Stripe ou comportement WordPress.
