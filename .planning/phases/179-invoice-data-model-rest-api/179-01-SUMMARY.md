---
phase: 179-invoice-data-model-rest-api
plan: 01
subsystem: finance-invoicing
tags: [data-model, acf, cpt, invoice-numbering]

dependency_graph:
  requires: []
  provides:
    - rondo_invoice CPT with 4 lifecycle statuses
    - ACF field group for invoice data
    - InvoiceNumbering service for sequential number generation
  affects:
    - includes/class-post-types.php
    - acf-json/group_invoice_fields.json
    - includes/class-invoice-numbering.php

tech_stack:
  added:
    - Invoice CPT with custom statuses (draft, sent, paid, overdue)
    - InvoiceNumbering utility class in Rondo\Finance namespace
  patterns:
    - Custom post type registration following WordPress standards
    - ACF field groups with repeater fields for line items
    - Static utility class for sequential number generation

key_files:
  created:
    - acf-json/group_invoice_fields.json
    - includes/class-invoice-numbering.php
  modified:
    - includes/class-post-types.php

decisions: []

metrics:
  duration: 114
  tasks_completed: 2
  files_created: 2
  files_modified: 1
  commits: 2
  completed_date: 2026-02-15
---

# Phase 179 Plan 01: Invoice Data Model Summary

**One-liner:** Invoice CPT with lifecycle statuses (draft/sent/paid/overdue), ACF fields including line_items repeater linked to discipline_cases, and sequential invoice numbering service (2026T001 format)

## What Was Built

This plan established the foundational data model for the invoice system by:

1. **Invoice CPT Registration**: Created `rondo_invoice` custom post type with Dutch labels (Facturen/Factuur), configured for REST API access at `/wp/v2/invoices`
2. **Invoice Statuses**: Registered 4 custom post statuses representing the invoice lifecycle:
   - `rondo_draft` (Concept) - Initial state
   - `rondo_sent` (Verstuurd) - Sent to recipient
   - `rondo_paid` (Betaald) - Payment received
   - `rondo_overdue` (Verlopen) - Payment overdue
3. **ACF Field Structure**: Created comprehensive field group with:
   - Basic fields: invoice_number (auto-generated), person (required), status, total_amount
   - Line items repeater: discipline_case, description, amount
   - Payment tracking: payment_link, pdf_path, sent_date, due_date
4. **Invoice Numbering Service**: Built `InvoiceNumbering` class with:
   - `generate_next()` - Creates sequential numbers in YYYY-T-### format (e.g., 2026T001)
   - `is_valid()` - Validates invoice number format
   - Automatic year-based sequencing by querying existing invoice numbers

## Architecture

**Data Model Pattern:**
- CPT for entity structure (rondo_invoice)
- Custom post_status for lifecycle states (WordPress native)
- ACF fields for complex data (line items repeater with discipline_case relationships)
- Static utility class for business logic (number generation)

**Field Organization:**
- Invoice metadata: number, person, status, dates, amounts
- Line items: repeater with discipline_case relationship, description, amount
- Payment integration: payment_link (for Rabobank API), pdf_path (for generated invoices)

**Namespace Structure:**
- `Rondo\Core\PostTypes` - CPT registration
- `Rondo\Finance\InvoiceNumbering` - Invoice-specific business logic

## Technical Details

**Invoice CPT Configuration:**
```php
'public' => false,              // Not publicly accessible
'publicly_queryable' => false,  // No frontend queries
'show_ui' => true,              // Admin UI enabled
'show_in_rest' => true,         // REST API enabled
'rest_base' => 'invoices',      // Endpoint: /wp/v2/invoices
'supports' => ['title', 'author']
```

**Invoice Number Format:**
- Pattern: `YYYY-T-###` (e.g., 2026T001)
- Sequential per calendar year
- Zero-padded to 3 digits minimum
- Validation regex: `/^\d{4}T\d{3,}$/`

**Line Items Repeater Structure:**
- `discipline_case` - post_object relationship to discipline_case CPT
- `description` - text field for additional context
- `amount` - number field with euro symbol, min 0, step 0.01

## Dependencies

**Requires:**
- WordPress 6.0+
- ACF Pro (for field groups and repeater fields)
- Existing discipline_case CPT (for line item relationships)
- Existing person CPT (for invoice recipient)

**Provides for subsequent plans:**
- Invoice data structure for REST API (Plan 02)
- Field definitions for frontend UI
- Number generation for invoice creation workflow

## Deviations from Plan

None - plan executed exactly as written.

## Verification Results

All verification checks passed:
- ✓ `rondo_invoice` CPT registered in class-post-types.php
- ✓ All 4 invoice statuses registered (draft, sent, paid, overdue)
- ✓ ACF field group JSON valid and includes line_items repeater
- ✓ InvoiceNumbering class with generate_next() method exists
- ✓ All PHP files pass syntax check

## Task Breakdown

| Task | Description | Commit | Files |
|------|-------------|--------|-------|
| 1 | Register Invoice CPT, statuses, and ACF fields | 0c1872ba | includes/class-post-types.php, acf-json/group_invoice_fields.json |
| 2 | Create invoice numbering service | 5c3d3c9f | includes/class-invoice-numbering.php |

## Self-Check: PASSED

**Created files verified:**
- ✓ acf-json/group_invoice_fields.json exists and is valid JSON
- ✓ includes/class-invoice-numbering.php exists with no syntax errors

**Commits verified:**
- ✓ 0c1872ba - feat(179-01): register Invoice CPT with statuses and ACF fields
- ✓ 5c3d3c9f - feat(179-01): create invoice numbering service

**Functionality verified:**
- ✓ rondo_invoice CPT registration present
- ✓ 4 custom statuses registered
- ✓ ACF field group includes all required fields
- ✓ InvoiceNumbering class methods exist and validate

All claims in this summary are supported by verified file contents and git history.
