# AP-01 - Apps Faluss dans Faluss Portal

## Statut et frontière

PF-01 ajoute `Faluss Portal`, un plugin front-office installé sur `faluss.com`.
Il rend le shortcode `[faluss_portal]` destiné à la page privée
`/mon-faluss/`. Le portail est une surface de lecture et de navigation : il ne
crée aucune identité, donnée d'abonnement, transaction, facture, préférence
transversale ou profil universel.

AP-01 remplace uniquement les états vides des deux panneaux contextuels Apps
par un registre serveur unique et un composant de carte partagé. Le shell
PF-01G reste inchangé : sidebar, repli, indicateur actif, avatar, panneau gris,
défilement, barre `Mes apps · Explorer`, Master Profile et projections métier
conservent exactement leur structure antérieure.

PF-01G aligne ce socle sur les frames et les captures réelles Shell, Master
Profile, Abonnement et Facturation. Le shell conserve une sidebar, ses bulles
monochromes, l'indicateur vertical noir, les pills contextuelles noires, le
panneau clair et l'accès profil par l'avatar de sidebar uniquement. La mise en oeuvre reste une grille
responsive : aucun cadre figé ou positionnement absolu de page n'est utilisé.
La grille est limitée à `100svh` et sa ligne commune utilise
`minmax(0, 1fr)`. La classe de page `faluss-portal-page`, ajoutée uniquement à
`/mon-faluss/`, verrouille le défilement du document WordPress/Elementor. La
sidebar et son avatar restent ainsi immobiles et non défilants dans toutes les
sections, tandis que le seul défilement vertical du shell appartient au panneau
gris, avant comme après repli de la sidebar.

La hiérarchie de navigation est contractuelle : le mot-symbole Faluss ouvre
`Accueil`, suivi de `Apps Faluss`, puis du seul séparateur, puis `Analytics`,
`Abonnement`, `Facturation`, `Paramètres` et `Aide`. Le mot-symbole remplace le
bouton Accueil ; aucun second bouton Accueil n'est rendu. L'indicateur actif est
une barre noire fixée au bord gauche de l'écran et déplacée physiquement vers
le contrôle actif. Sur desktop comme sur mobile, l'avatar seul reste au bas de
la sidebar. Aucune carte membre basse, mini-card de profil ou surface glass
flottante n'est rendue dans le contenu.

## Accès et sources de vérité

| Besoin portail | Source canonique | Projection PF-01 | Écriture PF-01 |
| --- | --- | --- | --- |
| Session membre | Cookie WordPress créé par Faluss Identity Client | utilisateur `subscriber` lié | aucune |
| Sujet Faluss | `faluss_identity_links` local, filtré par l'utilisateur courant | Faluss ID opaque, jamais dans le HTML ou l'URL | aucune |
| Offre et état | `Faluss_Subscriptions_Resolver` | niveau, état normalisé, échéance et périodicité seulement | aucune |
| Customer Portal | `Faluss_Subscriptions_Billing::create_portal()` | bouton uniquement si configuration et Customer locaux existent | session Stripe hébergée à la demande, sans stockage portail |
| Factures | Faluss Subscriptions / Customer Portal Stripe | état vide tant qu'aucune projection locale autorisée n'existe | aucune |
| Points Faluss | façade interne `Token_Engine_Points_Service` du Core PF | document quotidien Hub filtré et état d'action seulement | aucune écriture Portal ; délégation explicite au Core |
| Identité visuelle | identité WordPress locale minimale | nom d'affichage sûr ou « Membre Faluss », avatar neutre | aucune |
| Apps Faluss | registre AP-01 local et preuve canonique de carte publiée lorsqu'elle existe localement | disponibilité, possession et application active séparées | aucune |
| Analytics, quêtes, boutique | contrats futurs propres aux applications | état vide explicite | aucune |

La vérification de session part toujours de la liaison locale `wp_user_id` de
l'utilisateur courant. Le portail n'accepte jamais un Faluss ID venant d'une
URL, d'un onglet, d'un formulaire ou de JavaScript. Un visiteur non connecté
ne reçoit que le bouton **Continuer avec Faluss** de Faluss Identity Client ;
aucun login, compte local ou lien d'identité n'est créé hors SSO.

