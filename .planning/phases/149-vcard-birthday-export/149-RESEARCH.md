# Phase 149: Fix vCard Birthday Export - Research

**Researched:** 2026-02-06
**Domain:** vCard export, JavaScript date formatting
**Confidence:** HIGH

## Summary

This phase fixes a gap identified in the v19.0 milestone audit where the frontend JavaScript vCard export function (`generateVCard` in `src/utils/vcard.js`) still reads from a deleted `personDates` array parameter instead of the person's direct `acf.birthdate` field.

The fix is straightforward: update the JavaScript vCard export to read birthdate from `person.acf.birthdate` (the same field the PHP backend already uses correctly). The PHP backend (`includes/class-vcard-export.php`) already has a working implementation that correctly reads from the ACF birthdate field, so the JavaScript implementation should mirror it.

This is a pure JavaScript fix - no backend changes required. The ACF field `birthdate` is already populated and returned in REST API responses (type: `date_picker`, return format: `Y-m-d`).

**Primary recommendation:** Replace the `personDates` array lookup with a direct read of `person.acf.birthdate` in the `generateVCard` function.

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| Native JavaScript | ES2020+ | Date formatting | No dependencies needed for YYYYMMDD format |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| N/A | - | - | This is simple date formatting, no libraries needed |

**Installation:**
```bash
# No installation required - pure JavaScript fix
```

## Architecture Patterns

### Existing Pattern: PHP vCard Export (Reference)

The PHP backend already correctly implements birthday export. The JavaScript should mirror this pattern.

**File:** `includes/class-vcard-export.php` lines 132-146

```php
// Source: Stadion codebase includes/class-vcard-export.php
public static function get_birthday( $person_id ) {
    $birthdate = get_field( 'birthdate', $person_id );
    return ! empty( $birthdate ) ? $birthdate : null;
}

// In generate() method:
$birthday = self::get_birthday( $person->ID );
if ( $birthday ) {
    $bday = self::format_date( $birthday );
    if ( $bday ) {
        $lines[] = "BDAY:{$bday}";
    }
}
```

### Current Pattern (BROKEN): JavaScript vCard Export

**File:** `src/utils/vcard.js` lines 197-209

```javascript
// BROKEN: Reads from deleted personDates array
if (personDates && Array.isArray(personDates)) {
  const birthday = personDates.find(d => {
    const dateType = Array.isArray(d.date_type) ? d.date_type[0] : d.date_type;
    return dateType?.toLowerCase() === 'birthday';
  });
  if (birthday?.date_value) {
    const bday = formatVCardDate(birthday.date_value);
    if (bday) {
      lines.push(`BDAY:${bday}`);
    }
  }
}
```

### Target Pattern: Fixed JavaScript vCard Export

```javascript
// FIXED: Read directly from person.acf.birthdate
if (acf.birthdate) {
  const bday = formatVCardDate(acf.birthdate);
  if (bday) {
    lines.push(`BDAY:${bday}`);
  }
}
```

### Anti-Patterns to Avoid
- **Using optional parameters for required data:** The `personDates` parameter was optional and never passed - this pattern hides bugs
- **Complex lookups for simple fields:** Birthday is a direct field, not a lookup in a related collection

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Date formatting | Custom date parser | Existing `formatVCardDate()` | Already handles YYYYMMDD format correctly |

**Key insight:** The `formatVCardDate()` helper already exists in `vcard.js` (lines 38-46) and correctly formats dates to YYYYMMDD.

## Common Pitfalls

### Pitfall 1: Forgetting to Update Function Signature
**What goes wrong:** The `personDates` parameter in function signature remains, causing confusion
**Why it happens:** Partial refactoring
**How to avoid:** Remove unused `personDates` from options destructuring
**Warning signs:** Unused variable warnings from linter

### Pitfall 2: Incorrect Date Format Handling
**What goes wrong:** Passing Date object instead of string to `formatVCardDate`
**Why it happens:** Assuming the field returns a Date
**How to avoid:** ACF returns birthdate as `Y-m-d` string, `formatVCardDate` handles this correctly
**Warning signs:** "Invalid Date" in output

### Pitfall 3: Not Testing Empty/Null Values
**What goes wrong:** Generating invalid vCard with empty BDAY line
**Why it happens:** Not checking for empty string
**How to avoid:** Use truthy check: `if (acf.birthdate)`
**Warning signs:** vCard contains `BDAY:` with no value

## Code Examples

### vCard BDAY Format Specification

Per [RFC 6350](https://www.rfc-editor.org/rfc/rfc6350.html), the BDAY property supports these formats:
- `BDAY:19960415` - Full date (YYYYMMDD) - **this is what we use**
- `BDAY:--0415` - Recurring date without year
- `BDAY:19531015T231000Z` - Date-time with time zone

### Existing formatVCardDate Helper
```javascript
// Source: src/utils/vcard.js lines 38-46
function formatVCardDate(date) {
  if (!date) return '';
  const d = new Date(date);
  if (isNaN(d.getTime())) return '';
  const year = d.getFullYear();
  const month = String(d.getMonth() + 1).padStart(2, '0');
  const day = String(d.getDate()).padStart(2, '0');
  return `${year}${month}${day}`;
}
```

### ACF Birthdate Field Definition
```json
// Source: acf-json/group_person_fields.json lines 86-96
{
  "key": "field_birthdate",
  "label": "Birthdate",
  "name": "birthdate",
  "type": "date_picker",
  "display_format": "d F Y",
  "return_format": "Y-m-d",
  "readonly": 1,
  "wrapper": {
    "width": "50"
  }
}
```

### PersonDetail.jsx Already Uses person.acf.birthdate
```javascript
// Source: src/pages/People/PersonDetail.jsx lines 133-136
const birthDate = person?.acf?.birthdate && person.acf.birthdate !== ''
  ? new Date(person.acf.birthdate)
  : null;
```

### PersonDetail.jsx Call Site (No personDates Passed)
```javascript
// Source: src/pages/People/PersonDetail.jsx lines 604-614
const handleExportVCard = () => {
  if (!person) return;

  try {
    downloadVCard(person, {
      teamMap,
      // NOTE: personDates is NOT passed here - proving it's dead code
    });
  } catch {
    alert('vCard kon niet worden geexporteerd. Probeer het opnieuw.');
  }
};
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Important Dates CPT with `personDates` array | Direct `birthdate` field on person | Phase 148 | vCard export broken |

**Deprecated/outdated:**
- `personDates` parameter: Was used when birthdays were stored as Important Date CPT entries. Now birthdays are stored directly on person records as `acf.birthdate`.

## Open Questions

None - this is a straightforward fix with clear scope.

## Sources

### Primary (HIGH confidence)
- `/Users/joostdevalk/Code/rondo/rondo-club/src/utils/vcard.js` - Current broken implementation
- `/Users/joostdevalk/Code/rondo/rondo-club/includes/class-vcard-export.php` - PHP reference implementation (working)
- `/Users/joostdevalk/Code/rondo/rondo-club/acf-json/group_person_fields.json` - ACF field definition
- `/Users/joostdevalk/Code/rondo/rondo-club/src/pages/People/PersonDetail.jsx` - Call site showing personDates not passed

### Secondary (MEDIUM confidence)
- [RFC 6350](https://www.rfc-editor.org/rfc/rfc6350.html) - vCard Format Specification for BDAY format

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - No external dependencies, simple JavaScript
- Architecture: HIGH - Clear pattern from PHP implementation to mirror
- Pitfalls: HIGH - Limited scope, clear error cases

**Research date:** 2026-02-06
**Valid until:** Indefinite (stable fix, not dependent on external APIs)
