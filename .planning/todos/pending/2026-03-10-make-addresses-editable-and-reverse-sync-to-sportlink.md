---
created: 2026-03-10T19:29:41.616Z
title: Make addresses editable and reverse sync to Sportlink
area: general
files:
  - rondo-club/ (person addresses repeater, REST API, UI)
  - rondo-sync/ (address sync, reverse sync)
---

## Problem

Addresses in Rondo Club are currently read-only — they come from Sportlink via rondo-sync but cannot be edited in the Rondo Club UI. Users need to edit addresses directly in Rondo Club and have those changes sync back to Sportlink.

The address data model was recently updated (quick tasks 123-124):
- `street` replaced with `street_name`, `house_number`, `house_number_addition`
- `factuur-adres` removed; billing address now stored as an `adressen` repeater entry with `address_label = "Factuur"`

## Solution

### rondo-club:
- Add address editing UI (AddressEditModal or inline editing on PersonDetail)
- Ensure REST API accepts address updates via the `adressen` repeater
- Fields per address: `address_label`, `street_name`, `house_number`, `house_number_addition`, `postcode`, `city`, `country`

### rondo-sync:
- Add reverse sync support for address fields (Rondo → Sportlink)
- Map Rondo address fields back to Sportlink's `StreetName`, `AddressNumber`, `AddressNumberAppendix`, `Zipcode`, `City`, `Country`
- Handle the Factuur-labeled address separately if Sportlink has a billing address concept
