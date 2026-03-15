---
name: headway
description: >-
  Returns the content of the project's Headway changelog post(s) as markdown.
  Use when the user types /headway, or asks for the headway post, headwayapp
  announcement, or changelog text to publish in Headway.
---

# Headway post

## When to apply

Apply this skill when the user:
- Types **/headway**
- Asks for the "post headway", "annonce headway", or "texte pour headwayapp"
- Wants the markdown content to copy into Headway for a release announcement

## What to do

1. **Ne crée ni ne modifie jamais de fichier dans le dépôt Git** pour le contenu Headway (pas de nouveaux fichiers dans `headway/`, pas de modifications de fichiers existants).
2. Si des fichiers markdown existent déjà dans le répertoire **headway/** du projet (relatif à la racine du workspace), lis-les uniquement en lecture et renvoie leur contenu.
3. Si tu dois **rédiger un nouveau texte Headway**, travaille intégralement en mémoire : génère ou modifie le markdown directement dans ta réponse, sans l’enregistrer dans le repo.
4. Si tu as absolument besoin d’un fichier temporaire pour t’organiser, utilise un emplacement temporaire hors dépôt (par exemple un chemin système type `/tmp/…`), et considère-le comme éphémère.
5. Renvoie toujours le markdown complet dans ta réponse (bloc de code markdown ou message principal) afin que l’utilisateur puisse le copier-coller dans Headway.

### Ton style d’écriture pour Headway

- **Public visé** : grand public / membres de club, pas des développeurs.
- **Ton** : clair, positif, orienté bénéfices (ce que ça change pour l’utilisateur), pas de jargon technique.
- **Niveau de détail** :
  - Ne pas décrire les détails d’implémentation (pas de DQL, entités, PDF engine, etc.).
  - Expliquer la fonctionnalité en termes simples : *ce qu’on peut faire*, *pourquoi c’est utile*, *dans quels cas on l’utilise*.
- **Structure recommandée** :
  - Un titre clair et court.
  - 2–4 sections maximum, avec des sous-titres simples (ex. “Ce qui change pour vous”, “Comment l’utiliser”).
  - Des listes à puces pour les points importants.
- **Évite** :
  - Les termes trop techniques (ORM, repository, Twig, etc.).
  - Les messages trop longs : privilégie un texte que l’on lit en **30–60 secondes**.

Si plusieurs fichiers existent dans `headway/`, renvoie le contenu du plus récent ou du plus pertinent, sauf si l’utilisateur demande un fichier précis.

Ne résume ni ne réécris le contenu sauf si l’utilisateur demande explicitement des modifications ; renvoie-le tel quel pour un simple copier-coller.

