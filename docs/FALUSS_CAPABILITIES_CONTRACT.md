# CAP-01A — Contrat du registre d'applications et de capacités Faluss

## Statut et frontière

CAP-01A définit uniquement la fondation documentaire et contractuelle du futur
registre d'applications Faluss. Il permet aux applications propriétaires de
déclarer des capacités puis, dans un lot ultérieur, de demander des bindings
contextuels dans Faluss Hub, le Master Profile, Faluss Me, Analytics,
Progression et Quêtes.

Ce lot ne produit aucun runtime, plugin, endpoint, transport, écran, onglet,
table, migration, donnée membre, ZIP, asset, changement de comportement
WordPress ou implémentation de Fans, Shop, Cosmetics, Analytics, Quêtes ou
Progression. Il ne modifie pas les échanges SSO Identity, Token Engine
Connector, FPR, Stripe ou les mécanismes Portal existants.

Les mots **DOIT**, **NE DOIT PAS** et **PEUT** sont normatifs. Une absence de
document ou de projection reste une absence : elle ne devient jamais false,
0, « non possédé », « gratuit » ou « inactif ».

## Frontière de propriété

Chaque dérivé Faluss conserve ses données, ses moteurs, ses règles et ses
autorisations. Il ne peut jamais injecter librement du PHP, HTML, CSS,
JavaScript, shortcode, callback ou SQL dans une autre application. Il peut
seulement déclarer, dans un manifeste versionné :

- des capacités nommées et leurs interfaces fermées ;
- des read-models typés, minimaux et filtrés par leur propriétaire ;
- des références de contenu opaques ;
- des sources d'événements soumises à un contrat spécialisé futur ;
- des actions symboliques, déléguées à leur propriétaire.

Le registre ne devient pas propriétaire des données métier et ne transforme
jamais une action symbolique en autorisation. La surface consommatrice reste
seule propriétaire du rendu, de l'ordre, de l'accessibilité, du composant et du
système visuel. Elle peut refuser un binding pourtant demandé ; le producteur ne
décide jamais de sa visibilité effective.

L'autorité est réservée au moteur faluss-apps-registry et au namespace Master
Profile apps.registry. Faluss Portal ne devient pas propriétaire du registre :
sa fonction PHP codée en dur reste un mécanisme transitoire de présentation.
De même, AP-02A conserve son read-model Faluss Me étroit existant ; il n'est pas
transformé silencieusement en registre universel. CAP-01B remplacera plus tard
les décisions codées en dur par un registre runtime réel.

## Deux documents distincts

### Manifeste d'application

Le manifeste décrit ce qu'une application sait proposer à l'écosystème. Il
n'est jamais spécifique à un membre. Le schéma autonome
[faluss-app-capability-manifest.schema.json](../contracts/faluss-app-capability-manifest.schema.json)
impose :

- manifest_version, app_key stable et capability_namespace stable ;
- un propriétaire et une autorité ;
- un état produit fermé : planned, active, maintenance ou retired ;
- des origines canoniques HTTPS, une présentation publique minimale et un asset
  officiel éventuel ;
- des capacités, interfaces, emplacements demandés, actions symboliques et
  règles de compatibilité/dépréciation.

Il ne contient jamais de faluss_id, wp_user_id, e-mail, session, abonnement
membre, droit, solde, historique, paiement ou décision d'activation
personnelle. Le namespace de toute capability_key DOIT appartenir au
capability_namespace déclaré par l'application : aucune application ne peut
déclarer la capacité d'un autre moteur. Une action est un identifiant symbolique
sans URL, callback, code exécutable ni autorisation implicite.

Un asset officiel éventuel n'est référencé que par asset_key, MIME, SHA-256,
dimensions intrinsèques et mode de distribution. CAP-01A n'ajoute ni ne modifie
aucun asset. Une copie déclarée immuable DOIT rester byte-for-byte conforme à
son SHA-256 ; aucun asset ne peut être redessiné, dérivé, optimisé, recadré,
filtré ou réenregistré sans demande explicite. L'absence d'asset officiel reste
une absence : aucun logo de substitution et aucun CSS de réparation ne sont
inventés.

