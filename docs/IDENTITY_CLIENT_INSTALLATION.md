# Installation de Faluss Identity Client

Ne pas installer ce plugin sur `faluss.me`. Il est destiné aux applications clientes, après recette isolée.

1. Activez le plugin sur l’application cliente : les tables de liaisons et d’état SSO sont créées localement après vérification stricte, et la règle du callback est enregistrée puis purgée. La désactivation purgera également cette règle.
2. Dans **Réglages → Faluss Identity Client**, renseignez le `client_id`, les retours locaux exacts et gardez le flag désactivé jusqu’à validation.
3. Déclarez `https://site-client.example/faluss-identity/callback` comme URI HTTPS exacte dans Faluss Identity.
4. Pour un client confidentiel, définissez `FALUSS_IDENTITY_CLIENT_SECRET` côté serveur dans `wp-config.php` ou l’environnement. Le secret n’est ni dans Git ni dans les options WordPress.
5. Placez `[faluss_identity_client_button]` ou le widget **Continuer avec Faluss** sur une page existante, puis activez le flag après recette.

Le callback échange le code avec PKCE S256 puis ouvre uniquement une session WordPress locale. Si l’e-mail retourné existe sans liaison, aucune liaison automatique n’est créée : le membre doit d’abord ouvrir sa session locale puis utiliser le bouton pour lier explicitement son Faluss ID.

## Frontière produit ultérieure

Faluss Identity ne gère aucun abonnement, portefeuille ALB ou cosmétique. Faluss.me consommera plus tard des droits d’abonnement et un inventaire cosmétique propres à sa carte publique. Les objets obtenus avec ALB ou progression restent possédés ; les avantages inclus dans un abonnement restent temporaires.
