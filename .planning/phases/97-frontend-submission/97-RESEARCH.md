# Phase 97: Frontend Submission - Research

**Researched:** 2026-01-21
**Domain:** React frontend for feedback submission with TanStack Query, React Router, and Tailwind CSS
**Confidence:** HIGH

## Summary

This phase implements the frontend UI for submitting and viewing feedback within the Stadion React SPA. The backend REST API at `/rondo/v1/feedback` is already complete (Phase 96), so this phase focuses purely on React components, routing, API integration, and user experience.

The codebase has well-established patterns for list pages (TodosList, DatesList, PeopleList), detail views (PersonDetail, TeamDetail), modal forms (PersonEditModal, TeamEditModal), and TanStack Query hooks (usePeople, useTodos, useDashboard). The frontend implementation should follow these existing patterns exactly.

Key UI requirements include:
- Navigation via sidebar menu item
- List view with type/status filtering
- Form with conditional fields based on feedback type
- Optional system info capture (browser, version, URL)
- File attachments via WordPress media library

**Primary recommendation:** Follow the TodosList/DatesList pattern for the list page, PersonEditModal pattern for the submission form, and create a useFeedback hook following usePeople/useTodos patterns.

## Standard Stack

The established libraries/tools for this domain:

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| React | 18.2.0 | UI components | Already used |
| React Router | 6.21.0 | Navigation and routing | Already used for all routes |
| TanStack Query | 5.17.0 | Server state management, caching | Already used for all API calls |
| Tailwind CSS | 3.4.0 | Styling | Already used throughout |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| react-hook-form | 7.49.0 | Form state management | Modal form validation |
| lucide-react | 0.309.0 | Icons | UI icons for badges, buttons |
| date-fns | 3.2.0 | Date formatting | Display created/modified dates |
| axios | 1.6.0 | HTTP client | Via existing `api/client.js` |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| react-hook-form | useState | react-hook-form already used for all modals, provides validation |
| Modal form | Separate page route | Modals match existing UX patterns (PersonEditModal, etc.) |
| Inline form | Wizard/multi-step | Simple form, single page is appropriate |

**Installation:**
No new packages required - all dependencies are already in place.

## Architecture Patterns

### Recommended Project Structure
```
src/
├── api/
│   └── client.js              # ADD feedback API methods to prmApi
├── hooks/
│   └── useFeedback.js         # NEW: TanStack Query hooks for feedback
├── pages/
│   └── Feedback/
│       ├── FeedbackList.jsx   # NEW: List view with filtering
│       └── FeedbackDetail.jsx # NEW: Single feedback view
├── components/
│   └── FeedbackModal.jsx      # NEW: Submission form modal
└── App.jsx                    # ADD routes: /feedback, /feedback/:id
```

### Pattern 1: API Client Extensions
**What:** Add feedback methods to existing `prmApi` object in `api/client.js`
**When to use:** All REST API calls
**Example:**
```javascript
// Source: src/api/client.js (existing pattern)
export const prmApi = {
  // ... existing methods

  // Feedback
  getFeedbackList: (params) => api.get('/rondo/v1/feedback', { params }),
  getFeedback: (id) => api.get(`/rondo/v1/feedback/${id}`),
  createFeedback: (data) => api.post('/rondo/v1/feedback', data),
  updateFeedback: (id, data) => api.put(`/rondo/v1/feedback/${id}`, data),
  deleteFeedback: (id) => api.delete(`/rondo/v1/feedback/${id}`),
};
```

### Pattern 2: TanStack Query Hook Structure
**What:** Query keys factory and hooks for list/detail/mutations
**When to use:** All data fetching
**Example:**
```javascript
// Source: src/hooks/usePeople.js (existing pattern)
export const feedbackKeys = {
  all: ['feedback'],
  lists: () => [...feedbackKeys.all, 'list'],
  list: (filters) => [...feedbackKeys.lists(), filters],
  details: () => [...feedbackKeys.all, 'detail'],
  detail: (id) => [...feedbackKeys.details(), id],
};

export function useFeedbackList(filters = {}) {
  return useQuery({
    queryKey: feedbackKeys.list(filters),
    queryFn: async () => {
      const response = await prmApi.getFeedbackList(filters);
      return response.data;
    },
  });
}

export function useCreateFeedback() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (data) => prmApi.createFeedback(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: feedbackKeys.lists() });
    },
  });
}
```

