# M004: Contributie Exclusion Improvements — Context

**Gathered:** 2026-03-12
**Status:** Ready for planning

## Project Description

Three improvements to the "Uitsluiten van contributie" feature on the person detail FinancesCard: add a confirmation prompt, refresh the component after toggling (currently requires page reload), and send a notification email to the Secretaris and Penningmeester.

## Why This Milestone

The exclusion toggle is a significant financial action (removes a person from fee calculations) but currently fires immediately without confirmation. The UI doesn't refresh after toggling, requiring a full page reload. And there's no notification to the people who need to know about it (Secretaris and Penningmeester).

## User-Visible Outcome

### When this milestone is complete, the user can:

- Click "Uitsluiten van contributie" and see a confirmation dialog before the action fires
- See the FinancesCard immediately update to show the excluded/included state without page reload
- Know that the Secretaris and Penningmeester automatically receive an email when someone is excluded or re-included

### Entry point / environment

- Entry point: Person detail page → FinancesCard component
- Environment: Production (https://rondo.svawc.nl)
- Live dependencies involved: Email via wp_mail/Lettermint

## Completion Class

- Contract complete means: Confirmation prompt shown, component refreshes, email sent to correct recipients
- Integration complete means: Real exclusion toggle on production sends email and updates UI
- Operational complete means: none

## Final Integrated Acceptance

To call this milestone complete, we must prove:

- Clicking "Uitsluiten van contributie" shows "Wil je deze persoon echt uitsluiten van contributie?" and only proceeds on "Ja"
- Clicking "Opnemen" shows a confirmation and only proceeds on "Ja"
- After confirming, the FinancesCard immediately reflects the new state
- An email is sent to users with Secretaris and Penningmeester roles with the correct subject and content
- The email links the excluded person's name to their Rondo page

## Risks and Unknowns

- **Finding Secretaris/Penningmeester users** — There's existing logic in `class-lettermint-webhook.php` that finds users with "secretaris" in their work_history job_title. Same pattern needed for "penningmeester". Falls back to administrators.

## Existing Codebase / Prior Art

- `src/components/FinancesCard.jsx` — Contains the toggle button and `handleToggleExclusion()`. Uses `useUpdatePerson` + `queryClient.invalidateQueries()` but only invalidates fee keys, not the full person data.
- `includes/class-fee-cache-invalidator.php` — `log_contributie_exclusion_toggle()` already fires on `updated_post_meta` for `_exclude_from_contributie`. This is where the email send should be added (same hook, same context).
- `includes/class-lettermint-webhook.php` — Has `get_secretaris_user_ids()` and `person_has_current_secretaris_role()` private methods that find users with "secretaris" work_history. This pattern should be extracted/reused for both secretaris and penningmeester lookups.
- `includes/class-email-template.php` — `EmailTemplate::render()` provides branded HTML email shell.
- `includes/class-finance-config.php` — `get_contact_email()` and `get_display_name()` for email From address.

> See `.gsd/DECISIONS.md` for all architectural and pattern decisions.

## Scope

### In Scope

- Confirmation dialog on both "Uitsluiten van contributie" and "Opnemen" buttons
- Immediate FinancesCard re-render after toggle (proper query invalidation)
- Email notification to Secretaris and Penningmeester on exclusion/re-inclusion
- Email subject: "{name} uitgesloten van contributiebetaling" (or "opgenomen in contributiebetaling" for re-inclusion)
- Email content: "{actor} heeft zojuist, op {datum - tijd}, {name} uitgesloten van contributiebetaling." with person name linked to Rondo page
- Extract role-lookup helper from LettermintWebhook for reuse

### Out of Scope / Non-Goals

- Changing the exclusion business logic itself
- Configurable email template for this notification (hardcoded is fine)
- Notification to the excluded person themselves

## Technical Constraints

- Confirmation should use `window.confirm()` for simplicity (consistent with other confirm dialogs in the app)
- Role lookup must search work_history for job_title containing "secretaris" or "penningmeester" (case-insensitive), same as LettermintWebhook pattern
- Email uses `EmailTemplate::render()` for consistent branding
- Person link in email uses `home_url('/people/' . $person_id)` for the Rondo page URL

## Integration Points

- **FeeCacheInvalidator** — Hook into existing `log_contributie_exclusion_toggle()` method to also send email
- **EmailTemplate** — Branded HTML email rendering
- **LettermintWebhook** — Extract/reuse role-finding pattern for secretaris + add penningmeester
- **FinancesCard** — Frontend confirmation + query invalidation fix
