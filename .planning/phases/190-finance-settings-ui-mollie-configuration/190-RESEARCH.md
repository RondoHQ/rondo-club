# Phase 190: Finance Settings UI — Mollie Configuration - Research

**Researched:** 2026-02-18
**Domain:** React UI — extending existing FinanceSettings page with Mollie API key input, payment provider selector, and test/live mode badge
**Confidence:** HIGH

## Summary

Phase 190 is a pure UI addition to an already well-implemented Finance Settings page. All backend infrastructure was built in Phases 186–189: `FinanceConfig` already stores/retrieves `mollie_api_key` (encrypted), `active_payment_provider`, and derives `mollie_environment`. The REST GET endpoint already returns `mollie_has_api_key` (bool) and `mollie_environment` (string) — never the raw key. The REST POST endpoint already accepts `mollie_api_key` and `active_payment_provider` via `FinanceConfig::update_settings()`, but the REST route args declaration in `class-rest-api.php` does NOT yet list `mollie_api_key` or `active_payment_provider` as accepted args. This is a small backend gap that must be fixed alongside the UI.

The existing FinanceSettings page (`src/pages/Finance/FinanceSettings.jsx`) uses a tab-based layout with four tabs: Organisatie, Betaling, E-mail, Rabobank. The simplest approach is to add a fifth "Mollie" tab following the exact same pattern as the Rabobank tab — a masked password input for the key, a save button that only sends the key if non-empty, and a derived badge showing "Test" or "Live" based on `settings.mollie_environment`. The payment provider selector (Rabobank / Mollie) logically fits in the existing Betaling tab or as part of a general "Betaling" concept — but since the Rabobank tab currently handles provider-specific config and the Betaling tab handles IBAN/terms, the cleanest placement is a new Mollie tab, with the provider selector either in the Betaling tab or promoted to the top of the settings form.

**Primary recommendation:** Add one new "Mollie" tab to FinanceSettings. Move the payment provider radio selector (Rabobank / Mollie) to the existing "Betaling" tab (it is payment-level config, not Mollie-specific). Add `mollie_api_key` and `active_payment_provider` to the REST route args in `class-rest-api.php`. No new hooks, no new PHP classes, no new API endpoints needed.

## Standard Stack

### Core

| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| React | 18 | Component rendering | Project standard |
| TanStack Query | v5 | Server state via `useFinanceSettings` / `useUpdateFinanceSettings` | Already used in FinanceSettings |
| Tailwind CSS v4 | v4 | Styling — OKLCH tokens, `.card`, `.btn` patterns | Project standard |
| Lucide React | current | Icons — `Eye`, `EyeOff`, `KeyRound`, `Loader2`, `CheckCircle`, `AlertCircle` | Project standard |

### Supporting

| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| `useSearchParams` (React Router 6) | v6 | Tab state in URL (optional) | Already used in FinanceSettings |
| `TabButton` component | local | Tab navigation | Already used in FinanceSettings |

### Alternatives Considered

| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| New Mollie tab | Expanding Rabobank tab with Mollie section | Rabobank tab would become confusing as a catch-all payment tab |
| Provider selector in Betaling tab | Provider selector in a new "Betalingsprovider" tab | Extra tab for a single setting is overkill |
| `type="password"` input for API key | Custom masked display | `type="password"` is simpler; toggle to show/hide with Eye icon is acceptable UX |

## Architecture Patterns

### Existing File Structure

```
src/
├── pages/Finance/FinanceSettings.jsx   # Main file — all changes here
├── hooks/useFinanceSettings.js         # Existing hooks — no changes needed
├── api/client.js                       # prmApi.updateFinanceSettings — no changes needed
└── components/TabButton.jsx            # Reuse as-is
includes/
└── class-rest-api.php                  # Add mollie_api_key + active_payment_provider to args
```

### Pattern 1: Existing Tab Pattern

**What:** Each tab is a string ID in `TABS` array. Active tab controls which section renders. Tab state is local React state.

**Current TABS array:**
```jsx
const TABS = [
  { id: 'organization', label: 'Organisatie' },
  { id: 'payment',      label: 'Betaling' },
  { id: 'email',        label: 'E-mail' },
  { id: 'rabobank',     label: 'Rabobank' },
];
```

**Add:**
```jsx
  { id: 'mollie', label: 'Mollie' },
```

### Pattern 2: Masked Credential Input (from Rabobank tab)

