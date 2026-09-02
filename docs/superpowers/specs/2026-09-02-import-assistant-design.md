# Assistant d'import de fichiers (matériel, puis invitations)

Date : 2026-09-02. Statut : validé en conversation.

## 1. Objectif

Un gestionnaire importe la liste du matériel de son club depuis un fichier CSV, XLS ou XLSX venu de n'importe quel logiciel, en rapprochant lui-même les colonnes du fichier des données de Tarmac, avec un aperçu des valeurs pour ne pas se tromper, y compris quand le fichier n'a pas de ligne de titres. Le même assistant remplace ensuite l'import d'invitations, aujourd'hui limité au format Givav et sans aperçu avant envoi.

Hors périmètre : l'import des plans d'entretien (format structuré généré par Tarmac), l'affectation des pilotes propriétaires et du responsable d'un matériel.

## 2. Contraintes

- Le fichier n'est jamais conservé sur le disque du serveur : il est copié sur S3 (filesystem `oneup_flysystem.s3_filesystem`, déjà utilisé pour les achats) sous `imports/<club id>/<uuid>.<ext>` dès l'écran 1, relu depuis S3 à chaque étape, supprimé à la fin de l'import ou par la commande de purge (`app:imports:purge`, fichiers de plus de 24 h). Le multi-serveur ne casse rien : seule la session porte la clé.
- La session ne contient que l'état de l'assistant : clé S3, nom d'origine, choix « ligne de titres », rapprochements. Jamais les lignes.
- Lecture par PhpSpreadsheet (CSV, XLS, XLSX). CSV : séparateur détecté entre `;`, `,` et tabulation sur les premières lignes ; encodage UTF-8 ou Windows-1252 détecté et converti. Taille maximale 2 Mo, 2 000 lignes de données.
- Toute écriture en base attend la confirmation de l'écran 3. Rien n'est créé avant.
- Un import est réservé aux gestionnaires (`#[IsGranted('MANAGE')]`).
- Traductions en clés `lowerCamelCase` dans le catalogue XLF ; templates camelCase, mobile-first, cibles 44 px.

## 3. L'assistant, trois écrans

Un composant générique `ImportAssistant` paramétré par une « définition d'import » (`ImportDefinitionInterface`) : la liste des champs cibles (clé, libellé, obligatoire, synonymes pour la pré-affectation, éventuel rapprochement de valeurs), la validation d'une ligne, et l'action d'import d'une ligne. Deux définitions : `EquipmentImportDefinition` et `InvitationImportDefinition`.

### 3.1 Écran 1 — le fichier

Champ fichier, texte d'aide (formats, taille, « le fichier peut venir de n'importe quel logiciel »). À la soumission : copie sur S3, lecture des 200 premières lignes, détection d'en-tête (heuristique : première ligne sans cellule numérique, sans doublon, sans cellule vide sur les colonnes non vides, et textes courts), enregistrement de l'état en session, redirection vers l'écran 2. Erreurs : format illisible, fichier vide, trop grand.

### 3.2 Écran 2 — le rapprochement des colonnes

- En haut, la case « La première ligne contient les titres des colonnes », pré-cochée selon la détection. La changer recharge l'écran (les colonnes sans en-tête s'appellent Colonne A, B, C…).
- Une ligne par champ cible : libellé (et « obligatoire »), un menu déroulant des colonnes du fichier (avec « — ignorer — »), et sous le menu un aperçu des trois premières valeurs non vides de la colonne choisie. Le menu est pré-rempli par synonymes sur les en-têtes (insensible aux accents et à la casse).
- Pour un champ à valeurs contraintes (le type de matériel, les rôles d'un membre), un second bloc « Valeurs trouvées » : chaque valeur distincte de la colonne choisie (au plus 20) avec un menu vers la valeur Tarmac correspondante ou « ignorer la ligne ». Si aucune colonne n'est choisie pour ce champ, un menu unique fixe la valeur pour tout le fichier (obligatoire pour le type de matériel).
- Bouton « Vérifier l'aperçu ». Validation : chaque champ obligatoire a une colonne, deux champs ne pointent pas la même colonne.

### 3.3 Écran 3 — l'aperçu et la confirmation

Tableau de toutes les lignes après rapprochement, avec pour chacune l'état : « sera importé » ou « ignoré » et la raison (champ obligatoire vide, valeur non rapprochée, doublon avec l'existant, doublon dans le fichier, e-mail invalide). Compteurs en tête. Boutons « Modifier le rapprochement » et « Importer N lignes ». À la confirmation : import ligne par ligne dans une transaction, suppression du fichier S3, effacement de l'état de session, flash « N matériels importés, M lignes ignorées », redirection vers la liste.

