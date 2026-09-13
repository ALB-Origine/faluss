# Architecture cible

## Topologie

```text
faluss.me
  └── Faluss Identity (autorité d'identité) + profil public + Token Engine Connector

faluss.com
  └── Faluss Portal (shell membre privé PF-01) + Faluss Subscriptions (niveaux Gratuit/Pro) + Token Engine (ledger économique central)

pro.faluss.com
  └── Altlab Platform + Faluss Identity Client + Token Engine Connector (futur usage)

date.faluss.com
  └── Date + Faluss Identity Client + Token Engine Connector (futur usage)
```

Les installations WordPress, leurs tables `users`, leurs sessions et leurs bases de données restent séparées. Les sites peuvent être sur le même serveur, mais ne se font pas confiance par défaut.

## Faluss Portal PF-01

`Faluss Portal` est une brique de rendu front-office de `faluss.com`, insérée
par le shortcode `[faluss_portal]` sur `/mon-faluss/`. Il utilise la session
locale issue de Faluss Identity Client comme unique point de départ puis ne
projette que des lectures réduites de Faluss Subscriptions. Il ne lit aucun
ledger Token Engine tant qu'un namespace PF officiel n'est pas défini : un
solde ALB historique ne devient jamais Point Faluss. Il ne possède ni table,
ni identité, ni abonnement, ni solde, ni facture, ni profil universel. Son
contrat détaillé et la matrice de sources de vérité sont
dans [`FALUSS_PORTAL.md`](FALUSS_PORTAL.md).

AP-02A fait transiter pendant l'échange SSO serveur-à-serveur un read-model
minimal et versionné de Faluss Me : état `published` et route membre canonique,
sans contenu de carte ni donnée fournie par le navigateur. Identity émet la
projection, Identity Client en valide l'autorité et la conserve pour le compte
local lié, puis Portal la lit sans appel inter-domaine supplémentaire. Une
projection absente ou invalide n'active jamais Faluss Me. Les destinations
membre sont `faluss.com/mon-faluss` pour Hub et `faluss.me/mon-faluss` pour Me.

## Master Profile fédéré MP-01A

Le futur Master Profile est une projection fédérée, jamais une source métier.
Chaque moteur reste propriétaire de ses tables, règles, métriques et décisions,
et fournit seulement un read-model minimal, versionné, daté et filtré. Le
Master Profile ne lit pas directement le stockage d'un dérivé, ne persiste pas
son payload et ne complète jamais un module absent par une valeur supposée. Le
seul sujet commun est le `faluss_id` opaque, réservé aux échanges serveur et
retiré de tout rendu public.

Les namespaces empêchent les collisions : `progression.*` appartient
exclusivement au moteur Progression, tandis que `date.*`, `fans.*` et `hof.*`
restent dans leurs moteurs respectifs. Une contribution dérivée à la progression
globale nécessitera un pont de politique futur, explicite et versionné, détenu
par Progression. L'assembleur ne calcule ni ne fusionne aucun score.

Les seules audiences de module sont `private`, `members` et `public`. La
politique de sujet facultative `ghost_until` masque prioritairement la Carte
Membre et toutes les projections publiques sans toucher aux sources ni aux
réglages enregistrés. Le contrat complet, les modules de référence et le schéma
machine-readable sont définis dans
[`MASTER_PROFILE_CONTRACT.md`](MASTER_PROFILE_CONTRACT.md). MP-01A ne modifie
aucun plugin, route, écran, table, migration ou comportement WordPress.


## Registre d'applications et capacités CAP-01A

CAP-01A réserve faluss-apps-registry comme autorité du futur read-model
apps.registry. Les applications productrices déclarent seulement un manifeste
versionné, des capacités et des références ou actions symboliques ; elles ne
transportent ni code exécutable, ni identité rendue, ni donnée métier. Les
surfaces Portal, Master Profile et Faluss Me restent propriétaires de leur
rendu et n'activent un module qu'après un binding actif, résolu côté serveur.

Le registre ne remplace ni la projection AP-02A ni le registre de présentation
AP-01 déjà codé dans Portal. CAP-01A ne crée aucun plugin, transport, endpoint,
table, migration ou comportement WordPress. FED-01A définit le transport privé
contractuel, FED-01B livre son runtime isolé, puis CAP-01B livrera le registre
runtime ; aucun registre CAP-01B n'est livré ici.

## Transport privé fédéré FED-01A

