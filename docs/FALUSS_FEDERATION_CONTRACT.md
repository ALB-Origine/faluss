# FED-01A — Contrat du transport privé fédéré Faluss

## Statut et frontière

FED-01A définit uniquement le contrat du futur transport privé fédéré entre des
nœuds Faluss approuvés : `faluss.com`, `faluss.me` et un domaine futur déclaré
explicitement. Il ne livre aucun plugin, route, table, migration, option,
écran, clé, appel réseau, donnée membre, cache durable, asset ou archive.
L'implémentation est réservée à FED-01B, après ce contrat et avant CAP-01B.

Le transport est bidirectionnel et strictement serveur-à-serveur. Il ne passe
jamais par le navigateur, ne partage aucune session WordPress, ne donne aucun
accès direct aux tables d'un autre moteur et n'est ni un registre métier, ni un
broker, ni un relais d'impersonation, ni un canal de code exécutable. Il ne
porte aucune mutation économique ou commerciale générique.

FPR, Token Engine Connector, Faluss Identity (secret client, codes, PKCE et
session) et Stripe/Faluss Subscriptions gardent leurs transports spécialisés.
Ils ne sont ni encapsulés, ni routés, ni remplacés par Federation.

## Nœuds, clés et politique locale

Chaque nœud possède sa propre paire Ed25519. La clé privée reste hors Git et
hors base/options WordPress exportables, dans une configuration protégée du
serveur. La clé publique et ses métadonnées peuvent être enregistrées par le
runtime futur seulement. Il n'existe aucune clé partagée globale et aucune clé
FPR ne peut être réutilisée.

Un `key_id` est opaque, versionné et borné. Son état local fermé est `active`,
`rotating`, `revoked` ou `expired`. Une rotation accepte au plus une ancienne
et une nouvelle clé pendant une fenêtre bornée ; une clé `revoked` est refusée
immédiatement. Ed25519 est l'unique algorithme : HMAC, RSA, crypto maison,
signature absente et mécanisme de repli sont interdits. Si Sodium est absent
dans un runtime futur, ce runtime échoue fermé sans erreur fatale et n'émet ni
n'accepte aucun échange.

La confiance est une politique locale attachée à une clé publique : identité
exacte du nœud et de l'application émetteurs, opérations admises, applications
propriétaires, capacités exactes, audiences maximales, état de clé et période
de validité. Aucun wildcard global, rôle WordPress, authentification navigateur,
origine, IP, `Referer` ou `Origin` n'élargit cette politique. Un manifeste
distant n'installe pas une clé et ne crée pas de confiance.

## Transport réservé et limites

La seule route réservée au runtime futur est :

```text
POST /wp-json/faluss-federation/v1/exchange
```

Elle n'est pas enregistrée par FED-01A. Toute cible future utilise HTTPS au
port canonique, sans query string, fragment, userinfo ni redirection, avec
`sslverify` actif et `Content-Type: application/json`. CORS ne confère aucun
droit. La résolution d'hôte, les redirections, `Origin`, `Referer` et l'adresse
IP ne sont pas des preuves d'identité.

FED-01B bornera explicitement taille brute de requête/réponse, profondeur JSON,
nombre de champs et d'éléments, taille de chaîne, délai de connexion, délai
total, débit par clé/opération et erreurs cryptographiques. Les plafonds
contractuels sont 65 536 octets, profondeur 16, 128 champs/éléments, chaînes
de 4 096 octets, connexion 3 secondes et total 10 secondes. Les réponses
porteront `Cache-Control: private, no-store` et `X-Content-Type-Options:
nosniff`; aucun payload métier n'est durablement mis en cache.

## Enveloppe de requête

Le schéma autonome Draft 2020-12
[`faluss-federation-request.schema.json`](../contracts/faluss-federation-request.schema.json)
impose une enveloppe fermée :

- `protocol_version` vaut `1` et `message_type` vaut `request` ;
- `request_id` est un UUID v4 ;
- `operation` appartient exclusivement à `diagnostic.read`, `manifest.read`
  ou `read_model.read` ;
- `sender` contient le `node_id`, l'`app_key` et le `key_id` exacts ;
  `recipient` contient le nœud et l'application exacts ;
- `issued_at` et `expires_at` sont RFC3339 UTC ; la durée maximale est cinq
  minutes et la dérive d'horloge serveur maximale est 60 secondes ;
- `nonce` est une valeur aléatoire forte, base64url sans padding, bornée et
  non rejouable ;
- `subject_context` est soit `null`, soit seulement un `subject_faluss_id`
  UUID v4 et l'audience demandée ;
- `parameters` est fermé par opération.

`subject_context` est nul pour `diagnostic.read` et `manifest.read`. Pour
`read_model.read`, le contrat spécialisé décide si le contexte sujet est
obligatoire : lorsqu'il lie le document à un sujet, il l'est. Le Faluss ID ne
vient jamais d'un navigateur, n'apparaît jamais dans une URL, le DOM, un cache
public ou un journal, et ne constitue jamais seul une autorisation. Le
producteur le résout et le réautorise côté serveur.

| Opération | Paramètres admis | Résultat interdit |
| --- | --- | --- |
| `diagnostic.read` | objet vide | sujet, secret, chemin serveur, version PHP, table, option ou configuration |
| `manifest.read` | `app_key`, `requested_manifest_version` nullable | Faluss ID, audience, membre ou octets d'asset |
| `read_model.read` | application propriétaire, capacité exacte, type de document, version de contrat, audience exacte | wildcard, action déléguée ou mutation |

