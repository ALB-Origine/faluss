# Migration Altlab Platform vers Faluss Identity

## Principe

Altlab Platform conserve ses utilisateurs WordPress, commandes WooCommerce, droits, produits possédés, progression et tokens. Seule l'entrée de connexion change progressivement.

## Phases

1. **Adaptateur inactif** — installer Faluss Identity Client dans Altlab sans changer le parcours actuel.
2. **Liaison volontaire** — un membre Altlab prouve son e-mail via Faluss Identity, puis le client crée la liaison avec son `wp_user_id` actuel.
3. **Double parcours contrôlé** — le SSO est activé pour les comptes liés. Le passwordless Altlab ne sert plus qu'aux comptes historiques non liés et aux parcours de récupération explicitement documentés.
4. **Bascule** — après recette et export du diagnostic de liaisons, Faluss devient l'unique entrée membre. Les administrateurs restent hors SSO public.
5. **Migration de domaine** — la même liaison est importée vers `pro.faluss.com`; ses utilisateurs WordPress reçoivent de nouveaux IDs locaux, mais les Faluss IDs et les droits métiers migrés restent inchangés.

## Contraintes

- Aucun rapprochement silencieux par e-mail entre deux comptes locaux.
- Aucun compte privilégié Altlab n'est lié via le flux membre.
- Les commandes invitées continuent d'être revendiquées par Altlab selon son mécanisme actuel ; Faluss Identity ne traite pas de commandes.
- Le rollback consiste à désactiver le bouton SSO et les nouvelles liaisons, jamais à supprimer des comptes ou droits.
