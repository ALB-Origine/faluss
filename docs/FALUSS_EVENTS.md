# Faluss Events 0.3.1 — transport fiable, workers et rétention

## Frontière

Faluss Events valide `faluss.event-source-catalog` 1.0.0, `faluss.event` 1.0.0
et `faluss.event-acceptance` 1.0.0. Son schéma interne est `2`.
EVT-01B.2B active le transport des outbox, la réception authentifiée et les
workers internes. EVT-01B.2C ajoute exclusivement la table de reçus de purge et
la migration additive `1 → 2`, sans modifier le DDL ni les lignes des cinq
tables historiques.

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
- `Faluss_Events_Schema` vérifie les six tables du schéma 2 sans `dbDelta()` ;
  une mise à jour depuis le schéma 1 vérifié crée seulement la sixième.
- `Faluss_Events_Engine` accepte catalogues et événements, puis résout les
  routes et consommateurs exacts enregistrés par du PHP de confiance.
- `Faluss_Events` conserve les registres fermés et enregistre les trois callables
  distincts de l'adaptateur publish : validateur de requête, receiver inbound et
  validateur d'accusé.
- `Faluss_Events_Workers` réclame et finalise outbox et deliveries par lease
  opaque, hors transaction pendant tout réseau ou callback.
- `Faluss_Events_Retention` purge par lots les faits expirés et conserve un
  reçu minimal pendant exactement trente jours.

L'absence ou le doublon d'un adaptateur, d'une route ou d'un consommateur ferme
le chemin concerné. Aucun callback, pair, URL ou destination réseau ne vient de
l'enveloppe ou du navigateur.

## Installation et données

La mise à jour d'un plugin au schéma 1 vérifie les cinq tables historiques,
crée et vérifie seulement la table de tombstones, puis déclare le schéma 2. Elle
ne répare et ne réécrit aucune table existante. Une installation fraîche suit
le chemin atomique : six
tables temporaires InnoDB vérifiées champ et index par champ, puis promues par
un unique `RENAME TABLE`. L'option `faluss_events_schema_version = 2` n'est
écrite qu'après vérification.

La désactivation retire exclusivement les trois hooks Cron Faluss Events. La
désinstallation ne supprime aucune table ni donnée. Aucun catalogue, événement,
outbox, inbox, delivery ou tombstone n'est créé au chargement, à l'activation
ou à la mise à jour.

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

Trois hooks WP-Cron uniques, outbox, consommateurs et rétention, utilisent un intervalle
d'une minute. La planification est idempotente à l'activation et lors de la
première requête après mise à jour d'un plugin actif. Un verrou consultatif
global, borné et distinct par worker empêche deux exécutions simultanées. Si le
schéma, le registre ou le verrou est indisponible, aucune ligne n'est traitée.

WP-Cron dépend du trafic WordPress. Un vrai cron serveur pourra ultérieurement
appeler `wp-cron.php`, sans service externe créé par ce lot.

## Rétention effective

Le hook `faluss_events_run_retention` sélectionne au plus cinquante événements
dont `retention_until` est atteint selon l'heure UTC serveur. Sous un verrou
consultatif global puis un verrou haché par événement, chaque purge relit le
fait `FOR UPDATE`, crée ou vérifie son tombstone, supprime dans l'ordre outbox,
inbox, deliveries consommateurs, puis le fait, et committe l'ensemble. Toute
erreur ou ambiguïté annule la transaction. Aucun catalogue n'est supprimé.

Le tombstone ne contient que son UUID, `event_id`, les SHA-256 d'identité source
et d'événement, `purged_at`, `expires_at` et `created_at`. Il ne permet jamais de
reconstruire l'enveloppe. Un retry exact retourne `existing`; une divergence est
un conflit. Les tombstones arrivés à trente jours sont supprimés dans une
transaction séparée et bornée.

Avant un appel Federation ou un callback, chaque worker prend le même verrou
d'événement et relit `retention_until`. Un fait expiré reçoit seulement le code
technique `expired` : aucun réseau, callback ou effet métier n'est exécuté et
aucune transaction SQL ne reste ouverte pendant une sortie externe.

## Recette WordPress limitée

1. Sauvegarder `faluss.com` et `faluss.me`.
2. Installer le même ZIP Events sur les deux sites.
3. Vérifier la version `0.3.1` et le schéma `2`.
4. Vérifier les six tables et la nouvelle table vide.
5. Vérifier les trois hooks Cron uniques.
6. Confirmer que les cinq anciennes tables n'ont perdu aucune ligne.
7. Relancer les diagnostics Federation existants.
8. Ne modifier aucune politique et ne produire aucun événement métier.

## Consommateur Analytics AN-01B.1

Faluss Analytics `0.1.0` utilise exclusivement les façades publiques de ce
runtime pour enregistrer six validateurs de payload et le consumer exact
`analytics.events` / `faluss-analytics.aggregate-v1`. Il n'ajoute aucune route,
catalogue, source ou modification à Faluss Events `0.3.1`. En l'absence des
futurs catalogues, providers, routes et producteurs Hub/Me, aucun fait réel
n'est livré au consumer.

## Providers propriétaires AN-01B.2

Portal `0.1.23` et Link `0.3.20` enregistrent dans
`Faluss_Events::register_catalog_provider()` les tuples fermés
`faluss-hub/faluss-hub.events/1.0.0` et
`faluss-me/faluss-me.events/1.0.0`. Les catalogues sont validés par les classes
Events de production puis croisés avec les manifestes CAP propriétaires. Les
enregistrements sont idempotents; une collision ou une divergence ferme le
provider.

Ces providers ne font qu'exposer leur document à une lecture explicitement
autorisée. Ils n'appellent aucun chemin d'acceptation, de publication, de route
ou de rafraîchissement; ils ne créent donc ni catalogue accepté, ni événement,
ni outbox, inbox, delivery ou métrique.

## Routes propriétaires AN-01B.3

Sans modifier Faluss Events `0.3.1` ni son schéma `2`, Portal `0.1.24` et Link
`0.3.21` utilisent `Faluss_Events_Engine::register_delivery_route()` pour deux
descripteurs fermés : Hub local vers Hub Analytics, et Me Federation vers ce
même Hub. Link utilise aussi l'unique registre public de payload validators
pour les trois contrats Me AN-01 ; Portal n'enregistre aucun validateur.

Les états d'enregistrement des validateurs et de la route Me sont séparés afin
que le signal Federation puisse reprendre seulement la route. Les répétitions
sont sans effet ; une collision ou un état partiel divergent ferme le runtime.
L'enregistrement n'accepte pas le catalogue, ne persiste rien, ne planifie rien
et ne constitue aucune autorisation `event_catalog.read` ou `event.publish`.
