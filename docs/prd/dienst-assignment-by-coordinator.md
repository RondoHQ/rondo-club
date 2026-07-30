# PRD: Vrijwilligers indelen op een inschrijftaak

> Let a vrijwilligerscoördinator add a person to a `dienst_shift` on that person's behalf, see every
> member while doing it, and fix wrong contact details — without inheriting the club's finance and
> support internals.

**Status:** Plan — not implemented, awaiting review
**Components:** club
**Date:** 2026-07-30
**Baseline:** `main` @ `2c228546` (v33.78.0)
**Implementation:** PR 1 built on `feat/person-field-sensitivity-and-notes-scope` (v33.78.3)

---

## 1. Why this is needed

Members sign themselves up through `/vrijwillig`. Coordinators cannot do it for them. Today the
only way to get someone onto a shift is:

1. Ask the member to log in and sign up (blocked when they have no account, no e-mail, or phone
   only), or
2. Write `acf.assigned_persons` directly through `POST /wp/v2/dienst-shifts/{id}` — which is what the
   frontend shift editor would have to do, and which is **actively harmful** (see §3).

The intended capability is narrow: *"a coordinator standing in the kantine adds Jan to Saturday's
bardienst because Jan told them he'd do it."* It augments self-service signup, it does not replace it.

Two access-control changes come along with it, both driven by review decisions (§4.1):

- A coordinator must be able to **find** anyone in the club, not just their age groups.
- A coordinator hits wrong phone numbers and e-mail addresses constantly and must be able to **fix
  them** — without that turning into "may read and write every field on every member".

That second requirement is what most of this plan is about. It is a genuine access-control design
change, not a flag flip.

## 2. Terrain as it exists today

### 2.1 Data model

There is no assignment entity. Assignments are a single serialized meta array on the shift, plus
per-person side meta:

| Meta key on `dienst_shift` | Meaning |
|---|---|
| `assigned_persons` | `int[]` of `person` post IDs. Registered in `class-post-types.php:1087`, `show_in_rest` **writable**. |
| `_shift_signup_at_{person_id}` | Signup timestamp. Drives the 30-minute cancel grace and the "Recente aanmeldingen" table. |
| `_shift_signup_user_{person_id}` | WP user ID that created the assignment (`GuardianAccountService::SHIFT_USER_META_PREFIX`). Already an attribution trail. |
| `_shift_signup_guardian_name_{person_id}` | Guardian display name when a parent used a child's account. |
| `_shift_confirmation_queued_at_{person_id}` | Pending confirmation e-mail queue (`ShiftEmailScheduler`). |
| `_no_show_{person_id}` | No-show marker, admin-only. |
| `_shift_customized` | Shift was manually edited → the template expander must never overwrite or delete it. |

Shift itself: `dienst_type_id`, `template_id`, `capacity`, `start_datetime`, `end_datetime`,
`status` (`open` / `vol` / `voltooid` / `geannuleerd`), `notes`, `iva_waived`.

`person` ↔ WP user link: `rondo_linked_person_id` user meta, `UserProvisioning::META_USER_ID` post
meta. Assignments reference the **person**, never the user.

### 2.2 Rules already enforced server-side (`MemberShifts::signup()`)

- `VolunteerEligibilityService::may_volunteer()` — oud-leden are refused.
- VOG: `datum-vog` + 3 years, when `dienst_type.vog_required`.
- IVA: `IvaStatus::is_valid()`, when `dienst_type.iva_required` and not `shift.iva_waived`.
- Pool: `dienst_type.required_pool` → person must have a current `work_history` entry for that
  commissie.
- Capacity: refuse when `count(assigned) >= capacity`; flip `status` to `vol` when it fills.
- Status: only `open` / `vol` accept a signup.
- Overlap: same-person time overlap returns `409 overlap_warning` with `can_force: true`.
- Concurrency: `with_shift_write_lock()` — an `add_option()`-based lock, because `assigned_persons`
  is a read-modify-write on serialized meta.
- Side effects: `_shift_signup_at_`, `GuardianAccountService::mark_shift_signup()`,
  `ShiftEmailScheduler::queue_signup_confirmation()`, `VolunteerObligationCalculator::invalidate_cache()`.

### 2.3 Permissions

`vrijwilligers` grants **manage** on `dienst_type`, `shift_template`, `dienst_shift` and `taakuitleg`
via `UserRoles::sync_role_capabilities()`. `AccessControl::grant_volunteer_editing()` then lets any
holder edit records authored by someone else. Roles carrying it: `rondo_vrijwilligers`,
`rondo_iva_approver`, `rondo_bestuur`, `administrator`.

It grants **nothing** on `person` beyond read. `can_edit_people()` lists `manage_options`, `fairplay`,
`vog`, `financieel`, `ledenadministratie` — not `vrijwilligers`.

Admins toggle capabilities per role in Settings → Admin → Capabilities
(`/rondo/v1/settings/capability-matrix`, allowlist in `class-rest-capabilities.php:554`).

### 2.4 Person access control — and where it is already leaky

The rule lives in `AccessControl` and is driven by a single value,
`get_permitted_age_groups()`, with three outcomes:

| Outcome | Who | Sees which people | Sees which fields |
|---|---|---|---|
| `null` | holds a cap in `AGE_GROUP_BYPASS_CAPS` | everyone | **everything** |
| non-empty array | role has an entry in `rondo_age_group_access` | their age groups | **everything** |
| `[]` | everyone else (plain members) | self + minor children | `MEMBER_VISIBLE_ACF_FIELDS` allowlist |

`vrijwilligers` is **not** in `AGE_GROUP_BYPASS_CAPS`, so a coordinator is scoped today. Two
consequences already visible in the codebase:

- The assignee panel calls `wpApi.getPeople({ include: ids })` and gets **nothing** for a scoped
  coordinator — which is why `MemberShifts::add_assignee_display_names()` bolts
  `assigned_person_names` onto the `rest_prepare_dienst_shift` response as a workaround.
- Volunteer surfaces that genuinely need club-wide data (`/rondo/v1/iva/people`,
  `/rondo/v1/volunteer-data-quality/{category}`) bypass the rule with `suppress_filters => true`.

**The important finding: the model conflates two independent axes.** "Which people" and "which
fields" are decided by one value, and field redaction only ever fires for the household tier. So:

