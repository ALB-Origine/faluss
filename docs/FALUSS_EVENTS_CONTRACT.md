# EVT-01A — Contrat commun des événements Faluss

## Statut et frontière

EVT-01A définit uniquement deux formats documentaires, versionnés et
machine-readable : l'enveloppe `faluss.event` `1.0.0` et le catalogue
`faluss.event-source-catalog` `1.0.0`. Les mots **DOIT**, **NE DOIT PAS** et
**PEUT** sont normatifs.

Ce lot ne produit, ne transmet, ne reçoit, ne stocke et ne consomme aucun
événement réel. Il ne livre ni plugin, ZIP, moteur Analytics, bus, outbox,
inbox, queue, worker, table, migration, route, appel réseau, cookie,
fingerprint, tracking, UI ou comportement WordPress. Il réserve les règles
nécessaires à EVT-01B et AN-01 sans activer les six types décrits plus bas et
sans modifier les manifestes CAP Hub ou Me.

Aucun événement réel n'est créé par ce contrat documentaire.

Les schémas autonomes Draft 2020-12 sont :

- [`faluss-event-envelope.schema.json`](../contracts/faluss-event-envelope.schema.json) ;
- [`faluss-event-source-catalog.schema.json`](../contracts/faluss-event-source-catalog.schema.json).

## Un fait, jamais une commande

Un événement est un fait métier déjà survenu, validé et commis par le moteur
qui le possède. Il n'est jamais une commande, une autorisation, une demande de
mutation, une preuve suffisante de paiement, une source de solde PF, un
entitlement, une attribution de quête ou de progression, ni une instruction du
navigateur.

Ainsi, `faluss-hub.daily-reward.claimed` ne peut être construit qu'après le
commit PF réussi dont Token Engine reste propriétaire. Le consommateur ne doit
jamais recréditer des PF à partir de cet événement. De même,
`faluss-me.link.clicked` atteste seulement une interaction reconnue par Faluss
Me : il ne transporte ni URL, ni libellé, ni contenu du lien.

Chaque moteur métier conserve ses données, ses décisions et ses écritures. Le
futur Faluss Events sera seulement un journal append-only de faits normalisés
et un routeur vers des consommateurs autorisés. Il ne deviendra pas une source
de vérité économique, identitaire ou d'entitlement.

## Topologie future séparée

La chaîne future comporte quatre responsabilités qui ne peuvent pas être
fusionnées :

1. le propriétaire métier valide et commet l'action originale ;
2. un adaptateur serveur appartenant au propriétaire construit ensuite
   l'enveloppe ;
3. Faluss Events accepte cette enveloppe de manière idempotente ;
4. chaque consommateur autorisé traite sa livraison avec sa propre idempotence.

Une source distante utilisera plus tard un outbox propriétaire jusqu'à sa
livraison signée. Une source locale sur `faluss.com` utilisera exactement la
même enveloppe, sans second format. Le stockage futur distinguera l'événement
immuable, sa réception idempotente, chaque livraison Analytics/Quêtes/
Progression et l'état propre de chaque consommateur. Une nouvelle livraison ne
recrée jamais l'événement métier original.

## Enveloppe `faluss.event` 1.0.0

La racine est fermée et contient exactement, dans son contrat logique, les
quatorze champs suivants :

| Champ | Règle |
| --- | --- |
| `contract_version` | exactement `1.0.0` |
| `event_id` | UUID v4 minuscule généré côté serveur |
| `event_type` | clé namespacée par l'`app_key` propriétaire |
| `event_version` | version sémantique stricte |
| `source` | identité propriétaire et catalogue exacts |
| `source_event_reference` | référence opaque, stable, propriétaire et bornée |
| `occurred_at` | instant UTC strict du fait métier |
| `produced_at` | instant UTC strict de construction de l'enveloppe |
| `subject_context` | sujet Faluss réservé ou `null` |
| `actor_context` | acteur membre, système ou anonyme selon un état fermé |
| `object_context` | objet propriétaire opaque ou `null` |
| `destinations` | liste non vide parmi les trois consommateurs fermés |
| `payload_contract` | type et version exacts du payload spécialisé |
| `payload` | objet borné soumis au validateur spécialisé exact |

Aucun navigateur ne choisit `event_id`, `event_type`, `event_version`,
`source_event_reference`, les dates, la source ou les destinations. Tous sont
construits depuis l'état serveur déjà autorisé du propriétaire.

### Identité et namespace

