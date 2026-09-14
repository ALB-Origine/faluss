# Roadmap Faluss Identity

## FI-00 — Fondation documentaire

Valider les contrats, règles de sécurité et migration Altlab. Aucun flux actif.

## FI-01 — Plugin Faluss Identity

Créer le bootstrap, migrations vérifiées, tables Identity, profils `faluss_id` et diagnostic d'administration. Pas de SSO ni de formulaire public.

## FI-02 — Passwordless central

Adapter la cérémonie Altlab sous les noms Faluss, avec ses tests de concurrence et de rôles. Le seul résultat est une session locale sur `faluss.me` et un profil Identity actif.

## FI-03 — Profil public Faluss.me

Créer le profil public rattaché au Faluss ID : identifiant stable, nom, bio, avatar, publication et liens externes ordonnés. Aucun annuaire, recherche ni donnée métier.

## FI-04 — Autorisation et échange de code

Implémenter PKCE, `state`, code 60 secondes, consommation atomique et endpoint d'échange. Couvrir les refus client, URI, scope, PKCE, expiration et rejeu.

## FI-05 — Plugin client et intégration Altlab

Créer la liaison locale, la session WordPress locale et le parcours de connexion. L'activer d'abord dans Altlab derrière un drapeau de fonctionnalité. Aucun plugin de droits, Commerce ou Token Engine n'est modifié dans ce lot.

## FI-06 — Préparation Pro

Documenter l'export/import de liaison et des données Pro avant le changement de domaine. Réaliser la migration seulement après recette fonctionnelle complète.

## Hors périmètre initial

Facturation centralisée, dashboard Faluss.com, personnalisation complète de faluss.me, tableaux agrégés et intégration Date. Ils viennent après la validation FI-05.

## EC-01 — Contrat d’économie préparatoire

Documenter le moteur économique commun, la frontière Catalogue/Entitlements et une migration progressive depuis les règles Altlab observées, sans créer de ledger, de solde, de récompense ni de droit actif. Les prochains lots seront le moteur partagé, les entitlements, le daily reward Faluss Link puis les usages commerce et cosmétiques.

## TE-01 — Token Engine Core

Créer le cœur WordPress générique : configuration explicite d’unité, projets et règles administrables, ledger immuable, projections de solde et ajustements manuels idempotents. Il n’expose aucun endpoint, connecteur, wallet, récompense, paiement ou entitlement.

## TE-02 — Connecteur WordPress Faluss

Fournir le transport privé HTTPS, les identifiants de projet, le jeton court borné à `wallet.read`, le diagnostic et la lecture de solde. Le Connector résout un sujet Faluss actif ou un sujet générique par filtre. Aucun site consommateur ne peut écrire les tables du cœur.

## TE-03 — Daily reward Faluss Link

Le Core exécute désormais la règle globale configurable `daily_reward` pour un Connector explicitement autorisé par `reward.claim`. Faluss Link reste une surface : elle ne porte ni solde, ni règle, ni logique concurrente de récompense.

## EC-02 — Droits centralisés et thèmes Faluss verrouillables

Le Core porte les définitions et attributions historisées de droits de thème. Le Connector les lit uniquement avec `entitlements.read`; Catalogue référence seulement une définition active et Faluss Link revalide la décision côté serveur pour chaque sélection et rendu public. Aucun paiement, abonnement, boutique, entitlement local ou donnée métier n’est ajouté.

## FL-18 — Mes découvertes Faluss

Ajouter à Faluss Link une bibliothèque privée locale à `faluss.me` : un membre actif peut retrouver les cartes publiques d’autres membres qu’il a consultées. L’entrée est enregistrée côté serveur, est bornée par membre, reste invisible aux créateurs et ne contient aucune donnée de navigation ni snapshot de profil. Le widget et shortcode résident sur une page membre créée manuellement, jamais dans Studio. Un futur produit d’analytics créateur reste explicitement séparé et ne doit pas convertir cette bibliothèque en suivi de visiteurs.

## FL-19 — Teasers visuellement réservés

Faluss Link ajoute une présentation Public, Membre Faluss ou Droit requis aux teasers média. Le Connector relit la décision EC-02 côté serveur et tout échec ferme l’affichage visuel. Aucun fichier média n’est encore protégé, aucun achat, token, abonnement, paiement ou entitlement local n’est créé : la livraison réellement protégée reste un lot de moteur de contenu futur.

## FI-07.2 — Retour du déclencheur de navigation

