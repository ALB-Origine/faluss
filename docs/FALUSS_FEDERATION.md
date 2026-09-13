# Faluss Federation 0.1.4 — exploitation privée

## Frontière

`plugins/faluss-federation/` matérialise FED-01A.1 sur un nœud WordPress approuvé. Il ne transporte que `diagnostic.read`, `manifest.read` et `read_model.read`, par HTTPS serveur-à-serveur, Ed25519 et politique locale fermée. Il n'est ni un RPC générique, ni un transport de paiement, claim, entitlement, profil, média ou donnée métier. Il ne remplace pas FPR, Identity, Token Engine Connector, Stripe ou Faluss Subscriptions.

En version 0.1.4, seul `diagnostic.read` possède un producteur. Les deux autres opérations renvoient une réponse signée `not_available` tant qu'un plugin propriétaire de confiance n'a pas enregistré son provider et son validateur spécialisés. Cette version ne livre donc ni CAP-01B, ni manifeste Hub/Me, ni read-model membre fédéré.

## Préconditions et configuration locale

Le plugin demande WordPress 6.4 et PHP 7.4. L'extension PHP Sodium **native**, ses constantes Ed25519 et toutes les primitives requises, dont `sodium_memzero`, sont nécessaires pour rendre le transport opérationnel. Les fonctions homonymes fournies uniquement par `sodium_compat` ne satisfont jamais cette précondition. Sodium n'est cependant pas nécessaire pour activer le plugin : sans extension native valide, l'écran d'administration reste entièrement affichable, indique `Transport indisponible : Sodium absent ou invalide`, aucune route n'est enregistrée et toutes les façades échouent fermées.

Tout échec de nettoyage mémoire, qu'il prenne la forme d'une `SodiumException` ou d'une autre `Throwable`, est contenu. Les variables sensibles sont rendues inaccessibles après la tentative et l'auto-test, l'identité locale ou la signature concernée échoue fermée ; un nettoyage impossible ne peut donc pas être converti en succès.

Avant d'activer réellement le transport, définir exclusivement dans la configuration protégée du serveur, jamais dans Git ni dans la base :

```php
define( 'FALUSS_FEDERATION_LOCAL_NODE_ID', '...' );
define( 'FALUSS_FEDERATION_LOCAL_APP_KEY', '...' );
define( 'FALUSS_FEDERATION_LOCAL_ORIGIN', 'https://example.invalid' );
define( 'FALUSS_FEDERATION_LOCAL_KEY_ID', '...' );
define( 'FALUSS_FEDERATION_LOCAL_KEY_VALID_FROM', '2026-01-01T00:00:00Z' );
define( 'FALUSS_FEDERATION_LOCAL_KEY_VALID_UNTIL', '2027-01-01T00:00:00Z' );
define( 'FALUSS_FEDERATION_PRIVATE_SEED', 'base64url-canonique-de-43-caracteres' );
```

Le seed est exactement 32 octets en base64url canonique sans padding. Il sert uniquement à dériver ponctuellement la paire Ed25519 et est effacé en mémoire. Le plugin ne l'affiche, ne l'exporte, ne le journalise et ne le persiste jamais. L'origine doit être HTTPS canonique et identique à l'origine WordPress.

Les identités initiales attendues sont documentées, jamais déduites depuis le hostname :

| Site | node_id | app_key | origine |
| --- | --- | --- | --- |
| faluss.com | `hub-node` | `faluss-hub` | `https://faluss.com` |
| faluss.me | `me-node` | `faluss-me` | `https://faluss.me` |

Tout nœud futur définit ses propres constantes explicites.

## État et schéma

Le transport est `ready` seulement avec schéma 1 exact, extension Sodium native chargée, constantes et primitives Ed25519 requises présentes, auto-test valide, origine locale exacte, période de clé active, seed dérivable et au moins un pair exploitable. L'activation ne génère ni clé ni pair et n'appelle aucun domaine.

L'installation fraîche, sous verrou MariaDB borné, crée exclusivement quatre tables InnoDB préfixées WordPress :

| Table | Contenu et rétention |
| --- | --- |
| `faluss_federation_peers` | clés publiques et politiques locales exactes ; aucune clé privée |
| `faluss_federation_request_bindings` | liaison `(sender_node_id, request_id)` vers hash du corps ; au moins 15 minutes |
| `faluss_federation_nonces` | hash de nonce, clé émettrice, opération et consommation ; au moins 15 minutes |
| `faluss_federation_audit` | audit technique allowlisté ; maximum 30 jours |

