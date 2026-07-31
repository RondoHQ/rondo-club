# Hourly feedback-fix routine — design

**Status:** Draft, not yet enabled.
**Owner:** Joost.
**Scheduler:** Cowork scheduled task (`0 * * * *`, top of every hour) — separate from the existing launchd job.
**Related prior art:** `bin/get-feedback.sh` + `bin/com.rondo.feedback-agent.plist` (5-min launchd loop, currently running).

---

## 1. Purpose

Every hour, pick ONE feedback issue with status **Goedgekeurd** (`approved` in the DB) from Rondo Club's WP install, assess the fix's risk, and either:

- commit a safe/simple fix directly to `main`, OR
- open a ready-to-merge PR, OR
- open a PR with a clarifying question in the body.

Then update the issue's status so the next run doesn't pick it again. On empty runs (no approved items), do nothing and exit clean.

Why an *hourly* Cowork routine on top of the existing 5-min launchd script:

- Cowork gives access to Slack DM, Obsidian, Granola, and the Chrome MCP — the launchd script only has bash+Claude CLI.
- Hourly cadence with hard safety rails is more conservative than the 5-min loop, which is helpful now that agents are pushing to `main` autonomously.
- Structured risk-tiering (direct commit / PR / PR-with-question) is not in the launchd script — it always opens PRs.

**Open question for Joost:** should this REPLACE the launchd job (disable `com.rondo.feedback-agent.plist`) or COEXIST with it? Coexistence needs the shared lock file (see §5) so they don't grab the same issue. Default recommendation: coexist while this is being validated; if it works, disable launchd after ~2 weeks.

---

## 2. Discovery — what we already have

### 2.1 Feedback CPT

- Slug: **`rondo_feedback`** — registered at `rondo-club/includes/class-post-types.php:587` (function starts at line 551).
- Lives only in the `rondo-club` repo; feedback filed against `rondo-sync` or `website` carries a `_feedback_project` meta indicating which sibling repo the fix belongs in.
- `show_in_rest = true`, `rest_base = feedback`, but the routine uses the custom endpoints (§2.3) — richer payload, admin-only status writes.

### 2.2 Status vocabulary — DB stores English, UI shows Dutch

Canonical enum: `rondo-club/includes/class-feedback-status-service.php:16`

```
const ALLOWED_STATUSES = [
    'new', 'approved', 'in_progress', 'in_review',
    'resolved', 'declined', 'needs_info',
];
```

Dutch labels (`rondo-club/src/pages/Feedback/FeedbackList.jsx:29–37`): `new → Nieuw`, `approved → Goedgekeurd`, `in_progress → In behandeling`, `in_review → In review`, `resolved → Opgelost`, `declined → Afgewezen`, `needs_info → Info nodig`.

**"Goedgekeurd" = `approved` in the DB.** Lifecycle: `new` → `approved` → `in_progress` → `in_review` → `resolved` / `declined`. `needs_info` flips back to `new` when the user replies (`class-rest-feedback.php:794`).

Resolving requires a Dutch `resolution_summary` (StatusService line 39). On resolve, `_feedback_resolved_at` is stamped and a resolution email is sent to the reporter.

### 2.3 DB-access mechanism (picked)

**Custom REST API** at namespace `rondo/v1`, defined in `rondo-club/includes/class-rest-feedback.php`. Auth: HTTP Basic with a WordPress Application Password.

Credentials already live in `rondo-club/.env`:

- `RONDO_API_URL` (`https://rondo.svawc.nl`)
- `RONDO_API_USER`
- `RONDO_API_PASSWORD`

Endpoints the routine uses:

| Verb | Path | Purpose |
|---|---|---|
| `GET` | `/wp-json/rondo/v1/feedback?status=approved&per_page=20&orderby=date&order=asc` | Fetch the queue |
| `GET` | `/wp-json/rondo/v1/feedback/{id}` | Full detail |
| `GET` | `/wp-json/rondo/v1/feedback/{id}/comments` | Prior agent/user comments |
| `PUT` | `/wp-json/rondo/v1/feedback/{id}` | Status flip + `agent_branch`, `pr_url`, `resolution_summary` |
| `POST` | `/wp-json/rondo/v1/feedback/{id}/comments` | Post a question when downgrading to `needs_info` |

**Why REST and not WP-CLI or direct DB:**