1. An age-group coordinator (tier 2) **already sees `financiele-blokkade`, `wacht_op_overschrijving`,
   `freescout-id` and every `_nikki_*` balance** for everyone in their age groups. That is today's
   behaviour, not something this feature introduces.
2. `class-rest-people.php:2058` — the raw-SQL list endpoint — assembles `get_fields( $row->ID )`
   **wholesale** into `$person['acf']` and never passes through `rest_prepare_person`, so it is
   unredacted for *every* caller including plain members. The narrow field list at
   `class-rest-people.php:540` shows the pattern that endpoint should have followed.

Any solution that just adds `vrijwilligers` to the bypass list inherits both problems and makes the
first one club-wide. That is why §5.3 splits the axes instead.

### 2.5 Field-level write scoping already exists

`enforce_sponsor_manager_scope()` (`class-rest-people.php:1044`) is the precedent to copy: a
capability (`sponsorbeheer`) that grants person edit, narrowed on `rest_pre_insert_person` to a field
allowlist (`company_name`, `is_sponsor`, `sponsor_pass_variant`), returning `403` with a
`blocked_fields` payload. `block_former_member_edits()` uses the same hook and does a
`maybe_serialize()` diff to distinguish "sent unchanged" from "actually changing".

### 2.6 Reverse sync to Sportlink

`rondo-sync` `pipelines/reverse-sync.js` pushes Rondo Club → Sportlink for exactly:
`email_1`, `email_2`, `mobile_1`, `mobile_2`, `telephone_1`, `telephone_2`, the **Home** row of the
`addresses` repeater (`lib/detect-rondo-club-changes.js:47`), plus `datum-vog`, `freescout-id` and
`financiele-blokkade`.

This matters twice over:

- A coordinator fixing a phone number **lands in Sportlink**, which is the whole point — Sportlink is
  the club's system of record.
- The reverse-synced contact set and the write allowlist this plan proposes are the same set. That is
  not a coincidence; it is the reason the allowlist is drawn where it is.

### 2.7 Existing UI

- `src/pages/Vrijwilligers/VrijwilligersDienstForm.jsx` — the shift editor. Has an
  **"Aanmeldingen (n)"** panel that is remove-only. Copy literally says *"Leden melden zich aan via
  `/vrijwillig`. Hier kun je iemand handmatig verwijderen."*
- `src/pages/Vrijwilligers/VrijwilligersDiensten.jsx` — manage calendar + "Recente aanmeldingen".
- `src/pages/People/PersonDetail.jsx` — one `canEditPeople` boolean gates every edit affordance
  (`:163`), already with a sponsor-shaped exception. `ContactEditModal.jsx` exists and is already
  contact-scoped.
- `src/pages/Vrijwillig/Vrijwillig.jsx` — member self-service. Untouched by this work.

## 3. Why the generic REST route is not an option

Writing `assigned_persons` through `POST /wp/v2/dienst-shifts/{id}` skips every rule in §2.2 and
additionally:

- **Detaches the shift from its sjabloon.** `ShiftTemplateExpander::detach_on_manual_edit()` runs on
  `rest_after_insert_dienst_shift` and sets `_shift_customized = 1`. Adding one person would
  permanently exclude the shift from template re-rollout.
- Leaves `_shift_signup_at_` unset, so the added person **cannot cancel** and never appears in
  "Recente aanmeldingen".
- Sends no confirmation e-mail and no iCal attachment.
- Never flips `status` to `vol`, so an over-full shift keeps accepting self-service signups.
- Takes no write lock → a concurrent member signup silently overwrites the coordinator's write.

So: a dedicated endpoint mirroring `signup()`, plus a guard closing the generic route (§6.4).

---

## 4. Decisions & remaining questions

### 4.1 Decided (Joost, 2026-07-30)

| # | Question | Decision |
|---|---|---|
| Q1 | May the coordinator override a missing VOG/IVA? | **No.** Both stay hard blocks. The error names the certificate and links to the person. `shift.iva_waived` remains the only legitimate IVA exception. |
| Q3 | Distinct "je bent ingedeeld" mail, or reuse the confirmation? | **Reuse the bevestiging.** `queue_signup_confirmation()` as-is — batched, 10-minute delay, iCal. No new template fields. |
| Q4 | Assign to a past or `voltooid` shift? | **Blocked.** Future `open` / `vol` only. |
| Q7 | Picker scope? | **Coordinators see everyone.** `vrijwilligers` stops being age-group scoped. |
| Q9 | Separate `diensten_indelen` capability + `rondo_dienstplanner` role? | **Dropped.** Everything gates on `vrijwilligers`. No new capability, no new role, no dual code path. |
| Q10 | Accept the full-ACF widening that Q7 brings? | **No — fix the scoping properly.** Split "which people" from "which fields" (§5.3). Coordinators additionally get **contact-field write access** (§5.4). |
| Q14 | May a coordinator change a person's photo? | **Yes.** Logically photos are identity, not contact data — but practically a coordinator maintaining their players' records needs both. Photos join the coordinator grant (§5.4 item 5). |
| Q15 | Do coordinators get notes/timeline access along with club-wide visibility? | **No — and tighten it for everyone.** Person notes, activities and timeline become restricted to `ledenadministratie`, finance (`can_view_finances()`) and admins (§5.6). Coordinators never gain them; several roles that have them today lose them. |

### 4.2 Still open

