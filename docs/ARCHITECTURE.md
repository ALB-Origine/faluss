# Architecture cible

## Topologie

```text
faluss.me
  └── Faluss Identity (autorité d'identité) + profil public

faluss.com
  └── Faluss Hub (compte, facturation et vues transverses)

pro.faluss.com
  └── Altlab Platform + Faluss Identity Client

date.faluss.com
  └── Date + Faluss Identity Client
```

Les installations WordPress, leurs tables `users`, leurs sessions et leurs bases de données restent séparées. Les sites peuvent être sur le même serveur, mais ne se font pas confiance par défaut.

## Identifiants

| Élément | Portée | Usage |
|---|---|---|
| `faluss_id` | écosystème | identité stable, UUID opaque |
| `wp_user_id` | une installation | session et données métier locales |
| `client_id` | une application déclarée | identification SSO de l'application |

Le client stocke une liaison unique `wp_user_id ↔ faluss_id`. La liaison est créée seulement après l'échange de code validé par Faluss Identity.

## Découplage métier

Faluss Identity ne connaît pas les commandes, acquisitions, tokens, progression ou données de rencontre. Il ne renvoie que les attributs consentis au client, dans un scope déterminé.

Faluss Hub agrège ultérieurement des états minimaux, par exemple « Pro activé ». Une donnée ne remonte jamais par défaut : chaque projection est spécifiée, validée et révocable.
