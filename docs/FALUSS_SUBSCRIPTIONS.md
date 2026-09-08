# Faluss Subscriptions — SUB-01A

## Responsabilités

**Faluss Subscriptions**, installé exclusivement sur `faluss.com`, est l’autorité
centrale des niveaux Gratuit/Pro, des essais, des attributions administratives,
de leur audit et de leur résolution déterministe. Son identifiant métier est le
`faluss_id` opaque de Faluss Identity ; il ne crée jamais de seconde identité.

| Système | Responsabilité | Hors responsabilité |
|---|---|---|
| Faluss Identity | identité, session, Faluss ID | abonnement, carte, droits produit |
| Faluss Subscriptions | niveau Gratuit/Pro, essai, cycle de vie, audit | paiement, session, données Link |
| Token Engine | ledger ALB, droits de thèmes et récompenses | abonnement, revenu, carte |
| Faluss Link / autres consommateurs | données et application locale d’un droit | calculer ou stocker l’abonnement |

SUB-01A calcule le niveau `faluss.pro`, mais aucun produit consommateur ne lui
est encore connecté : les liens, collections, réseaux et réglages existants de
Faluss Link restent gratuits et inchangés.

## Catalogue canonique v1

Le catalogue est versionné dans `Faluss_Subscriptions_Catalog` et il est
consultable, mais non éditable, dans l’administration.

| Clé | Nom public | Période | Prix TTC | Devise | Essai | Carte | Renouvellement | Vente |
|---|---|---:|---:|---|---:|---|---|---|
| `free` | Faluss Gratuit | — | 0 | EUR | 0 | non | non | actif |
| `pro` | Faluss Pro | mensuel | 999 centimes | EUR | 15 jours | obligatoire | automatique | préparé, non actif |
| `pro` | Faluss Pro | annuel | 9900 centimes | EUR | 15 jours | obligatoire | automatique | préparé, non actif |

Faluss Pro devient commercialement actif seulement dans SUB-01B, lorsque le
paiement, les preuves serveur et les avantages Pro minimaux seront livrés. Il
n’existe ni identifiant, ni prix, ni produit de fournisseur de paiement dans le
code de SUB-01A.

## États, sources et résolution

Les états normalisés sont `free`, `trialing`, `active`, `canceling`,
`past_due`, `suspended`, `expired`, `comped` et `revoked`.

La résolution est faite à la lecture, exclusivement en UTC, selon la version de
calcul indiquée dans chaque réponse. Elle retourne le niveau, le droit
`faluss.pro`, la source retenue, l’expiration et une raison non sensible.

1. une `compliance_override` active de refus/révocation gagne toujours ; le
   résultat est Gratuit ;
2. une attribution administrative Pro valide (`admin_grant`) gagne sur les
   sources positives et donne l’état `comped` jusqu’à son expiration ;
3. un essai `trialing` non expiré donne Pro jusqu’à son terme exact ;
4. un abonnement `active` ou `canceling` donne Pro jusqu’à la fin de période ;
5. `past_due` conserve Pro seulement jusqu’à la fin du délai de régularisation
   de sept jours ;
6. l’absence, l’expiration ou la suspension donnent Gratuit sans supprimer les
   données des produits consommateurs.

Les sources réservées au modèle sont `free`, `trial`, `subscription`,
`admin_grant`, `permanent_purchase`, `cosmetic_ownership` et
`compliance_override`. Les trois dernières ne deviennent actives qu’avec leurs
moteurs spécifiques : Pro n’inclut ni achat créateur, ni abonnement créateur,
ni jeton, ni cosmétique permanent, ni commission de Shop.

## Essais

Un essai est unique par `faluss_id` et par empreinte de moyen de paiement. La
table conserve uniquement une empreinte SHA-256 dérivée, jamais une carte, un
numéro, un e-mail ou un payload fournisseur.

