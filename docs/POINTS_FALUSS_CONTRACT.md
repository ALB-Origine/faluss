# PF-02A — Contrat du moteur Points Faluss et des droits économiques

## Statut et frontière

PF-02A définit exclusivement le contrat d'architecture du futur moteur officiel
**Points Faluss (PF)**. Il ne crée aucun solde réel, table, migration, route,
endpoint, cron, interface, widget, shortcode, plugin, ZIP, donnée membre ou
comportement WordPress.

Il ne modifie ni Faluss Portal, ni le Master Profile, ni Faluss Identity,
Faluss Link, Subscriptions, Stripe, Fans, Hall of Fame, Token Engine existant ou
une donnée membre. Le moteur réel est réservé à PF-02B et devra être livré dans
un lot séparé après validation de ce contrat.

Le schéma machine-readable v1 est
[`contracts/faluss-pf-ledger-entry.schema.json`](../contracts/faluss-pf-ledger-entry.schema.json).
Il décrit une future écriture de ledger ; il ne définit aucun transport et
n'autorise donc aucune route implicite.

## Décision fondamentale

Le membre voit un seul univers : les **Points Faluss**. Le moteur conserve,
pour chaque unité, son origine et ses droits économiques. Cette distinction peut
être invisible dans la marque, mais ne doit jamais être perdue dans le ledger,
une décision de dépense ou une compensation.

| Classe interne | Origine autorisée | Utilisations autorisées |
| --- | --- | --- |
| `earned` | progression, récupérations et récompenses | cosmétiques et éléments strictement Faluss |
| `funded` | PF acquis contre un paiement confirmé | cosmétiques Faluss et futurs soutiens créateur monétisables |
| `promotional` | bonus de pack, campagnes ou geste commercial | mêmes limites que `earned` |

Les classes constituent un ensemble fermé. Une unité `earned` ou
`promotional` ne devient jamais `funded`, ni directement ni par une chaîne de
compensations. Le solde est dérivé du ledger officiel par classe ; aucune valeur
éditable, agrégat indépendant ou copie client n'est une source de vérité.

Les PF ne sont pas transférables entre membres, remboursables en euros ou
convertibles en cryptoactif. Ils ne sont jamais un moyen de retrait pour un
créateur. Un futur revenu créateur est un montant euro distinct, issu d'un
événement de soutien monétisable confirmé et détenu par Fans/Marketplace.

Un PF gagné, promotionnel ou acheté ne donne jamais de score Hall of Fame par
lui-même. Hall of Fame recevra seulement, dans un lot ultérieur, une valeur euro
officielle de soutien monétisable confirmée par Fans/Marketplace. Aucun solde
ALB, ledger ALB, libellé ALB ou historique Alternative LAB ne peut être lu,
converti, renommé, masqué ou présenté comme PF.

## Ledger PF futur

Le ledger PF sera append-only et immuable. Une ligne historique n'est jamais
modifiée ni supprimée. Toute correction future est une écriture séparée de sens
`compensation`, reliée à l'écriture compensée.

Chaque entrée v1 comporte les éléments suivants :

| Champ | Règle |
| --- | --- |
| `entry_id` | UUID opaque unique de l'écriture. |
| `subject_faluss_id` | UUID opaque du sujet, serveur uniquement et jamais rendu publiquement. |
| `amount_pf` | Entier PF strictement positif ; le sens porte la direction. |
| `direction` | `credit`, `debit` ou `compensation`. |
| `economic_class` | `earned`, `funded` ou `promotional`, immuable pour l'unité concernée. |
| `category` / `category_version` | Catégorie réservée, concrète et versionnée. |
| `source_owner` / `source_event_reference` | Moteur propriétaire et référence opaque, non devinable, de l'événement source. |
| `idempotency_key` | Clé métier unique : une conséquence économique ne peut être créée deux fois. |
| `policy_version` | Version de la politique de décision appliquée. |
| `occurred_at` | Instant UTC de l'événement. |
| `compensates_entry_id` | Référence obligatoire pour une compensation et absente pour toute autre direction. |
| `administrative_reason` | Motif privé obligatoire pour une future correction manuelle. |
| `metadata` | Métadonnées privées minimales ; jamais e-mail, carte bancaire, payload Stripe, secret ou identifiant technique rendu publiquement. |

