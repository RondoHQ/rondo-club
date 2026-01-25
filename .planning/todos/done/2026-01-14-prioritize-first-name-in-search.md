---
created: 2026-01-14T20:12
title: Prioritize first name in search
area: api
files:
  - includes/class-rest-api.php
---

## Problem

When searching for people, the current search may not prioritize first name matches appropriately. Users typically search by first name, so results matching the first name should rank higher than matches on other fields (last name, notes, etc.).

## Solution

TBD - investigate current search implementation in `STADION_REST_API` and adjust relevance scoring to weight first name matches more heavily.