Après fermeture pointer de la navigation portaled, le déclencheur retourne immédiatement à ses couleurs Normal configurées. Une fermeture clavier conserve le focus visible et les interactions existantes, sans règle globale sur le header, les cartes ou les popups tiers.

## ONB-01 — Fondation de l’onboarding de carte

Après une identité passwordless active, un membre choisit explicitement de créer son Faluss ou de continuer sans carte. Le flux `/commencer` garde un état minimal reprenable sur le profil Identity, réserve atomiquement l’identifiant public FI-03 et renvoie ensuite vers le Studio existant. Les préférences Faluss Link, les entitlements, les contenus et les paiements restent hors de ce lot ; ONB-02 les complétera seulement après une réservation réussie.

## ONB-02 — Création guidée de carte

Après la réservation ONB-01, la page Elementor d’onboarding devient un assistant repris à la dernière étape : nom, avatar facultatif, en-tête, style, réseaux, liens puis publication explicite. Il réutilise les tables et le rendu de Faluss Identity et Faluss Link ; aucun profil, média, lien ou préférence n’est dupliqué. Un brouillon n’est jamais public et la finalisation idempotente ouvre ensuite Studio Faluss.

## SUB-01A — Fondation centrale Gratuit/Pro — livré

Faluss Subscriptions est installé sur `faluss.com` comme autorité centrale du
catalogue Gratuit/Pro, des essais, des droits de niveau, des attributions
administratives, des audits et diagnostics. SUB-01A ne livre ni paiement, ni
Checkout, ni webhook, ni portail client, ni bannière, ni consommation de droit
dans Faluss Link. Voir [`FALUSS_SUBSCRIPTIONS.md`](FALUSS_SUBSCRIPTIONS.md).

## SUB-01B — Stripe central Faluss Pro — livré techniquement en mode test

Faluss Subscriptions embarque maintenant l’adaptateur Stripe PHP épinglé, le
Customer central, Checkout/Customer Portal privés, validation TTC des Prices,
webhooks signés et idempotents, reprise par relecture Stripe, réconciliation et
administration sandbox test. Aucun appelant membre, bouton public ou consommateur
de droit Faluss Link n’est activé par ce lot.

## SUB-01C à SUB-01D — Abonnements — à réaliser

SUB-01C définira l’appelant membre authentifié, les cycles Hub et une recette
Stripe réelle. SUB-01D définira les connecteurs intersites de droits, sans
dupliquer identité ou abonnement.

## MP-01A — Universal Profile Contract — livré contractuellement

Définir le Master Profile comme projection fédérée et les enveloppes de modules
versionnées `identity.core`, `apps.registry`, `subscriptions.private`,
`pf.summary`, `progression.global`, `cosmetics.equipped`, `date.*`,
`fans.creator` et `hof.score`. Le contrat fixe propriété, audiences, états vides,
fraîcheur, mode `ghost_until`, namespaces de métriques, actions déléguées et
compatibilité. Il ne livre aucun Master Profile visible, plugin, route, écran,
table, migration, donnée membre, ZIP ou changement de comportement WordPress.

Les lots ultérieurs devront d'abord définir le read-model spécialisé de leur
moteur et prouver son filtrage. Ils ne pourront brancher une projection au
Master Profile qu'après validation d'un transport privé distinct et sans lecture
directe de table, duplication de source, fusion de scores ou exposition du
`faluss_id` technique.


## CAP-01A — Contrat du registre et des capacités — livré contractuellement

CAP-01A réserve l'autorité faluss-apps-registry, le read-model spécialisé
apps.registry, les états fermés d'application, relation et capacité, les
interfaces fermées et les emplacements contextuels v1. Les deux schémas
machine-readable définissent un manifeste d'application sans identité membre et
un read-model membre filtré, avec fraîcheur, compatibilité, bindings actifs et
actions déléguées. Portal et AP-02A conservent leurs mécanismes actuels : aucun
registre runtime ni remplacement de décision codée en dur n'est livré.

CAP-01A ne crée aucun runtime, plugin, transport, endpoint, écran, table,
migration, asset, ZIP ou changement de comportement WordPress. Il ne livre ni
Fans, Shop, Cosmetics, Analytics, Quêtes ni Progression, et ne corrige pas le
défaut mobile reporté DR-02A.2 de Portal 0.1.20.

L'ordre de livraison conservé est :

