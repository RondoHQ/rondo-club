# S02: Sitewide Rollout

**Goal:** Apply correct button tier hierarchy to all Finance-related pages and components.
**Demo:** Apply correct button tier hierarchy to all Finance-related pages and components.

## Must-Haves


## Tasks

- [x] **T01: 213-sitewide-rollout 01** `est:6min`
  - Apply correct button tier hierarchy to all Finance-related pages and components.

Purpose: Finance pages are the most complex button area — FactuurDetail alone has ~20 buttons with 3 inline color override patterns (green for mark-paid, red for delete, orange for reset) that must be replaced with proper tier classes. This plan eliminates all rogue inline styles and assigns correct tiers based on action weight.

Output: All 8 Finance-related files using only btn-primary/secondary/tertiary/danger with no inline color overrides.
- [x] **T02: 213-sitewide-rollout 02**
  - Apply correct button tier hierarchy to all modal dialogs (22 files).

Purpose: Modal dialogs are the most consistent pattern — Save=primary, Cancel=secondary. Most modals already follow this but need cleanup of redundant Tailwind classes. DeleteFieldDialog has inline red styles that must become btn-danger.

Output: All 22 modal/dialog files using clean btn-* classes with no redundant inline styles.
- [x] **T03: 213-sitewide-rollout 03**
  - Apply correct button tier hierarchy to Feedback, VOG, Contributie, Clothing, Todos pages and DataTable toolbar components.

Purpose: These are the remaining pages that need tier assignment. The DataTable toolbar is used across many list pages and its filter/column buttons should consistently use btn-tertiary. Feedback, VOG, Contributie pages have a mix of primary CTAs and utility buttons.

Output: All 13 remaining files using correct btn-* tiers with no rogue styles.
- [x] **T04: 213-sitewide-rollout 04** `est:5min`
  - Apply correct button tier hierarchy to People, Teams, Commissies, Settings, Profile, and remaining page files (14 files).

Purpose: These pages have back-navigation, share, filter, and utility buttons that should be tertiary. Settings has Save buttons (primary) and utility buttons (tertiary). Split from original 213-02 to keep plans within scope limits.

Output: All 14 page files using correct btn-* tiers with no rogue styles.

## Files Likely Touched

- `src/pages/Finance/FactuurDetail.jsx`
- `src/pages/Finance/Facturen.jsx`
- `src/pages/Finance/FinanceSettings.jsx`
- `src/pages/Finance/FinanceDashboard.jsx`
- `src/pages/Finance/FactuurNieuw.jsx`
- `src/components/finance/InvoiceDraftForm.jsx`
- `src/components/FinancesCard.jsx`
- `src/components/DisciplineCaseTable.jsx`
- `src/components/AddressEditModal.jsx`
- `src/components/ContactEditModal.jsx`
- `src/components/PersonEditModal.jsx`
- `src/components/RelationshipEditModal.jsx`
- `src/components/WorkHistoryEditModal.jsx`
- `src/components/CustomFieldsEditModal.jsx`
- `src/components/CommissieEditModal.jsx`
- `src/components/TeamEditModal.jsx`
- `src/components/FeedbackModal.jsx`
- `src/components/FeedbackEditModal.jsx`
- `src/components/DashboardCustomizeModal.jsx`
- `src/components/ShareModal.jsx`
- `src/components/DeleteFieldDialog.jsx`
- `src/components/MeetingDetailModal.jsx`
- `src/components/Timeline/GlobalTodoModal.jsx`
- `src/components/Timeline/TodoModal.jsx`
- `src/components/Timeline/CompleteTodoModal.jsx`
- `src/components/Timeline/NoteModal.jsx`
- `src/components/Timeline/QuickActivityModal.jsx`
- `src/components/FieldFormPanel.jsx`
- `src/components/AccountCard.jsx`
- `src/pages/People/ColumnSettingsModal.jsx`
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
