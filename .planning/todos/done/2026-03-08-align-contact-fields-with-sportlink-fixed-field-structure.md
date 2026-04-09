---
created: 2026-03-08T10:07:28.058Z
title: Align contact fields with Sportlink fixed field structure
area: general
files:
  - rondo-club/ (people data model, REST API, UI)
  - rondo-sync/ (individual sync, reverse sync, people sync)
---

## Problem

Rondo Club currently supports unlimited email and phone number fields per person, while Sportlink has a fixed set of fields: Email1, Email2, Mobile1, Mobile2, Telephone1, Telephone2. This mismatch makes reliable reverse sync (Rondo → Sportlink) impossible because there's no way to map arbitrary Rondo fields back to specific Sportlink fields.

## Solution

Replace the unlimited email/phone fields in Rondo Club with the same fixed structure as Sportlink:

- Email1, Email2
- Mobile1, Mobile2
- Telephone1, Telephone2

### Migration
- All currently stored email addresses → Email1 (any second emails → Email2)
- All currently stored mobile numbers → Mobile1 (any second mobiles → Mobile2)
- All currently stored telephone numbers → Telephone1 (any second telephones → Telephone2)

### Scope (both repos)

**rondo-club:**
- Data model: replace flexible email/phone arrays with fixed fields
- REST API: update person endpoints to use new field structure
- UI: update person detail/edit forms to show fixed fields

**rondo-sync:**
- Individual sync: map Sportlink fields 1:1 to Rondo fields
- Reverse sync: map Rondo fields 1:1 back to Sportlink fields
- People sync: update bulk sync to use new field mapping

This 1:1 mapping eliminates ambiguity and makes bidirectional sync reliable.
