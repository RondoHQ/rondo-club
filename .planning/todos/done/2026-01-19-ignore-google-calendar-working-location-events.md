---
created: 2026-01-19T15:45
title: Ignore Google Calendar workingLocation events
area: api
files:
  - includes/class-rest-api.php
---

## Problem

Google Calendar has an `eventType` field that can be set to `workingLocation` for events that represent where someone is working from (e.g., "Working from home", "Working from office"). These events clutter the meeting widget on the dashboard since they're not actual meetings - they're location indicators.

Currently, these events are fetched and displayed alongside real meetings, which reduces the usefulness of the meeting widget.

## Solution

Filter out events with `eventType === 'workingLocation'` when fetching calendar events from Google Calendar API. This should be done server-side in the calendar sync/fetch logic to avoid unnecessary data transfer.