L'état d'accès utilise toute la surface du viewport et centre une carte de
connexion de largeur lisible, y compris lorsqu'Elementor contient le shortcode
dans une colonne. Le bouton interne **Continuer avec Faluss** transmet toujours
l'URL locale exacte `https://faluss.com/mon-faluss/` en production. Faluss
Portal ajoute cette URL à l'allowlist du client Identity au moment de la
lecture, sans écrire son option et sans dépendre du réglage manuel d'un autre
widget. Le client SSO conserve ses validations same-site, state et PKCE, puis
revient sur l'URL nue, sans paramètres OAuth. Le portail ne modifie ni le
protocole ni Faluss Identity.

Sur le front-office, la barre d'administration WordPress est masquée pour les
membres sans capacité éditoriale ou d'administration. Elle reste inchangée
dans `/wp-admin/` et pour les comptes disposant de `edit_posts` ou
`manage_options`.

La façade d'abonnement réduit délibérément la décision centrale à des valeurs
publiques d'interface. Elle ne transmet pas les références Customer,
souscription, Checkout ou Price, les sources internes, les données de carte,
les adresses, les payloads Stripe ou les secrets. Le navigateur ne contacte
jamais Stripe directement. Le Customer Portal est créé au POST serveur,
protégé par nonce et relié au seul Faluss ID de la session courante ; son URL
est validée comme URL Stripe HTTPS avant la redirection.

Le retour neutre historique du Customer Portal (`faluss_subscriptions_return=portal`)
est intercepté avant le contrôleur de retours Checkout SUB-01B puis ramené,
sans paramètre fournisseur, à `Facturation / Paiement`. Cette interception ne
lit ni n'écrit droit, essai, abonnement, audit ou état Stripe.

## Etats réels et états vides

- **Accueil / Vue** affiche l'état de session et l'offre uniquement lorsqu'ils
  sont réellement résolus.
- **Accueil / Activité** et **Découvrir** restent explicites tant que les
  applications n'ont pas publié de contrat d'événements et d'activation.
- **Apps Faluss / Mes apps** rend Faluss Hub pour tout membre lié au portail.
  AP-02A ajoute Faluss Me seulement lorsque la fin de l'onboarding SSO fournit
  une projection SSO serveur versionnée attestant une carte `published` et sa
  route canonique `https://faluss.me/mon-faluss`. Identity reste propriétaire
  de cette décision ; Identity Client valide l'autorité exacte et conserve le
  read-model minimal pour le compte WordPress lié. En l'absence de projection,
  aucune possession Faluss Me n'est supposée. Date, Fans et Pro ne sont pas
  disponibles et ne peuvent donc pas apparaître dans `Mes apps`.
- **Apps Faluss / Explorer** présente les cinq entrées du registre partagé.
  Faluss Hub est l'application active et affiche `Vous êtes ici` sans
  navigation ; Faluss Me est la seule application disponible non active. Si sa
  projection membre est connue, `Visiter` reprend exactement
  `https://faluss.me/mon-faluss`, sinon l'entrée non possédée conserve la
  destination publique existante. Date, Fans et Pro affichent
  `Bientôt disponible` sans lien ni URL de production inventée. Aucun appel
  inter-domaine supplémentaire n'est effectué par Portal pour déduire la
  possession : la preuve locale est issue exclusivement de l'échange SSO
  serveur-à-serveur déjà authentifié.
- **Apps Faluss / Mes apps — Hub** porte depuis DR-02A la seule action PF
  effectivement active. Pour une session `subscriber` liée à une identité
  Faluss active, Portal demande un état filtré au Core PF, puis peut déléguer
  l'intention fixe de claim Hub par POST authentifié et nonce. Le Core Token
  Engine `0.4.1`, schéma `5`, reste seul à choisir le jour `Europe/Paris`,
  l'idempotence et l'écriture de `20 PF` de classe `earned`, catégorie
  `daily_accrual`, propriétaire `faluss-hub`, clé `hub.daily_accrual`.
  Portal ne lit ni n'écrit aucun ledger, ne conserve aucun cache métier et ne
  reçoit jamais du navigateur le sujet, le montant, la classe, la date, la clé
  de reward ou une clé d'idempotence. Depuis AP-02A.2, `claimable` affiche le badge PF
  officiel et `20` dans une pill glass compacte : cette pill visible est le véritable
  bouton POST, sans calque interactif superposé. Seul ce statut délègue
  `owner_claim`. Après la réponse réellement `claimed` du Core, la même
  géométrie pill conserve le badge PF et `20` dans un état non interactif, sans
  second claim. Les états
  `ineligible`, `unavailable` et `not_supported` redeviennent une action de
  navigation sans promesse de gain. Le clic de claim reste isolé de l'accès
  normal de la card Hub.
