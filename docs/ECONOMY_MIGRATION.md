# EC-01 — Plan de migration économique

## Statut et garde-fous

Ce plan n’exécute aucune migration. Il ne supprime, recrée ou modifie aucune donnée Altlab ou Faluss. Les règles et données historiques Altlab restent autoritatives tant que les conditions de bascule ci-dessous ne sont pas démontrées.

## Étapes proposées

1. Relever dans les composants Altlab confirmés les règles d’acquisition, de droit et les événements existants, avec leur origine, leur historique et leurs comportements de correction.
2. Conserver les données historiques en place et introduire, à côté, un adaptateur `pro.faluss` qui traduit des faits métier confirmés vers le contrat Faluss Economy.
3. Faire consommer le même contrat par faluss.me et date.faluss.com, sans leur confier de ledger, de solde ou de règle locale.
4. Valider les clés d’idempotence, les références source, les décisions de règles et les scénarios de répétition/correction avant toute écriture économique partagée.
5. Basculer seulement lorsque les projections comparées, l’historique conservé et les non-régressions des plugins Altlab actuels sont démontrés ; conserver un retour sûr vers l’autorité existante tant que ce n’est pas le cas.

## Conditions de bascule

- Le modèle Altlab effectivement observé est documenté, sans supposer de schéma non lu.
- Chaque événement accepté a une clé d’idempotence et une référence source vérifiables.
- Les données historiques restent lisibles et les plugins Altlab courants gardent leur comportement.
- Les droits, récompenses et corrections sont testés contre le moteur partagé avant tout usage public.

## Ordre des lots ultérieurs

1. Moteur partagé Faluss Economy.
2. Entitlements communs.
3. Daily reward Faluss Link comme simple demande au moteur.
4. Usages commerce et cosmétiques, toujours décidés par les moteurs communs.

Les règles d’accès, paiement, token et abonnement ne seront jamais déplacées dans Faluss Link.
