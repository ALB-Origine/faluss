# Catalogue Faluss

`faluss-catalog` est la source centrale des thèmes de carte et, à terme, des cosmétiques Faluss. FC-01 ne gère que les presets de cartes du périmètre `faluss-link` : leur nom, ordre, état, aperçu et valeurs visuelles validées.

Le thème système **Faluss par défaut** est immuable. Il reproduit le rendu historique d’une carte sans personnalisation et reste le repli lorsque le catalogue est absent ou qu’un preset n’est plus disponible. Un thème inactif ou supprimé ne peut jamais être résolu : Faluss Link remplace de manière ciblée et idempotente les seules références qui le désignent par **Faluss par défaut**, sans toucher aux surcharges visuelles personnelles ni aux autres préférences.

Faluss Link consomme le preset sans le posséder. La cascade est : repli Faluss par défaut, thème actif sélectionné, surcharges individuelles du membre, puis surcharge Elementor explicitement renseignée. La référence effective et la liste bornée des surcharges sont conservées avec les préférences de la carte ; aucune donnée d’identité, d’e-mail ou de droit n’est dupliquée.

Un moteur partagé d’entitlements pourra ultérieurement décider qu’un thème est inclus, Premium, achetable en ALB, lié à une progression ou réservé à un dérivé. Ces règles resteront dans ce moteur commun : FC-01 et Faluss Link ne créent ni token, ni abonnement, ni paiement, ni boutique, ni droit d’accès.