1. CAP-01A — contrat du registre et des capacités ;
2. FED-01A — contrat du transport privé fédéré ;
3. FED-01B — runtime plugin du transport privé fédéré ;
4. CAP-01B — registre runtime et projection réelle apps.registry ;
5. EVT-01A — contrat commun des événements, sans runtime ;
6. EVT-01B.1/1.1 — validateurs, lecture signée et liaison du catalogue au nœud, sans provider réel ;
7. EVT-01B.2A — cœur persistant : catalogues et événements append-only, canonicalisation, outbox, inbox et deliveries inactives ;
8. EVT-01B.2B — `event.publish`, leases, transport Federation et workers ;
9. AN-01A — contrat Analytics et payloads Hub/Me, sans runtime ;
10. AN-01B — catalogues et providers Hub/Me, politiques explicites, premiers événements réels et premier consommateur Analytics ;
11. MP-01B ;
12. COS-01 ;
13. SHOP-01 ;
14. intégrations contextuelles Faluss.me ;
15. Quêtes et Progression.

SUB-01C et SUB-01D, le Daily Reward Faluss Me 75 PF, Fans, Date, Shop et Hall
of Fame comme moteurs propriétaires futurs, ainsi que le staging opérationnel,
restent à réaliser et ne sont pas déclarés livrés par CAP-01A.

## EVT-01A — Contrat commun des événements — livré contractuellement

EVT-01A livre les schémas Draft 2020-12 fermés de l'enveloppe `faluss.event`
1.0.0 et du catalogue `faluss.event-source-catalog` 1.0.0. Il fixe le fait
métier post-commit, les sources namespacées, les contextes sujet/acteur/objet,
les trois destinations fermées, les payloads spécialisés, les données
interdites, l'append-only, le retry par hash canonique et la séparation entre
réception et effets consommateurs.

Les types Hub `portal.viewed`, `app.opened`, `daily-reward.claimed` et Me
`card.viewed`, `link.clicked`, `collection.opened` sont seulement réservés.
EVT-01A ne crée aucun plugin, runtime, transport Federation, bus, table,
migration, endpoint, outbox, inbox, queue, cookie, tracking, événement réel,
Analytics, Quête, Progression ou comportement WordPress. EVT-01B.1 apporte les
validateurs génériques et l'échange de catalogues. EVT-01B.2A apporte le cœur
persistant ; EVT-01B.2B livre transport et workers avant qu'AN-01 apporte
catalogues propriétaires, providers Hub/Me, validateurs de payload,
politiques et premiers événements.

## EVT-01B.1 — Runtime des catalogues d'événements — livré techniquement

Faluss Events `0.1.1` fournit les validateurs PHP de production du catalogue et
de l'enveloppe EVT-01A, un registre fermé de providers futurs et la validation
croisée avec le manifeste CAP accepté. Faluss Federation `0.2.0`, schéma `1`,
ajoute la seule lecture `event_catalog.read` avec politique explicite, signature,
fraîcheur, anti-rejeu, débit et audit minimal inchangés.

Le catalogue est lié cryptographiquement au destinataire authentifié : ses
`node_id` et `app_key` doivent correspondre exactement au nœud et à
l'application de la requête Federation.

Aucun catalogue/provider Hub ou Me, événement, tracking, Analytics, table,
migration, outbox, inbox, worker, cookie ou politique réelle n'est livré par
EVT-01B.1. `event.publish` reste absent. AN-01 reste nécessaire avant toute
activation métier.

## EVT-01B.2A — Cœur persistant du moteur — livré techniquement

Faluss Events `0.2.1`, schéma `1`, matérialise exactement cinq tables InnoDB :
catalogues acceptés, événements, outbox, inbox et deliveries consommateurs. Le
moteur canonicalise et hache les documents, sérialise les identités et
`event_id` concurrents, refuse les divergences et écrit chaque événement avec
toutes ses lignes opérationnelles dans une transaction unique.

Les routes et consommateurs sont des registres PHP fermés sans wildcard. Aucun
descriptor réel n'est enregistré, aucun callback n'est exécuté et les cinq
tables restent vides après installation. Aucun endpoint, événement réel,
tracking ou donnée métier n'est ajouté.

EVT-01B.2A.1 aligne avant installation les limites des deux schémas JSON et des
validateurs PHP sur le stockage existant : 512 caractères pour les clés
namespacées, 128 pour les clés consommateur internes et 32 pour les versions
sémantiques EVT. Le DDL du schéma 1 reste inchangé, sans migration.

## EVT-01B.2B — Transport et workers — livré techniquement