- WP-CLI over SSH works from Joost's laptop but requires an interactive SSH session with SiteGround — brittle for a scheduled runner.
- Direct MySQL means shipping SG DB creds into the runner. Rejected on security grounds.
- The REST endpoints already enforce the state-machine (admin-only status writes, `resolution_summary` requirement, `needs_info` reset). Reusing them means we can't corrupt the state machine.
- Same mechanism as `bin/get-feedback.sh` — proven in production.

**No new endpoint needed.**

### 2.4 Deployment

- Every push to `main` triggers `.github/workflows/ci.yml` → `_deploy-production.yml` (build → rsync over SSH → cache clear). See AGENTS.md Rule 8. Rollback via `rollback-production.yml`.
- **Direct commits to `main` deploy automatically.** This is the whole reason the risk tier matters.
- `main` is the release branch. There's no staging step between `main` and prod.

### 2.5 Prerequisites for the runner box

- `gh` CLI authed as an account with push+PR rights on `RondoHQ/rondo-club`, `RondoHQ/rondo-sync`, `RondoHQ/website`.
- `git` configured with a commit identity (`user.name` + `user.email`) — recommend a dedicated `rondo-agent` identity so commits are attributable.
- `jq`, `curl`, `node` (for `npm run build`), `composer` (for `composer lint`).
- Cowork Slack MCP configured (both single-workspace `mcp__ef177067-*__slack_send_message` for the Emilia workspace and/or `mcp__multi-slack__*` — either works; we DM Joost on Emilia only).

---

## 3. The risk heuristic

Conservative by default. When in doubt, downgrade one tier.

### 3.1 Safe + simple → direct commit to `main`

ALL of these must hold:

- Diff ≤ 30 lines, single file, single hunk in intent.
- No changes to: DB schema / migrations, ACF field definitions, `class-rest-*.php` endpoint contracts, `class-access-control.php`, `class-user-roles.php`, `class-membership-fees.php` or anything under `Fees/` / `Contributie/`, `class-google-*.php`, `.env`, `.claude/`, `.planning/`, workflow files under `.github/`, `bin/deploy.sh`.
- Feedback issue explicitly specifies the target value (e.g. "the button label should read X" — X is unambiguous).
- `npm run lint`, `npm run build`, and `composer lint` all pass on the change.
- If the file has associated Codeception tests that are currently green (`AgeGroupAccessTest` and any others confirmed green on `main`), those tests still pass.

**Examples that qualify:**

- Typo fix in a `.php` string or `.jsx` copy.
- Missing `alt=""` on an `<img>`.
- Dutch translation added to `Feedback/*.jsx` labels.
- Hardcoded value change the reporter names exactly ("`per_page` should be 50 not 25").
- Adding a missing `type="button"` on a `<button>` inside a form.

### 3.2 Ready-to-merge PR (no question)

The change is well-defined but nontrivial:

- Multi-file OR > 30 lines.
- New feature, even small.
- Any refactor.
- Adds or modifies tests.
- Touches auth, payments, membership fees, Google integrations, DB schema, REST contracts.
- Requires reasoning about edge cases (empty states, permissions, i18n locale, mobile viewport, offline behavior).

PR opens against `main`, review requested from Copilot, `in_review` status set on the feedback, agent DMs Joost.

### 3.3 PR with a specific question in the body

The feedback is ambiguous OR the fix has ≥2 plausible approaches with real trade-offs:

- "Should we show X or Y?" / "Do you prefer inline or modal?" / "Weekly or monthly?"
- Bug report lacks repro steps and none can be inferred confidently.
- The fix would introduce a new abstraction and there's a competing existing one.
- The feedback describes a symptom whose root cause has more than one plausible location.

Routine's obligation:

- Do the best-effort implementation of ONE approach in the PR (so Joost can react to something concrete).
- PR body has a dedicated `## Question for Joost` section stating the question and what the alternative would look like.
- Feedback status flipped to `needs_info`, and the question also posted as a `POST /comments` entry so it shows up in the Rondo UI.

### 3.4 Calibration examples

