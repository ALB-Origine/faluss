# Faluss Events 0.1.0 — runtime de contrats

## Frontière

Faluss Events matérialise les validateurs PHP de production des contrats
`faluss.event-source-catalog` 1.0.0 et `faluss.event` 1.0.0. Le plugin ne crée
ni table, migration, option, route, écran, shortcode, cron, worker, file,
cookie, asset ou donnée membre. Il ne publie, ne stocke et ne livre aucun
événement.

La version 0.1.0 n'enregistre aucun catalogue ni provider Hub ou Me. Elle
prépare seulement une API PHP fermée pour un futur propriétaire et la lecture
signée d'un catalogue par Faluss Federation 0.2.0. Sans Federation ou Faluss
Apps Registry, le plugin se charge sans erreur mais refuse l'intégration.

## Architecture interne

- `Faluss_Events_Catalog_Validator` contrôle le catalogue fermé, ses formats,
  namespaces, définitions, politiques, destinations, limites, compatibilité et
  contenu interdit. Sa validation croisée réutilise obligatoirement
  `Faluss_Apps_Registry_Manifest_Validator`.
- `Faluss_Events_Envelope_Validator` lie une enveloppe au catalogue exact,
  applique le temps, le sujet, l'acteur, l'objet, les destinations et les
  limites de payload, puis appelle seulement un validateur spécialisé
  enregistré localement par code de confiance.
- `Faluss_Events` possède les registres fermés de providers de catalogue et de
  validateurs de payload. Un doublon de tuple rend le registre de catalogues
  indisponible globalement.
- l'adaptateur Federation enregistre le validateur du contrat de catalogue,
  puis seulement les descriptors explicitement fournis par un futur plugin
  propriétaire. Le chargement ne déclenche aucun transport.

## Enregistrement propriétaire futur

`Faluss_Events::register_catalog_provider()` accepte exactement :

```php
array(
    'owner_app_key'       => 'owner-app',
    'capability_key'      => 'owner-app.events',
    'catalog_version'     => '1.0.0',
    'catalog_provider'    => $trusted_catalog_callback,
    'cap_manifest_provider' => $trusted_manifest_callback,
)
```

Les callbacks viennent uniquement du PHP serveur installé. Ils retournent
chacun `payload_contract` et `payload`. Le provider Federation appelle d'abord
le provider CAP, puis le provider EVT ; il valide les deux documents et leur
cohérence avant de rendre uniquement le catalogue. Une absence vaut
`not_available`, une incohérence `incompatible` et une exception
`temporarily_unavailable`.

`Faluss_Events::register_payload_validator()` associe un type de document et
une version exacts à un callback PHP de confiance. Aucun nom de classe ou de
fonction reçu par le réseau, le catalogue ou le navigateur n'est exécuté.

## Lecture distante

`Faluss_Events::read_remote_catalog()` effectue exactement deux lectures
signées dans cet ordre : `manifest.read`, puis `event_catalog.read`. Chaque
réponse est liée au pair, fraîche et validée séparément ; la validation croisée
CAP/EVT est ensuite obligatoire. Il n'existe ni cache, transient, document
stale, catalogue vide inventé, lecture de table distante ou transport de
secours.

La requête `event_catalog.read` porte `subject_context: null` et exactement
`owner_app_key`, `capability_key`, `catalog_version`. Elle ne porte ni audience,
Faluss ID, événement, payload, destination, URL ou matériau cryptographique.
La politique du pair doit autoriser explicitement l'opération, l'owner et la
capacité ; aucune politique existante n'est modifiée automatiquement.

## Recette WordPress limitée

1. Sauvegarder les deux installations.
2. Mettre Faluss Federation à jour vers 0.2.0 sur les deux sites.
3. Installer Faluss Events 0.1.0 sur les deux sites.
4. Ne modifier aucune politique Federation.
5. Vérifier Federation `ready` et le schéma 1.
6. Vérifier Faluss Events chargé, sans provider de catalogue.
7. Relancer `diagnostic.read` dans les deux directions.
8. Retester les manifestes Hub et Me.
9. Vérifier qu'aucune table Faluss Events n'a été créée.
10. Confirmer qu'aucun événement ou tracking n'est actif.

`event_catalog.read` reste refusé ou `not_available` tant qu'AN-01 n'a livré
aucun provider et qu'aucune politique explicite ne l'autorise.
