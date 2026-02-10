---
phase: quick-53
plan: 53
type: execute
wave: 1
depends_on: []
files_modified:
  - functions.php
autonomous: true

must_haves:
  truths:
    - "When datum-vog field is updated to a new value, all three tracking dates are cleared"
    - "Existing 9 people with recent datum-vog (within 1 year) have stale tracking dates cleaned up"
    - "vog_reminder_sent_date is cleared alongside email_sent and justis_submitted dates"
  artifacts:
    - path: "functions.php"
      provides: "Updated rondo_reset_vog_tracking_on_datum_update() to clear all 3 tracking dates"
      contains: "delete_post_meta.*vog_reminder_sent_date"
  key_links:
    - from: "acf/update_value/name=datum-vog"
      to: "rondo_reset_vog_tracking_on_datum_update"
      via: "ACF filter hook"
      pattern: "add_filter.*acf/update_value/name=datum-vog"
---

<objective>
Fix VOG tracking date reset behavior to include vog_reminder_sent_date when datum-vog is updated, and clean up 9 existing people with stale tracking dates.

Purpose: The vog_reminder_sent_date field was added in quick-51 but the existing reset hook (added around phase 119-122) only clears vog_email_sent_date and vog_justis_submitted_date. When a VOG date is renewed/updated, all workflow tracking must reset to allow fresh emails/reminders.

Output: Updated reset function + one-time cleanup of stale data
</objective>

<execution_context>
@/Users/joostdevalk/.claude/get-shit-done/workflows/execute-plan.md
@/Users/joostdevalk/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@.planning/PROJECT.md
@.planning/STATE.md

## Relevant Prior Work
@.planning/quick/51-add-vog-reminder-email-sent-automaticall/51-SUMMARY.md — Added vog_reminder_sent_date tracking (2026-02-10)
@.planning/quick/52-change-vog-reminder-to-manual-bulk-actio/52-SUMMARY.md — Changed to manual bulk action (2026-02-10)

## Current Implementation
functions.php line 972-980:
- `rondo_reset_vog_tracking_on_datum_update()` currently clears 2 fields (email_sent, justis_submitted)
- Hook: `acf/update_value/name=datum-vog`
- Missing: vog_reminder_sent_date (added in quick-51)

## Data State
Production database (krv_ prefix):
- 114 total meta entries for the 3 tracking fields
- 9 people have recent datum-vog (within 1 year) WITH stale tracking dates
- These 9 need one-time cleanup

Query pattern established:
```sql
SELECT p.ID FROM krv_posts p
INNER JOIN krv_postmeta pm1 ON p.ID = pm1.post_id AND pm1.meta_key = 'datum-vog'
LEFT JOIN krv_postmeta pm2 ON p.ID = pm2.post_id AND pm2.meta_key = 'vog_email_sent_date'
LEFT JOIN krv_postmeta pm3 ON p.ID = pm3.post_id AND pm3.meta_key = 'vog_justis_submitted_date'
LEFT JOIN krv_postmeta pm4 ON p.ID = pm4.post_id AND pm4.meta_key = 'vog_reminder_sent_date'
WHERE p.post_type = 'person'
  AND pm1.meta_value >= DATE_SUB(NOW(), INTERVAL 1 YEAR)
  AND (pm2.meta_value IS NOT NULL OR pm3.meta_value IS NOT NULL OR pm4.meta_value IS NOT NULL)
```
</context>

<tasks>

<task type="auto">
  <name>Update reset hook and clean up stale data</name>
  <files>functions.php</files>
  <action>
**Update rondo_reset_vog_tracking_on_datum_update() function (line 972):**

Add third delete_post_meta call for vog_reminder_sent_date:

```php
function rondo_reset_vog_tracking_on_datum_update( $value, $post_id, $field, $original ) {
	// Only process if datum-vog is actually changing to a new value
	if ( $value !== $original && ! empty( $value ) ) {
		delete_post_meta( $post_id, 'vog_email_sent_date' );
		delete_post_meta( $post_id, 'vog_justis_submitted_date' );
		delete_post_meta( $post_id, 'vog_reminder_sent_date' ); // ADD THIS LINE
	}
	return $value;
}
```

**One-time cleanup of 9 stale records:**

Use WP-CLI on production to clear tracking dates for people with recent datum-vog:

