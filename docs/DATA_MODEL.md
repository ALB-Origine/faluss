# Modèle de données minimal

Les noms réels utilisent le préfixe WordPress actif.

## Faluss Identity

| Table | Clés / contenu |
|---|---|
| `faluss_identity_profiles` | `faluss_id` unique, `wp_user_id` unique, statut, dates, version de consentement ; état ONB-01 / ONB-02 minimal (choix, statut de réservation, prochaine étape reprenable, version de flow) |
| `faluss_identity_public_profiles` | `faluss_id` unique, identifiant public unique, contenu public, ordre des liens et statut de publication |
| `faluss_identity_challenges` | challenge haché, OTP haché, empreinte cookie navigateur, tentatives, statut, expiration |
| `faluss_identity_rate_limits` | bucket haché, compteur, fenêtre et expiration ; InnoDB |
| `faluss_identity_clients` | client opaque, statut, secret haché, scopes et URI de retour autorisées |
| `faluss_identity_auth_codes` | code haché, Faluss ID, client, URI, challenge PKCE, scopes, expiration, consommation atomique |
| `faluss_identity_authorization_requests` | empreinte de poignée navigateur, client, URI, scopes, PKCE et `state`, expiration et décision ; reprise locale de consentement |
| `faluss_identity_audit` | événement minimal et non sensible, conservation bornée |

Les codes, OTP, secrets navigateur et secrets client ne sont jamais conservés en clair. Les migrations valident moteur InnoDB, index, unicité et ordre d'index avant de déclarer le schéma utilisable.

L’état ONB-01 / ONB-02 est ajouté aux lignes Identity existantes : il ne crée ni table de profils concurrente, ni copie de slug, de carte, d’e-mail ou de préférence visuelle. Le slug public permanent reste l’unique clé dans `faluss_identity_public_profiles`. ONB-02 conserve seulement la prochaine étape (`wizard_name` à `wizard_finish`) ; toutes les valeurs de carte restent dans les tables Identity ou Link qui les possèdent déjà.

## Client

| Table | Clés / contenu |
|---|---|
| `faluss_identity_links` | `wp_user_id` unique, `faluss_id` unique, dates de liaison et dernière preuve |
| `faluss_identity_client_state` | état SSO haché, retour local validé et expiration courte |

Un conflit de liaison est bloquant : il ne doit jamais être résolu automatiquement par l'e-mail.

## Faluss Link

| Table | Clés / contenu |
|---|---|
| `faluss_link_cards` | `faluss_id` unique, préférences visuelles, réseaux sociaux complémentaires et dates ; aucune donnée d’identité dupliquée |
| `faluss_link_blocks` | `faluss_id` et identifiant de bloc uniques, ordre stable et payload de contenu validé ; un teaser peut conserver son mode visuel Public/Membre/Droit et un code de droit central, sans copie d’attribution, de solde ou de décision |
| `faluss_link_discoveries` | paire unique visiteur Faluss / profil découvert, première et dernière découverte, compteur interne ; aucune bio, photo, lien, IP, user-agent ni référent |
| `faluss_link_discovery_settings` | `faluss_id` du visiteur unique, préférence privée d’enregistrement et date de mise à jour |

Les tables de découvertes sont privées à Faluss Link. Les profils sont relus depuis la table publique Identity seulement au rendu de la bibliothèque ; un profil masqué ou supprimé est ignoré sans dupliquer son contenu. La suppression individuelle et totale est toujours filtrée par le `faluss_id` du membre courant.

ONB-02 ne crée aucune table Faluss Link : `faluss_link_cards` porte les préférences visuelles et les réseaux du brouillon, tandis que `faluss_link_blocks` porte les liens libres ordonnés. Ces valeurs sont relues par le même résolveur que le Studio et la carte publique.

## Production Reset FPR-01

FPR-01 n'ajoute aucune table ni migration aux plugins métier. Son plugin isolé
conserve seulement ses propres options d'armement, verrouillage, nonce Hub
consommé sous empreinte et reçu technique composé de `run_id`, statut, dates et
compteurs. Il ne persiste jamais e-mail, login, `faluss_id`, URL ou chemin de
média dans ces options.

Le reset vide uniquement les tables membre Identity/Link explicitement listées
dans [`FALUSS_PRODUCTION_RESET.md`](FALUSS_PRODUCTION_RESET.md), tout en
préservant `faluss_identity_clients`, schémas et structures. Il refuse si la
table PF existe mais n'est pas vide et n'opère aucune mutation de
`token_engine_ledger`, le ledger ALB historique.

## Token Engine

