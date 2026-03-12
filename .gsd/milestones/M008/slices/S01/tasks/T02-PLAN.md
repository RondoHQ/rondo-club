---
estimated_steps: 4
estimated_files: 4
---

# T02: Deploy to production and verify

**Slice:** S01 — Credit badge and filter on Facturen list
**Milestone:** M008

## Description

Bump the version, update the changelog, commit, deploy to production, and visually verify the credit badge and filter on the live Facturen page.

## Steps

1. Read current version from `package.json` and `style.css`, bump patch version in both files
2. Add changelog entry to `CHANGELOG.md` under a new version heading: "Added" section with credit badge and filter description
3. Commit all changes with message `feat: add Credit badge and filter to Facturen list`
4. Run `bin/deploy.sh` to deploy to production (includes build, rsync, and cache clear)

## Must-Haves

- [ ] Version bumped in both `style.css` and `package.json`
- [ ] Changelog entry added in Keep a Changelog format
- [ ] Changes committed and pushed
- [ ] `bin/deploy.sh` exits 0
- [ ] Production site accessible at https://rondo.svawc.nl/financien/facturen

## Verification

- `bin/deploy.sh` completes without errors
- `git log --oneline -1` shows the feat commit
- Production Facturen page loads and (for human verification) credit invoices show rose "Credit" badge

## Observability Impact

- Signals added/changed: None
- How a future agent inspects this: Check `git log` for commit, check `style.css` version on production via SSH
- Failure state exposed: None

## Inputs

- `src/pages/Finance/Facturen.jsx` — modified in T01 with credit badge and filter changes
- `bin/deploy.sh` — existing deployment script
- `.env` — production deployment credentials

## Expected Output

- `style.css` — version bumped
- `package.json` — version bumped
- `CHANGELOG.md` — new version entry with credit badge description
- Production deployment complete with cache cleared
