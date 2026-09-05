# Token Engine — connecteurs futurs

## Frontière

Le cœur Token Engine reste unique sur l’instance économique. Chaque site consommateur recevra plus tard un connecteur léger : le cœur conserve le ledger et l’idempotence ; le connecteur authentifie le sujet et traduit un événement local validé en appel vers l’intégration centrale autorisée.

Dans l’écosystème Faluss, un connecteur pourra fournir le `faluss_id` comme `subject_id`. Ce couplage appartient au connecteur, pas au cœur : Token Engine ne crée ni utilisateur WordPress ni identité Faluss, et ne déduit jamais un sujet à partir d’un e-mail.

## Règles d’intégration

- Un connecteur ne lit ni n’écrit directement les tables `token_engine_*`.
- Il utilise une interface protégée définie par le lot d’intégration, puis la façade PHP du cœur côté instance économique.
- Il fournit une clé d’idempotence stable par événement métier et conserve sa propre preuve d’authentification.
- Les droits, paiements, abonnements, produits et contenus restent dans leurs moteurs respectifs ; le cœur ne retourne qu’une opération ou une projection de solde.

## Suites prévues

**TE-02** réalisera le connecteur WordPress et l’authentification d’un sujet Faluss. Il définira le transport protégé, les autorisations et la reprise idempotente entre une application et le cœur.

**TE-03** pourra afficher une récompense quotidienne dans Faluss Link. Cette interface demandera une décision au moteur commun ; elle ne possédera ni solde, ni règle, ni logique de réclamation concurrente.