| Table | Clés / contenu |
|---|---|
| `token_engine_projects` | `project_key` unique et stable, nom, état, identifiant connecteur public facultatif, empreinte/version de secret et permissions ; aucun projet système |
| `token_engine_rules` | `rule_key` unique et stable, projet facultatif, portée, déclencheur, périodicité, cooldown, montant positif, état, dates ; TE-03 exécute explicitement uniquement la règle globale `daily_reward` lorsque le Connector porte `reward.claim` |
| `token_engine_ledger` | UUID unique, `subject_id`, projet, règle facultative, sens, montant positif, clé d’idempotence unique, référence, métadonnées JSON bornées et date ; source unique de la projection de solde |
| `token_engine_connector_tokens` | empreinte unique de jeton opaque, projet, version de secret, permissions, expiration et date ; aucun jeton clair stocké |
| `token_engine_entitlement_definitions` | code unique, libellé, projet/surface, type générique `theme`, état et dates ; aucune identité ou donnée métier |
| `token_engine_entitlement_grants` | UUID unique, `subject_id`, définition, source manuelle, référence d’opération unique, cycle de vie (début/fin/révocation) et dates ; aucun solde ni ledger dupliqué |

La donnée `subject_id` reste générique. Le connecteur Faluss futur fournira un `faluss_id`, sans que cette table ne copie une identité, un e-mail ou des données métier.

PF-01 réserve le libellé visible **Point Faluss (PF)**, mais ne projette aucun
solde : aucun ledger Faluss explicitement identifié par un namespace ou code
d'unité tel que `faluss_pf` n'existe encore. Un ledger ALB / Alternative LAB
historique reste hors de cette surface et ne peut être converti, masqué ou
affiché comme PF. Cette règle ne renomme ni code d'unité configurable, ni ligne
historique, ni table Token Engine.

## Points Faluss PF-02A

PF-02A n'ajoute aucune table, colonne, option, migration, solde ou donnée membre.
Le futur ledger PF est décrit seulement comme une suite append-only d'entrées
versionnées : UUID d'entrée, `faluss_id` opaque serveur, montant PF entier
positif, direction, classe `earned`/`funded`/`promotional`, catégorie, source,
idempotence, politique, date UTC et référence de compensation éventuelle.

Les balances futures seront dérivées de ces entrées par classe, jamais éditées ou
copiées dans Portal, Master Profile, Fans, Hall of Fame ou Progression. Une
compensation sera une nouvelle entrée de la même classe, liée à l'original ;
elle ne peut pas reclassifier `earned` ou `promotional` en `funded`. Le schéma
de format, sans stockage ni runtime, est
[`contracts/faluss-pf-ledger-entry.schema.json`](../contracts/faluss-pf-ledger-entry.schema.json).

Les catégories `profile_daily_claim`, `daily_accrual`, `pf_pack_purchase`,
`pf_pack_bonus`, `fans_support`, `cosmetic_redemption`, `manual_adjustment` et
`reversal` sont réservées et inactives. Aucun solde ou historique ALB ne peut
devenir PF, et PF-02A ne crée aucune projection `pf.summary`.

## Points Faluss PF-02B

PF-02B ajoute uniquement `token_engine_pf_ledger`, une table InnoDB dédiée et
préfixée WordPress. Elle contient un identifiant interne, `entry_uuid`,
`faluss_id`, montant PF positif, direction, classe économique, catégorie,
versions, propriétaire et référence source opaque, clé d'idempotence, date UTC,
référence de compensation, motif administratif privé, métadonnées JSON bornées
et date de création. Les UUID d'entrée et les clés d'idempotence sont uniques ;
des index couvrent le sujet/classe/date, sujet/catégorie/date,
propriétaire/catégorie/date et la référence compensée. Depuis le schéma `5`,
`compensates_entry_uuid` porte l'index unique nullable
`pf_compensates_entry_unique` : les entrées ordinaires restent à `NULL`, tandis
qu'une écriture d'origine ne peut recevoir qu'une seule compensation complète.

Cette table n'est ni une extension ni une lecture de `token_engine_ledger` :
aucune table ALB existante, donnée historique ou configuration d'unité n'est
modifiée. Les balances PF sont dérivées à la lecture par classe et aucune table
de solde ne les matérialise. PF-02B n'ajoute aucun read-model `pf.summary`,
projection Portal/Master Profile ou donnée membre visible.

## Daily Rewards DR-01