The Rabobank tab already uses this pattern:
- Input starts empty (never pre-populated from API for security)
- Placeholder shows `'••••••••'` when credentials already exist (`settings.rabobank_has_credentials`)
- Payload only includes the credential field if user typed something non-empty
- After save, field is cleared

Apply the same pattern for Mollie:
```jsx
// In formData initial state
mollie_api_key: '',

// In useEffect that loads settings — do NOT populate from API
// settings.mollie_has_api_key is bool, not the key itself

// In payload construction
if (formData.mollie_api_key.trim()) {
  payload.mollie_api_key = formData.mollie_api_key;
}

// After save, clear the field
setFormData(prev => ({ ...prev, mollie_api_key: '' }));
```

### Pattern 3: Environment Badge (derived from settings)

`settings.mollie_environment` is already returned by the GET endpoint as `'live'`, `'test'`, or `''`.

```jsx
// Badge rendering — shown only when mollie_has_api_key is true
{settings?.mollie_has_api_key && settings?.mollie_environment && (
  <span className={`inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ${
    settings.mollie_environment === 'live'
      ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
      : 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400'
  }`}>
    {settings.mollie_environment === 'live' ? 'Live' : 'Test'}
  </span>
)}
```

### Pattern 4: Payment Provider Selector

`active_payment_provider` defaults to `'rabobank'`. The selector is a radio group (same pattern as `rabobank_environment`):

```jsx
// In formData
active_payment_provider: '',

// In useEffect
active_payment_provider: settings.active_payment_provider || 'rabobank',

// In payload
active_payment_provider: formData.active_payment_provider,

// Radio group (in Betaling tab)
<label className="flex items-center gap-2">
  <input
    type="radio"
    name="active_payment_provider"
    value="rabobank"
    checked={formData.active_payment_provider === 'rabobank'}
    onChange={(e) => setFormData(prev => ({ ...prev, active_payment_provider: e.target.value }))}
    className="text-electric-cyan focus:ring-electric-cyan"
  />
  <span className="text-sm text-gray-700 dark:text-gray-300">Rabobank</span>
</label>
<label className="flex items-center gap-2">
  <input
    type="radio"
    name="active_payment_provider"
    value="mollie"
    checked={formData.active_payment_provider === 'mollie'}
    onChange={(e) => setFormData(prev => ({ ...prev, active_payment_provider: e.target.value }))}
    className="text-electric-cyan focus:ring-electric-cyan"
  />
  <span className="text-sm text-gray-700 dark:text-gray-300">Mollie</span>
</label>
```

### Pattern 5: REST Route Args Registration (backend gap)

The `update_finance_settings` route in `class-rest-api.php` does not list `mollie_api_key` or `active_payment_provider` as accepted args. WordPress REST API strips unregistered params when `allow_batch` is false and sanitization is expected. `FinanceConfig::update_settings()` calls `isset($data['mollie_api_key'])` which checks the raw request — but `$request->get_params()` may not pass unregistered params through in strict mode. Add these to the route args to be safe:

```php
'mollie_api_key'          => [ 'required' => false, 'sanitize_callback' => 'sanitize_text_field' ],
'active_payment_provider' => [ 'required' => false, 'sanitize_callback' => 'sanitize_text_field' ],
```

### Anti-Patterns to Avoid

- **Populating the API key input from API response:** The API only returns `mollie_has_api_key` (bool), never the key itself. Never attempt to show the key in the input — it is never returned.
- **Showing the environment badge when no key is set:** Only render the badge when `settings.mollie_has_api_key === true`.
- **Adding a new REST endpoint:** The existing `/rondo/v1/finance/settings` GET+POST handles everything. No new endpoint is needed.
- **Creating a new hook:** `useFinanceSettings` and `useUpdateFinanceSettings` are sufficient.
- **Separate save button per section:** The existing single "Opslaan" submit button at the bottom covers all tabs — maintain this pattern.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Environment detection | Custom key-parsing logic in UI | Already in `FinanceConfig::derive_mollie_environment()` — backend returns `mollie_environment` string | Single source of truth; frontend just reads `settings.mollie_environment` |
| Credential masking | Custom masked input component | Standard `type="password"` with placeholder `'••••••••'` | Already used for Rabobank; consistent pattern |
| API key validation | Frontend key format check | Leave to Mollie API; save always succeeds, Mollie rejects invalid keys at payment creation time | Avoids false positives, keeps UI simple |

