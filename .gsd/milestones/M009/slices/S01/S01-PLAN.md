# S01: Person detail page cleanup

## Tasks

- [ ] **T01: Implement all four UI changes** `est:small`
  - Remove VOG status pill from person header (the `{/* VOG Status Badge */}` block)
  - Hide Relaties card when `sortedRelationships.length === 0`
  - Show Account card only when `personData.linked_user_id` exists (not just admin + volunteer)
  - Add optional `count` prop to TabButton, display count on Tijdlijn, Tuchtzaken, Rollen, Kleding tabs
- [ ] **T02: Build, deploy, verify** `est:small`
