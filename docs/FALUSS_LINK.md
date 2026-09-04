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
