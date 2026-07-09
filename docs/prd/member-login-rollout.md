# Member login rollout

**Status:** draft plan, 2026-07-09
**Question:** can every Rondo member log in? No — 9 of the 739 people who need to can.

## Who actually needs a login

The population is not "members". It is **the people who owe a volunteer obligation**, because
that is what `/vrijwillig` exists to serve. `VolunteerEligibilityService` already models this as
two unit kinds:

- **SPELER** — one obligation per O17+ player. The player acts for themselves.
- **GEZIN** — one obligation per household with a JO16- player. *The parents act, not the child.*
  The code comment says it outright: "in te vullen door één of meerdere ouders samen".

Measured on production (2026-07-09), current season:

| | count |
|---|---|
| Obligation units | 738 (343 gezin + 395 speler) |
| **Distinct people who must be able to act** | **739** |
| — spelers (O17+ players) | 395 |
| — parents carrying ouderplicht | 361 |
| — people who are both | 17 |
| Of those 739, have a WordPress account today | **9** |

## The finding that inverts the obvious plan

Parents are mostly **not KNVB members**.

| Actor group | n | has email | has KNVB-ID | has birthdate |
|---|---|---|---|---|
| Spelers | 395 | 395 | 395 | 395 |
| Parents | 361 | 305 | 88 | 88 |

The 273 parents without a KNVB-ID also have no birthdate — across the whole `person` CPT the two
fields are missing from *exactly* the same 328 active records. Those records are parents, sponsors
and external contacts, not Sportlink members.

Two consequences:

1. **Scoping login to "members aged 16+" is wrong.** It would hand accounts to 676 members while
   locking out the 273 parents who carry the ouderplicht for their children.
2. **Keying activation on KNVB-ID + birthdate is wrong.** It excludes those same 273 people.
   The only identifier a parent reliably has is an **email address**.

## The good news

The obligation machinery is already parent-aware. `get_eligible_unit_for_person()` walks
`find_youth_children()` and returns the merged gezin unit for a non-playing adult, with multi-child
scaling applied. `/vrijwillig` resolves the logged-in user to a person via `rondo_linked_person_id`
and asks for that unit. **A parent who has an account already sees and can claim the family's
diensten today.** Nothing in the sign-up path needs to change.

The gap is entirely **identity and provisioning**, not volunteer logic.

## Decisions taken (2026-07-09)

1. **A playing parent owes both duties** — the speler obligation *and* the gezin obligation.
2. **A parent may see their child's person record.**
3. **Anyone in the system may activate an account.** Willingness to volunteer is not gated on owing
   an obligation.
4. **Shift attribution is a fixed order: the speler duty fills first, then the gezin duty.** A shift
   discharges exactly one unit. No member-facing choice, no new UI.

Each of these collides with something in the current code. See blockers 0, 5, 6 and 7.

## Blockers, in order of severity

### 0. SECURITY — any logged-in user can read all 4,095 person records — **FIXED in 33.28.2**
`AccessControl::filter_rest_query()` honours a `suppress_age_group` request parameter with no
capability check at all — the only condition is `is_user_logged_in()`:

```php
if ( $post_type === 'person' && $request->get_param( 'suppress_age_group' ) && is_user_logged_in() ) {
    self::$suppress_age_group_filter = true;
}
```

Reproduced on production as `borre.valk`, a plain `rondo_user` with no capabilities:

| request | result |
|---|---|
| `GET /wp/v2/people` | 200, `X-WP-Total: 0` |
| `GET /wp/v2/people/{id}` | 403 |
| `GET /wp/v2/people?suppress_age_group=1` | **200, `X-WP-Total: 4095`, ACF included (names, emails)** |

Only `src/pages/Teams/Kaderlijst.jsx` passes the flag legitimately. Today this is a **live privilege
escalation** for the four coordinator accounts, whose `rondo_age_group_access` restriction to one
age group they can shed at will. The moment members activate, it becomes a full member-database
disclosure — an AVG breach involving minors. `self::$suppress_age_group_filter` is also a static
that is never reset once set.

**Fixed in 33.28.2.** The flag is now honoured only for users whose `get_permitted_age_groups()`
returns a *non-empty* list — coordinators, the Kaderlijst case it was built for. Management users
are unrestricted already, so it stays a no-op for them; users with an empty list ("see nobody")
have the flag ignored. Verified on production after deploy:

| user | `suppress=0` | `suppress=1` |
|---|---|---|
| plain member (`borre.valk`) | 0 | **0** (was 4095) |
| coordinator (`jasper.jansen`) | 252 | 4095 |
| admin (`joost`) | 4095 | 4095 |

