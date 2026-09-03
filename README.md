# Faluss

Socle technique de l'écosystème Faluss, propriété d'Alternative LAB.

## Périmètre initial

- `faluss.me` : identité centrale, connexion passwordless et profil public ;
- `faluss.com` : hub de compte, droits transverses et facturation future ;
- `pro.faluss.com` : première application cliente, issue d'Altlab Platform ;
- `date.faluss.com` : application cliente isolée, sans remontée de données intimes vers le hub.

Le dépôt contient deux plugins WordPress distincts :

```text
plugins/
├── faluss-identity/          # installé seulement sur faluss.me
└── faluss-identity-client/   # installé sur chaque application cliente
```

Le contrat d'identité est décrit dans [docs/IDENTITY_PROTOCOL.md](docs/IDENTITY_PROTOCOL.md). Aucun plugin métier (commerce, droits, progression, Token Engine) ne doit dépendre d'un autre identifiant que le `wp_user_id` local de son site.

## Règle de développement

Le `faluss_id` est l'identité transversale. Le `wp_user_id` reste local à chaque WordPress. Ils sont associés, jamais substitués.

## Démarrage

1. Lire `AGENTS.md`, puis les documents de `docs/` concernés.
2. Implémenter la vertical slice FI-01 à FI-05 dans l'ordre de `docs/ROADMAP.md`.
3. Ne jamais activer le SSO sur Altlab Platform avant la recette de migration documentée.

## Interdictions

- aucun secret, token, export SQL, donnée membre ou fichier `.env` réel dans Git ;
- aucun partage de base WordPress, de cookie ou de session entre domaines ;
- aucun détail de Date dans Faluss Identity, Faluss Hub ou Pro ;
- aucun droit d'accès métier décidé par Faluss Identity.