- Le badge PF officiel embarqué dans
  `assets/images/pf/faluss-pf-badge.png` est utilisé exclusivement dans cette
  zone d'action Hub ; il ne représente ni un solde ni une nouvelle surface PF.
  Portal ne montre aucun total, historique ou ventilation PF. Faluss Me ne
  possède encore aucun adaptateur de claim : sa card et toute autre card sans
  reward conservent uniquement la zone glass de navigation, sans `0 PF`, sans
  `75 PF` et sans action économique fictive.

Toutes les cards de `Mes apps` partagent la même primitive glass, bordée d'un
filet translucide : cercle pour une navigation simple, pill allongée pour une
récupération économique déclarée. La card entière reste navigable vers sa
destination membre ; seule une action métier explicitement autorisée, aujourd'hui
`hub.daily_accrual`, intercepte son propre clic. Les styles Elementor sont
neutralisés sur le véritable bouton et le focus clavier utilise uniquement un
anneau blanc suivant le rayon de la pill. Faluss Hub vise toujours
`https://faluss.com/mon-faluss` depuis Faluss Me et depuis son registre ; aucune
card possédée ne retombe sur une home marketing générique.

Le symbole Faluss Me de Portal est dérivé mécaniquement de l'asset officiel :
fond blanc retiré, symbole blanc conservé et canal alpha réel. Ce même PNG et la
même géométrie `object-fit: contain` sont utilisés dans `Mes apps` et `Explorer`,
sans filtre, mode de fusion, agrandissement CSS ni fond de conteneur.
- **Analytics** rend des composants réutilisables de KPI, graphe, tableau et
  filtre à l'état vide. Le futur contrat par application devra fournir une
  date, une source, une métrique, une portée et l'autorisation de lecture ; il
  ne pourra jamais injecter des chiffres ou revenus d'exemple.
- **Abonnement / Mon offre** rend `Faluss Gratuit` ou `Faluss Max`, l'état
  résolu (dont `trialing`) et la date d'essai ou d'échéance lorsqu'elle existe.
  **Comparer** vient du catalogue SUB-01B : gratuit, 9,99 EUR TTC mensuel,
  99 EUR TTC annuel, essai de 15 jours et carte obligatoire. Aucun avantage
  applicatif non déclaré n'est inventé.
- **Facturation / Historique** ne rend aucune référence Stripe. En l'absence
  de projection de facture canonique, il indique cet état et, si disponible,
  propose le Customer Portal. **Paiement** n'affiche ni ne collecte de carte.
- **Paramètres**, **Aide**, **Quêtes** et **Boutique** restent honnêtes tant que
  leurs sources de données ne sont pas activées.

## Master Profile préparatoire

Le Master Profile est une surface immersive locale, ouverte depuis la bulle
avatar de sidebar sans changement de page. Le dialogue gère Échap, historique,
fermeture et réduction de mouvement, sans déplacer programmatiquement le focus.
Il monte depuis le bas à l'ouverture et redescend à la fermeture ; l'opacité
n'est jamais le mécanisme de transition. Ses trois onglets partagent un unique
indicateur noir déplacé par `transform` et ne créent aucune copie de données.
Son en-tête utilise une grille symétrique — chevron transparent, tabs
centrés, réserve droite équivalente. PF-01F décale uniquement la cellule du
chevron retour de `10 px` vers la gauche, sans modifier la grille symétrique ni
la géométrie et le centrage des onglets. Son CTA profil conserve le bouton primaire noir Faluss.com,
pleine largeur, avec icône et texte blancs :

