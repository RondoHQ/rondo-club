# Family Tree Visualization

This document describes the family tree visualization feature that displays relationships between people as an interactive graph.

## Overview

The family tree feature provides an interactive visualization of family relationships, showing:
- Generational hierarchy (parents above, children below)
- Spouse/partner connections (side-by-side on same level)
- Profile photos and demographic information
- Deceased status indicators

## Technology

| Component | Technology |
|-----------|------------|
| Visualization | vis.js Network |
| Data Layer | vis-data DataSet |
| UI | React with TanStack Query |
| Styling | Tailwind CSS |

## URL

```
/people/{id}/family-tree
```

Shows the family tree centered on the specified person.

## Components

### Page Component

**`src/pages/People/FamilyTree.jsx`**

The main page that:
1. Fetches the current person and all people
2. Fetches relationship types for type resolution
3. Builds graph data from relationships
4. Renders the tree visualization

### Visualization Component

**`src/components/family-tree/TreeVisualization.jsx`**

Renders the vis.js Network with:
- Hierarchical layout (parents → children)
- Zoom/pan controls
- Click handling for navigation
- Double-click to center on a node

### Node Component (Reference)

**`src/components/family-tree/PersonNode.jsx`**

A standalone React component for person cards. Currently unused as vis.js renders nodes natively, but available for future custom rendering needs.

## Graph Building

### Relationship Types Included

The tree only shows family relationships:

```javascript
const FAMILY_RELATIONSHIP_TYPES = ['parent', 'child', 'spouse', 'lover', 'partner'];
```

Other relationship types (friend, colleague, etc.) are excluded from the tree.

### Builder Functions

Located in `src/utils/familyTreeBuilder.js`:

| Function | Purpose |
|----------|---------|
| `buildFamilyGraph()` | Creates graph from relationships via BFS traversal |
| `graphToVisFormat()` | Converts graph to vis.js nodes/edges with levels |
| `buildRelationshipMap()` | Extracts relationships from people data |
| `enrichRelationshipsWithTypes()` | Adds type slugs from relationship type taxonomy |

### Graph Data Flow

```
People Data → buildRelationshipMap() → buildFamilyGraph() → graphToVisFormat() → vis.js Network
```

### Edge Types

| Type | Visual | Description |
|------|--------|-------------|
| Parent-Child | Solid gray line | From parent to child, vertical |
| Spouse | Dashed pink line | Between partners, horizontal |

### Generation Calculation

Generations are calculated relative to the starting person using BFS:

| Generation | Level | Position |
|------------|-------|----------|
| Grandparents | -2 | Top |
| Parents | -1 | Above |
| Start Person | 0 | Center |
| Children | +1 | Below |
| Grandchildren | +2 | Bottom |

Spouses always share the same level as their partner.

## Node Appearance

### Node Properties

Each node displays:
- **Photo** - Profile thumbnail or initials placeholder
- **Name** - Person's full name (+ † if deceased)
- **Gender Symbol** - ♂ (male), ♀ (female), ⚧ (other)
- **Age** - Calculated from birth date
- **Birth Date** - Formatted as DD-MM-YYYY

### Visual Indicators

| Status | Border | Background | Text |
|--------|--------|------------|------|
| Start Person | Amber (3px) | Yellow highlight | Normal |
| Normal | Gray (2px) | White | Normal |
| Deceased | Dark gray | Gray placeholder | Muted gray |

### Deceased Detection

The system checks the `is_deceased` field from the person record:

```javascript
const isDeceased = person.is_deceased === true;
```

This field is computed server-side and included in the person API response.

## Interactions

### Mouse Controls

| Action | Effect |
|--------|--------|
| Click node | Navigate to person detail page |
| Double-click node | Center view on that node |
| Scroll | Zoom in/out |
| Drag background | Pan view |

### Keyboard Controls

| Key | Effect |
|-----|--------|
| Arrow keys | Pan view |
| +/- | Zoom in/out |

### Control Buttons

Three buttons in the top-right corner:

| Button | Icon | Effect |
|--------|------|--------|
| Zoom In | 🔍+ | Increase zoom 1.3x |
| Zoom Out | 🔍- | Decrease zoom 1.3x |
| Reset | ↺ | Fit entire tree in view |

## Layout Configuration

### vis.js Options

```javascript
layout: {
  hierarchical: {
    enabled: true,
    direction: 'UD',           // Up-Down
    sortMethod: 'directed',
    levelSeparation: 180,      // Vertical spacing
    nodeSpacing: 200,          // Horizontal spacing
    treeSpacing: 250,
    blockShifting: true,
    edgeMinimization: true,
    parentCentralization: true,
  },
}
```

### Post-Layout Spouse Adjustment

After initial layout, spouses are repositioned to be closer together:

```javascript
// Spouse pairs are moved 60px apart centered on their midpoint
newPositions[id1] = { x: centerX - 60, y: pos1.y };
newPositions[id2] = { x: centerX + 60, y: pos2.y };
```

## Empty State

When no family relationships exist:

```
No family relationships found.
Add parent or child relationships to see the family tree.
```

## Performance Considerations

### Data Loading

- All people are fetched once and cached
- Relationship types are cached for 5 minutes
- Person dates are fetched in parallel for deceased status

### Graph Complexity

For large family trees:
- BFS traversal ensures all connected members are included
- Edge deduplication prevents redundant connections
- Physics is disabled for stable, predictable layout

## Adding Relationships

To add people to the family tree:

1. Go to the person's detail page
2. Add a relationship with type: parent, child, spouse, partner, or lover
3. The inverse relationship is automatically created
4. Return to the family tree to see the updated graph

## Troubleshooting

### Tree Not Showing

1. **No family relationships** - Add parent/child/spouse relationships
2. **Wrong relationship types** - Only family types (parent, child, spouse, partner, lover) appear
3. **Data loading** - Wait for all data to load

### Wrong Hierarchy

1. **Check relationship direction** - Parent should have "child" relationship to children
2. **Check relationship types** - Use correct type (parent vs child)

### Performance Issues

For very large trees (100+ nodes):
1. Consider filtering to direct ancestors/descendants
2. Reduce `levelSeparation` and `nodeSpacing`

## Related Documentation

- [Data Model](./data-model.md) - Person relationships field
- [Relationships](./relationships.md) - How relationships work
- [Frontend Architecture](./frontend-architecture.md) - React component structure

