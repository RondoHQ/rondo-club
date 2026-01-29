# Quick Task 014: Team Activiteit column and subtitle

## Description

1. Add "Activiteit" as a column on the Teams list
2. Add "{Activiteit} - {Gender}" as a subtitle in the Team detail page under the header

## Tasks

1. Verify TeamsList already supports custom field columns (it does - via `listViewFields` with `show_in_list_view: true`)
2. Add subtitle display in TeamDetail.jsx showing "Activiteit - Gender"

## Analysis

The TeamsList.jsx already has full support for showing custom fields as columns:
- Line 465-478: Fetches custom field metadata for team post type
- Line 474-478: Filters to `show_in_list_view` enabled fields
- Line 219-235: Renders custom field columns using CustomFieldColumn component

User needs to enable "Show in list view" for the Activiteit field in Settings > Custom Fields.

For TeamDetail, we add a subtitle line below the team name.
