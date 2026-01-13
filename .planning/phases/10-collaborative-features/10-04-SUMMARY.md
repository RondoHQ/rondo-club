# Plan 10-04 Summary: Workspace iCal Calendar Feeds

## Status: COMPLETE

## Overview
Extended the iCal feed system to support workspace-scoped calendars, allowing workspace members to subscribe to important dates for all contacts visible to the workspace.

## Tasks Completed

### Task 1: Add workspace iCal rewrite rules and query vars
- Added `prm_workspace_ical` and `prm_workspace_id` query vars
- Added workspace calendar rewrite rule: `^workspace/([0-9]+)/calendar/([a-f0-9]+)\.ics$`
- Updated `handle_feed_request()` to check for workspace feed first

### Task 2: Implement workspace iCal feed handler
- Created `handle_workspace_feed()` method with security checks:
  - Token validation via `get_user_by_token()`
  - Workspace existence check (post_type, post_status)
  - Membership verification via `PRM_Workspace_Members::is_member()`
- Created `get_workspace_important_dates()` method:
  - Queries people with workspace_access taxonomy term
  - Queries important dates linked via related_people ACF field
  - Includes fallback for ACF serialized relationship format
- Created `output_workspace_ical()` and `generate_workspace_ical()` methods
- Workspace calendar named "Caelis - {Workspace Name}"

### Task 3: Add workspace calendar URL to WorkspaceDetail UI
- Added iCal token fetch via `prmApi.getIcalUrl()` on component mount
- Constructed workspace calendar URL with workspace ID and token
- Added Calendar Subscription card with:
  - Calendar icon header
  - Descriptive text about workspace calendar subscription
  - Copy-to-clipboard input field with token
  - Copy button with visual feedback (Check icon on copied)
  - Fallback message when iCal feed not enabled in Settings

## Files Modified
- `/Users/joostdevalk/Code/caelis/includes/class-ical-feed.php` - Workspace feed implementation
- `/Users/joostdevalk/Code/caelis/src/pages/Workspaces/WorkspaceDetail.jsx` - Calendar subscription UI
- `/Users/joostdevalk/Code/caelis/src/api/client.js` - Added getIcalUrl helper
- `/Users/joostdevalk/Code/caelis/docs/ical-feed.md` - Updated documentation
- `/Users/joostdevalk/Code/caelis/CHANGELOG.md` - Added changelog entry
- `/Users/joostdevalk/Code/caelis/style.css` - Version bump to 1.58.0
- `/Users/joostdevalk/Code/caelis/package.json` - Version bump to 1.58.0

## Commits
- `b2e85a6` - feat(10-04): add workspace iCal calendar feed support

## Technical Notes

### Security Model
1. Token must belong to a valid user (same as personal feed)
2. Workspace must exist and be published
3. User must be a member of the workspace (any role: admin, member, viewer)

### ACF Relationship Query
The `related_people` field may store data in different formats depending on ACF version:
- Simple array of IDs
- Serialized array with quoted IDs

The implementation handles both formats with a fallback LIKE query for serialized data.

### API Enhancement
The `/prm/v1/user/ical-url` endpoint now returns a `token` field in addition to `url` and `webcal_url`, allowing the frontend to construct workspace calendar URLs without parsing the URL.

## Verification Checklist
- [x] Workspace query vars registered
- [x] Workspace rewrite rule added
- [x] Workspace feed handler implemented
- [x] Membership verification before feed generation
- [x] WorkspaceDetail shows calendar URL with copy button
- [x] `npm run build` succeeds
- [x] Documentation updated

## Notes for Production
After deployment, rewrite rules need to be flushed. This will happen automatically on theme activation, or can be done manually by visiting Settings > Permalinks in WordPress admin.
