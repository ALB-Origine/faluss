# Contrat UI des plugins Faluss — FUI-01

Ce contrat s’applique aux écrans produits par les plugins WordPress Faluss. Il ne remplace pas l’administration WordPress, son thème, sa sidebar native ou ses réglages globaux.

## Autonomie

Chaque plugin reste autonome. Il embarque les composants et styles nécessaires à son propre écran et ne dépend pas d’un plugin central de présentation. Les styles sont limités au conteneur du plugin concerné ; ils ne modifient ni `:root`, ni l’administration globale, ni les autres produits.

## Shell

Un écran Faluss peut organiser son contenu dans un shell à deux zones :

- une sidebar produit à gauche avec le bandeau **Faluss** et la mention **by Alternative LAB** ;
- une navigation persistante, claire et utilisable au clavier ;
- un panneau actif à droite, identifié par son titre ;
- des URL server-rendered de secours pour chaque panneau.

Une amélioration JavaScript peut rendre une navigation plus fluide, mais elle ne doit jamais rendre un formulaire ou une action d’administration dépendant de JavaScript. Sur petit écran, la sidebar devient compacte ou la navigation horizontale défilable. La sidebar native WordPress reste visible et inchangée.

## Tokens visuels

| Élément | Valeur |
|---|---|
| Canvas | `#FFFDF5` |
| Surface | `#FFFFFF` |
| Encre | `#080808` |
| Texte discret | `#6F6A63` |
| Accent | `#FF3D16` |
| Bordure | `rgba(8,8,8,.12)` |
| Ombre | `0 12px 30px rgba(8,8,8,.06)` |
| Police | Outfit, puis repli système |

Utiliser des rayons, espacements et focus cohérents. Les surfaces sont claires et tactiles ; les actions principales sont explicites. Éviter violet, verre générique, gradients décoratifs, dashboards bento artificiels et surcharge visuelle.

## Accessibilité

La navigation active utilise `aria-current`, les panneaux possèdent un titre lié, chaque champ porte un libellé, et le focus clavier reste nettement visible. Les contrastes, messages de succès/erreur et états désactivés sont fonctionnels sur écran standard comme sur mobile/tablette.
