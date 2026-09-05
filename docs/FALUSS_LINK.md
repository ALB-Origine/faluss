# Faluss Link FL-01

Faluss Link est une surface de carte publique : elle lit l’identité, le profil, l’avatar et les liens existants depuis Faluss Identity et conserve uniquement des préférences de carte liées au `faluss_id`.

FL-01 ne contient ni abonnement, ni ALB, ni inventaire cosmétique, ni shop, onglet, contenu verrouillé ou récompense. Ces moteurs pourront ultérieurement fournir des états à la carte par contrats dédiés ; les objets acquis restent possédés et les avantages d’abonnement restent temporaires.

## Recette Elementor du profil public

Pour les profils publics à la racine, sélectionnez `/modele-profile` dans le réglage **Profil public Faluss**. Cette page Elementor doit contenir un unique conteneur pleine largeur, sans padding externe, et le widget **Carte Faluss** sans identifiant. Choisissez la présentation **Page immersive** ; l’identifiant vide est résolu depuis l’URL publique, par exemple `/origin`.

Utilisez **Carte compacte** seulement pour intégrer volontairement une carte dans une page éditoriale. Le Studio et les widgets historiques conservent ce rendu compact afin de ne jamais récupérer le shell immersif de la route publique.

## Studio Faluss et réseaux (FL-05)

Sur la page mon-faluss, utilisez seulement le widget **Studio Faluss**. Ses onglets **Profil**, **Liens** et **Style** enregistrent dans un seul parcours : le profil et ses liens publics restent dans Faluss Identity ; les préférences visuelles et les réseaux restent dans Faluss Link.

Dans **Réglages > Réseaux Faluss Link**, un administrateur peut activer les réseaux proposés, ajuster leur libellé et choisir leurs ressources image depuis la médiathèque. Les membres ajoutent ensuite un réseau avec un sélecteur et une URL HTTPS : aucune syntaxe technique n’est à saisir.

La couverture s’envoie depuis le Studio avec une action explicite du membre. L’aperçu est vivant et la carte publique reprend l’alignement, le fondu de couverture, les réseaux et les boutons choisis après enregistrement.

## Couleur du nom et future fondation de styles (FL-07)

Dans **Studio Faluss > Style**, le membre choisit la couleur de son nom parmi les quatre pastilles Faluss accessibles : Rose (`#BE79FF`), Blanc (`#FFFFFF`), Noir (`#000000`, valeur par défaut) et Prune (`#82206B`). Ce choix n’affecte ni le handle, ni le statut, ni la bio, ni les liens. Une couleur explicitement renseignée dans le widget Elementor **Carte Faluss** peut la surcharger ; un contrôle Elementor laissé vide conserve la préférence membre.

Faluss Link prépare une résolution de styles de carte sans encore introduire de sélecteur de thème, de contenu Premium ou de droit associé. Lorsqu’un thème de carte existera, la priorité sera : thème sélectionné, préférences du membre, puis tokens Faluss Theme. Aujourd’hui, aucun thème n’est sélectionnable et les préférences du membre restent la source effective.

## Blocs de contenu v1 (FL-08)

L’onglet **Liens** contient un compositeur de contenu mobile : **Titre de section**, **Texte** et **Lien**. Chaque bloc est ajouté, déplacé avec les actions Monter/Descendre ou supprimé sans position technique visible. Les liens historiques sont importés de façon idempotente dans la source Faluss Link à la première ouverture du Studio ; tant que cette migration n’a pas eu lieu, la carte publique les rend depuis le profil Identity sans perte.

Les modules ultérieurs pourront fournir leurs propres blocs validés, par exemple un produit `pro.faluss`, un contenu verrouillé ou une récompense quotidienne. Faluss Link ne portera jamais leurs règles d’accès, de paiement, de token ou d’abonnement : elles resteront dans les moteurs communs de l’écosystème.

## Teaser média public (FL-09)

Le compositeur **Liens** ajoute le bloc **Teaser média** : une image déposée explicitement par le membre, avec un titre et un texte facultatifs. Le Studio et la carte publique hydratent la même source Faluss Link normalisée ; chaque bloc conserve son identifiant et son ordre lors des modifications, suppressions et déplacements. L’image est validée comme image appartenant au membre avant sa persistance. Aucun URL de média externe, vidéo, iframe, téléchargement, droit Premium ou mécanisme de paiement n’est accepté.

Le teaser est une fondation visuelle publique. Lorsqu’un moteur commun Faluss de contenus et d’entitlements existera, il pourra lui associer des règles d’accès réelles, une vente en euros ou en ALB. Faluss Link ne possédera jamais ces règles d’accès, de paiement, de token ou d’abonnement.

## Composition et lisibilité (FL-10)

