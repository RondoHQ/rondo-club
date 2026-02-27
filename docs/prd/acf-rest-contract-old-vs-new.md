# REST Contract: Old vs New (ACF Removal)

## Purpose

This document defines the **hard-cutover REST contract** for removing ACF from API payloads.

- **Old** = current production behavior with `acf` payloads.
- **New** = target behavior after cutover.
- Scope is the endpoints currently used by app code and sync flows that read/write ACF-shaped data.

## Global Rules (New Contract)

1. `acf` is removed from all request and response payloads.
2. New payload bucket is `fields`.
3. Field keys are normalized to snake_case:
   - `-` becomes `_` (`knvb-id` -> `knvb_id`, `datum-vog` -> `datum_vog`).
4. Repeater/object structures stay structurally the same unless explicitly documented below.
5. Unsupported legacy keys are removed (`industry`, `relatiecode`, `vog-email-verzonden`).
6. Custom-field management endpoints are removed (code-based definitions only).

## Endpoint Index

| Endpoint | Old | New |
|---|---|---|
| `GET /wp/v2/people`, `GET /wp/v2/people/{id}` | returns `acf` | returns `fields` |
| `POST /wp/v2/people`, `PUT /wp/v2/people/{id}` | accepts `acf` | accepts `fields` |
| `GET /rondo/v1/people/filtered` | each row contains `acf` | each row contains `fields` |
| `GET /wp/v2/teams`, `GET /wp/v2/teams/{id}` | returns `acf` | returns `fields` |
| `POST /wp/v2/teams`, `PUT /wp/v2/teams/{id}` | accepts `acf` | accepts `fields` |
| `GET /wp/v2/commissies`, `GET /wp/v2/commissies/{id}` | returns `acf` | returns `fields` |
| `POST /wp/v2/commissies`, `PUT /wp/v2/commissies/{id}` | accepts `acf` | accepts `fields` |
| `GET /wp/v2/discipline-cases`, `GET /wp/v2/discipline-cases/{id}` | returns `acf` | returns `fields` |
| `POST /wp/v2/discipline-cases`, `PUT /wp/v2/discipline-cases/{id}` | accepts `acf` | accepts `fields` |
| `GET/POST /wp/v2/relationship_type` (+ single term update) | uses `acf` for inverse relation | uses `fields` |
| `GET /rondo/v1/entity/{id}` | embeds `acf` | embeds `fields` |
| `POST /rondo/v1/google-sheets/export-people` | column IDs use old ACF key names | column IDs use normalized new key names |
| `/rondo/v1/custom-fields/*` | CRUD + metadata | removed |

## 1) People (`/wp/v2/people`)

### 1.1 GET collection/single

Old response (relevant fragment):

```json
{
  "id": 123,
  "title": { "rendered": "Jan Jansen" },
  "acf": {
    "first_name": "Jan",
    "infix": "",
    "last_name": "Jansen",
    "knvb-id": "1234567",
    "datum-vog": "2024-03-01",
    "huidig-vrijwilliger": true,
    "work_history": [],
    "contact_info": [],
    "relationships": [],
    "vog_email_sent_date": "2025-01-10"
  },
  "is_deceased": false,
  "birth_year": 2003
}
```

New response (relevant fragment):

```json
{
  "id": 123,
  "title": { "rendered": "Jan Jansen" },
  "fields": {
    "first_name": "Jan",
    "infix": "",
    "last_name": "Jansen",
    "knvb_id": "1234567",
    "datum_vog": "2024-03-01",
    "huidig_vrijwilliger": true,
    "work_history": [],
    "contact_info": [],
    "relationships": [],
    "vog_email_sent_date": "2025-01-10"
  },
  "is_deceased": false,
  "birth_year": 2003
}
```

### 1.2 Create/update

Old request:

```json
{
  "title": "Jan Jansen",
  "status": "publish",
  "acf": {
    "first_name": "Jan",
    "last_name": "Jansen",
    "contact_info": [
      { "contact_type": "email", "contact_label": "Email", "contact_value": "jan@example.com" }
    ]
  }
}
```

New request:

```json
{
  "title": "Jan Jansen",
  "status": "publish",
  "fields": {
    "first_name": "Jan",
    "last_name": "Jansen",
    "contact_info": [
      { "contact_type": "email", "contact_label": "Email", "contact_value": "jan@example.com" }
    ]
  }
}
```

### 1.3 Person field key map

