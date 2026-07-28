# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Projet

Tarmac est une webapp Symfony 7.4 (PHP ≥ 8.5) **mobile-first** de gestion de clubs aéronautiques. UI Tabler.io + Stimulus, DB PostgreSQL en prod (MySQL configuré en `.env` par défaut — vérifier `.env.local`), traductions FR uniquement au format **XLF ICU**.

## Commandes courantes

- Démarrer le serveur de dev : `bin/server` (lance le proxy Symfony, attache `*.tarmac.wip`, configure Xdebug, ouvre `https://www.tarmac.wip`). **Ne pas** utiliser `php bin/console server:start`, et ne pas tenter de redémarrer le serveur manuellement — c'est souvent sans effet.
- Vider le cache : `symfony console cache:clear` (préférer à `bin/console cache:clear`).
- Tests : `bin/phpunit` ou `vendor/bin/phpunit`. Un seul test : `vendor/bin/phpunit --filter NomDuTest tests/Chemin/FichierTest.php`. Config dans `phpunit.dist.xml`, bootstrap `tests/bootstrap.php`, `APP_ENV=test`. `failOnDeprecation/Notice/Warning` est activé.
- Migrations Doctrine : `symfony console doctrine:migrations:migrate`. Helper local : `bin/database`.
- Traductions : les nouvelles clés s'ajoutent **à la main** dans `translations/messages+intl-icu.fr.xlf`. Pour lister celles qui manquent : `symfony console debug:translation fr --domain=messages --only-missing`. **Ne jamais lancer `translation:extract`** (voir la section Traductions ci-dessous).
- Compilation des assets (prod) : `composer assets:compile` (= `php bin/console asset-map:compile`).
- Déploiement : `bin/release` (release Heroku — voir `Procfile` / `app.json`).

## URL locales

- Site public / admin : `https://www.tarmac.wip`
- App d'un club : `https://<subdomain>.tarmac.wip` (ex. `https://demo.tarmac.wip`)
- Toujours passer par le proxy Symfony (CURL inclus). Si Chrome renvoie `UNRESOLVED`, l'utilisateur doit faire « Reapply-settings » sur `chrome://net-internals/proxyservice.config#proxy`.

## Architecture

### Multi-tenant par sous-domaine
Le tenant (club) est résolu à partir du sous-domaine HTTP par `App\Service\SubdomainService` + `App\Service\ClubResolver` (cache dans `$request->attributes['_club']`). Trois espaces de routage / contrôleurs, organisés par sous-domaine :

- `src/Controller/Public/` — pages publiques (login, reset password, homepage) sur `www`.
- `src/Controller/Admin/` — super-admin (gestion des clubs et utilisateurs globaux) sur `www`.
- `src/Controller/App/` — espace utilisateur cross-club sur `www` (dashboard global).
- `src/Controller/Club/` — toute l'application métier d'un club, servie sur `<subdomain>.tarmac.wip` (équipements, plans de maintenance, tâches, sous-tâches, contributions, membres, invitations, achats, logbook, spécialisations).

Tous les contrôleurs héritent de `App\Controller\ExtendedController`, qui surcharge `redirectToRoute()` pour **injecter automatiquement le paramètre `subdomain`** sur les routes préfixées `club_`. Ne pas étendre `AbstractController` directement dans cette app.

### Modèle de domaine (src/Entity)
Cœur : `Club` ←→ `Membership` ←→ `User`. Un `Membership` porte les rôles club (Member / Manager / Inspector — voir `Entity/Enum/`). Maintenance : `Equipment` → `Task` → `SubTask` → `Contribution` (saisies de temps par membre). Plans réutilisables : `Plan` → `PlanTask` → `PlanSubTask`, instanciés sur un équipement via `PlanApplication`. Autres : `Activity` (logbook), `Invitation`, `Purchase`, `Specialisation`, `ResetPasswordRequest`.

### Sécurité
- Authentification : `App\Security\FormLoginAuthenticator` + `UserChecker`.
- Autorisation : Voters dans `src/Security/Voter/` (`ClubVoter`, `TaskVoter`, `SubTaskVoter`, `PurchaseVoter`). Toute vérification d'accès club/tâche **doit** passer par un Voter — ne pas réimplémenter de check ad-hoc dans un contrôleur.

### Services notables
- `Service/Maintenance/` — logique plans de maintenance / application aux équipements.
- `Service/Logging/` — incluant `WebRequestLogProcessor` enrichissant les logs Monolog.
- `Service/PlanSpreadsheetService.php` — import XLSX de plans (PhpSpreadsheet).
- `Service/InvitationService.php` + `InvitationImportService.php` — invitations membres, import en masse.

