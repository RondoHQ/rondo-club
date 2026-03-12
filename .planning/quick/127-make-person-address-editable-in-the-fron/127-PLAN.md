---
phase: quick-127
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - src/pages/People/PersonDetail.jsx
autonomous: true
requirements: [ADDR-EDIT]
must_haves:
  truths:
    - "User can add a new address to a person via AddressEditModal"
    - "User can edit an existing address on a person via AddressEditModal"
    - "User can delete an address from a person with confirmation"
    - "Edit/delete/add buttons only visible to users with canEditPeople"
  artifacts:
    - path: "src/pages/People/PersonDetail.jsx"
      provides: "Address CRUD UI integrated with AddressEditModal"
  key_links:
    - from: "PersonDetail.jsx address section"
      to: "AddressEditModal component"
      via: "showAddressModal state + onSubmit handler"
    - from: "PersonDetail.jsx handleSaveAddress"
      to: "updatePerson.mutateAsync"
      via: "sanitizePersonAcf with updated addresses array"
---

<objective>
Wire the existing AddressEditModal into PersonDetail.jsx to enable add, edit, and delete of person addresses.

Purpose: Addresses are currently read-only on the person detail page. The AddressEditModal component already exists but is not connected.
Output: Fully functional address CRUD on PersonDetail page following the same pattern as relationships.
</objective>

<execution_context>
@/Users/joostdevalk/.claude/get-shit-done/workflows/execute-plan.md
@/Users/joostdevalk/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@src/pages/People/PersonDetail.jsx
@src/components/AddressEditModal.jsx

<interfaces>
<!-- AddressEditModal props (from src/components/AddressEditModal.jsx): -->
```jsx
export default function AddressEditModal({
  isOpen,      // boolean - controls visibility
  onClose,     // () => void - close handler
  onSubmit,    // (data) => void - receives address object with all fields
  isLoading,   // boolean - disables form while saving
  address = null  // object|null - null for add mode, object for edit mode
})
```

<!-- Address object shape (from AddressEditModal form): -->
```js
{
  address_label: '',
  street_name: '',
  house_number: '',
  house_number_addition: '',
  postal_code: '',
  city: '',
  state: '',
  country: 'Netherlands',
  country_code: '',
}
```

<!-- Existing pattern from PersonDetail.jsx (relationship CRUD): -->
```jsx
// State pattern (lines 114-120):
const [showRelationshipModal, setShowRelationshipModal] = useState(false);
const [isSavingRelationship, setIsSavingRelationship] = useState(false);
const [editingRelationship, setEditingRelationship] = useState(null);
const [editingRelationshipIndex, setEditingRelationshipIndex] = useState(null);

// Save handler pattern (lines 188-227):
const handleSaveRelationship = async (data) => {
  setIsSavingRelationship(true);
  try {
    const relationships = [...(person.acf?.relationships || [])];
    // ... build item, splice or push ...
    const acfData = sanitizePersonAcf(person.acf, { relationships });
    await updatePerson.mutateAsync({ id, data: { acf: acfData } });
    setShowRelationshipModal(false);
  } catch (error) { console.error(error); }
  finally { setIsSavingRelationship(false); }
};

// Delete handler pattern (lines 228-280):
const handleDeleteRelationship = async (index) => {
  if (!window.confirm('...')) return;
  const relationships = [...(person.acf?.relationships || [])];
  relationships.splice(index, 1);
  const acfData = sanitizePersonAcf(person.acf, { relationships });
  await updatePerson.mutateAsync({ id, data: { acf: acfData } });
};
```

<!-- Icons already imported: Plus, Pencil, Trash2, MapPin -->
<!-- canEditPeople already available (line 79) -->
<!-- sanitizePersonAcf and updatePerson already available -->
</interfaces>
</context>

<tasks>

<task type="auto">
  <name>Task 1: Wire AddressEditModal into PersonDetail with add/edit/delete</name>
  <files>src/pages/People/PersonDetail.jsx</files>
  <action>
In `src/pages/People/PersonDetail.jsx`, make these changes:

1. **Add import** (after line 19, with the other modal imports):
   ```jsx
   import AddressEditModal from '@/components/AddressEditModal';
   ```

2. **Add state variables** (after line 120, with the other modal states):
   ```jsx
   const [showAddressModal, setShowAddressModal] = useState(false);
   const [isSavingAddress, setIsSavingAddress] = useState(false);
   const [editingAddress, setEditingAddress] = useState(null);
   const [editingAddressIndex, setEditingAddressIndex] = useState(null);
   ```

3. **Add handleSaveAddress handler** (after handleDeleteRelationship, around line 280). Follow the exact same pattern as handleSaveRelationship:
   ```jsx
   const handleSaveAddress = async (data) => {
     setIsSavingAddress(true);
     try {
       const addresses = [...(person.acf?.addresses || [])];
       if (editingAddressIndex !== null) {
         addresses[editingAddressIndex] = data;
       } else {
         addresses.push(data);
       }
       const acfData = sanitizePersonAcf(person.acf, { addresses });
       await updatePerson.mutateAsync({ id, data: { acf: acfData } });
       setShowAddressModal(false);
       setEditingAddress(null);
       setEditingAddressIndex(null);
     } catch (error) {
       console.error('Failed to save address:', error);
     } finally {
       setIsSavingAddress(false);
     }
   };
   ```