### Pattern 3: List Page with Filtering
**What:** Filter controls with state-driven API params
**When to use:** FeedbackList page
**Example:**
```javascript
// Source: src/pages/Todos/TodosList.jsx (existing pattern)
export default function FeedbackList() {
  const [typeFilter, setTypeFilter] = useState('');  // '' | 'bug' | 'feature_request'
  const [statusFilter, setStatusFilter] = useState(''); // '' | 'new' | 'in_progress' | 'resolved' | 'declined'

  const { data: feedback, isLoading } = useFeedbackList({
    type: typeFilter || undefined,
    status: statusFilter || undefined,
  });

  // Render filter buttons + list
}
```

### Pattern 4: Modal Form with react-hook-form
**What:** Reusable modal component with form validation
**When to use:** FeedbackModal for create/edit
**Example:**
```javascript
// Source: src/components/PersonEditModal.jsx (existing pattern)
export default function FeedbackModal({
  isOpen,
  onClose,
  onSubmit,
  isLoading,
  feedback = null, // For editing
}) {
  const { register, handleSubmit, watch, reset, formState: { errors } } = useForm({
    defaultValues: {
      title: '',
      content: '',
      feedback_type: 'bug',
      // ... conditional fields
    },
  });

  const feedbackType = watch('feedback_type');

  // Reset form when modal opens
  useEffect(() => {
    if (isOpen) {
      reset(feedback ? mapFeedbackToForm(feedback) : defaultValues);
    }
  }, [isOpen, feedback, reset]);

  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 z-50 ...">
      {/* Modal structure matching PersonEditModal */}
    </div>
  );
}
```

### Pattern 5: Conditional Form Fields
**What:** Show/hide fields based on feedback type selection
**When to use:** Bug vs Feature Request fields
**Example:**
```javascript
// Bug-specific fields
{feedbackType === 'bug' && (
  <>
    <div>
      <label className="label">Steps to reproduce</label>
      <textarea {...register('steps_to_reproduce')} className="input" rows={3} />
    </div>
    <div>
      <label className="label">Expected behavior</label>
      <textarea {...register('expected_behavior')} className="input" rows={2} />
    </div>
    <div>
      <label className="label">Actual behavior</label>
      <textarea {...register('actual_behavior')} className="input" rows={2} />
    </div>
  </>
)}

// Feature request fields
{feedbackType === 'feature_request' && (
  <div>
    <label className="label">Use case</label>
    <textarea {...register('use_case')} className="input" rows={3} />
  </div>
)}
```

### Pattern 6: System Info Capture (Opt-in)
**What:** Checkbox to include browser/version/URL automatically
**When to use:** Feedback submission form
**Example:**
```javascript
// Capture system info on submit if opted in
const handleFormSubmit = (data) => {
  const submitData = { ...data };

  if (data.include_system_info) {
    submitData.browser_info = navigator.userAgent;
    submitData.app_version = window.stadionConfig?.version || 'unknown';
    submitData.url_context = window.location.href;
  }

  delete submitData.include_system_info; // Don't send checkbox value
  onSubmit(submitData);
};
```

### Pattern 7: File Attachment Upload
**What:** Upload files to WordPress media, store IDs in feedback
**When to use:** Screenshot attachments
**Example:**
```javascript
// Source: src/components/CustomFieldsEditModal.jsx MediaInput pattern
const [attachments, setAttachments] = useState([]);
const [isUploading, setIsUploading] = useState(false);

const handleFileUpload = async (files) => {
  setIsUploading(true);
  try {
    const newAttachments = [];
    for (const file of files) {
      const response = await wpApi.uploadMedia(file);
      newAttachments.push({
        id: response.data.id,
        url: response.data.source_url,
        thumbnail: response.data.media_details?.sizes?.thumbnail?.source_url || response.data.source_url,
        title: file.name,
      });
    }
    setAttachments(prev => [...prev, ...newAttachments]);
  } catch (error) {
    console.error('Upload failed:', error);
  } finally {
    setIsUploading(false);
  }
};

// On submit, send array of attachment IDs
const attachmentIds = attachments.map(a => a.id);
```

### Pattern 8: Status/Type Badge Components
**What:** Colored badges for status and type display
**When to use:** List items and detail view
**Example:**
```javascript
// Status badges matching existing UI patterns
const statusColors = {
  new: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
  in_progress: 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400',
  resolved: 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
  declined: 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-400',
};

const typeColors = {
  bug: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
  feature_request: 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400',
};

function StatusBadge({ status }) {
  return (
    <span className={`px-2 py-0.5 rounded-full text-xs font-medium ${statusColors[status]}`}>
      {status.replace('_', ' ')}
    </span>
  );
}
```

