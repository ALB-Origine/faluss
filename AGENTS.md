# Instructions de développement Faluss

## Mission

Construire un SSO passwordless WordPress pour l'écosystème Faluss sans casser les applications clientes ni répliquer leurs données métier.

## Répartition des responsabilités

- **Faluss Identity** (`faluss.me`) : création et preuve de l'identité, session centrale, Faluss ID, clients autorisés et échange de codes.
- **Faluss Identity Client** : protocole de connexion, création ou liaison du compte WordPress local, session locale.
- **Application cliente** : données métier, rôles locaux, droits d'accès, catalogue, commandes, progression et tokens.

## Invariants non négociables

1. Le `faluss_id` est un UUID opaque, stable et non réutilisable.
2. Chaque application conserve son propre `wp_user_id`; les plugins métier ne sont pas refactorés pour utiliser le `faluss_id`.
3. L'identité ne transmet aucun secret dans une URL, jamais un e-mail ou un jeton dans les logs, et ne crée jamais un compte privilégié.
4. Le SSO v1 utilise un code d'autorisation à usage unique, durée 60 secondes, lié au client, à son URI de retour exacte et à PKCE S256.
5. L'échange serveur-à-serveur exige aussi le secret du client, stocké hors Git et hors options WordPress exportables.
6. Les URI de retour sont préenregistrées, exactes et HTTPS en production; aucune URI n'est acceptée sur la seule base d'une requête.
7. Chaque transition critique est atomique en base InnoDB et échoue fermée.
8. Une adresse e-mail vérifiée est une preuve ponctuelle. Elle n'est ni le Faluss ID, ni une clé de droits, ni une clé de rapprochement implicite entre applications.
9. Date reste une enclave : le Faluss ID peut prouver l'appartenance, pas exposer les préférences, conversations, rencontres, notes, signalements ou score détaillé.

## Méthode obligatoire

Avant de modifier : lire les documents concernés, les appelants et les tests. Écrire les scénarios positif et négatif.

Après modification : exécuter `php -l` sur chaque PHP modifié, les tests de contrat concernés, un scan de secrets ciblé et `git diff --check`. Ne déclarer aucune recette WordPress réelle sans preuve.

## Compatibilité Altlab

Le passwordless Altlab existant sert de référence de sécurité : challenge 256 bits, cookie navigateur `HttpOnly` lié au challenge, OTP haché, cinq essais, expiration dix minutes et rate limit transactionnel. Sa logique est adaptée dans Faluss Identity; ses classes ne sont pas copiées sans renommage, tests et contrat explicite.

La migration d'Altlab est additive et réversible : voir `docs/MIGRATION_ALTLAB.md`.
