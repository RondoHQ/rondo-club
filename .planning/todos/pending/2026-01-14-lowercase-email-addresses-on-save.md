---
created: 2026-01-14T20:24
title: Lowercase email addresses on save
area: api
files:
  - includes/class-post-types.php
---

## Problem

Email addresses are stored as entered by the user, which can lead to inconsistent data (e.g., "John@Example.com" vs "john@example.com"). Email addresses are case-insensitive by RFC specification, so they should be normalized.

## Solution

Convert all email addresses to lowercase on save. This should be done in the backend when saving person/contact data to ensure consistency regardless of how the user enters the email.
