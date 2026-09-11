# MP-01A — Universal Profile Contract

## Statut et frontière

MP-01A définit le contrat de fédération du futur Master Profile. Il ne livre
aucun Master Profile visible et ne modifie aucun comportement WordPress. Le
Master Profile est une **projection fédérée** : il assemble des read-models
explicites fournis par leurs moteurs propriétaires, sans devenir une source
métier.

Le lot ne crée ni route, écran, widget, shortcode, API REST, interface
Elementor, table, migration, cron, donnée membre, plugin, archive ZIP ou version
installable. Il ne change aucune logique Identity, SSO, Link, Portal,
Subscriptions, Stripe, Token Engine, onboarding ou moteur dérivé.

Le schéma générique v1 est
[`contracts/master-profile-module.schema.json`](../contracts/master-profile-module.schema.json).
Il décrit un document échangé ; il ne prescrit aucun transport et n'autorise
donc implicitement aucune route.

## Principes normatifs

Les mots **DOIT**, **NE DOIT PAS** et **PEUT** sont normatifs.

1. Le seul identifiant commun est le `faluss_id`, UUID opaque et stable. Il
   sert à adresser le sujet entre serveurs, jamais à afficher une identité.
2. Chaque moteur reste l'unique source de ses données, décisions, métriques et
   règles d'accès.
3. Le Master Profile NE DOIT PAS lire directement une table, une option ou un
   stockage interne d'un moteur, ni persister, répliquer ou matérialiser son
   payload comme une nouvelle source.
4. Un moteur expose uniquement un read-model minimal, versionné, daté et déjà
   filtré pour les audiences qu'il autorise.
5. Le Master Profile filtre encore selon l'audience demandée, l'activation, la
   fraîcheur et le mode fantôme. Il ne peut jamais élargir l'autorisation du
   moteur.
6. Un module absent signifie **absence de donnée**. Il ne devient jamais `0`,
   `false`, « gratuit », « inactif », « aucun score » ou toute autre valeur
   inventée.
7. Le Master Profile affiche les modules séparément. Il ne calcule, additionne,
   normalise, classe, convertit ni fusionne leurs scores.

## Carte Membre universelle

La future Carte Membre publique est publique par défaut seulement lorsque les
trois préconditions canoniques sont simultanément prouvées par Identity :

- identité complète ;
- identité active ;
- handle public réservé.

Avant cette preuve complète, aucune carte publique routable ne doit être
présumée. La surface publique initiale est limitée à l'identité publique
autorisée, l'avatar autorisé, le nom affiché et `@handle`. L'e-mail,
l'abonnement, les identifiants techniques, la facturation et les droits restent
privés.
Une carte publique est accessible par lien et dans l'écosystème, mais le
référencement externe est désactivé par défaut. Son éventuelle indexation devra
faire l'objet d'un contrat ultérieur explicite ; MP-01A n'ajoute aucun sitemap,
annuaire, recherche ou directive de publication.

Le Master Profile universel ne remplace pas la carte Faluss.me. Cette dernière
reste une carte de liens facultative détenue par Faluss Identity et Faluss Link.

## Assemblage d'une projection

Pour une audience et un sujet donnés, un futur assembleur DOIT appliquer cet
ordre, sans raccourci :

1. établir côté serveur le `faluss_id` du sujet et l'audience effective ;
2. vérifier l'existence d'une Carte Membre routable lorsque la consultation est
   publique ;
3. appliquer `ghost_until` avant toute sélection de module public ;
4. demander au moteur propriétaire son read-model explicite ;
5. vérifier le schéma, la version, le propriétaire, le sujet, l'activation et
   l'audience autorisée ;
6. omettre un document absent, invalide, expiré ou non autorisé ;
7. rendre le payload sans le compléter depuis une autre source et sans
   réinterpréter une métrique.

Un échec est fermé. Une projection invalide ne peut pas être remplacée par une
ancienne copie persistée, un accès direct à la base du moteur ou une valeur de
secours supposée.

## Enveloppe de module v1

Chaque document de module conforme contient au minimum :

