# T03: 213-sitewide-rollout 03

**Slice:** S02 — **Milestone:** M001

## Description

Apply correct button tier hierarchy to Feedback, VOG, Contributie, Clothing, Todos pages and DataTable toolbar components.

Purpose: These are the remaining pages that need tier assignment. The DataTable toolbar is used across many list pages and its filter/column buttons should consistently use btn-tertiary. Feedback, VOG, Contributie pages have a mix of primary CTAs and utility buttons.

Output: All 13 remaining files using correct btn-* tiers with no rogue styles.

## Must-Haves

- [ ] "On Feedback, VOG, Contributie, Clothing pages all buttons use correct tier for their action weight"
- [ ] "DataTable toolbar filter button and column settings button use btn-tertiary"
- [ ] "Clear filter buttons use btn-tertiary across all pages"
- [ ] "Primary actions (Create, Send) use btn-primary; utility actions use btn-tertiary"
- [ ] "SeasonSelector dropdown styled as btn-tertiary (no lift, no shadow)"

## Files

- `src/pages/Feedback/FeedbackList.jsx`
- `src/pages/Feedback/FeedbackDetail.jsx`
- `src/pages/VOG/VOGList.jsx`
- `src/pages/VOG/VOGUpcoming.jsx`
- `src/pages/Contributie/ContributieList.jsx`
- `src/pages/Contributie/ContributieOverzicht.jsx`
- `src/pages/Contributie/NogTeFactureren.jsx`
- `src/pages/Contributie/SeasonSelector.jsx`
- `src/pages/Clothing/ClothingPage.jsx`
- `src/pages/Todos/TodosList.jsx`
- `src/components/DataTable/DataTableToolbar.jsx`
- `src/components/DataTable/ColumnSettingsPanel.jsx`
- `src/components/CustomFieldsSection.jsx`
