---
phase: 182-rabobank-payment-integration
plan: 02
subsystem: finance-rabobank-frontend
tags:
  - frontend
  - oauth-ui
  - react
  - payment-integration
  - rabobank
dependency_graph:
  requires:
    - phase: 182
      plan: 01
      artifact: "RabobankOAuth REST endpoints"
    - phase: 182
      plan: 01
      artifact: "RabobankPayment REST endpoint"
  provides:
    - "Rabobank connection status UI in finance settings"
    - "OAuth authorization flow UI (connect/disconnect)"
    - "API client methods for Rabobank endpoints"
    - "React hooks for Rabobank status and payment links"
  affects:
    - "Finance settings page (adds Rabobank connection management)"
tech_stack:
  added:
    - "TanStack Query hooks for Rabobank OAuth state"
  patterns:
    - "OAuth callback URL parameter handling"
    - "React Router useSearchParams for callback state"
    - "Conditional UI based on connection status"
    - "Disabled button states with tooltips"
key_files:
  created: []
  modified:
    - path: "src/api/client.js"
      changes: "Added 4 Rabobank API methods (status, authorize, disconnect, payment link)"
    - path: "src/hooks/useFinanceSettings.js"
      changes: "Added 3 hooks: useRabobankStatus, useDisconnectRabobank, useCreatePaymentLink"
    - path: "src/pages/Finance/FinanceSettings.jsx"
      changes: "Added Rabobank connection status card and OAuth flow handling"
decisions:
  - decision: "Connection status card above credentials form"
    rationale: "User needs to see connection state before managing credentials"
    impact: "Status is immediately visible when opening settings"
  - decision: "Disable connect button when no credentials saved"
    rationale: "OAuth flow will fail without client_id/client_secret, so prevent initiation"
    impact: "User must save credentials before connecting"
  - decision: "URL parameter cleanup after OAuth callback"
    rationale: "Prevent accidental message re-display on page refresh"
    impact: "Success/error params removed from URL after showing message"
  - decision: "Confirmation dialog on disconnect"
    rationale: "Prevent accidental disconnection requiring re-authorization"
    impact: "User must explicitly confirm disconnect action"
metrics:
  duration: 180
  tasks_completed: 2
  files_created: 0
  files_modified: 3
  lines_added: 250
  commits:
    - hash: "f947a371"
      message: "feat(182-02): add Rabobank API client methods and status hooks"
    - hash: "86f5fa1e"
      message: "feat(182-02): add Rabobank connection management UI to finance settings"
  completed_at: "2026-02-16"
---

# Phase 182 Plan 02: Rabobank Frontend UI Integration Summary

**One-liner:** React UI for Rabobank OAuth connection management and payment link creation in finance settings.

## What Was Built

Integrated Rabobank OAuth connection management into the finance settings page with full UI for authorization flow and status monitoring:

1. **API Client Methods** - Four new Rabobank endpoints in `prmApi`:
   - `getRabobankStatus()` - Check connection status
   - `getRabobankAuthorizeUrl()` - Get OAuth authorization URL
   - `disconnectRabobank()` - Clear stored tokens
   - `createPaymentLink(invoiceId)` - Create payment request (for future use)

2. **React Hooks** - Three TanStack Query hooks in `useFinanceSettings.js`:
   - `useRabobankStatus()` - Query hook for connection status
   - `useDisconnectRabobank()` - Mutation hook for disconnect
   - `useCreatePaymentLink()` - Mutation hook for payment link creation

3. **Finance Settings UI** - Updated Rabobank section with two-part design:
   - **Part A: Connection Status Card** - Visual status indicator with action buttons
   - **Part B: Credentials Form** - Client ID/Secret management (existing functionality)

## Technical Implementation

### Connection Status UI

**Connected State:**
```jsx
- Green card with Link2 icon
- Status: "Gekoppeld" with green indicator dot
- Environment label: (Sandbox) or (Productie)
- Disconnect button (red/secondary style)
- Confirmation dialog before disconnect
```

**Not Connected State:**
```jsx
- Yellow/amber card with AlertCircle icon
- Status: "Niet gekoppeld" with yellow indicator dot
- "Koppelen met Rabobank" button (primary electric-cyan)
- Button disabled if no credentials saved
- Tooltip explains credential requirement
```

### OAuth Flow Handling

**Authorization Initiation:**
1. User clicks "Koppelen met Rabobank"
2. Frontend calls `getRabobankAuthorizeUrl()` REST endpoint
3. Browser redirects to `response.data.authorize_url`
4. User authorizes via Rabobank OAuth screen
5. Rabobank redirects back to `/finance-settings?rabobank=connected` or `?rabobank=error`

**Callback Handling (useEffect):**
```javascript
useEffect(() => {
  const rabobankParam = searchParams.get('rabobank');
  if (rabobankParam === 'connected') {
    setShowSuccess(true);           // Green banner
    setTimeout(() => setShowSuccess(false), 5000);
    searchParams.delete('rabobank'); // Clean URL
    setSearchParams(searchParams, { replace: true });
  } else if (rabobankParam === 'error') {
    setSaveError(message);           // Red error banner
    searchParams.delete('rabobank');
    searchParams.delete('message');
    setSearchParams(searchParams, { replace: true });
  }
}, [searchParams, setSearchParams]);
```

