---
created: 2026-03-08T10:10:00.000Z
title: Normalize phone numbers to E164 format
area: general
files:
  - rondo-club/ (people data model, phone storage, tel: links)
  - rondo-sync/ (phone number sync)
---

## Problem

Phone numbers are stored inconsistently — with/without spaces, dashes, and using local "0" prefix instead of international "+31". This causes duplicate detection issues (same number stored in different formats appears as two different numbers) and inconsistent tel: links.

## Solution

Normalize all phone numbers on save:
1. Strip spaces and dashes
2. Replace leading "06" / "0" with "+316" / "+31"
3. Store in normalized format (e.g. `+31612345678`)
4. Use normalized format in `tel:` links

### Migration
- Run a one-time migration on all existing phone numbers to normalize them
- Deduplicate any phone numbers that become identical after normalization

### Note
Best implemented after or together with the Sportlink field alignment todo, since both touch phone number storage.