```bash
source .env && ssh -p "$DEPLOY_SSH_PORT" "$DEPLOY_SSH_USER@$DEPLOY_SSH_HOST" "cd $DEPLOY_REMOTE_THEME_PATH && wp db query \"DELETE pm FROM krv_postmeta pm INNER JOIN krv_posts p ON pm.post_id = p.ID INNER JOIN krv_postmeta pm_datum ON p.ID = pm_datum.post_id AND pm_datum.meta_key = 'datum-vog' WHERE p.post_type = 'person' AND pm_datum.meta_value >= DATE_SUB(NOW(), INTERVAL 1 YEAR) AND pm.meta_key IN ('vog_email_sent_date', 'vog_justis_submitted_date', 'vog_reminder_sent_date')\" --skip-plugins --skip-themes"
```

This DELETE joins:
- pm: The meta records to delete (tracking dates)
- p: Posts table (filter to person post type)
- pm_datum: datum-vog meta (filter to recent VOGs only)

Deletes all 3 tracking meta keys for people with datum-vog within last year (these are renewals that should have clean state).

**Do NOT add documentation comment** — this is a simple 1-line addition to existing function, no explanation needed.
  </action>
  <verify>
**Code change:**
```bash
grep -A 5 "function rondo_reset_vog_tracking_on_datum_update" functions.php | grep "vog_reminder_sent_date"
```
Should show the new delete_post_meta line.

**Cleanup verification:**
```bash
source .env && ssh -p "$DEPLOY_SSH_PORT" "$DEPLOY_SSH_USER@$DEPLOY_SSH_HOST" "cd $DEPLOY_REMOTE_THEME_PATH && wp db query \"SELECT COUNT(*) as remaining FROM krv_posts p INNER JOIN krv_postmeta pm1 ON p.ID = pm1.post_id AND pm1.meta_key = 'datum-vog' LEFT JOIN krv_postmeta pm2 ON p.ID = pm2.post_id AND pm2.meta_key = 'vog_email_sent_date' LEFT JOIN krv_postmeta pm3 ON p.ID = pm3.post_id AND pm3.meta_key = 'vog_justis_submitted_date' LEFT JOIN krv_postmeta pm4 ON p.ID = pm4.post_id AND pm4.meta_key = 'vog_reminder_sent_date' WHERE p.post_type = 'person' AND pm1.meta_value >= DATE_SUB(NOW(), INTERVAL 1 YEAR) AND (pm2.meta_value IS NOT NULL OR pm3.meta_value IS NOT NULL OR pm4.meta_value IS NOT NULL)\" --skip-plugins --skip-themes"
```
Should return `remaining: 0` (was 9 before cleanup).
  </verify>
  <done>
- functions.php contains `delete_post_meta( $post_id, 'vog_reminder_sent_date' )`
- Production database has 0 people with recent datum-vog AND stale tracking dates (down from 9)
- Future datum-vog updates will clear all 3 tracking dates automatically
  </done>
</task>

</tasks>

<verification>
**Manual test on production:**

1. Pick a test person with datum-vog field
2. Note their current tracking dates (if any): `wp post meta list {ID} --keys=vog_email_sent_date,vog_justis_submitted_date,vog_reminder_sent_date`
3. Update datum-vog field via React UI or WP-CLI: `wp post meta update {ID} datum-vog "2026-03-01"`
4. Verify all 3 tracking dates are cleared: `wp post meta list {ID} --keys=vog_email_sent_date,vog_justis_submitted_date,vog_reminder_sent_date` (should be empty)

**Edge case coverage:**
- Updating datum-vog to same value → no reset (correct, per `$value !== $original`)
- Clearing datum-vog (empty value) → no reset (correct, per `! empty( $value )`)
- Setting datum-vog for first time → clears any orphaned tracking dates (correct behavior)
</verification>

<success_criteria>
- [x] functions.php `rondo_reset_vog_tracking_on_datum_update()` includes vog_reminder_sent_date deletion
- [x] 9 production people with recent VOGs have clean tracking state (0 stale records)
- [x] Manual test confirms all 3 dates clear when datum-vog changes
- [x] Code committed and deployed to production
</success_criteria>

<output>
After completion, create `.planning/quick/53-reset-vog-tracking-dates-when-datum-vog-/53-SUMMARY.md`
</output>