### Read-model apps.registry

Le read-model est calculé et filtré côté serveur pour le membre courant. Le
schéma autonome
[faluss-apps-registry-read-model.schema.json](../contracts/faluss-apps-registry-read-model.schema.json)
ne contient jamais de faluss_id, identifiant WordPress, session, e-mail,
paiement, solde, contenu métier ou contenu exécutable. Le sujet technique peut
exister uniquement dans une enveloppe serveur MP-01A avant d'être supprimé du
rendu.

Pour chaque application et capacité, il distingue obligatoirement :

- la disponibilité générale de l'application ;
- la relation du membre avec l'application ;
- l'état de la capacité ;
- la disponibilité, la source et la fraîcheur du read-model spécialisé ;
- la compatibilité avec la surface consommatrice ;
- les bindings actifs et les actions réellement autorisées.

Une application disponible n'est pas automatiquement liée au membre. Une
application liée ne rend pas automatiquement toutes ses capacités actives. Une
capacité active ne garantit pas qu'un read-model frais et compatible existe.
Une surface consommatrice ne complète, ne fusionne et n'invente jamais une
projection absente.

## États fermés et règle de binding

Les seuls états v1 sont :

| Élément | États autorisés |
| --- | --- |
| disponibilité d'application | available, unavailable, retired |
| relation membre | active, inactive, not_linked |
| capacité | enabled, disabled, temporarily_unavailable, not_supported |
| read-model spécialisé | available, unavailable, expired, not_supported |
| compatibilité de surface | compatible, incompatible, unsupported |

Seule une capacité enabled, appartenant à une application available, avec une
relation membre active, un read-model spécialisé available, une source fraîche
et une compatibilité compatible, PEUT produire un binding active. Les autres
états n'autorisent ni mutation, ni action autorisée, ni faux module. Une
capacité désactivée peut rester déclarée comme état, mais conserve des listes
vides de bindings et d'actions. Lorsqu'une capacité disparaît ou est révoquée,
le module est simplement omis sans mutation des données sources.

### Alignement JSON Schema CAP-01A.1

Les deux schémas Draft 2020-12 imposent directement les fermetures
structurelles suivantes :

- une application unavailable ou retired, ou dont la relation membre est
  inactive ou not_linked, impose des listes vides de active_bindings et
  allowed_actions pour chacune de ses capacités ;
- une capacité disabled, temporarily_unavailable ou not_supported, un
  read-model spécialisé unavailable, expired ou not_supported, ou une
  compatibilité incompatible ou unsupported, impose ces deux listes vides ;
- event_source ne peut viser que analytics.events, quests.events ou
  progression.events, et ces trois consommateurs n'acceptent aucune autre
  interface ;
- une capacité de manifeste portant module_read_model exige un contrat de
  read-model typé ; une action symbolique exige l'interface delegated_action.

Les deux schémas déclarent aussi x-semantic-invariants avec
server_validation_required: true et on_violation: reject. Cette extension
descriptive, sans exécutable ni validation propriétaire, impose au serveur un
refus fermé pour les comparaisons entre valeurs dynamiques : appartenance d'une
interface de binding aux interfaces de sa capacité, préfixes de namespace,
cohérence propriétaire/source, cohérence propriétaire/action et unicité
sémantique.

Les clés métier suivantes sont uniques dans leur collection : app_key dans un
document apps.registry, capability_key dans un manifeste et dans chaque
application du read-model, la paire (slot, interface) dans les bindings d'une
capacité, et action_key dans ses actions symboliques ou autorisées. Une
collision n'a ni premier ni dernier gagnant : elle échoue fermée, sans fusion ni
écrasement.

Le dépôt ne fournit pas de validateur JSON Schema Draft 2020-12. Le test
CAP-01A.1 vérifie donc structurellement les branches if/then ajoutées, puis
vérifie séparément les invariants sémantiques côté serveur. Le helper PHP n'est
pas présenté comme une preuve d'exécution d'un validateur JSON Schema standard.

## Interfaces et emplacements v1

