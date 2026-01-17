---
created: 2026-01-15T13:57
title: Add wp-config.php constants installation documentation
area: docs
files:
  - docs/
  - wp-config.php
---

## Problem

Users need documentation explaining which constants should be set in `wp-config.php` to enable various integrations and connections. Currently there's no centralized guide explaining:

1. What constants are available
2. What each constant does
3. Where to get the values (e.g., Google Cloud Console, Slack API, etc.)
4. Which are required vs optional

Known constants to document:
- Google Calendar OAuth (client ID, secret, redirect URI)
- CalDAV connections
- Slack webhook/API credentials
- Email/SMTP settings
- Any other integration-specific constants

## Solution

Create an installation guide in `docs/` (e.g., `docs/INSTALLATION.md` or `docs/CONFIGURATION.md`) that:
1. Lists all wp-config.php constants
2. Groups them by feature/integration
3. Provides step-by-step instructions for obtaining credentials
4. Includes example values/format
5. Notes which are required for which features