### Anti-Patterns to Avoid
- **Separate API client file:** Add to existing `prmApi` object, don't create new file
- **Class components:** Use functional components with hooks
- **Redux for server state:** Use TanStack Query (already in use)
- **Inline styles:** Use Tailwind CSS classes
- **Custom modal implementation:** Follow existing modal pattern with fixed positioning
- **Direct fetch() calls:** Use axios via api/client.js

## Don't Hand-Roll

Problems that look simple but have existing solutions:

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Form validation | Custom validation logic | react-hook-form `register` with rules | Already used, handles all edge cases |
| Loading states | Custom loading flags | TanStack Query `isLoading`, `isPending` | Built-in, consistent |
| Cache invalidation | Manual state updates | `queryClient.invalidateQueries()` | Automatic refetching |
| File uploads | Custom upload component | wpApi.uploadMedia pattern | Already implemented |
| Date formatting | Manual formatting | date-fns `format()` | Consistent with codebase |
| Optimistic updates | Manual state management | TanStack Query `onMutate` | Built-in support |
| URL state | Custom URL parsing | React Router `useSearchParams` | Built-in, handles encoding |

**Key insight:** The codebase has mature patterns for every common operation. Copy existing patterns rather than inventing new approaches.

## Common Pitfalls

### Pitfall 1: Not Following Existing Modal Pattern
**What goes wrong:** Inconsistent UX, z-index issues, scroll lock problems
**Why it happens:** Building modal from scratch instead of copying
**How to avoid:** Copy PersonEditModal.jsx structure exactly - fixed positioning, backdrop, overflow handling
**Warning signs:** Modal doesn't close on backdrop click, content scrolls behind modal

### Pitfall 2: Forgetting Query Key Invalidation
**What goes wrong:** List doesn't update after create/edit
**Why it happens:** Not invalidating related query keys
**How to avoid:** In mutation `onSuccess`, invalidate `feedbackKeys.lists()`
**Warning signs:** Need to manually refresh page to see changes

### Pitfall 3: Not Handling Loading/Error States
**What goes wrong:** Blank screens, poor UX
**Why it happens:** Only handling success case
**How to avoid:** Check `isLoading`, `error` from useQuery, show appropriate UI
**Warning signs:** White screen while loading, no feedback on errors

### Pitfall 4: Conditional Field Reset Issues
**What goes wrong:** Stale values from previous type selection
**Why it happens:** Not resetting conditional fields when type changes
**How to avoid:** Reset related fields when feedback_type changes
**Warning signs:** Bug report contains feature request fields or vice versa

### Pitfall 5: File Upload Without Progress Feedback
**What goes wrong:** User thinks upload is stuck
**Why it happens:** No visual loading indicator
**How to avoid:** Show spinner/progress during upload, disable submit
**Warning signs:** User submits multiple times, files missing

### Pitfall 6: Navigation Not Added to Layout
**What goes wrong:** Users can't find feedback feature
**Why it happens:** Forgetting to add sidebar menu item
**How to avoid:** Add to `navigation` array in Layout.jsx
**Warning signs:** Direct URL works but no way to navigate there

## Code Examples

Verified patterns from existing codebase:

### Route Registration in App.jsx
```javascript
// Source: src/App.jsx (existing pattern)
// Add inside Routes under protected routes
<Route path="/feedback" element={<FeedbackList />} />
<Route path="/feedback/:id" element={<FeedbackDetail />} />
```

### Navigation Item in Layout.jsx
```javascript
// Source: src/components/layout/Layout.jsx (existing pattern)
import { MessageSquare } from 'lucide-react'; // Or Bug icon

const navigation = [
  // ... existing items
  { name: 'Feedback', href: '/feedback', icon: MessageSquare },
  // ... Settings goes last
];
```

### List Page Empty State
```javascript
// Source: src/pages/Dates/DatesList.jsx (existing pattern)
{!isLoading && !error && feedback?.length === 0 && (
  <div className="card p-12 text-center">
    <MessageSquare className="w-12 h-12 text-gray-300 dark:text-gray-600 mx-auto mb-4" />
    <h3 className="text-lg font-medium mb-1">No feedback yet</h3>
    <p className="text-gray-500 dark:text-gray-400 mb-4">
      Report bugs or request features.
    </p>
    <button onClick={() => setShowModal(true)} className="btn-primary">
      <Plus className="w-4 h-4 mr-2" />
      Submit feedback
    </button>
  </div>
)}
```

