---
created: 2026-01-13T20:56
title: WP CLI command to regenerate birthday names
area: tooling
files:
  - includes/class-auto-title.php
---

## Problem

After fixing the important date title format to use full names, existing birthdays will still have the old format ("Jan's birthday" instead of "Jan Ippen's birthday"). Need a WP CLI command to regenerate all birthday titles with the new format.

## Solution

TBD - Create a WP CLI command that:
1. Queries all important_date posts of type "birthday"
2. For each, regenerates the title using the linked person's full name
3. Updates the post title
