---
created: 2026-01-28T20:16
title: Add Sportlink link for persons with KNVB ID
area: ui
files:
  - src/pages/PersonDetail.jsx
  - src/components/PersonHeader.jsx
---

## Problem

Persons who have a KNVB ID should have a quick link to view their profile in Sportlink Club. Currently there's no way to jump directly to their Sportlink page from the Person detail view.

## Solution

1. Download Sportlink favicon (https://club.sportlink.com/assets/favicon/apple-icon-60x60.png) into the codebase (e.g., `public/images/sportlink-icon.png` or similar)
2. If person has KNVB ID field populated, show an icon link in the Person header
3. Link format: Determine Sportlink Club URL pattern using KNVB ID
4. Icon-only link (no label text), with title="View in Sportlink Club"
