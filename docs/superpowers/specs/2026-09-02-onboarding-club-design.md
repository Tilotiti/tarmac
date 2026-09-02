# Inscription autonome des clubs et checklist de configuration

Date : 2026-09-02. Statut : validé en conversation, en attente de relecture du document.

## 1. Objectif

Un président de club crée son compte et son club depuis la page d'accueil, sans passer par l'admin, puis configure le club guidé par une checklist sur son tableau de bord. L'admin garde un contrôle a posteriori : notification à chaque création, désactivation en un clic.

Public visé : des bénévoles peu à l'aise avec l'informatique. Chaque écran tient sur un mobile (320 px), porte un seul bouton principal et se reprend après une fermeture d'onglet.

Hors périmètre : plans d'entretien modèles, validation manuelle avant activation, captcha.

## 2. Existant réutilisé

- Inscription par invitation (`SecurityController::acceptInvitation`) et `RegistrationFormType`.
- Import d'invitations XLSX (`InvitationImportService`), rôles `isManager` / `isInspector` / `isPilote` portés par `Invitation` et `Membership`.
- Message d'accueil du club (`Club::welcomeMessage`) déjà affiché sur le tableau de bord.
- Admin des clubs (`Admin\ClubController`) avec activer / désactiver.
- Cookie de session partagé sur `.%domain%` : une connexion sur `www` vaut sur les sous-domaines.
- `symfonycasts/reset-password-bundle` déjà installé, même famille que `verify-email-bundle`.
- Clés de traduction `landingValue*` (open source, bénévole, sans pub) présentes dans le catalogue et non affichées.

## 3. Partie 1 : inscription du compte et du club

Contrôleur `App\Controller\Public\OnboardingController`, hôte `www.%domain%`, préfixe `/creer-mon-club`. Une route par écran.

### 3.1 Écran « Compte » — `public_onboarding_account`

- Champs : prénom, nom, e-mail, mot de passe (répété). Formulaire `ClubRegistrationAccountType`.
- Sous le titre, deux lignes : Tarmac est gratuit, fait par un bénévole pour les clubs associatifs, sans engagement.
- Honeypot : champ caché `website`, la soumission est ignorée s'il est rempli.
- Rate limiter `onboarding_account` : 5 soumissions par IP et par heure (`symfony/rate-limiter`, à installer).
- Message d'erreur de `NotCompromisedPassword` et `PasswordStrength` traduits en français compréhensible (« Ce mot de passe apparaît dans des fuites de données connues, choisissez-en un autre »).
- Un e-mail déjà utilisé affiche : « Un compte existe déjà avec cette adresse » et un lien vers la connexion, qui ramène à l'écran « Club » une fois connecté (paramètre `_target_path`).
- À la soumission valide : `User` créé avec `isVerified = false`, e-mail de confirmation envoyé, redirection vers l'écran « Confirmez votre e-mail ».
- Utilisateur déjà connecté : redirection directe vers « Club » s'il est vérifié, vers « Confirmez votre e-mail » sinon.

### 3.2 Écran « Confirmez votre e-mail » — `public_onboarding_verify`

- Texte : « Nous avons envoyé un lien à <e-mail>. Cliquez dessus pour continuer. »
- Bouton « Renvoyer le lien », rate limiter 3 envois par heure et par compte.
- Lien signé produit par `symfonycasts/verify-email-bundle` (à installer), durée de vie 24 h. Le clic sur le lien (`public_onboarding_verify_confirm`) pose `isVerified = true`, connecte l'utilisateur via `Security::login()` et redirige vers « Club ».
- Le lien expiré affiche « Ce lien a expiré » et le bouton « Renvoyer ».
- Commande `app:users:purge-unverified` : supprime les comptes `isVerified = false`, sans adhésion, créés depuis plus de 7 jours. À planifier avec Heroku Scheduler (une fois par jour). Aucun cron n'existe aujourd'hui dans le projet.

### 3.3 Écran « Club » — `public_onboarding_club`