| # | Question | Default I'd ship |
|---|---|---|
| Q2 | **May the added person cancel afterwards?** Within 21 days of the shift, `_shift_signup_at_ = now()` grants the 30-minute grace; outside that window they can cancel anyway. | **Yes, same rules as self-signup.** Nobody should be trapped in a dienst somebody else planned for them. |
| Q5 | **Over-capacity: refuse or allow deliberate over-book?** | **Hard refuse** ("verhoog eerst de capaciteit"). Capacity drives the calendar colouring. |
| Q8 | **Is `Inschrijftaak` the right word?** The UI calls both `dienst_type` and `dienst_shift` "Inschrijftaak". | Keep it. Separate cleanup. |
| **Q11** | **Which fields go in each sensitivity group (§5.3)?** Review already forced three corrections (finance keyed on `can_view_finances()`; `wacht_op_overschrijving` reclassified as membership-administration; support group opened to `ledenadministratie` so the board and membership desk keep FreeScout). Remaining debatables: `datum-overlijden`, `photo_gallery`, `nickname`, `pronouns`, `isparent`. Custom fields cannot be classified at all (§5.3 limitation). | Ship the three groups as corrected; leave everything else visible. Coordinators need `datum-vog`, `leeftijdsgroep`, `huidig-vrijwilliger` and `type-lid` to do their job, so those stay. |
| **Q12** | **May a coordinator edit *non-Home* address rows?** Only the Home row reverse-syncs (§2.6), so an edit to a second address silently diverges from Sportlink. | **Allow the whole `addresses` repeater** — restricting to one row inside a repeater is fiddly UI for a rare case. Accept the divergence; it already exists for full people managers. |
| **Q13** | **Should coordinator contact edits be attributed?** Person records have notes/activities via `CommentTypes`; contact writes currently leave no trace beyond `post_modified`. | **Out of scope for v1.** Worth doing for all editors at once rather than only this capability. |
| **Q16** | **Q15 fallout: may a plain member still read shared notes on their own record?** Today a scoped member passes `check_person_access` for their own person, so staff-written "shared" notes about them are readable by them. The Q15 predicate ends that. | **Let it end.** Staff notes about a member were almost certainly never meant for the member's eyes; this reads as a fix, not a regression. Flagged in case some workflow deliberately used notes as member-visible messages. |
| **Q17** | **Q15 fallout: fairplay loses notes.** FairPlay users handle discipline cases and plausibly log conversation notes on person records today. After Q15 they cannot read or write person notes. | **Ship as decided** (ledenadministratie + finance only), but verify with the FairPlay commissie before PR 1 deploys; if they turn out to live in notes, adding `fairplay` to the predicate is a one-word change. |

Assumptions I'm making without asking:

- Assignments stay person-scoped meta on the shift (Rule 0: no custom tables).
- The added person needs neither a Rondo account nor an e-mail address. Missing e-mail = no
  notification, surfaced in the response.
- Coordinators get **edit** on people, never **create** or **delete**.
- Dutch UI copy, English code comments. No new frontend dependency.

---

## 5. Access control model

### 5.1 No new capability

Per Q9, everything gates on the existing `vrijwilligers` capability, admins included via the standard
`|| current_user_can( 'manage_options' )`. `Base::check_vrijwilligers_permission()` already exists and
is reused unchanged. This removes the whole `diensten_indelen` / `rondo_dienstplanner` layer from the
earlier draft — no `BASE_ROLES` entry, no capability-matrix label, no second picker path.

### 5.2 What a coordinator gains

| May | May not |
|---|---|
| See every person in the club (§5.3) | See finance, support or sponsor-CRM fields (§5.3) |
| Edit contact fields on any person (§5.4) | Edit any other person field |
| Change a person's photo (Q14, §5.4 item 5) | Read or write person notes / activities / timeline (Q15, §5.6) |
| Add / remove shift assignees (§6) | Create or delete a person |
| Everything `vrijwilligers` already allowed | Everything else it already disallowed |

### 5.3 Split the two axes (Q7 + Q10)

The fix for Q10 is not a special case for `vrijwilligers` — it is separating the axes that §2.4 shows
are conflated. Two independent rules:

**Axis A — which people.** Unchanged three tiers. `vrijwilligers` is added to
`AGE_GROUP_BYPASS_CAPS`, so coordinators land in the "everyone" tier.

**Axis B — which fields.** New, capability-driven, applied to **every** caller regardless of scope:

```php
// class-access-control.php — group key is a *predicate*, not a raw capability
private const SENSITIVE_ACF_FIELD_GROUPS = [
    'finance'  => [ 'financiele-blokkade', 'nikki-contributie-status', '_nikki_*' ],  // prefix match
    'support'  => [ 'freescout-id', 'onboarding-email-lid-sent', 'onboarding-email-vrijwilliger-sent' ],
    'sponsor'  => [ 'sponsit_contact_id', 'sponsit_person_id' ],
];

public static function filter_sensitive_acf( array $acf, ?int $user_id = null ): array;
```

Group access predicates — keyed on functions, not raw caps, after review surfaced three
misclassifications:

- `finance` → `UserRoles::can_view_finances()`, **not** the raw `financieel_read` cap. The
  capability matrix can produce a `financieel`-only role; `can_view_finances()` already encodes
  "write implies read" (`class-user-roles.php:86`).
- `support` → `manage_options` **or `ledenadministratie`** — `rondo_bestuur` does not hold
  `manage_options` (`class-user-roles.php:74`), and keying FreeScout/onboarding purely on admin
  would blind the board and the membership desk to fields they use today.
- `sponsor` → `sponsorbeheer`.
- `wacht_op_overschrijving` is **not** in any group. It is a KNVB transfer flag — membership
  administration, not finance — and `rondo_ledenadministratie` renders it as a badge
  (`PeopleList.jsx:189`). Classifying it as finance would break the membership desk. It stays
  generally visible.

`manage_options` satisfies every predicate. Applied at both payload-assembly sites:

1. `filter_rest_single_access()` — `rest_prepare_person` fires per item, so this covers the single
   route *and* every collection item. Composes after the existing member allowlist; order is safe
   because `MEMBER_VISIBLE_ACF_FIELDS` already excludes all three groups.
2. `class-rest-people.php:2058` — the raw-SQL list. Wrap the wholesale `get_fields()` dump in
   `filter_sensitive_acf()`. **This is a pre-existing leak** (§2.4) that currently exposes finance
   flags to plain members through `/rondo/v1/people/filtered`; it needs fixing regardless of this
   feature and is the reason PR 1 ships on its own. While there, also apply
   `filter_member_visible_acf()` for `is_scoped_member()` callers — the raw-SQL route currently
   returns scoped members far more than the canonical allowlist does.

**Redacting the payload is not enough — gate the filter params too.** `/rondo/v1/people/filtered`
accepts `financiele_blokkade` and `wacht_op_overschrijving` as query filters with no capability
check (`class-rest-people.php:1434`, `:1454`). Anyone can ask "everyone with
`financiele_blokkade=1`" and read the flag off result membership, redaction or not. PR 1 must
ignore (or 403) a sensitive filter param when the caller fails that group's predicate — same for
the filter-options counts endpoint. Without this the leak is closed on one door and open on the
other.

**Known limitation:** admin-created custom fields (the customfields manager) ride along in
`get_fields()` and cannot be classified — a future "financiële notitie" custom field would be
coordinator-visible. Out of scope here; noted under Q11.

