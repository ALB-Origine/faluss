# Sécurité

## Réemploi du passwordless Altlab

Faluss Identity reprend les garanties déjà présentes dans Altlab Member : challenge de 256 bits, secret navigateur séparé de 256 bits, cookie `Secure`/`HttpOnly`/`SameSite=Lax`, OTP haché, validité de dix minutes, cinq tentatives, consommation atomique et rate limiting transactionnel par e-mail et IP hachés.

## Ajouts imposés par le SSO

- PKCE S256 obligatoire, jamais `plain` ;
- code d'autorisation opaque, haché, consommé une fois, TTL 60 secondes ;
- `state` vérifié côté client avant tout échange ;
- URI de retour préenregistrée et comparée exactement ;
- secret client hors Git, non stocké en clair et révocable ;
- validation finale du statut du profil avant émission de code ;
- comptes et rôles privilégiés exclus du passwordless ;
- aucune erreur ne révèle si un e-mail, un Faluss ID ou un client existe ;
- aucune clé de production dans les options exportables WordPress.

## Cloisonnement

L'identité et la présence dans une app ne donnent aucun droit métier dans une autre. Date doit implémenter ses propres contrôles d'âge, consentements, signalements, blocages et modération ; Faluss Identity n'en est pas dépositaire.
