# VOG Filtered People API

This document describes how to retrieve the list of volunteers who need a new or renewed VOG (Verklaring Omtrent het Gedrag), including their KNVB IDs. This is the same data shown on the VOG tab in the frontend.

## Endpoint

**GET** `/stadion/v1/people/filtered`

**Permission:** Approved users only (`check_user_approved`)

## VOG Tab Parameters

To retrieve exactly the people shown on the VOG tab, use these parameters:

| Name | Value | Description |
|------|-------|-------------|
| `huidig_vrijwilliger` | `1` | Only current volunteers |
| `vog_missing` | `1` | People without VOG date |
| `vog_older_than_years` | `3` | People whose VOG is older than 3 years |
| `per_page` | `100` | Results per page (max 100) |
| `page` | `1` | Page number |

### Example Request

```
GET /wp-json/stadion/v1/people/filtered?huidig_vrijwilliger=1&vog_missing=1&vog_older_than_years=3&per_page=100&page=1
```

The combination of `vog_missing=1` and `vog_older_than_years=3` creates an OR condition:
- People with **no** VOG date (new volunteers), OR
- People whose VOG date is **older than 3 years** (need renewal)

### Example Response

```json
{
  "people": [
    {
      "id": 123,
      "first_name": "Jan",
      "infix": "de",
      "last_name": "Vries",
      "modified": "2026-01-15 10:30:00",
      "thumbnail": "https://example.com/wp-content/uploads/jan-de-vries-150x150.jpg",
      "labels": ["Vrijwilliger"],
      "acf": {
        "first_name": "Jan",
        "infix": "de",
        "last_name": "Vries",
        "knvb-id": "KNVB12345678",
        "datum-vog": "",
        "contact_info": [
          {
            "contact_type": "email",
            "contact_value": "jan@example.com",
            "contact_label": "Email"
          },
          {
            "contact_type": "mobile",
            "contact_value": "06-12345678",
            "contact_label": "Mobile"
          }
        ],
        "vog_email_sent_date": "2026-01-10",
        "vog_justis_submitted_date": ""
      }
    }
  ],
  "total": 25,
  "page": 1,
  "total_pages": 1
}
```

### Key Fields per Person

| Field | Location | Description |
|-------|----------|-------------|
| KNVB ID | `acf.knvb-id` | The person's KNVB registration number |
| VOG date | `acf.datum-vog` | Date of last VOG (empty = new volunteer) |
| Email | `acf.contact_info[]` | Find entry where `contact_type` = `email` |
| Phone | `acf.contact_info[]` | Find entry where `contact_type` = `phone` or `mobile` |
| VOG email sent | `acf.vog_email_sent_date` | Date the VOG request email was sent |
| Justis submitted | `acf.vog_justis_submitted_date` | Date submitted to Justis system |

## Optional VOG Filters

These additional filters narrow down the VOG list:

| Name | Values | Description |
|------|--------|-------------|
| `vog_type` | `nieuw`, `vernieuwing` | `nieuw` = no VOG date, `vernieuwing` = expired VOG |
| `vog_email_status` | `sent`, `not_sent` | Filter by whether VOG email has been sent |
| `vog_justis_status` | `submitted`, `not_submitted` | Filter by Justis submission status |

### Example: Only New Volunteers Needing VOG

```
GET /wp-json/stadion/v1/people/filtered?huidig_vrijwilliger=1&vog_type=nieuw&per_page=100
```

### Example: Only VOG Renewals

```
GET /wp-json/stadion/v1/people/filtered?huidig_vrijwilliger=1&vog_type=vernieuwing&vog_older_than_years=3&per_page=100
```

### Example: Not Yet Emailed

```
GET /wp-json/stadion/v1/people/filtered?huidig_vrijwilliger=1&vog_missing=1&vog_older_than_years=3&vog_email_status=not_sent&per_page=100
```

## Sorting

| Name | Values | Default | Description |
|------|--------|---------|-------------|
| `orderby` | `first_name`, `last_name`, `modified`, `custom_datum-vog`, `custom_vog_email_sent_date`, `custom_vog_justis_submitted_date` | `first_name` | Sort field |
| `order` | `asc`, `desc` | `asc` | Sort direction |

The VOG tab defaults to sorting by `custom_datum-vog` ascending (oldest/empty VOG dates first).

## Extracting KNVB IDs

To get just the KNVB IDs from the response, extract `acf.knvb-id` from each person in the `people` array:

```javascript
const response = await fetch('/wp-json/stadion/v1/people/filtered?huidig_vrijwilliger=1&vog_missing=1&vog_older_than_years=3&per_page=100');
const data = await response.json();

const knvbIds = data.people
  .map(person => person.acf?.['knvb-id'])
  .filter(Boolean);
```

## Volunteer Status Logic

A person is considered a "current volunteer" (`huidig_vrijwilliger=1`) if they have an active position where:
- The position is in a **commissie** (any role), OR
- The position is in a **team** with a **staff role** (not a player role)

Player roles (not counted as volunteer):
`Aanvaller`, `Verdediger`, `Keeper`, `Middenvelder`, `Teamspeler`, `Speler`, `Champions league`, `Zondag recranten`, `Zaterdag recreanten`

Honorary/membership roles (excluded):
`Donateur`, `Erelid`, `Lid van Verdienste`, `Verenigingslid voor het leven (contributievrij)`

Commissies listed in the `stadion_vog_exempt_commissies` option are also excluded from volunteer status.

## Related Documentation

- [REST API Overview](./rest-api.md) - All API endpoints
- [Access Control](./access-control.md) - Permission model
- [Data Model](./data-model.md) - Post types and fields