**Honest blast radius:** the narrowing is not just "age-group coordinators". Every bypass-cap role
that fails a group's predicate loses those fields — with the corrected predicates above that means
`fairplay`, `vog`, `toegangscontrole` and `manage_clothing` holders lose finance, support and
sponsor-CRM fields they can technically see today. Reviewed against actual UI usage: none of those
roles has a screen that renders them (the `financiele-blokkade` banner and `wacht_op_overschrijving`
badge are used by roles that keep access under the corrected predicates). Still: enumerate this in
the PR 1 changelog entry, because "strictly narrowing" is true of the code and understates the org
impact.

Why this is better than a fourth visibility tier:

- It **narrows** existing behaviour: management roles stop seeing sensitive fields their function
  doesn't need. A fourth tier would only have narrowed the new role.
- It keeps `can_view_person()` as the single authority on "which people", which its docblock
  explicitly asks for.
- Sensitivity becomes a property of the field, expressed once, instead of a property of the viewer
  repeated per tier.

**Second place to change:** `src/pages/Settings/Settings.jsx:3337` keeps a hand-copied
`MANAGEMENT_CAPS` array mirroring `AGE_GROUP_BYPASS_CAPS`, driving "granting a management capability
auto-clears that role's age-group restriction". Add `vrijwilligers` in the same commit or the admin UI
leaves stale, now-ignored age-group config in place.

**Also widens, intentionally:** `get_kaderlijst_people()` (`class-rest-api.php:59`) branches on the
same value, so coordinators get the club-wide kaderlijst. And `permitted_age_groups` on the
current-user payload flips to `null`, so the "Je ziet alleen leden uit de leeftijdsgroepen: …" banner
(`PeopleList.jsx:1386`) correctly disappears — no code change needed.

### 5.4 Contact-field write access (Q10)

Mirrors `enforce_sponsor_manager_scope()` exactly (§2.5) — capability grants the edit, pre-insert
guard narrows the fields.

```php
// class-access-control.php
public const CONTACT_WRITE_FIELDS = [
    'email_1', 'email_2',
    'mobile_1', 'mobile_2',
    'telephone_1', 'telephone_2',
    'addresses',
];

public static function can_edit_person_contact( $user_id = null ): bool;  // vrijwilligers, or any full editor
```

Exactly the reverse-synced set from §2.6, so a coordinator's correction reaches Sportlink.

1. **`can_edit_person()`** gains a `vrijwilligers` branch so `restrict_person_editing()`
   (`map_meta_cap`) stops mapping `edit_post` to `do_not_allow`. `can_delete_person()` is
   **not** touched — no delete.
2. **`can_edit_people()` stays as it is.** It means "full people manager" and is read by the sponsor
   guard and the frontend; widening it would grant everything. This is the single most important line
   in this section to get right in review.
3. **`UserRoles::cpt_capabilities()`** gains an `'edit'` level returning read + `edit_*` primitives
   but **not** `create_posts`, `publish_posts` or any `delete_*`. `sync_role_capabilities()` grants
   `cpt_capabilities( 'person', 'edit' )` when the role holds `vrijwilligers`.
4. **One unified field-scope guard, not a second parallel one.** Review caught a deadlock in the
   "mirror the sponsor guard" approach: `enforce_sponsor_manager_scope()` fires for any
   `sponsorbeheer` holder without `can_edit_people()` and 403s **every** edit to a non-sponsor
   person (`class-rest-people.php:1082`). A role holding both `sponsorbeheer` and `vrijwilligers` —
   trivially creatable in the capability matrix — would get 403 on contact edits from the sponsor
   guard and 403 on sponsor edits from the volunteer guard: the intersection of two independent
   allowlists is empty. So instead of adding a sibling, **refactor to a single
   `enforce_person_field_scope()`** that replaces `enforce_sponsor_manager_scope()`:
   - Full editors (`can_edit_people()`) bypass entirely, as today.
   - Otherwise the allowed set is the **union** of what the user's capabilities grant:
     `CONTACT_WRITE_FIELDS` for `vrijwilligers`, the sponsor field set for `sponsorbeheer` (with the
     existing create-path and dual-role rules preserved verbatim).
   - Runs on `rest_pre_insert_person` **after** `block_former_member_edits` (priority 10) so
     oud-leden stay read-only; the exact number is irrelevant — later filters receive the
     `WP_Error` — but the guard must start with an `is_wp_error( $prepared_post )` early return,
     which the sponsor guard already has and the plan's earlier draft forgot to require.
   - Uses the same `maybe_serialize()` diff as `block_former_member_edits()` so a client that
     round-trips the full `acf` object is judged on what it *changes*, not what it sends — the
     codebase's own `sanitizePersonAcf` round-tripping habit (see CLAUDE.md) makes this mandatory.
   - **Also rejects core post-field changes** (`status`, `title`, `author`, `slug`) for non-full
     editors: with `edit_post` granted, a scoped editor could otherwise `PUT {"status":"draft"}` and
     make a person vanish from every `post_status => publish` query in the app. This hole exists
     today for sponsor managers; the refactor closes it for both rather than handing it to a much
     larger audience.
   - Violations → `403 rondo_person_field_scope` with `blocked_fields`.
5. **Photos are in the coordinator grant (Q14), and every other custom person route must be
   audited.** The photo route (`POST /people/{id}/photo`) gates on `check_person_edit_permission()`
   → `can_edit_person()` (`class-rest-base.php:168`), so it inherits the new `vrijwilligers` branch
   automatically — which is now the *intended* behaviour, per Q14: a coordinator maintaining their
   players' records updates photos as routinely as phone numbers. No code change needed on that
   route; add `photo_gallery` and the featured-image path to what the docs describe as the
   coordinator grant, and gate the frontend photo-edit affordance on
   `canEditPeople || canEditContact`. The remaining custom person writes (`sync-from-sportlink`,
   onboarding-email, bulk endpoints) still need the audit — each one reading `can_edit_person()`
   silently inherits the branch, and for those the answer is *not* obviously yes.
