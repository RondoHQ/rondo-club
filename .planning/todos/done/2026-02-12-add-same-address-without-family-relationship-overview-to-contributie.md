---
created: 2026-02-12T10:44:50.481Z
title: Add same-address without family relationship overview to contributie
area: ui
files:
  - src/pages/Contributie/
  - includes/class-rest-api.php
---

## Problem

People living at the same address may qualify for family discount, but if they don't have family relationships recorded in the system, they won't be grouped together for discount calculations. There's currently no easy way to spot these cases.

Club admins need a way to identify people who share an address but aren't linked as family, so they can add the missing relationships and ensure correct discount application.

## Solution

Add a new tab under the Contributie page that shows an overview of people who:
1. Live at the same address (matching street + postal code + city)
2. Do NOT have family relationships listed between them in the system

This acts as a data quality tool — surfacing missing family links that affect fee calculations. The tab should group people by address and highlight which ones lack relationship records.
