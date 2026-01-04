# Relationship Types Configuration

## Overview

Relationship Types are taxonomy terms that define the types of relationships between people. Each relationship type can be configured with an inverse relationship type, which determines what relationship is automatically created on the other person's record.

## Accessing Relationship Types Settings

1. Navigate to **Settings** in the main navigation
2. Click on **Relationship Types** in the Configuration section
3. You'll see a list of all relationship types with their inverse mappings

## Creating a New Relationship Type

1. Click **Add Relationship Type**
2. Enter the **Name** (e.g., "Godparent", "Neighbor", "Teammate")
3. Optionally select an **Inverse Relationship Type**:
   - For symmetric relationships, select the same type (e.g., "Friend" → "Friend")
   - For asymmetric relationships, select the inverse (e.g., "Parent" → "Child")
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

For relationships where both sides are the same type:

- **Spouse** → **Spouse**
- **Friend** → **Friend**
- **Colleague** → **Colleague**
- **Acquaintance** → **Acquaintance**

Simply select the same relationship type as its inverse.

### Parent-Child Relationships

For hierarchical family relationships:

- **Parent** → **Child**
- **Child** → **Parent**
- **Grandparent** → **Grandchild**
- **Grandchild** → **Grandparent**
- **Stepparent** → **Stepchild**
- **Stepchild** → **Stepparent**
- **Godparent** → **Godchild**
- **Godchild** → **Godparent**

### Extended Family

For aunt/uncle and niece/nephew relationships:

- **Uncle** → **Nephew** (if related person is male) or **Niece** (if related person is female)
- **Aunt** → **Nephew** (if related person is male) or **Niece** (if related person is female)
- **Nephew** → **Uncle** (if related person is male) or **Aunt** (if related person is female)
- **Niece** → **Uncle** (if related person is male) or **Aunt** (if related person is female)

These are gender-dependent and will be automatically resolved based on the related person's gender.

### Professional Relationships

For work-related connections:

- **Boss** → **Subordinate**
- **Subordinate** → **Boss**
- **Mentor** → **Mentee**
- **Mentee** → **Mentor**

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

## Common Configurations

### Complete Family Tree Setup

```
Parent → Child
Child → Parent
Grandparent → Grandchild
Grandchild → Grandparent
Stepparent → Stepchild
Stepchild → Stepparent
Uncle → Nephew/Niece (gender-dependent)
Aunt → Nephew/Niece (gender-dependent)
Nephew → Uncle/Aunt (gender-dependent)
Niece → Uncle/Aunt (gender-dependent)
Cousin → Cousin
Sibling → Sibling
```

### Professional Network

```
Boss → Subordinate
Subordinate → Boss
Mentor → Mentee
Mentee → Mentor
Colleague → Colleague
```

### Social Relationships

```
Friend → Friend
Acquaintance → Acquaintance
Partner → Partner
Spouse → Spouse
Ex → Ex
```

## Best Practices

1. **Set inverses for all types**: Even if a relationship seems one-way, consider if there's a meaningful inverse
2. **Use consistent naming**: Keep relationship type names clear and consistent
3. **Test mappings**: After configuring, create a test relationship to verify the inverse is created correctly
4. **Document custom types**: If you create custom relationship types, document their purpose and inverse mappings
5. **Review periodically**: Check your relationship types list periodically to ensure all mappings are correct

## Deleting Relationship Types

1. Find the relationship type in the list
2. Click the **Delete** button (trash icon)
3. Confirm the deletion

**Warning**: Deleting a relationship type will remove it from all existing relationships. The inverse relationships will also be affected. Consider editing relationships first if you want to change types rather than delete them.

## Troubleshooting

### Inverse not working

- Verify the inverse mapping is set correctly
- Check that both relationship types exist
- Ensure the related person's gender is set (for gender-dependent types)

### Can't find a relationship type

- Use the search function in the selector
- Check if the type was accidentally deleted
- Verify you're looking in the right section

### Wrong inverse created

- Double-check the inverse mapping configuration
- For gender-dependent types, verify both people have correct gender values
- Test with a new relationship to see if the issue persists

