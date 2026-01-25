# Phase 98: Admin Management - Research

**Researched:** 2026-01-21
**Domain:** React frontend admin UI for feedback management with status workflow and application password management
**Confidence:** HIGH

## Summary

This phase implements admin-only feedback management within the Settings page, plus a user-facing application password UI for API access. The codebase already has extensive patterns for all required components: tab/subtab navigation (ConnectionsTab), sortable table views (PeopleList), confirmation dialogs (window.confirm), and application password management (existing CardDAV subtab).

The backend REST API at `/stadion/v1/feedback` is fully complete (Phase 96), including admin-only permissions for status/priority changes and filtering/sorting support. The frontend implementation should follow existing Settings page patterns exactly.

Key implementation areas:
1. Add "Feedback" tab to Settings Admin section (admin-only)
2. Table view with filtering by type/status/priority and sortable columns
3. Status workflow controls (dropdown or inline buttons)
4. Priority assignment (dropdown)
5. Add "API Access" tab to Settings with application password management (all users)

**Primary recommendation:** Follow existing Settings page patterns for tabs/subtabs, PeopleList pattern for sortable tables, and existing App Password UI (currently in ConnectionsTab/CardDAV) for the API Access tab.

## Standard Stack

The established libraries/tools for this domain:

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| React | 18.2.0 | UI components | Already used |
| TanStack Query | 5.17.0 | Server state management | Already used throughout |
| Tailwind CSS | 3.4.0 | Styling | Already used throughout |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| lucide-react | 0.309.0 | Icons | UI icons for actions |
| date-fns | 3.2.0 | Date formatting | Display dates |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Table view | Card list | Tables better for admin data density, existing pattern in PeopleList |
| window.confirm | Custom modal | Simpler, already used for all confirmations in Settings |
| Inline status edit | Separate modal | Inline faster for workflow, matches admin efficiency needs |

**Installation:**
No new packages required - all dependencies already in place.

## Architecture Patterns

### Recommended Project Structure
```
src/pages/Settings/Settings.jsx    # Extend with Feedback and API Access tabs
src/hooks/useFeedback.js           # Extend with admin hooks if needed
src/api/client.js                  # Already has all feedback endpoints
```

### Pattern 1: Settings Tab Structure
**What:** Add tabs to existing TABS array, render content via renderTabContent switch
**When to use:** Adding Feedback and API Access tabs
**Example:**
```javascript
// Source: src/pages/Settings/Settings.jsx
const TABS = [
  { id: 'appearance', label: 'Appearance', icon: Palette },
  { id: 'connections', label: 'Connections', icon: Share2 },
  { id: 'notifications', label: 'Notifications', icon: Bell },
  { id: 'api-access', label: 'API Access', icon: Key },  // NEW - all users
  { id: 'data', label: 'Data', icon: Database },
  { id: 'admin', label: 'Admin', icon: Shield, adminOnly: true },
  { id: 'about', label: 'About', icon: Info },
];

// Admin tab adds link to feedback management
// OR Add 'feedback' tab with adminOnly: true
```

### Pattern 2: Admin Link to Separate Page
**What:** AdminTab links to separate management pages (like User Approval)
**When to use:** Complex admin management UIs
**Example:**
```javascript
// Source: src/pages/Settings/Settings.jsx - AdminTab
<Link
  to="/settings/feedback"
  className="block p-4 rounded-lg border border-gray-200 hover:bg-gray-50 hover:border-gray-300 transition-colors"
>
  <p className="font-medium">Feedback management</p>
  <p className="text-sm text-gray-500">View and manage all user feedback</p>
</Link>
```

