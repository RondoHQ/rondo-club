# Phase 121: Bulk Actions - Research

**Researched:** 2026-01-30
**Domain:** React bulk selection with API operations, progress feedback
**Confidence:** HIGH

## Summary

This phase adds bulk action capabilities to the VOG list: selecting multiple volunteers via checkboxes and performing bulk operations (send VOG emails, mark as requested). The implementation follows established patterns from PeopleList.jsx which already has a complete bulk selection system with checkboxes, selection toolbar, dropdown menus, and modal-based bulk operations.

**Key findings:**
- PeopleList.jsx already implements full checkbox selection with header toggle (select all/deselect all)
- VOGList.jsx has all the filtering logic in place, just needs selection layer added
- Backend `VOGEmail::send()` method exists but no REST endpoint yet - needs to be created
- Backend `bulk-update` endpoint pattern exists in class-rest-people.php for reference
- No toast/notification system in codebase - use success state in toolbar or modal
- Existing modal pattern in codebase for confirmations (DeleteFieldDialog.jsx, BulkLabelsModal)

**Primary recommendation:** Add checkbox selection to VOGList.jsx following PeopleList.jsx patterns exactly, create new REST endpoint for bulk VOG actions (send email, mark requested), display progress/results inline in the action toolbar with a simple status message.

## Standard Stack

The established libraries/tools for this domain:

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| React | 18.2.0 | UI framework | Already in use |
| TanStack Query | 5.17.0 | Server state, mutations | Existing pattern for bulk operations |
| Lucide React | 0.309.0 | Icons (Square, CheckSquare, MinusSquare, Mail, Check) | Already imported in PeopleList |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| clsx | 2.1.0 | Conditional classes | Selection highlight styling |

### Alternatives Considered
None - all choices dictated by existing codebase patterns. No new dependencies needed.

**Installation:**
No new dependencies required - all libraries already installed.

## Architecture Patterns

### Recommended Project Structure
```
src/
├── pages/VOG/
│   └── VOGList.jsx         # Add selection state, toolbar, bulk actions
├── hooks/
│   └── useVOGBulkActions.js # NEW: Custom hook for VOG-specific bulk operations
└── api/
    └── client.js            # Add VOG bulk action endpoints
includes/
└── class-rest-people.php    # Add VOG bulk endpoints (or new class)
```

### Pattern 1: Checkbox Selection State Management

**What:** React state management for multi-select using Set
**When to use:** Any list view with bulk actions

**Example:**
```javascript
// Source: src/pages/People/PeopleList.jsx lines 736-976
const [selectedIds, setSelectedIds] = useState(new Set());

// Toggle single selection
const toggleSelection = (personId) => {
  setSelectedIds(prev => {
    const next = new Set(prev);
    if (next.has(personId)) {
      next.delete(personId);
    } else {
      next.add(personId);
    }
    return next;
  });
};

// Toggle all visible
const toggleSelectAll = () => {
  if (selectedIds.size === people.length) {
    setSelectedIds(new Set());
  } else {
    setSelectedIds(new Set(people.map(p => p.id)));
  }
};

// Clear selection (after action completes or data changes)
const clearSelection = () => setSelectedIds(new Set());

// Derived state
const isAllSelected = people.length > 0 && selectedIds.size === people.length;
const isSomeSelected = selectedIds.size > 0 && selectedIds.size < people.length;
```

### Pattern 2: Selection Toolbar with Actions Dropdown

**What:** Floating/sticky toolbar showing selection count and action buttons
**When to use:** When items are selected and actions are available

**Example:**
```javascript
// Source: src/pages/People/PeopleList.jsx lines 1481-1531
{selectedIds.size > 0 && (
  <div className="sticky top-0 z-20 flex items-center justify-between bg-accent-50 dark:bg-accent-800 border border-accent-200 dark:border-accent-700 rounded-lg px-4 py-2 shadow-sm">
    <span className="text-sm text-accent-800 dark:text-accent-200 font-medium">
      {selectedIds.size} {selectedIds.size === 1 ? 'vrijwilliger' : 'vrijwilligers'} geselecteerd
    </span>
    <div className="flex items-center gap-3">
      {/* Actions dropdown */}
      <div className="relative" ref={bulkDropdownRef}>
        <button onClick={() => setShowBulkDropdown(!showBulkDropdown)} className="...">
          Acties <ChevronDown className="w-4 h-4" />
        </button>
        {showBulkDropdown && (
          <div className="absolute right-0 mt-1 w-48 bg-white dark:bg-gray-800 border rounded-lg shadow-lg z-50">
            <button onClick={() => handleSendEmails()}>
              <Mail className="w-4 h-4" /> VOG email verzenden...
            </button>
            <button onClick={() => handleMarkRequested()}>
              <Check className="w-4 h-4" /> Markeren als aangevraagd...
            </button>
          </div>
        )}
      </div>
      <button onClick={clearSelection}>Selectie wissen</button>
    </div>
  </div>
)}
```

