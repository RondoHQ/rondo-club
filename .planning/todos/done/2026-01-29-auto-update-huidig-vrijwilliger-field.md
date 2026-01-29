---
created: 2026-01-29T23:49
title: Auto-update huidig-vrijwilliger field based on active roles
area: api
files:
  - includes/class-rest-api.php
  - includes/class-post-types.php
---

## Problem

The custom field `huidig-vrijwilliger` (current volunteer) needs to be automatically maintained based on whether a person currently holds an active position:

1. **Committee member** - active role in a commissie
2. **Staff member** - active (non-player) role in a team

Currently this field must be manually set, but it should be derived from the person's actual roles.

## Solution

When a position (work history entry) is added, updated, or removed for a person:

1. Check if the person has any **current** (no end date or end date in future) positions where:
   - Position is in a commissie (any role), OR
   - Position is in a team with a staff role (not a player role like Aanvaller, Keeper, etc.)

2. If yes → set `huidig-vrijwilliger` = true
3. If no → set `huidig-vrijwilliger` = false

Implementation options:
- Hook into `acf/save_post` for person post type
- Check work_history repeater field changes
- Re-evaluate volunteer status on each save
