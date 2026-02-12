---
created: 2026-02-12T10:37:02.357Z
title: Rework contributie overzicht to store discount tier per person
area: api
files:
  - src/pages/Contributie/
  - includes/class-rest-api.php
---

## Problem

The current contributie overzicht does a full calculation per person on every load. This is complex and potentially slow. The family discount "group" (which determines discount tier) only changes when a person or one of their family members / people at the same address changes.

Currently the system recalculates everything each time, when the discount tier could be stored as metadata on each person and only recalculated when relevant data changes (family relationships, address changes).

## Solution

Store per person whether they get a family discount and which tier (1st or 2nd):
- Add a meta field per person for discount tier (none / 1st / 2nd)
- Recalculate discount groups only when a person or their family/address-mates change
- The contributie overzicht then just reads the stored tier and calculates fees on the fly using the fee category config
- This simplifies the overzicht page logic and potentially improves performance

Trigger recalculation on:
- Person address change
- Person relationship change
- New person added to same address
- Family member added/removed