| Onglet | Données PF-01 | Ce qui reste hors périmètre |
| --- | --- | --- |
| Mon compte | identité locale sûre, date de liaison et état PF à venir | profil universel, handle universel, avatar universel, écriture de profil |
| Sécurité | rappel de l'autorité Faluss Identity | passwordless, sessions et e-mail |
| Confidentialité | état préparatoire | consentements et règles de visibilité universelles |

PF-01 propose le modèle cible sans le persister :

1. **Identité technique Faluss** - Faluss ID opaque, détenu exclusivement par
   Faluss Identity.
2. **Fiche membre universelle** - futur profil `faluss.com/@handle`, à définir
   dans son propre schéma et contrat de visibilité.
3. **Modules contextuels** - chaque app ajoute ses modules validés sans créer
   un profil concurrent.
4. **Carte Faluss.me** - `faluss.me/@handle` reste une carte de liens
   personnelle facultative, détenue par Identity/Faluss Link.
5. **Confidentialité, cosmétiques et progression** - décisions et sources de
   vérité dédiées, jamais inférées par le portail.

## Point Faluss (PF)

La dénomination future est **Point Faluss**, abréviation **PF**. PF-01 ne
projette encore aucun solde sous ce nom : aucun ledger PF officiel, avec un
namespace ou code d'unité dédié tel que `faluss_pf`, n'est actuellement défini.

Un solde historique ALB / Alternative LAB ne peut jamais être lu, converti,
masqué ou affiché comme PF. Le Master Profile indique donc seulement **Points
Faluss bientôt disponibles**, sans valeur numérique. Toute lecture future devra
vérifier explicitement une source de ledger Faluss PF identifiée ; elle fera
l'objet d'un lot Token Engine dédié, documenté et contractuellement testé, sans
migration implicite de transactions historiques.

## Interaction, accessibilité et repli

Les tabs contextuels sont propres à leur section : `Vue · Activité · Découvrir`,
`Mes apps · Explorer`, `Vue · Performance · Revenus · Sources`, `Mon offre ·
Comparer`, `Historique · Paiement`, `Général · Notifications · Préférences` et
`Aide · Nous contacter`. La navigation interne utilise des liens server-rendered vers des paramètres
`faluss_portal` non sensibles ; JavaScript les intercepte pour remplacer le
panneau sans rechargement, mettre à jour l'historique et déplacer les deux
indicateurs par `transform`. Sans JavaScript, les mêmes liens rechargent la
bonne vue. Les animations disparaissent sous `prefers-reduced-motion`.

Le contrôle situé dans le panneau principal replie la sidebar et étend la
surface de contenu sans rechargement. Son état `aria-expanded`, son libellé et
la préférence locale `falussPortalSidebarCollapsed` restent synchronisés. Un
refus de `localStorage` n'empêche pas le contrôle de fonctionner pendant la
page courante. Le chevron sidebar et le chevron retour du Master Profile sont
deux composants distincts ; ils partagent uniquement le SVG statique noir,
sans rotation. Chacun possède une zone transparente `30 × 30 px`, sans bordure,
centrée par sa propre cellule en grille. Le déclencheur avatar est un troisième
composant indépendant : sa cellule utilise `margin-top: auto`, son disque est
centré et reprend exactement le diamètre des bulles de navigation du breakpoint
courant (`48 px`, `44 px` sur mobile, `42 px` sur écran bas).

Les contrôles actifs ne changent pas la couleur de leurs icônes. Les éléments
interactifs restent sémantiques, mais PF-01G conserve la neutralisation explicite et
uniquement dans le portail les contours, ombres, bordures colorées et
`-webkit-tap-highlight-color` injectés par le navigateur, Safari ou Elementor
sur les cellules, contrôles, icônes, avatar, pseudo-éléments et états `hover`,
`focus`, `focus-visible` et `active`. Aucun script ne force le focus à
l'ouverture du profil. Les trois zones restent de vrais boutons libellés pour
les technologies d'assistance, sans habillage visuel parasite.

