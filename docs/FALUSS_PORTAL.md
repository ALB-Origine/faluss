# PF-01E - Faluss Portal Foundation

## Statut et frontière

PF-01 ajoute `Faluss Portal`, un plugin front-office installé sur `faluss.com`.
Il rend le shortcode `[faluss_portal]` destiné à la page privée
`/mon-faluss/`. Le portail est une surface de lecture et de navigation : il ne
crée aucune identité, donnée d'abonnement, transaction, facture, préférence
transversale ou profil universel.

PF-01E aligne ce socle sur les frames et les captures réelles Shell, Master
Profile, Abonnement et Facturation. Le shell conserve une sidebar, ses bulles
monochromes, l'indicateur vertical noir, les pills contextuelles noires, le
panneau clair et l'accès profil par l'avatar de sidebar uniquement. La mise en oeuvre reste une grille
responsive : aucun cadre figé ou positionnement absolu de page n'est utilisé.
La grille est limitée à `100svh` ; la sidebar reste immobile et non défilante,
tandis que le seul défilement vertical du shell appartient au panneau gris.

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
| Apps, analytics, quêtes, boutique | contrats futurs propres aux applications | état vide explicite | aucune |

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
- **Apps Faluss / Mes apps** et **Explorer** ne rendent que des états vides
  honnêtes tant qu'aucun contrat d'application officiel n'est disponible.
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
centrés, réserve droite équivalente. En PF-01E, son CTA profil adopte le bouton primaire noir Faluss.com,
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
interactifs restent sémantiques, mais PF-01E neutralise explicitement et
uniquement dans le portail les contours, ombres, bordures colorées et
`-webkit-tap-highlight-color` injectés par le navigateur, Safari ou Elementor
sur les cellules, contrôles, icônes, avatar, pseudo-éléments et états `hover`,
`focus`, `focus-visible` et `active`. Aucun script ne force le focus à
l'ouverture du profil. Les trois zones restent de vrais boutons libellés pour
les technologies d'assistance, sans habillage visuel parasite.

Le header contextuel est un enfant direct du panneau gris. Sa barre utilise
`100%` de la largeur locale du panneau, avec un retrait symétrique de `18 px`,
et `margin-inline: auto`. Elle ne contient aucune largeur ou translation liée
au viewport ou à la sidebar. Tous ses segments utilisent `flex: 1 1 0` : Vue,
Performance, Revenus et Sources reçoivent donc la même largeur, indépendamment
de leurs libellés. L'indicateur noir calcule son déplacement uniquement depuis
la largeur intérieure de cette barre locale. PF-01E ne change ni cette
géométrie, ni le retrait, ni le centrage : seuls les labels gagnent exactement
`2 px`, dont `12.5 px` sur mobile, sans règle particulière pour Analytics.

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

Une recette WordPress réelle et une recette Stripe ne font pas partie de la
preuve statique PF-01 ; elles doivent être exécutées sur une installation de
test avant production.