| Champ | Contrat |
| --- | --- |
| `namespace` | Namespace concret, stable, en minuscules et séparé par des points. Un wildcard tel que `date.*` désigne une famille documentaire, jamais une valeur de document. |
| `contract_version` | Version sémantique du contrat propre au module. |
| `owner` | Moteur propriétaire et autorité métier déclarée. Le Master Profile ne peut pas se déclarer propriétaire. |
| `subject_faluss_id` | UUID v4 opaque du sujet, réservé à la fédération serveur et interdit dans le payload rendu. |
| `activation` | État `enabled` ou `disabled`, daté et décidé par le propriétaire. Un module désactivé est vide. |
| `audiences` | Sous-ensemble non vide de `private`, `members`, `public`. Aucune quatrième audience n'existe. |
| `projection` | État `available` avec un payload minimal non vide, ou `empty` avec `{}`. |
| `empty_state` | Déclaration cohérente de l'état vide par le propriétaire. |
| `freshness` | Date de production, durée maximale et décision `omit` ou `refresh_from_owner` après expiration. |
| `source` | Read-model, moteur et version de source explicites ; le type est toujours `owner_read_model`. |
| `delegated_actions` | Liens profonds symboliques délégués au propriétaire ; aucune action métier n'est exécutée par le Master Profile. |
| `compatibility` | Version minimale consommateur, versions compatibles, dépréciation, remplacement et extinction éventuels. |

Le payload peut contenir des `fields` d'affichage et des `metrics` nommées. Il
reste soumis au contrat spécialisé du moteur. Toute clé de métrique DOIT porter
le namespace de son propriétaire. La liste `audiences` indique les audiences
maximales autorisées par le producteur ; le choix du membre peut seulement les
réduire.

### Activation, absence et état vide

Trois situations restent distinctes :

- **module absent** : aucun document n'est retourné ; le Master Profile omet le
  module et n'en déduit rien ;
- **module présent et vide** : le propriétaire retourne un état `empty`, un
  `payload = {}` et un motif borné ;
- **module désactivé** : le document est explicitement vide et aucune action
  profonde n'est proposée.

Les motifs v1 sont `not_enabled`, `no_data`, `not_authorized` et
`temporarily_unavailable`. Ils servent à la décision interne de rendu et ne
doivent pas révéler publiquement une donnée privée.

### Fraîcheur et source

`generated_at` décrit l'instant de génération UTC et `max_age_seconds` la durée
maximale d'utilisation. Après cette durée, le consommateur omet le module ou le
redemande au propriétaire selon `stale_behavior`. Il ne rend jamais une
projection expirée comme actuelle.

`source.engine` DOIT être identique à `owner.engine`. `source.read_model` et
`source.source_version` identifient le contrat effectivement lu. Le Master
Profile ne conserve pas de copie métier et ne peut pas contourner le read-model
en lisant une table dérivée.

## Visibilité

Les seules audiences sont :

- `private` : le sujet lui-même, après authentification et contrôle serveur ;
- `members` : un membre Faluss authentifié, dans les limites définies par le
  module ;
- `public` : consultation sans session, uniquement pour les champs explicitement
  publics.

La visibilité est choisie module par module. La valeur par défaut s'applique
tant que le membre ne l'a pas réduite ou modifiée dans les limites permises par
le moteur. Aucun réglage d'un module ne vaut consentement pour un autre.

Une projection publique NE DOIT contenir ni abonnement, e-mail, paiement,
`faluss_id`, identifiant WordPress ou fournisseur, référence Customer,
Checkout, Price, facture, droit, donnée de carte bancaire, payload Stripe ou
secret. Le `subject_faluss_id` de l'enveloppe est supprimé avant tout rendu,
HTML, URL, journal client ou cache public.

## Mode fantôme

`ghost_until` est un instant UTC facultatif, appartenant à la politique de
visibilité du sujet et non à un moteur métier. Il n'est pas dupliqué dans les
modules.

Lorsque `ghost_until` est strictement postérieur à l'instant de consultation :

- la Carte Membre et toutes les projections d'audience `public` sont masquées ;
- cette décision est prioritaire sur chaque visibilité publique individuelle ;
- les réglages individuels sont conservés sans mutation ;
- aucune donnée source n'est supprimée, modifiée ou désactivée ;
- les projections `private` et `members` conservent leurs propres contrôles et
  ne deviennent jamais publiques par compensation.

À l'expiration, les visibilités précédentes reprennent effet sans réécriture des
données source. Une consultation publique pendant le mode fantôme doit produire
le même résultat extérieur indiscernable qu'une carte non routable. Elle ne
retourne ni marqueur `ghost`, ni date, durée, cause ou indice permettant de
déduire l'existence du mode ou du sujet.

## Namespaces, scores et progression

Le premier segment d'un namespace délimite une autorité métrique. Les familles
suivantes sont réservées :