### Pattern 3: Bulk Action Modal with Confirmation

**What:** Modal dialog for confirming bulk operations before execution
**When to use:** Before performing irreversible or significant bulk actions

**Example:**
```javascript
// Derived from BulkLabelsModal and DeleteFieldDialog patterns
function BulkEmailModal({ isOpen, onClose, selectedCount, onSubmit, isLoading }) {
  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
      <div className="bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full max-w-md mx-4">
        <div className="flex items-center justify-between p-4 border-b dark:border-gray-700">
          <h2 className="text-lg font-semibold dark:text-gray-50">VOG email verzenden</h2>
          <button onClick={onClose} disabled={isLoading}>
            <X className="w-5 h-5" />
          </button>
        </div>

        <div className="p-4 space-y-4">
          <p className="text-sm text-gray-600 dark:text-gray-300">
            Verstuur VOG email naar {selectedCount} {selectedCount === 1 ? 'vrijwilliger' : 'vrijwilligers'}.
          </p>
          <p className="text-sm text-gray-500 dark:text-gray-400">
            Het systeem selecteert automatisch de juiste template (nieuw of vernieuwing)
            op basis van de bestaande VOG datum.
          </p>
        </div>

        <div className="flex justify-end gap-2 p-4 border-t dark:border-gray-700">
          <button onClick={onClose} disabled={isLoading} className="btn-secondary">
            Annuleren
          </button>
          <button onClick={onSubmit} disabled={isLoading} className="btn-primary">
            {isLoading ? 'Verzenden...' : `Verstuur naar ${selectedCount} vrijwilliger${selectedCount > 1 ? 's' : ''}`}
          </button>
        </div>
      </div>
    </div>
  );
}
```

### Pattern 4: Backend Bulk VOG Operations

**What:** REST endpoint for bulk VOG operations
**When to use:** Performing same operation on multiple people

**Example:**
```php
// Pattern from class-rest-people.php bulk_update_people
register_rest_route(
  'rondo/v1',
  '/vog/bulk-send',
  [
    'methods'             => \WP_REST_Server::CREATABLE,
    'callback'            => [ $this, 'bulk_send_vog_emails' ],
    'permission_callback' => [ $this, 'check_user_approved' ],
    'args'                => [
      'ids' => [
        'required'          => true,
        'validate_callback' => function ( $param ) {
          return is_array( $param ) && ! empty( $param );
        },
      ],
    ],
  ]
);

public function bulk_send_vog_emails( $request ) {
  $ids = $request->get_param( 'ids' );
  $vog_email = new \Stadion\VOG\VOGEmail();

  $results = [];
  foreach ( $ids as $person_id ) {
    // Determine template type based on datum-vog
    $datum_vog = get_field( 'datum-vog', $person_id );
    $template_type = empty( $datum_vog ) ? 'new' : 'renewal';

    $result = $vog_email->send( $person_id, $template_type );
    $results[] = [
      'id'      => $person_id,
      'success' => $result === true,
      'error'   => is_wp_error( $result ) ? $result->get_error_message() : null,
    ];
  }

  return rest_ensure_response( [
    'results'    => $results,
    'sent'       => count( array_filter( $results, fn($r) => $r['success'] ) ),
    'failed'     => count( array_filter( $results, fn($r) => !$r['success'] ) ),
  ] );
}
```

### Pattern 5: Row Selection Visual Feedback

**What:** Visual indication that a row is selected
**When to use:** Any row with checkbox selection

**Example:**
```javascript
// Source: src/pages/People/PeopleList.jsx PersonListRow lines 61-75
<tr className={`hover:bg-gray-100 dark:hover:bg-gray-700 ${
  isSelected
    ? 'bg-accent-50 dark:bg-accent-900/30'
    : isOdd ? 'bg-gray-50 dark:bg-gray-800/50' : 'bg-white dark:bg-gray-800'
}`}>
  <td className="pl-4 pr-2 py-3 w-10">
    <button onClick={(e) => { e.preventDefault(); onToggleSelection(person.id); }}>
      {isSelected ? (
        <CheckSquare className="w-5 h-5 text-accent-600 dark:text-accent-400" />
      ) : (
        <Square className="w-5 h-5" />
      )}
    </button>
  </td>
  {/* ... rest of row */}
</tr>
```

### Anti-Patterns to Avoid

- **Don't send emails synchronously one-by-one in frontend:** Use single bulk endpoint that handles the loop server-side
- **Don't block UI during bulk operations:** Show loading state but keep UI responsive
- **Don't clear selection on every re-render:** Use useEffect with data dependency to clear only when data changes
- **Don't use window.confirm():** Use modal pattern for consistent UX
- **Don't forget error handling:** Always handle partial failures (some emails sent, some failed)

