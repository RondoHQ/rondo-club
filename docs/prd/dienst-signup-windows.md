# PRD: Seizoensbrede planning en inschrijfvensters voor diensten

> Coordinators plan and see the whole season, Sep→Jun. Volunteers see the whole season too, but
> may only sign up for the first half until the second half opens — by default 1 November.

**Status:** Plan — not implemented, awaiting review
**Components:** club
**Date:** 2026-07-30
**Baseline:** `main` @ `2dae97e2` (v33.78.2)
**Sibling PRD:** [`dienst-assignment-by-coordinator.md`](dienst-assignment-by-coordinator.md) — who may
assign people to a dienst. Kept separate: different code paths, different PRs, and either can ship
without the other. §9 covers the one place they interact.

---

## 1. What is being asked

| Surface | Rule |
|---|---|
| Beheer (coordinator) | Show the entire club year of diensten, through June — the full Sep→Jun season, including diensten later in the year. |
| Inschrijven (volunteer) | Until November: only the first half (≈Sep–Dec) is signup-open. From November: the second half (Jan–Jun) opens too. |
| Inschrijven (volunteer) | Second-half diensten stay **visible** while closed, with the signup action disabled and a "kan vanaf 1 november" message. |

## 2. The finding that reorders this whole plan

**The diensten being discussed do not exist yet.**

`ShiftTemplateExpander::WINDOW_DAYS = 93` (`class-shift-template-expander.php:26`). A daily cron
(`rondo_expand_shift_templates`) expands every `shift_template` into concrete `dienst_shift` posts
for a rolling 93-day window and no further. In October, June's diensten are not in the database.

So neither headline requirement is a filtering problem today:

- "Show the whole season in beheer" — there is nothing to show past ~January.
- "Show second-half diensten greyed out" — likewise; a volunteer in September cannot see a March
  dienst because it does not exist.

The signup window that the second requirement asks for is, ironically, **already enforced by
accident**: `MemberShifts::AVAILABLE_WINDOW_DAYS = 93` plus the 93-day expansion means a volunteer
can only ever sign up about three months ahead. Today's behaviour is "a rolling quarter", and the
request is to replace it with "a season, with one deliberate mid-season gate".

That inverts the work. The order is:

1. **Expand the season** (§5) — make the shifts exist. Nothing else is possible first.
2. **Widen the views** (§6) — the calendar endpoint currently refuses a season-length range.
3. **Gate signup** (§7) — the actual new rule, and the smallest of the three.

A plan that starts at step 3 would ship a correct gate over an empty calendar.

## 3. Terrain

### 3.1 How diensten come into existence

- `shift_template` — recurring rule (day of week, times, capacity, `active_from`, `active_until`).
- `ShiftTemplateExpander::expand_range( $from, $to )` — idempotent; de-dupes on
  (`template_id`, `start_datetime`) via `find_existing_shift()`, and clamps each template to its
  own `active_from`/`active_until`.
- Triggers: daily cron over `[today, today+93d]`; `acf/save_post` on a template; and a manual
  **`POST /rondo/v1/shift-templates/expand`** which already takes an arbitrary `until` date
  (`class-shift-template-expander.php:41`) and is gated on `manage_options || vrijwilligers`.

That last one matters: **full-season rollout is already possible by hand today.** What is missing
is a horizon that maintains itself, and views that can display it.

### 3.2 How signup works today

`MemberShifts` (`class-rest-member-shifts.php`):

- `GET /rondo/v1/shifts/available` — flat list, `AVAILABLE_WINDOW_DAYS = 93`, status `open`.
- `GET /rondo/v1/shifts/calendar?view=signup|manage` — the surface both screens actually use.
  `calendar_range()` defaults `to` to `first day of this month +6 months -1 day` and hard-refuses
  anything longer than `CALENDAR_MAX_DAYS = 190` with `calendar_range_too_large`.
  **Sep 1 → Jun 30 is 303 days**, so the season view is refused by the current API.
- `POST /rondo/v1/shifts/{id}/signup` — the only write path. Enforces eligibility, VOG, IVA, pool,
  capacity, status and overlap.