`/rondo/v1/people/filtered` reads the same static but nothing ever sets it on that route
(`filter_rest_query` is hooked on `rest_person_query`, which only fires for `wp/v2/people`), so it
always applied the age filter and was never exposed.

**Residual, accepted for now:** a coordinator can still widen to all 4,095 records with full ACF —
that is precisely what the flag was built to do, because `Kaderlijst.jsx` fetches *every* person
(`_fields=id,acf`, all pages) and filters client-side. Four trusted accounts. The proper fix is to
give Kaderlijst a scoped endpoint returning only people with a current `work_history` functie, with
only the fields it renders, and then delete the flag entirely. Tracked as item 12.

### 1. No way to create 730 accounts
`provision()` is called one `person_id` at a time from an admin picker, sends its welcome email
inline, and — via `/rondo/v1/users/provisionable` — filters to people who *have a KNVB-ID*. That
filter alone hides every non-member parent. There is no bulk route and no self-service route.

### 2. Shared email addresses
WordPress enforces one account per `user_email`. Parents share addresses with each other and with
their children. Within the 16+ member cohort only 50 people across 24 addresses collide, but once
parents are included the collisions multiply, because a gezin is by definition a shared mailbox.

### 3. 56 parents have no email address at all
They cannot be reached by any activation flow. Ledenadministratie must collect these.

### 4. 27 gezin units have no parent (`data_quality=orphan`)
A youth player with no `relationships` entry and no adult housemate. Nobody can fulfil the
obligation. Already surfaced in `diagnostics.gezinnen_orphan`; needs chasing, not code.

### 5. Playing parents are counted in two units at once — a live bug
17 people are both an O17+ player and the parent of a JO16- player. For them, both code paths agree
that they are a speler, and *both also* build a gezin unit from their child. The result:

- `compute_eligibility_view()` emits **two** units containing the same adult — `speler-{id}` and
  `gezin-rel-{id}` — so the club's obligation total double-counts the household. Lennart Nieuwenhuis
  (person 52) shows as speler (2 diensten) **and** as a 2-child gezin (3 diensten): 5 in total.
- `get_eligible_unit_for_person()` returns a **single** unit and hits `return build_speler_unit()`
  before it ever calls `find_youth_children()`. So `/vrijwillig` shows Lennart 2 diensten.
- `compute_progress()` matches shifts against `unit['person_ids']`, so every shift Lennart claims
  counts toward **both** units.

Verified on production: person 25 (gezin required 2 / speler 2), person 52 (3 / 2), person 115
(2 / 2). Where the numbers coincide the bug is silent; where they don't, the member's page
under-states what the club believes the household owes.

Today this is nearly invisible — 9 people have accounts. The moment 739 log in, 17 households see a
number that contradicts the coordinator's dashboard.

**Decision 1 says the parent owes both.** That makes the dashboard's two units correct and exposes
two separate defects:

- `get_eligible_unit_for_person()` must return **all** units a person belongs to, not the first one.
  `/vrijwillig` must render more than one obligation.
- ~~**Double-crediting silently discounts the duty.**~~ **Fixed in 33.29.1.** `compute_progress()`
  credited a shift to every unit whose `person_ids` contain the actor, so Lennart's 2 + 3 = 5 duty
  was satisfied by 3 shifts. Needed **no data-model change** — the order is fixed and the speler
  duty is per-person, so the split is derivable. Each person's shifts fill their own speler duty
  first; the surplus flows to their gezin unit:

  ```
  speler.completed = min( completed[p], speler.required )
  gezin.completed  = Σ over p in gezin.person_ids of max( 0, completed[p] − speler_required(p) )
  ```

  where `speler_required(p)` is 2 for an O17+ player and 0 for everyone else (children, non-playing
  parents). Pending shifts are consumed after completed ones. `assigned_persons` needs no unit
  reference. A player with no youth children has nowhere to spill, so their speler unit keeps every
  shift — capping there would erase real work from `total_completed`.

  Shipped with zero risk: there were **no shift assignments at all** on production (19 shifts, all
  `open`), so no migration and no live progress numbers to disturb. Covered by
  `VolunteerShiftAttributionTest`; 4 of its 8 tests fail against the old calculator.

The header comment `SPELER : one obligation per O17+ player (vervangt de ouderplicht)` is now wrong
and must be corrected.

### 6. A parent cannot currently see their child's record
Verified as a plain member: `GET /wp/v2/people` returns 0 rows and `GET /wp/v2/people/{id}` returns
403. Plain members see nobody — which is correct today and wrong under decision 2.

Needs a positive, scoped grant in `AccessControl`: a member may read their own `person` record and
the records of people reachable through their `relationships` (children). Read-only to start; any
write path must respect the existing `former_member` read-only rule. This grant must be enforced in
`filter_rest_query()`, `filter_rest_single_access()` **and** `map_meta_cap`, not in React — the
frontend `KaderOrVrijwilligRedirect` guard is navigation, not authorization.

