---
phase: 67-invoice-columns
plan: 01
subsystem: finance/invoices
tags:
  - invoice-pdf
  - discipline-cases
  - pdf-generation
dependency_graph:
  requires:
    - Invoice PDF generator
    - Discipline case ACF fields (charge_codes, charge_description, sanction_description)
  provides:
    - 4-column invoice PDF table with card type and suspension
  affects:
    - Invoice PDF rendering
    - Discipline case invoice display
tech_stack:
  added: []
  patterns:
    - ACF field retrieval for card type determination
    - Conditional string formatting based on charge codes
key_files:
  created: []
  modified:
    - includes/class-invoice-pdf-generator.php
decisions:
  - Card type determined by charge_codes ending (-1 = yellow, else red)
  - Card type displays with charge description format "Kaart: description"
  - Suspension column shows "Ja" only when sanction_description equals "uitsluiting"
  - Table width distribution: Omschrijving 45%, Kaart 25%, Schorsing 15%, Bedrag 15%
metrics:
  duration: 45s
  tasks_completed: 1
  files_modified: 1
  completed_date: 2026-02-16
---

# Quick Task 67: Invoice PDF - Card Type and Suspension Columns

**One-liner:** Replace sanction column with separate card type and suspension columns in invoice PDFs, showing yellow/red card determination from charge codes.

## Objective

Replace the "Sanctie" column on invoice PDFs with "Kaart" (card type) and add a new "Schorsing" (suspension) column to better match invoice layout to discipline case data structure.

## What Was Built

Updated the invoice PDF generator to display a 4-column table structure with improved discipline case information display.

### Changes Made

**1. Table Structure Update**
- Changed from 3-column to 4-column layout
- New headers: Omschrijving | Kaart | Schorsing | Bedrag
- Column widths: 45% | 25% | 15% | 15%

**2. Card Type Logic**
- Added retrieval of `charge_codes` and `charge_description` ACF fields
- Implemented card color determination:
  - If `charge_codes` ends with "-1" → "Gele kaart"
  - Otherwise → "Rode kaart"
- Display format: "{Gele/Rode kaart}: {charge_description}"

**3. Suspension Flag**
- Added new `suspension` variable
- Shows "Ja" when `sanction_description` equals "uitsluiting"
- Shows empty string for all other cases

**4. HTML Template Updates**
- Updated table header to show 4 columns
- Modified line item row generation to output 4 cells
- Changed total row colspan from 2 to 3 to match new structure

## Deviations from Plan

None - plan executed exactly as written.

## Technical Details

### Card Type Determination

```php
$charge_codes = get_field( 'charge_codes', $case_id );
$charge_description = get_field( 'charge_description', $case_id );

if ( substr( $charge_codes, -2 ) === '-1' ) {
    $card_type = 'Gele kaart: ' . $charge_description;
} else {
    $card_type = 'Rode kaart: ' . $charge_description;
}
```

### Suspension Detection

```php
$sanction_desc = get_field( 'sanction_description', $case_id );
if ( $sanction_desc === 'uitsluiting' ) {
    $suspension = 'Ja';
}
```

## Files Modified

| File | Changes | Lines |
|------|---------|-------|
| includes/class-invoice-pdf-generator.php | 4-column table structure, card type logic, suspension flag | +25/-7 |

## Commit

- **adb1910e**: feat(67): update invoice PDF table to show card type and suspension columns

## Verification Status

- [x] Invoice PDF table header has 4 columns with appropriate widths
- [x] Card type column shows yellow/red card with charge description
- [x] Suspension column shows "Ja" only for uitsluiting cases
- [x] Total row colspan is correct (spans 3 columns)
- [x] Code maintains all existing escaping and formatting

## Self-Check: PASSED

**File verification:**
```bash
✓ FOUND: includes/class-invoice-pdf-generator.php
```

**Commit verification:**
```bash
✓ FOUND: adb1910e
```

**Code verification:**
- ✓ 4-column table header implemented
- ✓ Card type logic retrieves charge_codes and charge_description
- ✓ Card color determination based on "-1" suffix
- ✓ Suspension flag checks for "uitsluiting"
- ✓ Total row colspan updated to 3
- ✓ All escaping preserved (esc_html)

## Impact

### User-Facing
- Invoice PDFs now clearly show card type (yellow/red) with charge description
- Suspension status is explicitly flagged in separate column
- More readable and structured invoice layout

### Developer-Facing
- Invoice PDF generator now fetches additional ACF fields from discipline cases
- Card type determination logic is clear and maintainable
- Table structure is easier to understand with semantic column names

## Next Steps

None - quick task complete. Invoice regeneration will produce PDFs with the new 4-column structure.

---

*Quick task completed: 2026-02-16*
*Duration: 45 seconds*