- Accès : utilisateur connecté et vérifié. Sinon, redirection vers l'écran adéquat.
- Champs : nom du club, aérodrome (obligatoire), sous-domaine.
- Aérodrome : champ texte avec autocomplétion (Stimulus `aerodrome-search`) sur `GET /creer-mon-club/aerodromes?q=`, qui cherche dans `Aerodrome` par nom, commune et code OACI (préfixe ou sous-chaîne, insensible aux accents) et renvoie dix résultats « Nom (Commune) LFXX ». La sélection remplit un champ caché `aerodrome` (id). L'aérodrome est obligatoire pour le public. Lien « Mon terrain n'est pas dans la liste » (icône d'aide, style texte d'aide) : affiche une note invitant à écrire à contact@tarmac.center, sans toucher à la sélection. Seul l'administrateur peut enregistrer un club sans aérodrome, avec une commune en texte libre (`Club::locationLabel`) ; un tel club n'a pas de coordonnées et la détection de doublon retombe sur le nom seul.
- Le sous-domaine se calcule côté client (Stimulus `subdomain-preview`) à partir du nom : minuscules, accents retirés, caractères hors `[a-z0-9-]` remplacés par un tiret, tirets doublés ou en bordure supprimés, 30 caractères max. L'adresse complète s'affiche sous le champ : « Vos membres se connecteront sur https://<sous-domaine>.tarmac.center ». Le champ reste modifiable ; à chaque changement, une requête `GET /creer-mon-club/sous-domaine?value=` renvoie disponible / pris / interdit.
- Validation serveur : regex `^[a-z0-9][a-z0-9-]{0,28}[a-z0-9]$` (2 à 30 caractères), unicité, mots réservés : `www`, `app`, `admin`, `api`, `mail`, `smtp`, `imap`, `ftp`, `staging`, `test`, `demo`, `tarmac`. Constante `SubdomainService::RESERVED`.
- À la soumission valide, `ClubDuplicateFinder::find(name, aerodrome)` est appelé (voir 3.5). Sans résultat, ou si le formulaire porte `confirmed=1`, `ClubRegistrationService::register()` crée le club et redirige vers « Bienvenue ». Avec résultat, redirection vers l'écran « Doublon ».

### 3.4 `ClubRegistrationService::register(User $user, Club $club): Club`

Dans une transaction :

1. Persiste le club (`active = true`, `createdBy = $user`).
2. Crée une `Membership` gestionnaire pour l'utilisateur (`isManager = true`).
3. Envoie l'e-mail `email/clubCreated.html.twig` à chaque `User` ayant `ROLE_ADMIN` : nom, adresse, aérodrome, créateur (nom, e-mail), date, et les lignes « Doublon probable : <club> » ou « Voisin sur le même terrain : <club> » quand la création a été confirmée malgré des résultats. Lien vers la fiche admin du club.
4. Envoie l'e-mail `email/welcomePresident.html.twig` au créateur : adresse du club, ce que fait la checklist, lien vers le guide, adresse de contact.

Le `createdBy` sert à l'admin et au contrôle « Membres invités » (§4.2).

### 3.5 Doublon — `public_onboarding_duplicate`

