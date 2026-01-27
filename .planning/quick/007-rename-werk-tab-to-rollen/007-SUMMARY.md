# Quick Task 007 Summary: Rename "Werk" tab to "Rollen"

## Task Description
Rename the "Werk" tab on PersonDetail to "Rollen" as it better describes the content (team roles/positions).

## Changes Made

### File Modified
- `src/pages/People/PersonDetail.jsx` (line 1698)

### Change Detail
Changed tab label from "Werk" to "Rollen" in the PersonDetail component navigation tabs.

**Before:**
```jsx
Werk
```

**After:**
```jsx
Rollen
```

## Rationale
The tab displays work history/roles (team memberships and positions), making "Rollen" (Roles) a more accurate description than "Werk" (Work).

## Verification
- [x] Build successful
- [x] Tab label updated in PersonDetail component