6. **Frontend.** Expose `can_edit_person_contact` on the current-user payload. `PersonDetail.jsx`
   gains `canEditContact` next to `canEditPeople` (precedent: `canEditSponsorFields` at `:164` —
   and note CLAUDE.md's former-member section says "don't introduce a parallel can-edit boolean";
   PR 5 must amend that rule to name both sanctioned exceptions, or the next agent will refuse this
   pattern). The contact card and `ContactEditModal` gate on `canEditPeople || canEditContact`,
   everything else stays on `canEditPeople`. The `former_member` read-only rule already flips
   `canEditPeople` to `false` at `:1205` — `canEditContact` must be forced `false` there too, or
   the coordinator gets an edit button the server will refuse.

### 5.5 Board vs coordinator split

- **Coordinator** (`vrijwilligers`) — plans and fills diensten, sees the whole club, fixes contact
  details and photos.
- **Board / admin** (`manage_options`) — no-shows, fines, capability matrix, age-group access,
  support internals.

Finance stays with `financieel` / `financieel_read`; sponsor CRM with `sponsorbeheer`. Q10 is
what keeps those boundaries intact while Q7 opens the person list.

### 5.6 Notes, activities and timeline become a capability surface (Q15)

Today `/people/{id}/notes`, `/activities` and `/timeline` (plus note/activity create) all gate on
`CommentTypes::check_person_access()` (`class-comment-types.php:259`) — "can view the person = can
read and write their notes". With Q7 that would have handed coordinators every note in the club,
and field redaction cannot reach prose. Q15's decision: notes are not a visibility side effect but
their own surface.

```php
// class-access-control.php
public static function can_access_person_notes( $user_id = null ): bool {
    return user_can( $user_id, 'manage_options' )
        || user_can( $user_id, UserRoles::LEDENADMINISTRATIE_CAPABILITY )
        || UserRoles::can_view_finances( $user_id );
}
```

Changes:

1. `CommentTypes::check_person_access()` requires `can_access_person_notes()` **in addition to**
   `user_can_access_post()` — the person-visibility check stays, so a finance user still can't read
   notes on a person they can't see (irrelevant today since finance bypasses scoping, but keeps the
   rule composable).
2. `check_comment_access()` (update/delete own note) is left as is — author-or-admin. A user who
   loses the capability keeps no route to their old notes anyway, since update/delete of things
   they can no longer list is moot; no data is deleted.
3. **Frontend:** expose `can_access_person_notes` on the current-user payload. `PersonDetail.jsx`
   renders the timeline section and note composer only when it's true — today `usePersonTimeline`
   fires unconditionally (`PersonDetail.jsx:120`) and would otherwise 403 for every coordinator,
   fairplay and VOG user on every person view.
4. **Mentions:** `@mention` notifications link to the note on the person page; a mentioned user
   without notes access now gets a link they cannot open. Accepted for v1 — mentioning someone
   outside ledenadministratie/finance was already mentioning someone who couldn't act on it — but
   the mention-autocomplete should ideally filter to users passing `can_access_person_notes()`.

**Who loses access, explicitly** (all read *and* write): `fairplay` (Q17 — verify before deploy),
`vog`, `toegangscontrole`, `manage_clothing`, `sponsorbeheer` holders; age-group coordinators; and
scoped members reading shared staff notes on their own record (Q16 — reads as a fix). Existing
notes are untouched in the database; they simply become invisible to those roles. This is the
biggest *narrowing* in the plan and belongs in PR 1's changelog entry in plain Dutch.

---

## 6. Backend

New code in `includes/class-rest-member-shifts.php` — the class already owns `assigned_persons`
mutations, the write lock and the eligibility helpers. Reuse over a new class, per Rule 3.

### 6.1 `POST /rondo/v1/shifts/{id}/assignees`

Permission: `check_vrijwilligers_permission()`.

Request: `{ "person_id": 123, "force_overlap": false }`

Validation, in order — steps 1–5 and 8 **outside** the write lock, mirroring `signup()`, which
deliberately keeps eligibility/VOG/pool/overlap checks out of the critical section (the overlap
check alone is a serialized-meta LIKE scan; holding a 10-second-timeout lock through it lengthens
contention for no correctness gain). Only steps 6–7 — the read-modify-write — run inside
`with_shift_write_lock()`, which re-checks status and capacity against fresh meta:

1. Shift exists and is a `dienst_shift`; `status` ∈ `open`, `vol` (else `409 shift_closed`).
2. `start_datetime` in the future (else `409 shift_already_started`) — Q4.
3. Person exists and is a published `person` (else `404 invalid_person`).
4. `may_volunteer()` (else `403 not_eligible`).
5. VOG / IVA / pool via a helper extracted from `signup()` — one function, both callers, so member
   and coordinator rules can never drift (Rule 3). Errors: `403 vog_required`, `403 iva_required`,
   `403 pool_membership_required`. No override (Q1).
6. *(in lock)* Already assigned → `200 { already_assigned: true }` (idempotent; note `signup()`
   calls its equivalent key `already_signed_up` — different endpoint, deliberately different word).
7. *(in lock)* Capacity → `409 shift_full` (Q5); status re-checked.
8. Overlap → `409 overlap_warning` with `overlap_shift` + `can_force: true`; repeat with
   `force_overlap: true` proceeds.

Writes:

```php
update_post_meta( $shift_id, 'assigned_persons', $assigned );
update_post_meta( $shift_id, '_shift_signup_at_' . $person_id, time() );          // Q2
update_post_meta( $shift_id, '_shift_assigned_by_' . $person_id, get_current_user_id() );
update_post_meta( $shift_id, '_shift_assigned_at_' . $person_id, time() );
GuardianAccountService::mark_shift_signup( $shift_id, $person_id, get_current_user_id() );
ShiftEmailScheduler::queue_signup_confirmation( $person_id, $shift_id );          // Q3
// flip status to 'vol' when full
VolunteerObligationCalculator::invalidate_cache();
do_action( 'rondo_shift_assignee_added', $shift_id, $person_id, get_current_user_id() );
```

Response:
```json
{ "shift_id": 42, "person_id": 123, "assigned": true,
  "status": "vol", "assigned_count": 2, "capacity": 2,
  "notification": { "queued": true, "reason": null } }
```
`notification.reason` is `no_email` when `get_person_email()` is empty, so the UI warns instead of
implying a mail went out.

### 6.2 `GET /rondo/v1/shifts/{id}/assignable-people?search=jan`

Permission: `check_vrijwilligers_permission()`.

With Q7 in place, `/rondo/v1/search` would find the people — but not answer *"can this person take
**this** shift"*. That is what this endpoint is for, and why it survives Q9's simplification.