| Old key (`acf`) | New key (`fields`) |
|---|---|
| `first_name` | `first_name` |
| `infix` | `infix` |
| `last_name` | `last_name` |
| `nickname` | `nickname` |
| `gender` | `gender` |
| `pronouns` | `pronouns` |
| `birthdate` | `birthdate` |
| `photo_gallery` | `photo_gallery` |
| `former_member` | `former_member` |
| `lid-tot` | `lid_tot` |
| `datum-overlijden` | `datum_overlijden` |
| `contact_info` | `contact_info` |
| `addresses` | `addresses` |
| `work_history` | `work_history` |
| `relationships` | `relationships` |
| `knvb-id` | `knvb_id` |
| `isparent` | `isparent` |
| `type-lid` | `type_lid` |
| `leeftijdsgroep` | `leeftijdsgroep` |
| `lid-sinds` | `lid_sinds` |
| `vrijwilliger-sinds` | `vrijwilliger_sinds` |
| `datum-foto` | `datum_foto` |
| `datum-vog` | `datum_vog` |
| `huidig-vrijwilliger` | `huidig_vrijwilliger` |
| `financiele-blokkade` | `financiele_blokkade` |
| `freescout-id` | `freescout_id` |
| `factuur-adres` | `factuur_adres` |
| `factuur-email` | `factuur_email` |
| `factuur-referentie` | `factuur_referentie` |
| `vog_email_sent_date` | `vog_email_sent_date` |
| `vog_justis_submitted_date` | `vog_justis_submitted_date` |
| `vog_reminder_sent_date` | `vog_reminder_sent_date` |

### 1.4 Nested row map

`work_history[]`:
- `team` -> `team_id`
- `entity_type` -> `entity_type`
- `job_title` -> `job_title`
- `description` -> `description`
- `start_date` -> `start_date`
- `end_date` -> `end_date`
- `is_current` -> `is_current`

`relationships[]`:
- `related_person` -> `related_person_id`
- `relationship_type` -> `relationship_type_id`
- `relationship_label` -> `relationship_label`

## 2) People Filtered (`GET /rondo/v1/people/filtered`)

### 2.1 Response row

Old:

```json
{
  "id": 123,
  "first_name": "Jan",
  "last_name": "Jansen",
  "acf": {
    "knvb-id": "1234567",
    "datum-vog": "2024-03-01"
  }
}
```

New:

```json
{
  "id": 123,
  "first_name": "Jan",
  "last_name": "Jansen",
  "fields": {
    "knvb_id": "1234567",
    "datum_vog": "2024-03-01"
  }
}
```

### 2.2 Query params

Existing filter params remain unchanged unless listed below.

`orderby` changes:
- Old: `custom_knvb-id`, `custom_type-lid`, `custom_datum-vog`, etc.
- New: `field_knvb_id`, `field_type_lid`, `field_datum_vog`, etc.

Examples:
- `custom_huidig-vrijwilliger` -> `field_huidig_vrijwilliger`
- `custom_financiele-blokkade` -> `field_financiele_blokkade`
- `custom_lid-sinds` -> `field_lid_sinds`

## 3) Teams (`/wp/v2/teams`)

### 3.1 GET

Old fragment:

```json
{
  "id": 45,
  "acf": {
    "website": "https://example.org",
    "contact_info": [],
    "publicteamid": "TM123",
    "activiteit": "Veld - Zaterdag",
    "gender": "male",
    "investors": [1, 2]
  },
  "player_count": 12,
  "staff_count": 4
}
```

New fragment:

```json
{
  "id": 45,
  "fields": {
    "website": "https://example.org",
    "contact_info": [],
    "publicteamid": "TM123",
    "activiteit": "Veld - Zaterdag",
    "gender": "male",
    "investors": [1, 2]
  },
  "player_count": 12,
  "staff_count": 4
}
```

### 3.2 Create/update

Old request:

```json
{
  "title": "JO17-1",
  "status": "publish",
  "acf": {
    "website": "https://example.org",
    "investors": [1, 2]
  }
}
```

New request:

```json
{
  "title": "JO17-1",
  "status": "publish",
  "fields": {
    "website": "https://example.org",
    "investors": [1, 2]
  }
}
```

## 4) Commissies (`/wp/v2/commissies`)

### 4.1 GET

Old fragment:

```json
{
  "id": 88,
  "acf": {
    "website": "https://commissie.example",
    "contact_info": []
  },
  "member_count": 6
}
```

New fragment:

```json
{
  "id": 88,
  "fields": {
    "website": "https://commissie.example",
    "contact_info": []
  },
  "member_count": 6
}
```

### 4.2 Create/update

Old uses `acf`; new uses `fields` with same value structure.

## 5) Discipline Cases (`/wp/v2/discipline-cases`)

### 5.1 GET

Old fragment:

```json
{
  "id": 301,
  "acf": {
    "dossier_id": "D-2025-1001",
    "person": 123,
    "match_date": "20250118",
    "processing_date": "20250125",
    "match_description": "Team A - Team B",
    "team_name": "JO17-1",
    "home_team": 45,
    "away_team": 0,
    "charge_codes": "B1",
    "charge_description": "Omschrijving",
    "sanction_description": "2 wedstrijden",
    "administrative_fee": 25,
    "is_charged": "rondo"
  }
}
```

New fragment:

