# SV Voorbeeld demo showcase

`demo-showcase.json` is a fictional club fixture with 214 people, 12 teams, eight committees,
and 20 linked feature scenarios. It contains no production exports, real contact addresses,
bank accounts, provider credentials, or payment links.

Regenerate the same JSON with Python 3 and PHP installed:

```sh
python3 bin/generate-demo-showcase.py
```

The importer resolves all relationships after allocating IDs. Dates are relative to import day
in the WordPress timezone; `{season}` resolves to the season containing that day.
The fixture uses canonical field names from the native registry. Scalar internal state, such
as display pairing status, uses the corresponding native metadata storage key.

## Review and load

Deploy the theme first. Import is refused unless `rondo_is_demo_site` is enabled.
Validation checks the complete fixture before cleanup. Start with a read-only validation:

```sh
wp rondo demo import --input=wp-content/themes/rondo-club/fixtures/demo-showcase.json --dry-run
```

After approving replacement and making a private backup of the existing demo database:

```sh
wp rondo demo import --input=wp-content/themes/rondo-club/fixtures/demo-showcase.json --clean
```

`--clean` is required for a showcase import. It replaces the demo's Rondo records and settings,
including finance provider configuration, but preserves WordPress accounts, credentials for the
existing demo login, and completed migrations. New module records are deleted in batches of 100.
Native deletion protections stay active. If linked people or assigned/cancelled shifts are present,
the reset stops before deleting any records; review and approve a separate maintenance reset
before loading the replacement. A clean import replaces records instead of appending them.

The existing `demo` account is linked to Anna Bos and receives Rondo board and roster roles plus
training, Club TV, match registration, football administration, committee and feedback capabilities.
It receives no WordPress administrator role or `manage_options` capability.
Showcase email delivery is blocked and reports an unsent result. Payment providers remain
unconfigured after cleanup; no real checkout or financial transfer is part of this fixture.

## Feature tour

Reference names below map to real IDs in the `rondo_demo_showcase_manifest` option after import.

| Feature | Deliberate examples |
| --- | --- |
| Members, households and self-service | Anna Bos (`person:p001`), 12 parents linked to children (including three siblings), six former members with closed roles; demo account opens Anna's own profile |
| Teams, staff and calendars | Youth, girls, women, seniors, O23, G-football and walking football; five fictional fixtures per team including results and a cancellation |
| Committees and volunteers | Eight committees and volunteer pools; current and former roles |
| VOG and IVA | Current certificates, expired VOG (`person:p003`), missing VOG, approved IVA and manual exemption (`person:p004`) |
| Volunteer shifts | Open, full, completed and cancelled shifts; past shift with an extra retrospective helper (`dienst_shift:shift2`), recurring templates and task instructions |
| Contributions and installments | Draft, sent, overdue and paid invoices; three and eight installments (`rondo_invoice:i9`, `rondo_invoice:i10`), financial block and family relationships |
| Discipline | Eight fictional cases, billed and unbilled, with current-season classification |
| Tasks and feedback | Overdue, open, awaiting and completed tasks; feedback in three stages |
| Anniversaries | A member with 25 years of membership (`person:p006`) |
| Sponsors | Four fictional businesses and linked contacts, businessclub and sponsor-pass variants |
| Training and park calendar | Active weekly timetable, winter proposal and an upcoming maintenance closure |
| Rooms | Three rooms with opening hours and facilities, five confirmed reservations and one cancellation |
| Tournaments | Draft, open and closed editions; invited teams, open/submitted entries, a linked unpaid invoice and a manually paid example |
| Communication and newsletters | Concept, preparing, ready, published and cancelled items; monthly series, three channels and a fictional sender profile |
| Club TV | Two fictional displays, a five-item playlist, sponsor, welcome, programme and results slides |
| Entry control | Three fixtures and six historical admissions |
| Membership and guest passes | Current members, former-member restrictions, sponsor variants and Anna's claimed guest slot |
| Clothing | Three items, multiple sizes, deposits, issues and a return |
| Canteen revenue and costs | Fourteen synthetic daily reports with valid hourly activity and product sales, purchases, products and match-day context |
| Match compensation | Configured senior/O23 schemes; two completed registrations and a draft, with starter and substitute selections |
| Onboarding and change history | New member awaiting transfer/source confirmation, a welcome round and an audited contact change |

## Provider-dependent features

This is a data showcase, not a provider sandbox. Real Sportlink synchronization, Laposta export
and sending, payment checkout, bank transfers, email delivery, Apple/Google wallet provisioning,
calendar-provider synchronization, VOG document review, and physical Club TV players require
separate test accounts/devices or remain disabled on demo. The dataset includes the relevant
local records and honest missing-configuration states; it does not invent successful provider
operations. Seeded team programmes are local fixtures with a cache valid for the current season.

The old ignored `demo-fixture.json` export and version 1 importer remain supported.
