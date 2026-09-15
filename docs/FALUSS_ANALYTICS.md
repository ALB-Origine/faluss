# Faluss Analytics 0.1.0 — moteur privé AN-01B.1

## Frontière

Faluss Analytics est installé uniquement sur `faluss.com`. Il s'active lorsque
l'identité locale fournie par Faluss Federation vaut exactement `hub-node`,
`faluss-hub`, `https://faluss.com`. Une dépendance Events ou Federation absente,
un schéma indisponible ou une identité différente laisse le runtime fermé sans
fatal.

Le plugin ne fournit ni route, endpoint, écran, asset, cookie, pixel, tracking,
catalogue, provider, source, producteur ou politique Federation. Il n'émet aucun
événement. Les sources Hub et Me restent inactives tant que leurs lots
propriétaires ultérieurs ne les enregistrent pas explicitement.

## Stockage privé

Le schéma Analytics `1` crée atomiquement trois tables InnoDB, vérifie leur
collation WordPress, leurs colonnes et leurs index, puis écrit seulement l'option
`faluss_analytics_schema_version`. Une structure partielle ou divergente n'est
jamais réparée ni adoptée. L'installation ne crée aucune ligne.

### `*_faluss_analytics_receipts`

```sql
CREATE TABLE `{prefix}faluss_analytics_receipts` (`id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,`receipt_uuid` char(36) NOT NULL,`event_id` char(36) NOT NULL,`idempotency_sha256` char(64) NOT NULL,`event_sha256` char(64) NOT NULL,`subject_identity_sha256` char(64) NOT NULL,`processed_at` datetime NOT NULL,`expires_at` datetime NOT NULL,`created_at` datetime NOT NULL,PRIMARY KEY (`id`),UNIQUE KEY `receipt_uuid_unique` (`receipt_uuid`),UNIQUE KEY `receipt_event_unique` (`event_id`),UNIQUE KEY `receipt_idempotency_unique` (`idempotency_sha256`),KEY `receipt_expiry` (`expires_at`)) ENGINE=InnoDB {charset_collate}
```

Le reçu ne contient ni enveloppe, payload, Faluss ID brut, URL, identité
visiteur ou donnée Federation. Son expiration vaut exactement
`processed_at + 30 jours`.

### `*_faluss_analytics_daily_metrics`

```sql
CREATE TABLE `{prefix}faluss_analytics_daily_metrics` (`id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,`subject_identity_sha256` char(64) NOT NULL,`aggregate_date` date NOT NULL,`metric_key` varchar(64) NOT NULL,`metric_value` bigint(20) unsigned NOT NULL,`created_at` datetime NOT NULL,`updated_at` datetime NOT NULL,PRIMARY KEY (`id`),UNIQUE KEY `metric_subject_date_key` (`subject_identity_sha256`,`aggregate_date`,`metric_key`),KEY `metric_retention` (`aggregate_date`)) ENGINE=InnoDB {charset_collate}
```

### `*_faluss_analytics_daily_objects`

```sql
CREATE TABLE `{prefix}faluss_analytics_daily_objects` (`id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,`subject_identity_sha256` char(64) NOT NULL,`aggregate_date` date NOT NULL,`object_type` varchar(16) NOT NULL,`object_reference` varchar(84) NOT NULL,`metric_value` bigint(20) unsigned NOT NULL,`created_at` datetime NOT NULL,`updated_at` datetime NOT NULL,PRIMARY KEY (`id`),UNIQUE KEY `object_subject_date_reference` (`subject_identity_sha256`,`aggregate_date`,`object_type`,`object_reference`),KEY `object_retention` (`aggregate_date`)) ENGINE=InnoDB {charset_collate}
```

Le hash de sujet est :

```text
SHA-256("faluss-analytics:subject:v1\n" + lowercase_faluss_id)
```

La préimage et le Faluss ID ne sont jamais journalisés.

## Registres Events et agrégation

Le plugin enregistre les six validateurs de payload AN-01 `1.0.0`, puis un seul
consumer :

- destination `analytics.events` ;
- clé `faluss-analytics.aggregate-v1` ;
- cible `hub-node` / `faluss-hub` ;
- source locale `hub-node` / `faluss-hub` / `faluss-hub.events` / `1.0.0` ;
- source fédérée `me-node` / `faluss-me` / `faluss-me.events` / `1.0.0`.

Aucun wildcard n'est admis. Le callback recalcule exactement l'idempotency key
du worker Events, revalide source, acteur, sujet, objet, payload et références
AN-01, puis utilise des verrous hachés d'événement et de sujet. La métrique et
l'objet sont incrémentés atomiquement avant la création du reçu. Toute erreur
annule la transaction. Un retry exact retrouve le reçu et ne réapplique aucun
effet ; une divergence canonique est permanente. Deux événements visant le même
agrégat utilisent un upsert atomique.

Les six correspondances sont :

| Événement | Métrique |
| --- | --- |
| `faluss-hub.portal.viewed` | `hub.portal.raw_views` |
| `faluss-hub.app.opened` | `hub.app.opens` |
| `faluss-hub.daily-reward.claimed` | `hub.daily_reward.claims` |
| `faluss-me.card.viewed` | `me.card.raw_views` |
| `faluss-me.link.clicked` | `me.link.clicks` |
| `faluss-me.collection.opened` | `me.collection.opens` |

## Read-model et suppression

`Faluss_Analytics::summary()` est une façade PHP privée sans endpoint. Elle
produit `analytics.summary` `1.0.0`, refuse une période de plus de 800 jours,
borne la lecture aux 25 mois conservés, utilise un snapshot SQL cohérent et
limite les breakdowns d'objets à 200 dans un ordre déterministe. Une métrique
absente est omise plutôt que transformée en faux zéro. Les visiteurs uniques
restent toujours `not_supported`.

`Faluss_Analytics::delete_subject()` supprime transactionnellement les métriques
et objets du sujet haché. Les reçus restent jusqu'à leur expiration afin qu'un
retry tardif ne recrée pas les agrégats supprimés.

## Rétention

Le hook unique `faluss_analytics_run_retention`, planifié toutes les heures,
utilise l'heure UTC, un verrou consultatif et des lots de 50. Il supprime les
reçus arrivés à 30 jours et les agrégats dépassant 25 mois dans une même
transaction vérifiée. La désactivation retire uniquement ce hook ; aucune
désinstallation ne supprime table ou donnée.

## Recette WordPress limitée

1. Sauvegarder `faluss.com`.
2. Installer Faluss Analytics uniquement sur `faluss.com`.
3. Vérifier la version `0.1.0` et le schéma `1`.
4. Vérifier les trois tables vides.
5. Vérifier l'unicité du hook de rétention.
6. Vérifier Events `0.3.1` et Federation `0.3.0` inchangés.
7. Ne modifier aucune politique Federation.
8. Ne produire aucun événement métier.
9. Ne pas installer ce ZIP sur `faluss.me`.
