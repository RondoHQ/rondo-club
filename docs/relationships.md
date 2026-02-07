# Relationships System

## Overview

The system includes a bidirectional relationship system that automatically synchronizes relationships between people. When you create a relationship from Person A to Person B, the system automatically creates the inverse relationship from Person B to Person A.

## How Relationships Work

### Storage

Relationships are stored as an ACF (Advanced Custom Fields) repeater field on each Person post. Each relationship entry contains:

- **Related Person**: The ID of the person this relationship refers to
- **Relationship Type**: A taxonomy term ID indicating the type of relationship (Parent, Child, or Sibling)
- **Custom Label**: An optional override label for the relationship

### Automatic Bidirectional Sync

When you create, update, or delete a relationship, the system automatically:

1. **Creates the inverse relationship** on the related person's record
2. **Updates the inverse relationship** if the relationship type changes
3. **Deletes the inverse relationship** if the original is removed

The inverse relationship type is determined by the ACF field configuration on the Relationship Type taxonomy term.

### Example Flow

**Scenario**: You create a relationship where Person A is a "Parent" to Person B.

1. System stores: Person A -> Person B = "Parent"
2. System looks up inverse mapping: "Parent" -> "Child"
3. System creates: Person B -> Person A = "Child"

Both relationships are now synchronized.

## Default Relationship Types

Three relationship types are included by default:

### Symmetric Relationships

- **Sibling** <> **Sibling** (same type as inverse)

### Asymmetric Relationships

- **Parent** <> **Child** (each maps to the other)

### Automatic Sibling Sync

When a parent-child relationship is created, the system automatically creates sibling relationships between all children who share the same parent. If a parent-child link is removed and the child has no remaining parents, sibling relationships are cleaned up.

## Custom Relationship Types

Users can add custom relationship types via Settings > Relationship Types. Each custom type can be configured with an inverse mapping:

- For symmetric types: set the inverse to the same type
- For asymmetric types: set each type's inverse to its counterpart
- Leave the inverse empty for one-way relationships

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

## Best Practices

1. **Always set inverse mappings**: Configure inverse relationships for all relationship types you use
2. **Keep mappings consistent**: Ensure bidirectional mappings are set up correctly (if A->B maps to C, then C->A should map back to A's type)
3. **Test relationships**: After configuring new relationship types, test that inverses are created correctly

## Troubleshooting

### Inverse relationship not created

- Check that the relationship type has an inverse mapping configured in Settings > Relationship Types
- Verify both people exist and are accessible
- Check for JavaScript errors in the browser console

### Wrong inverse type created

- Verify the inverse mapping is correct in Settings > Relationship Types
- Check that the relationship type taxonomy terms exist

### Duplicate relationships

- The system prevents duplicates automatically
- If duplicates appear, check for conflicting relationship entries
- Use the delete function to remove incorrect relationships
