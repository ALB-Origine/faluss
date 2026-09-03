# Contrat Faluss Identity v1

## Objet

Le protocole relie une application cliente à une identité déjà prouvée sur `faluss.me`. Il ne partage ni cookie, ni session WordPress, ni mot de passe.

## Pré-enregistrement client

Un administrateur Faluss crée le client dans Faluss Identity. Le client possède :

- `client_id` opaque ;
- nom et statut ;
- URI de retour exactes ;
- scopes autorisés ;
- secret généré une seule fois, haché côté Identity et fourni hors Git au site client.

Le premier client est `pro.faluss.com`. Les URI de production doivent être HTTPS et sans joker.

## Connexion

1. Le client génère `state` (256 bits) et `code_verifier` (256 bits), conserve les deux dans une session serveur ou cookie `HttpOnly` local, puis calcule `code_challenge = BASE64URL(SHA-256(code_verifier))`.
2. Il redirige vers l'endpoint d'autorisation Faluss Identity avec `client_id`, `redirect_uri`, `state`, `code_challenge`, `code_challenge_method=S256` et les scopes demandés.
3. Faluss Identity vérifie le client et l'URI, puis réutilise sa session centrale ou lance la cérémonie passwordless.
4. Après preuve, Identity crée un code opaque, aléatoire, à usage unique, expirant sous 60 secondes. Il est lié au Faluss ID, client, URI, challenge PKCE et scopes validés.
5. Identity redirige vers l'URI préenregistrée avec seulement `code` et `state`.
6. Le client vérifie `state`, puis échange le code côté serveur avec `client_id`, secret client et `code_verifier`.
7. Identity consomme le code de manière atomique. Il retourne seulement les claims autorisés : `sub` (`faluss_id`) et, si demandé et accordé, `email` vérifié.
8. Le client crée ou retrouve son WordPress user local, confirme la liaison, ouvre une session WordPress locale et redirige vers son retour local déjà validé.

## Scopes v1

| Scope | Claims | Usage |
|---|---|---|
| `identity` | `sub` | obligatoire |
| `email` | e-mail vérifié | création/lien du compte local avec consentement prévu |
| `profile.read` | futur, non activé en v1 | profil public minimal |

Le scope Date n'existe pas. Date ne reçoit jamais plus que les claims strictement nécessaires à son inscription et sa connexion.

## Erreurs et journalisation

Les erreurs visibles restent génériques. Les journaux d'audit utilisent un identifiant d'événement, `client_id`, type d'action et horodatage. Ils n'enregistrent jamais code, verifier PKCE, secret client, OTP ou e-mail brut.
