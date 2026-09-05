# EC-01 — Contrat d’économie Faluss

## Statut

Ce document prépare une frontière d’intégration. EC-01 ne crée aucun plugin d’économie, table, endpoint, solde, balance ou attribution. ALB est une unité virtuelle interne : elle ne peut pas être achetée avec de l’argent réel, convertie en argent, ni présentée comme un actif financier.

## Faits Altlab observés

- `ALB-Origine/altlabPlatform/docs/ROADMAP.md` décrit une phase de noyau multiproduit fondée sur un schéma d’acquisition et de droit, des événements idempotents et un service commun ; sa phase Score prévoit un registre d’événements.
- Le même document place abonnements, achievements, récompenses et progression détaillée dans « Plus tard ».
- `ALB-Origine/altlabPlatform/docs/CURRENT_SYSTEM.md` indique explicitement qu’aucun suivi de progression n’est introduit par le parcours d’accès courant.
- Le repérage borné des noms de composants sous `ALB-Origine/altlabPlatform/plugin/` n’a exposé que `altlab-member` et `formation-modulaire-elementor` ; aucun composant nommé Token Engine, ALB, wallet ou reward n’a été trouvé dans ce périmètre de lecture.

## À confirmer

- L’emplacement, le schéma et les invariants exacts d’un éventuel Token Engine/ALB hors du périmètre de lecture borné.
- Les événements économiques Altlab déjà émis, leurs clés d’idempotence, leurs politiques de correction et leurs données historiques.
- Les règles de progression, de récompense, de droits Premium et les paramètres administrables réellement attendus par chaque dérivé.

## Contrat cible Faluss

Le **Faluss ID** est la seule identité transversale. Aucun dérivé ne crée son propre compte membre. Le moteur partagé doit accepter un événement métier de `faluss-link`, `pro` ou `date`, puis décider centralement son effet. Une transaction immuable comporte au minimum :

| Champ | Rôle |
| --- | --- |
| `faluss_id` | identité stable concernée |
| périmètre applicatif | `faluss-link`, `pro` ou `date` |
| événement métier | fait local validé par le dérivé |
| transaction immuable | écriture du ledger décidée par le moteur |
| montant ALB | variation interne, jamais monétaire |
| clé d’idempotence | empêche toute attribution répétée |
| référence source | origine métier vérifiable |
| horodatage | moment de la décision ou de l’événement |
| décision de règles | règle/version/raison appliquée |

Les dérivés émettent le fait métier et rendent leurs états locaux ; ils ne réimplémentent ni ledger, ni calcul de solde, ni règle d’attribution. Les récompenses et paramètres d’attribution pourront être administrés par dérivé, mais seront évalués par ce moteur unique.

## Frontières de responsabilité

| Composant | Responsabilité |
| --- | --- |
| Faluss Identity | identité et session |
| Futur Faluss Economy | ledger, règles, idempotence, solde |
| Faluss Catalog | définition des cosmétiques et thèmes |
| Futur Entitlements | droits obtenus et consommation |
| Dérivés | événements métier et rendu local |

Faluss Catalog définit les thèmes et cosmétiques ; les entitlements futurs décident de leur disponibilité. Faluss Link ne rend que ce résultat et n’écrit jamais une balance.

## Daily reward futur

Un visiteur sans Faluss ID sera orienté vers la création passwordless. Un membre identifié pourra demander une opération au moteur partagé selon les règles centrales. Faluss Link ne créera ni token, ni balance, ni attribution directe. Les droits Premium, tokens, progression, achats d’éléments et daily reward restent des lots ultérieurs.
