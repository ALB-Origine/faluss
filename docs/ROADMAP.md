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
