# Faluss Link FL-01

Faluss Link est une surface de carte publique : elle lit l’identité, le profil, l’avatar et les liens existants depuis Faluss Identity et conserve uniquement des préférences de carte liées au `faluss_id`.

La carte Faluss ne contient ni abonnement, ni ALB local, ni inventaire cosmétique, ni shop, onglet ou contenu verrouillé. La récompense quotidienne TE-03, lorsqu’elle est configurée, reste une décision du moteur commun par contrat dédié ; les objets acquis restent possédés et les avantages d’abonnement restent temporaires.

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

Sur une route publique, le shell Faluss place uniquement une couche de conteneur autour du header Elementor au-dessus du hero, hors du flux. Il ne modifie ni son design ni ses réglages : le hero commence au sommet du viewport, sans bande blanche, sur mobile comme sur desktop. Cette couche laisse le menu, ses liens et son panneau latéral déborder et recevoir leurs interactions au-dessus de la carte immersive ; la couverture et son dégradé restent purement visuels.

## Safe areas immersives (FL-14)

Les routes publiques Faluss utilisent le viewport enrichi `viewport-fit=cover`. Le hero conserve une image edge-to-edge derrière la zone système iPhone, tandis que le contenu interactif du header et le contenu de carte respectent `safe-area-inset-top`, `safe-area-inset-bottom` et les côtés. Le fond du document est sombre sur cette seule route afin qu’un premier rendu ou un rebond de défilement ne révèle jamais de bande crème ou blanche. Android et desktop conservent le même rendu, leurs safe areas valant zéro.

## Récompense quotidienne Faluss (TE-03)

Le widget Elementor **Récompense quotidienne Faluss** et le shortcode `[faluss_link_daily_reward]` sont prévus pour le modèle public `/modele-profile`. Ils n’acceptent ni identifiant de membre, ni montant, ni règle : le visiteur connecté réclame seulement pour son identité Faluss active. Un visiteur anonyme voit l’offre configurée par le Core avec le libellé par défaut `Réclamer mes {montant} {unité}`, puis reçoit un lien local vers `/login` et revient sur la carte en cours après la preuve passwordless ; aucune URL externe n’est ouverte. La microcopie par défaut `et débloquer le teaser gratuitement` décrit ce parcours de connexion, jamais un droit acquis avec les tokens.

Le widget demande son offre ou son état puis une éventuelle réclamation au Connector local avec une requête WordPress protégée. Il désactive le bouton pendant la requête, remplace immédiatement l’action par le gain ou l’état déjà réclamé, et affiche tout échec avec un message lisible non sensible. Faluss Link ne possède ni table de ledger, ni solde, ni règle, ni idempotence de gain. Le Core Token Engine reste l’unique autorité pour la règle globale `daily_reward`, son fuseau horaire, le cooldown quotidien, le ledger et le refus des doubles réclamations, y compris entre plusieurs cartes ou applications.

Dans Elementor, choisissez l’alignement, le contexte compact ou immersif, l’affichage du solde, les deux textes anonymes et le masquage discret d’un état sans règle ; les erreurs de connexion, de permission ou de sujet restent explicites. Les styles de surface et de bouton restent limités au widget et héritent des tokens Faluss Theme. Aucun shop, paiement, entitlement, Premium, cosmétique, streak, gain automatique ou wallet complet n’est ajouté par cette surface.

## Catalogue central de thèmes (FL-15)

Le carrousel **Thèmes** dans `Studio Faluss > Style` lit `faluss-catalog` quand ce plugin est présent. Un thème définit une base structurée pour le fond, la transition du hero, la couleur du nom, l’alignement, les réseaux et les boutons ; il ne contient jamais de CSS, HTML ou JavaScript libre. Après avoir choisi un thème, les réglages individuels du Studio restent disponibles et prennent le dessus sur les seules propriétés que le membre modifie.

Sans Catalogue Faluss, Faluss Link conserve son rendu **Faluss par défaut**, sans erreur ni dépendance obligatoire. Les surcharges Elementor explicitement renseignées gardent leur priorité locale sur les variables de carte.

## Résolution fiable des thèmes (FL-15.1)

