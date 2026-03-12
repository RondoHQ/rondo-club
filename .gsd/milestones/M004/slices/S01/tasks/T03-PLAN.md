---
estimated_steps: 5
estimated_files: 5
---

# T03: Version bump, changelog, docs, deploy, and verify

**Slice:** S01 — Confirmation, Refresh & Email Notification
**Milestone:** M004

## Description

Finalize the milestone by bumping the version to 31.11.0, updating the changelog, updating developer documentation, committing all changes, deploying to production, and verifying the three behaviors end-to-end on the live site.

## Steps

1. **Version bump:**
   - Update `style.css` Version from `31.10.0` to `31.11.0`.
   - Update `package.json` version from `31.10.0` to `31.11.0`.

2. **Changelog:**
   - Add `## [31.11.0]` entry at top of CHANGELOG.md with date.
   - Under `### Added`:
     - Confirmation dialog before toggling contributie exclusion/inclusion
     - Immediate FinancesCard refresh after exclusion toggle (no page reload)
     - Email notification to Secretaris and Penningmeester on exclusion toggle
   - Under `### Changed`:
     - Extracted `RoleFinder` helper from `LettermintWebhook` for reusable role-based user lookup

3. **Developer docs:**
   - Update `../developer/src/content/docs/features/membership-fees.md` to document the exclusion notification behavior: who receives email, what it contains, fallback to administrators.

4. **Git commit, push, and deploy:**
   - `git add -A && git commit -m "feat: add confirmation, refresh & email for contributie exclusion (M004)"` 
   - `git push`
   - Run `bin/deploy.sh` to deploy to production.

5. **Verify on production:**
   - Toggle "Uitsluiten van contributie" on a test person → confirm dialog appears.
   - After confirm, FinancesCard updates immediately to show "Uitgesloten van contributie" state.
   - Click "Opnemen" → confirm dialog appears → card refreshes back to fee display.
   - Check email inbox (Secretaris/Penningmeester) for notification emails with correct subject, body, person link, actor name, and timestamp.

## Must-Haves

- [ ] Version is 31.11.0 in both `style.css` and `package.json`
- [ ] CHANGELOG.md has [31.11.0] entry with correct Added/Changed sections
- [ ] Developer docs updated with exclusion notification documentation
- [ ] All changes committed and pushed
- [ ] Deployed to production via `bin/deploy.sh`
- [ ] Confirmation dialog verified on production
- [ ] Immediate refresh verified on production
- [ ] Email notification verified on production

## Verification

- Production site shows version 31.11.0
- Toggle exclusion on production → full end-to-end flow works (confirm → refresh → email)
- CHANGELOG.md contains the [31.11.0] entry

## Observability Impact

- Signals added/changed: None (deployment only)
- How a future agent inspects this: Check version in Settings page or `style.css` on server
- Failure state exposed: None

## Inputs

- All changes from T01 (RoleFinder + LettermintWebhook refactor) and T02 (FinancesCard + FeeCacheInvalidator email)
- `bin/deploy.sh` — deployment script
- `.env` — production deployment credentials

## Expected Output

- `style.css` — version bumped to 31.11.0
- `package.json` — version bumped to 31.11.0
- `CHANGELOG.md` — new [31.11.0] entry
- `../developer/src/content/docs/features/membership-fees.md` — updated with exclusion notification docs
- Production deployment live and verified