## Common Pitfalls

### Pitfall 1: REST Args Not Registered for Mollie Fields

**What goes wrong:** `mollie_api_key` and `active_payment_provider` are not listed in the REST route `args` array. WordPress REST API with strict sanitization may strip these from `$request->get_params()`.

**Why it happens:** The route was written before Phase 186 added Mollie support to `FinanceConfig`.

**How to avoid:** Add both fields to the `args` array in `class-rest-api.php` alongside the existing Rabobank args.

**Warning signs:** Saving Mollie API key appears to succeed (200 response) but `mollie_has_api_key` remains `false` in the returned settings.

### Pitfall 2: Payment Provider Selector in Wrong Tab Causes Confusion

**What goes wrong:** If the provider selector is placed inside the Mollie tab, selecting "Rabobank" while in the Mollie tab is counterintuitive.

**How to avoid:** Place the provider selector in the Betaling tab, not the Mollie tab. It is a global setting, not Mollie-specific config.

### Pitfall 3: Showing "Opslaan" For Credential Fields Gives False Security Sense

**What goes wrong:** User clears the API key field and clicks save — nothing happens (empty string is ignored per `FinanceConfig::update_mollie_api_key()`). But the user might expect this to DELETE the key.

**How to avoid:** Keep the existing Rabobank pattern: note in the UI that leaving the field blank retains the current value. Do NOT add a "Delete key" feature in this phase (not in requirements).

### Pitfall 4: Environment Badge Shows Stale Data After Save

**What goes wrong:** After saving a new API key, the badge still reflects the old environment until cache invalidates.

**How to avoid:** `useUpdateFinanceSettings` already calls `queryClient.setQueryData(['finance-settings'], data)` with the full response — the response includes updated `mollie_environment`. The badge will update immediately after save without any extra invalidation logic.

## Code Examples

### Mollie Tab Section Structure (full section)

```jsx
{/* Section 5: Mollie Integration */}
{activeTab === 'mollie' && <div className="card p-6">
  <div className="mb-4">
    <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100">Mollie Koppeling</h2>
    <p className="text-sm text-gray-600 dark:text-gray-400 mt-1">
      API-sleutel voor betalingen via Mollie.
    </p>
  </div>

  <div className="space-y-6">
    {/* Environment badge */}
    {settings?.mollie_has_api_key && (
      <div className="flex items-center gap-3">
        <span className="text-sm text-gray-600 dark:text-gray-400">Omgeving:</span>
        <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${
          settings.mollie_environment === 'live'
            ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
            : 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400'
        }`}>
          {settings.mollie_environment === 'live' ? 'Live' : 'Test'}
        </span>
      </div>
    )}

    {/* Existing key notice */}
    {settings?.mollie_has_api_key && (
      <div className="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg p-3">
        <p className="text-sm text-green-700 dark:text-green-300">
          API-sleutel opgeslagen. Laat het veld leeg om de huidige waarde te behouden.
        </p>
      </div>
    )}

    {/* API key input */}
    <div>
      <label htmlFor="mollie_api_key" className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
        API-sleutel
      </label>
      <input
        type="password"
        id="mollie_api_key"
        value={formData.mollie_api_key}
        onChange={(e) => setFormData(prev => ({ ...prev, mollie_api_key: e.target.value }))}
        placeholder={settings?.mollie_has_api_key ? '••••••••' : 'live_... of test_...'}
        className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-electric-cyan dark:focus:ring-electric-cyan focus:border-transparent"
      />
      <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
        Gebruik een <code>live_</code> sleutel voor productie of <code>test_</code> voor sandbox. De omgeving wordt automatisch afgeleid.
      </p>
    </div>
  </div>
</div>}
```

### Provider Selector Addition to Betaling Tab

```jsx
{/* Payment provider selector — add to existing Betaling tab */}
<div>
  <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
    Betalingsprovider
  </label>
  <div className="space-y-2">
    <label className="flex items-center gap-2">
      <input
        type="radio"
        name="active_payment_provider"
        value="rabobank"
        checked={formData.active_payment_provider === 'rabobank'}
        onChange={(e) => setFormData(prev => ({ ...prev, active_payment_provider: e.target.value }))}
        className="text-electric-cyan focus:ring-electric-cyan"
      />
      <span className="text-sm text-gray-700 dark:text-gray-300">Rabobank</span>
    </label>
    <label className="flex items-center gap-2">
      <input
        type="radio"
        name="active_payment_provider"
        value="mollie"
        checked={formData.active_payment_provider === 'mollie'}
        onChange={(e) => setFormData(prev => ({ ...prev, active_payment_provider: e.target.value }))}
        className="text-electric-cyan focus:ring-electric-cyan"
      />
      <span className="text-sm text-gray-700 dark:text-gray-300">Mollie</span>
    </label>
  </div>
  <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
    Kies welke provider wordt gebruikt voor betaallinks bij het versturen van facturen.
  </p>