La carte publique, l’hydratation du Studio, son aperçu et l’enregistrement passent par le même résolveur : base **Faluss par défaut**, thème Faluss Link actif et valide, surcharges personnelles bornées pour le fond, la transition, l’alignement, la couleur du nom, les réseaux et les boutons, puis CSS Elementor explicitement renseigné. Le choix de **Faluss par défaut** est un vrai nouveau thème de base : les six surcharges visuelles précédentes sont réinitialisées, et toute modification ultérieure est de nouveau conservée. Si un thème est désactivé ou supprimé, seule sa référence est remplacée par le défaut ; sa réactivation ultérieure ne le réapplique jamais silencieusement.

## Thèmes verrouillables EC-02

Dans **Studio Faluss > Style**, un thème inclus reste sélectionnable. Un thème lié à un droit central actif reste visible, mais affiche simplement **Droit requis** et ne peut pas être sélectionné tant que le Connector ne confirme pas le droit du propriétaire de la carte. La vérification est répétée à l’enregistrement et pour chaque rendu public : une révocation, expiration, désactivation ou indisponibilité du Core restitue le rendu **Faluss par défaut** sans supprimer la référence ni les préférences manuelles du membre.

Le bouton discret **Copier mon identifiant Faluss** est visible uniquement au propriétaire connecté dans Studio ; l’identifiant n’est jamais rendu sur la carte publique. Faluss Link ne stocke aucune attribution, aucun solde et aucune décision de droit ; aucun paiement, boutique, Premium ou accès verrouillé de contenu n’est créé par cette fonctionnalité.

## Studio compact et repli de thème FL-17 / EC-02.1

Le thème effectivement rendu est distinct de la préférence membre conservée. Lorsqu’un droit de thème devient indisponible, Faluss Link affiche la base **Faluss par défaut** sans réécrire le choix, les surcharges visuelles ni les autres données de carte. Les préférences personnelles déjà enregistrées restent donc visibles dans ce repli ; dès que le droit est de nouveau confirmé par le Connector, le thème précédemment choisi redevient disponible. Une surcharge Elementor explicitement renseignée reste la dernière couche de rendu.

Sur mobile, le Studio ouvre l’aperçu à la demande depuis la barre basse **Mettre à jour / Aperçu**. L’aperçu est fermé au chargement, son bouton affiche le nombre de modifications locales non enregistrées, et la barre respecte la zone sûre du navigateur. Le sélecteur de fond expose sa pastille, sa valeur hexadécimale et l’aide « Couleur derrière votre carte ». Le statut de publication est présenté comme le contrôle accessible **Profil public**, avec les états **Visible** et **Masqué** ; il enregistre la même donnée Identity qu’auparavant.

## Mes découvertes Faluss (FL-18)

**Mes découvertes Faluss** est une bibliothèque privée, locale à `faluss.me`. Ajoutez manuellement le widget Elementor **Mes découvertes Faluss** ou le shortcode `[faluss_link_discoveries]` dans une page membre distincte, par exemple `/mes-decouvertes`, puis liez cette page depuis le header Elementor. Faluss Link ne crée aucune page et ne modifie jamais le header ; le Studio reste exclusivement réservé à la personnalisation de la carte.

Lorsqu’un membre connecté avec une identité Faluss active consulte la carte publiée d’un autre membre, une seule entrée privée est actualisée. Elle conserve uniquement les deux références d’identité nécessaires, la première et dernière découverte, ainsi qu’un compteur interne ; elle ne copie ni bio, photo, liens, adresse IP, user-agent ni référent. Les propriétaires de cartes ne reçoivent aucune notification et aucune liste de visiteurs n’existe. Les visiteurs anonymes, les identités inactives, les cartes non publiées et la propre carte du membre ne produisent aucune écriture.

La bibliothèque affiche seulement les profils encore publiés, les plus récents d’abord. Le membre peut désactiver **Enregistrer mes découvertes**, retirer une entrée ou tout effacer : ces actions sont limitées à son identité active et n’affectent jamais celle d’un autre membre. L’historique existant est conservé lorsque l’enregistrement est désactivé. Chaque bibliothèque est bornée à 250 profils ; les entrées les plus anciennes sont écartées de manière déterministe.

Un futur moteur d’analytics créateur, s’il est un jour validé, restera un produit distinct. Il ne devra jamais lire, réutiliser ni transformer Mes découvertes en outil de suivi des visiteurs.
