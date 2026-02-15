---
phase: 178-finance-navigation-settings-backend
plan: 02
subsystem: finance
tags: [ui, settings, forms, hooks]
dependency_graph:
  requires: [finance-rest-api, finance-config-class, api-client]
  provides: [finance-settings-ui, finance-settings-hooks]
  affects: [settings-pages]
tech_stack:
  added: [useFinanceSettings hook, useUpdateFinanceSettings hook]
  patterns: [TanStack Query, controlled forms, conditional credential updates]
key_files:
  created:
    - src/hooks/useFinanceSettings.js
  modified:
    - src/pages/Finance/FinanceSettings.jsx
decisions:
  - title: "Conditional credential submission"
    rationale: "Only send rabobank_client_id and rabobank_client_secret to API if user enters new values, preserving existing credentials when fields left empty"
  - title: "Auto-formatting IBAN on blur"
    rationale: "Uppercase and strip spaces on blur event for consistent storage format, matches backend sanitization"
metrics:
  duration_minutes: 2
  tasks_completed: 1
  files_created: 1
  files_modified: 1
  commits: 1
  completed_date: 2026-02-15
---

# Phase 178 Plan 02: Finance Settings UI Summary

Complete finance settings form with 4 sections for configuring organization details, payment info, email templates, and Rabobank API credentials.

## What Was Built

**TanStack Query Hooks:**
- `useFinanceSettings()` - Fetches settings from `/rondo/v1/finance/settings`, caches with key `['finance-settings']`
- `useUpdateFinanceSettings()` - Mutation for saving settings, optimistically updates cache on success

**Finance Settings Page UI:**
Four distinct card sections with full form controls:

1. **Organisatiegegevens (Organization Details)**
   - Organization name (text input)
   - Address (textarea, 3 rows)
   - Contact email (email input)

2. **Betaalgegevens (Payment Details)**
   - IBAN (text input with auto-format on blur: uppercase, strip spaces)
   - Payment term days (number input, default 14, min 1, max 365)
   - Payment clause (textarea, 3 rows)

3. **E-mailsjabloon (Email Template)**
   - Email text (textarea, 8 rows, monospace font)
   - Variable documentation info box showing 6 available placeholders:
     - `{naam}` - Member name
     - `{factuur_nummer}` - Invoice number
     - `{tuchtzaken_lijst}` - Discipline cases overview
     - `{totaal_bedrag}` - Total amount
     - `{betaallink}` - Payment link
     - `{organisatie_naam}` - Organization name
   - Info box styled with blue background/border for both light and dark modes

4. **Rabobank Koppeling (Rabobank Integration)**
   - Environment radio buttons (sandbox/production)
   - Production warning badge when production selected
   - Client ID (text input, shows placeholder dots if credentials exist)
   - Client Secret (password input, shows placeholder dots if credentials exist)
   - Green "credentials saved" notice when `rabobank_has_credentials` is true
   - Credentials only submitted to API if user enters new values (preserves existing)

**Form Behavior:**
- Loads settings from API on mount via `useFinanceSettings` hook
- Populates form state from loaded data
- IBAN auto-formats on blur (uppercase, strip spaces)
- Submit button shows loading spinner while saving
- Success message displays for 3 seconds after save, auto-fades
- Error messages shown in red alert card on failure
- Credential fields cleared after successful save (security)
- Dark mode compatible throughout

## Deviations from Plan

None - plan executed exactly as written.

## Technical Implementation

**Hook Pattern:**
```js
export function useFinanceSettings() {
  return useQuery({
    queryKey: ['finance-settings'],
    queryFn: async () => {
      const response = await prmApi.getFinanceSettings();
      return response.data;
    },
  });
}

export function useUpdateFinanceSettings() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (data) => {
      const response = await prmApi.updateFinanceSettings(data);
      return response.data;
    },
    onSuccess: (data) => {
      queryClient.setQueryData(['finance-settings'], data);
    },
  });
}
```

**Conditional Credential Update:**
```js
const payload = {
  org_name: formData.org_name,
  // ... other fields always included
  rabobank_environment: formData.rabobank_environment,
};

// Only include if user entered new values
if (formData.rabobank_client_id.trim()) {
  payload.rabobank_client_id = formData.rabobank_client_id;
}
if (formData.rabobank_client_secret.trim()) {
  payload.rabobank_client_secret = formData.rabobank_client_secret;
}
```

**IBAN Auto-Format:**
```js
const handleIbanBlur = () => {
  const formatted = formData.iban.toUpperCase().replace(/\s+/g, '');
  setFormData(prev => ({ ...prev, iban: formatted }));
};
```

**Card Styling Pattern:**
```jsx
<div className="card p-6">
  <div className="mb-4">
    <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100">Section Title</h2>
    <p className="text-sm text-gray-600 dark:text-gray-400 mt-1">Description</p>
  </div>
  <div className="space-y-4">
    {/* Form fields */}
  </div>
</div>
```

## Files Changed

**Created:**
- `src/hooks/useFinanceSettings.js` - TanStack Query hooks for finance settings CRUD (37 lines)

**Modified:**
- `src/pages/Finance/FinanceSettings.jsx` - Replaced placeholder with full settings form (385 lines, up from 7)

## Testing Notes

- Build passes without errors (`npm run build` successful, 15.95s)
- No new lint errors introduced (pre-existing 113 errors remain)
- FinanceSettings.jsx compiled to `dist/assets/FinanceSettings-BgUk8UIR.js` (13.80 kB, gzipped 2.85 kB)
- All form fields use consistent Tailwind classes matching existing settings pages
- Dark mode styles verified on all inputs, textareas, cards, alerts

## Next Steps (Phase Completion)

Phase 178 is now complete. Next phase (179) will implement invoice generation system using the settings configured here.

## Self-Check: PASSED

**Created files verified:**
```bash
[ -f "src/hooks/useFinanceSettings.js" ] && echo "FOUND: src/hooks/useFinanceSettings.js"
```
Output:
```
FOUND: src/hooks/useFinanceSettings.js
```

**Modified files verified:**
```bash
[ -f "src/pages/Finance/FinanceSettings.jsx" ] && wc -l src/pages/Finance/FinanceSettings.jsx
```
Output:
```
385 src/pages/Finance/FinanceSettings.jsx
```

**Commit verified:**
```bash
git log --oneline --all | grep 2da54319
```
Output:
```
2da54319 feat(178-02): create Finance Settings UI with full form sections
```

All artifacts created, all commits present.
