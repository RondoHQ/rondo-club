---
created: 2026-01-14T20:13
title: Todo changes should invalidate dashboard cache
area: api
files:
  - includes/class-rest-api.php
---

## Problem

When a todo is added or updated, the dashboard cache is not invalidated. This means the dashboard may show stale todo data until the cache expires naturally.

## Solution

TBD - add cache invalidation hooks when todo posts are created, updated, or deleted. Likely needs to hook into `save_post_stadion_todo` or similar and clear the relevant dashboard transients.