4. **Add handleDeleteAddress handler** (after handleSaveAddress):
   ```jsx
   const handleDeleteAddress = async (index) => {
     if (!window.confirm('Weet je zeker dat je dit adres wilt verwijderen?')) return;
     const addresses = [...(person.acf?.addresses || [])];
     addresses.splice(index, 1);
     const acfData = sanitizePersonAcf(person.acf, { addresses });
     await updatePerson.mutateAsync({ id, data: { acf: acfData } });
   };
   ```

5. **Update the Adressen card header** (line 1354). Replace the plain `<h2>` with a flex header containing the add button, matching the Relaties pattern (lines 1420-1436):
   ```jsx
   <div className="flex items-center justify-between mb-3">
     <h2 className="font-semibold text-brand-gradient">Adressen</h2>
     {canEditPeople && (
       <button
         onClick={() => {
           setEditingAddress(null);
           setEditingAddressIndex(null);
           setShowAddressModal(true);
         }}
         className="btn-tertiary text-sm"
         title="Adres toevoegen"
       >
         <Plus className="w-4 h-4" />
       </button>
     )}
   </div>
   ```
   Remove the old `mb-4` from the h2 since the wrapper div now has `mb-3`.

6. **Add edit/delete buttons to each address row** (inside the address map, around line 1367). Change the address row div from `<div className="flex items-start">` to include `group` class and add edit/delete buttons. The structure should be:
   ```jsx
   <div key={index} className="flex items-start group">
     <MapPin className="w-4 h-4 text-gray-400 mt-1 mr-3 flex-shrink-0" />
     <div className="flex-1 min-w-0">
       {/* ... existing address label and link content unchanged ... */}
     </div>
     {canEditPeople && (
       <div className="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity ml-2">
         <button
           onClick={() => {
             setEditingAddress(acf.addresses[index]);
             setEditingAddressIndex(index);
             setShowAddressModal(true);
           }}
           className="p-1 hover:bg-gray-100 dark:hover:bg-gray-700 rounded"
           title="Adres bewerken"
         >
           <Pencil className="w-4 h-4 text-gray-400 hover:text-gray-600" />
         </button>
         <button
           onClick={() => handleDeleteAddress(index)}
           className="p-1 hover:bg-red-50 rounded"
           title="Adres verwijderen"
         >
           <Trash2 className="w-4 h-4 text-gray-400 hover:text-red-600" />
         </button>
       </div>
     )}
   </div>
   ```

7. **Add "Toevoegen" link in empty state** (line 1390). Update the empty state paragraph to match relationships pattern:
   ```jsx
   <p className="text-sm text-gray-500 text-center py-4">
     Nog geen adressen.{canEditPeople && <> <button onClick={() => { setEditingAddress(null); setEditingAddressIndex(null); setShowAddressModal(true); }} className="text-electric-cyan hover:underline">Toevoegen</button></>}
   </p>
   ```

8. **Render AddressEditModal** at the bottom of the component (after the RelationshipEditModal block, around line 1981):
   ```jsx
   {canEditPeople && (
     <AddressEditModal
       isOpen={showAddressModal}
       onClose={() => {
         setShowAddressModal(false);
         setEditingAddress(null);
         setEditingAddressIndex(null);
       }}
       onSubmit={handleSaveAddress}
       isLoading={isSavingAddress}
       address={editingAddress}
     />
   )}
   ```
  </action>
  <verify>
    <automated>cd /Users/joostdevalk/Code/rondo/rondo-club && npm run build 2>&1 | tail -5</automated>
  </verify>
  <done>
    - AddressEditModal imported and rendered in PersonDetail
    - Add button (Plus icon) visible in Adressen card header for editors
    - Edit (Pencil) and delete (Trash2) buttons appear on hover for each address row
    - Empty state shows "Toevoegen" link for editors
    - Save handler updates addresses array via sanitizePersonAcf + updatePerson.mutateAsync
    - Delete handler removes address with confirmation dialog
    - Build passes without errors
  </done>
</task>

</tasks>

<verification>
- `npm run build` completes without errors
- `npm run lint` passes (max-warnings: 0)
- Deploy to production and verify on a person with addresses: edit/delete buttons appear on hover
- Verify on a person without addresses: "Toevoegen" link appears
- Test add, edit, and delete flows on production
</verification>

<success_criteria>
- Addresses on PersonDetail are fully editable (add, edit, delete)
- UI follows the same pattern as relationships (Plus button in header, Pencil/Trash2 on hover, modal for add/edit)
- Only visible to users with canEditPeople capability
- Build and lint pass cleanly
</success_criteria>

<output>
After completion, create `.planning/quick/127-make-person-address-editable-in-the-fron/127-SUMMARY.md`
</output>