`member_shift_block_reason()` returns `vog` / `iva` / `pool`, and every caller uses it to
**`continue`** — i.e. block reasons *hide* a shift. This is the key structural detail for §7: the
new rule must make a shift *visible but disabled*, so it cannot reuse that mechanism.

### 3.3 Season definition already exists

`SeasonKey::current()` (`class-season-key.php:34`): the season runs **1 July – 30 June**;
July–December belongs to `YYYY-(YYYY+1)`. `VolunteerObligationCalculator` and the volunteer
dashboard already key everything on it. No new season concept is needed anywhere in this plan.

### 3.4 Where club-wide config lives

`ClubConfig` (`class-club-config.php`) — flat `rondo_*` options with a `DEFAULTS` map, read through
`GET /rondo/v1/config` (any logged-in user) and written through `POST /rondo/v1/config`
(`check_admin_permission`), edited in Settings. It already carries `volunteer_signup_info`, and
`functions.php:726` localises that into `window.rondoConfig` for the frontend. A new volunteer
setting has an obvious, boring home.

---

## 4. Decisions & open questions

### 4.1 Recommendations for the six design questions

| # | Question | Recommendation |
|---|---|---|
| **1** | First half vs second half: hard calendar split, or configurable boundary? | **Neither as posed — derive it from `SeasonKey`.** The season is already defined as 1 Jul – 30 Jun, so "first half" is Jul–Dec and "second half" is Jan–Jun, and the boundary *is* 1 January by construction. Making the split separately configurable would create a second, competing definition of a season half that could silently disagree with the obligation calculator. Configure the **opening moment** (Q2), not the split. |
| **2** | When does the second half open — fixed 1 November or configurable? | **Configurable, stored as a month-day, defaulting to `11-01`.** A month-day (not a full date) applies every season without an annual edit, which is what a board actually wants; a full date would need re-entering each summer and would silently expire. Home: `ClubConfig::OPTION_VOLUNTEER_SECOND_HALF_OPENS`, admin-writable via the existing `/rondo/v1/config` route, edited in Settings → Vrijwilligers next to the existing signup-info block. |
| **3** | Visual gate, server gate, or both? | **Both, and the server is the real one.** `POST /shifts/{id}/signup` gains the check; the calendar payload gains a flag so the UI can disable the button. Stated in the plan because the two must be derived from one shared helper, or they will drift. |
| **4** | Does signup already exist? | Yes — §3.2. The new rule is one more refusal inside the existing `signup()` guard chain, plus one more field on the existing calendar payload. No new endpoint. |
| **5** | Edge cases | §9. |
| **6** | Greyed out, or hidden? | **Greyed out with a badge**, per the request — but it forces a third day-state in the calendar (§8), which is the real cost and the reason to decide deliberately. |

### 4.2 Decided (Joost, 2026-07-30)

| # | Question | Decision |
|---|---|---|
| **W1** | How far ahead should the cron expand? | **To season end**, computed per run, with `WINDOW_DAYS` surviving as a floor so late-June runs still roll into the new season. The manual rollout endpoint is unchanged. §5.1 covers volume and guard rails. |
| **W2** | Club-wide opening date, or per dienst type? | **Club-wide.** One date for every dienst type. A per-type override stays additive if it is ever wanted. |
| **W4** | Does the gate apply to a future season's diensten? | **Yes.** Anything beyond the current season is closed, with its own computed opening date, so a June rollout cannot accidentally open next season's bardiensten. |
| **W5** | What is the club year for the views? | **August → June.** Not `SeasonKey`'s Jul→Jun and not the Sep→Jun of the original request. See the caveat below — this is the one decision that puts a view boundary and a domain boundary slightly out of step, deliberately. |

**W5 caveat, written down so it is not rediscovered as a bug.** `SeasonKey` remains the single
definition of *which season a date belongs to* — 1 July – 30 June — and every window, obligation and
credit calculation keeps using it. August→June is a **display default for the calendars only**:

- Default calendar range becomes 1 August → 30 June of the season containing today (334 days).
- A dienst in **July** still belongs to its season for every rule, but falls outside the default
  view. It is reachable by passing explicit `from`/`to`, and `expand_range()` still creates it.
