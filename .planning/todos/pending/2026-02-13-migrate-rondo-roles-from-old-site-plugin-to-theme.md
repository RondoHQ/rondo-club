---
created: 2026-02-13T08:56:41.738Z
title: Migrate Rondo roles from old site plugin to theme
area: auth
files: []
---

## Problem

The old site (stadion.svawc.nl) has custom WordPress roles ("Rondo" roles) that were created using a plugin. These roles need to be defined natively within the Rondo Club theme itself, rather than depending on an external plugin on the old site.

Currently, Rondo Club only registers a single "Rondo User" role (in `class-user-roles.php`). The old site has additional roles that should be brought over and registered directly by the theme.

## Solution

1. SSH into the old site (stadion.svawc.nl) and inspect the roles created by the plugin — identify role slugs, display names, and capabilities
2. Add those role definitions to `includes/class-user-roles.php` so they're registered on theme activation
3. Ensure role capabilities align with the current access control model
4. Consider whether this overlaps with the "club-admin" role todo (separate pending todo)
