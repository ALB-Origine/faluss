# Roadmap Faluss Identity

## FI-00 — Fondation documentaire

Valider les contrats, règles de sécurité et migration Altlab. Aucun flux actif.

## FI-01 — Plugin Faluss Identity

Créer le bootstrap, migrations vérifiées, tables Identity, profils `faluss_id` et diagnostic d'administration. Pas de SSO ni de formulaire public.

## FI-02 — Passwordless central

Adapter la cérémonie Altlab sous les noms Faluss, avec ses tests de concurrence et de rôles. Le seul résultat est une session locale sur `faluss.me` et un profil Identity actif.

## FI-03 — Profil public Faluss.me

Créer le profil public rattaché au Faluss ID : identifiant stable, nom, bio, avatar, publication et liens externes ordonnés. Aucun annuaire, recherche ni donnée métier.

## FI-04 — Autorisation et échange de code

Implémenter PKCE, `state`, code 60 secondes, consommation atomique et endpoint d'échange. Couvrir les refus client, URI, scope, PKCE, expiration et rejeu.

## FI-05 — Plugin client et intégration Altlab

Créer la liaison locale, la session WordPress locale et le parcours de connexion. L'activer d'abord dans Altlab derrière un drapeau de fonctionnalité. Aucun plugin de droits, Commerce ou Token Engine n'est modifié dans ce lot.

## FI-06 — Préparation Pro

Documenter l'export/import de liaison et des données Pro avant le changement de domaine. Réaliser la migration seulement après recette fonctionnelle complète.

## Hors périmètre initial

Facturation centralisée, dashboard Faluss.com, personnalisation complète de faluss.me, tableaux agrégés et intégration Date. Ils viennent après la validation FI-05.