Le Studio et la carte publique lisent une même composition normalisée : chaque **Titre de section**, **Texte**, **Lien** ou **Teaser média** conserve son identifiant stable et son ordre. Les éléments invalides ou dupliqués sont écartés, sans ligne fantôme. Le Studio reprend après enregistrement l’onglet **Profil**, **Liens** ou **Style** qui était actif, avec un paramètre local limité à cet onglet.

Le contraste est calculé sur la surface effective de chaque élément : les titres conservent la couleur membre ou Elementor lorsqu’elle est lisible, les textes secondaires conservent leur ton atténué lorsqu’il l’est, puis le rendu choisit noir ou blanc. Les futurs thèmes de carte pourront fournir leurs valeurs avant les préférences membre et les tokens Faluss Theme, sans changer les règles de validation de contenu.

## Surfaces éditoriales et teaser média (FL-11)

Chaque surface éditoriale résout désormais son propre contraste : un teaser clair garde un titre noir et un gris de lecture lisible, même si la carte utilise un fond plus sombre. La couleur choisie pour le nom reste prioritaire tant qu’elle est lisible sur sa surface ; seuls les cas insuffisamment contrastés basculent vers noir ou blanc. Le réglage **Apparence des réseaux** est conservé après l’enregistrement et s’applique de la même manière dans l’aperçu, en bulles ou en ligne, sur mobile comme sur desktop.

Un **Teaser média** sans titre ni texte affiche uniquement son image, sans panneau vide. Le membre choisit un format **Paysage**, **Portrait** ou **Carré** ; le format est conservé lors de la réhydratation, du déplacement et de l’édition. Le teaser reste public et visuel : un futur moteur commun de contenus pourra lui apporter une politique d’accès réelle, sans que Faluss Link ne gère un droit, un paiement, un token ou un abonnement.

## Ressources sociales et teaser éditorial (FL-12)

Dans **Réglages > Réseaux Faluss Link**, l’administrateur fournit indépendamment une **Icône contour** et un **Logo plein** pour chaque réseau. L’ancienne ressource unique est migrée sans perte comme icône contour ; les URLs déjà choisies par les membres restent intactes. Lorsqu’aucune ressource n’est disponible pour la variante demandée, Faluss Link emploie l’autre ressource si elle existe, sinon masque uniquement ce réseau : aucune image cassée ni pictogramme forcé n’est affiché.

Dans **Studio Faluss > Style**, le membre choisit explicitement **Icônes contour** ou **Logos pleins**. Le choix est conservé dans ses préférences et appliqué au même rendu public, mobile, en bulles, en ligne et dans l’aperçu vivant. Les images restent les ressources administrées : Faluss Link ne les recolorise pas par CSS.

Le gris éditorial `#6F6A63` reste la préférence de la bio, du handle et des textes de section lorsqu’il est lisible sur leur propre surface, y compris un fond noir. Les titres résolvent séparément noir ou blanc. Les légendes d’un teaser sont elles-mêmes une exception volontaire : elles sont posées à gauche sur l’image, dans un dégradé noir fixe vers le bas, avec un texte toujours blanc. Sans légende, le teaser reste strictement une image arrondie, sans panneau ajouté.

## Cohérence publique et ressources sociales (FL-13)

Les routes de profils publics Faluss sont dynamiques : Faluss Link les exclut du cache de page WordPress et LiteSpeed, sans toucher aux autres pages. Les modifications enregistrées dans Studio — variante sociale, alignement et contenu — sont donc visibles à la prochaine recharge, y compris pour un visiteur anonyme sur mobile, sans purge manuelle.

Les ressources sociales utilisent l’original WordPress `full`, avec `srcset`, `sizes` et une version issue de la date de modification de la pièce jointe. Le Studio, le rendu public mobile et desktop consomment exactement cette même ressource et la même variante. La classe d’alignement de carte est également commune : en mode Centre, avatar, identité, réseaux, blocs et liens sont centrés dans l’aperçu comme sur le profil public.

Sur une route publique, le shell Faluss Link place uniquement le conteneur Elementor de header au-dessus du hero, hors du flux. Il ne modifie ni son design ni ses réglages ; le hero commence donc au sommet du viewport, sans bande blanche, sur mobile comme sur desktop.

## Safe areas immersives (FL-14)

Les routes publiques Faluss utilisent le viewport enrichi `viewport-fit=cover`. Le hero conserve une image edge-to-edge derrière la zone système iPhone, tandis que le contenu interactif du header et le contenu de carte respectent `safe-area-inset-top`, `safe-area-inset-bottom` et les côtés. Le fond du document est sombre sur cette seule route afin qu’un premier rendu ou un rebond de défilement ne révèle jamais de bande crème ou blanche. Android et desktop conservent le même rendu, leurs safe areas valant zéro.
