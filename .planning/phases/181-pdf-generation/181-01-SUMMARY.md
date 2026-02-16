---
phase: 181-pdf-generation
plan: 01
subsystem: finance
tags: [pdf, invoice, mpdf, rest-api]
dependencies:
  requires:
    - phase: 179
      plan: 01
      feature: Invoice data model with rondo_invoice CPT
    - phase: 179
      plan: 02
      feature: Invoice REST API endpoints
    - phase: 178
      plan: 01
      feature: FinanceConfig service
  provides:
    - feature: PDF generation for invoices
      endpoints: [POST /rondo/v1/invoices/{id}/generate-pdf, GET /rondo/v1/invoices/{id}/pdf]
    - feature: InvoicePdfGenerator service class
      methods: [generate]
  affects:
    - subsystem: invoices
      impact: Invoices can now be converted to PDF documents
tech-stack:
  added:
    - library: mpdf/mpdf v8.2.7
      purpose: HTML-to-PDF generation (~15-20MB)
      config: DejaVu Sans font, A4 format, UTF-8 mode
  patterns:
    - pattern: Static service class
      rationale: No state needed, PDF generation is stateless
    - pattern: HTML template building
      rationale: mPDF accepts HTML/CSS, easier to style than direct PDF manipulation
key-files:
  created:
    - includes/class-invoice-pdf-generator.php: PDF generation service with HTML template builder
  modified:
    - composer.json: Added mpdf/mpdf dependency
    - includes/class-rest-invoices.php: Added generate_pdf and download_pdf endpoints
    - functions.php: Added InvoicePdfGenerator import
    - src/api/client.js: Added generateInvoicePdf() and getInvoicePdfUrl() methods
decisions:
  - decision: Use mPDF library for PDF generation
    rationale: HTML/CSS workflow familiar to developers, well-maintained library, good UTF-8/font support
    alternatives: [FPDF (low-level), TCPDF (complex API), Dompdf (limited CSS support)]
  - decision: Store PDFs in wp-content/uploads/invoices/
    rationale: WordPress convention, automatically backed up, web-accessible for downloads
  - decision: Dutch date formatting via helper method
    rationale: ACF stores dates in Ymd format, invoice PDFs require human-readable Dutch dates
  - decision: Inline CSS in HTML template
    rationale: mPDF supports subset of CSS, inline styles ensure maximum compatibility
metrics:
  duration: 201s
  tasks_completed: 2
  files_created: 1
  files_modified: 4
  commits: 2
  completed: 2026-02-16
---

# Phase 181 Plan 01: PDF Generation Summary

**One-liner:** HTML-to-PDF invoice generation via mPDF with club branding, member details, discipline case breakdown, and Dutch-formatted dates.

## What Was Built

Installed mPDF library and created complete PDF generation pipeline for invoices:

1. **InvoicePdfGenerator service class** (`includes/class-invoice-pdf-generator.php`)
   - `generate(int $invoice_id)` static method creates PDF and stores in uploads
   - Reads invoice data, person data (name, address, email), and finance config
   - Builds HTML template with 5 sections: club header, invoice meta, recipient info, line items table, payment details
   - Formats dates from ACF Ymd to Dutch format (e.g., "20260216" → "16 februari 2026")
   - Formats currency with Dutch conventions (€ 123,45)
   - Generates A4 PDF via mPDF with DejaVu Sans font
   - Saves to `wp-content/uploads/invoices/factuur-{number}.pdf`
   - Updates `pdf_path` ACF field on invoice record

2. **REST API endpoints** (`includes/class-rest-invoices.php`)
   - `POST /rondo/v1/invoices/{id}/generate-pdf` - Triggers PDF generation, returns updated invoice
   - `GET /rondo/v1/invoices/{id}/pdf` - Serves PDF file for download with proper Content-Type headers
   - Both require `financieel` capability

3. **Frontend API client methods** (`src/api/client.js`)
   - `generateInvoicePdf(id)` - Calls generation endpoint
   - `getInvoicePdfUrl(id)` - Returns URL string for download link href

## PDF Template Design

The generated PDF includes:

**Header section:**
- Rondo logo (50px height, if exists at `public/icons/rondo-logo.png`)
- Organization name (from FinanceConfig, electric-cyan #0891b2 brand color)
- Organization address (multi-line)
- Contact email

**Invoice metadata:**
- Factuurnummer (invoice number)
- Factuurdatum (invoice date, Dutch format)
- Vervaldatum (due date, Dutch format)

**Recipient section** (gray background box):
- Person name (first_name + infix + last_name)
- Street address (from addresses repeater, first entry)
- City
- Email (from contact_info repeater, first email type)

**Line items table:**
- Column 1: Omschrijving (description from discipline case match_description)
- Column 2: Sanctie (sanction from discipline case sanction_description)
- Column 3: Bedrag (amount, right-aligned, Dutch currency format)
- Total row with sum

**Payment section** (gray background box):
- IBAN with spaces for readability
- t.n.v. (organization name)
- Payment clause text (multi-line, from FinanceConfig)

## Integration Points

**FinanceConfig service:**
- `get_org_name()` - Organization name for header and payment section
- `get_org_address()` - Address for header (multi-line)
- `get_contact_email()` - Contact email for header
- `get_iban()` - Bank account for payment section
- `get_payment_term_days()` - Calculate due date if not set
- `get_payment_clause()` - Payment instructions text

**Person ACF fields:**
- `first_name`, `infix`, `last_name` - Build full name
- `addresses` repeater - Extract street and city from first entry
- `contact_info` repeater - Find first email entry

**Discipline case ACF fields:**
- `match_description` - Primary description for line item
- `sanction_description` - Sanction column in table

## Technical Implementation

**mPDF configuration:**
```php
new \Mpdf\Mpdf([
    'mode'              => 'utf-8',
    'format'            => 'A4',
    'default_font_size' => 10,
    'default_font'      => 'dejavusans', // Built-in, supports UTF-8 and € symbol
    'margin_left'       => 20,
    'margin_right'      => 20,
    'margin_top'        => 15,
    'margin_bottom'     => 15,
]);
```

**Date formatting helper:**
```php
private static function format_dutch_date( $ymd_date ) {
    // Converts "20260216" to "16 februari 2026"
    $dutch_months = [ 1 => 'januari', 2 => 'februari', ... ];
    // Parse Ymd format, lookup month name, return formatted string
}
```

**File storage:**
- Directory: `wp_upload_dir()['basedir'] . '/invoices'`
- Created via `wp_mkdir_p()` if not exists
- Filename pattern: `factuur-{invoice_number}.pdf`
- Stored path: Relative from uploads base (e.g., `invoices/factuur-2026T001.pdf`)

## Deviations from Plan

None - plan executed exactly as written.

## Verification Results

All verification checks passed:

1. ✅ mPDF installed at `vendor/mpdf/mpdf/src/Mpdf.php`
2. ✅ PHP syntax valid for both InvoicePdfGenerator and REST controller
3. ✅ Frontend build succeeds without errors
4. ✅ `generate()` static method exists in InvoicePdfGenerator
5. ✅ Two REST routes registered (generate-pdf POST, pdf GET)
6. ✅ FinanceConfig methods integrated (5 getters used)
7. ✅ `format_dutch_date()` helper converts Ymd to Dutch format
8. ✅ Person data fields accessed (name, addresses, contact_info)
9. ✅ Line items table iterates invoice line_items with discipline case details
10. ✅ PDF saved to `wp-content/uploads/invoices/` directory
11. ✅ `pdf_path` ACF field updated on invoice after generation
12. ✅ API client has `generateInvoicePdf()` and `getInvoicePdfUrl()` methods

## Commits

| Hash | Message | Files |
|------|---------|-------|
| 71a9da39 | feat(181-01): install mPDF and create InvoicePdfGenerator class | composer.json, composer.lock, includes/class-invoice-pdf-generator.php |
| 7cb419b0 | feat(181-01): add PDF generation REST endpoints and wire into functions.php | includes/class-rest-invoices.php, functions.php, src/api/client.js |

## Next Steps

Phase 182 will implement email delivery using the generated PDFs. Phase 184 will add UI for PDF download/preview in the management interface.

## Self-Check: PASSED

**Created files exist:**
```bash
✅ FOUND: includes/class-invoice-pdf-generator.php
```

**Commits exist:**
```bash
✅ FOUND: 71a9da39
✅ FOUND: 7cb419b0
```

**Key files modified:**
```bash
✅ composer.json contains mpdf/mpdf
✅ functions.php imports InvoicePdfGenerator
✅ includes/class-rest-invoices.php has generate_pdf and download_pdf methods
✅ src/api/client.js has generateInvoicePdf and getInvoicePdfUrl
```

All artifacts verified on disk. Plan execution complete.
