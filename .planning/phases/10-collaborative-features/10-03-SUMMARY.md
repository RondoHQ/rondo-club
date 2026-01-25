# Phase 10-03 Summary: Mention Notifications Integration

## Objective
Wire up mention notifications and integrate MentionInput into the note creation flow. Notify users when they are @mentioned in notes.

## Tasks Completed

### Task 1: Create STADION_Mention_Notifications class
- **Commit**: `775c691` - feat(10-03): implement @mention notifications and integrate MentionInput
- **Files**:
  - `includes/class-mention-notifications.php` - New class for handling mention notifications
  - `functions.php` - Added class to autoloader and instantiation in stadion_init()

### Task 2: Integrate MentionInput into PersonDetail note creation
- **Commit**: `775c691` - feat(10-03): implement @mention notifications and integrate MentionInput
- **Files**:
  - `src/components/Timeline/NoteModal.jsx` - Added MentionInput support
  - `src/pages/People/PersonDetail.jsx` - Pass workspaceIds to NoteModal

### Task 3: Add mention notification preference to Settings page
- **Commit**: `775c691` - feat(10-03): implement @mention notifications and integrate MentionInput
- **Files**:
  - `includes/class-rest-api.php` - Added mention_notifications to GET endpoint and new POST endpoint
  - `src/api/client.js` - Added updateMentionNotifications() method
  - `src/pages/Settings/Settings.jsx` - Added Mention Notifications dropdown in Notifications tab

## Technical Details

### STADION_Mention_Notifications Class
- Hooks into `stadion_user_mentioned` action (fired by STADION_Mentions when notes are saved)
- Checks user preference via `stadion_mention_notifications` user meta
- Three modes:
  - `digest` (default): Queues mention in `_queued_mention_notifications` user meta for daily digest
  - `immediate`: Sends HTML email immediately via wp_mail()
  - `never`: Skips notification entirely
- Self-mentions are automatically ignored (author not notified about their own mentions)
- Static `get_queued_mentions($user_id)` method for digest integration (to be used by STADION_Reminders in Plan 05)

### NoteModal MentionInput Integration
- NoteModal now accepts `workspaceIds` prop
- When workspaceIds has values: Uses MentionInput component with @mention autocomplete
- When workspaceIds is empty: Uses regular RichTextEditor (backward compatible)
- Empty check logic adapts to content type (plain text vs HTML)
- PersonDetail passes workspace IDs from `person.acf._assigned_workspaces` when visibility is 'workspace'

### Settings UI
- New "Mention notifications" section in Notifications tab
- Select dropdown with three options:
  - Include in daily digest (default)
  - Send immediately
  - Don't notify me
- Auto-saves on change with loading state

### REST API Endpoints
- **GET** `/stadion/v1/user/notification-channels` - Now includes `mention_notifications` field
- **POST** `/stadion/v1/user/mention-notifications` - New endpoint to update preference
  - Accepts `preference` parameter: 'digest', 'immediate', or 'never'
  - Returns updated preference in response

## Verification Checklist
- [x] Creating a note with @mention triggers notification to mentioned user
- [x] Notification queued if user preference is 'digest' (stored in user meta)
- [x] Notification sent immediately if user preference is 'immediate'
- [x] No notification sent if preference is 'never'
- [x] MentionInput appears for workspace contacts
- [x] Regular textarea/editor for private contacts
- [x] Settings page shows mention notification preference dropdown
- [x] `npm run build` succeeds
- [x] PHP syntax valid for all modified files

## Version
Updated to v1.59.0

## Files Modified
- `includes/class-mention-notifications.php` (new)
- `functions.php`
- `includes/class-rest-api.php`
- `src/api/client.js`
- `src/components/Timeline/NoteModal.jsx`
- `src/pages/People/PersonDetail.jsx`
- `src/pages/Settings/Settings.jsx`
- `style.css`
- `package.json`
- `CHANGELOG.md`
- `docs/rest-api.md`

## Integration Points
- Relies on `stadion_user_mentioned` hook from STADION_Mentions (Phase 10-02)
- Static `get_queued_mentions()` ready for digest integration in Plan 10-05
- MentionInput uses workspace member search API from Phase 10-02
