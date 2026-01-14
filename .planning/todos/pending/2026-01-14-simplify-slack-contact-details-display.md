---
created: 2026-01-14T20:16
title: Simplify Slack contact details display
area: ui
files:
  - src/components/ContactDetails.jsx
---

## Problem

In the Contact details card, Slack type contact details currently display more information than necessary. The display should be cleaner and more streamlined.

## Solution

For Slack type contact details, only display the label text and make it a clickable link to the Slack URL. Hide or remove any redundant information that's currently shown.