Faluss Events `0.3.0` et Federation `0.3.0`, schémas `1`, ajoutent l'opération
fermée `event.publish`, son accusé signé, les leases outbox/consumer, les routes
locales atomiques, deux workers Cron bornés et huit tentatives déterministes.
Le DDL Events et les quatre tables Federation restent inchangés. Aucun provider,
catalogue, événement, route ou consommateur métier n'est enregistré. AN-01 reste
nécessaire avant toute activation métier, puis l'ordre demeure MP-01B, COS-01,
SHOP-01, intégrations contextuelles Faluss.me, Quêtes et Progression.

## AN-01A — Contrat Analytics et premiers événements — livré contractuellement

AN-01A ferme les six payloads `faluss-hub.portal.viewed`,
`faluss-hub.app.opened`, `faluss-hub.daily-reward.claimed`,
`faluss-me.card.viewed`, `faluss-me.link.clicked` et
`faluss-me.collection.opened`. Les capacités futures sont
`faluss-hub.events` sur `hub-node` et `faluss-me.events` sur `me-node`; la seule
destination est `analytics.events`.

Le lot réserve `faluss-analytics` sur Hub, sa clé consommateur
`faluss-analytics.aggregate-v1` et le read-model privé `analytics.summary`
1.0.0. Les vues brutes sont distinguées des visiteurs uniques, obligatoirement
`not_supported` en v1. Rétention, suppression, absence d'identité visiteur et
politique Federation exacte sont contractuelles.

AN-01A ne crée aucun plugin, table, migration, catalogue/provider installé,
politique réelle, route, cookie, tracking, worker supplémentaire, événement,
écran, graphique, ZIP ou comportement WordPress. AN-01B reste nécessaire pour
toute activation réelle ; MP-01B reste postérieur et le module Analytics n'y
est pas activé par ce lot.

## FED-01A — Contrat du transport privé fédéré — livré contractuellement

FED-01A réserve le seul échange privé bidirectionnel entre nœuds Faluss
explicitement approuvés. Il fixe initialement les trois lectures fermées, HTTPS canonique,
Ed25519, clés locales hors Git et options WordPress exportables, politique
exacte, canonicalisation du corps, anti-rejeu atomique et réponses privées
bornées. Il n'installe aucune route, clé, table, migration, option, plugin,
cache, UI, donnée membre, asset ou transport runtime. FPR, Identity, Token
Connector, Stripe/Subscriptions et les mutations métier restent exclus.

FED-01B est livré techniquement par le plugin Federation `0.1.0`, avec transport
Ed25519, politique locale, anti-rejeu et seul diagnostic intégré. CAP-01B ne
pourra ensuite résoudre apps.registry qu'à partir de manifestes signés acceptés,
sans ajouter une application, capacité, binding ou action absente du manifeste.

## FED-01B — Runtime privé Faluss Federation — livré techniquement

Faluss Federation `0.1.0`, schéma `1`, ajoute le seul receiver privé
`/wp-json/faluss-federation/v1/exchange`, le client PHP fermé, les clés
publiques et politiques de pairs, le contrôle anti-rejeu transactionnel et
l'audit technique borné. Sodium ou toute constante locale manquante maintient
le runtime fermé sans fatal ni appel réseau. Seul `diagnostic.read` est produit
en 0.1.0 ; `manifest.read` et `read_model.read` retournent `not_available`
sans provider propriétaire enregistré. Aucun registre CAP-01B, manifeste Hub/Me,
read-model membre, mutation, paiement, entitlement ou transport des autres
moteurs n'est ajouté.

## PF-02A — Contrat Points Faluss et droits économiques — livré contractuellement

Le contrat PF réserve un futur ledger append-only à classes `earned`, `funded`
et `promotional`, les catégories de gains, packs, soutien, cosmétiques et
compensation, ainsi que la frontière avec Fans/Marketplace, Hall of Fame,
Progression, Portal et Master Profile. Il ne crée aucun solde, table, migration,
route, paiement, Stripe, plugin, ZIP ou comportement WordPress. Les règles
exactes de `profile_daily_claim` (75 PF) et `daily_accrual` (20 PF) sont
désormais portées par DR-01. PF-02B livre leur Core interne dans un sous-ledger
PF séparé, tout en restant bloqué sur les autres capacités PF et sur tout
adaptateur d'app.

## DR-01 — Contrat fédéré des Daily Rewards Faluss — livré contractuellement

