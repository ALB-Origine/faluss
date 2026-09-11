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

## Sous-ledger Points Faluss PF-02B

Token Engine `0.4.1` conserve le seul sous-ledger PF officiel
`token_engine_pf_ledger`. Il est distinct du ledger générique
`token_engine_ledger`, qui conserve intégralement son historique ALB : aucune
ligne ALB, configuration d'unité, table ni balance générique n'est lue,
modifiée, renommée ou convertie comme PF.

La migration de schéma `3` vers `4` est additive, verrouillée et fail-closed :
elle vérifie tout le schéma TE-03, crée seulement la table PF InnoDB avec les
index et unicités requis, puis vérifie de nouveau toutes les tables avant de
valider la version. Une installation neuve crée les tables historiques et la
table PF. Aucun backfill ou écritures PF de démonstration ne sont effectués à
l'activation.

`Token_Engine_Points_Service` est une façade PHP interne distincte. Elle accepte
seulement un `faluss_id` UUID v4, expose des balances dérivées par classe et
conserve les entrées append-only `earned`, `funded` et `promotional`. Aucun
endpoint REST, shortcode, action AJAX, formulaire, appel navigateur, cache
métier ou affichage Portal/Master Profile n'est ajouté.

Les seules écritures actives du cœur sont les deux daily rewards internes :
`faluss-hub` / `hub.daily_accrual` crédite `20 PF earned`, et `faluss-me` /
`me.profile_daily_claim` crédite `75 PF earned`. Le jour est calculé avec
`DateTimeImmutable` dans `Europe/Paris`, l'idempotence est dérivée par le Core
et les deux gains restent cumulables. Un adaptateur PHP propriétaire doit encore
fournir la preuve serveur d'identité active ; Faluss Me exige aussi carte publiée
et handle réservé. PF-02B ne livre aucun adaptateur, donc aucune app, card,
activation, connexion, cron ou page ne peut déclencher un claim à ce stade.

Packs, Fans, cosmétiques, ajustements manuels, paiements, Stripe, retrait,
transfert et projection `pf.summary` restent désactivés. Une compensation est
une primitive serveur future complète et unique : elle est liée à l'UUID
d'origine, conserve sa classe, ne peut pas rendre cette classe négative et ne
peut être écrite qu'une seule fois pour l'entrée d'origine. Le schéma `5`
impose aussi cette règle par l'index unique nullable
`pf_compensates_entry_unique`, après une migration `4 → 5` qui échoue sans
écriture si des doublons historiques non nuls sont détectés.

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

`Token_Engine_Connector_Access::rest_contract()` est l’unique contrat REST du Core. Sa base technique est produite par WordPress avec `rest_url( 'token-engine/v1/' )` pour les routes et diagnostics, mais n’est jamais une valeur de configuration Connector. Le Connector part de l’URL du site Core et tente d’abord `/wp-json/`, puis la forme WordPress `index.php?rest_route=` lorsqu’une réécriture n’est pas disponible. Le protocole actuel est la version `1` et expose les routes privées `POST /connector/token`, `GET /connector/diagnostic`, `POST /connector/balance`, `POST /connector/reward/offer`, `POST /connector/reward/status` et `POST /connector/reward/claim`. Elles exigent HTTPS, un projet actif et la permission minimale correspondante. Les jetons sont opaques, bornés au projet, expirent après cinq minutes et ne sont jamais placés dans une URL. Le diagnostic retourne aussi l’identité générique `token-engine`, la version de protocole et un identifiant non sensible. Aucune route connecteur ne crée une transaction, une règle ou un ajustement générique.

## Récompense quotidienne globale TE-03

TE-03 ajoute une exécution explicite de la règle globale `daily_reward`, sans créer de comportement à l’activation. L’administrateur crée et active lui-même cette règle dans **Règles** : portée `global`, déclencheur `claim`, périodicité `daily` et montant entier positif. Une règle globale conserve strictement `project_id = NULL` : l’administration indique alors « Toutes les surfaces autorisées ». Le projet authentifié par Connector identifie uniquement la surface émettrice autorisée et reçoit l’écriture du ledger ; il n’est jamais l’appartenance de la règle. Le code de l’unité et le fuseau horaire restent ceux de la configuration de l’instance ; aucune valeur de montant, d’unité ou de projet n’est codée dans le moteur.

Un connecteur doit conserver `wallet.read` puis recevoir explicitement la permission distincte `reward.claim` pour son projet. Le Core authentifie le connecteur, utilise son projet actif comme émetteur, vérifie la règle et calcule la fenêtre quotidienne dans le fuseau du Core. Il consulte puis inscrit le crédit dans le ledger atomique sous un verrou commun au sujet et à la règle : une même règle globale ne peut donc être accordée qu’une fois, y compris si deux surfaces autorisées la demandent simultanément. La clé d’idempotence est dérivée côté Core et aucun navigateur ne fournit un montant, une règle, un projet ou un `subject_id`.

Les routes privées supplémentaires sont `POST /connector/reward/offer`, `POST /connector/reward/diagnostic`, `POST /connector/reward/status` et `POST /connector/reward/claim`. Elles exigent HTTPS et une authentification de projet ; les routes d’offre, statut et gain renvoient explicitement `permission_denied` lorsque `reward.claim` manque. L’offre ne retourne que le montant et le code d’unité de la règle configurée, sans sujet et sans écriture. Le diagnostic est non mutatif : il vérifie le projet, la permission, la règle `daily_reward` et l’acceptation de sa portée globale, sans sujet ni ledger. Le statut et le gain retournent des états bornés : `granted`, `already_claimed`, `rule_unavailable`, `permission_denied`, `subject_unavailable`, `configuration_invalid` ou `transient_error` (et `available` avant la réclamation), ainsi que le montant, le code d’unité, la prochaine disponibilité et la projection de solde lorsqu’ils sont pertinents. Ils ne révèlent jamais d’information sur un autre sujet.

Dans l’instance Faluss, `ALB` reste une unité interne de ledger : il n’est ni achetable, ni convertible, ni associé à un prix par Token Engine. Les paiements, produits, entitlements, Premium, cosmétiques et droits d’accès restent hors de ce plugin.

## Droits centralisés EC-02

Le Core ajoute une administration native **Droits**. Une définition possède un code stable unique, un libellé, une surface/projet, un type générique `theme` et un état actif. Une attribution manuelle relie un `subject_id` opaque à une définition, avec une référence d’opération idempotente, des dates de début/fin facultative et une révocation historisée. Une attribution active équivalente n’est jamais dupliquée ; une révocation conserve son historique et une définition inactive, une attribution expirée ou révoquée ne peut plus être consommée.

`entitlements.read` est une permission distincte du client Connector. Les routes privées `GET /connector/entitlements/definitions` et `POST /connector/entitlement` ne retournent que les définitions compatibles ou la décision bornée pour un sujet et un droit ciblé ; elles ne modifient jamais le ledger. Le Core reste l’unique autorité des définitions, attributions et décisions. Il ne crée aucune attribution à l’activation.

## Limites hors EC-02

EC-02 ne fournit ni attribution automatique, wallet complet ou interface membre publique générale, boutique, paiement, Premium, cosmétique, progression, streak ou écriture distante arbitraire dans le ledger. Il ne dépend pas de WooCommerce, Elementor, Faluss Identity ou d’un autre plugin Faluss pour son cœur métier.