`event_id` est globalement unique. `event_type` commence obligatoirement par
`source.app_key + "."`. `source_event_reference` identifie de manière stable
l'occurrence métier chez le propriétaire et ne contient ni URL ni donnée
humaine. Un retry strictement identique conserve le même `event_id`, la même
référence, la même version et le même contenu.

L'identité métier idempotente future est la combinaison exacte :

```text
source.node_id
source.app_key
event_type
event_version
source_event_reference
```

La même identité avec le même hash canonique retrouve l'événement existant. La
même identité avec un contenu, un `event_id` ou une version différents est un
conflit fermé. Une référence déjà liée à un événement ne peut recevoir un
nouvel `event_id`. Il n'existe ni premier ni dernier écrit gagnant.

### Source et autorité CAP

`source` contient exactement :

- `node_id` ;
- `app_key` ;
- `owner` ;
- `capability_key` ;
- `catalog_version`.

En v1, `owner` est exactement égal à `app_key`. `event_type` et
`capability_key` appartiennent au namespace de cette application. La capacité
doit déclarer l'interface CAP `event_source`.

Un catalogue accepté ne constitue pas une autorisation. Au runtime futur, le
manifeste accepté, la capacité active, la compatibilité, la fraîcheur, le
binding CAP actif vers chaque destination et la politique locale du
consommateur restent tous obligatoires. L'échec d'un contrôle refuse seulement
la destination concernée et ne l'active jamais par défaut.

### Temps serveur

`occurred_at` et `produced_at` utilisent exclusivement
`Y-m-d\TH:i:s\Z` : UTC, sans offset, fraction, valeur normalisable ou date
impossible. `produced_at` ne précède jamais `occurred_at`. La date du
navigateur, un paramètre HTTP et le fuseau WordPress ne les influencent jamais.

Le catalogue fixe `max_delivery_delay_seconds`. Une livraison différée encore
dans cette limite conserve l'`occurred_at` original. Une livraison hors délai
est refusée ; le producteur ne réécrit ni la date ni l'événement.

### Sujet Faluss réservé

`subject_context` vaut `null` ou contient exactement :

```json
{
  "subject_type": "faluss_member",
  "subject_faluss_id": "11111111-1111-4111-8111-111111111111"
}
```

Le `faluss_id` est un UUID v4 résolu côté serveur. Il peut apparaître seulement
dans `subject_context.subject_faluss_id` et, pour un acteur membre distinct ou
identique, dans `actor_context.actor_faluss_id`. Il n'apparaît jamais dans le
payload, une référence, une URL, du HTML, du JavaScript, une réponse publique,
un cache public ou un journal technique. Il ne devient jamais un `wp_user_id`,
un e-mail ou une clé de rapprochement approximative.

### Acteur membre, système ou anonyme

`actor_context` contient toujours exactement `actor_type`,
`actor_faluss_id`, `anonymous_reference` et `anonymous_scope` :

- `member` exige un `actor_faluss_id` UUID v4 et les deux valeurs anonymes à
  `null` ;
- `system` exige les trois références à `null` ;
- `anonymous` exige `actor_faluss_id: null`. Sans comptage unique légal ou
  techniquement disponible, référence et scope valent tous deux `null`. Si une
  référence existe, elle est opaque, bornée, produite côté serveur et son scope
  vaut exactement `request` ou `daily`.

Une référence anonyme ne contient jamais d'IP, User-Agent, cookie, session ou
empreinte brute. Elle ne permet aucun rapprochement inter-applications, ne peut
pas être transformée en identité Faluss et n'est jamais exposée au propriétaire
de la carte. Son seul usage possible est un traitement agrégé explicitement
autorisé.

Le futur moteur Analytics devra imposer consentement, base légale, finalité,
rétention et suppression ou anonymisation réglementaire. EVT-01A ne crée aucun
cookie, fingerprint ni mécanisme de consentement.

### Objet propriétaire

`object_context` vaut `null` ou contient exactement `object_type` et
`object_reference`. La référence est opaque, propriétaire et bornée. Elle ne
contient jamais URL, slug public, titre, libellé, contenu, chemin média,
identifiant Stripe, secret ou donnée d'authentification.

### Destinations fermées

`destinations` est non vide, unique et limitée à :

- `analytics.events` ;
- `quests.events` ;
- `progression.events`.

Une même enveloppe peut viser plusieurs destinations ; elle reste un seul
événement. Le futur runtime vérifiera séparément, pour chacune, le manifeste,
le catalogue, la capacité `event_source`, le binding, la compatibilité et la
politique du consommateur. Aucun wildcard ou nom libre n'est admis.