- Practical effect: if the club ever plans a July toernooi-dienst, it will not appear on the default
  beheer calendar. Accepted; the alternative (moving the season boundary) would desynchronise
  volunteer obligations from the fee season, which is a far worse trade.
- `CALENDAR_MAX_DAYS` is set to **370** rather than 334, so an explicit July→June request still fits.

### 4.3 Still open

| # | Question | Default I'd ship |
|---|---|---|
| **W3** | **What happens to the 93-day `AVAILABLE_WINDOW_DAYS` flat list?** With a season horizon it becomes an arbitrary third window that disagrees with both the calendar and the gate. | **Retire the constant** and let `/shifts/available` return everything currently signup-open. If that list gets long, cap it by season rather than by an unrelated day count. |

Assumptions made without asking:

- The window gates **new signups only**; it never removes an existing assignment.
- Cancellation is never gated (matching the current "afmelden mag altijd" stance).
- Coordinators and admins bypass the window entirely (§10).
- Dutch UI copy; no new frontend dependency; no new CPT, table or ACF field.

---

## 5. Step 1 — make the season exist

### 5.1 Expansion horizon

Replace the fixed 93-day cron window with a season-bounded one:

```php
// ShiftTemplateExpander
public static function default_window_end( ?string $today = null ): string {
    // Season end (30 June) for the season containing today, never less than
    // WINDOW_DAYS ahead — so a late-June run still rolls into the new season.
    $season      = SeasonKey::current( $today );
    $season_end  = ( (int) substr( $season, 5, 4 ) ) . '-06-30';
    $minimum_end = gmdate( 'Y-m-d', strtotime( ( $today ?: 'today' ) . ' +' . self::WINDOW_DAYS . ' days' ) );

    return max( $season_end, $minimum_end );
}
```

`WINDOW_DAYS` survives as a floor, not a ceiling: the last weeks of June must not leave the club
with an empty calendar while the new season's templates are being set up.

Why this is safe to run daily:

- `expand_range()` is idempotent (`find_existing_shift()` on `template_id` + `start_datetime`), so
  after the first run each subsequent run creates approximately nothing.
- Templates are clamped by their own `active_from` / `active_until`, so a template that ends in
  December will not spray shifts into spring.

**Volume.** One weekly template over a full season ≈ 52 shifts; a club with 15 templates ≈ 780
`dienst_shift` posts per season, created once at season start. That is well within WordPress'
comfort zone but it is a real jump from the current steady state, so:

- The first run after deploy will be the big one. Log created counts (`expand_range()` already
  does) and check the cron did not time out.
- `rerun_template()` deletes and recreates future template-managed shifts; over a season-long
  horizon that is now a much bigger operation. It already preserves customised, cancelled and
  signed-up shifts, so correctness holds — but the "Opnieuw uitrollen" button in the sjabloon
  editor needs a confirmation that says how many shifts it will touch.

### 5.2 Templates need a season, not just a horizon

Expanding to season end only helps if templates are set up for the season. Two follow-ons:

- A template with an empty `active_until` now expands to season end, which is the intent.
- A template with `active_until` in December stops there — correct, and the way to express
  "eerste seizoenshelft" per template without any new field.

No schema change. Worth a line in the sjabloon editor explaining that an empty `active_until` now
means "tot het einde van het seizoen".

## 6. Step 2 — let the views show a season

### 6.1 Calendar range

`MemberShifts::CALENDAR_MAX_DAYS` 190 → **370**, and `calendar_range()`'s default range becomes the
club year of the season containing today: **1 August → 30 June** (W5). 370 leaves room for an
explicit July→June request without inviting unbounded ranges.

Cost check: `get_shift_calendar()` caps at `posts_per_page => 1000` and does per-shift meta reads.
A full season for a 15-template club (~780 shifts) fits under that cap but roughly triples today's
payload. Two mitigations, in order of preference:

1. **Keep the frontend asking for what it renders.** The manage calendar renders month grids; it
   can request the season in one call (the user asked to see the year) but the signup calendar has
   no reason to.