### Pattern 3: Sortable Table with Clickable Headers
**What:** Table with sortable columns using SortableHeader component
**When to use:** Admin feedback list view
**Example:**
```javascript
// Source: src/pages/People/PeopleList.jsx
function SortableHeader({ field, label, currentSortField, currentSortOrder, onSort }) {
  const isActive = currentSortField === field;
  return (
    <th scope="col" className="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider bg-gray-50 dark:bg-gray-800">
      <button
        onClick={() => onSort(field)}
        className="flex items-center gap-1 hover:text-gray-700 dark:hover:text-gray-200 cursor-pointer"
      >
        {label}
        {isActive && (
          currentSortOrder === 'asc' ? (
            <ArrowUp className="w-3 h-3" />
          ) : (
            <ArrowDown className="w-3 h-3" />
          )
        )}
      </button>
    </th>
  );
}
```

### Pattern 4: Inline Status/Priority Controls
**What:** Dropdown selects for changing status and priority inline
**When to use:** Quick admin actions in table rows
**Example:**
```javascript
// Inline select for status
<select
  value={item.status}
  onChange={(e) => handleStatusChange(item.id, e.target.value)}
  className="text-xs border rounded px-2 py-1 bg-white dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200"
>
  <option value="new">New</option>
  <option value="in_progress">In Progress</option>
  <option value="resolved">Resolved</option>
  <option value="declined">Declined</option>
</select>
```

### Pattern 5: Confirmation Dialog
**What:** Use window.confirm for destructive actions
**When to use:** Before revoking passwords, declining feedback
**Example:**
```javascript
// Source: src/pages/Settings/Settings.jsx
const handleDeleteAppPassword = async (uuid, name) => {
  if (!confirm(`Are you sure you want to revoke the app password "${name}"? Any devices using this password will no longer be able to sync.`)) {
    return;
  }
  // proceed with deletion
};
```

### Pattern 6: Application Password List with One-Time Modal
**What:** List with revoke button, create modal showing password once
**When to use:** API Access tab
**Example:**
```javascript
// Source: existing pattern in Settings.jsx CardDAV subtab
// Password visible only in modal after creation
{newPassword && (
  <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
    <div className="bg-white dark:bg-gray-800 rounded-lg p-6 max-w-md">
      <h3>Application Password Created</h3>
      <p className="text-sm text-gray-500">Copy this password now. It won't be shown again.</p>
      <div className="flex items-center gap-2 mt-4">
        <code className="flex-1 p-2 bg-gray-100 dark:bg-gray-700 rounded font-mono text-sm">
          {newPassword}
        </code>
        <button onClick={copyNewPassword} className="btn-secondary">
          {passwordCopied ? <Check /> : <Copy />}
        </button>
      </div>
      <button onClick={() => setNewPassword(null)} className="btn-primary w-full mt-4">
        Done
      </button>
    </div>
  </div>
)}
```

### Anti-Patterns to Avoid
- **Custom confirmation modals:** Use window.confirm for consistency with existing codebase
- **Separate admin feedback page outside Settings:** Keep in Settings (matches User Approval pattern) or use AdminTab links
- **Client-side only sorting:** Use API sorting params for consistency and scalability
- **Creating new hooks file:** Extend existing useFeedback.js if needed

## Don't Hand-Roll

Problems that look simple but have existing solutions:

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Sortable tables | Custom table component | Copy PeopleList SortableHeader | Battle-tested, consistent |
| Confirmation dialogs | Custom modal | window.confirm | Already used throughout Settings |
| Tab navigation | Custom tabs | Extend existing TABS array | Consistent with Settings page |
| Password copy | Custom clipboard | navigator.clipboard (existing) | Already used for passwords |
| Date formatting | Manual | date-fns format/formatDistanceToNow | Consistent with codebase |

**Key insight:** All required UI patterns already exist in the codebase. Copy and adapt rather than invent.

## Common Pitfalls

### Pitfall 1: Forgetting Admin-Only Check
**What goes wrong:** Non-admins can see/access feedback management
**Why it happens:** Not checking isAdmin for tab visibility and routes
**How to avoid:** Use `adminOnly: true` on tab config, check `isAdmin` in routes
**Warning signs:** Non-admin users see "Admin" or "Feedback" tabs

