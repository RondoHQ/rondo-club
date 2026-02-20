---
created: 2026-02-20T07:26:10.896Z
title: Drop Google Contacts and Calendar sync, add CSV export
area: general
files:
  - includes/class-google-contacts-sync.php
  - includes/class-google-contacts-export.php
  - includes/class-google-contacts-api-import.php
  - includes/class-google-contacts-connection.php
  - includes/class-rest-google-contacts.php
  - includes/class-google-calendar-provider.php
  - includes/class-calendar-sync.php
  - includes/class-calendar-connections.php
  - includes/class-rest-calendar.php
  - includes/class-google-oauth.php
  - includes/class-rest-google-sheets.php
  - includes/class-google-sheets-connection.php
  - src/api/client.js
  - src/pages/Settings/Settings.jsx
  - src/pages/People/PeopleList.jsx
  - src/pages/People/PersonDetail.jsx
  - functions.php
---

## Problem

Google Contacts sync and Google Calendar sync are complex integrations with significant code surface area (~10 PHP classes, OAuth flows, REST endpoints, frontend UI). They add maintenance burden and external dependencies (Google APIs, OAuth tokens) for features that see limited use. The Google Sheets export is useful but should be complemented with a simpler CSV export option that doesn't require Google authentication.

## Solution

1. **Remove Google Contacts sync:** Delete ~5 PHP classes (google-contacts-*.php), their REST endpoints, OAuth callbacks, Settings UI sections, and client.js API calls
2. **Remove Google Calendar sync:** Delete calendar-sync, calendar-connections, google-calendar-provider PHP classes, their REST endpoints, and frontend UI
3. **Simplify Google OAuth:** May be able to reduce google-oauth.php to only serve Google Sheets if that stays, or remove entirely if Sheets also uses a simpler auth
4. **Add CSV export:** Add a CSV download option alongside the existing Google Sheets export on PeopleList, VOGList, and ContributieList pages. CSV export needs no authentication — just generate and download
5. **Clean up functions.php:** Remove class loading for deleted classes