DR-01 n'ajoute aucune table, colonne, option, donnée membre, cache durable,
ledger PF, migration ou écriture. Il décrit un document éphémère de read-model
filtré pour le membre courant : app, clé de reward, propriétaire, statut,
reward légalement annonçable, période `daily` `Europe/Paris`, délégation,
fraîcheur, source et compatibilité. Il ne contient jamais `faluss_id`, e-mail,
session, solde, détail d'éligibilité, historique ou donnée de paiement.

La date logique, l'éligibilité et l'idempotence sont des décisions serveur du
moteur propriétaire. L'absence de document n'est ni un solde `0`, ni un reward
réclamé, ni une éligibilité déduite. Le schéma sans transport ni stockage est
[`contracts/faluss-daily-reward.schema.json`](../contracts/faluss-daily-reward.schema.json).

## Faluss Subscriptions

| Table | Clés / contenu |
|---|---|
| `faluss_subscriptions` | UUID interne, Faluss ID opaque, fournisseur et références, plan/période, statut normalisé, essai/périodes, ancre/fin de grâce, annulation, synchronisation et version |
| `faluss_subscription_trials` | Faluss ID et empreinte dérivée du moyen de paiement uniques, éligibilité, cycle de vie, preuve serveur de vérification et dérogation auditée |
| `faluss_entitlements` | droit, valeur, source, référence idempotente, priorité, dates, statut et version liés au Faluss ID |
| `faluss_subscription_events` | fournisseur et événement uniques, type, état de traitement, tentatives, empreinte de payload et erreur nettoyée |
| `faluss_subscription_audit` | acteur, action, Faluss ID, source, états nettoyés, justification et UTC |
| `faluss_billing_customers` | lien unique Faluss ID ↔ Customer fournisseur ; aucune adresse e-mail ni moyen de paiement |
| `faluss_billing_checkout_sessions` | état opaque, clé d’idempotence, références de session/customer et expiration ; aucune URL Stripe ou donnée de carte |
| `faluss_subscription_notifications` | file de notification par référence hachée ; aucun destinataire, contenu ou e-mail persistant |

Ces tables sont propres à `faluss.com`. Elles ne dupliquent ni profil,
e-mail, carte, contenu de carte publique, solde Point Faluss (PF), achat permanent ou
cosmétique. Leur schéma, leurs sources de droits et leurs transitions sont
définis dans [`FALUSS_SUBSCRIPTIONS.md`](FALUSS_SUBSCRIPTIONS.md).

## Faluss Portal

PF-01 n'ajoute aucune table. Le portail filtre la liaison client Identity par
utilisateur WordPress courant et réduit la décision d'abonnement à des valeurs
affichables. DR-02A n'ajoute toujours ni table, colonne, migration, cache
durable ni écriture Portal : le seul flux PF est la délégation Hub explicite
vers la façade interne Token Engine. Portal ne lit directement ni
`token_engine_pf_ledger` ni `token_engine_ledger` ALB et ne persiste aucune
projection, période ou idempotence PF.

Le document de statut retourné par le Core pour Hub est éphémère et filtré :
clé Hub, propriétaire, statut, reward légalement annonçable, jour
`Europe/Paris`, délégation, fraîcheur et compatibilité. Le `faluss_id`, les
références d'écriture, les clés d'idempotence, les soldes, historiques, e-mails,
paiements, Stripe et critères d'éligibilité détaillés n'entrent ni dans le HTML
ni dans la réponse AJAX. Une absence ou une indisponibilité reste un état sans
donnée inventée, jamais un solde `0` ou un gain simulé.

AP-02A n'ajoute ni table, ni colonne, ni migration. Identity produit à la fin
de l'échange SSO une projection d'app minimale `me` (`contract_version`, état
`published`, URL membre canonique). Identity Client la valide contre son
autorité configurée et la conserve dans la meta privée
`_faluss_identity_client_member_apps_v1` du compte WordPress lié. Cette meta est
un read-model révocable, jamais une source de publication ; elle est supprimée
au prochain échange lorsque la projection n'est plus fournie. Portal ne la lit
que par la façade Identity Client et ne reçoit ni slug public, ni contenu de
carte, ni donnée économique.


## Registre d'applications et capacités CAP-01A

CAP-01A n'ajoute aucune table, colonne, option, migration, cache persistant,
donnée membre ou copie de donnée métier. Il définit seulement un manifeste
d'application sans identité membre et un futur read-model éphémère
apps.registry, filtré côté serveur. Celui-ci distingue disponibilité de
l'application, relation membre, état de capacité, source/fraîcheur du
read-model spécialisé, compatibilité de surface, bindings actifs et actions
effectivement autorisées.

