# Architecture cible

## Topologie

```text
faluss.me
  └── Faluss Identity (autorité d'identité) + profil public + Token Engine Connector

faluss.com
  └── Faluss Hub (compte, facturation et vues transverses) + Token Engine (ledger économique central)

pro.faluss.com
  └── Altlab Platform + Faluss Identity Client + Token Engine Connector (futur usage)

date.faluss.com
  └── Date + Faluss Identity Client + Token Engine Connector (futur usage)
```

Les installations WordPress, leurs tables `users`, leurs sessions et leurs bases de données restent séparées. Les sites peuvent être sur le même serveur, mais ne se font pas confiance par défaut.

## Modèle Elementor de profil public

Un administrateur peut sélectionner une page Elementor déjà publiée dans **Réglages → Profil public Faluss**. Les routes `faluss.me/identifiant` rendent alors cette page ; le widget **Profil public Faluss**, sans identifiant renseigné, reçoit l’identifiant de la route. Le plugin ne crée aucune page. Si aucun modèle valide n’est sélectionné ou qu’Elementor est indisponible, le rendu autonome du profil est conservé.

## Identifiants

| Élément | Portée | Usage |
|---|---|---|
| `faluss_id` | écosystème | identité stable, UUID opaque |
| `wp_user_id` | une installation | session et données métier locales |
| `client_id` | une application déclarée | identification SSO de l'application |

Le client stocke une liaison unique `wp_user_id ↔ faluss_id`. La liaison est créée seulement après l'échange de code validé par Faluss Identity.

Le client conserve l’état SSO éphémère sous empreinte serveur et cookie `HttpOnly`, puis consomme cet état une seule fois avant l’échange PKCE. Un e-mail existant sans liaison ne peut jamais déclencher de rapprochement automatique : l’utilisateur ouvre d’abord sa session locale et lie explicitement son Faluss ID.

## Découplage métier

Faluss Identity ne connaît pas les commandes, acquisitions, tokens, progression ou données de rencontre. Il ne renvoie que les attributs consentis au client, dans un scope déterminé.

Faluss Hub agrège ultérieurement des états minimaux, par exemple « Pro activé ». Une donnée ne remonte jamais par défaut : chaque projection est spécifiée, validée et révocable.

Faluss Identity ne porte ni abonnement, ni portefeuille ALB ni cosmétique. Faluss.me consommera ultérieurement des droits d’abonnement et un inventaire cosmétique propres à sa carte publique ; les objets acquis avec ALB ou progression restent possédés, tandis que les avantages inclus à l’abonnement sont temporaires.

## Économie partagée

Token Engine, installé une seule fois sur l’instance économique, est le détenteur générique du ledger, des règles, de l’idempotence et des projections de solde. Il reçoit un `subject_id` opaque ; le Connector TE-02 peut lui fournir le `faluss_id` déjà actif sans répliquer l’identité. Les sites dérivés n’installent jamais le cœur. TE-03 ajoute seulement la demande privée d’une règle globale `daily_reward`, protégée par la permission distincte `reward.claim` : le Core décide, verrouille et inscrit le crédit. Faluss Link ne modifie jamais un ledger et ne garde aucune règle, solde ou idempotence locale. Les contrats sont documentés dans `TOKEN_ENGINE.md` et `TOKEN_ENGINE_CONNECTOR.md`.
