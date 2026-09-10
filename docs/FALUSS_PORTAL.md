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
| Points Faluss | aucune source PF officielle dans PF-01 | « Points Faluss bientôt disponibles », sans valeur | aucune |
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
  Faluss Me n'y est ajouté que si l'API Identity canonique est chargée sur la
  même instance et fournit une preuve locale d'une carte `published` appartenant
  au même Faluss ID. En l'absence de cette preuve locale, aucune possession
  Faluss Me n'est supposée. Date, Fans et Pro ne sont pas disponibles et ne
  peuvent donc pas apparaître dans `Mes apps`.
- **Apps Faluss / Explorer** présente les cinq entrées du registre partagé.
  Faluss Hub est l'application active et affiche `Vous êtes ici` sans
  navigation ; Faluss Me est la seule application disponible non active et son
  lien est limité à `https://www.faluss.me/`. Date, Fans et Pro affichent
  `Bientôt disponible` sans lien ni URL de production inventée. Aucun appel
  inter-domaine, côté navigateur ou côté serveur, n'est effectué pour déduire
  la possession.
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

AP-01A conserve ce renderer et ce registre sans variation : son header unique
aligne verticalement le logo, l'identité et, dans `Mes apps`, l'action de droite.
La taille de chaque zone logo vient exclusivement de
`--faluss-app-logo-size` (`58 px` sur desktop, `46 px` sur mobile), et l'image
utilise `object-fit: contain` sans règle propre à une application. Le même
header est repris dans Explorer avant la description et l'action d'état.
Pour aligner l'occupation visuelle sur le symbole Me sans valeur CSS propre à
chaque produit, les copies Portal de Hub, Date et Pro conservent leurs pixels
officiels sans mise à l'échelle ni rognage au centre d'un même canevas PNG
transparent de `239 × 239 px`. Le fichier Me de référence reste inchangé et la
zone Fans reste vide tant qu'aucun logo officiel n'existe.
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

## Installation et recette technique

1. Installer uniquement le ZIP `faluss-portal` puis l'activer sur faluss.com.
2. Ajouter `[faluss_portal]` à la page Elementor Canvas `/mon-faluss/`.
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
8. Ouvrir `Apps Faluss · Mes apps` : Faluss Hub doit être présent ; Faluss Me
   ne doit apparaître que si une carte publiée est effectivement vérifiable sur
   cette instance. Ouvrir `Explorer`, vérifier les cinq cartes, le lien officiel
   Faluss Me, les trois états `Bientôt disponible` non cliquables et le message
   temporaire de Faluss Hub. Vérifier enfin que seul le panneau gris défile.

Une recette WordPress réelle et une recette Stripe ne font pas partie de la
preuve statique PF-01 ; elles doivent être exécutées sur une installation de
test avant production.
