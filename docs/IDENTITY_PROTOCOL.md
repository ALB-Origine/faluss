# Contrat Faluss Identity v1

## Objet

Le protocole relie une application cliente à une identité déjà prouvée sur `faluss.me`. Il ne partage ni cookie, ni session WordPress, ni mot de passe.

## Pré-enregistrement client

Un administrateur Faluss crée le client dans Faluss Identity. Le client possède :

- `client_id` opaque ;
- nom et statut ;
- URI de retour HTTPS exactes, sans joker ni fragment ;
- scopes autorisés ;
- secret optionnel pour les clients confidentiels, généré une seule fois, haché côté Identity et fourni hors Git au site client.

Le premier client est `pro.faluss.com`. Les URI de production doivent être HTTPS et sans joker.

## Connexion

1. Le client génère `state` (256 bits) et `code_verifier` (256 bits), conserve les deux dans une session serveur ou cookie `HttpOnly` local, puis calcule `code_challenge = BASE64URL(SHA-256(code_verifier))`.
2. Il redirige vers l'endpoint d'autorisation Faluss Identity avec `client_id`, `redirect_uri`, `state`, `code_challenge`, `code_challenge_method=S256` et les scopes demandés.
3. Faluss Identity vérifie le client actif, l'URI exacte, les scopes et `S256`, puis réutilise sa session centrale ou redirige uniquement vers son `/login` local. La demande validée et son `state` restent côté serveur pendant cette reprise.
4. Le membre confirme ou refuse explicitement les attributs demandés. Après confirmation, Identity crée un code opaque, aléatoire, à usage unique, expirant sous 60 secondes. Il est lié au Faluss ID, client, URI, challenge PKCE et scopes validés.
5. Identity redirige vers l'URI préenregistrée avec seulement `code` et `state`.
6. Le client vérifie `state`, puis échange le code côté serveur avec `grant_type=authorization_code`, `client_id`, `redirect_uri`, `code_verifier` et, pour un client confidentiel, son secret.
7. Identity consomme le code de manière atomique. Il retourne seulement les claims autorisés : `faluss_id` et, si demandé et accordé, `email` vérifié.
8. Le client crée ou retrouve son WordPress user local, confirme la liaison, ouvre une session WordPress locale et redirige vers son retour local déjà validé.

## Scopes v1

| Scope | Claims | Usage |
|---|---|---|
| `identity.basic` | `faluss_id` | obligatoire |
| `identity.email` | e-mail vérifié | création/lien du compte local avec consentement prévu |

Les seuls endpoints FI-04 sont `GET/POST /oauth/authorize` et `POST /oauth/token`.
`/oauth/token` répond avec `{ "faluss_id", "scope" }` et ajoute `email` uniquement lorsque `identity.email` a été accordé. Il ne retourne aucun access token, refresh token, donnée Pro, Date, profil public ou Token Engine.

Le scope Date n'existe pas. Date ne reçoit jamais plus que les claims strictement nécessaires à son inscription et sa connexion.

## Erreurs et journalisation

Les erreurs visibles restent génériques. Si et seulement si l'URI de retour a déjà été validée, le refus retourne `error` et le `state` au client. Les journaux d'audit utilisent un identifiant d'événement, `client_id`, type d'action et horodatage. Ils n'enregistrent jamais code, verifier PKCE, secret client, OTP, état ou e-mail brut.

## Préférence front des membres Faluss (FI-06)

Un utilisateur qui possède un profil Faluss Identity et dont l’unique rôle WordPress est `subscriber` reçoit la préférence WordPress `show_admin_bar_front=false`. Le filtre front applique la même règle à chaque affichage afin qu’une réactivation accidentelle de la préférence ne réintroduise pas la barre d’administration. Les rôles combinés ou différents — administrateur, éditeur, auteur, client WooCommerce ou tout autre rôle — ne sont jamais modifiés.

La migration FI-06 est versionnée et idempotente. Elle parcourt exclusivement les `wp_user_id` de la table des profils Faluss Identity ; elle ne parcourt pas la liste globale des utilisateurs et ne crée aucune table ni meta Elementor.