- Query on `person`, **no `suppress_filters`** — coordinators are unscoped now, so the normal
  filters return the whole club and the flag would only mask future scoping bugs. Note: one
  `WP_Query` cannot OR a meta LIKE (`first_name`/`last_name`) with a title search —
  `global_search()` needs four sequential `get_posts` calls for exactly this reason
  (`class-rest-api.php:786`). Reuse that pattern (first-name, last-name, title passes, merged and
  capped) rather than pretending it's one query.
- `search` required, min 2 chars. Max 25 results, `post_status => publish`, `former_member`
  excluded.
- Payload per person — deliberately minimal after a perf review:
  ```json
  { "id": 123, "name": "Jan Jansen", "leeftijdsgroep": "Senioren",
    "already_assigned": false, "blocked": false, "block_reason": null }
  ```
  The first draft also returned `obligation` and `has_overlap` per result. Cut: `has_overlap` runs
  `query_shifts_for_person()` — a serialized-meta LIKE scan — per result, 25 scans per keystroke,
  violating §7.3's own rule; and the obligation calculator's cache is invalidated on every signup,
  so it would recompute exactly during busy signup periods. The POST already 409s on overlap with
  a proper confirm flow, and obligation status is visible on the person page. If the picker later
  needs a nudge signal ("this person still owes 2 shifts"), design it against the cached
  eligibility view, not per-keystroke.
- `block_reason` reuses `member_shift_block_reason()` (cheap: a handful of meta reads on the
  already-loaded dienst type), so the picker greys out exactly what the POST would refuse. Blocked
  people are **shown, disabled, with the reason** — a coordinator needs to know *why* they can't
  add Jan, or they'll conclude the search is broken.
- Names via `GuardianAccountService::display_name_for_person()`, not `get_the_title()`, so
  guardian-held youth accounts render correctly.

### 6.3 `DELETE /rondo/v1/shifts/{id}/assignees/{person_id}` (existing)

Permission already `manage_options || vrijwilligers` — unchanged by Q9. Clear the new
`_shift_assigned_by_` / `_shift_assigned_at_` meta in `remove_assignee()`.

### 6.4 Close the generic write path

Mirroring `ShiftCancellationService::prevent_direct_rest_cancellation()`:

```php
add_filter( 'rest_pre_insert_dienst_shift', [ $this, 'prevent_direct_assignee_writes' ], 10, 2 );
```

Reject any `POST /wp/v2/dienst-shifts/{id}` whose `acf.assigned_persons` / `meta.assigned_persons`
**differs from stored**, with `403 rondo_use_assignee_endpoint`. Without this the new endpoint is a
suggestion rather than a gate. Two comparison rules, both load-bearing:

- Compare against stored values rather than "is present", so a future round-tripping caller isn't
  broken by an unchanged array — `VrijwilligersDienstForm.jsx:263` sends an explicit field list
  today, but that is a habit, not a guarantee.
- **Normalize both sides to `int[]` before comparing.** `assigned_persons` is both registered meta
  and an ACF relationship field, and REST clients deliver IDs as strings;
  `maybe_serialize(['5']) !== maybe_serialize([5])`, so a naive serialize-diff would 403 an
  unchanged round-trip. `array_map('intval', …)` + `array_values()` both sides, then compare.

### 6.5 Audit trail

No generic audit log exists for volunteer CPTs (`CommentTypes` is person-scoped). Attribution is
meta-based and already half-built: `_shift_signup_user_{person}`. This plan adds
`_shift_assigned_by_{person}` + `_shift_assigned_at_{person}`. Yes, `_shift_assigned_at_` duplicates
`_shift_signup_at_` at write time — deliberately: `signup_at` is deleted on cancel (it doubles as
the grace-period timer), while `assigned_at` survives as the audit record. It surfaces both in the
assignee panel
("Ingedeeld door Piet op 12-08-2026") and alongside `assigned_person_names` as
`assigned_person_sources`, and fires `rondo_shift_assignee_added` so a real audit log can hook in
later. Contact-edit attribution is explicitly deferred (Q13).

---

## 7. Data & migrations

**No new tables, no new CPT, no ACF change.**

New post meta (unregistered/internal, like the existing `_shift_signup_*` keys, so they stay out of
REST): `_shift_assigned_by_{person_id}`, `_shift_assigned_at_{person_id}`.

New PHP constants/predicates, no storage: `SENSITIVE_ACF_FIELD_GROUPS`, `CONTACT_WRITE_FIELDS`,
`can_access_person_notes()`, the added `AGE_GROUP_BYPASS_CAPS` entry. Notes data is untouched —
Q15 changes who may read it, not what is stored.

Migrations:

1. **Capability backfill** — `ROLES_VERSION` 4 → **5**, with a `maybe_upgrade_roles()` branch adding
   `cpt_capabilities( 'person', 'edit' )` to every role holding `vrijwilligers`. `add_role()` is a
   no-op on existing installs, so without this the person-edit primitives never reach production.
   This is the only stateful change.
2. **No data backfill.** Historic assignments have no `_shift_assigned_by_` meta and render as
   "Aangemeld via /vrijwillig". Stale `rondo_age_group_access` entries for coordinator roles simply
   stop being consulted.
3. **Indexing** — nothing new. Candidate search hits the indexed `postmeta.meta_key` path per field
   (the pattern `collect_iva_person_ids()` adopted after a production timeout on an OR query).
   `assigned_persons` stays a serialized `LIKE` lookup in `query_shifts_for_person()`; do **not** add
   a new query that `LIKE`-scans it across all shifts.

## 8. Edge cases

