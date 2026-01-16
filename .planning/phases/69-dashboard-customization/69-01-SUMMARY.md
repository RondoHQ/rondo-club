# Plan 69-01 Summary: Dashboard Customization

## Completed: 2026-01-16

## What Was Done

Implemented user-configurable dashboard with card visibility toggles and drag-and-drop reordering.

### Backend Changes

- Added REST API endpoints for dashboard settings (`includes/class-rest-api.php`):
  - `GET /prm/v1/user/dashboard-settings` - returns visible_cards and card_order arrays
  - `PATCH /prm/v1/user/dashboard-settings` - updates settings with validation
- Settings stored in WordPress user meta: `caelis_dashboard_visible_cards`, `caelis_dashboard_card_order`
- Added validation for valid card IDs: stats, reminders, todos, awaiting, meetings, recent-contacted, recent-edited, favorites

### Frontend Changes

- Added API client methods in `src/api/client.js`: `getDashboardSettings()`, `updateDashboardSettings()`
- Added React hooks in `src/hooks/useDashboard.js`: `useDashboardSettings()`, `useUpdateDashboardSettings()`
- Installed `@dnd-kit/core`, `@dnd-kit/sortable`, `@dnd-kit/utilities` for drag-and-drop
- Created `src/components/DashboardCustomizeModal.jsx` with:
  - Checkbox toggles for card visibility
  - Drag-and-drop reordering using @dnd-kit
  - Reset to defaults button
  - Mobile-friendly touch support
- Refactored `src/pages/Dashboard.jsx`:
  - Added "Customize" button with Settings icon
  - Converted card sections to renderer functions
  - Dynamic rendering based on user settings
  - Stats row rendered full-width, other cards in 3-column grid

## Files Modified

- `includes/class-rest-api.php` - Added dashboard settings endpoints
- `src/api/client.js` - Added API methods
- `src/hooks/useDashboard.js` - Added settings hooks and DEFAULT_DASHBOARD_CARDS export
- `src/pages/Dashboard.jsx` - Added customization modal and dynamic card rendering
- `package.json` - Added @dnd-kit dependencies

## Files Created

- `src/components/DashboardCustomizeModal.jsx` - New modal component

## Verification

- Build succeeds
- Dashboard loads with default layout when no settings saved
- Customize button opens modal
- Cards can be shown/hidden via checkboxes
- Cards can be reordered via drag-and-drop
- Settings persist after page refresh
- Reset to defaults works
- Touch drag works on mobile

## Key Decisions

- Stats row always renders full-width (separate from 3-column grid)
- Meetings card only shows if calendar connections exist (unchanged behavior)
- Default order matches original dashboard layout
- Used @dnd-kit for accessible, mobile-friendly drag-and-drop
