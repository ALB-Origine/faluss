# Faluss Subscriptions — SUB-01B

## Autorité centrale et frontières

Faluss Subscriptions, installé exclusivement sur faluss.com, est l’autorité centrale des niveaux Gratuit/Faluss Max, essais, abonnements Stripe, attributions administratives, audit et résolution. Sa clé métier est le faluss_id opaque de Faluss Identity : il ne crée ni identité, session, e-mail, donnée de carte publique, Token Engine ou projection Link.

| Système | Responsabilité | Hors responsabilité |
|---|---|---|
| Faluss Identity | identité, session, Faluss ID | paiement, droit Faluss Max |
| Faluss Subscriptions | catalogue, Stripe, essai, droit Faluss Max, audit | contenu Link et Token Engine |
| Faluss Link / produits | appliquer une décision contractuelle future | calculer ou stocker l’abonnement |
| Stripe | Checkout, Customer Portal, paiement | identité Faluss et autorisation produit |

SUB-01B ne crée aucun bouton de vente, bannière, shortcode, AJAX ou route REST anonyme de Checkout. Les accès sont des services PHP privés et l’administration WordPress protégée. Aucun consommateur Faluss n’est encore relié à ce droit : les fonctions Link restent inchangées. Token Engine ne reçoit aucune écriture ni projection. FL-21 reste le lot séparé qui pourra consommer explicitement une décision de droit validée.

## Catalogue Faluss Max

| Clé | Offre | Période | Prix TTC | Devise | Essai | Carte |
|---|---|---:|---:|---|---:|---|
| free | Faluss Gratuit | — | 0 | EUR | 0 | non |
| pro | Faluss Max | mensuel | 999 centimes | EUR | 15 jours | obligatoire |
| pro | Faluss Max | annuel | 9900 centimes | EUR | 15 jours | obligatoire |

Avant Checkout et avant qu’un webhook puisse affecter un droit, le serveur relit le Price Stripe. Il refuse un Price inactif ou divergent : EUR, montant exact, intervalle month/year avec quantité 1, taxe TTC inclusive et même Product Faluss Max. Les prix ne sont jamais modifiables dans WordPress. `pro` reste exclusivement la clé technique de plan et d’entitlement existante.

## SDK et configuration serveur

Le plugin embarque le SDK officiel stripe/stripe-php 21.3.0, épinglé par composer.lock, avec son vendor/ de production et la licence MIT. Il ne charge que ce SDK local, refuse la collision avec un SDK Stripe déjà chargé, et force l’API 2025-03-31.basil.

Les secrets viennent uniquement de constantes serveur, jamais d’options WordPress, HTML, JavaScript, URL, audit ou logs :

~~~php
define( 'FALUSS_STRIPE_MODE', 'test' ); // valeur par défaut
define( 'FALUSS_STRIPE_TEST_SECRET_KEY', '...' );
define( 'FALUSS_STRIPE_TEST_WEBHOOK_SECRET', '...' );
define( 'FALUSS_STRIPE_TEST_PRICE_PRO_MONTHLY', '...' );
define( 'FALUSS_STRIPE_TEST_PRICE_PRO_ANNUAL', '...' );
define( 'FALUSS_STRIPE_TEST_PRO_PRODUCT_ID', '...' );
define( 'FALUSS_STRIPE_TEST_PORTAL_CONFIGURATION_ID', '...' );
define( 'FALUSS_STRIPE_TAX_ENABLED', true );
~~~

Les équivalents LIVE sont nécessaires en live. Le live échoue fermé tant que FALUSS_STRIPE_LIVE_ENABLED n’est pas exactement true; test est le défaut. L’administration n’affiche que des booléens de disponibilité, le mode et la version d’API.

**Mise en service test :** créer le Product Faluss Max, les deux Prices TTC, activer Stripe Tax, configurer un Customer Portal sans changement libre de Price, déclarer les constantes et enregistrer le webhook. Pour une rotation, remplacer la constante hors Git, déployer, contrôler l’onglet Configuration puis envoyer un événement test signé. Pour l’arrêt d’urgence, retirer LIVE_ENABLED ou passer en test : Checkout et webhooks échouent fermé sans supprimer l’historique.

## Checkout, Customer, portail et retour

Faluss_Subscriptions_Billing::create_checkout() est un service PHP privé pour un futur appelant authentifié. Il accepte un Faluss ID opaque et monthly/annual; un verrou MySQL par Faluss ID, les contraintes uniques et une clé d’idempotence Stripe évitent les créations concurrentes. Le Customer Stripe ne contient que le Faluss ID opaque en métadonnées; sa référence est locale et aucune adresse e-mail n’est stockée.

