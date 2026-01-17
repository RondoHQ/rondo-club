---
created: 2026-01-17T15:24
title: Prevent duplicate email addresses across people
area: api
files:
  - includes/class-rest-api.php
  - src/pages/People/PersonEditModal.jsx
---

## Problem

Currently, when adding a new person with an email address that already belongs to another person in the system, the save succeeds. This can lead to data integrity issues:
- Same email associated with multiple person records
- Confusion when matching meetings to attendees
- Difficulty determining which person record is canonical

Expected behavior: When creating or editing a person, if the email address already exists on another person record, the save should fail with a clear error message.

## Solution

TBD - Options to consider:
1. Server-side validation in REST API that checks for existing email before save
2. Client-side pre-validation with warning/error UI
3. Both client and server validation (defense in depth)
4. Consider case-insensitivity (emails are stored lowercase per v3.6)