FED-01A réserve un échange HTTPS serveur-à-serveur entre nœuds approuvés, signé
uniquement Ed25519, à clés locales rotationnées et politique exacte par nœud,
application, opération, capacité et audience. Il ferme l'échange à trois
lectures bornées, impose canonicalisation, durée courte et anti-rejeu atomique,
et ne partage ni session WordPress ni table. FPR, Identity, Token Connector et
Stripe restent séparés. FED-01B ajoute exclusivement sa route privée, ses clés
publiques et politiques locales, son anti-rejeu et son audit technique ; aucun
provider métier, manifeste réel ou projection membre n'est embarqué.

## Contrat commun des événements EVT-01A

EVT-01A définit une enveloppe `faluss.event` et un catalogue propriétaire
`faluss.event-source-catalog`, tous deux en version 1.0.0. Un événement est un
fait métier déjà commis, jamais une commande, une autorisation, une source de
PF, un entitlement ou une donnée choisie par le navigateur. L'enveloppe locale
et une future enveloppe distante utilisent le même format fermé.

La topologie future sépare le propriétaire métier, son adaptateur serveur, le
moteur Faluss Events idempotent et chaque consommateur Analytics, Quêtes ou
Progression. L'événement sera append-only, livré au moins une fois et chaque
conséquence sera exactement une fois par idempotence propre au consommateur.
CAP `event_source`, catalogue EVT, binding actif et politique consommateur sont
tous obligatoires. Federation reste fermé aux trois lectures existantes ; un
transport signé d'événements est réservé à EVT-01B.

EVT-01A n'ajoute aucun plugin, runtime, table, migration, endpoint, outbox,
inbox, queue, appel réseau, cookie, tracking, événement réel ou changement
WordPress. Le contrat complet est dans
[`FALUSS_EVENTS_CONTRACT.md`](FALUSS_EVENTS_CONTRACT.md).

## Production Reset FPR-01

`Faluss Production Reset` est un plugin isolé, installé identiquement sur
`faluss.me` et `faluss.com`, pour le seul reset pré-lancement coordonné. Il ne
partage aucune base de données : la coordination reste limitée à une opération
Hub fixe, HMAC-signée, datée et non rejouable, sans liste de membres ni cible
transportée. Le Hub recalcule toujours ses candidats localement.

Le déclencheur serveur rendu est exclusivement dans l'administration de
`faluss.me`, avec `manage_options`, nonce et phrase exacte. Les deux sites sont
armés localement puis verrouillés après succès ou état partiel; la route Hub
n'est même enregistrée que pendant son armement. Aucun retry inter-sites, aucun
rollback distribué ni endpoint générique ne sont fournis. Le contrat
d'installation, de suppression sûre et de recette est
[`FALUSS_PRODUCTION_RESET.md`](FALUSS_PRODUCTION_RESET.md).

## Modèle Elementor de profil public

Un administrateur peut sélectionner une page Elementor déjà publiée dans **Réglages → Profil public Faluss**. Les routes `faluss.me/identifiant` rendent alors cette page ; le widget **Profil public Faluss**, sans identifiant renseigné, reçoit l’identifiant de la route. Le plugin ne crée aucune page. Si aucun modèle valide n’est sélectionné ou qu’Elementor est indisponible, le rendu autonome du profil est conservé.

## Onboarding de carte (ONB-01 / ONB-02)

La preuve passwordless crée ou active seulement l’identité opaque. La création facultative de carte est ensuite un flux distinct rendu par le widget **Onboarding Faluss** ou son shortcode. L’administrateur peut sélectionner une page Elementor déjà publiée dans **Réglages → Onboarding Faluss** : son URL réelle est la destination canonique, sans dépendre d’un slug imposé. Aucune page n’est créée automatiquement et `/commencer` reste le rendu de secours sans page sélectionnée.

Le navigateur ne transporte qu’une poignée opaque, courte et expirante dont le serveur conserve l’intention autorisée et le retour local validé. La priorité de retour est : consentement SSO local, retour exact d’un teaser ou d’une récompense, ouverture de l’onboarding pour une intention de carte, ouverture de l’onboarding pour une décision encore requise, puis retour générique. `faluss_id` reste entièrement serveur. La réservation finale insère atomiquement le slug dans le registre public FI-03 sous forme de brouillon minimal.