Le header contextuel est un enfant direct du panneau gris. Sa barre utilise
`100%` de la largeur locale du panneau, avec un retrait symétrique de `18 px`,
et `margin-inline: auto`. Elle ne contient aucune largeur ou translation liée
au viewport ou à la sidebar. PF-01G remplace la mesure JavaScript ponctuelle par
une grille CSS locale `repeat(n, minmax(0, 1fr))`, pour les groupes de deux,
trois ou quatre onglets. Vue, Performance, Revenus et Sources reçoivent donc la
même largeur, indépendamment de leurs libellés. Le curseur noir est découplé
des labels : sa largeur dépend du nombre de segments et son déplacement du seul
`data-active-index`. Il suit ainsi chaque image de la transition de largeur du
panneau, sans mesure liée au viewport, sans transition de largeur indépendante
et sans attendre un nouveau clic. PF-01G ne change ni la géométrie validée, ni
le retrait, ni le centrage, ni les tailles de labels héritées de PF-01E — dont
`12.5 px` sur mobile.

## Registre et carte Apps Faluss

AP-01 définit une seule entrée par application avec son slug, son dérivé de nom,
son asset officiel disponible, sa couleur, sa disponibilité, sa possession,
son état actif, son titre court et sa description Explorer. `Mes apps` et
`Explorer` passent tous deux ces mêmes entrées au même renderer de carte et aux
mêmes variables CSS `--faluss-app-accent` et
`--faluss-app-title-accent`. Explorer ajoute uniquement la description et son
action d'état ; aucune seconde variante de données n'existe.

Depuis CAP-01B.2, le catalogue AP-01 reste uniquement propriétaire des labels,
des descriptions, des couleurs, des logos, des destinations autorisées et de
l'ordre visuel Hub, Me, Date, Fans, Pro. Les décisions Hub et Me proviennent du
snapshot serveur `apps.registry` 1.0.0 produit par Faluss Apps Registry 0.2.0.
Portal demande ce snapshot exactement une fois par rendu et le réutilise pour
`Mes apps` et `Explorer`. Date, Fans et Pro restent des entrées de présentation
indisponibles : aucun faux manifeste ni faux document runtime n'est créé.

Hub apparaît dans `Mes apps` seulement comme application disponible avec une
relation active. Me exige en plus la destination canonique AP-02A déjà validée
et mise en mémoire par l'adaptateur Identity Client. L'absence ou la panne de
Me ne retire pas Hub. Le composant Daily Reward n'est préparé que pour le
binding exact `faluss-hub.daily-reward`, interface `delegated_action`, slot
`portal.apps.card_action`. `claimable` exige aussi l'action autorisée exacte ;
`claimed` conserve le binding sans action ; sans binding, le renderer reçoit
seulement l'action normale d'ouverture. Le moteur ne fournit aucune donnée
économique au navigateur et les renderers, le HTML, le CSS, le JavaScript et
les assets AP-01/AP-02A/DR-02A restent inchangés.

AP-01E conserve ce renderer et ce registre sans variation : son header unique
aligne verticalement le logo, l'identité et, dans `Mes apps`, l'action de droite.
Les limites visuelles mobiles restent explicites pour Hub `18 × 22 px`, Date
`23 × 23 px` et Pro `21.28 × 23 px`. La contrainte Me `12.43 × 23 px` est
abandonnée : elle rognait son asset officiel transparent sur certains moteurs
mobiles. Un seul track `--faluss-app-logo-column-width`, issu de la géométrie
de Hub (`18 px` sur mobile), détermine désormais le début commun des identités
Hub, Me, Date et Pro. Le canevas Me ne participe plus jamais à cette largeur :
son wrapper conserve ce track, alors que l'image officielle entière est centrée
dedans à `48 × 48 px` sur mobile (`53 × 53 px` hors breakpoint mobile). Ainsi,
son glyphe peint reste dans l'espace logo compris avant l'identité, sans
collision avec le texte et sans fenêtre de découpe, position absolue ni offset.
`object-fit: contain` préserve les proportions et le centre vertical du glyphe
reste aligné avec le groupe titre/sous-titre. Explorer place description et
action après le header. La zone Fans reste vide tant qu'aucun logo officiel
n'existe.
`Vous êtes ici` emploie le même rayon effectif de `100 px` que les autres
actions, y compris face aux états et pseudo-éléments injectés par le navigateur
ou Elementor.

