# Onboarding Faluss — ONB-01 / ONB-02

ONB-01 sépare la preuve d’identité de la création facultative d’une carte. Un `faluss_id` est une référence technique opaque, stable et uniquement serveur. L’identifiant visible est le slug public FI-03 ; il est unique, réservé une fois et protégé des routes et pages WordPress.

## Mise en place

1. Créez manuellement une page Elementor publiée pour l’onboarding et ajoutez le widget **Onboarding Faluss** (ou placez `[faluss_identity_onboarding]` sur une page existante).
2. Dans **Réglages → Onboarding Faluss**, sélectionnez cette page. Son URL publique réelle (par exemple `/start`) devient la destination canonique après passwordless ; son slug n’est jamais codé en dur. Sans sélection valide, la route de secours `/commencer` reste utilisable.
3. Après connexion, le membre peut sélectionner **Créer mon Faluss** ou **Continuer sans carte**. La seconde option ne crée aucun profil public et le membre pourra revenir à `/commencer` plus tard.

Le choix de création vérifie l’identifiant de façon dynamique, puis le réserve définitivement côté serveur dans la même table FI-03 que les profils publics. Une collision, une route WordPress, une page existante ou un mot réservé est refusé. Un membre ne peut jamais modifier un slug déjà réservé ; une primitive de support séparée exige `manage_options`.

## Retours passwordless

Les CTA de teaser et de récompense créent une poignée opaque, courte, signée et conservée côté serveur. Elle comprend seulement une intention autorisée et un retour local validé ; elle ne comprend jamais un e-mail, un secret ou un `faluss_id`. Après la preuve : une autorisation SSO locale reste prioritaire, un teaser ou une récompense revient sur sa carte, et une intention de création ouvre la page Elementor sélectionnée (ou `/commencer` en secours). Un retour local générique, notamment le bouton Navigation Faluss, ne reprend sa page précédente qu’après une décision d’onboarding terminée.

ONB-01 ne crée ni données Faluss Link, ni thème, ni entitlement, ni paiement, ni abonnement.

## Création guidée de carte (ONB-02)

Après une réservation réussie, le même widget sur la page Elementor sélectionnée devient un parcours de création repris à la dernière étape enregistrée. Il est disponible uniquement pour un membre connecté avec une identité active, un choix `create_card`, un slug réservé et un profil FI-03 encore en brouillon. Le parcours ne crée aucune page WordPress et ne dépend jamais d’un slug de page imposé.

Les données restent exclusivement dans leurs propriétaires canoniques :

| Étape | Données enregistrées | Source canonique |
|---|---|---|
| Nom, bio, avatar, publication et liens de compatibilité | profil public | Faluss Identity (`faluss_identity_public_profiles`) |
| Bordure d’avatar, police autorisée, traitement du nom, fond, boutons, réseaux et leurs URL | préférences de carte | Faluss Link (`faluss_link_cards`) |
| Liens libres ordonnés | blocs validés | Faluss Link (`faluss_link_blocks`) |

L’avatar est facultatif. Son envoi est une action explicite, protégée par nonce, réservée au membre connecté et limitée à une image dont il est l’auteur. Le profil Identity ne conserve que l’identifiant de la pièce jointe validée ; aucune image n’est supprimée automatiquement si le parcours est interrompu ou si le membre revient en arrière.

Les réseaux sont choisis dans le catalogue Faluss actif puis validés comme URL HTTPS (ou comme identifiant converti vers l’URL HTTPS connue du réseau). Les liens libres utilisent exclusivement HTTPS. Un brouillon ne devient jamais public. L’action finale publie explicitement le profil Identity, marque l’état ONB-01 terminé de façon idempotente, puis renvoie vers `/mon-faluss/`.

Le rendu d’aperçu utilise le même résolveur et le même markup de carte Faluss Link que la carte publique. Aucune table, copie d’identité, donnée métier, entitlement, abonnement, paiement, token ou règle économique n’est ajouté par ONB-02.
