# Faluss Events 0.3.0 — transport fiable et workers

## Frontière

Faluss Events valide `faluss.event-source-catalog` 1.0.0, `faluss.event` 1.0.0
et `faluss.event-acceptance` 1.0.0. Son schéma interne reste `1`.
EVT-01B.2B active le transport des outbox, la réception authentifiée et les
workers internes sur les cinq tables existantes, sans table, colonne, index ou
migration supplémentaire. `class-faluss-events-schema.php` et son DDL restent
identiques au lot précédent.

Les bornes de stockage restent 512 caractères pour une clé namespacée ou un
type de document, 128 pour une clé consommateur et 32 pour toute version
sémantique EVT. Elles sont appliquées avant verrou, transaction, écriture ou
génération d'UUID.

Ce lot n'enregistre aucun provider, catalogue, événement, route ou consommateur
métier Hub/Me. Il n'ajoute ni tracking, cookie, pixel, Analytics, Quêtes,
Progression, écriture PF, wallet, commerce ou Stripe. Federation passe à 0.3.0,
schéma 1, uniquement pour l'opération fermée `event.publish` sur sa route signée
existante.

## Architecture interne

- `Faluss_Events_Catalog_Validator` contrôle le catalogue fermé et sa cohérence
  avec le manifeste CAP accepté.
- `Faluss_Events_Envelope_Validator` sépare la forme transport de l'enveloppe de
  sa validation contre le catalogue et le validateur de payload exact.
- `Faluss_Events_Canonicalizer` produit les octets privés et SHA-256 utilisés
  pour catalogues, événements et identités métier.
- `Faluss_Events_Schema` vérifie les cinq tables du schéma 1 sans `dbDelta()`.
- `Faluss_Events_Engine` accepte catalogues et événements, puis résout les
  routes et consommateurs exacts enregistrés par du PHP de confiance.
- `Faluss_Events` conserve les registres fermés et enregistre les trois callables
  distincts de l'adaptateur publish : validateur de requête, receiver inbound et
  validateur d'accusé.
- `Faluss_Events_Workers` réclame et finalise outbox et deliveries par lease
  opaque, hors transaction pendant tout réseau ou callback.

L'absence ou le doublon d'un adaptateur, d'une route ou d'un consommateur ferme
le chemin concerné. Aucun callback, pair, URL ou destination réseau ne vient de
l'enveloppe ou du navigateur.

## Installation et données

La mise à jour d'un plugin déjà actif vérifie seulement le schéma déclaré et
planifie les workers manquants. Elle ne migre, ne répare et ne réécrit aucune
table. Une installation fraîche conserve le chemin atomique existant : cinq
tables temporaires InnoDB vérifiées champ et index par champ, puis promues par
un unique `RENAME TABLE`. L'option `faluss_events_schema_version = 1` n'est
écrite qu'après vérification.

La désactivation retire exclusivement les deux hooks Cron Faluss Events. La
désinstallation ne supprime aucune table ni donnée. Aucun catalogue, événement,
outbox, inbox ou delivery n'est créé au chargement, à l'activation ou à la mise
à jour.

## Canonicalisation et acceptation

La canonicalisation applique la sémantique RFC 8785/JCS au sous-ensemble fermé
d'EVT-01A : `null`, booléens, entiers sûrs, chaînes UTF-8 et tableaux PHP. Les
listes gardent leur ordre et les clés ASCII des objets sont triées
récursivement. Floats, UTF-8 invalide et formes hors contrat sont refusés.

Un catalogue accepté est immuable et identifié par
`node_id/app_key/capability_key/catalog_version`. L'identité d'un événement est
le SHA-256 canonique du tuple source, type, version et référence source. Deux
verrous nommés sérialisent l'identité métier et `event_id`; les contraintes
uniques restent la seconde barrière.