ONB-02 transforme ensuite ce brouillon sur la même surface Elementor en étapes enregistrées : Identity reste seul propriétaire du nom, de la bio, de l’avatar, de la publication et des liens publics ; Faluss Link reste seul propriétaire des préférences visuelles, réseaux et blocs. Le seul état ajouté au profil Identity existant est la prochaine étape reprenable : aucune table d’onboarding, copie d’identité ni source concurrente n’est créée. Le brouillon reste non rendu publiquement jusqu’à l’action finale idempotente de publication.

## Identifiants

| Élément | Portée | Usage |
|---|---|---|
| `faluss_id` | écosystème | identité stable, UUID opaque |
| `wp_user_id` | une installation | session et données métier locales |
| `client_id` | une application déclarée | identification SSO de l'application |

Le client stocke une liaison unique `wp_user_id ↔ faluss_id`. La liaison est créée seulement après l'échange de code validé par Faluss Identity.

Le client conserve l’état SSO éphémère sous empreinte serveur et cookie `HttpOnly`, puis consomme cet état une seule fois avant l’échange PKCE. Un e-mail existant sans liaison ne peut jamais déclencher de rapprochement automatique : l’utilisateur ouvre d’abord sa session locale et lie explicitement son Faluss ID.

## Découplage métier

Faluss Identity ne connaît pas les commandes, acquisitions, tokens, progression ou données de rencontre. Il ne renvoie que les attributs consentis au client, dans un scope déterminé.

Faluss Subscriptions, sur `faluss.com`, est l’autorité des abonnements, essais
et droits de niveau Gratuit/Pro. Il utilise le Faluss ID existant comme clé
opaque mais ne crée ni identité, ni session, ni transport intersite. Faluss Hub
pourra agrèger ultérieurement des projections minimales, par exemple « Pro
activé ». Une donnée ne remonte jamais par défaut : chaque projection est
spécifiée, validée et révocable.

Faluss Identity ne porte ni abonnement, ni portefeuille Point Faluss (PF) ni cosmétique. Faluss.me consommera ultérieurement des droits d’abonnement et un inventaire cosmétique propres à sa carte publique ; les objets acquis avec des Points Faluss ou de la progression restent possédés, tandis que les avantages inclus à l’abonnement sont temporaires.

SUB-01B ajoute dans Faluss Subscriptions l’adaptateur Stripe central serveur :
Customer lié au Faluss ID opaque, Checkout et Customer Portal hébergés, webhook
Stripe signé, migration additive et résolution d’états. Aucun appelant membre,
bouton de vente, connecteur Faluss.me ou projection Link n’est livré : les
services restent privés et la sandbox est réservée à l’administration test.

## Économie partagée

Token Engine, installé une seule fois sur l’instance économique, est le détenteur générique du ledger, des règles, des droits, de l’idempotence et des projections de solde. Il reçoit un `subject_id` opaque ; le Connector TE-02 peut lui fournir le `faluss_id` déjà actif sans répliquer l’identité. Les sites dérivés n’installent jamais le cœur. TE-03 ajoute seulement la demande privée d’une règle globale `daily_reward`, protégée par la permission distincte `reward.claim` : le Core décide, verrouille et inscrit le crédit. EC-02 ajoute des définitions de droits et des attributions historisées, lues seulement via `entitlements.read`; Catalogue porte la métadonnée de droit d’un thème et Faluss Link revalide la décision côté serveur pour chaque sélection de thème et chaque teaser visuellement réservé, sans conserver une copie locale. Cette couche teaser ne sécurise pas encore le fichier média. Faluss Link ne modifie jamais un ledger et ne garde aucune règle, solde, droit ou idempotence locale. Les contrats sont documentés dans `TOKEN_ENGINE.md` et `TOKEN_ENGINE_CONNECTOR.md`.

### Points Faluss PF-02A

PF-02A définit, sans l'implémenter, un futur ledger officiel Points Faluss à
classe économique fermée : `earned`, `funded` ou `promotional`. L'unité visible
reste PF, mais le moteur conserve l'origine afin que seuls les PF `funded`
puissent un jour financer un soutien créateur monétisable. Aucun PF ne devient
un retrait, un transfert membre, un revenu créateur, une valeur Hall of Fame ou
une métrique `progression.*`; le futur revenu euro et le score restent détenus
par Fans/Marketplace.

