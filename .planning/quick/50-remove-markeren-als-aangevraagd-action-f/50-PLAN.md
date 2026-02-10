# Quick Task 50: Remove "Markeren als aangevraagd" action from VOG page

## Goal
Remove the "Markeren als aangevraagd" bulk action from the VOG page. This action records `vog-email-verzonden` date without sending an email — it's unnecessary since the email action already covers this.

## Tasks

### Task 1: Remove from React frontend (VOGList.jsx)
**File:** `src/pages/VOG/VOGList.jsx`

Remove these pieces:
1. **State** (line 262): `const [showMarkRequestedModal, setShowMarkRequestedModal] = useState(false);`
2. **Mutation** (lines 402-408): `markRequestedMutation` useMutation
3. **Handler** (lines 429-436): `handleMarkRequested` async function
4. **In handleCloseModal** (line 452): Remove `setShowMarkRequestedModal(false);`
5. **Dropdown button** (lines 593-602): The "Markeren als aangevraagd..." button in the bulk actions dropdown
6. **Modal** (lines 1094-1147): The entire `{showMarkRequestedModal && (...)}` modal block

### Task 2: Remove from API client
**File:** `src/api/client.js` (line 308)

Remove: `bulkMarkVOGRequested: (ids) => api.post('/rondo/v1/vog/bulk-mark-requested', { ids }),`

### Task 3: Remove from PHP backend
**File:** `includes/class-rest-api.php`

1. Remove route registration (lines 488-504): The `register_rest_route` for `/vog/bulk-mark-requested`
2. Remove method (lines 3611-3658): The `bulk_mark_vog_requested()` method and its docblock

## Verification
- `npm run build` succeeds
- No references to `bulkMarkVOGRequested` or `showMarkRequestedModal` or `markRequestedMutation` remain
- The other two actions (send email, mark Justis) still work correctly