Le schéma ne remplace pas les contrôles transactionnels de PF-02B. Celui-ci
devra vérifier atomiquement l'unicité d'idempotence, la disponibilité de la
classe débitée, l'immuabilité, la référence compensée et les invariants de
solde. Une entrée absente ne devient jamais un solde ni un droit supposé.

### Catégories réservées

| Catégorie | Direction et classe réservées | Frontière |
| --- | --- | --- |
| `profile_daily_claim` | crédit `earned` | récupération PF réservée, non implémentée |
| `daily_accrual` | crédit `earned` | gain récurrent réservé, non implémenté |
| `pf_pack_purchase` | crédit `funded` | après preuve de paiement confirmée, sans intégration Stripe PF-02A |
| `pf_pack_bonus` | crédit `promotional` | bonus commercial distinct du montant financé |
| `fans_support` | débit `funded` | consommation future de soutien monétisable, sans revenu ni payout PF |
| `cosmetic_redemption` | débit des trois classes | dépense cosmétique sans revenu, retrait, HOF, progression ni conversion euro |
| `manual_adjustment` | sens explicite, motif administratif obligatoire | correction future séparée, jamais édition de solde |
| `reversal` | `compensation`, même classe que l'entrée compensée | correction append-only liée à l'original |

`profile_daily_claim`, `daily_accrual`, `pf_pack_purchase`, `pf_pack_bonus`,
`fans_support`, `cosmetic_redemption`, `manual_adjustment` et `reversal` sont
seulement réservées dans PF-02A. Elles ne déclenchent aucune écriture dans ce
lot.

### Idempotence, append-only et compensation

Une même `idempotency_key` ne produit jamais deux écritures économiques. Un
retry, une double soumission ou une concurrence doivent retrouver le résultat
déjà commis. PF-02B devra appliquer cette unicité dans le même périmètre
transactionnel que le ledger.

Une compensation porte la classe de son original : elle ne peut pas être
employée pour reclassifier `earned` ou `promotional` en `funded`. Une correction
administrative ne modifie pas une ligne existante ; elle produit une nouvelle
ligne avec un motif privé obligatoire.

Un remboursement ou litige futur exige une compensation explicite. S'il reste
insuffisamment de `funded` parce que les unités ont déjà été dépensées, PF-02B
doit définir et appliquer une politique atomique explicite avant toute écriture
(par exemple dette, refus ou état de résolution). Il ne peut jamais ignorer ce
cas silencieusement.

## PF gagnés et inspection ALB ciblée

PF-02A réserve deux crédits de classe `earned` :

- `75 PF` sous la source `profile_daily_claim`, pour la récupération quotidienne
  associée au handle Faluss.me ;
- `20 PF` sous la source `daily_accrual`, pour le gain quotidien récurrent.

Ces montants et sources sont des décisions PF réservées, pas une activation.
Une page publique, un rechargement, JavaScript, Elementor, un appel navigateur
ou un paramètre d'URL ne peut jamais, seul, créer une entrée PF.

### Faits ALB observés

L'inspection en lecture seule de la référence ALB SP-05A/SP-05B
`codex/silent-sp05b-economic-rewards` à `07af886` établit seulement les faits
suivants :

- le seul producteur documenté est une réclamation explicite et authentifiée du
  Wallet par `POST /wallet/me/daily-claim` ; le membre courant et un nonce REST
  sont requis ;
- le jour est calculé dans le fuseau WordPress et une clé
  `seasonal_daily_{wp_user_id}_{Ymd}` limite le crédit à un membre et une date ;
- les jours manqués ne sont pas rattrapés ; aucun cron ni polling n'est requis ;
- l'implémentation observée crédite une séquence ALB hebdomadaire
  `72/71/72/71/72/71/71`, sous conditions de configuration, de saison et de
  flags ; elle n'implémente ni `75`, ni `20`, ni handle Faluss.me.

`capture_frontend_activity()` existe dans le code observé mais aucun appelant
enregistré n'a été trouvé dans ce périmètre ; la documentation SP-05A décrit la
CTA Wallet comme seul producteur. Cette observation ne peut pas être étendue à
un futur moteur PF.

### Règles quotidiennes décidées par DR-01

