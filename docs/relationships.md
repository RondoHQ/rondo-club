# Relationships System

## Overview

The Personal CRM system includes a powerful bidirectional relationship system that automatically synchronizes relationships between people. When you create a relationship from Person A to Person B, the system automatically creates the inverse relationship from Person B to Person A.

## How Relationships Work

### Storage

Relationships are stored as an ACF (Advanced Custom Fields) repeater field on each Person post. Each relationship entry contains:

- **Related Person**: The ID of the person this relationship refers to
- **Relationship Type**: A taxonomy term ID indicating the type of relationship (e.g., Parent, Child, Spouse, Friend)
- **Custom Label**: An optional override label for the relationship

### Automatic Bidirectional Sync

When you create, update, or delete a relationship, the system automatically:

1. **Creates the inverse relationship** on the related person's record
2. **Updates the inverse relationship** if the relationship type changes
3. **Deletes the inverse relationship** if the original is removed

The inverse relationship type is determined by the ACF field configuration on the Relationship Type taxonomy term.

### Example Flow

**Scenario**: You create a relationship where Person A is a "Parent" to Person B.

1. System stores: Person A → Person B = "Parent"
2. System looks up inverse mapping: "Parent" → "Child"
3. System creates: Person B → Person A = "Child"

Both relationships are now synchronized. If you later change Person A's relationship to "Grandparent", the system will:
- Remove the old "Child" relationship from Person B
- Create a new "Grandchild" relationship from Person B to Person A

## Relationship Types

### Symmetric Relationships

Some relationships are symmetric - the inverse is the same type:

- **Spouse** → **Spouse**
- **Friend** → **Friend**
- **Colleague** → **Colleague**
- **Acquaintance** → **Acquaintance**
- **Sibling** → **Sibling**
- **Cousin** → **Cousin**

### Asymmetric Relationships

Most relationships have a specific inverse:

- **Parent** ↔ **Child**
- **Grandparent** ↔ **Grandchild**
- **Boss** ↔ **Subordinate**
- **Mentor** ↔ **Mentee**
- **Uncle** ↔ **Nephew**
- **Aunt** ↔ **Niece**

### Gender-Dependent Relationships

Some relationship types depend on the gender of the people involved:

- **Aunt/Uncle**: Depends on Person A's gender (aunt if female, uncle if male)
- **Niece/Nephew**: Depends on Person B's gender (niece if female, nephew if male)

When creating an inverse relationship, the system automatically resolves gender-dependent types based on the related person's gender.

**Example**: 
- Person A (female) creates "Aunt" relationship to Person B
- System creates inverse: Person B → Person A
- If Person B is female: inverse = "Niece"
- If Person B is male: inverse = "Nephew"

## Creating Relationships

### Via Frontend

1. Navigate to a Person's detail page
2. Click "Add Relationship" in the Relationships section
3. Select the related person
4. Choose the relationship type
5. Optionally add a custom label
6. Save

The inverse relationship is created automatically.

### Via WordPress Admin

1. Edit a Person post
2. Go to the Relationships tab
3. Add a relationship row
4. Select person and type
5. Update the post

The inverse relationship is created automatically via ACF hooks.

### Via REST API

When updating a person's `relationships` field via REST API, the inverse relationships are automatically synchronized.

## Editing Relationships

When you edit a relationship:

- **Change the person**: Old inverse is deleted, new inverse is created
- **Change the type**: Old inverse type is removed, new inverse type is created
- **Change the label**: Label is copied to the inverse relationship

## Deleting Relationships

When you delete a relationship, the corresponding inverse relationship is automatically removed from the related person's record.

## Edge Cases

### Missing Inverse Mapping

If a relationship type doesn't have an inverse mapping configured, no inverse relationship will be created. This is useful for one-way relationships or when you want to manually manage inverses.

### Circular References

The system prevents infinite loops when syncing relationships. If Person A and Person B are being updated simultaneously, the sync process tracks which posts are being processed to avoid circular updates.

### Missing People

If the related person doesn't exist or isn't accessible, the inverse relationship creation is skipped.

### Missing Gender Information

For gender-dependent relationships, if the related person's gender is not set, the system will use a default behavior (typically the first option in the gender-dependent group).

## Best Practices

1. **Always set inverse mappings**: Configure inverse relationships for all relationship types you use
2. **Use specific types**: Prefer specific types (aunt, uncle) over generic ones when gender is known
3. **Keep mappings consistent**: Ensure bidirectional mappings are set up correctly (if A→B maps to C, then C→A should map back to A's type)
4. **Test relationships**: After configuring new relationship types, test that inverses are created correctly

## Troubleshooting

### Inverse relationship not created

- Check that the relationship type has an inverse mapping configured in Settings → Relationship Types
- Verify both people exist and are accessible
- Check for JavaScript errors in the browser console

### Wrong inverse type created

- Verify the inverse mapping is correct in Settings → Relationship Types
- For gender-dependent types, ensure both people have gender set correctly
- Check that the relationship type taxonomy terms exist

### Duplicate relationships

- The system prevents duplicates automatically
- If duplicates appear, check for conflicting relationship entries
- Use the delete function to remove incorrect relationships