Le schéma partiel ou divergent échoue fermé : aucune réparation automatique, aucun `dbDelta()`, aucune table existante modifiée. La purge est opportuniste, bornée et sans cron.

## Administration et pairage

Le seul écran est **Outils → Faluss Federation**, réservé à `manage_options`. Il utilise un nonce WordPress et POST pour chaque mutation, sans CSS ni JavaScript personnalisé. Il présente les métadonnées publiques locales, l'état du schéma/Sodium, les compteurs techniques et un bundle public copiable ; il ne présente jamais seed, clé secrète, nonce, signature, payload ou Faluss ID.

Échanger les bundles publics par un canal approuvé puis saisir le pair exact : nœud, application, origine HTTPS, `key_id`, clé publique, période, opérations, applications propriétaires, capacités et audiences. Les opérations et audiences sont des listes fermées et n'acceptent aucun wildcard. Créer une nouvelle clé active pour le même couple fait passer l'ancienne active à `rotating`; une seule clé de chaque état peut coexister. L'enregistrement exige la phrase exacte `ENREGISTRER LE PAIR FEDERATION`; la révocation exige `REVOQUER LA CLE FEDERATION`.

Le diagnostic distant est volontaire et utilise exclusivement l'origine du pair déjà enregistrée côté serveur. Il ne prend aucune URL du navigateur.

## Transport

La route unique, disponible seulement lorsque le transport est prêt, est :

```text
POST /wp-json/faluss-federation/v1/exchange
```

Le receiver lit et hache les octets bruts une seule fois. Il récupère chacun des trois en-têtes cryptographiques uniques par `WP_REST_Request::get_header_as_array()` avec son nom HTTP public ; WordPress canonicalise ainsi la casse et traite tirets et underscores de manière identique. Une absence, plusieurs valeurs, une valeur ambiguë ou fusionnée par virgule reste refusée génériquement. Le receiver vérifie ensuite la forme JSON bornée, la clé/politique, la signature et la fraîcheur avant de consommer nonce et binding dans la même transaction InnoDB. Toute date d'enveloppe est émise et acceptée uniquement au format UTC canonique `Y-m-d\TH:i:s\Z`, sans fraction ni décalage. Les refus pré-authentification restent génériques. Les réponses post-authentification sont sérialisées une fois, signées sur les octets servis, privées (`Cache-Control: private, no-store`) et limitées à 65 536 octets. Les plafonds JSON sont profondeur 16, 128 champs ou éléments et 4 096 octets par chaîne.

Les limites par clé/opération/minute restent 30, 60 et 600. Avant la transaction, le receiver acquiert pendant au plus une seconde un verrou consultatif MariaDB propre au tuple émetteur, clé et opération. Son nom de 64 caractères dérive du préfixe WordPress et du tuple par SHA-256 tronqué, sans identifiant brut. Le verrou reste détenu jusqu'après commit ou rollback, puis sa libération doit être confirmée avant tout dispatch. Une requête plafonnée consomme toujours nonce et binding après commit confirmé ; une libération absente ou ambiguë échoue fermée. La purge opportuniste ne commence qu'après la sortie confirmée de la section verrouillée. Deux buckets distincts utilisent des verrous distincts.

La façade interne sortante n'expose que `diagnostic_read`, `manifest_read` et `read_model_read`. Elle force HTTPS, `sslverify`, zéro redirection, une durée totale effective de trois secondes et `limit_response_size` à 65 536 octets avec les seuls arguments supportés par l'API HTTP WordPress. Elle contrôle ensuite la taille reçue, l'identité, la liaison requête/réponse, les en-têtes, hash, signature, fraîcheur, statut et validateur spécialisé avant de rendre une réponse. Aucun payload reçu n'est persisté dans une option, table, transient ou cache durable.

## Recette WordPress à exécuter ultérieurement

Cette recette n'est pas exécutée par FED-01B :

1. Mettre à jour `faluss.com` et `faluss.me` avec le même ZIP Faluss Federation 0.1.4, sans modifier ni régénérer seed, `key_id`, clé publique, pair ou politique existante.
2. Confirmer sur les deux sites le schéma `1`, Sodium natif, l'auto-test Ed25519, l'état `ready` et la présence du receiver existant.
3. Lancer `diagnostic.read` de `faluss.com` vers `faluss.me`, puis de `faluss.me` vers `faluss.com`, et vérifier dans chaque sens la réponse signée ainsi que l'audit technique du receiver.

Ne pas configurer de secret de production durant le développement et ne pas enregistrer de provider CAP-01B avant son contrat et son lot dédié.