Le ledger PF sera append-only, idempotent et compensé par nouvelles écritures.
Il ne lit aucun solde ALB historique, Stripe, panier, commande, payout ou table
Fans. PF-02A n'ajoute aucun ledger, projection, lecture dans Portal/Master
Profile, route, table, migration, plugin ou comportement WordPress. Son contrat
et son schéma sont dans [`POINTS_FALUSS_CONTRACT.md`](POINTS_FALUSS_CONTRACT.md).

### Daily Rewards DR-01

DR-01 fixe seulement le futur read-model de délégation quotidienne. Les moteurs
Faluss Hub et Faluss Me restent les propriétaires de leurs décisions : `20 PF
earned` sous `hub.daily_accrual` et `75 PF earned` sous
`me.profile_daily_claim`, cumulables une fois par identité et jour
`Europe/Paris`, dans la limite actuelle de `95 PF earned`. Hub/Portal n'écrit
aucun PF, ne calcule aucune éligibilité et ne lit aucune table dérivée ; il ne
pourra que déléguer une action ou une navigation à un propriétaire.

Le document de statut est filtré pour le membre courant, frais et sans
`faluss_id`, e-mail, solde, paiement ou historique. Une absence reste une
absence de reward. DR-01 n'ajoute aucune route, action, card, UI, cache métier,
cron, table, migration ou comportement WordPress. Son contrat est dans
[`DAILY_REWARDS_CONTRACT.md`](DAILY_REWARDS_CONTRACT.md).

### Core PF-02B

PF-02B.1 porte Token Engine à `0.4.1` et conserve le sous-ledger InnoDB privé
`token_engine_pf_ledger`. C'est la seule source future de PF et il est séparé du
ledger générique `token_engine_ledger`, dont l'historique ALB reste strictement
intact. Le Core expose seulement une façade PHP interne pour les adaptateurs de
confiance ; aucune route Connector, lecture Portal, projection Master Profile,
écran, bouton ou cache client n'est créé.

Le Core impose les classes fermées, l'append-only, l'idempotence, une seule
compensation par écriture d'origine et l'absence de balance négative par classe.
Cette unicité est garantie par la façade transactionnelle et l'index PF unique
nullable ajouté par le schéma `5`. Packs, Fans, paiements, Stripe, retrait,
transfert, HOF, progression et `pf.summary` restent hors de cette livraison.

### Daily Reward Hub DR-02A

DR-02A active uniquement l'adaptateur local Faluss Portal de Faluss Hub. Après
la résolution canonique de la session membre liée et active, il appelle le
read-model `daily_status()` et, uniquement à la demande explicite POST/nonce du
membre, `claim_hub_daily()` du Core Token Engine `0.4.1` schéma `5`. Le Core est
l'unique auteur de l'entrée `20 PF` `earned` `daily_accrual` une fois par
`faluss_id` et jour logique `Europe/Paris`; Portal ne calcule ni le jour ni la
valeur, ne lit ni le ledger PF ni le ledger ALB, et ne crée aucune donnée ou
cache durable.

Le navigateur ne transmet qu'une intention fixe et son nonce. Il ne reçoit
jamais le `faluss_id`, une clé d'idempotence, un montant/solde, une classe, une
date, une référence de ledger, un e-mail ou une donnée de paiement. Les réponses
sont privées `no-store`, filtrées au document DR-01 lorsque le Core peut le
fournir, et ramenées à un état minimal contrôlé lors d'une indisponibilité.
Faluss Me et son gain `75 PF` restent sans adaptateur ni interface.

AP-02A conserve cette frontière économique : l'unique action active reste
`hub.daily_accrual`, affichée comme badge PF officiel avec le montant `20` dans
le composant glass commun des cards. Portal ne transmet toujours aucun montant,
classe, sujet, date ou clé d'idempotence du navigateur au Core. Les autres apps,
dont Faluss Me, n'affichent aucun gain supposé.

## Bibliothèque privée de découvertes

Mes découvertes est une donnée locale de Faluss Link, uniquement accessible au membre Faluss actif qui la possède. La route publique résout d’abord un profil Identity publié puis, seulement pour un autre membre connecté et ayant laissé l’enregistrement actif, actualise une paire de références d’identité côté serveur. Cette écriture ne contient ni contenu du profil ni donnée analytique de navigation. Elle n’est transmise à aucun Core, Connector, catalogue ou site tiers et n’est jamais exposée au propriétaire du profil découvert. Toute future analytique créateur doit être un système séparé, avec sa propre finalité et ses propres données ; elle ne peut pas dériver de cette bibliothèque.