Checkout est hébergé par Stripe : abonnement, un Price, quantité 1, carte obligatoire, adresse de facturation obligatoire et sauvegardée automatiquement sur le Customer Stripe pour Stripe Tax, automatic_tax, essai 15 jours et annulation sans moyen de paiement valide. Le retour navigateur est uniquement un contrôleur no-store : il valide le state opaque, l’expiration et, en succès, la session Checkout locale, puis effectue un PRG vers `wp-admin/admin.php?page=faluss-subscriptions&tab=sandbox-test` avec une notice à usage unique. Ni ce retour, ni sa notice ne décident d’un droit; seul le webhook signé et relu peut le faire. Tout retour invalide, expiré ou annulé suit le même PRG sûr sans donnée fournisseur dans l’URL finale.

Customer Portal est un service privé, limité au Customer Stripe relié au même Faluss ID. Résiliation/réactivation ne modifient que cancel_at_period_end; aucune baisse de Price, migration gratuite ou suppression immédiate n’est automatisée.

## Webhooks et droits

L’endpoint est :

~~~text
POST /wp-json/faluss-subscriptions/v1/stripe/webhook
~~~

Il vérifie le corps brut et Stripe-Signature avant toute écriture, est indépendant de session/nonce et répond Cache-Control: no-store, private. Il ne conserve que ID Stripe, type, date, tentative, statut, erreur nettoyée et empreinte SHA-256 du payload — jamais payload, carte, e-mail ou secret. L’ID d’événement unique rend les livraisons idempotentes. Une reprise administrateur relit l’événement Stripe par API puis la ressource courante : elle ne reconstruit jamais un vieux payload.

Les événements couverts sont checkout.session.completed, customer.subscription.*, invoice.*, charge.refunded, charge.dispute.*, customer.updated et payment_method.attached/detached. Toute décision relit Customer, métadonnées Faluss et Price courant : une photo d’événement ne peut pas attribuer de droit.

États normalisés : trialing, active, canceling, past_due, suspended, expired. L’essai exige une carte réellement relue et une fenêtre strictement égale à 15 jours, puis est consommé atomiquement par Faluss ID et empreinte dérivée de carte. past_due garde Pro au plus sept jours depuis le **premier** échec observé, sans glissement par relivraison. unpaid, paused, incomplete, incomplete_expired et les fins de période sont Gratuit. Les remboursements et litiges sont réconciliés, sans révocation automatique arbitraire.

La résolution UTC et à lecture conserve l’ordre : révocation conformité, attribution administrative, essai valide, abonnement actif/à résilier, grâce past_due, puis Gratuit. Une erreur Stripe n’efface ni n’invente un droit.

## Migration et opérations

La migration **v2**, additive/rejouable, ajoute grace_started_at et les trois tables InnoDB suivantes :

| Table | Rôle |
|---|---|
| faluss_subscriptions | abonnements, périodes, ancre/fin de grâce, état |
| faluss_subscription_trials | essai unique sans carte |
| faluss_entitlements | attributions horodatées |
| faluss_subscription_events | déduplication et empreinte |
| faluss_subscription_audit | audit nettoyé |
| faluss_billing_customers | lien Faluss ID / Customer, sans e-mail |
| faluss_billing_checkout_sessions | état opaque/idempotence/expiration |
| faluss_subscription_notifications | file sans destinataire ni contenu persistant |

Un v1 complet évolue uniquement par ajout/création sous verrou; un schéma partiel ou divergent échoue fermé. Désactivation/désinstallation ne suppriment aucune donnée.

La tâche quotidienne verrouille, réconcilie les abonnements stagnants et met en file J-7/J-3/J-1, incidents, confirmation, essai, annulation et fin de droits. wp_mail() n’est appelé que si une intégration de confiance fournit instantanément le destinataire via faluss_subscriptions_transactional_recipient. Faluss Subscriptions ne lit ni ne stocke l’e-mail Identity; sans ce contrat, l’envoi reste inactif.

## Administration et limites

manage_faluss_subscriptions est réservé à administrator. Tous les POST exigent capacité, nonce, validation, PRG, no-cache et audit. Les onglets sont Configuration sûre, Catalogue, Membre, Abonnements, Événements, Sandbox test, Audit et Diagnostics. La sandbox est test-only et n’est pas une surface publique.

Les contrats PHP ne remplacent pas une livraison Stripe réelle, une configuration d’hébergeur, l’envoi d’e-mails ou une recette WordPress/Safari. SUB-01C livrera un appelant membre authentifié et une surface Hub validée; SUB-01D définira les connecteurs intersites sans dupliquer l’identité ou l’abonnement.
