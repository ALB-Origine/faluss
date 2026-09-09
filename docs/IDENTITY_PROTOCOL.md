# Contrat Faluss Identity v1

## Objet

Le protocole relie une application cliente à une identité déjà prouvée sur `faluss.me`. Il ne partage ni cookie, ni session WordPress, ni mot de passe.

## Pré-enregistrement client

Un administrateur Faluss crée le client dans Faluss Identity. Le client possède :

- `client_id` opaque ;
- nom et statut ;
- URI de retour HTTPS exactes, sans joker ni fragment ;
- scopes autorisés ;
- secret optionnel pour les clients confidentiels, généré une seule fois, haché côté Identity et fourni hors Git au site client.

Le premier client est `pro.faluss.com`. Les URI de production doivent être HTTPS et sans joker.

## Connexion

1. Le client génère `state` (256 bits) et `code_verifier` (256 bits), conserve les deux dans une session serveur ou cookie `HttpOnly` local, puis calcule `code_challenge = BASE64URL(SHA-256(code_verifier))`.
2. Il redirige vers l'endpoint d'autorisation Faluss Identity avec `client_id`, `redirect_uri`, `state`, `code_challenge`, `code_challenge_method=S256` et les scopes demandés.
3. Faluss Identity vérifie le client actif, l'URI exacte, les scopes et `S256`, puis réutilise sa session centrale ou redirige uniquement vers son `/login` local. La demande validée et son `state` restent côté serveur pendant cette reprise.
4. Le membre confirme ou refuse explicitement les attributs demandés. Après confirmation, Identity crée un code opaque, aléatoire, à usage unique, expirant sous 60 secondes. Il est lié au Faluss ID, client, URI, challenge PKCE et scopes validés.
5. Identity redirige vers l'URI préenregistrée avec seulement `code` et `state`.
6. Le client vérifie `state`, puis échange le code côté serveur avec `grant_type=authorization_code`, `client_id`, `redirect_uri`, `code_verifier` et, pour un client confidentiel, son secret.
7. Identity consomme le code de manière atomique. Il retourne seulement les claims autorisés : `faluss_id` et, si demandé et accordé, `email` vérifié.
8. Le client crée ou retrouve son WordPress user local, confirme la liaison, ouvre une session WordPress locale et redirige vers son retour local déjà validé.

## Scopes v1

| Scope | Claims | Usage |
|---|---|---|
| `identity.basic` | `faluss_id` | obligatoire |
| `identity.email` | e-mail vérifié | création/lien du compte local avec consentement prévu |

Les seuls endpoints FI-04 sont `GET/POST /oauth/authorize` et `POST /oauth/token`.
`/oauth/token` répond avec `{ "faluss_id", "scope" }` et ajoute `email` uniquement lorsque `identity.email` a été accordé. Il ne retourne aucun access token, refresh token, donnée Pro, Date, profil public ou Token Engine.

Le scope Date n'existe pas. Date ne reçoit jamais plus que les claims strictement nécessaires à son inscription et sa connexion.

## Erreurs et journalisation

Les erreurs visibles restent génériques. Si et seulement si l'URI de retour a déjà été validée, le refus retourne `error` et le `state` au client. Les journaux d'audit utilisent un identifiant d'événement, `client_id`, type d'action et horodatage. Ils n'enregistrent jamais code, verifier PKCE, secret client, OTP, état ou e-mail brut.

## Préférence front des membres Faluss (FI-06)

Un utilisateur qui possède un profil Faluss Identity et dont l’unique rôle WordPress est `subscriber` reçoit la préférence WordPress `show_admin_bar_front=false`. Le filtre front applique la même règle à chaque affichage afin qu’une réactivation accidentelle de la préférence ne réintroduise pas la barre d’administration. Les rôles combinés ou différents — administrateur, éditeur, auteur, client WooCommerce ou tout autre rôle — ne sont jamais modifiés.

La migration FI-06 est versionnée et idempotente. Elle parcourt exclusivement les `wp_user_id` de la table des profils Faluss Identity ; elle ne parcourt pas la liste globale des utilisateurs et ne crée aucune table ni meta Elementor.

## Session membre et SSO Faluss.com (FI-06 mini-lot)

### Cause constatée et durée

Le code livré avant ce lot ne limitait pas une session WordPress membre à quinze minutes : `Faluss_Identity_Passwordless::COOKIE_TTL` vaut 600 secondes et protège uniquement le challenge OTP lié au navigateur ; `REQUEST_WINDOW` vaut 900 secondes et protège uniquement le rate limit de demande d’e-mail. Les demandes OAuth ordinaires conservées côté serveur ont aussi une durée de 600 secondes, tandis que le code d’autorisation garde sa durée de 60 secondes. Une demande first-party explicitement différée pour l’onboarding est l’unique exception : elle conserve la même poignée opaque dans le registre serveur pendant une heure, sans allonger le code OAuth. Aucun filtre `auth_cookie_expiration` n’était présent dans les plugins Faluss.