- `progression.*` : progression globale, exclusivement émise par le moteur
  Progression ;
- `date.*` : métriques propres à Date ;
- `fans.*` : capacité et métriques propres à Fans ;
- `hof.*` : métriques propres au Hall of Fame.

Un moteur dérivé NE DOIT jamais émettre ou écrire une métrique
`progression.*`. Un éventuel apport d'un événement `date.*`, `fans.*` ou
`hof.*` à la progression globale devra passer par un pont de politique futur,
explicite, versionné et détenu par le moteur Progression. Le pont consommera un
événement propriétaire ; il ne donnera pas au dérivé un droit d'écriture dans
la progression.

Le Master Profile ne fusionne jamais `progression.score`, `date.score`,
`fans.score` et `hof.score`, même si leurs valeurs ou libellés se ressemblent.
Il ne construit aucun « score total » et ne déduit aucun rang entre modules.

## Modules de référence non implémentés

`date.*` réserve une famille. Chaque document Date réel utilisera un namespace
concret, par exemple `date.summary`, et son propre contrat versionné.

| Module ou famille | Propriétaire de référence | Visibilité par défaut / audiences maximales | Caractère | Projection formellement interdite |
| --- | --- | --- | --- | --- |
| `identity.core` | Faluss Identity | `public` après identité complète, active et handle réservé / `private`, `members`, `public` | public conditionnel | e-mail, `faluss_id` rendu, `wp_user_id`, secret, session, abonnement, facturation, droit ou donnée métier |
| `apps.registry` | Faluss Apps Registry | `members` / `private`, `members` | membre | possession supposée, URL non déclarée, activité privée, droit ou donnée d'un autre moteur |
| `subscriptions.private` | Faluss Subscriptions | `private` / `private` | strictement privé | toute projection publique ; e-mail, paiement, carte, référence Stripe/Customer/Checkout/Price, payload fournisseur ou secret |
| `pf.summary` | Token Engine, projection officielle Faluss PF | `private` / `private` | strictement privé | ledger, transaction, règle, solde ALB converti, paiement, droit ou écriture économique |
| `progression.global` | Faluss Progression | `private` / `private`, `members`, `public` | public seulement sur choix | métrique `date.*`, `fans.*` ou `hof.*`, détail d'événement dérivé, calcul effectué par le Master Profile |
| `cosmetics.equipped` | Faluss Cosmetics | `private` / `private`, `members`, `public` | public seulement sur choix | inventaire complet, prix, achat, droit, moyen de paiement ou cosmétique non équipé |
| `date.*` | moteur Faluss Date propriétaire du sous-module | `private` / sous-ensemble déclaré de `private`, `members`, `public` | privé par défaut | préférences, conversations, rencontres, notes, signalements, score détaillé, existence ou cause du mode fantôme, métrique `progression.*` |
| `fans.creator` | Faluss Fans | `private` / `private`, `members`, `public` | public seulement sur activation et choix | rôle global définitif, paiement, bouton de don, revenus, soutien privé, identité de contributeur, score HOF ou métrique `progression.*` |
| `hof.score` | Faluss Hall of Fame | `private` / `private`, `members`, `public` | public seulement sur choix | paiement, historique de soutien, score Fans/Date, métrique `progression.*` ou score global recomposé |

La qualité de créateur est une capacité `fans.creator` activable et réversible,
jamais un rôle global. Une future action de soutien ne pourra être projetée que
si un module Fans public l'autorise explicitement. MP-01A ne crée ni profil
créateur, bouton de don, paiement, ni score HOF.

## Actions profondes

Une action profonde est une délégation de navigation vers une cible symbolique
du moteur propriétaire. Elle porte un `action_key`, le même `owner` que le
module et une délégation `owner_deep_link`. Le moteur résout ensuite la cible et
refait tous ses contrôles d'accès.

Le Master Profile NE DOIT PAS porter une mutation métier, un formulaire de
paiement, une attribution de droit, une écriture de score, un changement
d'abonnement ou une création de profil. Il ne transforme pas une action
profonde en autorisation.

## Versioning, compatibilité et dépréciation

Chaque module suit une version sémantique `MAJEUR.MINEUR.CORRECTIF` :

- un correctif clarifie ou corrige sans changer la forme ni la sémantique ;
- une version mineure ajoute seulement des champs optionnels ou des capacités
  que les consommateurs peuvent ignorer ;