Une capacité peut déclarer une ou plusieurs interfaces parmi cette liste fermée :

- module_read_model : projection minimale déjà filtrée par le propriétaire ;
- delegated_action : action symbolique dont l'exécution et les contrôles
  restent chez le propriétaire ;
- event_source : déclaration d'une future production d'événements, sans
  événement défini ou émis par CAP-01A ;
- content_reference_source : référence opaque vers un contenu sélectionné.

Une référence de contenu ne copie pas le contenu métier, ne transfère pas sa
propriété, ne constitue pas une autorisation permanente et DOIT être revalidée
par le propriétaire lors de chaque usage. Elle devient indisponible proprement
si sa source est retirée ou révoquée.

Les emplacements de surface v1 sont fermés :

- portal.apps.card_action
- portal.analytics.dataset
- master_profile.module
- master_profile.footer_action
- me.studio.tab
- me.studio.block_source
- me.public.tab
- me.public.block

Les sources d'événements visent séparément analytics.events, quests.events ou
progression.events. Un nouvel emplacement exige une évolution versionnée du
contrat ; aucun wildcard n'est admis. Une application peut demander un
emplacement compatible, mais ne décide jamais de la position, de l'ordre, des
dimensions, du CSS, du composant, de l'activation finale ou de la visibilité.

## Sécurité, fraîcheur et transport futur

Les décisions d'application liée, capacité active, slot, propriétaire, contrat
et action autorisée sont exclusivement serveur. Aucun navigateur ne les fournit
ni ne les décide. Il n'existe aucun accès direct aux tables d'un autre moteur,
aucun partage de session WordPress entre domaines, aucun rapprochement par
e-mail et aucun wp_user_id comme identité inter-applications.

Tout binding vers un emplacement inconnu, toute incompatibilité, expiration,
absence ou révocation échoue fermée. Aucune URL arbitraire n'est admise : toute
URL future doit être HTTPS, issue d'une origine canonique déclarée et validée
côté serveur. Les métriques progression.*, fans.*, date.* et hof.* restent chez
leurs propriétaires ; le Master Profile ne calcule aucun score composite.

CAP-01A ne crée aucun transport. FED-01A fixe séparément le contrat HTTPS
serveur-à-serveur, Ed25519, autorisations exactes par application et capacité,
corps canonique signé, durée courte, anti-rejeu, versions strictes, refus fermé
et réponses privées bornées ; FED-01B l'implémente sans registre ni provider
de manifeste ou de read-model métier. Aucun secret
ne peut résider dans Git, WordPress, le navigateur ou une URL publique. Les
transports spécialisés SSO Identity, Token Engine Connector et FPR restent
spécialisés et ne deviennent pas un bus universel.

## Exemples de frontières valides

- Faluss Hub peut déclarer un Daily Reward propriétaire vers
  portal.apps.card_action. Le contrat DR-01 reste seul propriétaire du statut,
  du montant, de la période et de l'idempotence.
- Faluss Me peut déclarer ses read-models ou une future source Analytics. Sa
  présence dans apps.registry exige une relation membre prouvée ; la
  disponibilité publique de faluss.me ne suffit pas.
- Faluss Fans peut déclarer fans.content.teaser_source avec
  content_reference_source, me.studio.block_source et l'action symbolique
  fans.select_teaser. Le registre ne transporte ni média, ni contenu Fans,
  ni URL privée, ni droit d'accès.
- Un moteur Shop peut déclarer un catalogue public du membre en
  module_read_model vers me.public.tab. Faluss Me rend l'onglet ; Shop
  conserve produits, prix, disponibilité, acquisition et autorisations.
- Faluss Cosmetics peut déclarer cosmetics.equipped vers
  master_profile.module, limité aux objets effectivement équipés et
  publiables, jamais à l'inventaire, aux prix, aux achats ou aux paiements.

Sont notamment invalides : toute donnée sensible dans l'un des deux documents,
du HTML/PHP/JavaScript/CSS/SQL injecté, un slot inconnu ou wildcard, une
capacité sans propriétaire/version/source/fraîcheur/compatibilité, une
application disponible assimilée à une relation membre active, une capacité
inactive accompagnée d'une action mutatrice, une URL non déclarée, une copie de
contenu Fans ou produit Shop, un read-model expiré rendu comme actuel, une
fusion de métriques, une lecture directe de table dérivée ou une activation
décidée par le navigateur.

