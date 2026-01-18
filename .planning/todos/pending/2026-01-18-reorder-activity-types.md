---
created: 2026-01-18T02:18
title: Reorder activity types and rename Zoom to Video
area: ui
files:
  - src/components/QuickActivityModal.jsx
---

## Problem

The activity types in the add activity modal are not in the most logical order for typical use. Also, "Zoom" is too specific - should be generic "Video" to cover all video call platforms.

## Solution

Reorder activity types to: Email, Chat, Phone, Video (was Zoom), Meeting, Coffee, Lunch, Dinner, Other

This puts digital communication first (most common), then in-person activities, with Other as fallback at the end.