### Payload spécialisé et borné

`payload_contract` contient exactement `document_type` et
`contract_version`. Le `payload` est un objet, mais le schéma générique ne
prétend pas connaître sa sémantique propriétaire. Un validateur spécialisé
doit correspondre exactement aux deux valeurs annoncées ; son absence ou une
clé supplémentaire entraîne un rejet fermé.

Le socle générique interdit `metadata`, `meta`, `context`, `properties` et les
clés sensibles. Il borne chaque objet à 32 champs, chaque tableau à 32 éléments,
chaque chaîne à 256 caractères et la profondeur à quatre niveaux. Le runtime
futur devra aussi appliquer au document décodé un plafond total de 128 champs
et 8 192 octets. Un nombre non entier n'est recevable que si un futur contrat
spécialisé fermé le déclare et le valide explicitement ; le socle EVT v1
n'accepte que les entiers JSON sûrs.

Ces plafonds ne remplacent jamais le contrat spécialisé. Un bloc arbitraire ou
un document de type annoncé mais non validé est rejeté.

## Catalogue `faluss.event-source-catalog` 1.0.0

Le catalogue est une déclaration non exécutable du propriétaire. Sa racine
fermée contient :

| Champ | Rôle |
| --- | --- |
| `contract_version` | exactement `1.0.0` |
| `document_type` | exactement `faluss.event-source-catalog` |
| `catalog_version` | version du catalogue |
| `node_id` | nœud propriétaire |
| `app_key` | application propriétaire |
| `owner` | égal à `app_key` en v1 |
| `owner_engine` | moteur métier propriétaire |
| `capability_key` | capacité namespacée de la source |
| `capability_interface` | exactement `event_source` |
| `event_types` | définitions fermées des faits possibles |
| `compatibility` | versions runtime, dépréciation et remplacement du catalogue |

Chaque entrée de `event_types` contient exactement :

- `event_type` et `event_version` ;
- `payload_contract` ;
- `subject_policy` (`required`, `optional` ou `forbidden`) ;
- `allowed_actor_types` ;
- `object_policy`, avec présence et types fermés ;
- `allowed_destinations` ;
- `max_delivery_delay_seconds` ;
- `data_classification` ;
- `max_retention_seconds` ;
- `member_result_visibility` ;
- `lifecycle`, avec dépréciation, `sunset_at` et remplacement éventuel.

Les classifications sont exclusivement `operational`, `pseudonymous` et
`personal`. La visibilité future des résultats est exclusivement
`aggregate_only`, `own_subject_only` ou `never`.

Chaque `event_type` et chaque paire `event_type + event_version` sont uniques
dans un catalogue. Le type, la capacité, le propriétaire et les contrats
appartiennent à l'application déclarée. Les destinations d'une enveloppe sont
un sous-ensemble exact de celles de sa définition. Sujet, acteur et objet
respectent sa politique.

Le catalogue ne contient ni URL de transport, endpoint, clé, secret,
signature, callback PHP, classe, fonction, script, HTML, CSS, asset ou contenu
exécutable. Il n'autorise pas lui-même l'émission : catalogue accepté, manifeste
CAP, binding actif et politique consommateur sont cumulatifs.

## Types réservés sans émission

Les noms suivants sont réservés contractuellement. Aucun catalogue propriétaire
installé, payload spécialisé, adaptateur ou événement réel n'est créé par
EVT-01A.

| Propriétaire | Type réservé | Sémantique minimale future |
| --- | --- | --- |
| Faluss Hub | `faluss-hub.portal.viewed` | membre courant et surface Hub normalisée |
| Faluss Hub | `faluss-hub.app.opened` | application cible opaque, jamais une URL |
| Faluss Hub | `faluss-hub.daily-reward.claimed` | fait post-commit PF, sans montant, solde ni clé ledger |
| Faluss Me | `faluss-me.card.viewed` | sujet propriétaire, acteur membre ou anonyme |
| Faluss Me | `faluss-me.link.clicked` | référence opaque du lien uniquement |
| Faluss Me | `faluss-me.collection.opened` | référence opaque de collection uniquement |

AN-01 matérialisera éventuellement ces réservations avec leurs contrats de
payload spécialisés et les manifestes CAP versionnés nécessaires.

## Données interdites

