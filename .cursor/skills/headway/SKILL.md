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

1. Read the markdown file(s) in the project's **headway/** directory (relative to workspace root).
2. Output the full content of the file(s) in your reply, in a markdown block or as the main message, so the user can copy it directly into Headway.

If there are multiple files in `headway/`, output the content of the most recent or relevant one unless the user specifies a file. If there is only one file, output that file's content.

Do not summarize or rewrite the content unless the user asks for changes; return it as-is for copy-paste.