2. If the season payload proves heavy, add `fields=summary` before adding pagination — the shape is
   already a per-day rollup.

Flagged, not solved: measure on production data before assuming it is fine. `PERFORMANCE-FINDINGS.md`
exists in the repo root and this belongs in it.

### 6.2 Beheer view

`VrijwilligersDiensten.jsx` passes no explicit range today, so it inherits the default. Once the
default is season-based it shows the season with no frontend change, and `ShiftCoverageCalendar`
already renders an arbitrary month span via `eachMonthOfInterval`. Add a season label
("Seizoen 2026–2027") and, if the payload measurement in §6.1 says so, a season switcher.

## 7. Step 3 — the signup gate

### 7.1 One helper, three callers

```php
// New: includes/class-shift-signup-window.php  (Rondo\Volunteer\ShiftSignupWindow)
final class ShiftSignupWindow {
    /** Is this shift open for self-service signup right now? */
    public static function is_open( int $shift_id, ?DateTimeImmutable $now = null ): bool;

    /** When does it open? Null when it is already open or has no window. */
    public static function opens_at( int $shift_id, ?DateTimeImmutable $now = null ): ?DateTimeImmutable;

    /** The configured opening moment for a given season, from the month-day setting. */
    public static function second_half_opens( string $season ): DateTimeImmutable;
}
```

Rules, in one place:

1. Shift start is in the **first half** of its season (Jul–Dec) → open.
2. Shift start is in the **second half** (Jan–Jun) → open from `second_half_opens( season )`,
   which is the configured month-day resolved into the season's first calendar year (default
   1 November).
3. Shift belongs to a **later season** than today's → closed, opening at that season's date (W4).

Everything derives from `start_datetime` and `SeasonKey`; there is no new per-shift state.

### 7.2 Server enforcement

In `MemberShifts::signup()`, after the status check and before capacity:

```php
if ( ! ShiftSignupWindow::is_open( $shift_id ) ) {
    return new \WP_Error(
        'signup_not_open_yet',
        sprintf(
            'Inschrijven voor deze inschrijftaak kan vanaf %s.',
            wp_date( 'j F', ShiftSignupWindow::opens_at( $shift_id )->getTimestamp() )
        ),
        [ 'status' => 409, 'opens_at' => ... ]
    );
}
```

`409`, consistent with the other "right shift, wrong moment" refusals (`shift_full`,
`shift_closed`).

### 7.3 Payload — visible but disabled

The window must **not** join `member_shift_block_reason()`: every caller of that helper does
`continue`, which hides the shift, and the requirement is the opposite. Instead extend the summary
in `format_shift_summary()` consumers:

```jsonc
{
  "can_signup": false,          // already exists — now also false when the window is shut
  "signup_opens_at": "2026-11-01",
  "signup_window_state": "second_half_locked"
}
```

`can_signup` already gates the button, so the button disables itself the moment the backend says
so — the failure mode if the frontend work slips is a clear server error, not a wrong grant.

## 8. UI

