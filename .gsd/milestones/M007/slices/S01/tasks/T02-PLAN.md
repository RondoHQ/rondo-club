---
estimated_steps: 8
estimated_files: 10
---

# T02: Clean developer docs, bump version, deploy

**Slice:** S01 — Remove iCal feed
**Milestone:** M007

## Description

Remove all iCal references from the developer documentation site (7 files), bump the version to 31.13.1, update the changelog, commit, push, deploy to production, and flush WordPress rewrite rules to clean up orphaned iCal rewrite rules.

## Steps

1. Delete `../developer/src/content/docs/integrations/ical-feed.md`
2. Update `../developer/src/content/docs/index.mdx` — change Integrations card description from "iCal feeds, contact import." to "Demo data pipeline." and change link from `/integrations/ical-feed/` to `/integrations/demo-data/`
3. Update `../developer/src/content/docs/architecture.md` — remove the `| \`IcalFeed\` | iCal calendar feed generation |` row from the Integrations table
4. Update `../developer/src/content/docs/architecture/php-autoloading.md` — remove all 4 iCal references:
   - Remove `| **iCal Feed** | iCal Feed (early return for performance) |` row from context table
   - Remove `iCal Feed` from Admin row and Frontend row in context table
   - Remove `- **Rondo\Calendar\ICalFeed** - Calendar feed generation (All requests for hook registration)` from Utility Classes
   - Remove `rondo_is_ical_request()` function from the code example
   - Remove `4. **iCal optimization** - Early return for feed requests` from Performance Benefits
5. Update `../developer/src/content/docs/architecture/relationship-system.md` — remove `| [iCal Feed](./ical-feed.md) | Calendar subscription system |` row from Related Documentation table
6. Update `../developer/src/content/docs/features/reminders.md` — remove `- [iCal Feed](./ical-feed.md) - Calendar subscription` from Related Documentation
7. Update `../developer/astro.config.mjs` — replace iCal Feed sidebar entry with Demo Data: change `{ label: 'iCal Feed', slug: 'integrations/ical-feed' }` to `{ label: 'Demo Data', slug: 'integrations/demo-data' }`
8. Bump version to 31.13.1 in `style.css` and `package.json`, add changelog entry, commit both repos, deploy to production via `bin/deploy.sh`, flush rewrite rules via WP-CLI SSH

## Must-Haves

- [ ] `integrations/ical-feed.md` deleted
- [ ] `index.mdx` Integrations card updated with valid link and description
- [ ] `architecture.md` IcalFeed row removed
- [ ] `php-autoloading.md` all 4 iCal references removed
- [ ] `relationship-system.md` iCal link removed
- [ ] `reminders.md` iCal link removed
- [ ] `astro.config.mjs` sidebar entry replaced
- [ ] Version bumped to 31.13.1
- [ ] Changelog updated
- [ ] Deployed to production
- [ ] Rewrite rules flushed

## Verification

- `grep -rn 'ical-feed\|IcalFeed\|iCal Feed\|iCal feed\|prm-ical\|rondo_is_ical_request' ../developer/src/content/docs/ ../developer/astro.config.mjs` — zero results
- `test ! -f ../developer/src/content/docs/integrations/ical-feed.md` — file deleted
- `npm run build` — exit code 0 (verify build still passes after version bump)
- Production site accessible and loads without errors

## Observability Impact

- Signals added/changed: None — documentation and deployment only
- How a future agent inspects this: check version in style.css, verify deploy via SSH
- Failure state exposed: None

## Inputs

- T01 completed — all source code iCal references already removed
- `../developer/src/content/docs/integrations/ical-feed.md` — file to delete
- 5 doc files with iCal references — identified in research
- `../developer/astro.config.mjs` — sidebar config
- `.env` — deploy credentials

## Expected Output

- `../developer/src/content/docs/integrations/ical-feed.md` — deleted
- `../developer/src/content/docs/index.mdx` — Integrations card points to demo-data
- `../developer/src/content/docs/architecture.md` — IcalFeed row removed
- `../developer/src/content/docs/architecture/php-autoloading.md` — clean, no iCal mentions
- `../developer/src/content/docs/architecture/relationship-system.md` — iCal row removed
- `../developer/src/content/docs/features/reminders.md` — iCal link removed
- `../developer/astro.config.mjs` — sidebar points to demo-data
- `style.css` — version 31.13.1
- `package.json` — version 31.13.1
- `CHANGELOG.md` — new entry for 31.13.1
- Production deployed with flushed rewrite rules
