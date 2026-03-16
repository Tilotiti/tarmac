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

1. **Ne crée ni ne modifie jamais de fichier dans le dépôt Git** pour le contenu Headway.
2. **Ne cherche jamais** le dossier `headway/` dans le projet.
3. **Rédige le texte Headway** en fonction du contexte fourni par l’utilisateur (fonctionnalité, version, etc.).
4. **Réponds uniquement dans le chat** : tout le contenu est livré directement dans la conversation.
5. **Livré systématiquement en Markdown** : le texte est fourni au format Markdown (bloc de code ou texte formaté) pour un copier-coller dans Headway.

### Ton style d'écriture pour Headway

- **Public visé** : grand public / membres de club, pas des développeurs.
- **Ton** : clair, positif, orienté bénéfices (ce que ça change pour l’utilisateur), pas de jargon technique.
- **Niveau de détail** :
  - Ne pas décrire les détails d’implémentation (pas de DQL, entités, PDF engine, etc.).
  - Expliquer la fonctionnalité en termes simples : *ce qu’on peut faire*, *pourquoi c’est utile*, *dans quels cas on l’utilise*.
- **Structure recommandée** :
  - Un titre clair et court.
  - 2–4 sections maximum, avec des sous-titres simples (ex. "Ce qui change pour vous", "Comment l’utiliser").
  - Des listes à puces pour les points importants.
- **Évite** :
  - Les termes trop techniques (ORM, repository, Twig, etc.).
  - Les messages trop longs : privilégie un texte que l’on lit en **30–60 secondes**.
