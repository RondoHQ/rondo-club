# Quick Task 014: Summary

## What Changed

### TeamDetail.jsx
Added subtitle display below team name showing "Activiteit - Gender":
- Shows below the team name in the header card
- Only displays if at least one of the fields has a value
- Uses filter(Boolean).join(' - ') to gracefully handle missing values
- File: `src/pages/Teams/TeamDetail.jsx:322-326`

### TeamsList.jsx (No changes needed)
The Teams list already supports custom field columns via the existing infrastructure:
- Custom fields with `show_in_list_view: true` automatically appear as columns
- User should enable "Show in list view" for Activiteit in Settings > Teamvelden

## How to enable Activiteit column on Teams list

1. Go to Settings > Aangepaste velden > Teamvelden
2. Edit the "Activiteit" field
3. Enable "Toon in lijstweergave" checkbox
4. Save

The column will automatically appear in the Teams list.