| Case | Behaviour |
|---|---|
| Person already assigned | `200 { already_assigned: true }`, no duplicate, no second mail. |
| Shift full | `409 shift_full`; picker disables Add first. |
| Shift `voltooid` / `geannuleerd` / already started | `409`. |
| Overlapping assignment | `409 overlap_warning` → confirm → retry with `force_overlap`. |
| Coordinator adds themselves | Allowed. Blocking it just sends them to `/vrijwillig` in another tab. |
| No VOG / IVA / not in required pool | Hard block; error names the reason, panel links to the person. |
| Youth person | Allowed if `may_volunteer()` passes; guardian-held names resolve via `display_name_for_person()`. |
| Person has no e-mail | Assignment succeeds, `notification.reason = "no_email"`, UI says so. |
| Person has no Rondo account | Assignment succeeds; mail goes to `email_1`. |
| `former_member = true` | Refused by `may_volunteer()`, filtered from the picker, **and** contact edits stay blocked by `block_former_member_edits()` — which is why `enforce_volunteer_manager_scope` must run after it, and why `canEditContact` must be forced `false` at `PersonDetail.jsx:1205`. |
| Coordinator edits a non-contact field | `403 rondo_volunteer_manager_scope` with `blocked_fields`. |
| Coordinator round-trips the full `acf` object unchanged | Allowed — the `maybe_serialize()` diff sees no change outside the allowlist. |
| Coordinator edits a non-Home address row | Allowed (Q12); silently does not reach Sportlink. |
| Coordinator tries to create or delete a person | Refused — no `create_*` / `delete_*` primitives. |
| Coordinator was previously age-group scoped | Now sees everyone, minus the sensitive groups. Their `rondo_age_group_access` entry becomes dead config. |
| Coordinator opens a person page | No timeline section, no note composer (Q15) — the section is hidden, not erroring. |
| Coordinator changes a photo | Allowed (Q14) via the existing photo route; frontend affordance gated on `canEditPeople \|\| canEditContact`. |
| Fairplay/VOG user opens a person they could already see | Person still visible; notes/timeline gone (Q15/Q17). PR 1 changelog must say so. |
| Member opens their own profile | Shared staff notes about them no longer visible (Q16). |
| User is @mentioned in a note but lacks notes access | Notification links to a note they cannot open — accepted for v1; filter mention-autocomplete later (§5.6 item 4). |
| Plain member hits `/rondo/v1/people/filtered` | Now redacted (§5.3 item 2) — a fix, and a payload change the frontend must tolerate. |
| Two coordinators add simultaneously | `with_shift_write_lock()` serializes; lock busy > 10s → `503 shift_busy`. |
| Coordinator adds, member cancels a minute later | Allowed (Q2); the queued mail is discarded by `discard_signup_confirmation()`. |
| Shift rolled out from a sjabloon | Unaffected: no `_shift_customized` set, and `rerun_template()` already preserves shifts with assignees. |
| Obligation credit | Automatic — the calculator reads `assigned_persons` and doesn't care who wrote it. Cache invalidated on write. |

## 9. Testing strategy

`tests/Wpunit/`, Codeception, `RondoTestCase`. The suite is largely stale (118/153 red) — the
volunteer shift tests are not, and must stay green.

**`AgeGroupAccessTest` — extend, don't expect red.** CLAUDE.md singles it out as the one green test
guarding person visibility. Its existing assertions never exercise a `vrijwilligers`-holding role,
so adding the cap to `AGE_GROUP_BYPASS_CAPS` should leave them green; the work is additive. New
assertions (and note #2's trap: **set the sensitive meta on the fixture person first**, or the
exclusion assertions pass vacuously):

1. A `vrijwilligers` user sees a person outside their configured age groups.
2. That user's payload **excludes** `financiele-blokkade`, `_nikki_*`, `freescout-id`, `sponsit_*` —
   on a fixture that actually has those fields populated.
3. A `financieel_read` user still sees the finance group; a `financieel`-only user too (pins the
   `can_view_finances()` predicate from §5.3); an admin sees all groups.
4. A `ledenadministratie` user still sees `wacht_op_overschrijving` and the support group — pins
   both §5.3 classification decisions.
5. An age-group coordinator **without** finance capabilities no longer sees finance flags — the
   deliberate narrowing; assert it so nobody "fixes" it back.
6. `/rondo/v1/people/filtered` (raw SQL) redacts identically to `wp/v2/people`, **and** ignores/403s
   a `financiele_blokkade` filter param from an uncapable caller.
7. A plain `rondo_user` and a member remain household-scoped.

**New: `tests/Wpunit/PersonContactScopeTest.php`**

1. `vrijwilligers` can update `email_1`, `mobile_1` and `addresses`.
2. The same user gets `403` + `blocked_fields` on `type-lid`, `financiele-blokkade`, `birthdate`.
3. The same user gets `403` on a core-field write — `{"status":"draft"}`, `{"title":"x"}` — with no
   `acf` in the request (§5.4 item 4).
4. **A user holding both `sponsorbeheer` and `vrijwilligers` can edit contact fields AND sponsor
   fields** — the assertion that forces the unified-guard design and fails on the old
   two-guard deadlock.
5. Round-tripping a full unchanged `acf` object succeeds.
6. Cannot create or delete a person; POST to the photo route **succeeds** (Q14 — photos are in the
   coordinator grant).
7. A `former_member` record refuses even contact edits — and the photo route too.
8. `can_edit_people()` still returns `false` for `vrijwilligers` — the guard-rail assertion for §5.4
   item 2.
9. Capability-matrix round-trip: toggling `vrijwilligers` onto a custom role adds the person-edit
   primitives via `sync_role_capabilities()`; untoggling removes them.

**New: `tests/Wpunit/PersonNotesAccessTest.php`** (Q15)

1. `ledenadministratie`, `financieel`, `financieel_read` and admin users can list, create and read
   notes/activities/timeline on a person.
2. `vrijwilligers`, `fairplay`, `vog`, `sponsorbeheer` and plain `rondo_user` accounts get `403` on
   all three routes — read and create.
3. A scoped member gets `403` on their **own** person's timeline (Q16).
4. `check_comment_access` still lets a note's author update/delete it only via author-or-admin —
   unchanged behaviour, pinned.

**New: `tests/Wpunit/ShiftAssignmentByCoordinatorTest.php`**

Capacity / VOG / IVA / pool / overlap / status / past-shift refusals; idempotency; side effects
(`_shift_signup_at_`, `_shift_assigned_by_`, queued mail, cache invalidated, action fired);
`prevent_direct_assignee_writes` blocking a changed array, allowing an unchanged one, **and**
allowing a string-vs-int unchanged round-trip (`['5']` vs `[5]` — the normalization in §6.4); and
that adding an assignee does **not** set `_shift_customized`.

**Extend:** `SecurityAuthorizationTest` (the widened person caps against every route),
`MemberShiftLifecycleTest` (coordinator adds → member cancels in grace),
`VolunteerShiftCapacityTest`, `VolunteerShiftAttributionTest`.

