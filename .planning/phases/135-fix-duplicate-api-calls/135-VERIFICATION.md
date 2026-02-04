---
phase: 135-fix-duplicate-api-calls
verified: 2026-02-04T11:30:00Z
status: human_needed
score: 3/3 must-haves verified
human_verification:
  - test: "Production page load single API call check"
    expected: "Dashboard shows one request per endpoint in network panel"
    why_human: "Requires runtime verification with browser DevTools network panel"
  - test: "Tab switching does not trigger refetches"
    expected: "Switch away and back to tab - no new API requests appear"
    why_human: "Requires runtime verification with browser DevTools network panel"
  - test: "Page transitions single API call check"
    expected: "Navigate People -> Dashboard -> Teams - single API call per endpoint"
    why_human: "Requires runtime verification with browser DevTools network panel"
---

# Phase 135: Fix Duplicate API Calls Verification Report

**Phase Goal:** All page loads make single API call per endpoint (not 2x)
**Verified:** 2026-02-04T11:30:00Z
**Status:** human_needed
**Re-verification:** No - initial verification

## Goal Achievement

### Observable Truths

| #   | Truth                                                               | Status     | Evidence                                                                                    |
| --- | ------------------------------------------------------------------- | ---------- | ------------------------------------------------------------------------------------------- |
| 1   | Production page loads make single API call per endpoint            | ✓ VERIFIED | QueryClient configured with staleTime: 5min, refetchOnWindowFocus: false                    |
| 2   | Tab switching does not trigger refetches                            | ✓ VERIFIED | refetchOnWindowFocus: false in defaultOptions                                               |
| 3   | React Query deduplicates concurrent requests with same queryKey     | ✓ VERIFIED | Built-in React Query behavior, consistent queryKeys used throughout hooks                   |

**Score:** 3/3 truths verified

### Required Artifacts

| Artifact       | Expected                                   | Status     | Details                                                                                        |
| -------------- | ------------------------------------------ | ---------- | ---------------------------------------------------------------------------------------------- |
| `src/main.jsx` | QueryClient with refetchOnWindowFocus: false | ✓ VERIFIED | Lines 23-31: Complete QueryClient configuration with all required defaults                     |

**Artifact Verification Details:**

**src/main.jsx** (51 lines):
- **Level 1 (Exists):** ✓ EXISTS
- **Level 2 (Substantive):** ✓ SUBSTANTIVE (51 lines, no stubs, proper exports)
- **Level 3 (Wired):** ✓ WIRED
  - QueryClient imported from @tanstack/react-query
  - QueryClientProvider wraps entire App (line 40)
  - Configuration includes:
    - `staleTime: 5 * 60 * 1000` (prevents immediate refetches)
    - `refetchOnWindowFocus: false` (prevents tab-switch refetches)
    - `retry: 1` (limits retry attempts)

### Key Link Verification

| From           | To                  | Via                                | Status     | Details                                                                              |
| -------------- | ------------------- | ---------------------------------- | ---------- | ------------------------------------------------------------------------------------ |
| src/main.jsx   | all useQuery calls  | QueryClientProvider defaultOptions | ✓ WIRED    | QueryClientProvider at line 40 wraps App, all queries inherit defaults              |
| useDashboard   | QueryClient defaults | useQuery hook                     | ✓ WIRED    | No staleTime override, uses defaults (checked src/hooks/useDashboard.js)            |
| current-user queries | QueryClient defaults | useQuery hook                | ✓ WIRED    | Multiple components use ['current-user'] queryKey, React Query deduplicates         |

**Key Link Details:**

1. **QueryClientProvider → useQuery calls:**
   - QueryClientProvider wraps App in main.jsx (line 40)
   - All useQuery calls in hooks (useDashboard, useTodos, useReminders, etc.) inherit defaultOptions
   - Verified in: src/hooks/useDashboard.js - no staleTime overrides in core hooks

2. **React Query Built-in Deduplication:**
   - Multiple components query ['current-user'] (App.jsx ApprovalCheck, FairplayRoute; Layout.jsx Sidebar, UserMenu)
   - React Query automatically merges concurrent requests with same queryKey
   - This is built-in behavior, no additional configuration needed

### Requirements Coverage

