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

## Blockers, in order of severity

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
number that contradicts the coordinator's dashboard. **This is a bug now, independent of login, and
should ship on its own before rollout.** Fixing it needs a product decision: does a playing parent
owe their speler obligation *and* the gezin obligation, or does the speler duty absorb it (as the
`SPELER : one obligation per O17+ player (vervangt de ouderplicht)` header comment implies)?

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
| 1 | Resolve the double-counted playing parent (17 people) — needs a product decision first | — |
| 2 | `rondo_contact_email` user meta + synthetic-email fallback in `UserProvisioning::provision()` | — |
| 3 | Drop the `knvb-id` requirement from `/rondo/v1/users/provisionable` | — |
| 4 | `authenticate` filter: username / KNVB-ID / unique contact-email login | 2 |
| 5 | Reroute password-reset and WP notification mail to `rondo_contact_email` | 2 |
| 6 | Public `/activeren` page + token endpoints + rate limiting | 2, 5 |
| 7 | Data-quality report: 56 parents without email, 27 orphan gezinnen | — |
| 8 | Docs in `../developer/src/content/docs/features/` | all |

Items 1, 2, 3 and 7 are independent and can land first. Item 1 is the one that is a bug **today**,
independent of login, and should ship on its own.

## Open questions

- Should a parent be able to see their child's person record, or only the family's diensten?
- Should the 328 non-member contacts who are *not* parents (sponsors, external) be able to activate
  at all? Current proposal: no — activation matches only people in an eligible unit.
- Do we re-evaluate obligations as children age past JO16 mid-season, or only at season roll-over?
