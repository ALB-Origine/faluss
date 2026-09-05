# Catalogue Faluss

`faluss-catalog` est la source centrale des thèmes de carte et, à terme, des cosmétiques Faluss. FC-01 ne gère que les presets de cartes du périmètre `faluss-link` : leur nom, ordre, état, aperçu et valeurs visuelles validées.

Le thème système **Faluss par défaut** est immuable. Il reproduit le rendu historique d’une carte sans personnalisation et reste le repli lorsque le catalogue est absent ou qu’un preset n’est plus disponible. Un thème inactif ou supprimé ne peut jamais être résolu : Faluss Link remplace de manière ciblée et idempotente les seules références qui le désignent par **Faluss par défaut**, sans toucher aux surcharges visuelles personnelles ni aux autres préférences.

Faluss Link consomme le preset sans le posséder. La cascade est : repli Faluss par défaut, thème actif sélectionné, surcharges individuelles du membre, puis surcharge Elementor explicitement renseignée. La référence effective et la liste bornée des surcharges sont conservées avec les préférences de la carte ; aucune donnée d’identité, d’e-mail ou de droit n’est dupliquée.

## Droit de thème EC-02

Un thème actif peut rester **Inclus** ou demander une définition active de type `theme`, proposée par Token Engine via le Connector pour la surface Faluss Link. L’administrateur ne saisit aucun code libre : si le Core ou Connector est indisponible, l’association ne peut pas être créée ou modifiée et le thème reste verrouillé à la consommation. Catalogue conserve seulement la métadonnée du droit ; il ne conserve ni attribution, ni décision d’accès, ni solde.

Faluss Link revalide le droit pour le `faluss_id` du profil au rendu. Un droit absent, révoqué, expiré, inactif ou non vérifiable retombe vers **Faluss par défaut** sans effacer la préférence membre. Aucun prix, achat, abonnement, token ou boutique n’est ajouté par Catalogue.