- une version majeure couvre toute suppression, renommage, nouvelle exigence
  ou modification de sémantique.

Un consommateur ne comprenant pas la version majeure omet le module. Il ne
devine jamais une correspondance. Une restriction de confidentialité peut
toujours fermer immédiatement une projection.

Une dépréciation conserve le namespace et le contrat annoncé jusqu'à
`sunset_at`. Elle déclare facultativement `replacement_namespace`; le
remplacement ne réutilise pas silencieusement l'ancien sens. Pendant la période
de compatibilité, le propriétaire peut servir deux versions, chacune validée
indépendamment. Le Master Profile ne convertit pas lui-même les payloads.

## Exemples valides

Projection publique minimale d'identité, une fois les préconditions prouvées :

```json
{
  "namespace": "identity.core",
  "contract_version": "1.0.0",
  "owner": { "engine": "faluss-identity", "authority": "public-identity" },
  "subject_faluss_id": "11111111-1111-4111-8111-111111111111",
  "activation": { "state": "enabled", "changed_at": "2026-09-11T10:00:00Z" },
  "audiences": ["private", "members", "public"],
  "projection": {
    "status": "available",
    "payload": {
      "fields": {
        "display_name": "Alice",
        "handle": "@alice",
        "avatar_url": "https://www.faluss.me/media/alice.jpg"
      }
    }
  },
  "empty_state": { "declared": false, "reason": null },
  "freshness": {
    "generated_at": "2026-09-11T10:00:00Z",
    "max_age_seconds": 60,
    "stale_behavior": "refresh_from_owner"
  },
  "source": {
    "type": "owner_read_model",
    "engine": "faluss-identity",
    "read_model": "public-identity",
    "source_version": "1.0.0"
  },
  "delegated_actions": [],
  "compatibility": {
    "minimum_consumer_version": "1.0.0",
    "backward_compatible_with": ["1.0.0"],
    "deprecated": false,
    "sunset_at": null,
    "replacement_namespace": null
  }
}
```

État vide explicite d'un module Date activé mais sans donnée publiable :

```json
{
  "namespace": "date.summary",
  "contract_version": "1.0.0",
  "owner": { "engine": "faluss-date", "authority": "date-summary" },
  "subject_faluss_id": "11111111-1111-4111-8111-111111111111",
  "activation": { "state": "enabled", "changed_at": "2026-09-11T10:00:00Z" },
  "audiences": ["private"],
  "projection": { "status": "empty", "payload": {} },
  "empty_state": { "declared": true, "reason": "no_data" },
  "freshness": {
    "generated_at": "2026-09-11T10:00:00Z",
    "max_age_seconds": 30,
    "stale_behavior": "omit"
  },
  "source": {
    "type": "owner_read_model",
    "engine": "faluss-date",
    "read_model": "date-summary",
    "source_version": "1.0.0"
  },
  "delegated_actions": [],
  "compatibility": {
    "minimum_consumer_version": "1.0.0",
    "backward_compatible_with": ["1.0.0"],
    "deprecated": false,
    "sunset_at": null,
    "replacement_namespace": null
  }
}
```

## Exemples invalides

- `{"namespace":"date.*"}` : une famille wildcard n'est pas un namespace de
  document concret.
- `{"namespace":"date.summary","metrics":{"progression.score":12}}` :
  Date tente d'écrire dans la progression globale.
- `{"namespace":"subscriptions.private","audiences":["public"]}` : un
  abonnement strictement privé devient public.
- `{"namespace":"fans.creator","payload":{"hof.score":90}}` : Fans tente
  de porter la métrique d'un autre moteur.
- `{"namespace":"identity.core","payload":{"email":"a@example.test"}}` :
  l'e-mail n'est jamais une projection publique.
- remplacer un module `hof.score` absent par `0` : la valeur est inventée.
- retourner `ghost_until`, `ghost_reason` ou un statut spécifique à une
  consultation publique : l'existence du mode fantôme devient déductible.
- lire `faluss_subscriptions`, un ledger, une table Date ou une option d'un
  plugin depuis le Master Profile : la projection devient une source
  concurrente et contourne le propriétaire.

## Portée vérifiable de MP-01A

Les seuls artefacts du lot sont le présent contrat, son schéma JSON, son test
statique et les trois mises à jour documentaires d'architecture, modèle de
données et roadmap. Aucun fichier sous `plugins/`, aucune migration, aucune
archive et aucun manifeste de version n'appartient à MP-01A.