## Coordination avec MP-01A et feuille de route

Le Master Profile consomme apps.registry comme read-model spécialisé sans
devenir son propriétaire. Il conserve ses règles d'audience, de fraîcheur, de
mode fantôme et d'absence ; un module contextuel n'apparaît qu'après résolution
d'un binding actif. FED-01A n'authentifie que le transport : les manifestes
signés acceptés bornent les applications, capacités, bindings et actions, sans
que leur origine n'installe de confiance. CAP-01B résoudra ce registre et
revalidera la version du manifeste. CAP-01A ne modifie pas
contracts/master-profile-module.schema.json.

Les étapes futures, sans implémentation ici, sont :

1. CAP-01A — contrat du registre et des capacités ;
2. FED-01A — contrat du transport privé fédéré ;
3. FED-01B — runtime plugin du transport privé fédéré, livré sans CAP-01B ;
4. CAP-01B — registre runtime et projection réelle apps.registry ;
5. EVT-01 — enveloppe commune d'événements ;
6. AN-01 — moteur Analytics et premiers événements réels Hub/Me ;
7. MP-01B — assembleur réel du Master Profile ;
8. COS-01 — catalogue, inventaire et équipement cosmétique ;
9. SHOP-01 — boutique Premium sur faluss.com ;
10. intégrations contextuelles Faluss.me ;
11. Quêtes et Progression après stabilisation des événements.

## Runtime CAP-01B.1

CAP-01B.1 livre uniquement l'échange signé des manifestes publics `faluss-hub` et `faluss-me`. Faluss Apps Registry 0.1.0 fournit le validateur PHP spécialisé du contrat `faluss.app-capability-manifest` 1.0.0, sans se présenter comme un moteur JSON Schema Draft 2020-12 complet. Le validateur de contrat est enregistré séparément des producteurs Federation : sa présence permet de vérifier une réponse distante sans autoriser la production locale d'une autre application.

Faluss Portal 0.1.21 possède le manifeste Hub et Faluss Link 0.3.19 possède le manifeste Me. Leur chargement intervient après l'enregistrement du validateur, sans dépendre de l'ordre d'activation. Les deux manifestes ont `official_asset: null`; aucun asset, rendu, CSS, JavaScript, donnée membre, table, option, cache durable ou politique Federation n'est créé ou modifié. Sans composant exact, le transport reste fermé avec `not_available` ou `incompatible`.

Le resolver, la projection membre `apps.registry` et sa consommation par Portal restent réservés à CAP-01B.2. La fonction PHP codée en dur de Portal demeure donc inchangée dans CAP-01B.1.

SUB-01C et SUB-01D, le Daily Reward Faluss Me 75 PF, Fans, Date, Shop et Hall
of Fame comme moteurs propriétaires futurs, ainsi que le staging comme chantier
opérationnel différé, restent préservés. Le défaut mobile réel DR-02A.2 est
également reporté : sous Portal 0.1.20, le tap sur la pill Hub ne navigue plus
mais aucun changement visuel observable n'est produit ; l'état de la requête et
du ledger reste indéterminé. Aucun de ces éléments n'est livré par CAP-01A.

## Portée vérifiable

CAP-01A est limité exactement aux neuf artefacts déclarés dans x-cap01a-scope
du schéma de manifeste. Il n'ajoute aucun fichier sous plugins/, aucune version
de plugin, aucun asset, ZIP, table, colonne, migration, option, route
REST/AJAX/admin-post, shortcode, widget, cron, hook runtime, écran ou
modification UI Portal, Faluss Me ou Master Profile. Il ne produit aucune
recette WordPress car rien n'est installable.

CAP-01A.1 aligne exclusivement les deux schémas, le présent contrat et le test
CAP-01A : aucun autre fichier, état, interface, emplacement, valeur métier ou
étape de roadmap n'est modifié.