## Don't Hand-Roll

Problems that look simple but have existing solutions:

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Checkbox icons | Custom SVGs | Lucide Square/CheckSquare/MinusSquare | Consistent styling, dark mode support |
| Selection state | Array with indexOf | Set with has/add/delete | O(1) lookup vs O(n) |
| Click outside handling | Manual event listeners | useRef + useEffect pattern | Already established in PeopleList |
| Email template selection | Client-side logic | Backend determines based on datum-vog | Single source of truth, less data sent |
| Progress feedback | Custom progress bars | Simple loading state + result count | Matches existing patterns, simpler |

**Key insight:** PeopleList.jsx already has 95% of the UI patterns needed. The main work is:
1. Adding checkbox column to VOGList
2. Creating backend bulk endpoint
3. Connecting with TanStack Query mutation

## Common Pitfalls

### Pitfall 1: Stale Selection After Data Refresh

**What goes wrong:** Selected IDs point to items that no longer match the filter
**Why it happens:** User selects items, data refreshes (someone updated a VOG), selected IDs are now invalid
**How to avoid:**
```javascript
// Clear selection when data changes
useEffect(() => {
  setSelectedIds(new Set());
}, [people]); // people is the query result array
```
**Warning signs:** Bulk action includes wrong people or errors about missing people

### Pitfall 2: Missing Email Address Handling

**What goes wrong:** Bulk email fails for volunteers without email addresses
**Why it happens:** Not all people have email in contact_info
**How to avoid:**
- Backend should check for email before attempting send
- Return detailed results: which succeeded, which failed and why
- Frontend should show summary: "3 emails sent, 2 skipped (no email address)"
**Warning signs:** Unhandled errors or confusing user feedback

### Pitfall 3: Template Type Determination

**What goes wrong:** Wrong email template sent (new vs renewal)
**Why it happens:** Client-side logic diverges from what backend expects
**How to avoid:**
- Let backend determine template type based on datum-vog field
- Frontend just sends person IDs, backend does the logic
- EMAIL-04 requirement: auto-select template based on datum-vog presence
**Warning signs:** New volunteers getting renewal template or vice versa

### Pitfall 4: Race Condition on Selection Clear

**What goes wrong:** Selection cleared before mutation completes, losing context for error messages
**Why it happens:** Clearing selection in onSuccess before processing result
**How to avoid:**
```javascript
const handleBulkAction = async () => {
  const idsToProcess = Array.from(selectedIds);
  try {
    const result = await mutation.mutateAsync({ ids: idsToProcess });
    // Show result THEN clear
    showResult(result);
    clearSelection();
  } catch (error) {
    // Keep selection so user can retry
    showError(error);
  }
};
```
**Warning signs:** Error messages don't know which items failed

### Pitfall 5: ACF Field Update for vog-email-verzonden

**What goes wrong:** Frontend shows email sent but field not updated
**Why it happens:** Backend updates wrong field or uses post_meta instead of ACF
**How to avoid:**
```php
// Use ACF's update_field, not update_post_meta
update_field( 'vog-email-verzonden', current_time( 'Y-m-d' ), $person_id );
```
**Warning signs:** VOGList still shows people after email sent, Mail icon doesn't appear

## Code Examples

Verified patterns from codebase:

### Checkbox Column Header
```javascript
// Source: src/pages/People/PeopleList.jsx lines 326-344
<th scope="col" className="pl-4 pr-2 py-3 w-10 bg-gray-50 dark:bg-gray-800 sticky left-0 z-[11]">
  <button
    onClick={onToggleSelectAll}
    className="text-gray-400 hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300"
    title={isAllSelected ? 'Deselect all' : 'Select all'}
  >
    {isAllSelected ? (
      <CheckSquare className="w-5 h-5 text-accent-600 dark:text-accent-400" />
    ) : isSomeSelected ? (
      <MinusSquare className="w-5 h-5 text-accent-600 dark:text-accent-400" />
    ) : (
      <Square className="w-5 h-5" />
    )}
  </button>
</th>
```

### Checkbox Row Cell
```javascript
// Source: src/pages/People/PeopleList.jsx lines 64-75
<td className="pl-4 pr-2 py-3 w-10 sticky left-0 z-[1] bg-inherit">
  <button
    onClick={(e) => { e.preventDefault(); onToggleSelection(person.id); }}
    className="text-gray-400 hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300"
  >
    {isSelected ? (
      <CheckSquare className="w-5 h-5 text-accent-600 dark:text-accent-400" />
    ) : (
      <Square className="w-5 h-5" />
    )}
  </button>
</td>
```