`diagnostic.read` ne rend que protocole, nœud, clé, permissions et horloge.
`manifest.read` ne rend qu'un manifeste non-membre et ses métadonnées ; un
asset éventuel reste une référence, jamais ses octets. Pour
`read_model.read`, le producteur peut réautoriser, réduire ou refuser et le
transport ne transforme jamais le read-model reçu.

## Canonicalisation, signature et anti-rejeu de requête

Le corps JSON est sérialisé une seule fois. Le même octet brut est haché
SHA-256 hexadécimal minuscule, envoyé et vérifié avant toute relecture JSON.
La signature Ed25519 détachée est base64url sans padding. Les en-têtes futurs
obligatoires sont :

```text
X-Faluss-Federation-Key-Id
X-Faluss-Federation-Content-SHA256
X-Faluss-Federation-Signature
```

La chaîne canonique de requête est formée dans cet ordre fixe, séparée par LF :
`protocol_version`, méthode HTTP, chemin exact, `sender.node_id`,
`recipient.node_id`, `sender.key_id`, `issued_at`, `expires_at`, `nonce` et
SHA-256 hexadécimal des octets bruts. Les valeurs de l'enveloppe, des en-têtes,
de la chaîne et du corps doivent toutes correspondre ; tout écart est rejeté
avant le dispatch.

Le nonce provient de `random_bytes` dans le runtime futur. Après vérification
crypto et avant dispatch, sa consommation est atomique sur le triplet
`(sender_node_id, key_id, nonce)`. Un `request_id` ne peut jamais être lié à un
hash de corps différent. La rétention anti-rejeu est au minimum la durée de la
requête plus la dérive admise. Une réémission automatique ne contourne pas ces
contrôles.

## Opérations closes et interdictions

La liste précédente est exhaustive. Sont notamment interdits :

- `event.publish` et tout transport EVT avant son contrat spécialisé ;
- RPC arbitraire, action déléguée générique, écriture de profil ou de registre ;
- claim, PF, débit, crédit, achat, paiement, entitlement, cosmétique, Fans,
  Shop, progression ou quête ;
- upload, proxy média, contenu binaire, copie de contenu privé ou lecture de
  table distante ;
- partage/réutilisation FPR, Identity ou Token Connector, HMAC, RSA, crypto
  maison, repli non signé, navigateur ou session WordPress.

Toute demande invalide, non autorisée, expirée, incompatible ou non conforme
échoue fermée. Elle n'entraîne ni accès direct, ni cache ancien, ni valeur
inventée, ni deuxième transport de secours.

## Enveloppe et signature de réponse

Le schéma autonome Draft 2020-12
[`faluss-federation-response.schema.json`](../contracts/faluss-federation-response.schema.json)
impose `protocol_version: "1"`, `message_type: "response"`, le `request_id`
et le hash de la requête, l'identité complète du répondeur, le destinataire,
des dates UTC bornées, un statut fermé, `payload_contract`, `payload` et
`error`.

Les statuts sont exclusivement `success`, `empty`, `not_available`,
`not_authorized`, `incompatible`, `temporarily_unavailable`, `invalid_request`
et `replay_rejected`. Un succès porte obligatoirement un type et une version de
contrat de payload exacts avec un payload non vide. `empty` porte exactement
`{}` et aucun contrat inventé. Les autres statuts n'emportent aucun payload ;
ils peuvent seulement produire une erreur publique, bornée et non sensible.

La réponse est sérialisée une seule fois et signée Ed25519. Sa chaîne canonique
séparée par LF lie dans cet ordre : `protocol_version`, statut HTTP,
`request_id`, hash du corps de requête, `responder.node_id`,
`recipient.node_id`, `responder.key_id`, `generated_at`, `expires_at` et
SHA-256 hexadécimal du corps brut de réponse. Le consommateur vérifie identité
du répondeur, état/période de clé, signature, liaison requête/hash,
destinataire, fraîcheur et contrat spécialisé du payload. Une signature valide
ne remplace pas la validation du contrat de payload.

## Confidentialité, audit et coordination

Un audit futur peut contenir seulement identifiant de requête, nœuds, opération,
capacité, résultat non sensible, instant UTC, durée et code de diagnostic
opaque. Il n'enregistre jamais Faluss ID, corps, payload, signature, matériau
de clé, nonce brut, e-mail, session, URL membre, paiement ou contenu privé.
Aucune réponse de diagnostic ne divulgue secret, chemin, table, option,
configuration ou information d'infrastructure.

Les manifestes signés et acceptés sont la source du futur registre :
applications, capacités, bindings et actions n'en sont qu'un sous-ensemble.
Le registre ne peut rien ajouter qui soit absent d'un manifeste ; il revalide
version et compatibilité. L'origine d'un manifeste ne crée aucune confiance
cryptographique. FED authentifie et borne l'échange seulement ; CAP-01B
résoudra le registre et ses bindings.

Federation ne devient jamais propriétaire d'un module Master Profile. MP-01A
conserve contrat spécialisé, audience, fraîcheur, absence et mode fantôme. Une
enveloppe serveur peut porter un Faluss ID uniquement dans le contexte ci-dessus
et le retire avant rendu. Aucun transport n'autorise table directe ni copie
persistante ancienne de read-model.

## Portée vérifiable

FED-01A est limité exactement à neuf artefacts : ce contrat, les deux schémas,
son test et les cinq mises à jour de coordination CAP/MP/architecture/modèle de
données/roadmap listées dans `x-fed01a-scope`. Il ne modifie aucun fichier sous
`plugins/`, aucun transport existant, runtime CAP-01B/EVT/MP, interface,
migration, donnée WordPress réelle ou asset. Il ne fournit aucune recette
WordPress, puisqu'aucun runtime n'est installé.
