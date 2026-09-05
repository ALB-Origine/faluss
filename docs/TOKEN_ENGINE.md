# Token Engine — TE-01

## Rôle

Token Engine est un cœur WordPress générique, installé une seule fois pour une économie donnée. Dans l’écosystème Faluss, son instance cible sera `faluss.com`. Il n’existe aucun comportement, unité, projet, règle ou solde de démonstration à l’activation.

Le cœur ne connaît aucune identité WordPress : il reçoit un `subject_id` textuel, stable et opaque. Un futur connecteur Faluss lui passera un `faluss_id`, sans que ce plugin ne crée de compte ni ne lise de profil.

## Administration et configuration

Le menu **Token Engine** est réservé à `manage_options` et contient Configuration, Projets, Règles, Ledger et Ajustement manuel.

La configuration requiert un code d’unité en majuscules, ses libellés singulier et pluriel, et un fuseau horaire de référence. Tant qu’elle n’est pas complète, aucune écriture n’est acceptée. Après la première transaction, le code de l’unité est immuable ; les libellés restent administrables.

L’ajustement manuel utilise un UUID d’opération porté par le formulaire comme clé d’idempotence. Un renvoi du même formulaire retrouve donc l’écriture plutôt que d’en inscrire une seconde.

## Ledger et règles

Le ledger est la seule source de vérité : une ligne immuable est un crédit ou un débit d’un montant entier positif. Le solde est calculé comme les crédits moins les débits ; aucune balance modifiable n’est stockée. L’écriture est transactionnelle, idempotente et refuse un débit qui rendrait la projection négative.

Un projet représente une application autorisée. Une règle décrit seulement une politique future : elle peut être de portée `global` ou `project`, déclenchée par `event` ou `claim`, avec une périodicité `none`, `once`, `daily` ou `cooldown`. TE-01 n’exécute aucune règle automatiquement.

La portée `global` permettra par exemple à une règle quotidienne, affichée plus tard sur `faluss.me` et `pro.faluss.com`, de ne produire qu’un seul crédit dans toute l’économie. Une règle `project` sera réservée aux événements d’une application. L’éligibilité et l’ordonnancement de ces politiques sont explicitement hors TE-01.

## Façade PHP interne

Les intégrations doivent appeler `Token_Engine_Service`, jamais écrire directement dans les tables :

- `configuration()` et `configuration_is_valid()` ;
- `find_project()`, `active_projects()` et `find_rule()` ;
- `balance( $subject_id, $project_key )` ;
- `write_transaction( $values )` pour un crédit ou un débit idempotent.

`write_transaction()` attend notamment `subject_id`, `project_key`, `direction`, `amount` et `idempotency_key`; `transaction_uuid`, `rule_key`, `source_reference` et un objet de métadonnées borné restent optionnels. La façade ne fait aucune évaluation future de règle ou d’éligibilité.

## Accès connecteur TE-02

Un projet actif peut désormais générer un identifiant client public et un secret aléatoire. Le secret est affiché seulement dans la réponse d’administration qui le crée ou le régénère ; le Core conserve uniquement son empreinte vérifiable et une version de secret. La régénération invalide les jetons courts déjà émis et ne réactive jamais une permission révoquée.

Chaque projet affiche l’**URL du site Core** à copier dans le Connector, son client public et l’état de la seule permission TE-02, `wallet.read`. Cette URL provient de l’URL canonique WordPress du site, y compris un éventuel sous-répertoire ; elle ne contient jamais une racine REST. La première génération accorde la permission par défaut ; un administrateur peut ensuite la révoquer explicitement. Un client historique sans cette permission reste refusé avec un diagnostic précis jusqu’à ce qu’elle soit accordée.

`Token_Engine_Connector_Access::rest_contract()` est l’unique contrat REST du Core. Sa base technique est produite par WordPress avec `rest_url( 'token-engine/v1/' )` pour les routes et diagnostics, mais n’est jamais une valeur de configuration Connector. Le Connector part de l’URL du site Core et tente d’abord `/wp-json/`, puis la forme WordPress `index.php?rest_route=` lorsqu’une réécriture n’est pas disponible. Le protocole actuel est la version `1` et expose exclusivement trois routes privées : `POST /connector/token`, `GET /connector/diagnostic` et `POST /connector/balance`. Elles exigent HTTPS, un projet actif et la permission minimale `wallet.read`. Les jetons sont opaques, bornés au projet, expirent après cinq minutes et ne sont jamais placés dans une URL. Le diagnostic retourne aussi l’identité générique `token-engine`, la version de protocole et un identifiant non sensible. Aucune route connecteur ne crée une transaction, une règle ou un ajustement.

## Limites TE-02

TE-02 ne fournit ni attribution automatique, récompense quotidienne, wallet ou interface membre publique, boutique, paiement, entitlement, Premium, cosmétique, progression ou écriture distante dans le ledger. Il ne dépend pas de WooCommerce, Elementor, Faluss Identity ou d’un autre plugin Faluss pour son cœur métier.