### Pitfall 2: Not Using API Sorting
**What goes wrong:** Inconsistent behavior, performance issues
**Why it happens:** Sorting client-side when API supports orderby/order params
**How to avoid:** Pass orderby and order params to useFeedbackList
**Warning signs:** Sort doesn't persist across page loads, slow with many items

### Pitfall 3: Stale Data After Status Change
**What goes wrong:** Table shows old status after update
**Why it happens:** Not invalidating query cache after mutation
**How to avoid:** useUpdateFeedback already invalidates - verify it's being used
**Warning signs:** Need to refresh page to see changes

### Pitfall 4: Password Shown After Modal Close
**What goes wrong:** Security risk - password remains visible
**Why it happens:** Not clearing newPassword state
**How to avoid:** Set newPassword to null when modal closes
**Warning signs:** Password visible when reopening create form

### Pitfall 5: Missing Loading States
**What goes wrong:** UI feels broken during operations
**Why it happens:** Not showing loading indicator during mutations
**How to avoid:** Use isPending from mutation hooks, disable buttons
**Warning signs:** Button clicks with no feedback, multiple submissions

## Code Examples

Verified patterns from existing codebase:

### Filter State with URL Params
```javascript
// Source: src/pages/Settings/Settings.jsx
const [searchParams, setSearchParams] = useSearchParams();
const activeTab = searchParams.get('tab') || 'appearance';

const setActiveTab = (tab) => {
  setSearchParams({ tab });
};
```

### Feedback List with API Filters
```javascript
// Source: src/hooks/useFeedback.js (extend)
export function useFeedbackListAdmin(filters = {}) {
  return useQuery({
    queryKey: feedbackKeys.list({ ...filters, admin: true }),
    queryFn: async () => {
      const response = await prmApi.getFeedbackList({
        ...filters,
        per_page: 50,  // Admin sees more at once
      });
      return response.data;
    },
    enabled: !!filters.isAdmin,
  });
}
```

### Table Row with Inline Controls
```javascript
// Admin feedback row with status/priority controls
function FeedbackRow({ item, onStatusChange, onPriorityChange, isUpdating }) {
  return (
    <tr className="hover:bg-gray-50 dark:hover:bg-gray-700">
      <td className="px-4 py-3">
        <span className={`px-2 py-0.5 rounded-full text-xs ${typeColors[item.feedback_type]}`}>
          {item.feedback_type === 'bug' ? 'Bug' : 'Feature'}
        </span>
      </td>
      <td className="px-4 py-3 font-medium dark:text-gray-100">{item.title}</td>
      <td className="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{item.author_name}</td>
      <td className="px-4 py-3">
        <select
          value={item.status}
          onChange={(e) => onStatusChange(item.id, e.target.value)}
          disabled={isUpdating}
          className="text-xs border rounded px-2 py-1 bg-white dark:bg-gray-700 dark:border-gray-600"
        >
          <option value="new">New</option>
          <option value="in_progress">In Progress</option>
          <option value="resolved">Resolved</option>
          <option value="declined">Declined</option>
        </select>
      </td>
      <td className="px-4 py-3">
        <select
          value={item.priority}
          onChange={(e) => onPriorityChange(item.id, e.target.value)}
          disabled={isUpdating}
          className="text-xs border rounded px-2 py-1 bg-white dark:bg-gray-700 dark:border-gray-600"
        >
          <option value="low">Low</option>
          <option value="medium">Medium</option>
          <option value="high">High</option>
          <option value="critical">Critical</option>
        </select>
      </td>
      <td className="px-4 py-3 text-sm text-gray-500">
        {format(new Date(item.created_at), 'MMM d, yyyy')}
      </td>
      <td className="px-4 py-3">
        <Link to={`/feedback/${item.id}`} className="text-accent-600 hover:underline text-sm">
          View
        </Link>
      </td>
    </tr>
  );
}
```

