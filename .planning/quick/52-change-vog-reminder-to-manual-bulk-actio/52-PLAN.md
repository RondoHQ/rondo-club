# Quick Task 52: Change VOG reminder to manual bulk action with date column

## Goal
Change the VOG reminder email from automatic cron to a manual bulk action (like "VOG email verzenden" and "Markeren bij Justis aangevraagd"). Add a "Herinnering" column to the VOG list showing when the reminder was sent.

## Tasks

### Task 1: Remove cron infrastructure from VOGEmail class

**File:** `includes/class-vog-email.php`

1. Remove the `__construct()` method (lines 722-724) that registers the cron action
2. Remove `schedule_reminder_cron()` static method (lines 647-651)
3. Remove `unschedule_reminder_cron()` static method (lines 658-660)
4. Remove `process_pending_reminders()` method (lines 670-715)

**File:** `functions.php`

5. Remove `new VOGEmail();` call in `stadion_init()` (around line 372) — it was only needed for cron hook registration
6. Remove `VOGEmail::schedule_reminder_cron();` from theme activation (around line 845)
7. Remove `VOGEmail::unschedule_reminder_cron();` from theme deactivation (around line 875)

### Task 2: Add bulk send reminder REST endpoint

**File:** `includes/class-rest-api.php`

1. Register a new route `POST /rondo/v1/vog/bulk-send-reminder` (follow the pattern of `/vog/bulk-send`):
   ```php
   register_rest_route('rondo/v1', '/vog/bulk-send-reminder', [
       'methods'             => \WP_REST_Server::CREATABLE,
       'callback'            => [ $this, 'bulk_send_vog_reminders' ],
       'permission_callback' => [ $this, 'check_user_approved' ],
       'args'                => [
           'ids' => [
               'required'          => true,
               'validate_callback' => function ( $param ) {
                   return is_array( $param ) && ! empty( $param );
               },
           ],
       ],
   ]);
   ```

2. Add `bulk_send_vog_reminders()` method — follows exact pattern of `bulk_send_vog_emails()`:
   - For each person_id: determine template type (`reminder_new` or `reminder_renewal` based on `datum-vog`)
   - Call `$vog_email->send_reminder( $person_id, $template_type )`
   - Return results with `sent`, `failed`, `total` counts

3. Expose `vog_reminder_sent_date` in the REST response — add to both:
   - The `modify_person_rest_response` filter (around line 808, add after `vog_justis_submitted_date` block)
   - The filtered people response builder in `class-rest-people.php` (around line 1337, add after `vog_justis_submitted_date` block)

### Task 3: Add frontend bulk action and column

**File:** `src/api/client.js`

1. Add API method:
   ```javascript
   bulkSendVOGReminders: (ids) => api.post('/rondo/v1/vog/bulk-send-reminder', { ids }),
   ```

**File:** `src/pages/VOG/VOGList.jsx`

2. Add modal state: `const [showSendReminderModal, setShowSendReminderModal] = useState(false);`

3. Add mutation:
   ```javascript
   const sendRemindersMutation = useMutation({
     mutationFn: ({ ids }) => prmApi.bulkSendVOGReminders(ids),
     onSuccess: (response) => {
       setBulkActionResult(response.data);
       queryClient.invalidateQueries({ queryKey: ['people', 'filtered'] });
     },
   });
   ```

4. Add handler `handleSendReminders` (same pattern as `handleSendEmails`)

5. Update `handleCloseModal` to include `setShowSendReminderModal(false)`

6. Add dropdown button "Herinnering verzenden..." with `Mail` icon after the Justis button

7. Add "Herinnering" column:
   - In VOGRow: add `<td>` showing `person.acf?.['vog_reminder_sent_date']` formatted as yyyy-MM-dd (same pattern as Justis date column)
   - In table header: add `<SortableHeader label="Herinnering" columnId="custom_vog_reminder_sent_date" ... />`

8. Add Send Reminder Modal (follows exact pattern of Send Email Modal):
   - Title: "VOG herinnering verzenden"
   - Description: "Verstuur VOG herinnering naar X vrijwilliger(s)."
   - Subtitle: "Het systeem selecteert automatisch de juiste template (nieuw of vernieuwing) op basis van de bestaande VOG datum."
   - Action button calls `handleSendReminders`

## Verification
- `npm run build` succeeds
- Cron code fully removed from VOGEmail and functions.php
- New bulk action appears in dropdown
- Reminder column shows in VOG list table
- REST endpoint `/vog/bulk-send-reminder` works
- `vog_reminder_sent_date` exposed in REST responses