Le payload, les références, les destinations, les logs techniques et toute
sortie membre interdisent : e-mail, login, nom ou pseudonyme, handle, URL ou
domaine brut, IP, User-Agent brut, cookie, session, OTP, code d'autorisation,
nonce de transport, signature, clé publique ou privée, identifiant ou payload
Stripe, donnée de carte bancaire, contenu privé, bio, contenu de lien, chemin
ou fichier média, `wp_user_id`, solde PF, clé d'idempotence économique,
metadata libre et contenu exécutable.

Une dimension normalisée future, telle qu'une classe d'appareil ou une
catégorie de provenance, exige son propre payload spécialisé fermé et
versionné. Une valeur brute n'est jamais admise.

## Idempotence, canonicalisation et livraisons

La livraison producteur future est **au moins une fois**. La conséquence de
chaque consommateur est **exactement une fois par idempotence**. L'événement
accepté est append-only : il ne peut être modifié ni supprimé fonctionnellement
pour être corrigé. Une correction métier est un nouvel événement spécialisé
qui référence de manière opaque une occurrence antérieure.

Le runtime futur calculera le SHA-256 du document canonique selon RFC 8785/JCS,
UTF-8, avec l'ordre des tableaux significatif et sans valeur JSON ambiguë :

- même identité et même hash : retourner l'occurrence existante ;
- même identité et hash différent : conflit fermé ;
- même référence avec autre `event_id` ou version : conflit fermé.

Analytics, Quêtes et Progression possèdent chacun une unicité de livraison et
un état de traitement indépendants. Deux workers, un retry ou une reprise après
panne ne peuvent déclencher deux conséquences. L'échec d'un consommateur
n'annule pas l'acceptation des autres et ne recrée jamais l'événement original.

## Coordination avec CAP et Federation

L'interface CAP `event_source` reste une déclaration. Une source future exige
en plus un catalogue EVT accepté, une capacité et un binding actifs, une
compatibilité valide et la politique locale de chaque consommateur. CAP-01B.2
n'active aucun événement.

EVT-01B.1 ajoute uniquement la lecture signée `event_catalog.read`, distincte
de `manifest.read` et `read_model.read`. Elle porte un sujet nul et le tuple
exact `owner_app_key`, `capability_key`, `catalog_version`; elle ne transporte
jamais une enveloppe `faluss.event`. `event.publish` demeure interdit et aucune
opération existante ne peut être détournée pour publier un événement.

Faluss Events 0.1.0 réutilise le validateur CAP de Faluss Apps Registry,
valide séparément manifeste et catalogue, puis exige application/propriétaire,
capacité `event_source`, bindings de chaque destination et compatibilité non
dépréciée/non expirée. Cette vérification n'active aucun binding runtime.
L'échange conserve signature Ed25519, fraîcheur, anti-rejeu, débit et politique
locale de Federation, sans réutiliser de secret FPR, Identity, Token Connector,
Stripe ou de session WordPress.

## Frontières des consommateurs futurs

Analytics pourra compter et agréger des événements acceptés, produire des
read-models filtrés, masquer toute identité de visiteur et appliquer les règles
de consentement et de rétention. Quêtes pourra utiliser certains faits comme
entrée mais décidera seul éligibilité, progression, idempotence et récompense.
Progression conservera ses propres politiques et écritures.

Aucun consommateur ne modifie l'événement, ne réinterprète une donnée absente,
n'attribue directement des PF hors Token Engine, ne fusionne deux membres ou ne
déduit une identité depuis une référence anonyme.

## Portée vérifiable et preuve

Les deux schémas embarquent le même `x-evt01a-scope` listant exactement les dix
fichiers autorisés. Le manifeste déclare explicitement : nature
`documentary-contract-only`, aucun runtime WordPress, transport, table,
migration, endpoint, événement réel, asset ou changement UI.

Le test EVT-01A parse les deux JSON, contrôle leur structure Draft 2020-12,
leurs branches fermées et les invariants sémantiques avec un helper PHP de test.
Ce helper n'est ni un runtime Faluss Events ni la preuve d'une validation JSON
Schema standard complète. Une validation standard ne peut être revendiquée que
si un moteur Draft 2020-12 indépendant est réellement disponible et exécuté.

EVT-01B.1 ne modifie pas ces deux schémas normatifs. Son test exécutable appelle
les validateurs PHP de production, le registre fermé, la politique et le chemin
de réponse signé Federation. Aucun catalogue Hub/Me réel, provider propriétaire,
événement, table, migration, outbox, inbox, worker, tracking ou Analytics n'est
livré.