### 7. A willing volunteer with no obligation is refused
Decision 3 says anyone may activate because they might want to volunteer. But
`get_available_shifts()` refuses them:

```php
$eligible = get_eligible_unit_for_person( $person_id, $season ) !== null
    || $exempt !== null; // Exempt members may still volunteer voluntarily.
if ( ! $eligible ) { return [ 'eligible' => false, 'shifts' => [],
    'block_reason' => 'Je valt niet onder de vrijwilligersplicht-doelgroep.' ]; }
```

So a sponsor, a grandparent, or an O17+ player's willing partner activates an account and lands on
an empty page telling them they are not in the target group. Exempt members are already allowed
through, which shows the intent: **owing an obligation and being allowed to volunteer are different
things.** Split them — `may_volunteer()` should be true for any active person; the unit only decides
whether progress is *required*.

Note that activation under decision 3 reaches the 396 active members under 16. Creating accounts for
under-16s raises an AVG consent question that is a club/legal decision, not a technical one.

## Proposed approach

### Identity model — synthetic email only on collision
- Keep `user_email` = the real address whenever it is free. Those members log in with their email,
  exactly as the 17 existing accounts do. No migration.
- When the address is already claimed by another WP user, assign a synthetic
  `person-{id}@{no-mx-subdomain}` and set the real address in `rondo_contact_email` user meta.
- Set `rondo_contact_email` for **every** provisioned user, so all outbound Rondo mail has one
  code path and never has to ask which address is real.
- Add an `authenticate` filter resolving username → KNVB-ID → unique `rondo_contact_email` match.
- Filter password-reset mail to `rondo_contact_email`. Suppress any WordPress mail addressed to the
  synthetic domain.

### Activation — email-first, link is the proof
A public page at `/activeren`, styled like the existing standalone `/betaling/{token}` page
(`class-public-payment-page.php` is the pattern to copy).

1. Member enters an email address. Nothing else — a parent has nothing else to enter.
2. Server finds every active, non-former `person` whose `email_1`/`email_2` matches.
3. Server **always** responds "Als we een lid gevonden hebben, is er een e-mail verstuurd."
   (no enumeration).
4. If there were matches, one email goes to that address with a signed, expiring token.
5. The token page lists the matched people — "Wie ben je?" — and creates the account for the chosen
   person, then sets a password.

The security property that makes email-only acceptable: **activation never grants access directly.**
The link always goes to the address already on file. Someone who guesses an address learns nothing
and receives nothing. A household mailbox can activate any person on that mailbox, which is the
intended behaviour for a gezin.

Rate-limit per IP and per email via transients.

### Rollout
No mass mailing. Announce the activation URL through the club's existing channels and let members
come. Provisioning happens lazily, one member at a time, at the moment they ask for it.

## Work breakdown

| # | Work | Depends on |
|---|---|---|
| ~~0~~ | ~~Gate `suppress_age_group`~~ — **done, shipped in 33.28.2** | — |
| ~~1~~ | ~~`get_eligible_units_for_person()` returns all units; `/vrijwillig` renders both~~ — **done, 33.29.0** | — |
| ~~3~~ | ~~Split `may_volunteer()` from owing an obligation~~ — **done, 33.29.0** | — |
| ~~2~~ | ~~Attribute each shift to one unit, speler duty first~~ — **done, 33.29.1** | — |
| 12 | Scoped Kaderlijst endpoint, then delete `suppress_age_group` altogether | 1 |
| 4 | Scoped read grant: member sees own record + children, enforced server-side | 0 |
| 5 | `rondo_contact_email` user meta + synthetic-email fallback in `UserProvisioning::provision()` | — |
| 6 | Drop the `knvb-id` requirement from `/rondo/v1/users/provisionable` | — |
| 7 | `authenticate` filter: username / KNVB-ID / unique contact-email login | 5 |
| 8 | Reroute password-reset and WP notification mail to `rondo_contact_email` | 5 |
| 9 | Public `/activeren` page + token endpoints + rate limiting | 5, 8 |
| 10 | Data-quality report: 56 parents without email, 27 orphan gezinnen | — |
| 11 | Docs in `../developer/src/content/docs/features/` | all |

Item 0 is a live vulnerability and must not wait for the rest. Items 1, 3, 5, 6 and 10 are
independent. Nothing in 5–9 should be deployed before 0 and 4 are done, because they are what let
738 new people through the door.

## Open questions

- AVG consent for the 396 active members under 16 who may now activate.
- Do we re-evaluate obligations as children age past JO16 mid-season, or only at season roll-over?