| Feedback | Tier | Why |
|---|---|---|
| "Typo in the welcome banner: 'welkkom' → 'welkom'" | Direct commit | 1 char, unambiguous, no logic. |
| "The 'Save' button on Person edit is grey — should be blue like elsewhere" | Direct commit | Copy-through of an existing pattern, single class change. |
| "Add a 'Notes' field to the person profile" | PR | New field → ACF + REST + React. Multi-file, needs migration plan. |
| "Team overview should show U19 highlighted" | PR-with-question | "Highlighted how — background color, badge, tooltip?" — pick one, ask. |
| "Login is broken for me since yesterday" | PR-with-question | Need repro; also touches auth = never direct commit. |
| "Add German translation of the dashboard" | PR | Large, non-trivial i18n, needs infra decision. |
| "The 'Contributie' amount for U12 shows €0 — should be €85" | PR (never direct) | Payment/fee code. Even if change is one line, tier locks it to PR minimum. |

---

## 4. Safety rails

Baked into the SKILL, not policy documents:

1. **Max ONE issue per run.** Never chain fixes.
2. **Refuse to fix if the working tree isn't clean** (`git status --porcelain` non-empty) OR `main` isn't checked out OR local `main` is behind `origin/main` and can't fast-forward.
3. **Always start from a fresh `git fetch origin && git checkout main && git pull --ff-only`.**
4. **Test-gate direct commits.** Before any commit to `main`, run `npm run lint && npm run build && composer lint`. On any failure, downgrade to PR and paste the failing output into the PR body.
5. **Never touch** `.env`, `.claude/`, `.planning/`, `.github/workflows/`, `bin/deploy.sh`, `wp-config.php`, or `acf-json/*.json` (unless the feedback explicitly says so, in which case it's PR-tier at minimum).
6. **Never run `bin/deploy.sh` directly.** Deploys go through the GitHub Actions pipeline triggered by push to `main`.
7. **Every commit + PR body links to the feedback:** admin URL `https://rondo.svawc.nl/feedback/{id}` + feedback ID + a short "why this fix" paragraph in Dutch (for the resolution email later).
8. **Status transitions:**
   - Pick-time: `approved` → `in_progress` (atomic — see §5).
   - Direct commit succeeded: `in_progress` → `resolved` with `resolution_summary` in Dutch.
   - PR opened (no question): `in_progress` → `in_review` with `pr_url` + `agent_branch`.
   - PR opened with question: `in_progress` → `needs_info` with question also posted as a comment.
   - Any unrecoverable failure: reset to `approved` (matches `get-feedback.sh` crash cleanup).
9. **Slack DM per run**, always. Format:
   - Empty run: no DM.
   - Direct commit: `✅ Fixed #{id}: {title} — commit {sha} (auto-deploying)`.
   - PR opened: `📬 PR opened for #{id}: {title} — {pr_url}`.
   - PR with question: `❓ PR + question for #{id}: {title} — {pr_url} — {short_question}`.
   - Skipped/error: one line explaining why.
10. **Log every run** to `rondo-club/logs/hourly-feedback-fix.log` (create if missing, append-only, timestamped Europe/Amsterdam).

---

## 5. Concurrency

Two protections:

**5a. Atomic status flip at pick-time.**

The routine's very first mutating call is `PUT /wp-json/rondo/v1/feedback/{id}` with `{"status": "in_progress"}`. The server-side StatusService validates transitions. Between GETting the queue and PUTing the flip, another runner may have grabbed the same item — the second PUT will race, and both may "succeed" because `approved → in_progress` is a legal move from either state. To prevent that:

- Immediately after the PUT, `GET /wp-json/rondo/v1/feedback/{id}` and check `_feedback_agent_branch` — if set to a branch that isn't ours, we lost the race, revert (`in_progress → approved` — TODO: verify this transition is legal in StatusService; if not, do NOT revert and instead skip cleanly), and exit.
- The routine's PUT should include `agent_branch: "cowork/feedback-{id}-{sha7}"` and `agent_plan: "cowork-hourly:{run_uuid}"` so the tag is unique per run.

**5b. Cross-runner lock file.**

- `/tmp/rondo-feedback-cowork.lock` — flock() this file for the duration of the run.
- Before acquiring: check if `/tmp/rondo-feedback-claude.lock` (the launchd script's lock) is held. If yes, exit clean — the launchd script is mid-fix, don't stomp on it.
- The launchd script does NOT check for our lock, so if the two run truly simultaneously it's possible for both to acquire an item. Mitigations: the launchd script picks the OLDEST approved item, so if we also pick oldest, the atomic status flip catches the race.

**Recommendation:** while coexisting, edit `com.rondo.feedback-agent.plist` to check for our lock too. If we replace the launchd script (recommended path), no coexistence risk.

---

## 6. Multi-repo handling

Phase 1: **only handle feedback with `_feedback_project == 'rondo-club'`.** For other projects, skip the item, don't flip status, log "skipped: rondo-sync (phase 2)". This mirrors the initial rollout of `get-feedback.sh` and avoids requiring the Cowork runner to know how to switch working directories mid-run.

Phase 2 (after rondo-club works): `cd` into the sibling repo (`../rondo-sync`, `../website`) based on `_feedback_project` and run the same routine. Each has its own `AGENTS.md` — read it before making changes.

---

## 7. Failure modes and their handling

| Failure | Handling |
|---|---|
| No approved items | DM nothing. Exit 0. Log "empty queue". |
| REST API unreachable | Exit 0 with WARNING log + Slack DM "⚠️ Rondo API unreachable this run". |
| Working tree dirty | Exit 0 with WARNING log + Slack DM "⚠️ Skipped — working tree dirty on {branch}". Do NOT touch feedback status. |
| Not on `main` / can't ff-pull | Same as above. |
| Lock held (either lock file) | Exit 0 silently. Log "lock held by PID X". |
| Race lost to launchd script | Exit 0. Log "race lost — {id} was picked by {other_agent_branch}". |
| Test failure during direct-commit gate | Downgrade to PR. Paste failing output into PR body. |
| `npm run build` fails on branch | Do NOT push. DM Joost "🚨 Feedback #{id}: build broke on branch, no PR opened". Reset status to `approved`. |
| `gh pr create` fails | Reset status to `approved`. DM Joost with the gh error output. |
| Runner crashes mid-run | Cowork task infrastructure logs the crash. The `in_progress` item stays stuck; next run should detect it (any item with `_feedback_agent_branch` set to a `cowork/…` branch that has no corresponding remote branch = orphaned) and reset to `approved`. |
| Claude decides fix is impossible | Set to `declined`. Post a comment explaining why. DM Joost. |

---

## 8. Rollout plan

1. **Land this doc + the SKILL** in a PR. Do NOT register the scheduled task.
2. **Manual dry-run** — invoke the SKILL manually from Cowork on a real `approved` item, in "report only" mode (no writes, no commits, no PR). Verify: it picks the right item, drafts the right tier, would DM the right message.
3. **Live single-run** — invoke the SKILL manually once, non-dry, with a low-stakes item (typo). Verify: commit lands, deploy triggers, status flips to `resolved`, Slack DM arrives.
4. **Enable the scheduled task** at `0 * * * *` — but keep `enabled: true` and start with the shared lock (§5b) coexisting with launchd.
5. **After ~5 clean runs**: propose disabling `com.rondo.feedback-agent.plist`.
6. **After ~2 weeks**: enable Phase 2 (rondo-sync, website).

---

## 9. Open questions

1. **Coexist or replace the launchd script?** (See §1.) My recommendation: coexist during validation, replace after.
2. **Is `in_progress → approved` a legal transition in StatusService?** Need to check the state machine before writing revert logic (§5a). If not legal, we skip cleanly on race loss.
3. **Direct-commit tier — appetite check.** Are typos and copy fixes really acceptable to auto-deploy without human review? Alternative: no direct commits ever, always PR, but with an "auto-merge if green" flag on the PR.
4. **`resolution_summary` for direct commits** — should it always be "Fixed automatically by the hourly agent. Zie commit {sha}." or should it try to phrase it like the reporter would want to read?
5. **Author identity for commits** — use a dedicated GitHub account (`rondo-agent`)? Or commit as Joost with a `Co-authored-by: Claude <noreply@anthropic.com>` trailer?
6. **Slack channel for DMs** — DM to Joost only, or also post to a `#rondo-agent` channel for team visibility?

---

## 10. What ships in the PR

- This doc: `docs/routines/hourly-feedback-fix.md`.
- The SKILL: `~/Documents/Claude/Scheduled/hourly-feedback-fix/SKILL.md` (lives outside this repo — attached to the plan as a reference; installed via `create_scheduled_task` after review).
- No code changes in the repo — the routine reuses the existing REST endpoints and doesn't need new PHP.

Cron expression when registered: `0 * * * *`. Suggested `jitterSeconds`: 300 (spread runs so they don't all fire on the same second across machines).
