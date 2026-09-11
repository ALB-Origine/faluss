# Faluss Production Reset — FPR-01

## Objet et limites

`Faluss Production Reset` est un plugin isolé, identique sur `faluss.me` et
`faluss.com`, destiné au seul reset pré-lancement coordonné FPR-01. Il ne
modifie aucun plugin Faluss existant, aucune table, aucun schéma ni réglage de
métier. Il ne touche pas au ledger ALB `*_token_engine_ledger`, aux projets,
règles, connecteurs ou entitlements Token Engine.

Le contrôle est volontairement inactif sur tout hôte autre que les hôtes
canoniques `faluss.me` et `faluss.com`. Il ne crée ni shortcode, ni écran
front-office, ni AJAX, ni cron. Le seul endpoint est le receiver Hub interne,
enregistré uniquement sur `faluss.com` pendant son armement explicite.

## Installation avant le lancement

1. Contrôler la sauvegarde exploitable des deux sites selon la procédure de
   l'hébergeur. FPR-01 n'en crée, n'en exporte et n'en télécharge aucune.
2. Installer le même fichier `faluss-production-reset-0.1.0.zip` sur
   `faluss.me` et `faluss.com`, puis activer **Faluss Production Reset** sur
   les deux installations.
3. Dans les deux `wp-config.php`, avant la ligne qui invite à arrêter les
   modifications, ajouter exactement la même valeur aléatoire, longue et
   unique, conservée hors Git :

   ```php
   define( 'FALUSS_PRODUCTION_RESET_SHARED_SECRET', 'valeur-aleatoire-longue-et-unique' );
   ```

   La constante est obligatoire, n'est jamais fournie par le ZIP, n'est jamais
   stockée dans une option WordPress et doit comporter au moins 32 caractères.
4. Depuis l'administration de chacun des deux sites, un utilisateur capable de
   `manage_options` ouvre **Outils → Mise en production Faluss** et saisit
   `ARMER LE RESET FALUSS`. Le reset ne peut pas commencer si l'un des deux
   sites n'est pas armé.

## Contrôle central et déroulement

La confirmation n'existe que sur `faluss.me`. Elle nécessite `manage_options`,
un nonce WordPress et la phrase exacte `METTRE FALUSS EN PRODUCTION`. Le
préflight est toujours relancé juste avant l'exécution. L'écran n'affiche que
des compteurs, jamais une adresse e-mail, un login, un `faluss_id`, une URL ou
un chemin de média.

1. `faluss.me` vérifie localement les tables requises, les candidats calculés
   côté serveur, les médias et le ledger PF, puis appelle le préflight Hub
   signé.
2. La moindre précondition inconnue ou invalide arrête le flux avant toute
   suppression.
3. Après confirmation, le Hub recalcule seul ses candidats, exécute son reset
   et signe une réponse composée de compteurs seulement.
4. `faluss.me` ne commence son reset local qu'après cette réponse Hub signée
   avec le statut `success`.
5. Les deux installations se désarment et se verrouillent. Elles ne peuvent
   être réutilisées qu'après un réarmement explicite et indépendant dans les
   deux administrations.

Une transaction distribuée et un rollback inter-sites n'existent pas. Si le
Hub a agi mais que le reset Identity échoue, le contrôle de `faluss.me` se
verrouille également avec un reçu `partial`; aucun retry automatique n'est
proposé. Les reçus ne conservent que `run_id`, statut, horodatages et
compteurs.

## Receiver Hub

Le receiver fixe `hub_member_reset_v1` n'accepte que les champs canoniques
`protocol`, `operation`, `phase`, `run_id`, `issued_at` et `nonce`. Il ne reçoit
jamais une liste de personnes, une table, un montant, un rôle, une cible ou une
URL. Il vérifie le HMAC SHA-256 du corps exact avec la constante, une fenêtre
de cinq minutes, le nonce atomiquement consommé une seule fois et la version
de protocole. Sa réponse est elle aussi HMAC-signée et limitée aux compteurs.

Après verrouillage, la route n'est plus enregistrée. Aucune sécurité ne repose
sur `Origin`, `Referer`, une URL cachée, un rôle transmis, une option front-end
ou un secret dans la base.

## Données retirées et préservées

Sur `faluss.me`, le plugin efface le contenu des tables Identity et Link de
membres prévues par FPR-01 : profils privés et publics, challenges OTP, rate
limits, codes et demandes d'autorisation, audit Identity, cards, blocks,
découvertes et réglages de découverte. Les clients SSO
`*_faluss_identity_clients`, les options et schémas, pages, modèles Elementor,
réglages WordPress et structure des tables restent intacts.

Sur `faluss.com`, il retire les liaisons `*_faluss_identity_links` et les états
`*_faluss_identity_client_state`. Sur les deux sites, les comptes liés non
privilégiés et tous les `subscriber` non privilégiés sont supprimés via
`wp_delete_user()`, avec leurs contenus membres. Tout compte capable de
`manage_options` est préservé, y compris ses rôles, contenus, médias et accès
d'administration; ses données Faluss dans les tables supprimées ne sont pas
préservées.

Le préflight exige que `*_token_engine_pf_ledger` existe et soit vide. Il ne
supprime jamais une ligne PF append-only. Il ne lit, ne modifie, ni ne référence
destructivement `*_token_engine_ledger` (ALB).

## Médias et caches

Avant toute suppression, le plugin rassemble uniquement les attachments dont
l'auteur est un membre candidat, les `avatar_attachment_id` des profils publics
Identity, les `cover_attachment_id` des cards Link et les `attachment_id` des
teasers Link `media_teaser`. Chaque élément doit être un attachment WordPress
appartenant à un membre non privilégié et ne doit pas être référencé par un
contenu conservé. Toute ambiguïté, métadonnée incomplète ou partage bloque le
préflight.

La suppression utilise exclusivement `wp_delete_attachment( $attachment_id,
true )`. Les chemins contrôlés sont ceux retournés par WordPress et les tailles
déclarées par ses métadonnées; leur absence est vérifiée après suppression. Le
plugin ne parcourt ni ne supprime récursivement `uploads`, ne déduit jamais un
nom de fichier et ne supprime aucun média plateforme, Elementor ou
administratif ambigu.

Seuls le cache objet WordPress et le hook LiteSpeed connu sont purgés. Les
sauvegardes hébergeur, CDN tiers et stockages externes ne sont ni effacés ni
contrôlés par FPR-01.

## Recette WordPress réelle (à exécuter séparément)

La recette ci-dessous est une procédure de mise en production; elle n'est pas
une preuve fournie par les tests statiques du dépôt.

1. Après sauvegarde, installer et activer le ZIP vérifié sur les deux sites,
   poser la constante identique puis armer explicitement les deux sites.
2. Depuis `faluss.me`, faire le préflight et confirmer que les compteurs sont
   attendus et que le PF ledger est vide.
3. Confirmer le reset, puis vérifier que les administrateurs restent
   connectables et que tous les membres Identity et Hub sont absents.
4. Vérifier que profils publics et routes associées ne rendent plus de carte,
   que les médias membres ne sont plus dans la médiathèque ni à leurs chemins
   WordPress, et que le client SSO de `faluss.com` reste configuré.
5. Vérifier que `*_token_engine_ledger` ALB est inchangé, que
   `*_token_engine_pf_ledger` est vide, et que le bouton central comme le
   receiver Hub sont verrouillés.
6. Vérifier qu'une inscription passwordless avec une ancienne adresse e-mail
   de test reste possible immédiatement.

Ne déclarer cette recette exécutée qu'après son exécution effective sur les deux
installations concernées.
