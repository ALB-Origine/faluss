# Token Engine — connecteurs futurs

## Frontière

Le cœur Token Engine reste unique sur `faluss.com`. Le plugin autonome **Token Engine Connector** s’installe sur `faluss.me`, `pro.faluss.com`, `date.faluss.com` ou tout WordPress compatible. Il ne crée aucune table de ledger, règle, solde ou attribution locale : le Core conserve le ledger et l’idempotence.

Dans l’écosystème Faluss, un connecteur pourra fournir le `faluss_id` comme `subject_id`. Ce couplage appartient au connecteur, pas au cœur : Token Engine ne crée ni utilisateur WordPress ni identité Faluss, et ne déduit jamais un sujet à partir d’un e-mail.

## Configuration et lecture TE-02

Un administrateur copie dans Connector uniquement l’**URL HTTPS du site Core** affichée par le projet du Core, par exemple `https://www.faluss.com`, puis renseigne l’identifiant public, le secret et la clé de projet. Un sous-répertoire WordPress est accepté. Connector refuse HTTP, query string, fragment, userinfo et tout endpoint REST saisi manuellement. Il conserve le domaine canonique, y compris `www` lorsqu’il est configuré par le Core.

Après une sauvegarde, Connector vérifie immédiatement qu’il peut relire le secret protégé. L’écran indique uniquement **Secret enregistré** ou **Secret requis**. Une sauvegarde sans secret conserve une valeur déjà vérifiée ; si le stockage protégé n’est pas disponible ou ne peut pas être relu, la sauvegarde est refusée et la configuration précédente reste intacte. Après une régénération du secret sur le Core, l’administrateur doit remplacer manuellement le secret local.

Le Core accepte seulement la permission `wallet.read` en TE-02. À partir de l’URL de site enregistrée, Connector tente les trois routes enfants — `connector/token`, `connector/diagnostic`, `connector/balance` — avec `/wp-json/`, puis utilise automatiquement `index.php?rest_route=` si la route réécrite est absente. La forme REST détectée est dérivée en mémoire, jamais saisie ni persistée comme une seconde URL. Il échange les identifiants via HTTPS, appelle le diagnostic ou la lecture de solde avec un jeton `Bearer` dans l’en-tête, puis oublie ce jeton. Il exige l’identité Core `token-engine` et la version de protocole `1` avant de valider la connexion. Il ne possède aucune route front, shortcode, widget ou wallet. Son écran de diagnostic distingue l’URL du site, le mécanisme REST détecté, la route, le Core/version, les credentials, la permission et le jeton court ; le diagnostic Faluss est disponible séparément seulement après une connexion Core validée. Les messages et leurs identifiants de diagnostic ne contiennent jamais le corps d’une réponse distante, un secret, un jeton, un en-tête ou la valeur du sujet.

## Migration TE-02.4

À la mise à jour, Connector convertit automatiquement et seulement lorsque la forme est certaine les anciennes bases `https://hote/wp-json/token-engine/v1/`, `https://hote/sous-dossier/wp-json/token-engine/v1/` et `https://hote/index.php?rest_route=/token-engine/v1/` vers l’URL de site correspondante. Le `client_id`, la clé projet, le secret chiffré local et les droits existants sont conservés : aucune régénération n’est requise.

## Sujet

Une fois les plugins WordPress complètement chargés, le connecteur utilise d’abord `Faluss_Identity_Registry::get_active_for_wp_user()` pour le compte WordPress courant. Si cette interface publique n’existe pas, son seul repli est une lecture stricte du schéma Faluss Identity : profil correspondant au `wp_user_id`, statut `active`, puis `faluss_id` valide. Il ne crée, n’active, ne modifie ni ne répare aucun profil. Le filtre documenté `token_engine_connector_subject_id` reçoit ensuite ce sujet proposé et l’utilisateur WordPress courant. Sans valeur valide, le Connector n’effectue aucune requête de solde ; il n’emploie jamais le `wp_user_id` comme sujet central.

## Façade PHP interne

Les intégrations locales appellent `Token_Engine_Connector_Service`, jamais une table ou un endpoint front :

- `is_configured()` vérifie la configuration protégée ;
- `current_subject_id()` résout le sujet courant sans utiliser un `wp_user_id` comme solde central ;
- `core_connection_test()` (ou l’alias historique `test_connection()`) effectue le diagnostic Core non sensible et indépendant du sujet ;
- `faluss_subject_diagnostic()` vérifie séparément la disponibilité d’un sujet et retourne seulement une empreinte tronquée non réversible pour le diagnostic d’administration ;
- `balance_for_current_subject()` lit le solde central seulement si un sujet valide est disponible.

## Règles d’intégration

- Un connecteur ne lit ni n’écrit directement les tables `token_engine_*`.
- Il utilise les routes privées versionnées du Core pour le diagnostic et la lecture ; il ne peut pas écrire dans le ledger.
- Il fournit une clé d’idempotence stable par événement métier et conserve sa propre preuve d’authentification.
- Les droits, paiements, abonnements, produits et contenus restent dans leurs moteurs respectifs ; le cœur ne retourne qu’une opération ou une projection de solde.

## Suite prévue

**TE-03** pourra afficher une récompense quotidienne dans Faluss Link. Cette interface demandera une décision au moteur commun ; elle ne possédera ni solde, ni règle, ni logique de réclamation concurrente. Les gains, règles, paiements, tokens et entitlements continuent d’appartenir aux moteurs communs de l’écosystème.