**Manual on production after deploy** (no automated E2E harness):
1. As `rondo_vrijwilligers`: open a member outside your old age group — visible, no finance fields,
   **no timeline section, no note composer**.
2. Fix their mobile number and photo → saves; confirm the reverse sync picks the number up on the
   next run.
3. Try to change `type-lid` → refused, with a sensible message.
4. Add a member without IVA to a bardienst → blocked with the right reason.
5. Add a valid member → appears immediately, count updates, calendar recolours, mail with iCal
   arrives.
6. Member logs into `/vrijwillig` → sees the dienst, can cancel it.
7. As a `ledenadministratie` or finance user: notes/timeline still work fully on any person.
8. As a fairplay user: person pages render cleanly without the timeline section (no 403 toast).
9. As a plain member: `/profile` and PeopleList still render; own timeline section gone (Q16).

## 10. Rollout

No feature flag — the repo does hard cutovers (`docs/prd/acf-removal-hard-cutover-plan.md`).

1. **PR 1 — the narrowing bundle, alone.** `SENSITIVE_ACF_FIELD_GROUPS` + `filter_sensitive_acf()`
   at both payload sites + filter-param gating + **the notes restriction (§5.6)** +
   `AgeGroupAccessTest` extension + `PersonNotesAccessTest`. Ships **before** any widening, so both
   redaction and the notes gate are already in place when coordinators are unscoped — and it fixes
   the pre-existing `/rondo/v1/people/filtered` leak (§2.4) on its own merits. Strictly narrowing;
   the risks are a frontend screen that silently depended on a field it shouldn't have had, and the
   Q17 fairplay check. The changelog entry must name both narrowings in plain Dutch.
2. **PR 2 — visibility widening.** `AGE_GROUP_BYPASS_CAPS` + `Settings.jsx` `MANAGEMENT_CAPS` mirror.
   One line of production behaviour reaching people and kaderlijst — notes are already gated by
   PR 1, so this no longer touches them. Isolated so it can be reverted without touching anything
   else.
3. **PR 3 — contact write scope.** `can_edit_person_contact()`, `cpt_capabilities( 'person', 'edit' )`,
   `ROLES_VERSION` 5, the unified `enforce_person_field_scope()` (absorbing the sponsor guard),
   the custom-route audit (photos deliberately inherit, Q14), `PersonContactScopeTest`, frontend
   `canEditContact` + photo affordance gating.
4. **PR 4 — the diensten feature.** The three endpoints, `prevent_direct_assignee_writes`, assignee
   panel Add affordance + picker modal, `ShiftAssignmentByCoordinatorTest`.
5. **PR 5 — docs.** New `../developer/src/content/docs/features/volunteer-shifts.md` (Rule 6 — the
   dienst system is undocumented there; only `taakuitleg.md` exists), the two-axis model written up in
   `features/access-control.md`, API reference entries.

Verify each PR on production before starting the next — PRs 1–3 change behaviour for every
coordinator in the club, and only PR 4 is the feature anyone asked for.

**Versioning:** every `main` merge deploys (Rule 8) and Rules 1–2 want a version + changelog per
milestone, so the "one bump at the end" idea doesn't fit this repo. Version each PR: 1–3 as
`33.78.x` patches with `### Changed`/`### Fixed` entries (PR 1's entry must plainly say **both**:
finance fields are no longer visible without finance rights, **and** notities/tijdlijn on
personen are now only for ledenadministratie en financiën — fairplay and VOG users will notice the
second one immediately), and PR 4 as **33.79.0** with the `### Added` feature entry in Dutch.

**Who is affected on deploy — name all of them:** every role holding `vrijwilligers` gets the full
package, which is `rondo_vrijwilligers`, `rondo_bestuur`, **and `rondo_iva_approver`** — the
kantine board member gains club-wide person visibility and contact-write too. Intended (they hold
`vrijwilligers` today for the IVA screens), but comms must not say "coordinators" and quietly mean
three roles.

**Comms:** the added member receives the existing bevestigingsmail ("Bevestiging: <dienst> op
<datum>") — accurate enough for something arranged in person, and no new copy to write (Q3).
Coordinators, board and IVA-approvers need one paragraph: *"je ziet nu alle leden, je kunt
contactgegevens en foto's bijwerken, en je kunt iemand zelf indelen; VOG en IVA blijven verplicht;
notities blijven voorbehouden aan ledenadministratie en financiën."* FairPlay and VOG users need a
separate heads-up about losing notes (Q17) **before** PR 1 lands, not after. Nothing changes for
members on `/vrijwillig`.

**Rollback:** revert the merge, use the *Roll back production* workflow. Reverting PR 3 is safe
against writes: `can_edit_person()` loses the `vrijwilligers` branch, so `restrict_person_editing()`
maps `edit_post` back to `do_not_allow` regardless of the stale role primitives — the residue is
only the plural `edit_rondo_persons` primitive lingering on roles, which passes some collection-level
checks (`context=edit` list requests) but no per-record write. Still clean it up: `ROLES_VERSION`
doesn't downgrade, so run a one-off `sync_role_capabilities()` over the affected roles after a
revert. Worth a note in the PR description.

## 11. Effort

**L (6–9 focused days).** Q9 removed a layer; Q10 added a bigger one, and the adversarial review
added the filter-param gating, the unified field guard (the sponsor-guard refactor is the price of
not deadlocking dual-capability roles) and the custom-route audit. The diensten feature itself is
now clearly the smaller half of the work.

| Slice | Size |
|---|---|
| Field-sensitivity layer + filter-param gating + notes restriction + tests (PR 1) | M |
| Visibility widening + `MANAGEMENT_CAPS` mirror (PR 2) | XS |
| Contact write scope: caps level, `ROLES_VERSION` 5, unified field guard (absorbs sponsor guard), custom-route audit, frontend gating (PR 3) | M–L |
| Three endpoints + eligibility helper extraction + `prevent_direct_assignee_writes` (PR 4) | M |
| Frontend picker + assignee panel (PR 4) | S–M |
| Tests across all four | M |
| Docs (feature doc from scratch + access-control model + API reference) | S |

If this is too much at once, **PRs 1–2 stand alone and are worth shipping on their own merits** —
they close a live data leak and unblock the coordinator's day-to-day work. PR 4 without PR 3 also
works: coordinators could fill diensten but still not fix a phone number.
