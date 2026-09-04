# Modèle de données minimal

Les noms réels utilisent le préfixe WordPress actif.

## Faluss Identity

| Table | Clés / contenu |
|---|---|
| `faluss_identity_profiles` | `faluss_id` unique, `wp_user_id` unique, statut, dates, version de consentement |
| `faluss_identity_public_profiles` | `faluss_id` unique, identifiant public unique, contenu public, ordre des liens et statut de publication |
| `faluss_identity_challenges` | challenge haché, OTP haché, empreinte cookie navigateur, tentatives, statut, expiration |
| `faluss_identity_rate_limits` | bucket haché, compteur, fenêtre et expiration ; InnoDB |
| `faluss_identity_clients` | client opaque, statut, secret haché, scopes et URI de retour autorisées |
| `faluss_identity_auth_codes` | code haché, Faluss ID, client, URI, challenge PKCE, scopes, expiration, consommation atomique |
| `faluss_identity_audit` | événement minimal et non sensible, conservation bornée |

Les codes, OTP, secrets navigateur et secrets client ne sont jamais conservés en clair. Les migrations valident moteur InnoDB, index, unicité et ordre d'index avant de déclarer le schéma utilisable.

## Client

| Table | Clés / contenu |
|---|---|
| `faluss_identity_links` | `wp_user_id` unique, `faluss_id` unique, dates de liaison et dernière preuve |
| `faluss_identity_client_state` | état SSO haché, retour local validé et expiration courte |

Un conflit de liaison est bloquant : il ne doit jamais être résolu automatiquement par l'e-mail.