L'inspection ALB ne prouve pas les montants ou critères PF, mais la décision
produit est désormais explicitement portée par
[`DAILY_REWARDS_CONTRACT.md`](DAILY_REWARDS_CONTRACT.md). DR-01 remplace le
blocage générique de PF-02B concernant les deux sources :

- `hub.daily_accrual` est un crédit `earned` de `20 PF`, pour une identité
  Faluss active, une fois par `faluss_id` et jour `Europe/Paris`, par action
  explicite déléguée à Faluss Hub ;
- `me.profile_daily_claim` est un crédit `earned` de `75 PF`, pour une identité
  active dont la carte Faluss.me est effectivement publiée avec handle public
  réservé, une fois par `faluss_id` et jour `Europe/Paris`, uniquement depuis
  `faluss.me/@handle` ;
- les deux crédits sont cumulables le même jour, pour un maximum de `95 PF
  earned`, sans rattrapage ; leur idempotence est définie par moteur,
  `reward_key`, `faluss_id`, date logique et version de politique.

PF-02B est autorisé à implémenter exclusivement ces règles quotidiennes après
ses propres garanties transactionnelles. Il reste bloqué sur le reste du ledger,
son stockage, les migrations, moteurs runtime, UI, registry Apps, packs,
paiements, Stripe, Fans et retrait créateur. La décision DR-01 ne convertit pas
ALB et ne modifie pas le schéma de ledger PF : elle spécifie seulement les deux
catégories déjà réservées.

## PF-02B — Core réel isolé

PF-02B implémente ce sous-ledger dans Token Engine `0.4.0`, sous la table dédiée
`token_engine_pf_ledger`. Il ne modifie pas `token_engine_ledger`, qui conserve
le ledger ALB existant, ni aucune configuration générique, donnée historique ou
source de vérité ALB. La migration additive crée uniquement la table PF et ses
index après vérification fail-closed du schéma existant.

Le Core écrit seulement par `Token_Engine_Points_Service`, façade PHP interne
qui n'accepte que le `faluss_id` UUID v4. Ses balances sont dérivées par classe,
ses entrées sont append-only et idempotentes, et une compensation est liée à
l'UUID d'origine, de même classe, sans solde négatif. Aucune ligne PF n'est
créée à l'installation.

Les crédits `daily_accrual` (`20 PF earned`) et `profile_daily_claim` (`75 PF
earned`) sont maintenant implémentés dans le Core, avec période fixe
`Europe/Paris` calculée par `DateTimeImmutable`, sans rattrapage et avec clés
d'idempotence dérivées côté serveur. Aucun adaptateur Hub ou Faluss Me n'est
livré : aucune card, route publique, connexion, cron ou page ne peut encore les
déclencher. Packs, Fans, cosmétiques, ajustements, paiements, Stripe, retrait,
transfert, Hall of Fame, progression et projection `pf.summary` restent hors
PF-02B.

## PF acquis et packs

L'achat de PF est une capacité future désactivée. Un pack pourra un jour
assembler une entrée `funded`, adossée au montant effectivement encaissé, et une
entrée `promotional` pour le bonus commercial. Par exemple, `6 000 PF` peuvent
être présentés comme `5 000 funded` et `1 000 promotional`; le moteur conserve
les deux écritures et leurs droits distincts.

PF-02A ne crée aucun prix, pack, checkout, Price Stripe, remboursement, écran,
vente ou appel à Stripe. PF ne lit jamais un panier, une commande, Stripe, un
payout ou une table Fans. Une future intégration transmettra seulement une
preuve serveur minimale, versionnée et déjà confirmée par son moteur
propriétaire.

La formule premium future est **Faluss Plus**, `3,99 € / mois`, sans engagement
et sans gain direct de PF, quelle que soit la classe. Faluss Plus n'est jamais
une source PF.

## Cosmétiques et ordre de dépense

Les futurs cosmétiques Faluss peuvent consommer les trois classes avec l'ordre
réservé suivant :

1. `promotional` ;
2. `earned` ;
3. `funded`.

Une dérogation future doit être explicitement décidée, affichée au membre et
versionnée dans la politique. Un débit `cosmetic_redemption` ne produit jamais
un revenu créateur, droit de retrait, score Hall of Fame, progression globale ou
conversion en euro.

