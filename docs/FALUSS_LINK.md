# Faluss Link FL-01

Faluss Link est une surface de carte publique : elle lit l’identité, le profil, l’avatar et les liens existants depuis Faluss Identity et conserve uniquement des préférences de carte liées au `faluss_id`.

FL-01 ne contient ni abonnement, ni ALB, ni inventaire cosmétique, ni shop, onglet, contenu verrouillé ou récompense. Ces moteurs pourront ultérieurement fournir des états à la carte par contrats dédiés ; les objets acquis restent possédés et les avantages d’abonnement restent temporaires.

## Recette Elementor du profil public

Pour les profils publics à la racine, sélectionnez `/modele-profile` dans le réglage **Profil public Faluss**. Cette page Elementor doit contenir un unique conteneur pleine largeur, sans padding externe, et le widget **Carte Faluss** sans identifiant. Choisissez la présentation **Page immersive** ; l’identifiant vide est résolu depuis l’URL publique, par exemple `/origin`.

Utilisez **Carte compacte** seulement pour intégrer volontairement une carte dans une page éditoriale. Le Studio et les widgets historiques conservent ce rendu compact afin de ne jamais récupérer le shell immersif de la route publique.

## Studio Faluss et réseaux (FL-05)

Sur la page mon-faluss, utilisez seulement le widget **Studio Faluss**. Ses onglets **Profil**, **Liens** et **Style** enregistrent dans un seul parcours : le profil et ses liens publics restent dans Faluss Identity ; les préférences visuelles et les réseaux restent dans Faluss Link.

Dans **Réglages > Réseaux Faluss Link**, un administrateur peut activer les réseaux proposés, ajuster leur libellé et, si nécessaire, choisir une icône image depuis la médiathèque. Les icônes SVG intégrées restent le repli par défaut. Les membres ajoutent ensuite un réseau avec un sélecteur et une URL HTTPS : aucune syntaxe technique n’est à saisir.

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