Pour un événement local, l'événement et toutes ses outbox sont insérés ensemble.
Pour un événement inbound, source node/app/owner doivent égaler le sender
authentifié et chaque consommateur doit cibler le destinataire node/app exact ;
événement, inbox et deliveries sont confirmés dans une transaction. Le succès
Federation n'est produit qu'après ce commit. Un retry identique ne crée aucune
seconde ligne et rend un accusé `existing`; une identité ou un contenu divergent
rend un conflit.

## Leases, routes et retries

Chaque worker prend au plus 50 lignes dues sous transaction, attribue un lease
base64url opaque, passe chaque ligne à `leased` et incrémente `attempt_count`.
Le commit précède tout transport ou callback. La finalisation exige le même
`lease_token` et le statut `leased`; un worker ancien ne confirme jamais une
ligne reprise. Un lease expiré est récupérable.

L'outbox utilise `pending`, `leased`, `retry`, `delivered`, `dead_letter`.
`next_attempt_at` est l'échéance métier et `lease_expires_at` uniquement
l'expiration du lease. Une route Federation relit l'enveloppe, vérifie ses
octets et son hash, résout le descriptor exact et appelle seulement
`Faluss_Federation_Client::event_publish()`. Seul un accusé signé `accepted` ou
`existing`, lié à l'ID et au SHA-256 canonique, confirme `delivered`.

Une route locale ne fait aucun HTTP. Dans une transaction unique, elle verrouille
l'outbox encore louée, crée uniquement les deliveries manquantes par leur clé
d'idempotence, puis confirme l'outbox. Elle ne crée aucune inbox.

Les deliveries consommateur utilisent `pending`, `leased`, `retry`, `processed`,
`dead_letter`. En `leased`, `lease_expires_at` est l'expiration du lease ; en
`retry`, c'est la date minimale de reprise ; dans un état terminal, elle vaut
`NULL`. Le callback reçoit exactement l'enveloppe, `delivery_uuid`, destination,
`consumer_key`, numéro de tentative et une clé d'idempotence stable dérivée de
event ID/destination/consumer. `true` confirme `processed` ;
`faluss_events_retryable`, une exception ou une forme inconnue réessaie ;
`faluss_events_permanent` termine en `dead_letter`. Aucun retour libre, message
ou détail d'exception n'est stocké.

Le maximum est huit tentatives, lease initial compris. Les délais après les sept
premiers échecs sont 60, 300, 900, 3 600, 10 800, 21 600 et 43 200 secondes. Le
huitième échec termine en `dead_letter`. `last_result_code` reste dans une
allowlist technique fixe sans HTTP brut, URL, corps, signature, exception,
identifiant membre ou payload.

## Ordonnancement WordPress

Deux hooks WP-Cron uniques, outbox et consommateurs, utilisent un intervalle
d'une minute. La planification est idempotente à l'activation et lors de la
première requête après mise à jour d'un plugin actif. Un verrou consultatif
global, borné et distinct par worker empêche deux exécutions simultanées. Si le
schéma, le registre ou le verrou est indisponible, aucune ligne n'est traitée.

WP-Cron dépend du trafic WordPress. Un vrai cron serveur pourra ultérieurement
appeler `wp-cron.php`, sans service externe créé par ce lot.

## Recette WordPress limitée

1. Sauvegarder `faluss.com` et `faluss.me`.
2. Mettre à jour Faluss Federation avec le même ZIP 0.3.0 sur les deux sites,
   puis Faluss Events avec le même ZIP 0.3.0.
3. Ne modifier aucune clé, pair, origine, période, seed ou donnée existante.
4. Vérifier les deux versions, les deux schémas 1 et l'état `ready`.
5. Vérifier les cinq tables Events existantes et les deux hooks Cron uniques.
6. Relancer diagnostics bidirectionnels et lectures de manifeste.
7. Laisser `event.publish` absent des politiques tant qu'aucun test EVT autorisé
   n'est prévu. Pour un futur test, l'ajouter explicitement avec la capacité
   source exacte, jamais par wildcard.
8. Confirmer qu'aucun provider, catalogue, événement, route ou consommateur
   métier Hub/Me/Analytics/Quêtes/Progression n'est actif.
