---
created: 2026-02-20T07:26:10.896Z
title: Drop the Gravatar integration
area: api
files:
  - includes/class-rest-people.php
  - src/api/client.js
  - src/hooks/usePeople.js
  - src/components/PersonEditModal.jsx
---

## Problem

The Gravatar integration is an unnecessary external dependency. It fetches profile pictures from gravatar.com based on email hash. This is rarely useful for a sports club context where members typically don't have Gravatar accounts. It adds an external service call and UI surface area for minimal value.

## Solution

Remove the Gravatar endpoint from `class-rest-people.php`, the API client call in `client.js`, the hook usage in `usePeople.js`, and any UI trigger in `PersonEditModal.jsx`. Clean removal of all 4 files' Gravatar-related code.
