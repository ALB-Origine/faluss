# Faluss Events 0.2.0 — cœur persistant sans transport

## Frontière

Faluss Events valide les contrats `faluss.event-source-catalog` 1.0.0 et
`faluss.event` 1.0.0 et matérialise leur cœur persistant. Son schéma interne est
`1`. EVT-01B.2A crée cinq tables append-only, canonicalise catalogues et
enveloppes, impose l'idempotence métier et prépare les lignes durables de
livraison.

Ce lot n'enregistre aucun provider ou catalogue Hub/Me, ne produit aucun
événement et n'active ni `event.publish`, ni endpoint, transport, lease, worker,
cron, retry, callback consommateur, tracking, Analytics, Quêtes ou Progression.
Federation reste en version 0.2.0, schéma 1, sans modification.

## Architecture interne

- `Faluss_Events_Catalog_Validator` contrôle le catalogue fermé et sa cohérence
  avec le manifeste CAP accepté.
- `Faluss_Events_Envelope_Validator` lie l'enveloppe au catalogue exact et à un
  validateur de payload enregistré par du PHP de confiance. L'ordre des clés
  d'un objet JSON n'a aucune portée sémantique.
- `Faluss_Events_Canonicalizer` produit les octets privés utilisés pour les
  SHA-256 du catalogue, de l'enveloppe et de l'identité métier.
- `Faluss_Events_Schema` crée et vérifie le schéma 1 sans `dbDelta()` et refuse
  toute structure partielle ou divergente.
- `Faluss_Events_Engine` accepte explicitement un catalogue validé, un événement
  local ou un événement inbound authentifié. Il ne lance aucun transport et
  n'exécute aucun callback.
- `Faluss_Events` conserve les registres fermés de providers et validateurs de
  payload, ainsi que les lectures signées de catalogue déjà livrées par
  EVT-01B.1/1.1.

## Migration contrôlée

La première requête `plugins_loaded` après remplacement d'un 0.1.1 actif,
l'activation et l'installation neuve suivent le même chemin. Cinq
tables temporaires InnoDB sont créées, vérifiées champ par champ et index par
index, puis promues ensemble par un unique `RENAME TABLE`. L'option
`faluss_events_schema_version = 1` n'est écrite qu'après la vérification des
cinq tables finales. Une table préexistante, une option inattendue ou une
structure divergente arrête l'opération sans réparation ni adoption implicite.

Une fois l'option 1 déclarée, le chargement normal ne fait qu'une lecture
d'option et n'exécute aucune requête de schéma ou de donnée. Une seconde
activation vérifie les structures sans créer de table supplémentaire.
L'installation n'insère aucun catalogue, événement, outbox, inbox ou delivery.
La désactivation et la désinstallation ne réécrivent et ne suppriment aucune
donnée.

## Canonicalisation supportée

La canonicalisation applique la sémantique RFC 8785/JCS au sous-ensemble fermé
d'EVT-01A : `null`, booléens, entiers compris entre
`-9007199254740991` et `9007199254740991`, chaînes UTF-8 et tableaux PHP. Les
listes conservent leur ordre ; les objets associatifs ont des clés ASCII triées
récursivement, ce qui est équivalent à l'ordre JCS pour ces clés contractuelles.
Unicode et slashs restent non échappés, tandis que les contrôles JSON reçoivent
leur échappement normatif.

Les floats, NaN, infinis, objets PHP, ressources, UTF-8 invalide et clés hors du
sous-ensemble sont refusés avant toute écriture. Un tableau PHP vide représente
une liste vide ; seule la racine `payload`, connue par le schéma comme objet,
est encodée en `{}` lorsqu'elle est vide. Il ne s'agit donc pas d'une
implémentation JCS générale pour des valeurs PHP arbitraires.

## Catalogues et événements

Un catalogue accepté est immuable et identifié par
`node_id/app_key/capability_key/catalog_version`. Même tuple et mêmes octets
canoniques retrouve le snapshot ; toute divergence produit
`faluss_events_conflict`. Le rafraîchissement distant est uniquement explicite
et appelle d'abord `Faluss_Events::read_remote_catalog()`. La résolution locale
utilise uniquement un provider PHP déjà enregistré.

L'identité d'un événement est le SHA-256 canonique du tuple exact
`source.node_id`, `source.app_key`, `source.owner`, `source.capability_key`,
`source.catalog_version`, `event_type`, `event_version` et
`source_event_reference`. Deux verrous nommés, l'un sur cette identité et
l'autre sur `event_id`, sérialisent aussi bien deux versions d'un même fait que
deux faits revendiquant le même identifiant. Les contraintes uniques restent
la seconde barrière.

Pour un événement local, l'événement et toutes ses outbox sont insérés dans une
transaction. Pour un événement inbound, la source doit égaler le sender
authentifié ; événement, inbox et toutes les deliveries consommateurs sont
insérés dans une transaction. Une route ou un consommateur exact manquant est
refusé avant écriture. Un retry identique vérifie également toutes ses lignes
opérationnelles avant de retourner l'occurrence existante.

Les registres de routes et consommateurs n'acceptent aucun wildcard. Les
callbacks doivent être des callables PHP de confiance, ne sont jamais chargés
depuis le réseau ou la base et ne sont pas exécutés dans 0.2.0. Un doublon rend
le registre concerné indisponible de manière fail-closed.

## Lecture distante de catalogue

`Faluss_Events::read_remote_catalog()` conserve le chemin signé
`manifest.read`, puis `event_catalog.read`. Chaque réponse est liée au pair,
fraîche et validée ; la validation croisée CAP/EVT demeure obligatoire. Aucun
cache stale, fallback réseau ou lecture de table distante n'est ajouté.

## Recette WordPress limitée

1. Sauvegarder `faluss.com` et `faluss.me`.
2. Mettre à jour uniquement Faluss Events avec le même ZIP 0.2.0 sur les deux
   sites, puis activer le plugin.
3. Ne modifier aucune politique Federation, clé, pair, provider ou constante.
4. Vérifier Faluss Events 0.2.0, schéma 1 et l'état `ready`.
5. Vérifier la présence exacte des cinq tables documentées dans
   `DATA_MODEL.md` et l'absence de toute ligne dans chacune.
6. Vérifier Federation 0.2.0, schéma 1 et `ready`, puis relancer le diagnostic
   bidirectionnel existant.
7. Confirmer qu'aucun événement, provider, catalogue réel, transport, worker,
   cron, tracking ou consommateur métier n'est actif.

EVT-01B.2B ajoutera `event.publish`, les leases et les workers. AN-01 reste
bloqué jusqu'à la validation de ce second sous-lot.