`ClubDuplicateFinder::find(string $name, ?Aerodrome $aerodrome): DuplicateReport` parcourt tous les clubs actifs en PHP (quelques dizaines, pas d'index) et classe chacun selon deux signaux :

- **Nom proche** : noms normalisés (minuscules, accents et ponctuation retirés, mots vides `de`, `du`, `des`, `le`, `la`, `les`, `d`, `l` retirés, formes `aeroclub`, `aero club`, `aero-club`, `ac`, `club de vol a voile`, `cvv`, `centre de vol a voile` ramenées à un jeton commun) dont l'un contient l'autre, ou dont la distance de Levenshtein rapportée à la longueur est inférieure à 0,25.
- **Distance** : même aérodrome, ou moins de 25 km entre les coordonnées (formule de Haversine). Un club sans coordonnées n'a pas de signal de distance.

| Nom proche | Distance | Classement |
|---|---|---|
| oui | même aérodrome | doublon probable |
| oui | moins de 25 km | doublon possible |
| oui | loin | homonyme, ignoré |
| oui | inconnue (un des deux clubs sans coordonnées) | doublon possible |
| non | même aérodrome | voisin sur le même terrain |
| non | autre | ignoré |

Le rapport contient deux listes : `duplicates` (probable et possible) et `neighbours`. Plusieurs clubs cohabitent sur un même aérodrome (aéroclub avion et club planeur, ou deux clubs planeur) : un voisin n'est jamais traité en doublon.

Écran, affiché dès qu'une des listes n'est pas vide, sans nom ni e-mail de gestionnaire :

- Avec des doublons : titre « Ce club existe déjà ? », liste des doublons (nom, aérodrome, adresse).
- Avec des voisins seulement : titre « D'autres clubs sont déjà sur ce terrain », liste des voisins, phrase « Si votre club est l'un d'eux, demandez à le rejoindre. Sinon, continuez : plusieurs clubs peuvent partager un aérodrome. »
- Avec les deux : la liste des doublons en premier, puis les voisins sous un sous-titre.

Deux sorties, identiques dans les trois cas :

- **« Demander à rejoindre ce club »** : ouvre `public_onboarding_join_request` (§3.6).
- **« Ce n'est pas mon club, continuer »** : renvoie le formulaire « Club » avec `confirmed=1` et les valeurs saisies ; la création passe, l'e-mail admin liste les doublons et les voisins.

### 3.6 Demande d'adhésion — `JoinRequest`

Entité `JoinRequest` : `club`, `user` (le demandeur, connecté et vérifié), `message` (texte, 500 caractères max), `createdAt`, `handledAt` (nullable), `handledBy` (nullable). Une seule demande ouverte par couple utilisateur / club.

Écran : message libre facultatif, bouton « Envoyer ma demande ». À l'envoi :

- E-mail `email/joinRequest.html.twig` à chaque gestionnaire du club : nom, e-mail et message du demandeur, lien vers `club_invitation_create` prérempli (`?email=&firstname=&lastname=`).
- Le demandeur voit : « Les gestionnaires de <club> ont été prévenus. Sans réponse sous quelques jours, écrivez à contact@tarmac.center. » Lien vers ses clubs.

Sur la page Invitations du club, un bloc « Demandes d'adhésion » liste les demandes ouvertes avec deux boutons : « Inviter » (même lien prérempli, la demande passe `handledAt` à l'acceptation de l'invitation par `InvitationService::acceptInvitation`) et « Ignorer » (pose `handledAt`). L'admin voit toutes les demandes ouvertes sur la fiche du club.

`InvitationController::create` accepte les paramètres de préremplissage en GET.

### 3.7 Écran « Bienvenue » — `public_onboarding_done`

Récapitulatif : nom, adresse cliquable, « Votre club est prêt. Le tableau de bord vous indique ce qu'il reste à configurer. » Bouton unique « Ouvrir mon club » vers le tableau de bord du sous-domaine.

### 3.8 Points d'entrée

- Landing : bouton « Créer mon club » (§5).
- `public_clubs` (liste des clubs de l'utilisateur) : bouton « Créer un club ».
- Menu avatar (`public/base.html.twig` et `club/base.html.twig`) : entrée « Créer un club ».

`security.yaml` : `^/creer-mon-club` accessible sans authentification (les écrans font leurs propres redirections), `^/verify` idem.

## 4. Partie 2 : checklist de configuration

### 4.1 Service `App\Service\ClubHealthCheck`

`check(Club $club): HealthReport`. Le rapport contient une liste ordonnée de `HealthCheckItem` : `key`, `status` (`done` / `todo`), `route` d'action et paramètres, `essential` (bool). Chaque contrôle est une classe dans `App\Service\HealthCheck\` implémentant `HealthCheckInterface::check(Club): HealthCheckItem`, enregistrée par tag `app.health_check` avec une priorité pour l'ordre. Rien n'est stocké par étape : tout se déduit des données à chaque affichage. Chaque contrôle a un test unitaire.

### 4.2 Contrôles de démarrage (phase 1)

| Clé | Condition « fait » | Action | Essentiel |
|---|---|---|---|
| `aerodrome` | `Club::aerodrome` non nul | `club_settings_aerodrome` | non |
| `equipment` | au moins un `Equipment` actif | `club_equipment_new` | oui |
| `members` | au moins une `Invitation` ou une `Membership` dont l'utilisateur n'est pas `createdBy` | `club_invitations` | oui |
| `secondManager` | au moins deux `Membership` avec `isManager` | `club_members` | non |
| `inspector` | au moins une `Membership` avec `isInspector` | `club_members` | oui |
| `plan` | au moins une `PlanApplication` active | `club_plans_explain` | non |
| `welcomeMessage` | `welcomeMessage` non vide | `club_dashboard_edit_welcome_message` | non |

Chaque contrôle porte trois clés de traduction : `healthCheck<Key>Title`, `healthCheck<Key>Why` (une phrase sur l'utilité, écrite pour le président : « Sans qualifié, les sous-tâches à inspecter restent bloquées ») et `healthCheck<Key>Action` (le libellé du bouton).

### 4.3 Carte sur le tableau de bord

Visible des gestionnaires seulement (`is_granted('MANAGE')`), placée au-dessus des sections existantes. Titre « Votre Tarmac est-il prêt ? », sous-titre « 3 sur 7 », une ligne par contrôle avec icône d'état, titre, phrase d'utilité et bouton d'action (44 px de haut). Les contrôles faits sont repliés sous un lien « Voir ce qui est fait ».

Masquage : bouton « Masquer », qui pose `Club::healthCheckDismissedAt`. La carte masquée revient quand un contrôle essentiel est `todo` et que le masquage date de plus de 30 jours. Un badge « Configuration 3/7 » reste dans le menu Club tant que tout n'est pas fait, et rouvre la carte (`?healthCheck=1` remet `healthCheckDismissedAt` à null).

La carte disparaît d'elle-même quand tous les contrôles sont faits.

### 4.4 Page « Votre aérodrome » — `club_settings_aerodrome`

Route sur le sous-domaine, réservée aux gestionnaires. Un seul champ, le même sélecteur d'aérodrome que l'inscription (§3.3), avec le même lien « Mon terrain n'est pas dans la liste » renvoyant vers l'administrateur (pas de commune libre pour les gestionnaires). L'enregistrement copie latitude et longitude depuis l'aérodrome. C'est par cette page que les clubs déjà présents sur Tarmac renseignent leur terrain, sans passer par l'admin : pour eux, la carte de checklist réapparaît avec ce seul item.

### 4.5 Page « Comprendre les plans d'entretien » — `club_plans_explain`

Route sur le sous-domaine, réservée aux gestionnaires. Trois cartes en deux phrases chacune : un plan décrit les opérations d'un type de matériel ; on l'applique à chaque appareil ; Tarmac génère les tâches et les renouvelle. Lien vers la section « Plans d'entretien » du guide. Bouton « Créer mon premier plan » vers `club_plan_new`. Mention : « Vous pouvez aussi créer des tâches ponctuelles sans plan. »

### 4.6 Contrôles de santé courante (phase 2)

Même mécanisme, priorité inférieure, non essentiels : matériel actif sans plan appliqué, tâche en retard, sous-tâches en attente d'inspection sans qualifié actif, invitations expirées non renouvelées.

### 4.7 Import d'invitations en deux temps (phase 2)

`InvitationImportService` se scinde en `analyse(UploadedFile, Club): ImportPreview` (lignes retenues avec e-mail, prénom, nom ; lignes ignorées avec raison) et `send(ImportPreview, Club, array $roles)`. Le contrôleur `club_invitation_import` affiche l'aperçu après l'envoi du fichier, l'aperçu étant conservé en session, puis un second formulaire avec, par ligne retenue, les cases gestionnaire, qualifié, pilote, et le bouton « Envoyer les invitations ». Les rôles sont reportés sur chaque `Invitation` ; `acceptInvitation` les copie déjà sur la `Membership`.

Formats acceptés : XLSX, XLS, CSV (séparateur `;` ou `,`, détecté). En-têtes reconnus, insensibles à la casse et aux accents : `courriel`, `e-mail`, `email`, `mail`, `adresse`, `adresse e-mail` pour l'e-mail ; `prénom`, `prenom` ; `nom`, `nom de famille`. Un fichier modèle `public/downloads/modele-import-membres.xlsx` est proposé sur la page.

## 5. Page d'accueil

- Héros : bouton principal « Créer mon club » vers `public_onboarding_account` pour un visiteur non connecté ; « Se connecter » en bouton fantôme. Sous les boutons : « Gratuit pour les associations. Aucune carte bancaire, aucun engagement. » Visiteur connecté : « Mes clubs » reste principal, « Créer un club » en fantôme.
- Bloc valeurs : afficher les trois cartes `landingValueOpenSource`, `landingValueVolunteer`, `landingValueNoAds` sous le héros.
- « Comment ça marche » : quatre étapes réécrites : créez votre club, déclarez votre matériel, invitez vos membres, appliquez vos plans d'entretien. Clés `landingStep1..4` réécrites.
- Bloc contact : titre « Besoin d'un coup de main ? », texte « Écrivez-moi, je réponds et je vous aide à démarrer. », placé après « Comment ça marche ». L'adresse mailto reste.
- CTA final : deux colonnes. « Vous êtes membre ? Demandez une invitation à votre club. » et « Vous présidez un club ? Créez-le maintenant. » avec le bouton. Les clés `landingCtaBody` et `landingFinalCtaBody` (« Tarmac fonctionne sur invitation ») sont remplacées.
- Pas de compteur de clubs.

## 6. Admin

- E-mail de notification à chaque création (§3.4).
- Fiche club : créateur, aérodrome, date, demandes d'adhésion ouvertes, clubs sur le même terrain.
- Formulaire admin du club (`ClubType`) : même champ aérodrome avec autocomplétion, pour renseigner les clubs existants.
- Liste admin des clubs : badge « Sans aérodrome » sur les clubs concernés et filtre correspondant, pour suivre la reprise. La reprise se fait soit par l'admin, soit par les gestionnaires du club via la checklist (§4.4).
- Dashboard admin : bloc « Clubs créés ces 30 derniers jours » avec nom, créateur, matériels, membres, invitations, bouton vers la fiche.

## 7. Guide utilisateur

- Nouvelle section « Créer votre club » après « À quoi sert Tarmac ? » : compte, e-mail, club, doublon, ce qui se passe ensuite.
- « Premiers pas » réécrite en deux parties : « Vous avez reçu une invitation » (contenu actuel) et « Vous venez de créer votre club » (la checklist, dans l'ordre).
- « Membres & invitations » : demandes d'adhésion, et en phase 2 l'aperçu d'import avec rôles.

## 8. Modèle de données

- `Aerodrome` : `icaoCode` (string 4, unique, nullable car certains terrains n'en ont pas), `name`, `municipality` (nullable), `latitude`, `longitude` (float), `sourceId` (id OurAirports, unique). Chargée par la commande `app:aerodromes:import` depuis `airports.csv` d'OurAirports (domaine public), filtre `iso_country = FR` et `type` dans `small_airport`, `medium_airport`, `large_airport` ; relance idempotente sur `sourceId`. Le CSV n'est pas commité : la commande le télécharge depuis `https://davidmegginson.github.io/ourairports-data/airports.csv` ou lit un chemin passé en argument. À lancer une fois en production après la migration.
- `Club` : `aerodrome` (ManyToOne Aerodrome, nullable, `SET NULL`), `locationLabel` (string 255, nullable, commune saisie quand le terrain n'est pas listé), `latitude` et `longitude` (float, nullables, copiés de l'aérodrome à l'inscription et à chaque changement d'aérodrome), `createdBy` (ManyToOne User, nullable, `SET NULL`), `healthCheckDismissedAt` (datetimetz immutable, nullable). Les clubs existants ont tout à null jusqu'à ce que l'admin renseigne leur aérodrome.
- `JoinRequest` : voir §3.6.
- `User` : aucun changement, `isVerified` existe.
- Une migration Doctrine par lot.

## 9. Traductions

Toutes les chaînes en clés `lowerCamelCase` ajoutées à la main dans `translations/messages+intl-icu.fr.xlf`, groupées sous un commentaire par écran. Vérification par `lint:xliff`.

## 10. Tests

- Unitaires : `ClubDuplicateFinder` (les cinq lignes de la grille de §3.5, dont deux clubs sur le même terrain avec des noms différents), normalisation des noms, Haversine, chaque contrôle de `ClubHealthCheck`, génération du sous-domaine, mots réservés, `ClubRegistrationService` (club, adhésion, e-mails), parsing du CSV OurAirports sur un extrait fixé dans `tests/fixtures`.
- Fonctionnels (`WebTestCase`, nouveau dans le projet, base de test à mettre en place) : parcours complet compte → e-mail → club → bienvenue ; parcours doublon → demande d'adhésion ; utilisateur connecté qui crée un second club ; page d'accueil avec les deux états de boutons.

## 11. Sécurité et abus

- Honeypot et rate limiter sur le compte et le renvoi d'e-mail.
- E-mail vérifié avant toute création de club.
- Purge des comptes non vérifiés à 7 jours.
- Aucune donnée de gestionnaire exposée sur l'écran doublon.
- Un utilisateur peut créer plusieurs clubs ; pas de plafond en phase 1, l'admin voit chaque création.

## 12. Phasage

Phase 1 : §3 complet, §4.1 à 4.5, §5, §6, §7 (hors import), §8, §10.
Phase 2 : §4.6, §4.7, géocodage de la commune saisie en texte libre via le service Géoplateforme (sans clé, 50 appels par seconde et par IP) pour donner des coordonnées aux clubs sans aérodrome listé, carte des clubs sur la landing, guide correspondant.

## 13. Dépendances à installer

- `symfonycasts/verify-email-bundle`
- `symfony/rate-limiter` (et `symfony/lock` s'il n'est pas déjà tiré)
- Heroku Scheduler pour `app:users:purge-unverified`
- Données OurAirports (`airports.csv`, domaine public), chargées par `app:aerodromes:import`
