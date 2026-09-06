# Onboarding Faluss — ONB-01

ONB-01 sépare la preuve d’identité de la création facultative d’une carte. Un `faluss_id` est une référence technique opaque, stable et uniquement serveur. L’identifiant visible est le slug public FI-03 ; il est unique, réservé une fois et protégé des routes et pages WordPress.

## Mise en place

1. Créez manuellement une page Elementor publiée pour l’onboarding et ajoutez le widget **Onboarding Faluss** (ou placez `[faluss_identity_onboarding]` sur une page existante).
2. Dans **Réglages → Onboarding Faluss**, sélectionnez cette page. Son URL publique réelle (par exemple `/start`) devient la destination canonique après passwordless ; son slug n’est jamais codé en dur. Sans sélection valide, la route de secours `/commencer` reste utilisable.
3. Après connexion, le membre peut sélectionner **Créer mon Faluss** ou **Continuer sans carte**. La seconde option ne crée aucun profil public et le membre pourra revenir à `/commencer` plus tard.

Le choix de création vérifie l’identifiant de façon dynamique, puis le réserve définitivement côté serveur dans la même table FI-03 que les profils publics. Une collision, une route WordPress, une page existante ou un mot réservé est refusé. Un membre ne peut jamais modifier un slug déjà réservé ; une primitive de support séparée exige `manage_options`.

## Retours passwordless

Les CTA de teaser et de récompense créent une poignée opaque, courte, signée et conservée côté serveur. Elle comprend seulement une intention autorisée et un retour local validé ; elle ne comprend jamais un e-mail, un secret ou un `faluss_id`. Après la preuve : une autorisation SSO locale reste prioritaire, un teaser ou une récompense revient sur sa carte, et une intention de création ouvre la page Elementor sélectionnée (ou `/commencer` en secours). Un retour local générique, notamment le bouton Navigation Faluss, ne reprend sa page précédente qu’après une décision d’onboarding terminée.

ONB-01 ne crée ni données Faluss Link, ni thème, ni entitlement, ni paiement, ni abonnement. ONB-02 écrira plus tard les préférences de carte depuis ce même état Identity après une réservation effective.