DR-01 fixe la période quotidienne serveur `Europe/Paris`, sans rattrapage, et
les deux rewards cumulables : Hub `20 PF earned` (`hub.daily_accrual`) et
Faluss Me `75 PF earned` (`me.profile_daily_claim`), pour un maximum de
`95 PF earned`. Il définit le read-model de statut filtré et la délégation
exclusive au moteur propriétaire ; Hub/Portal ne crédite ni ne simule jamais un
succès. Le lot ne produit aucune route, table, ledger, migration, cron, UI
Portal, plugin, ZIP, paiement, Stripe ou comportement WordPress.

## PF-02B — Core réel du ledger Points Faluss — livré techniquement

Token Engine `0.4.0` ajoute, par migration additive et vérifiée, la seule table
PF `token_engine_pf_ledger`; le ledger générique ALB reste inchangé. La façade
PHP interne applique classes fermées, append-only, idempotence, compensation de
même classe et solde dérivé non négatif. Elle connaît les deux daily rewards
`20 PF` Hub et `75 PF` Faluss Me en `Europe/Paris`, sans rattrapage, mais aucun
adaptateur, UI, Portal, route, cron, paiement, Stripe, pack, Fans, retrait ou
projection `pf.summary` n'est livré.

## PF-02B.1 — Unicité de compensation du ledger PF — livré techniquement

Token Engine `0.4.1` porte le schéma à `5`. La migration additive `4 → 5`
vérifie d'abord l'absence de doublon non nul, puis remplace uniquement l'index
PF de recherche par l'index unique nullable `pf_compensates_entry_unique` sur
`compensates_entry_uuid`. La façade refuse explicitement toute seconde
compensation d'une même écriture, tout en préservant le retry strictement
identique. Aucun ledger ALB, adaptateur, claim, route, UI ou capacité PF
supplémentaire n'est modifié.

## DR-02A — Gain quotidien Faluss Hub réel — livré techniquement

Faluss Portal `0.1.16` délègue désormais le premier claim réel vers le Core PF
Token Engine `0.4.1`, schéma `5`. Seul Hub est actif : une identité Faluss
active peut demander explicitement `20 PF earned` `daily_accrual`, une fois par
jour serveur `Europe/Paris`. Portal ne produit aucune écriture PF, ne montre
aucun solde ni historique, et ne conserve aucun état durable ; le Core décide
l'idempotence, la date et l'unique entrée append-only. Faluss Me, son claim de
`75 PF`, les prérequis de carte/handle et toute liaison inter-sites restent non
implémentés.

## AP-02A — Mes Apps cohérent et action PF intégrée — livré techniquement

Faluss Portal `0.1.17` consomme désormais la projection minimale de carte
publiée émise par Faluss Identity `0.4.14` pendant le SSO et validée par Faluss
Identity Client `0.5.2`. Une carte publiée active immédiatement Faluss Me dans
Mes Apps et Explorer réutilise sa destination membre ; Faluss Link `0.3.14`
envoie réciproquement la card Hub vers `faluss.com/mon-faluss`. Toutes les cards
Mes Apps conservent la zone circulaire glass historique. Seul Hub y affiche le
badge PF officiel et `20` avec son claim existant ; Faluss Me n'obtient ni
`75 PF`, ni adaptateur, ni écriture. Aucun endpoint, table, migration ou
changement Token Engine n'est ajouté.

## FPR-01 — Mise en production Faluss — livré techniquement

Le plugin isolé **Faluss Production Reset** `0.1.0` coordonne un unique reset
pré-lancement de `faluss.me` vers `faluss.com`. Il exige un armement explicite
des deux administrations, `manage_options`, nonce, phrase de confirmation,
préflight signé et une constante secrète hors Git dans les deux `wp-config.php`.
Il supprime uniquement les membres non privilégiés, leurs données Identity,
Link ou Identity Client listées et leurs médias WordPress non ambigus, en
préservant les administrateurs WordPress, les clients SSO, les réglages et le
ledger ALB. Le PF ledger doit être vide et n'est jamais modifié. Après succès
ou échec partiel, les deux sites se verrouillent sans retry automatique ni
rollback simulé. Voir [`FALUSS_PRODUCTION_RESET.md`](FALUSS_PRODUCTION_RESET.md).

## SUB-03 — Faluss Plus — prérequis produit réservé

Faluss Plus est prévu à `3,99 € / mois`, sans engagement et sans gain direct de
Points Faluss, quelle que soit leur classe. Cette décision est un prérequis de
SUB-03 uniquement : PF-02A ne modifie ni Faluss Subscriptions, ni Stripe, ni
prix, Checkout, entitlement ou paiement réel.
