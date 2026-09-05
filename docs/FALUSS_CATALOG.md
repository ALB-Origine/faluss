# Catalogue Faluss

`faluss-catalog` est la source centrale des thèmes de carte et, à terme, des cosmétiques Faluss. FC-01 ne gère que les presets de cartes du périmètre `faluss-link` : leur nom, ordre, état, aperçu et valeurs visuelles validées.

Le thème système **Faluss par défaut** est immuable. Il reproduit le rendu historique d’une carte sans personnalisation et reste le repli lorsque le catalogue est absent ou qu’un preset n’est plus disponible. Les thèmes inactifs ne sont plus proposés dans Studio, mais restent résolus pour les membres qui les avaient déjà choisis afin de ne pas modifier leur carte sans action explicite.

Faluss Link consomme le preset sans le posséder. La cascade est : repli Faluss par défaut, thème sélectionné, surcharges individuelles du membre, puis surcharge Elementor explicitement renseignée. Le slug du thème d’origine et la liste bornée des surcharges sont conservés avec les préférences de la carte ; aucune donnée d’identité, d’e-mail ou de droit n’est dupliquée.

Un moteur partagé d’entitlements pourra ultérieurement décider qu’un thème est inclus, Premium, achetable en ALB, lié à une progression ou réservé à un dérivé. Ces règles resteront dans ce moteur commun : FC-01 et Faluss Link ne créent ni token, ni abonnement, ni paiement, ni boutique, ni droit d’accès.
