# Invoice Fields for Person CPT

**Stadion CRM - Invoice/Billing Data Fields for Person**

---

## Overview

Add support for invoice/billing-related fields on the Person CPT. These fields capture alternative invoice addresses and email addresses that differ from a member's primary contact information. The data originates from Sportlink's financial tab and is synced via sportlink-sync.

---

## Use Cases

1. **Alternative Invoice Address**: Member's billing address differs from their residential address (e.g., employer address, partner's address)
2. **Invoice Email**: Dedicated email for invoice delivery (e.g., finance department email)
3. **External Reference**: Custom invoice reference code for accounting systems

---

## Field Definitions

### New ACF Fields

Add the following fields to the Person field group:

| Field Key | Label | Type | Description |
|-----------|-------|------|-------------|
| `factuur-adres` | Factuuradres | `textarea` | Formatted invoice address (only set when different from member address) |
| `factuur-email` | Factuur e-mail | `email` | Dedicated email address for invoices |
| `factuur-referentie` | Factuur referentie | `text` | External invoice code/reference for accounting |

### Field Configuration

```json
{
  "factuur-adres": {
    "type": "textarea",
    "label": "Factuuradres",
    "instructions": "Alternatief adres voor facturen (automatisch gevuld vanuit Sportlink)",
    "required": false,
    "rows": 3,
    "new_lines": "br",
    "readonly": true
  },
  "factuur-email": {
    "type": "email",
    "label": "Factuur e-mail",
    "instructions": "E-mailadres voor factuurverzending (automatisch gevuld vanuit Sportlink)",
    "required": false,
    "readonly": true
  },
  "factuur-referentie": {
    "type": "text",
    "label": "Factuur referentie",
    "instructions": "Externe referentiecode voor facturen (automatisch gevuld vanuit Sportlink)",
    "required": false,
    "maxlength": 100,
    "readonly": true
  }
}
```

**Note:** Fields are read-only in the UI since they are managed by Sportlink sync. Manual edits would be overwritten.

---

## UI Display Location

### Person Card: Financieel Card

Add a new card on the Person detail page:

```
+----------------------------------+
| Financieel                    ⚙️ |
+----------------------------------+
| Factuuradres:                    |
| Kerkstraat 42                    |
| 1234 AB Amsterdam                |
|                                  |
| Factuur e-mail:                  |
| finance@bedrijf.nl               |
|                                  |
| Referentie:                      |
| PO-2024-1234                     |
+----------------------------------+
```

### Display Rules

- **Card visibility**: Only show "Financieel" card if at least one invoice field has a value
- **Field visibility**: Only show individual fields when they have values
- **Formatting**: Display address with line breaks preserved

---

## REST API

### Existing Person Endpoints

Invoice fields are automatically included in the standard Person API responses since they are ACF fields:

```bash
# Get person with invoice data
GET /wp/v2/people/{id}
```

Response includes:
```json
{
  "id": 123,
  "acf": {
    "first_name": "Jan",
    "infix": "",
    "last_name": "Jansen",
    "factuur-adres": "Kerkstraat 42\n1234 AB Amsterdam",
    "factuur-email": "finance@bedrijf.nl",
    "factuur-referentie": "PO-2024-1234"
  }
}
```

### Updates via API

Invoice fields can be updated via PUT request (requires `first_name` and `last_name` as always, `infix` optional):

```bash
PUT /wp/v2/people/{id}
Content-Type: application/json

{
  "acf": {
    "first_name": "Jan",
    "last_name": "Jansen",
    "factuur-email": "new-finance@bedrijf.nl"
  }
}
```

---

## Data Source

### Sportlink Financial Tab

Data is extracted from Sportlink's `/financial` member page via two API endpoints:

1. **MemberPaymentInvoiceAddress API**
   - `Address.StreetName` → invoice street
   - `Address.AddressNumber` → invoice house number
   - `Address.AddressNumberAppendix` → house number addition
   - `Address.ZipCode` → postal code
   - `Address.City` → city
   - `Address.CountryName` → country
   - `Address.IsDefault` → determines if custom address is set

2. **MemberPaymentInvoiceInformation API**
   - `PaymentInvoiceInformation.EmailAddress` → `factuur-email`
   - `PaymentInvoiceInformation.ExternalInvoiceCode` → `factuur-referentie`

### Sync Behavior

- **Address**: Only synced to `factuur-adres` when `IsDefault = false` (member has set a custom invoice address)
- **Email**: Always synced when present
- **Reference**: Always synced when present
- Formatted address combines: street + house number + addition, postal code + city, country (if not Netherlands)

---

## Migration

No database migration required - fields use standard ACF post_meta storage.

### Steps

1. Add ACF field definitions to Stadion Person field group
2. Expose in REST API (automatic via ACF)
3. Add "Financieel" card to Person detail view
4. Deploy sportlink-sync changes to populate data

---

## Testing

### Verify Field Storage

```bash
# After sync, check person has invoice data
curl -u user:pass "https://stadion/wp-json/wp/v2/people/123" | jq '.acf | {
  "factuur-adres": .["factuur-adres"],
  "factuur-email": .["factuur-email"],
  "factuur-referentie": .["factuur-referentie"]
}'
```

### Expected Response

```json
{
  "factuur-adres": "Kerkstraat 42\n1234 AB Amsterdam",
  "factuur-email": "finance@bedrijf.nl",
  "factuur-referentie": "PO-2024-1234"
}
```

---

## Future Considerations

1. **Editable in UI**: Allow manual edits with conflict detection vs Sportlink
2. **Bank Account**: Add `factuur-iban` field for IBAN numbers
3. **Payment Method**: Track preferred payment method (automatic incasso, overboeking)
4. **Invoice History**: Link to invoice records if Stadion adds billing features