</div>
```

### Backend: Add Missing Args to REST Route

In `class-rest-api.php`, within the `args` array for the `update_finance_settings` POST route:

```php
'mollie_api_key'          => [ 'required' => false, 'sanitize_callback' => 'sanitize_text_field' ],
'active_payment_provider' => [
  'required'          => false,
  'sanitize_callback' => 'sanitize_text_field',
  'validate_callback' => function( $param ) {
    return in_array( $param, [ 'rabobank', 'mollie' ], true );
  },
],
```

### formData Changes

```jsx
// Add to initial formData:
active_payment_provider: 'rabobank',
mollie_api_key: '',

// Add to useEffect settings loader:
active_payment_provider: settings.active_payment_provider || 'rabobank',
// Do NOT add mollie_api_key here — key is never returned

// Add to payload in handleSubmit:
active_payment_provider: formData.active_payment_provider,
if (formData.mollie_api_key.trim()) {
  payload.mollie_api_key = formData.mollie_api_key;
}

// Add to post-save cleanup:
setFormData(prev => ({ ...prev, mollie_api_key: '' }));
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Hard-coded Rabobank block in `send_invoice` | Provider routing via `active_payment_provider` | Phase 189 | UI now needs to expose provider setting |
| No Mollie storage | Encrypted `mollie_api_key` in FinanceConfig | Phase 186 | GET endpoint already returns `mollie_has_api_key` + `mollie_environment` |
| GET returns raw credentials | GET returns only bool/env strings | Phase 186 | Full key is never exposed via REST — by design |

## Open Questions

1. **Where to place the provider selector?**
   - What we know: Requirements say "Finance Settings includes a payment provider selector" — no tab specified.
   - What's unclear: Betaling tab (logical payment grouping) vs. its own position vs. top of form.
   - Recommendation: Betaling tab. It controls payment behavior but is not Mollie-specific. Avoids cluttering Mollie tab with cross-cutting config.

2. **Should "Delete Mollie key" be in scope?**
   - What we know: Requirements do not mention key deletion. Phase 186 `update_mollie_api_key('')` deletes the key, but that's a backend detail.
   - What's unclear: Not in scope per requirements. Out of scope for this phase.
   - Recommendation: Skip entirely.

## Sources

### Primary (HIGH confidence)

- Direct codebase reading:
  - `/Users/joostdevalk/Code/rondo/rondo-club/includes/class-finance-config.php` — `get_all_settings()` returns `mollie_has_api_key`, `mollie_environment`, `active_payment_provider`; `update_settings()` handles `mollie_api_key` and `active_payment_provider`
  - `/Users/joostdevalk/Code/rondo/rondo-club/src/pages/Finance/FinanceSettings.jsx` — full existing UI; tab pattern, formData shape, handleSubmit credential-only-if-non-empty pattern
  - `/Users/joostdevalk/Code/rondo/rondo-club/includes/class-rest-api.php` lines 728-755 — REST route for finance/settings (GET+POST); current args do NOT include mollie fields
  - `/Users/joostdevalk/Code/rondo/rondo-club/src/hooks/useFinanceSettings.js` — `useFinanceSettings`, `useUpdateFinanceSettings` hooks (setQueryData on success)
  - `/Users/joostdevalk/Code/rondo/rondo-club/src/api/client.js` — `prmApi.getFinanceSettings`, `prmApi.updateFinanceSettings`

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — no new dependencies; all reuse existing codebase patterns
- Architecture: HIGH — all patterns directly observed in existing FinanceSettings.jsx and FinanceConfig.php
- Pitfalls: HIGH — REST args gap is directly confirmed by reading class-rest-api.php; all others derived from code

**Research date:** 2026-02-18
**Valid until:** 2026-03-18 (stable internal codebase, no external library churn)
