---
created: 2026-01-18T10:45
title: Move deploy config to .env and create deploy script
area: tooling
files:
  - AGENTS.md
  - .env.example
---

## Problem

The AGENTS.md file contains hardcoded server credentials and deployment commands:
- SSH host: `c1130624.sgvps.net`
- SSH port: `18765`
- SSH user: `u25-eninwxjgiulh`
- WordPress path: `~/www/cael.is/public_html/`
- Production URL: `https://cael.is/`

These should be in a `.env` file for:
1. Security (credentials not in version-controlled docs)
2. Flexibility (different devs/environments)
3. Cleaner AGENTS.md (instructions without inline credentials)

## Solution

1. Move server config to `.env` file (and update `.env.example`)
2. Create `/bin/deploy.sh` script that:
   - Reads from `.env`
   - Handles both node_modules and non-node_modules deploys
   - Runs cache flush commands
3. Update AGENTS.md Rule 8 to reference the script instead of inline rsync commands
