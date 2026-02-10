# Quick Task 51: Add VOG Reminder Email (auto-sent 7 days after Justis date)

## Goal
Add a "reminder email" to the VOG process. This email is sent automatically 7 days after the Justis date is set on a person. There are two reminder templates (new volunteer / renewal), configured in settings. Templates support `{first_name}`, `{email_sent_date}`, `{justis_date}`, and `{previous_vog_date}` (renewal only) variables.

## Architecture

### Approach: Daily cron checks for pending reminders
- Register a daily cron event `rondo_vog_reminder_check` that runs once per day
- Query people where `vog_justis_submitted_date` is exactly 7 days ago AND `vog_reminder_sent_date` is empty
- For each match, send the appropriate reminder email (new vs renewal based on `datum-vog`)
- Record `vog_reminder_sent_date` in post meta to prevent duplicate sends

### Why cron (not immediate scheduling per person):
- Simpler to implement and maintain
- No risk of orphaned scheduled events
- Daily sweep catches any missed people
- Follows the same pattern as `rondo_daily_reminder_check`

## Tasks

### Task 1: Extend VOGEmail class with reminder templates

**File:** `includes/class-vog-email.php`

1. Add option constants:
   ```php
   const OPTION_REMINDER_TEMPLATE_NEW = 'rondo_vog_reminder_template_new';
   const OPTION_REMINDER_TEMPLATE_RENEWAL = 'rondo_vog_reminder_template_renewal';
   ```

2. Add getter methods with fallback to defaults:
   - `get_reminder_template_new(): string` — fallback to default reminder template for new volunteers
   - `get_reminder_template_renewal(): string` — fallback to default renewal reminder template

3. Add update methods:
   - `update_reminder_template_new(string $template): bool`
   - `update_reminder_template_renewal(string $template): bool`

4. Add default templates:
   - `get_default_reminder_template_new()` — mentions `{first_name}`, `{email_sent_date}`, `{justis_date}`
   - `get_default_reminder_template_renewal()` — adds `{previous_vog_date}`

5. Add a `send_reminder(int $person_id, string $template_type)` method:
   - Similar to `send()` but uses reminder templates
   - Builds vars: `first_name`, `email_sent_date` (from `vog_email_sent_date` meta), `justis_date` (from `vog_justis_submitted_date` meta), `previous_vog_date` (renewal only, from `datum-vog` ACF field)
   - Subject: "VOG herinnering" (new) / "VOG herinnering - vernieuwing" (renewal)
   - Records `vog_reminder_sent_date` in post meta after send
   - Logs to timeline via `CommentTypes::create_email_log()`

6. Update `get_all_settings()` to include the two new templates.

7. Update `send()` method's `in_array` validation to also accept `'reminder_new'` and `'reminder_renewal'` — **No, keep send() for initial emails only.** The new `send_reminder()` method handles reminders separately.

### Task 2: Add daily cron for VOG reminders

**File:** `includes/class-vog-email.php` (add cron methods to VOGEmail class)

1. Add `schedule_reminder_cron()` method:
   ```php
   public static function schedule_reminder_cron(): void {
       if ( ! wp_next_scheduled( 'rondo_vog_reminder_check' ) ) {
           wp_schedule_event( strtotime( 'tomorrow 08:00' ), 'daily', 'rondo_vog_reminder_check' );
       }
   }
   ```

2. Add `unschedule_reminder_cron()` static method.

3. Add `process_pending_reminders()` method:
   - Query: `WP_Query` for `person` posts where:
     - `vog_justis_submitted_date` meta exists
     - `vog_justis_submitted_date` value is exactly 7 days ago (compare date string)
     - `vog_reminder_sent_date` meta does NOT exist or is empty
   - For each person:
     - Determine template type based on `datum-vog` ACF field (empty = new, exists = renewal)
     - Call `$this->send_reminder( $person_id, $template_type )`
   - Return count of sent reminders

4. Register the cron hook in `__construct()` or via a static `init()` method:
   ```php
   add_action( 'rondo_vog_reminder_check', [ $this, 'process_pending_reminders' ] );
   ```

**File:** `functions.php`

5. In `stadion_init()` or theme setup, call `VOGEmail::schedule_reminder_cron()`.
6. In theme deactivation, call `wp_clear_scheduled_hook( 'rondo_vog_reminder_check' )`.

### Task 3: Extend settings REST API & React UI

**File:** `includes/class-rest-api.php`

1. In `update_vog_settings()`, handle the two new template params:
   ```php
   $reminder_template_new = $request->get_param( 'reminder_template_new' );
   $reminder_template_renewal = $request->get_param( 'reminder_template_renewal' );

   if ( $reminder_template_new !== null ) {
       $vog_email->update_reminder_template_new( $reminder_template_new );
   }
   if ( $reminder_template_renewal !== null ) {
       $vog_email->update_reminder_template_renewal( $reminder_template_renewal );
   }
   ```

**File:** `src/pages/VOG/VOGSettings.jsx`

2. Add the two new template fields to the settings state:
   ```javascript
   const [vogSettings, setVogSettings] = useState({
     // ... existing fields ...
     reminder_template_new: '',
     reminder_template_renewal: '',
   });
   ```

3. Add two new textarea sections in the UI (after the existing template sections), with a separator/heading "Herinnering templates":
   - "Herinnering template nieuwe vrijwilliger" — available variables: `{first_name}`, `{email_sent_date}`, `{justis_date}`
   - "Herinnering template verlenging" — available variables: `{first_name}`, `{email_sent_date}`, `{justis_date}`, `{previous_vog_date}`

## Verification
- `npm run build` succeeds
- Settings UI shows reminder template fields
- Default templates are sensible Dutch text
- Cron hook registered on theme activation
- Cron processes people with Justis date 7 days ago
- Reminder email uses correct template (new vs renewal)
- `vog_reminder_sent_date` prevents duplicate sends
