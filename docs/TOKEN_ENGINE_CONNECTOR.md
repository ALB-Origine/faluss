# Token Engine — connecteurs futurs

## Frontière

Le cœur Token Engine reste unique sur `faluss.com`. Le plugin autonome **Token Engine Connector** s’installe sur `faluss.me`, `pro.faluss.com`, `date.faluss.com` ou tout WordPress compatible. Il ne crée aucune table de ledger, règle, solde ou attribution locale : le Core conserve le ledger et l’idempotence.

Dans l’écosystème Faluss, un connecteur pourra fournir le `faluss_id` comme `subject_id`. Ce couplage appartient au connecteur, pas au cœur : Token Engine ne crée ni utilisateur WordPress ni identité Faluss, et ne déduit jamais un sujet à partir d’un e-mail.

## Configuration et lecture TE-02

Un administrateur configure dans Connector l’URL HTTPS exacte du Core, l’identifiant public, le secret et la clé de projet. Le secret est chiffré au repos avec les clés de l’instance, n’est jamais réaffiché, et sert seulement à obtenir un jeton court en mémoire. Le test de connexion donne un diagnostic non sensible ; ni secret, ni jeton, ni valeur de sujet n’y apparaissent.

Le Core accepte seulement la permission `wallet.read` en TE-02. Le Connector échange les identifiants via HTTPS, appelle le diagnostic ou la lecture de solde avec un jeton `Bearer` dans l’en-tête, puis oublie ce jeton. Il ne possède aucune route front, shortcode, widget ou wallet.

## Sujet

Le filtre documenté `token_engine_connector_subject_id` reçoit le sujet proposé et l’utilisateur WordPress courant. Sans valeur valide, le Connector n’effectue aucune requête de solde. Lorsqu’il détecte Faluss Identity, l’adaptateur optionnel récupère seulement un `faluss_id` déjà actif ; il ne crée ni profil, ni compte, ni session. Une autre application fournit son propre sujet stable via le filtre.

## Façade PHP interne

Les intégrations locales appellent `Token_Engine_Connector_Service`, jamais une table ou un endpoint front :

- `is_configured()` vérifie la configuration protégée ;
- `current_subject_id()` résout le sujet courant sans utiliser un `wp_user_id` comme solde central ;
- `test_connection()` effectue le diagnostic non sensible ;
- `balance_for_current_subject()` lit le solde central seulement si un sujet valide est disponible.

## Règles d’intégration

- Un connecteur ne lit ni n’écrit directement les tables `token_engine_*`.
- Il utilise les routes privées versionnées du Core pour le diagnostic et la lecture ; il ne peut pas écrire dans le ledger.
- Il fournit une clé d’idempotence stable par événement métier et conserve sa propre preuve d’authentification.
- Les droits, paiements, abonnements, produits et contenus restent dans leurs moteurs respectifs ; le cœur ne retourne qu’une opération ou une projection de solde.

## Suite prévue

**TE-03** pourra afficher une récompense quotidienne dans Faluss Link. Cette interface demandera une décision au moteur commun ; elle ne possédera ni solde, ni règle, ni logique de réclamation concurrente. Les gains, règles, paiements, tokens et entitlements continuent d’appartenir aux moteurs communs de l’écosystème.
