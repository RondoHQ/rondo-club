---
phase: 179-invoice-data-model-rest-api
plan: 02
subsystem: finance-invoicing
tags: [rest-api, crud, invoice-endpoints, overdue-detection]

dependency_graph:
  requires:
    - rondo_invoice CPT from 179-01
    - InvoiceNumbering service from 179-01
    - FinanceConfig for payment term calculation
  provides:
    - Invoice REST API endpoints at /rondo/v1/invoices
    - Invoice API client methods for frontend
  affects:
    - includes/class-rest-invoices.php
    - functions.php
    - src/api/client.js

tech_stack:
  added:
    - Invoice REST controller with CRUD endpoints
    - Overdue detection logic
    - Frontend API client integration
  patterns:
    - REST controller extends Base class (Rondo\REST namespace)
    - Permission gating via financieel capability
    - Automatic overdue status updates on list requests
    - ACF field formatting for REST responses

key_files:
  created:
    - includes/class-rest-invoices.php
  modified:
    - functions.php
    - src/api/client.js

decisions: []

metrics:
  duration: 179
  tasks_completed: 2
  files_created: 1
  files_modified: 2
  commits: 2
  completed_date: 2026-02-15
---

# Phase 179 Plan 02: Invoice REST API Summary

**One-liner:** Invoice REST controller with CRUD endpoints (list with filters, get, create, update status), overdue detection, financieel capability gating, and frontend API client integration

## What Was Built

This plan created the REST API layer for invoice management:

1. **Invoice REST Controller** (`includes/class-rest-invoices.php`):
   - Extends `Rondo\REST\Base` class following established pattern
   - Namespace: `Rondo\REST\Invoices`
   - All endpoints gated by `financieel` capability

2. **REST Endpoints** (under `/rondo/v1/invoices`):
   - **GET /invoices** — List invoices with optional filters:
     - `status` filter: draft, sent, paid, overdue
     - `person_id` filter: invoices for specific person
     - Runs overdue detection before returning results
   - **GET /invoices/{id}** — Single invoice with full details
   - **POST /invoices** — Create invoice with auto-numbering
   - **POST /invoices/{id}/status** — Update invoice status

3. **Business Logic Integration**:
   - Auto-generates invoice numbers via `InvoiceNumbering::generate_next()`
   - Calculates total_amount from line items on creation
   - Sets sent_date and calculates due_date when status → sent
   - Overdue detection: checks sent invoices against due_date on every list request

4. **Frontend Integration**:
   - Added imports to functions.php: `RESTInvoices`, `InvoiceNumbering`
   - Instantiated in REST initialization block
   - Added 4 API client methods: `getInvoices`, `getInvoice`, `createInvoice`, `updateInvoiceStatus`

## Architecture

**REST Controller Pattern:**
- Extends `Rondo\REST\Base` for shared infrastructure (permission checks, formatting methods)
- Registers routes via `rest_api_init` hook
- Permission callback: `check_financieel_permission()` checks `current_user_can('financieel')`

**Data Flow:**
```
Frontend → prmApi.getInvoices()
        → /rondo/v1/invoices
        → Invoices::get_invoice_list()
        → check_overdue_invoices() (updates statuses)
        → WP_Query with filters
        → format_invoice() (list view)
        → REST response
```

**Create Flow:**
```
Frontend → prmApi.createInvoice({person_id, line_items})
        → /rondo/v1/invoices POST
        → Invoices::create_invoice()
        → InvoiceNumbering::generate_next() (e.g., 2026T001)
        → wp_insert_post() with rondo_draft status
        → update_field() for ACF fields
        → format_invoice_detail() (full detail)
        → REST response
```

**Status Update Flow:**
```
Frontend → prmApi.updateInvoiceStatus(id, 'sent')
        → /rondo/v1/invoices/{id}/status POST
        → Invoices::update_invoice_status()
        → wp_update_post() to rondo_sent
        → If sent: set sent_date, calculate due_date (sent + payment_term_days)
        → format_invoice_detail()
        → REST response
```

## Technical Details

**Endpoint Details:**

1. **GET /invoices** (list):
   - Permission: financieel
   - Query params: status (optional), person_id (optional)
   - Runs `check_overdue_invoices()` before query
   - Returns array of `format_invoice()` summaries
   - Default: all invoice statuses, ordered by date DESC

2. **GET /invoices/{id}** (single):
   - Permission: financieel
   - Returns `format_invoice_detail()` with line_items array
   - Each line item includes discipline_case summary (dossier_id, descriptions)
   - 404 if invoice not found or wrong post type