## 4. Définition « matériel »

| Champ | Obligatoire | Synonymes d'en-tête | Valeurs |
|---|---|---|---|
| `name` | oui | nom, appareil, désignation, immatriculation, immat, matériel, libellé | texte, 255 max |
| `type` | oui | type, catégorie, famille, genre | rapprochement de valeurs vers planeur / avion / infrastructure ; pré-affectation : planeur, glider, motoplaneur → planeur ; avion, ulm, remorqueur, airplane → avion ; treuil, remorque, hangar, véhicule, infra → infrastructure |
| `description` | non | description, remarque, commentaire, notes, modèle | texte |
| `owner` | non | propriétaire, appartenance | rapprochement de valeurs vers club / privé ; défaut club |

Le nom reste libre (une cuisine n'a pas d'immatriculation). Doublon : nom normalisé (ASCII, minuscules, espaces réduits) déjà présent parmi les matériels actifs du club, ou répété dans le fichier. `createdBy` = l'importateur, `active` = vrai.

## 5. Définition « invitations » (dernière étape)

| Champ | Obligatoire | Synonymes | Valeurs |
|---|---|---|---|
| `email` | oui | courriel, e-mail, email, mail, adresse | format e-mail |
| `firstname` | oui | prénom, prenom | texte |
| `lastname` | oui | nom, nom de famille | texte |
| `isManager` | non | gestionnaire, manager | rapprochement de valeurs vers oui / non |
| `isInspector` | non | qualifié, inspecteur, part 66 | oui / non |
| `isPilote` | non | pilote, propriétaire | oui / non |

Sur l'écran 3, en plus des colonnes, trois cases par ligne (gestionnaire, membre qualifié, pilote) pré-cochées selon le rapprochement et modifiables à la main. Ignorés : e-mail invalide, déjà membre, invitation déjà en attente, doublon dans le fichier. À la confirmation : création et envoi des invitations avec les rôles (`InvitationService`). L'ancien import Givav et son formulaire disparaissent ; les en-têtes Givav (Courriel, Prénom, Nom) sont couverts par les synonymes.

## 6. Points d'entrée et documentation

- Liste du matériel : bouton « Importer » à côté d'« Ajouter du matériel » ; liste des invitations : le bouton « Importer » existant mène au nouvel assistant.
- Checklist : les textes matériel et membres mentionnent déjà l'import.
- Guide : section « Les matériels » (import, rapprochement des colonnes, fichier sans titres), section « Membres & invitations » (import avec rôles et aperçu). Un fichier modèle CSV téléchargeable pour chacun.

## 7. Modèle de données

Aucune nouvelle table. L'état de l'assistant vit en session (`import.<club id>.<définition>`), le fichier sur S3.

## 8. Tests

- Unitaires : lecture (CSV `;` et `,`, XLSX, Windows-1252, fichier sans en-tête), détection d'en-tête (cas positifs et négatifs), pré-affectation par synonymes, rapprochement de valeurs, validation des lignes matériel et invitations (doublons, valeurs manquantes), avec des fixtures dans `tests/fixtures/imports/`.
- Fonctionnels : parcours complet matériel (écran 1 → 2 → 3 → import, matériels créés, fichier S3 supprimé via un filesystem en mémoire en test), changement de la case « titres », parcours invitations avec rôles et e-mails envoyés, accès refusé à un simple membre.

## 9. Dépendances

Aucune nouvelle : PhpSpreadsheet et Flysystem S3 sont déjà installés. En test, le filesystem `s3` est remplacé par un adaptateur mémoire (`league/flysystem-memory`, à installer en dev) via `when@test`.