**Recommendation: show second-half diensten, disabled, with a badge.** Per the request, and
because hiding them produces the exact confusion this feature is meant to remove ("where are the
voorjaarsdiensten?"). The trade-off is real though, and it is not mainly clutter:

**The calendar's day colouring becomes wrong.** `ShiftCoverageCalendar` currently has two states:
`full` (green, all filled) and `open` (red, plekken open). A February day full of not-yet-open
diensten would render red — "hier moet je je inschrijven" — for something nobody can act on. So:

- Add a third day state `locked` (neutral grey, distinct from both), with the day label reading
  "opent 1 november" instead of "N plekken open".
- Compute it in `get_shift_calendar()` alongside the existing rollup: a day is `locked` when every
  shift on it is window-closed.
- `dayStatusLabel()` and the legend need the third case; the aria-label already reads the state
  string, so screen readers follow for free.

Per-shift, in the day popover and the signup list: keep the row, disable the button, and show a
badge — **"Inschrijven kan vanaf 1 november"** — using `signup_opens_at` rather than hardcoded copy,
so a board that changes the date does not need a deploy.

The member page also deserves one sentence above the calendar while the second half is shut, e.g.
*"Diensten van januari tot en met juni gaan open op 1 november."* That is the difference between a
volunteer understanding the system and filing a bug report.

## 9. Interaction with the assignment PRD

The sibling PRD adds `POST /rondo/v1/shifts/{id}/assignees` — a coordinator assigning someone.
**That endpoint must not enforce the signup window.** A coordinator arranging February's bardienst
in September is a deliberate act by someone with the capability to do it, and the window exists to
pace self-service, not to constrain planning. Concretely: the window check goes in `signup()`, not
in the shared eligibility helper that both paths call. If both PRDs land, add a test asserting
exactly this, because the natural refactor is to push the check into the shared helper and silently
break coordinator planning.

## 10. Permission model

No new capability. The window applies to **self-service signup only**:

| Actor | Sees the season | May sign up in a closed window |
|---|---|---|
| Volunteer (member) | Yes, disabled beyond the window | No — `409 signup_not_open_yet` |
| Coordinator (`vrijwilligers`) | Yes, in beheer | N/A — assigns via the assignment endpoint (§9) |
| Admin (`manage_options`) | Yes | Yes, via the same assignment path |

Config write is admin-only, inherited from `POST /rondo/v1/config`'s existing
`check_admin_permission`.

## 11. Data & migrations

**No new tables, CPTs or ACF fields.** Everything derives from `start_datetime` + `SeasonKey` +
one option.

- New option `rondo_volunteer_second_half_opens`, month-day string, default `11-01`, in
  `ClubConfig::DEFAULTS` with getter/updater and validation (`m-d`, valid calendar date).
- Surfaced to the frontend via `functions.php:726`'s existing `rondoConfig` localisation so the
  badge can render without an extra request.
- **Data volume is the real migration** (§5.1): the first cron run after deploy expands the rest of
  the season. Run it manually via the existing `/shift-templates/expand` endpoint at a quiet moment
  rather than discovering the cost during the nightly cron.
- Indexing: unchanged. Calendar queries already filter on the indexed `start_datetime` meta with a
  `BETWEEN`; a longer range is more rows, not a different plan.

## 12. Edge cases

| Case | Behaviour |
|---|---|
| Member already signed up for a second-half dienst before it opened (admin-added, coordinator-assigned, or migrated) | **Assignment stands.** The window gates new signups only; it never removes anyone. Their dienst shows normally in "Mijn inschrijftaken". |
| That member then cancels in October | Cancellation is allowed (never gated). **But they cannot re-sign until November** — the shift is back in a closed window. Mildly surprising; mitigated by the badge, and the coordinator can re-assign them. Worth naming in the volunteer comms. |
| Dienst spanning the year boundary (31 Dec 20:00 → 1 Jan 01:00) | Half is decided by `start_datetime`, so it counts as first-half and is open. Deterministic, and the alternative (end time) would close New Year's Eve diensten. |
| Dienst with no `start_datetime` | Already impossible in practice — `format_shift_summary()` returns null without start/end, so such shifts are invisible everywhere. `is_open()` returns false (fail closed). |
| July / August diensten | Belong to the *next* season by `SeasonKey`, i.e. its first half → open once that season is the current one. See W5. |
| Diensten of a future season rolled out early | Closed until that season's opening date (W4). |
| Board changes the date mid-season, from 1 Nov to 15 Oct, on 20 Oct | Second half opens immediately for everyone; nothing is retroactive and nothing is lost. Changing it *later* (to 1 Dec after November started) re-closes the second half — signups already made stand. Flag in the settings UI copy. |
| Config missing or malformed | Falls back to `11-01`. Validation on write, fail-safe on read. |
| Shift is cancelled or full | Existing refusals still apply and take precedence in the message; window state is only interesting for an otherwise-signup-able shift. |
| Coordinator assigns into a closed window | Allowed by design (§9). |

## 13. Testing

**New: `tests/Wpunit/ShiftSignupWindowTest.php`** — pure date logic, no REST, fast:

1. First-half shift (October, season 2026-2027) is open in September.
2. Second-half shift (March 2027) is closed on 31 October 2026, open on 1 November 2026.
3. Boundary: open exactly at 00:00 on the opening day, in the WordPress timezone — not UTC.
4. Configured month-day `10-15` moves the opening; malformed config falls back to `11-01`.
5. Next-season shift is closed even when today is past this season's opening date (W4).
6. Shift with no `start_datetime` → closed (fail closed).
7. `opens_at()` returns null for an already-open shift.

**New: `tests/Wpunit/ShiftSignupWindowEnforcementTest.php`** — the REST layer:

1. `POST /shifts/{id}/signup` on a closed second-half shift → `409 signup_not_open_yet` with
   `opens_at` in the payload.
2. Same shift after the opening date → succeeds.
3. Closed shifts are **present** in `GET /shifts/calendar?view=signup` with `can_signup: false` and
   `signup_opens_at` set — the regression guard against someone routing the window through
   `member_shift_block_reason()` and hiding them.
4. A day whose shifts are all window-closed reports `state: "locked"`.
5. An existing assignment on a closed shift survives and still appears in `/my-shifts`.
6. Cancelling a closed-window assignment succeeds.
7. **The coordinator assignment endpoint ignores the window** (§9) — only if the sibling PRD has
   landed; otherwise a `@todo` referencing it.

**Extend `ShiftTemplateExpanderTest`:**

1. `default_window_end()` returns season end for a mid-season date, and the 93-day floor in late June.
2. Expanding twice over a season range creates nothing the second time (idempotence at scale).
3. A template with `active_until` in December produces no January shifts.

**Manual on production:** roll out one season manually, confirm the beheer calendar renders Jul→Jun,
confirm a volunteer sees March diensten greyed with the badge, confirm the signup POST refuses,
then move the config date to today and confirm they open without a deploy.

## 14. Rollout

Order matters — each step is useless before the one above it:

1. **PR 1 — expansion horizon.** `default_window_end()`, expander tests, the "Opnieuw uitrollen"
   confirmation copy. Deploy, then run the manual rollout at a quiet moment and check the created
   count and cron timing. Nothing user-visible changes yet beyond a fuller calendar.
2. **PR 2 — season-length views.** `CALENDAR_MAX_DAYS`, season-based default range, beheer season
   label. Measure the payload here (§6.1) before moving on.
3. **PR 3 — the gate.** `ShiftSignupWindow`, `ClubConfig` setting + Settings UI, the `signup()`
   refusal, the payload fields, both new test files.
4. **PR 4 — the UI.** `locked` day state, badges, the explanatory sentence, legend.

Between PR 2 and PR 3 the second half is briefly **fully open** to volunteers — the views widen
before the gate exists. If that is unacceptable (it likely is, in a live season), ship PR 3 before
PR 2 and accept a season the API will not yet serve, or ship 2+3 together.

Version: patch bumps for PRs 1–2, **minor** (`33.79.0`) for PR 3 as the first user-visible feature.
CHANGELOG entries in Dutch per Rule 2.

**Comms.** Volunteers need one message before PR 3 lands: the second half opens on 1 November, you
can already see what is coming, and diensten you already have stay yours. Coordinators need to know
the beheer calendar now covers the season and that they can still assign anyone at any time.

**Rollback.** All four PRs are inert to revert — no schema, no destructive migration. The one
irreversible side effect is the shifts created by the wider horizon; they are ordinary
`dienst_shift` posts and can be left in place (a reverted expander simply stops maintaining them).
Deleting them is not required and would risk destroying signups.

## 15. Effort

**M (3–5 focused days)**, dominated by the calendar UI state and the payload measurement rather
than by the gate itself.

| Slice | Size |
|---|---|
| Expansion horizon + tests (PR 1) | S |
| Season-length views + payload measurement (PR 2) | S–M |
| `ShiftSignupWindow` + config + enforcement + tests (PR 3) | M |
| Calendar `locked` state, badges, copy (PR 4) | M |

The gate is genuinely small. If this needs to be cut down, **PR 1 alone has standalone value** —
a coordinator being able to plan and see the whole season is useful with or without the window.
