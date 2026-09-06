# Modèle de données minimal

Les noms réels utilisent le préfixe WordPress actif.

## Faluss Identity

| Table | Clés / contenu |
|---|---|
| `faluss_identity_profiles` | `faluss_id` unique, `wp_user_id` unique, statut, dates, version de consentement ; état ONB-01 / ONB-02 minimal (choix, statut de réservation, prochaine étape reprenable, version de flow) |
| `faluss_identity_public_profiles` | `faluss_id` unique, identifiant public unique, contenu public, ordre des liens et statut de publication |
| `faluss_identity_challenges` | challenge haché, OTP haché, empreinte cookie navigateur, tentatives, statut, expiration |
| `faluss_identity_rate_limits` | bucket haché, compteur, fenêtre et expiration ; InnoDB |
| `faluss_identity_clients` | client opaque, statut, secret haché, scopes et URI de retour autorisées |
| `faluss_identity_auth_codes` | code haché, Faluss ID, client, URI, challenge PKCE, scopes, expiration, consommation atomique |
| `faluss_identity_authorization_requests` | empreinte de poignée navigateur, client, URI, scopes, PKCE et `state`, expiration et décision ; reprise locale de consentement |
| `faluss_identity_audit` | événement minimal et non sensible, conservation bornée |

Les codes, OTP, secrets navigateur et secrets client ne sont jamais conservés en clair. Les migrations valident moteur InnoDB, index, unicité et ordre d'index avant de déclarer le schéma utilisable.

L’état ONB-01 / ONB-02 est ajouté aux lignes Identity existantes : il ne crée ni table de profils concurrente, ni copie de slug, de carte, d’e-mail ou de préférence visuelle. Le slug public permanent reste l’unique clé dans `faluss_identity_public_profiles`. ONB-02 conserve seulement la prochaine étape (`wizard_name` à `wizard_finish`) ; toutes les valeurs de carte restent dans les tables Identity ou Link qui les possèdent déjà.

## Client

| Table | Clés / contenu |
|---|---|
| `faluss_identity_links` | `wp_user_id` unique, `faluss_id` unique, dates de liaison et dernière preuve |
| `faluss_identity_client_state` | état SSO haché, retour local validé et expiration courte |

Un conflit de liaison est bloquant : il ne doit jamais être résolu automatiquement par l'e-mail.

## Faluss Link

| Table | Clés / contenu |
|---|---|
| `faluss_link_cards` | `faluss_id` unique, préférences visuelles, réseaux sociaux complémentaires et dates ; aucune donnée d’identité dupliquée |
| `faluss_link_blocks` | `faluss_id` et identifiant de bloc uniques, ordre stable et payload de contenu validé ; un teaser peut conserver son mode visuel Public/Membre/Droit et un code de droit central, sans copie d’attribution, de solde ou de décision |
| `faluss_link_discoveries` | paire unique visiteur Faluss / profil découvert, première et dernière découverte, compteur interne ; aucune bio, photo, lien, IP, user-agent ni référent |
| `faluss_link_discovery_settings` | `faluss_id` du visiteur unique, préférence privée d’enregistrement et date de mise à jour |

Les tables de découvertes sont privées à Faluss Link. Les profils sont relus depuis la table publique Identity seulement au rendu de la bibliothèque ; un profil masqué ou supprimé est ignoré sans dupliquer son contenu. La suppression individuelle et totale est toujours filtrée par le `faluss_id` du membre courant.

ONB-02 ne crée aucune table Faluss Link : `faluss_link_cards` porte les préférences visuelles et les réseaux du brouillon, tandis que `faluss_link_blocks` porte les liens libres ordonnés. Ces valeurs sont relues par le même résolveur que le Studio et la carte publique.

## Token Engine

| Table | Clés / contenu |
|---|---|
| `token_engine_projects` | `project_key` unique et stable, nom, état, identifiant connecteur public facultatif, empreinte/version de secret et permissions ; aucun projet système |
| `token_engine_rules` | `rule_key` unique et stable, projet facultatif, portée, déclencheur, périodicité, cooldown, montant positif, état, dates ; TE-03 exécute explicitement uniquement la règle globale `daily_reward` lorsque le Connector porte `reward.claim` |
| `token_engine_ledger` | UUID unique, `subject_id`, projet, règle facultative, sens, montant positif, clé d’idempotence unique, référence, métadonnées JSON bornées et date ; source unique de la projection de solde |
| `token_engine_connector_tokens` | empreinte unique de jeton opaque, projet, version de secret, permissions, expiration et date ; aucun jeton clair stocké |
| `token_engine_entitlement_definitions` | code unique, libellé, projet/surface, type générique `theme`, état et dates ; aucune identité ou donnée métier |
| `token_engine_entitlement_grants` | UUID unique, `subject_id`, définition, source manuelle, référence d’opération unique, cycle de vie (début/fin/révocation) et dates ; aucun solde ni ledger dupliqué |

La donnée `subject_id` reste générique. Le connecteur Faluss futur fournira un `faluss_id`, sans que cette table ne copie une identité, un e-mail ou des données métier.