### TanStack Mutation for Bulk Operation
```javascript
// Pattern from src/hooks/usePeople.js useBulkUpdatePeople
export function useBulkVOGActions() {
  const queryClient = useQueryClient();

  const sendEmailsMutation = useMutation({
    mutationFn: ({ ids }) => prmApi.bulkSendVOGEmails(ids),
    onSuccess: () => {
      // Refetch VOG list to update email sent indicators
      queryClient.invalidateQueries({ queryKey: ['people', 'filtered'] });
    },
  });

  const markRequestedMutation = useMutation({
    mutationFn: ({ ids }) => prmApi.bulkMarkVOGRequested(ids),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['people', 'filtered'] });
    },
  });

  return { sendEmailsMutation, markRequestedMutation };
}
```

### Click Outside Handler
```javascript
// Source: src/pages/People/PeopleList.jsx lines 885-908
useEffect(() => {
  const handleClickOutside = (event) => {
    if (
      bulkDropdownRef.current &&
      !bulkDropdownRef.current.contains(event.target)
    ) {
      setShowBulkDropdown(false);
    }
  };

  document.addEventListener('mousedown', handleClickOutside);
  return () => {
    document.removeEventListener('mousedown', handleClickOutside);
  };
}, []);
```

### Results Display Pattern
```javascript
// For showing operation results in a simple format
function BulkActionResult({ sent, failed, errors }) {
  return (
    <div className="p-3 rounded-lg bg-gray-50 dark:bg-gray-900">
      <div className="flex items-center gap-2 text-sm">
        {sent > 0 && (
          <span className="text-green-600 dark:text-green-400">
            {sent} email{sent > 1 ? 's' : ''} verzonden
          </span>
        )}
        {sent > 0 && failed > 0 && <span className="text-gray-400">|</span>}
        {failed > 0 && (
          <span className="text-red-600 dark:text-red-400">
            {failed} mislukt
          </span>
        )}
      </div>
      {errors && errors.length > 0 && (
        <ul className="mt-2 text-xs text-gray-500 dark:text-gray-400">
          {errors.map((e, i) => (
            <li key={i}>{e}</li>
          ))}
        </ul>
      )}
    </div>
  );
}
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Individual API calls per item | Single bulk endpoint | Phase 111 (PeopleList) | Better performance, atomic operations |
| Array.indexOf for selection | Set for selection state | Phase 111 | O(1) lookup performance |
| window.confirm | Modal dialogs | Original design | Consistent UX, better styling |

**Deprecated/outdated:**
- Direct update_post_meta for ACF fields - use update_field() instead
- Individual email sends from frontend loop - use bulk backend endpoint

## Open Questions

1. **Error Recovery UI**
   - What we know: Some emails may fail (no email address)
   - What's unclear: Should user be able to retry just the failed ones?
   - Recommendation: Show results, let user clear selection. If they want to retry, they re-select. Simpler than partial retry.

2. **VOG Requested Date Field**
   - What we know: Need to record date when marked as "VOG requested"
   - What's unclear: Is this the same as vog-email-verzonden or separate?
   - Recommendation: Likely the same - sending email = requesting VOG. Use vog-email-verzonden field.

## Sources

### Primary (HIGH confidence)
- `/Users/joostdevalk/Code/stadion/src/pages/People/PeopleList.jsx` - Complete bulk selection pattern (lines 61-75, 326-344, 736-976, 1481-1531)
- `/Users/joostdevalk/Code/stadion/src/pages/VOG/VOGList.jsx` - Current VOG list to extend (329 lines)
- `/Users/joostdevalk/Code/stadion/includes/class-rest-people.php` - Bulk update endpoint pattern (lines 125-205, 882-958)
- `/Users/joostdevalk/Code/stadion/includes/class-vog-email.php` - VOG email service (lines 132-232)
- `/Users/joostdevalk/Code/stadion/src/hooks/usePeople.js` - useBulkUpdatePeople hook (lines 384-396)
- `/Users/joostdevalk/Code/stadion/src/api/client.js` - API client patterns (lines 119-122)

### Secondary (MEDIUM confidence)
- `/Users/joostdevalk/Code/stadion/src/components/DeleteFieldDialog.jsx` - Modal confirmation pattern
- `/Users/joostdevalk/Code/stadion/.planning/phases/121-bulk-actions/121-CONTEXT.md` - Phase requirements and decisions

### Tertiary (LOW confidence)
None - all findings verified with codebase inspection

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - All patterns exist in codebase
- Architecture: HIGH - Exact patterns from PeopleList.jsx
- Pitfalls: HIGH - Derived from actual codebase and VOG email implementation

**Research date:** 2026-01-30
**Valid until:** 2026-03-30 (60 days - stable patterns, based on established PeopleList implementation)