Le faluss_id peut servir uniquement dans l'enveloppe serveur MP-01A avant
suppression au rendu ; il n'entre pas dans apps.registry. Aucun navigateur ne
décide l'activation ou le slot, aucune surface ne lit directement une table
dérivée, et une absence ne devient jamais une valeur ou une projection
inventée. Les deux schémas contractuels sans transport ni stockage sont
[faluss-app-capability-manifest.schema.json](../contracts/faluss-app-capability-manifest.schema.json)
et
[faluss-apps-registry-read-model.schema.json](../contracts/faluss-apps-registry-read-model.schema.json).

## Transport privé fédéré FED-01A et runtime FED-01B

FED-01A n'ajoute aucune table, colonne, option, migration, clé, cache durable
ni copie de read-model. Il définit les enveloppes éphémères de requête et de
réponse signées Ed25519 : identités exactes de nœud/application, `key_id`,
requête liée, dates UTC courtes, nonce anti-rejeu et payload spécialisé validé
indépendamment. Un contexte serveur peut contenir le `faluss_id` opaque mais
celui-ci n'est jamais rendu, journalisé ni une autorisation suffisante. Les
clés privées demeurent hors Git et hors données WordPress exportables ; les
clés publiques et politiques de confiance sont gérées par FED-01B uniquement
dans ses quatre tables préfixées : `faluss_federation_peers`,
`faluss_federation_request_bindings`, `faluss_federation_nonces` et
`faluss_federation_audit`. Elles ne contiennent ni seed, clé privée, payload,
Faluss ID, e-mail, session ni donnée métier. Les deux tables anti-rejeu ont une
rétention technique d'au moins 15 minutes ; l'audit minimal est purgé au plus
après 30 jours. Aucune projection CAP-01B ou Master Profile n'est persistée.

## Événements EVT-01A et runtime EVT-01B.1

EVT-01A n'ajoute aucune table, colonne, index, migration, option, transient,
outbox, inbox, queue, audit, cache ou donnée membre. Les schémas
[`faluss-event-envelope.schema.json`](../contracts/faluss-event-envelope.schema.json)
et
[`faluss-event-source-catalog.schema.json`](../contracts/faluss-event-source-catalog.schema.json)
décrivent seulement des documents futurs et ne provoquent aucune écriture.

Le futur modèle distinguera conceptuellement l'événement métier immuable, son
acceptation idempotente, une livraison par destination et l'état de traitement
propre à chaque consommateur. L'identité idempotente associera nœud,
application, type, version et référence propriétaire ; le hash canonique
RFC 8785/JCS permettra de retrouver un retry identique et de refuser un conflit.
Ces structures ne sont pas matérialisées. Faluss Events 0.1.0 ne possède aucune
table, colonne, option, transient ou donnée membre ; son registre de providers
et ses validateurs résident uniquement en mémoire pendant la requête PHP.
Federation 0.2.0 réutilise ses quatre tables techniques existantes sans migration
ni nouvelle colonne. Son audit de `event_catalog.read` conserve seulement les
nœuds, l'opération, la capacité et le résultat générique, jamais le manifeste,
le catalogue, les types, destinations, payloads, URL, Faluss ID ou clés.

Un `faluss_id` éventuel reste limité aux contextes sujet ou acteur réservés de
l'enveloppe serveur. Il n'entre jamais dans le payload, une référence, une URL,
un cache public, un log technique ou une sortie membre. Aucun solde PF,
identifiant économique, paiement ou contenu propriétaire n'est copié.

## Master Profile MP-01A

MP-01A n'ajoute aucune table, colonne, option, migration, donnée membre ou copie
de projection. Le Master Profile ne possède pas de modèle métier persistant :
il consomme des enveloppes éphémères conformes à
[`MASTER_PROFILE_CONTRACT.md`](MASTER_PROFILE_CONTRACT.md), identifiées côté
serveur par le seul `faluss_id` opaque.

Chaque enveloppe déclare son namespace concret, sa version, son propriétaire,
son activation, ses audiences, son payload minimal ou son état vide, sa
fraîcheur, son read-model source, ses actions déléguées et sa compatibilité. Une
absence d'enveloppe reste une absence de donnée. `ghost_until` est une politique
de visibilité du sujet et non une donnée copiée dans chaque module ; il masque
les projections publiques sans modifier les données des moteurs.

Le schéma JSON v1 réside dans
[`contracts/master-profile-module.schema.json`](../contracts/master-profile-module.schema.json).
Il décrit un format d'échange seulement et ne crée ni transport, route REST,
cache persistant, table fédérée ou nouvelle autorité de données.
