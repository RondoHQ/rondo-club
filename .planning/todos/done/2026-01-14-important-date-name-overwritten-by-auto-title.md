---
created: 2026-01-14T20:20
title: Important date name overwritten by auto-title
area: api
files:
  - includes/class-auto-title.php
  - src/components/ImportantDateModal.jsx
---

## Problem

When editing an important date in the edit modal and changing the name/title, the change gets immediately overwritten by the auto-generated title. This prevents users from customizing the display name of important dates.

## Solution

TBD - investigate how `STADION_Auto_Title` interacts with the edit modal save. Options:
1. Disable auto-title generation when a custom title is explicitly set
2. Add a flag to indicate user has overridden the title
3. Only apply auto-title on initial creation, not on updates
