# Contrat UI produit Faluss — FPU-01

Ce document régit les interfaces membres et publiques Faluss : onboarding,
Studio, bibliothèque Mes découvertes et les prochaines surfaces produit. Il ne
gouverne pas l’administration WordPress : celle-ci reste définie par
[`FALUSS_PLUGIN_UI.md`](FALUSS_PLUGIN_UI.md).

## Source visuelle ONB-02

Les écrans Figma suivants font autorité pour l’onboarding membre ONB-02 :

- [Arrière-plans — `3695:2381`](https://www.figma.com/design/lh0rDFgk4F7IgNyHFrGgtR/DIVE-PROJECT?node-id=3695-2381)
- [Boutons — `3703:2466`](https://www.figma.com/design/lh0rDFgk4F7IgNyHFrGgtR/DIVE-PROJECT?node-id=3703-2466)
- [Import de photo — `3703:2546`](https://www.figma.com/design/lh0rDFgk4F7IgNyHFrGgtR/DIVE-PROJECT?node-id=3703-2546)
- [Composants et variantes — `3704:2648`](https://www.figma.com/design/lh0rDFgk4F7IgNyHFrGgtR/DIVE-PROJECT?node-id=3704-2648)

Les quatre frames sont mobiles, en `440 × 956 px`. Elles composent un canvas
clair, une barre supérieure, un aperçu compact de carte et un panneau inférieur
qui recouvre l’aperçu. Les autres étapes d’onboarding réemploient ces mêmes
primitives ; elles ne créent pas une seconde direction visuelle.

## Tokens de produit

Ces tokens ONB-02 sont locaux aux composants produit qui les consomment. Ils ne
modifient ni le kit Elementor, ni `:root`, ni les variables globales Faluss
Theme.

| Rôle | Valeur Figma |
|---|---|
| Canvas de l’onboarding | `linear-gradient(162.13deg, #F4F4F4 56.59%, #FFFFFF 93.54%)` |
| Surface/panneau | `#FFFFFF` |
| Contrôle inactif | `#F4F4F4` |
| Encre | `#1E1E1E` |
| Texte discret | `#707070` |
| Action par défaut | `#ED4343` |
| Action survol | `#D43D3D` |
| Texte d’action | `#F2F1F7` |
| Piste de jauge | `#CACACA` |
| Carte active | `#FF3951` |
| Police | Outfit Light, Medium et Bold ; repli système sans-serif |

Les rayons de référence sont `100 px` (CTA), `18.5 px` (contrôle segmenté),
`16 px` (carte de choix), `26 px` (upload) et `50 px` (panneau inférieur). Les
ombres sont rares : upload `0 0 30.7px rgba(0,0,0,.04)` et choix actif
`0 0 5.3px rgba(0,0,0,.25)`.

## Primitives

- **Header produit** — retour circulaire `44 × 44`, jauge `269 × 12` et symbole
  Faluss `35 × 35` ; la progression provient toujours de l’état serveur repris.
- **CTA** — largeur de contenu, hauteur `45 px`, capsule, libellé Outfit Medium
  `16 px`. Les états hover et active ne changent ni la hiérarchie ni le libellé.
- **Contrôle segmenté** — `274 × 37`, surface grise et curseur blanc ; les deux
  onglets gardent rôles, focus et associations ARIA.
- **Carte de choix** — `126 × 126`, fond sombre `#191919`, aperçu propre à la
  variante et liseré rouge uniquement lorsqu’elle est sélectionnée.
- **Upload** — carte blanche à rayon `26 px` avec zone intérieure `#F4F4F4`.
  L’import est une action explicite, sans création de média silencieuse.
- **Palette** — choix circulaires à valeurs exactes `#000000`, `#191919`,
  `#737373`, `#DDDDDD`, `#FFFFFF`, `#321752`, `#350D0D`, `#1A7061` et
  `#A748B5`, plus une entrée personnalisée native.
- **Aperçu** — le cadre visuel est un petit écran de `271 × 557 px` avec une
  viewport interne `229 px` de large. Il réemploie le renderer de carte partagé
  et applique une échelle interne compacte ; il n’est ni une carte parallèle,
  ni un état persistant.
- **Panneau inférieur** — blanc, coins supérieurs `50 px`, posé au-dessus de
  l’aperçu. Son panneau actif seul défile lorsque le viewport se réduit.

## États et mouvement

Une seule étape d’onboarding est exposée à la fois. Les transitions utilisent
`transform` et `opacity`, puis placent le focus sur le titre de l’étape. La
jauge est animée sans reflow. Sous `prefers-reduced-motion: reduce`, les
transitions deviennent immédiates sans retirer navigation, focus, validation ou
reprise serveur.

Les choix, boutons, champs et erreurs restent utilisables au clavier. Les
éléments invisibles sont `hidden` et `inert`, pas simplement rendus transparents.
Le document complet ne devient jamais une pile verticale d’étapes ; Safari peut
conserver son comportement de défilement et de clavier natif.

## Données et aperçu

Le produit n’invente aucune copie de données : Identity possède nom, avatar,
publication et liens de compatibilité ; Faluss Link possède préférences de
carte, réseaux et blocs. Chaque modification de brouillon passe par le même
normaliseur et le même renderer de carte que le Studio/public. Le squelette de
liens est un repère visuel limité à l’aperçu : il n’est ni enregistré ni publié.

La prochaine évolution de thème de carte peut résoudre son style dans cet ordre
: preset autorisé, préférences explicites du membre, puis tokens Faluss Theme.
Cette fondation ne crée ni thème Premium, ni entitlement, ni nouveau moteur de
droits.

## Application progressive

Studio, Mes découvertes et les futures surfaces membre adopteront ce contrat
progressivement, composant par composant. Une migration visuelle ne doit jamais
modifier des données, un parcours Identity, une règle métier ou l’administration
WordPress sans lot explicite.