FI-06 ajoute donc un filtre explicite et fixe `auth_cookie_expiration=3600` secondes pour le seul utilisateur dont le rôle exact est `subscriber` et qui possède un profil Faluss Identity actif. Il ne réémet pas de cookie en tâche de fond : la durée reste une heure fixe à partir de l’ouverture de session. Administrateurs, rôles techniques, comptes avec plusieurs rôles, challenges OTP, rate limits et codes OAuth restent inchangés. Si une instance observait auparavant quinze minutes pour une session WordPress, cette limite provenait donc d’un filtre ou d’une configuration extérieure à ce dépôt ; le filtre FI-06 reprend désormais la maîtrise de la durée pour le membre normal.

### Reconnaissance premier parti

Le client officiel Faluss.com reste un client OAuth `authorization_code` normal. Dans **Réglages → Clients SSO Faluss** sur `faluss.me`, l’administrateur doit cocher *Client officiel Faluss.com* seulement lorsque l’unique URI déclarée est exactement `https://faluss.com/faluss-identity/callback`. Ce choix est stocké dans le registre client comme marqueur `first_party`; il est désactivé par défaut pour tout client existant ou nouveau.

Sur une action explicite nécessitant une session membre, Faluss.com crée le même `state` 256 bits, lié au cookie local `Secure`, `HttpOnly`, `SameSite=Lax`, le même verifier PKCE S256 et la même ligne d’état à usage unique de dix minutes. Si une session locale liée est déjà ouverte, il retourne directement vers la destination locale listée. Sinon, le navigateur effectue une navigation de premier niveau vers `faluss.me` :

1. Identity vérifie le client actif, l’URI exacte, `identity.basic` (et `identity.email` seulement lorsqu’il est demandé), le state et S256 avant toute approbation.
2. Sans session centrale, Identity redirige vers son formulaire passwordless existant. Ce simple affichage n’envoie aucun e-mail ; le membre doit demander lui-même son OTP.
3. Avec une session centrale active, seul le client marqué `first_party` dont le callback correspond exactement à celui de Faluss.com est autorisé sans écran de consentement. Tous les autres clients gardent le consentement explicite.
4. Pour ce seul client first-party, Identity vérifie ensuite l’état ONB-01 explicite. Un membre encore incomplet reste sur Faluss.me : la demande déjà validée est différée dans le registre serveur existant, sous la même poignée HTTP-only opaque. Publication de la carte ou choix explicite *Continuer sans carte* écrit `complete`, puis relance seulement la route locale d’autorisation. Ni la présence d’une carte, ni un brouillon, ni le retour navigateur ne valent finalisation.
5. Identity produit alors un code opaque, haché, à usage unique et limité à 60 secondes. Faluss.com l’échange côté serveur puis retrouve ou crée sa liaison locale selon les règles existantes, ouvre une session locale bornée à une heure et redirige vers le retour local whitelisté sans paramètre OAuth.

Il n’existe aucun cookie WordPress partagé, iframe silencieuse, accès implicite aux abonnements, Stripe, Tokens, Pro, Date ou profils publics. Le retour local est comparé exactement à la liste configurée et à l’origine locale. Les erreurs, state altérés/expirés ou codes rejoués ne créent ni session ni liaison et ne mettent aucune donnée sensible dans l’URL ou l’audit. Faluss.me demeure ainsi l’autorité d’identité et d’onboarding ; Faluss.com est uniquement le consommateur d’une identité Faluss finalisée.

## Navigation Faluss (FI-07)

`Navigation Faluss` est un widget Elementor à placer manuellement dans le modèle Header choisi par l’administrateur. Il ne crée ni page, ni menu, ni modèle Elementor et ne remplace pas The Plus Popup Builder / Off Canvas ou un autre widget de navigation existant.

Le déclencheur reste à l’endroit où Elementor le rend. À l’ouverture, son panneau est cloné dans un `dialog` natif placé sous `document.body`, donc dans la top layer du navigateur : il reste au-dessus d’une carte publique immersive, d’un shell WordPress ou d’un contexte d’empilement Elementor sans modifier leur `z-index` ni leurs interactions. Chaque instance possède ses identifiants propres, ferme avec le fond ou Échap, rend le focus à son déclencheur et respecte `prefers-reduced-motion`.

Les trois seules actions sont `Mon Faluss` (`/mon-faluss/`), `Ma liste` (`/list/`) et une action session : `Connexion` vers `/login/` avec un retour local validé pour un visiteur anonyme, ou l’URL WordPress de déconnexion avec nonce pour un membre connecté. La page `/list/` est créée et ajoutée à la navigation manuellement par l’administrateur ; le widget ne la crée jamais.

FI-07.1 maintient cette architecture et rend les réglages visuels effectifs : le fond cliquable est un élément neutre, isolé des styles de boutons du thème, avec états Normal et Survol propres. La bulle sépare largeur, hauteur, icône, padding, marge, couleurs et opacités par état ; son flou ne concerne que son arrière-plan. La durée de transition configure le panneau et le fond, tandis que le verrou de défilement mémorise puis restaure la position et les styles de défilement sans transformation ou mise à l’échelle de la page.
