# Quick Task 012: Team Detail Three Column Layout

## Task Description

Split the Teams detail page into three columns under the team header:
1. **Spelers** - Players identified by roles: Aanvaller, Verdediger, Keeper, Middenvelder, Teamspeler
2. **Staf** - All other current members (non-player roles)
3. **Voormalig spelers** - Former players only (not former staff)

## Plan

### Task 1: Modify TeamDetail.jsx members section

**File:** `src/pages/Teams/TeamDetail.jsx`

**Changes:**
1. Define player roles array: `['Aanvaller', 'Verdediger', 'Keeper', 'Middenvelder', 'Teamspeler']`
2. Split `employees.current` into:
   - `players`: current members with player roles
   - `staff`: current members with non-player roles
3. Filter `employees.former` to only include former players
4. Change grid from 2 columns to 3 columns (`grid-cols-1 md:grid-cols-2 lg:grid-cols-3`)
5. Render three cards: Spelers, Staf, Voormalig spelers