### API Access Tab with Password Management
```javascript
// Copy existing pattern from ConnectionsCardDAVSubtab
function APIAccessTab({
  appPasswords, appPasswordsLoading,
  handleCreateAppPassword, creatingPassword,
  newPassword, setNewPassword, copyNewPassword, passwordCopied,
  handleDeleteAppPassword, formatDate
}) {
  return (
    <div className="space-y-6">
      <div className="card p-6">
        <h2 className="text-lg font-semibold mb-4 dark:text-gray-100">Application Passwords</h2>
        <p className="text-sm text-gray-600 mb-4 dark:text-gray-400">
          Create application passwords for API access from external tools and scripts.
        </p>

        {/* Create form */}
        <form onSubmit={handleCreateAppPassword} className="flex gap-2 mb-6">
          <input
            type="text"
            value={newPasswordName}
            onChange={(e) => setNewPasswordName(e.target.value)}
            placeholder="Password name (e.g., 'My Script')"
            className="input flex-1"
          />
          <button type="submit" disabled={creatingPassword} className="btn-primary">
            {creatingPassword ? 'Creating...' : 'Create'}
          </button>
        </form>

        {/* Password list */}
        {appPasswordsLoading ? (
          <div className="animate-pulse h-20 bg-gray-200 dark:bg-gray-700 rounded" />
        ) : appPasswords.length === 0 ? (
          <p className="text-sm text-gray-500 dark:text-gray-400">No application passwords yet.</p>
        ) : (
          <div className="space-y-2">
            {appPasswords.map((password) => (
              <div key={password.uuid} className="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                <div>
                  <p className="font-medium dark:text-gray-100">{password.name}</p>
                  <p className="text-xs text-gray-500 dark:text-gray-400">
                    Last used: {formatDate(password.last_used)}
                  </p>
                </div>
                <button
                  onClick={() => handleDeleteAppPassword(password.uuid, password.name)}
                  className="text-red-600 hover:text-red-700 dark:text-red-400 text-sm"
                >
                  Revoke
                </button>
              </div>
            ))}
          </div>
        )}
      </div>

      {/* New password modal */}
      {newPassword && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
          {/* ... modal content ... */}
        </div>
      )}
    </div>
  );
}
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Custom modals for confirm | window.confirm | Already standard | Simpler, consistent |
| Separate filter state | URL params for filters | Already standard | Shareable URLs, back button works |
| Manual cache invalidation | TanStack Query invalidateQueries | Already standard | Automatic refetching |

**Deprecated/outdated:**
- None - all patterns are current

## Open Questions

Things that couldn't be fully resolved:

1. **Feedback tab vs AdminTab link**
   - What we know: AdminTab uses links for User Approval, Labels, etc.
   - What's unclear: Should feedback be a separate tab or a link in AdminTab?
   - Recommendation: Use link in AdminTab to `/settings/feedback` (matches existing pattern)

2. **API Access tab visibility**
   - What we know: CONTEXT.md says dedicated tab for all users
   - What's unclear: Exact position in tab order
   - Recommendation: Place before "Data" tab, visible to all users

## Sources

### Primary (HIGH confidence)
- `src/pages/Settings/Settings.jsx` - Existing tab structure, app password handling, confirmation patterns
- `src/pages/People/PeopleList.jsx` - SortableHeader component, table structure
- `src/hooks/useFeedback.js` - Existing TanStack Query hooks
- `src/api/client.js` - Feedback API endpoints
- `includes/class-rest-feedback.php` - Backend API structure, permissions

### Secondary (MEDIUM confidence)
- Phase 97 research - Feedback UI patterns
- TanStack Query documentation - Mutation patterns

### Tertiary (LOW confidence)
- None

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - All libraries already in use
- Architecture: HIGH - Extensive existing patterns to follow
- Pitfalls: HIGH - Based on direct codebase analysis

**Research date:** 2026-01-21
**Valid until:** 90 days (stable React patterns in this codebase)