```json
{
  "id": 301,
  "fields": {
    "dossier_id": "D-2025-1001",
    "person_id": 123,
    "match_date": "20250118",
    "processing_date": "20250125",
    "match_description": "Team A - Team B",
    "team_name": "JO17-1",
    "home_team_id": 45,
    "away_team_id": 0,
    "charge_codes": "B1",
    "charge_description": "Omschrijving",
    "sanction_description": "2 wedstrijden",
    "administrative_fee": 25,
    "is_charged": "rondo"
  }
}
```

`is_charged = "exception"` remains possible as computed value for exempt teams.

### 5.2 Create/update

Old uses `acf`; new uses `fields`.

## 6) Relationship Types (`/wp/v2/relationship_type`)

### 6.1 GET

Old fragment:

```json
{
  "id": 12,
  "name": "Ouder",
  "slug": "ouder",
  "acf": {
    "inverse_relationship_type": 13
  }
}
```

New fragment:

```json
{
  "id": 12,
  "name": "Ouder",
  "slug": "ouder",
  "fields": {
    "inverse_relationship_type_id": 13
  }
}
```

### 6.2 Create/update

Old request:

```json
{
  "name": "Ouder",
  "slug": "ouder",
  "acf": {
    "inverse_relationship_type": 13
  }
}
```

New request:

```json
{
  "name": "Ouder",
  "slug": "ouder",
  "fields": {
    "inverse_relationship_type_id": 13
  }
}
```

## 7) Entity Lookup (`GET /rondo/v1/entity/{id}`)

Old fragment:

```json
{
  "id": 45,
  "type": "team",
  "acf": {
    "website": "https://example.org",
    "activiteit": "Veld - Zaterdag"
  }
}
```

New fragment:

```json
{
  "id": 45,
  "type": "team",
  "fields": {
    "website": "https://example.org",
    "activiteit": "Veld - Zaterdag"
  }
}
```

## 8) Google Sheets Export (`POST /rondo/v1/google-sheets/export-people`)

### 8.1 Request columns

Old request:

```json
{
  "columns": ["name", "knvb-id", "datum-vog", "huidig-vrijwilliger"],
  "filters": {},
  "title": "Export"
}
```

New request:

```json
{
  "columns": ["name", "knvb_id", "datum_vog", "huidig_vrijwilliger"],
  "filters": {},
  "title": "Export"
}
```

### 8.2 Column key mapping

| Old column ID | New column ID |
|---|---|
| `knvb-id` | `knvb_id` |
| `type-lid` | `type_lid` |
| `lid-sinds` | `lid_sinds` |
| `vrijwilliger-sinds` | `vrijwilliger_sinds` |
| `datum-foto` | `datum_foto` |
| `datum-vog` | `datum_vog` |
| `huidig-vrijwilliger` | `huidig_vrijwilliger` |
| `financiele-blokkade` | `financiele_blokkade` |
| `freescout-id` | `freescout_id` |
| `factuur-adres` | `factuur_adres` |
| `factuur-email` | `factuur_email` |
| `factuur-referentie` | `factuur_referentie` |

## 9) Custom Field API (`/rondo/v1/custom-fields/*`)

### 9.1 Old endpoints

- `GET /rondo/v1/custom-fields/{post_type}`
- `POST /rondo/v1/custom-fields/{post_type}`
- `GET /rondo/v1/custom-fields/{post_type}/{field_key}`
- `PUT /rondo/v1/custom-fields/{post_type}/{field_key}`
- `DELETE /rondo/v1/custom-fields/{post_type}/{field_key}`
- `GET /rondo/v1/custom-fields/{post_type}/metadata`
- `PUT /rondo/v1/custom-fields/{post_type}/order`

### 9.2 New contract

- These endpoints are removed.
- Field definitions become code-only.
- If a read-only metadata endpoint is still needed by UI, replacement endpoint is:
  - `GET /rondo/v1/field-definitions/{post_type}` (read-only, no CRUD).

## 10) Sync Endpoints (No Contract Shape Change)

These endpoints are not ACF payload endpoints; request/response shape remains unchanged:

- `GET /rondo/v1/people/find-by-email?email=...` -> `{ "id": <int|null> }`
- `POST /rondo/v1/capability-sync` -> `{ "knvb_id": "...", "functies": [...] }`
- `POST /rondo/v1/sportlink/sync-individual` -> `{ "knvb_id": "..." }`

## Removed and Non-Mapped Legacy Fields

- Removed outright: `industry`, `relatiecode`, `vog-email-verzonden`.
- Removed from API payloads with no replacement: `_shared_with` (sharing model no longer field-based).

## Migration Notes

1. Replace every `payload.acf` write with `payload.fields`.
2. Replace every `response.acf` read with `response.fields`.
3. Rename all dashed keys to underscore keys.
4. Update `orderby` values from `custom_*` to `field_*` normalized keys in `people/filtered`.
5. Update Google Sheets column IDs to normalized keys.
6. Remove all usage of `/rondo/v1/custom-fields/*`.
