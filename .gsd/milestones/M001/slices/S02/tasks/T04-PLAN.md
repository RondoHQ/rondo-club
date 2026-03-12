# T04: 213-sitewide-rollout 04

**Slice:** S02 — **Milestone:** M001

## Description

Apply correct button tier hierarchy to People, Teams, Commissies, Settings, Profile, and remaining page files (14 files).

Purpose: These pages have back-navigation, share, filter, and utility buttons that should be tertiary. Settings has Save buttons (primary) and utility buttons (tertiary). Split from original 213-02 to keep plans within scope limits.

Output: All 14 page files using correct btn-* tiers with no rogue styles.

## Must-Haves

- [ ] "On People, Teams, Commissies list pages, filter clear and utility buttons use btn-tertiary"
- [ ] "On detail pages, back-navigation links use btn-tertiary, share buttons use btn-tertiary"
- [ ] "On Settings pages, Save buttons use btn-primary, utility and add-item buttons use correct tiers"
- [ ] "No redundant inline-flex/items-center/px-4/py-2/rounded-lg classes remain alongside btn-* classes"

## Files

- `src/pages/People/PeopleList.jsx`
- `src/pages/People/PersonDetail.jsx`
- `src/pages/Teams/TeamDetail.jsx`
- `src/pages/Teams/TeamsList.jsx`
- `src/pages/Teams/Kaderlijst.jsx`
- `src/pages/Commissies/CommissieDetail.jsx`
- `src/pages/Commissies/CommissiesList.jsx`
- `src/pages/Settings/Settings.jsx`
- `src/pages/Settings/CustomFields.jsx`
- `src/pages/Settings/RelationshipTypes.jsx`
- `src/pages/Settings/FeedbackManagement.jsx`
- `src/pages/Profile/Profile.jsx`
- `src/pages/MembershipPassScanner.jsx`
- `src/router.jsx`
