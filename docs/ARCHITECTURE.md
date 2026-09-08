# Architecture cible

## Topologie

```text
faluss.me
  └── Faluss Identity (autorité d'identité) + profil public + Token Engine Connector

faluss.com
  └── Faluss Hub (compte et vues transverses) + Faluss Subscriptions (niveaux Gratuit/Pro) + Token Engine (ledger économique central)

pro.faluss.com
  └── Altlab Platform + Faluss Identity Client + Token Engine Connector (futur usage)

date.faluss.com
  └── Date + Faluss Identity Client + Token Engine Connector (futur usage)
```

Les installations WordPress, leurs tables `users`, leurs sessions et leurs bases de données restent séparées. Les sites peuvent être sur le même serveur, mais ne se font pas confiance par défaut.

## Modèle Elementor de profil public

Un administrateur peut sélectionner une page Elementor déjà publiée dans **Réglages → Profil public Faluss**. Les routes `faluss.me/identifiant` rendent alors cette page ; le widget **Profil public Faluss**, sans identifiant renseigné, reçoit l’identifiant de la route. Le plugin ne crée aucune page. Si aucun modèle valide n’est sélectionné ou qu’Elementor est indisponible, le rendu autonome du profil est conservé.

## Onboarding de carte (ONB-01 / ONB-02)

La preuve passwordless crée ou active seulement l’identité opaque. La création facultative de carte est ensuite un flux distinct rendu par le widget **Onboarding Faluss** ou son shortcode. L’administrateur peut sélectionner une page Elementor déjà publiée dans **Réglages → Onboarding Faluss** : son URL réelle est la destination canonique, sans dépendre d’un slug imposé. Aucune page n’est créée automatiquement et `/commencer` reste le rendu de secours sans page sélectionnée.

Le navigateur ne transporte qu’une poignée opaque, courte et expirante dont le serveur conserve l’intention autorisée et le retour local validé. La priorité de retour est : consentement SSO local, retour exact d’un teaser ou d’une récompense, ouverture de l’onboarding pour une intention de carte, ouverture de l’onboarding pour une décision encore requise, puis retour générique. `faluss_id` reste entièrement serveur. La réservation finale insère atomiquement le slug dans le registre public FI-03 sous forme de brouillon minimal.

ONB-02 transforme ensuite ce brouillon sur la même surface Elementor en étapes enregistrées : Identity reste seul propriétaire du nom, de la bio, de l’avatar, de la publication et des liens publics ; Faluss Link reste seul propriétaire des préférences visuelles, réseaux et blocs. Le seul état ajouté au profil Identity existant est la prochaine étape reprenable : aucune table d’onboarding, copie d’identité ni source concurrente n’est créée. Le brouillon reste non rendu publiquement jusqu’à l’action finale idempotente de publication.

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

Faluss Subscriptions, sur `faluss.com`, est l’autorité des abonnements, essais
et droits de niveau Gratuit/Pro. Il utilise le Faluss ID existant comme clé
opaque mais ne crée ni identité, ni session, ni transport intersite. Faluss Hub
pourra agrèger ultérieurement des projections minimales, par exemple « Pro
activé ». Une donnée ne remonte jamais par défaut : chaque projection est
spécifiée, validée et révocable.

Faluss Identity ne porte ni abonnement, ni portefeuille ALB ni cosmétique. Faluss.me consommera ultérieurement des droits d’abonnement et un inventaire cosmétique propres à sa carte publique ; les objets acquis avec ALB ou progression restent possédés, tandis que les avantages inclus à l’abonnement sont temporaires.

SUB-01B ajoute dans Faluss Subscriptions l’adaptateur Stripe central serveur :
Customer lié au Faluss ID opaque, Checkout et Customer Portal hébergés, webhook
Stripe signé, migration additive et résolution d’états. Aucun appelant membre,
bouton de vente, connecteur Faluss.me ou projection Link n’est livré : les
services restent privés et la sandbox est réservée à l’administration test.

## Économie partagée

Token Engine, installé une seule fois sur l’instance économique, est le détenteur générique du ledger, des règles, des droits, de l’idempotence et des projections de solde. Il reçoit un `subject_id` opaque ; le Connector TE-02 peut lui fournir le `faluss_id` déjà actif sans répliquer l’identité. Les sites dérivés n’installent jamais le cœur. TE-03 ajoute seulement la demande privée d’une règle globale `daily_reward`, protégée par la permission distincte `reward.claim` : le Core décide, verrouille et inscrit le crédit. EC-02 ajoute des définitions de droits et des attributions historisées, lues seulement via `entitlements.read`; Catalogue porte la métadonnée de droit d’un thème et Faluss Link revalide la décision côté serveur pour chaque sélection de thème et chaque teaser visuellement réservé, sans conserver une copie locale. Cette couche teaser ne sécurise pas encore le fichier média. Faluss Link ne modifie jamais un ledger et ne garde aucune règle, solde, droit ou idempotence locale. Les contrats sont documentés dans `TOKEN_ENGINE.md` et `TOKEN_ENGINE_CONNECTOR.md`.

## Bibliothèque privée de découvertes

Mes découvertes est une donnée locale de Faluss Link, uniquement accessible au membre Faluss actif qui la possède. La route publique résout d’abord un profil Identity publié puis, seulement pour un autre membre connecté et ayant laissé l’enregistrement actif, actualise une paire de références d’identité côté serveur. Cette écriture ne contient ni contenu du profil ni donnée analytique de navigation. Elle n’est transmise à aucun Core, Connector, catalogue ou site tiers et n’est jamais exposée au propriétaire du profil découvert. Toute future analytique créateur doit être un système séparé, avec sa propre finalité et ses propres données ; elle ne peut pas dériver de cette bibliothèque.