3. **POST /invoices** (create):
   - Permission: financieel
   - Required: person_id, line_items (array)
   - Validates person exists and is 'person' post type
   - Generates invoice_number via `InvoiceNumbering::generate_next()`
   - Calculates total_amount from line item amounts
   - Creates post with rondo_draft status
   - Sets ACF fields: invoice_number, person, status, total_amount, line_items
   - Returns created invoice via `format_invoice_detail()`

4. **POST /invoices/{id}/status** (status update):
   - Permission: financieel
   - Required: status (draft, sent, paid, overdue)
   - Updates post_status to `rondo_{status}`
   - Updates ACF status field
   - If status = 'sent': sets sent_date (today), calculates due_date (sent_date + payment_term_days from FinanceConfig)
   - Returns updated invoice via `format_invoice_detail()`

**Overdue Detection:**
- Method: `check_overdue_invoices()`
- Runs on every GET /invoices request
- Queries invoices with post_status = 'rondo_sent' and due_date meta
- Compares due_date to today (Ymd format)
- Updates to rondo_overdue status if due_date < today

**Response Formats:**

`format_invoice()` (list view):
```php
[
    'id' => int,
    'invoice_number' => string,
    'person' => person_summary (from Base::format_person_summary),
    'total_amount' => float,
    'status' => string (draft/sent/paid/overdue),
    'post_status' => string (rondo_draft/rondo_sent/etc),
    'sent_date' => string|null (Ymd format),
    'due_date' => string|null (Ymd format),
    'payment_link' => string|null,
    'created' => string (post_date)
]
```

`format_invoice_detail()` (single view):
- All fields from `format_invoice()` plus:
- `line_items`: array of `{description, amount, discipline_case: {id, dossier_id, match_description, charge_description, sanction_description}}`
- `pdf_path`: string|null

**Frontend API Client:**

Added to `prmApi` object in `src/api/client.js`:

```javascript
getInvoices: (params = {}) => api.get('/rondo/v1/invoices', { params }),
getInvoice: (id) => api.get(`/rondo/v1/invoices/${id}`),
createInvoice: (data) => api.post('/rondo/v1/invoices', data),
updateInvoiceStatus: (id, status) => api.post(`/rondo/v1/invoices/${id}/status`, { status }),
```

**functions.php Integration:**
- Added imports: `use Rondo\REST\Invoices as RESTInvoices;`, `use Rondo\Finance\InvoiceNumbering;`
- Instantiated in REST block: `new RESTInvoices();` (after `new RESTFeedback();`)

## Dependencies

**Requires:**
- Invoice CPT and statuses from 179-01
- ACF field group from 179-01
- InvoiceNumbering service from 179-01
- FinanceConfig for payment_term_days
- Base REST class for permission checks and formatting
- Discipline_case CPT for line item relationships

**Provides for subsequent plans:**
- REST API for frontend invoice management UI
- CRUD operations for invoice workflow
- Automatic invoice numbering
- Status transition logic
- Overdue detection

## Deviations from Plan

None - plan executed exactly as written.

## Verification Results

All verification checks passed:
- ✓ PHP syntax check passes on class-rest-invoices.php
- ✓ `new RESTInvoices` found in functions.php
- ✓ InvoiceNumbering integration confirmed (use statement + generate_next call)
- ✓ check_overdue_invoices method exists and is called
- ✓ financieel capability check present (9 occurrences)
- ✓ All 4 API client methods added (getInvoices, getInvoice, createInvoice, updateInvoiceStatus)
- ✓ npm run build succeeds

## Task Breakdown

| Task | Description | Commit | Files |
|------|-------------|--------|-------|
| 1 | Create Invoice REST controller | dc7f58cb | includes/class-rest-invoices.php |
| 2 | Wire invoice classes and add API client methods | 9052c4c8 | functions.php, src/api/client.js |

## Self-Check: PASSED

**Created files verified:**
```bash
✓ includes/class-rest-invoices.php exists and has no syntax errors
✓ File contains 464 lines with complete implementation
```

**Commits verified:**
```bash
✓ dc7f58cb - feat(179-02): create Invoice REST controller with CRUD endpoints
✓ 9052c4c8 - feat(179-02): wire invoice classes and add API client methods
```

**Functionality verified:**
```bash
✓ 3 register_rest_route calls (list, single, status update)
✓ InvoiceNumbering::generate_next() integration present
✓ check_overdue_invoices() method implemented and called
✓ financieel permission check on all endpoints
✓ All 4 API client methods present in client.js
✓ RESTInvoices instantiated in functions.php
✓ Frontend build passes (16.59s)
```

All claims in this summary are supported by verified file contents and git history.