### Frontend
- `assets/app.js`, `assets/bootstrap.js`, `assets/controllers/` (Stimulus, déclarés dans `controllers.json`), `assets/styles/app.css` (extension de Tabler).
- Servi via Symfony AssetMapper + ImportMap (`importmap.php`) — **pas de build Node**. En dev, `bin/server` supprime `public/assets` à chaque lancement pour éviter le cache figé.

## Conventions imposées

### Traductions (FR uniquement, XLF ICU)
- **Toujours** des clés `lowerCamelCase` (`{{ 'welcomeUser'|trans }}`), jamais de chaîne en dur côté utilisateur.
- Catalogue **unique** : `translations/messages+intl-icu.fr.xlf`. **Ne jamais créer de YAML de traduction** (`.yaml`/`.yml`) — ils seront supprimés.
- ICU pour variables/pluriels : `{count, plural, one {# item} other {# items}}`.
- ⛔ **Ne jamais lancer `symfony console translation:extract`.** `php-translation/symfony-bundle` a surchargé la commande (les options `--force --format=xlf` n'existent plus) et sa configuration `app` est en `output_format: yaml` : elle génère `translations/messages+intl-icu.fr.yaml` + `validators+intl-icu.fr.yaml`, soit un doublon complet du catalogue au format interdit, sans toucher au XLF. Si ça arrive, supprimer les `.yaml` produits.
- Ajouter les clés **manuellement** en fin de `<body>`, avec le prochain `id` numérique libre, groupées sous un commentaire XML. Vérifier ensuite avec `symfony console lint:xliff translations/`.
- `debug:translation` liste les clés manquantes, mais son état `unused` n'est **pas fiable** : les clés utilisées uniquement depuis du PHP (messages flash) ou depuis les options `label`/`help` d'un formulaire ne sont pas détectées. Ne jamais supprimer une clé sur cette seule base.
- Clés dynamiques (`(equipment.type.value ~ 'Type')|trans`) : énumérer tous les cas possibles et les ajouter manuellement (aucun outil ne les voit).
- Glossaire : Club, Équipement, Planeur (Glider), Membre, Gestionnaire (Manager), Qualifié (Inspector). Ne jamais traduire « Tarmac ».

### Templates Twig
- Dossiers et fichiers en **camelCase** : `templates/club/equipment/newEquipment.html.twig`. Pas de `snake_case` ni `kebab-case`.
- Pour les listes filtrables, **toujours** inclure `templates/component/filters.html.twig` (le formulaire de filtre doit être en GET, sans CSRF, tous champs `required => false`). Ne pas écrire de filtre custom dans un template.
- Mobile-first : démarrer à 320px, enrichir à `@media (min-width: 768px)`. Cibles tactiles ≥ 44×44 px. Privilégier composants Tabler.

### Breadcrumbs (contrôleurs Club / Admin)
- Pas de breadcrumb sur Dashboard (L1) ni sur les pages d'index (L2). Obligatoire à partir de L3 (`show`, `edit`, `new`).
- Toujours démarrer par `['label' => 'home', 'route' => 'club_dashboard' | 'app_dashboard']` (le label spécial `home` rend l'icône `ti-home`).
- Une page `edit` référence la liste **et** la page `show` du parent. Voir `.cursor/rules/breadcrumbs.mdc` pour le détail.

### Doctrine — aliases de QueryBuilder
Toujours utiliser des aliases **descriptifs et complets** : `createQueryBuilder('contribution')`, `->join('contribution.subTask', 'subtask')`. Pas de `c`, `t`, `m`, `eq1`. Pour plusieurs jointures sur la même entité, suffixer le contexte : `equipment_glider`, `subtask_awaiting_inspection`.

### Guide utilisateur — règle d'entretien
La documentation utilisateur vit dans `templates/public/guide/index.html.twig` (route `public_guide`, accessible depuis le dropdown utilisateur). **À chaque modification fonctionnelle** de l'app — ajout / suppression / renommage de fonctionnalité, changement de workflow (tâches, achats, plans, invitations…), modification d'un rôle ou d'une permission, changement de navigation, renommage de vocabulaire métier — **mettre à jour le guide en cohérence dans le même commit**. Si une section devient obsolète, la corriger ou la supprimer ; ne jamais laisser le guide décrire un comportement qui n'existe plus. Le ton reste didactique, accessible à un membre non-technique, écrit du point de vue d'un président de club qui explique à ses membres. Respecter le glossaire (Matériel, Membre, Gestionnaire, Qualifié, Pilote…).

## Notes

- `.cursor/rules/*.mdc` contiennent les règles complètes et font autorité — ce CLAUDE.md en est un condensé.
- Documents `PERMISSIONS_*.md` et `CHANGES_SUMMARY.md` à la racine documentent l'analyse historique des permissions ; les Voters dans `src/Security/Voter/` sont la source de vérité courante.
