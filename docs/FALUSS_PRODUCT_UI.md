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
- **Aperçu** — le shell visuel est un petit écran de `271 × 557 px` avec une
  viewport interne `229 px` de large. Il cadre une présentation de carte à
  densité compacte, nette et réactive ; il ne réduit jamais une carte statique
  par `transform: scale()`.
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

### Shell viewport ONB-02.5

La page Elementor Canvas d’onboarding est une application mobile continue : la
classe de route Faluss Link borne à cette seule page la remise à zéro de la
chaîne de conteneurs WordPress/Elementor, puis le wizard occupe `100dvh` et les
safe areas contrôlées par la page. Aucun décalage fixe ou marge négative ne
compense le shell. Le header produit, le fond et l’étape forment le même
viewport ; le statut de sauvegarde est une live region dédiée qui ne réserve
aucune surface visuelle.

Quatre layouts explicites existent : Nom en haut de son panel, aperçu compact
avec panneau inférieur superposé, Upload opaque sans aperçu, puis listes pleine
hauteur sans aperçu. Les étapes courtes ne défilent pas. Pour Réseaux et Liens,
seule la liste centrale peut défiler ; le titre, le bouton principal et
« Passer » restent stables. Les contenus des contrôles segmentés utilisent une
transition courte en opacité et translation, supprimée avec
`prefers-reduced-motion`.

## Données et aperçu

Le produit n’invente aucune copie de données : Identity possède nom, avatar,
publication et liens de compatibilité ; Faluss Link possède préférences de
carte, réseaux et blocs. Chaque modification de brouillon passe par le même
normaliseur et la même façade de présentation de carte que le Studio/public.

La frontière ONB-02.4 est stricte : le **preview shell Figma** ne possède que la
géométrie du wizard (viewport, mockup, marges, profondeur, panneau superposé
et safe areas). La **présentation de carte partagée** résout thème éventuel,
préférences Link et brouillon d’onboarding, puis rend fond, avatar et bordure,
nom, police, alignement, réseaux, liens et variantes de boutons. Elle est la
source commune du rendu public, de l’aperçu Studio et de l’aperçu onboarding.
Le contexte `onboarding-preview` ne change que la densité des métriques, jamais
la sémantique ni les données rendues.

La densité compacte conserve une colonne de contenu à largeur pleine : liens
réels et squelettes utilisent toute la largeur intérieure du petit écran. Le
traitement du nom est résolu par la façade partagée en poids et approche
typographique, et l’avatar utilise partout le même cadrage circulaire centré
avec `object-fit: cover`. Sa bordure est une couche du conteneur et ne change
donc jamais le cadrage de l’image.

Les contrôles segmentés En-tête et Style utilisent le même principe que le
switcher du Studio : un indicateur unique se translate sous les options tandis
que le panneau associé apparaît en opacité et translation. Le mouvement est
supprimé avec `prefers-reduced-motion`, sans modifier l’état sélectionné.

Une carte qui ne possède aucune préférence d’alignement explicite utilise le
repli centré dans la présentation partagée, donc dans l’onboarding, le Studio et
le public. Une préférence gauche explicite déjà enregistrée reste prioritaire :
ONB-02.5 ne migre et ne réécrit aucune carte historique.

Lorsqu’aucun réseau ou lien n’existe encore, cette même présentation injecte
uniquement dans l’aperçu des repères temporaires : Instagram, TikTok, X et trois
liens. Ils portent une marque d’aperçu, disparaissent dès qu’une donnée réelle
existe et ne sont ni enregistrés, ni publiés.

La prochaine évolution de thème de carte peut résoudre son style dans cet ordre
: preset autorisé, préférences explicites du membre, puis tokens Faluss Theme.
Cette fondation ne crée ni thème Premium, ni entitlement, ni nouveau moteur de
droits.

## Application progressive

### Studio V1

La planche Studio V1 fournie pour l’intégration est composée de frames mobiles
`440 × 956 px` et de sa planche Components. Elle définit un shell de produit
réutilisable, pas un canevas à positions absolues : topbar d’actions, identité
membre, navigation contextuelle, contenu dans le flux du document et dock
inférieur fixé avec réservation et safe areas.

Le dock principal contient l’action circulaire de création, l’action compacte
d’aperçu et une capsule à quatre destinations : **Liens**, **Shop**, **Design**
et **Profil**. Son indicateur actif rouge `#ED4343` glisse par `transform`. Shop
et Profil peuvent rester visuellement présents et désactivés tant qu’aucune
surface produit réelle n’existe. Le niveau contextuel emploie un indicateur noir
glissant : **Tous / Collections** pour Liens et **Apparence / En-tête / Liens**
pour Design. Le focus visible reste violet et le mouvement devient immédiat sous
`prefers-reduced-motion`.

Les cartes de gestion utilisent `#F5F5F5`, une surface de champ blanche, un
rayon proche de `18 px` et une cible tactile d’au moins `44 px`. Une carte de
lien possède un résumé replié et une édition développée ; une seule édition est
ouverte. Les états vides utilisent la même illustration abstraite de cartes,
sans faux lien ni faux compteur. Les collections affichent leur nom, leur
description facultative et le nombre réel de liens. Elles réemploient les
sections du flux canonique ; elles n’ont ni URL ni stockage autonome.

Le bouton aux yeux ouvre toujours la façade de carte partagée documentée plus
haut. Les sous-vues Design écrivent uniquement les préférences déjà portées par
Faluss Link : thème et fond, identité visuelle et réseaux, variantes de liens.
Les contrôles ne créent jamais une présentation Studio parallèle à la carte
publique.

Studio, Mes découvertes et les futures surfaces membre adopteront ce contrat
progressivement, composant par composant. Une migration visuelle ne doit jamais
modifier des données, un parcours Identity, une règle métier ou l’administration
WordPress sans lot explicite.