## Soutien de créateur Faluss Fans

Un futur soutien créateur monétisable peut seulement débiter la classe
`funded`, sous la catégorie `fans_support`. `earned` et `promotional` ne
financent jamais un revenu créateur, retrait ou score Hall of Fame. Une future
interaction sociale non monétisable devra être distinguée et n'est pas définie
par PF-02A.

Après une consommation `funded` autorisée, un futur événement minimal et
idempotent peut porter une valeur euro de référence distincte du nombre PF
affiché. Fans/Marketplace reste seul propriétaire de la commission, du revenu,
du versement et du score Hall of Fame. PF ne calcule ni n'écrit aucun de ces
résultats.

Fans/Marketplace doit rejeter une auto-transaction lorsque le Faluss ID du
soutien est celui du créateur. Une telle tentative ne produit ni revenu,
rémunération PF, score Hall of Fame ni événement monétaire consommable. Les gros
packs et bonus PF ne peuvent jamais gonfler artificiellement ces valeurs.

## Frontières de moteurs et projections

- PF n'est ni `progression.*`, ni `fans.*`, ni `hof.*` ; il ne produit pas de
  score global ou dérivé.
- Le Hall of Fame ne lit jamais le ledger PF et ne calcule pas depuis un solde.
- Le Master Profile ne lit ni ledger PF, ni historique, ni événement de soutien.
- Le futur module `pf.summary` reste privé par défaut et ne peut être branché
  qu'après PF-02B, via un read-model officiel séparé.
- Aucun montant PF n'est affiché dans Faluss Portal ou Master Profile par
  PF-02A.
- Une écriture ne peut pas être émise par Master Profile, navigateur,
  Elementor, JavaScript, URL, page publique ou client non autorisé.

## Exemples valides réservés

Les documents suivants sont valides pour le schéma ; ils illustrent un futur
ledger mais ne déclenchent rien dans PF-02A.

```json
{
  "entry_id": "11111111-1111-4111-8111-111111111111",
  "subject_faluss_id": "22222222-2222-4222-8222-222222222222",
  "amount_pf": 75,
  "direction": "credit",
  "economic_class": "earned",
  "category": "profile_daily_claim",
  "category_version": "1.0.0",
  "source_owner": "faluss-identity",
  "source_event_reference": "profile-claim-4d9e2ee6e8c34e1b",
  "idempotency_key": "pf-profile-daily-20260911-4d9e2ee6e8c34e1b",
  "policy_version": "1.0.0",
  "occurred_at": "2026-09-11T10:00:00Z",
  "compensates_entry_id": null,
  "administrative_reason": null,
  "write_origin": "server",
  "metadata": {}
}
```

Un gain récurrent réservé de `20 PF earned` utilise `daily_accrual` et sa propre
clé d'idempotence. Un pack conceptuel de `6 000 PF` crée deux crédits :
`5 000 funded` sous `pf_pack_purchase` et `1 000 promotional` sous
`pf_pack_bonus`. Une compensation de remboursement est une entrée
`reversal` de direction `compensation`, de même classe `funded`, liée par
`compensates_entry_id` à l'entrée d'origine.

## Exemples invalides

- convertir une référence ou un solde `ALB` en `pf_pack_purchase` ;
- reclasser une entrée `earned` ou `promotional` en `funded` ;
- débiter `earned` ou `promotional` sous `fans_support` ;
- écrire deux fois la même `idempotency_key` ;
- éditer ou supprimer une ligne au lieu de créer `reversal` ;
- calculer Hall of Fame, progression ou revenu créateur depuis le solde PF ;
- produire un PF depuis Faluss Plus ;
- créditer depuis Master Profile, Elementor, JavaScript, navigateur, URL ou
  page publique ;
- accepter un soutien dont soutien et créateur portent le même Faluss ID ;
- utiliser une compensation financée déjà dépensée sans politique explicite de
  remboursement/litige.

## Portée vérifiable de PF-02A

PF-02A est limité exactement à ce contrat, son schéma de ledger, son test
statique et les mises à jour d'architecture, modèle de données et roadmap. Il
ne touche aucun fichier sous `plugins/`, aucune archive ZIP, table, migration,
route, interface, paiement ou comportement WordPress.