### Filter Button Group
```javascript
// Source: src/pages/Todos/TodosList.jsx (existing pattern)
<div className="flex rounded-lg border border-gray-200 dark:border-gray-700 p-0.5">
  <button
    onClick={() => setTypeFilter('')}
    className={`px-3 py-1 text-sm rounded-md transition-colors ${
      typeFilter === ''
        ? 'bg-accent-100 dark:bg-accent-800 text-accent-700 dark:text-accent-300'
        : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200'
    }`}
  >
    All
  </button>
  <button
    onClick={() => setTypeFilter('bug')}
    className={`px-3 py-1 text-sm rounded-md transition-colors ${
      typeFilter === 'bug'
        ? 'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-300'
        : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200'
    }`}
  >
    Bugs
  </button>
  <button
    onClick={() => setTypeFilter('feature_request')}
    className={`px-3 py-1 text-sm rounded-md transition-colors ${
      typeFilter === 'feature_request'
        ? 'bg-purple-100 dark:bg-purple-900/30 text-purple-700 dark:text-purple-300'
        : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200'
    }`}
  >
    Features
  </button>
</div>
```

### Drag and Drop File Upload Zone
```javascript
// Source: src/components/PersonEditModal.jsx (existing pattern)
const [dragActive, setDragActive] = useState(false);

const handleDrag = useCallback((e) => {
  e.preventDefault();
  e.stopPropagation();
  if (e.type === 'dragenter' || e.type === 'dragover') {
    setDragActive(true);
  } else if (e.type === 'dragleave') {
    setDragActive(false);
  }
}, []);

const handleDrop = useCallback((e) => {
  e.preventDefault();
  e.stopPropagation();
  setDragActive(false);

  if (e.dataTransfer.files && e.dataTransfer.files.length > 0) {
    handleFileUpload(Array.from(e.dataTransfer.files));
  }
}, []);

// In JSX:
<div
  className={`relative rounded-lg border-2 border-dashed p-4 text-center transition-colors ${
    dragActive
      ? 'border-accent-500 bg-accent-50 dark:bg-accent-800'
      : 'border-gray-300 hover:border-gray-400 dark:border-gray-600'
  }`}
  onDragEnter={handleDrag}
  onDragLeave={handleDrag}
  onDragOver={handleDrag}
  onDrop={handleDrop}
>
  <input
    type="file"
    accept="image/*"
    multiple
    onChange={(e) => handleFileUpload(Array.from(e.target.files))}
    className="absolute inset-0 w-full h-full opacity-0 cursor-pointer"
  />
  <Upload className="w-6 h-6 mx-auto text-gray-400 mb-2" />
  <p className="text-sm text-gray-600 dark:text-gray-300">
    Drop screenshots or <span className="text-accent-600 dark:text-accent-400">browse</span>
  </p>
</div>
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| useState for server data | TanStack Query | Already standard | Automatic caching, refetching |
| Class components | Functional with hooks | React 16.8+ | Simpler, more composable |
| CSS modules | Tailwind CSS | Already standard | Consistent styling |
| Manual form handling | react-hook-form | Already standard | Less boilerplate |

**Deprecated/outdated:**
- `componentDidMount`: Use `useEffect`
- Redux for server state: Use TanStack Query
- Inline styles: Use Tailwind classes

## Open Questions

Things that couldn't be fully resolved:

1. **Detail view edit capability**
   - What we know: Users own their feedback, can edit via API
   - What's unclear: Should detail view have edit button, or edit via list only?
   - Recommendation: Add edit button on detail view for owners (matches PersonDetail pattern)

2. **Pagination vs infinite scroll**
   - What we know: API supports pagination with per_page/page params
   - What's unclear: Expected volume of feedback items
   - Recommendation: Start with simple pagination (like existing lists), add infinite scroll later if needed

## Sources

### Primary (HIGH confidence)
- `src/pages/Todos/TodosList.jsx` - List page pattern
- `src/pages/Dates/DatesList.jsx` - List with modal pattern
- `src/components/PersonEditModal.jsx` - Form modal pattern
- `src/hooks/usePeople.js` - TanStack Query hooks pattern
- `src/hooks/useDashboard.js` - Simple hooks pattern
- `src/api/client.js` - API client pattern
- `src/components/layout/Layout.jsx` - Navigation pattern
- `src/App.jsx` - Route registration pattern
- `src/components/CustomFieldsEditModal.jsx` - File upload pattern

### Secondary (MEDIUM confidence)
- TanStack Query documentation - Mutation patterns
- react-hook-form documentation - Watch and conditional fields
- Tailwind CSS documentation - Utility classes

### Tertiary (LOW confidence)
- None

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - All libraries already in use
- Architecture: HIGH - Extensive existing patterns to follow
- Pitfalls: HIGH - Based on direct codebase analysis

**Research date:** 2026-01-21
**Valid until:** 90 days (stable React patterns in this codebase)