Les assets Hub, Me, Date et Pro sont embarqués dans le dossier versionné du
plugin Portal. Aucun logo Fans officiel n'existe encore : sa carte conserve la
place structurelle du logo sans dessiner ni substituer un symbole. Les seules
destinations autorisées sont l'accueil HTTPS Faluss Hub et Faluss Me ; chaque
lien ouvre une nouvelle page avec `noopener noreferrer`. Les produits non
disponibles n'ont aucune URL dans le registre.

Les deux backgrounds de la carte sont partagés : le dégradé horizontal
`couleur · blanc · couleur` est assombri par une couche glass linéaire afin de
préserver la lecture. L'action `Vous êtes ici` reste locale et non
décisionnaire ; son message `Impossible d’ouvrir, vous y êtes déjà` disparaît
automatiquement en moins de trois secondes. Aucun Faluss ID, état Stripe,
référence de compte ou autre donnée technique n'est rendu.

DR-02A.1 rend la pill PF prioritaire dans le hit-testing Safari/WebKit sans
changer sa géométrie ni son rendu. Le lien global de la carte reste en couche 1,
alors que l'action PF, son formulaire et le bouton réel occupent respectivement
les couches 3, 4 et 5. Le header compact ne crée plus de contexte ou de barrière
`pointer-events` qui placerait le lien au-dessus du bouton. L'état indisponible
laisse toujours passer le clic vers la carte ; les états `claimable` et
`claimed` absorbent la zone exacte de la pill, de sorte qu'un clic PF ne peut ni
naviguer ni ouvrir une nouvelle page.

## Installation et recette technique

1. Sauvegarder les deux installations et les plugins actuels. Mettre Faluss
   Apps Registry `0.2.0` à jour sur faluss.com et faluss.me, puis mettre à jour
   uniquement Identity Client `0.5.3` et Portal `0.1.22` sur faluss.com. Ne
   modifier aucun autre plugin ni aucune politique Federation.
2. Conserver `[faluss_portal]` sur la page Elementor Canvas `/mon-faluss/`.
3. Conserver le client officiel Faluss.com activé côté Identity. Le plugin
   ajoute lui-même `/mon-faluss/` aux retours locaux autorisés en mémoire ;
   aucune configuration manuelle de widget n'est requise.
4. Vérifier en visiteur l'état d'accès et le bouton SSO, puis en membre lié la
   sidebar, les pills et le changement sans rechargement entre les sept sections.
5. Vérifier une souscription `trialing` et une offre gratuite : aucun Faluss ID
   ni identifiant Stripe ne doit apparaître dans le DOM, l'URL ou la notice.
6. Vérifier Facturation sans facture locale, puis le Customer Portal seulement
   pour un Customer réellement configuré ; son retour doit revenir à Paiement.
7. Vérifier le Master Profile, Échap, retour navigateur, absence de rendu de
   focus, `prefers-reduced-motion`, mobile et desktop. Le bloc PF doit afficher
   « Points Faluss bientôt disponibles » sans montant, même si un ledger ALB
   historique existe dans Token Engine.
8. Depuis faluss.com, lancer un nouveau parcours SSO, publier effectivement la
   carte pendant l'onboarding Faluss Me puis laisser le flux revenir sur
   `/mon-faluss/`. Ouvrir `Apps Faluss · Mes apps` : Hub et Me doivent apparaître
   immédiatement ; Me doit viser `https://faluss.me/mon-faluss`, Explorer doit
   reprendre la même route, et la card Hub dans Faluss Me doit viser
   `https://faluss.com/mon-faluss`.
9. Vérifier sur toutes les cards `Mes apps` la zone circulaire glass historique.
   Hub `claimable` n'y affiche que le badge PF non modifié et `20`; son clic ne
   navigue pas et un état `claimed` ne permet aucun second claim. Me n'affiche
   aucun montant PF. Vérifier enfin les trois états `Bientôt disponible` et que
   seul le panneau gris défile.
10. Vérifier encore les deux manifestes, `Mes apps` avec Hub puis Me pour un
    membre publié, Hub seul pour un membre sans projection Me, et `Explorer`
    avec exactement cinq cards inchangées. Simuler une indisponibilité Me doit
    seulement retirer Me du résultat membre, jamais Hub.

Cette recette WordPress réelle n'est pas couverte par la preuve automatisée et
doit être exécutée sur les deux installations avant production. Aucune recette
Stripe n'est requise par AP-02A.