### UI Organization

**Before (Plan 182-01):**
- Single Rabobank section with credentials only

**After (Plan 182-02):**
- **Connection Status Card** (top) - Current state + action buttons
- **API Credentials** (below) - Environment selector + Client ID/Secret
- Separated concerns: status management vs credential management

### Button States

| Button | Enabled When | Action |
|--------|--------------|--------|
| Koppelen | Credentials saved, not connected | Fetch authorize URL → redirect |
| Ontkoppelen | Connected | Confirm → call disconnect endpoint |

**Disabled state tooltip:** "Sla eerst je Client ID en Client Secret op"

## Integration Points

### With Backend (Plan 182-01)

- **GET /rondo/v1/rabobank/status** - Connection status check
- **GET /rondo/v1/rabobank/authorize** - Authorization URL generation
- **POST /rondo/v1/rabobank/disconnect** - Token clearing
- **GET /rondo/v1/rabobank/callback** - OAuth callback handling (backend redirects to `/finance-settings?rabobank=connected`)
- **POST /rondo/v1/invoices/{id}/payment-link** - Payment link creation (ready for Phase 184)

### With Existing Frontend

- **useFinanceSettings()** - Extended with Rabobank-specific hooks
- **FinanceSettings.jsx** - Reorganized Rabobank section with status card
- **prmApi** - Extended with Rabobank methods following existing pattern

## Deviations from Plan

None - plan executed exactly as written. All API methods, hooks, and UI components implemented as specified.

## User Experience Flow

**First-Time Setup:**
1. User opens Finance Settings → Rabobank section
2. Sees yellow "Niet gekoppeld" status card
3. Enters Client ID and Client Secret in credentials form
4. Clicks "Opslaan" to save credentials
5. "Koppelen met Rabobank" button becomes enabled
6. Clicks connect → redirects to Rabobank OAuth
7. Authorizes → redirects back with success message
8. Status card turns green "Gekoppeld"

**Disconnecting:**
1. User clicks "Ontkoppelen" button
2. Confirms via browser dialog
3. Backend clears tokens
4. Status card returns to yellow "Niet gekoppeld"
5. Must re-authorize to reconnect

**Changing Environment:**
1. User switches environment radio (sandbox ↔ production)
2. Clicks "Opslaan" to save environment change
3. If currently connected, must disconnect and reconnect with new environment
4. Backend uses environment-specific OAuth URLs

## Verification Results

- ✅ Finance settings page renders without errors
- ✅ Rabobank status query calls GET /rondo/v1/rabobank/status
- ✅ Connect button disabled when `rabobank_has_credentials` is false
- ✅ Connect button fetches authorize URL and redirects to Rabobank
- ✅ Disconnect button with confirmation calls POST /rondo/v1/rabobank/disconnect
- ✅ OAuth callback params handled and cleaned up from URL
- ✅ API client has all 4 new Rabobank methods
- ✅ Three new hooks exported and working
- ✅ Build compiles successfully (npm run build)
- ✅ No new lint errors introduced (pre-existing 113 errors remain)

## Files Modified

1. **src/api/client.js** (4 new methods)
   - Added Rabobank OAuth methods after finance settings methods
   - Follows existing prmApi pattern for endpoint organization

2. **src/hooks/useFinanceSettings.js** (3 new hooks)
   - `useRabobankStatus()` - TanStack Query for status polling
   - `useDisconnectRabobank()` - Mutation with cache invalidation
   - `useCreatePaymentLink()` - Mutation for invoice payment links

3. **src/pages/Finance/FinanceSettings.jsx** (+189 lines, -67 lines)
   - Added imports: Link2, Unlink, ExternalLink, useSearchParams
   - Added hooks: useRabobankStatus, disconnectMutation
   - Added OAuth callback handling useEffect
   - Replaced Rabobank section with two-part design
   - Added connection status card (connected/not connected states)
   - Moved credentials to separate subsection

## Ready For Next Phase

**Phase 184 (Invoice Detail Page - Payment Link UI) will use:**
- `useCreatePaymentLink()` hook for button click handler
- API client method `createPaymentLink(invoiceId)`
- Connection status from `useRabobankStatus()` to enable/disable button
- Payment link display and copy functionality

**This plan provides:**
- Complete OAuth flow UI for Rabobank connection
- Connection status visibility for payment link feature
- API client methods ready for invoice page integration
- Hooks for payment link creation and status checking

## Self-Check: PASSED

**Modified files verification:**
```
FOUND: src/api/client.js (contains getRabobankStatus, getRabobankAuthorizeUrl, disconnectRabobank, createPaymentLink)
FOUND: src/hooks/useFinanceSettings.js (contains useRabobankStatus, useDisconnectRabobank, useCreatePaymentLink)
FOUND: src/pages/Finance/FinanceSettings.jsx (contains connection status card and OAuth flow handling)
```

**Commits verification:**
```
FOUND: f947a371
FOUND: 86f5fa1e
```

All claimed files and commits exist. Summary is accurate.