`activate_verified_trial()` est un service interne pour le futur adaptateur de
paiement : il exige une référence de vérification serveur et une empreinte de
moyen de paiement. Il n’existe aucune route REST/AJAX, shortcode ou action
navigateur pour l’appeler. Deux verrous MySQL ordonnés, associés aux contraintes
uniques, empêchent une double activation concurrente. Une dérogation
administrative ne fait que marquer l’éligibilité : elle ne permet jamais de
démarrer un essai sans preuve de paiement vérifiée.

## Tables et migrations

Les tables utilisent le préfixe WordPress courant, InnoDB, colonnes et index
vérifiés strictement :

| Table | Rôle |
|---|---|
| `faluss_subscriptions` | références fournisseur, état normalisé, périodes, délai et version |
| `faluss_subscription_trials` | éligibilité et consommation unique de l’essai |
| `faluss_entitlements` | droits horodatés, source, priorité et version |
| `faluss_subscription_events` | idempotence des événements futurs avec empreinte de payload seule |
| `faluss_subscription_audit` | mutations sensibles avec états nettoyés et justification |

La migration v1 crée des tables temporaires, vérifie leur moteur, colonnes et
index, puis les promeut dans un unique `RENAME TABLE`. Elle est rejouable : un
schéma complet identique est simplement revalidé ; un schéma partiel ou divergent
échoue fermé et n’est ni supprimé ni réparé automatiquement. La vérification
tolère uniquement l’absence des anciennes largeurs d’affichage d’entiers dans
`SHOW FULL COLUMNS` (par exemple `bigint unsigned` au lieu de
`bigint(20) unsigned` sous MySQL 8) ; moteur, colonnes, nullabilité, index et
unicité restent vérifiés strictement. La désactivation du plugin ne supprime
aucune donnée ; aucune désinstallation destructive n’est fournie.

## Administration

La capacité `manage_faluss_subscriptions` est donnée seulement au rôle
`administrator` à l’activation et réparée sans élargissement de rôle à chaque
initialisation d’administration. Les actions sont toutes des POST authentifiés,
avec capacité, nonce, validation, échappement, audit et en-têtes no-cache :

- consulter le catalogue immuable, les états calculés, essais, événements,
  erreurs et audit ;
- rechercher par Faluss ID opaque ;
- attribuer temporairement Faluss Pro avec une justification et une expiration
  UTC obligatoires ;
- révoquer une attribution administrative avec justification ;
- enregistrer une dérogation d’éligibilité à l’essai ;
- relancer une vérification sûre de migration et diagnostic.

Une attribution, révocation ou dérogation n’est considérée comme réussie qu’après
écriture, relecture et audit dans la même transaction. Une erreur annule la
mutation et donne une notification locale, explicite et sans SQL, secret ni détail
interne. Les notifications PRG sont stockées brièvement par administrateur,
consommées une seule fois après redirection et ne placent ni résultat ni Faluss ID
dans l’URL. Le membre ciblé est repris depuis cette notification afin que le droit
calculé soit relu immédiatement.

Le champ `datetime-local` est saisi dans le fuseau du site WordPress puis converti
en UTC avant la persistance. L’écran Diagnostics expose pour chacune des cinq
tables son nom, moteur, volumes de colonnes et d’index, et son état de conformité,
sans divulguer d’erreur SQL.

L’administration ne permet jamais de modifier directement le statut d’un
fournisseur, les montants, une carte ou un abonnement fournisseur.

## Frontières des lots suivants

- **SUB-01B** : intégration de paiement côté serveur, création de la preuve de
  moyen de paiement et ingestion idempotente d’événements authentifiés.
- **SUB-01C** : portail client, résiliation et projections minimales validées.
- **SUB-01D** : connecteurs intersites de droits, avec protocole et consentement
  explicitement définis.
- **ONB-03** : éventuelle surface de choix/essai après SUB-01B ; aucun bouton
  ni bannière d’essai n’est livré ici.
- **FL-21** : consommation explicite d’un droit réellement disponible, sans
  toucher aux fonctions Link gratuites existantes.
