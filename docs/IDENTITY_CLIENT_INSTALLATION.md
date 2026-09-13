# Installation de Faluss Identity Client

Ne pas installer ce plugin sur `faluss.me`. Il est destiné aux applications clientes, après recette isolée.

1. Activez le plugin sur l’application cliente : les tables de liaisons et d’état SSO sont créées localement après vérification stricte, et la règle du callback est enregistrée puis purgée. La désactivation purgera également cette règle.
2. Dans **Réglages → Faluss Identity Client**, renseignez le `client_id`, les retours locaux exacts et gardez le flag désactivé jusqu’à validation.
3. Déclarez `https://site-client.example/faluss-identity/callback` comme URI HTTPS exacte dans Faluss Identity.
4. Pour un client confidentiel, définissez `FALUSS_IDENTITY_CLIENT_SECRET` côté serveur dans `wp-config.php` ou l’environnement. Le secret n’est ni dans Git ni dans les options WordPress.
5. Placez `[faluss_identity_client_button]` ou le widget **Continuer avec Faluss** sur une page existante, puis activez le flag après recette.

Le callback échange le code avec PKCE S256 puis ouvre uniquement une session WordPress locale. Si l’e-mail retourné existe sans liaison, aucune liaison automatique n’est créée : le membre doit d’abord ouvrir sa session locale puis utiliser le bouton pour lier explicitement son Faluss ID.

## Client officiel Faluss.com (FI-06)

Sur `faluss.com`, configurez uniquement l’URI de callback `https://faluss.com/faluss-identity/callback` et une liste réduite de destinations locales de portail (par exemple `https://faluss.com/mon-faluss/`). Le shortcode et le widget utilisent désormais l’action POST dédiée `faluss_identity_client_continue`; l’ancienne action `faluss_identity_client_start` reste compatible avec les intégrations existantes. Les deux exigent un nonce WordPress et créent le même state, verifier PKCE et cookie local protégé.

Après avoir enregistré ce client, cochez dans l’administration Identity de `faluss.me` le marqueur **Client officiel Faluss.com**. Ne l’activez pas pour `pro.faluss.com`, Date ou un tiers : ces clients continuent à présenter le consentement. Le bouton doit être rendu uniquement dans une surface qui requiert réellement une session ; il ne redirige pas automatiquement la page d’accueil publique. Une session locale Faluss.com déjà liée évite l’aller-retour OAuth ; une absence de session centrale affiche simplement le formulaire passwordless Identity sans envoi d’e-mail automatique.

Le client Faluss.com demande `identity.basic` dans tous les cas. Il demande aussi `identity.email` seulement dans le flux de connexion anonyme, car ce flux peut créer un premier utilisateur WordPress local lorsqu’aucune liaison Faluss ID n’existe. Le flux de liaison d’un utilisateur local déjà connecté demande uniquement `identity.basic`; l’e-mail n’est jamais un mécanisme de rapprochement automatique d’un compte local existant.

## Adaptateur Apps Registry (CAP-01B.2)

Identity Client 0.5.3 enregistre sur faluss.com la source fédérée fermée
`faluss-me` auprès de Faluss Apps Registry 0.2.0. Le manifeste public est lu
par Federation avec le pair `me-node`, l'application `faluss-me` et la version
`1.0.0`; aucune URL ni clé ne provient du navigateur ou du descripteur.

La relation membre réutilise exclusivement
`member_app_projection($faluss_id, 'me')`. Une projection AP-02A exacte,
publiée et dirigée vers `/mon-faluss` produit `active`; une absence confirmée
produit `not_linked`; une erreur de l'autorité locale omet Me sans inventer un
état. La projection validée reste en mémoire pour la requête PHP courante afin
que Portal réutilise sa destination canonique sans seconde lecture. Aucune
nouvelle table, migration, option, route ou donnée membre n'est ajoutée.

## Frontière produit ultérieure

Faluss Identity ne gère aucun abonnement, portefeuille ALB ou cosmétique. Faluss.me consommera plus tard des droits d’abonnement et un inventaire cosmétique propres à sa carte publique. Les objets obtenus avec ALB ou progression restent possédés ; les avantages inclus dans un abonnement restent temporaires.
