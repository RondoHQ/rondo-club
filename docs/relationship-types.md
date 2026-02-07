# Relationship Types Configuration

## Overview

Relationship Types are taxonomy terms that define the types of relationships between people. Each relationship type can be configured with an inverse relationship type, which determines what relationship is automatically created on the other person's record.

## Default Types

Three relationship types are included by default:

| Type | Inverse | Category |
|------|---------|----------|
| Parent | Child | Asymmetric |
| Child | Parent | Asymmetric |
| Sibling | Sibling | Symmetric |

## Accessing Relationship Types Settings

1. Navigate to **Settings** in the main navigation
2. Click on **Relationship Types** in the Configuration section
3. You'll see a list of all relationship types with their inverse mappings

## Creating a New Relationship Type

1. Click **Add Relationship Type**
2. Enter the **Name** (e.g., "Godparent", "Neighbor", "Teammate")
3. Optionally select an **Inverse Relationship Type**:
   - For symmetric relationships, select the same type (e.g., "Friend" -> "Friend")
   - For asymmetric relationships, select the inverse (e.g., "Mentor" -> "Mentee")
   - Leave empty if there's no inverse
4. Click **Save**

The new relationship type will be available immediately when creating relationships.

## Editing Relationship Types

1. Find the relationship type in the list
2. Click the **Edit** button (pencil icon)
3. Modify the **Name** or **Inverse Relationship Type**
4. Click **Save**

**Note**: Changing an inverse mapping will update existing inverse relationships the next time those relationships are edited.

## Setting Inverse Mappings

### Symmetric Relationships

For relationships where both sides are the same type, select the same relationship type as its inverse:

- **Sibling** -> **Sibling**

### Asymmetric Relationships

For hierarchical or directional relationships, set each type to map to its counterpart:

- **Parent** -> **Child**
- **Child** -> **Parent**

### No Inverse

Some relationship types may not have a meaningful inverse:

- Leave the inverse field empty
- No automatic inverse relationship will be created
- You can manually create relationships in both directions if needed

## Using the Searchable Selector

The inverse relationship type selector is searchable:

1. Click in the field
2. Start typing to filter relationship types
3. Select from the filtered results
4. The selector includes all types, including the current type itself (for symmetric relationships)

## Common Configuration

### Family Tree Setup

```
Parent -> Child
Child -> Parent
Sibling -> Sibling
```

This is the default configuration. The system also automatically creates sibling relationships when multiple children share the same parent.

## Restore Defaults

If you need to restore the default inverse mappings, use the **Restore Defaults** button on the Relationship Types settings page. This will reset the inverse mappings for parent, child, and sibling to their default configuration without affecting any custom types you may have added.

## Best Practices

1. **Set inverses for all types**: Even if a relationship seems one-way, consider if there's a meaningful inverse
2. **Use consistent naming**: Keep relationship type names clear and consistent
3. **Test mappings**: After configuring, create a test relationship to verify the inverse is created correctly
4. **Document custom types**: If you create custom relationship types, document their purpose and inverse mappings

## Deleting Relationship Types

1. Find the relationship type in the list
2. Click the **Delete** button (trash icon)
3. Confirm the deletion

**Warning**: Deleting a relationship type will remove it from all existing relationships. The inverse relationships will also be affected. Consider editing relationships first if you want to change types rather than delete them.

## Troubleshooting

### Inverse not working

- Verify the inverse mapping is set correctly
- Check that both relationship types exist

### Can't find a relationship type

- Use the search function in the selector
- Check if the type was accidentally deleted
- Verify you're looking in the right section

### Wrong inverse created

- Double-check the inverse mapping configuration
- Test with a new relationship to see if the issue persists
