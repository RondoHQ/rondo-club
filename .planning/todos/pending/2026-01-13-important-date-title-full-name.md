---
created: 2026-01-13T20:56
title: Important date title should use full name
area: api
files:
  - includes/class-auto-title.php
---

## Problem

When creating an important date like a birthday, the auto-generated title uses only the first name (e.g., "Jan's birthday"). It should use both first and last name for clarity (e.g., "Jan Ippen's birthday").

This becomes important when you have multiple people with the same first name.

## Solution

TBD - Update the auto-title generation logic in class-auto-title.php to include last name when generating important date titles.