| Requirement | Status       | Blocking Issue |
| ----------- | ------------ | -------------- |
| DUP-01: Dashboard page load makes single API call per endpoint | ✓ SATISFIED | None - config verified, needs runtime confirmation |
| DUP-02: All page transitions make single API call per endpoint | ✓ SATISFIED | None - config verified, needs runtime confirmation |
| DUP-03: React Query properly deduplicates concurrent requests | ✓ SATISFIED | None - built-in behavior confirmed, consistent queryKeys |

### Anti-Patterns Found

| File                                | Line | Pattern             | Severity | Impact                                                                                   |
| ----------------------------------- | ---- | ------------------- | -------- | ---------------------------------------------------------------------------------------- |
| src/components/TeamEditModal.jsx    | 235  | staleTime: 0        | ⚠️ WARNING | Modal intentionally refetches on every open (commented as design choice)                 |
| src/components/CommissieEditModal.jsx | 235  | staleTime: 0      | ⚠️ WARNING | Modal intentionally refetches on every open (commented as design choice)                 |

**Anti-pattern Analysis:**

The `staleTime: 0` overrides in modal components are **intentional** per inline comments ("Always refetch when modal opens"). These queries:
- Only execute when `enabled: isOpen && !!team?.id && investorIds.length > 0`
- Are modal-specific, not main page load queries
- Do NOT affect the phase goal (page loads making single API call per endpoint)
- These are conditional queries for edit scenarios, not impacting dashboard/page load performance

**No blocker anti-patterns found** - the phase goal targets page loads, not modal interactions.

### Human Verification Required

The automated checks confirm that the configuration is correct and properly wired. However, the phase goal requires runtime verification that cannot be confirmed through static code analysis.

#### 1. Production Dashboard Page Load

**Test:** 
1. Open production site in browser
2. Open DevTools Network tab, filter by "Fetch/XHR"
3. Hard refresh Dashboard (Cmd/Ctrl+Shift+R to bypass cache)
4. Observe network requests

**Expected:** 
- Single API call to /stadion/v1/dashboard
- Single API call to /wp/v2/users/me (current-user)
- No duplicate request IDs or timestamps
- Each endpoint called exactly once

**Why human:** Requires runtime observation of network traffic with browser DevTools

#### 2. Tab Switching Refetch Prevention

**Test:**
1. Load Dashboard page in production
2. Note network requests (should be single calls per endpoint)
3. Switch to another browser tab for 10+ seconds
4. Return to Dashboard tab
5. Check Network tab for new requests

**Expected:**
- No new API requests when returning to tab
- Existing data remains displayed (from staleTime cache)
- Network panel shows no activity after tab switch

**Why human:** Requires runtime verification of refetch behavior across tab focus events

#### 3. Page Transitions Single API Call

**Test:**
1. In production, navigate: Dashboard → People → Teams → Dashboard
2. Monitor Network tab during each navigation
3. Verify each page load triggers appropriate API calls

**Expected:**
- Dashboard: /stadion/v1/dashboard (once)
- People: /wp/v2/people (once)
- Teams: /wp/v2/teams (once)
- Dashboard (return): No new API call (cache still fresh from staleTime: 5min)

**Why human:** Requires runtime verification of navigation flow and cache behavior

#### 4. React Query Concurrent Request Deduplication

**Test:**
1. Open production site
2. Open React DevTools (if available) or Network panel
3. Navigate to Dashboard (where multiple components query ['current-user'])
4. Observe whether concurrent ['current-user'] requests are merged

**Expected:**
- Only one network request to /wp/v2/users/me despite multiple useQuery(['current-user']) calls
- React Query's built-in deduplication merges concurrent requests

**Why human:** Requires runtime observation to confirm deduplication behavior in production environment

### Verification Context

**Development vs Production:**
- React 18 StrictMode intentionally double-mounts components in development
- This can cause apparent duplicate requests even with correct configuration
- Production builds do NOT have StrictMode double-mount behavior
- **All verification should be performed on PRODUCTION** per plan guidance

**What the configuration achieves:**
1. `staleTime: 5 * 60 * 1000` - Data is "fresh" for 5 minutes, no refetch on remount within this window
2. `refetchOnWindowFocus: false` - Tab switches don't trigger new API calls
3. React Query's built-in deduplication - Concurrent requests with same queryKey share a single network call
4. `retry: 1` - Limits retry attempts to reduce redundant failed requests

---

_Verified: 2026-02-04T11:30:00Z_
_Verifier: Claude (gsd-verifier)_
