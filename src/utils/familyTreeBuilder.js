/**
 * Family Tree Builder Utilities
 * 
 * Builds tree structures from relationship data for family tree visualization.
 * 
 * Approach:
 * 1. Collect all relevant family members (ancestors, siblings, descendants)
 * 2. Build tree from root ancestors downward
 */

// Family relationship type slugs - only parent/child for tree
const FAMILY_RELATIONSHIP_TYPES = ['parent', 'child'];

/**
 * Check if a relationship type is a family relationship
 */
export function isFamilyRelationshipType(typeSlug) {
  return FAMILY_RELATIONSHIP_TYPES.includes(typeSlug?.toLowerCase());
}

/**
 * Check if relationship type indicates parent
 */
function isParentType(typeSlug) {
  return typeSlug?.toLowerCase() === 'parent';
}

/**
 * Check if relationship type indicates child
 */
function isChildType(typeSlug) {
  return typeSlug?.toLowerCase() === 'child';
}

/**
 * Build a graph structure from relationships
 */
export function buildFamilyGraph(startPersonId, allPeople, relationshipMap) {
  const nodes = new Map();
  const edges = [];
  const visited = new Set();
  
  const peopleMap = new Map();
  allPeople.forEach(person => peopleMap.set(person.id, person));
  
  const queue = [{ personId: startPersonId, depth: 0 }];
  visited.add(startPersonId);
  
  // Helper to extract person data
  function extractPersonData(person, personId) {
    let personName = person.name || person.title?.rendered || person.title || `Person ${personId}`;
    if (personName && typeof personName === 'string' && typeof document !== 'undefined') {
      const txt = document.createElement('textarea');
      txt.innerHTML = personName;
      personName = txt.value;
    }
    
    let age = null;
    if (person.acf?.birth_date) {
      const birthDate = new Date(person.acf.birth_date);
      if (!isNaN(birthDate.getTime())) {
        age = Math.floor((new Date() - birthDate) / (365.25 * 24 * 60 * 60 * 1000));
      }
    }
    
    return {
      id: personId,
      name: personName,
      gender: person.acf?.gender || '',
      photo: person.thumbnail || null,
      age,
      birthDate: person.acf?.birth_date || null,
    };
  }
  
  // Add start person
  const startPerson = peopleMap.get(startPersonId);
  if (startPerson) {
    nodes.set(startPersonId, extractPersonData(startPerson, startPersonId));
  }
  
  // BFS traversal
  while (queue.length > 0) {
    const { personId, depth } = queue.shift();
    const relationships = relationshipMap.get(personId) || [];
    
    for (const rel of relationships) {
      if (rel.relationship_type_slug && !isFamilyRelationshipType(rel.relationship_type_slug)) {
        continue;
      }
      
      const relatedPersonId = rel.related_person;
      if (!relatedPersonId || !peopleMap.has(relatedPersonId)) continue;
      
      if (!nodes.has(relatedPersonId)) {
        const relatedPerson = peopleMap.get(relatedPersonId);
        nodes.set(relatedPersonId, extractPersonData(relatedPerson, relatedPersonId));
      }
      
      // Add edge if not exists
      const edgeExists = edges.some(e => 
        (e.from === personId && e.to === relatedPersonId) ||
        (e.from === relatedPersonId && e.to === personId)
      );
      
      if (!edgeExists) {
        edges.push({
          from: personId,
          to: relatedPersonId,
          type: rel.relationship_type_slug,
          label: rel.relationship_name || rel.relationship_label || '',
        });
      }
      
      if (!visited.has(relatedPersonId)) {
        visited.add(relatedPersonId);
        queue.push({ personId: relatedPersonId, depth: depth + 1 });
      }
    }
  }
  
  return { nodes: Array.from(nodes.values()), edges };
}

// ============================================================================
// HELPER FUNCTIONS FOR TREE BUILDING
// ============================================================================

/**
 * Get parents of a person from adjacency list
 * @param {number} personId 
 * @param {Map} adjacencyList 
 * @returns {number[]} Array of parent IDs
 */
function getParents(personId, adjacencyList) {
  const neighbors = adjacencyList.get(personId) || [];
  return neighbors
    .filter(n => isChildType(n.type)) // Person has "child" relationship = neighbor is parent
    .map(n => n.nodeId);
}

/**
 * Get children of a person from adjacency list
 * @param {number} personId 
 * @param {Map} adjacencyList 
 * @returns {number[]} Array of child IDs
 */
function getChildren(personId, adjacencyList) {
  const neighbors = adjacencyList.get(personId) || [];
  return neighbors
    .filter(n => isParentType(n.type)) // Person has "parent" relationship = neighbor is child
    .map(n => n.nodeId);
}

/**
 * Get siblings of a person (other children of their parents)
 * @param {number} personId 
 * @param {Map} adjacencyList 
 * @returns {number[]} Array of sibling IDs
 */
function getSiblings(personId, adjacencyList) {
  const parents = getParents(personId, adjacencyList);
  const siblings = new Set();
  
  for (const parentId of parents) {
    const children = getChildren(parentId, adjacencyList);
    children.forEach(childId => {
      if (childId !== personId) {
        siblings.add(childId);
      }
    });
  }
  
  return Array.from(siblings);
}

/**
 * Find root ancestors (people with no parents) in a set of family members
 * @param {Set} familySet 
 * @param {Map} adjacencyList 
 * @returns {number[]} Array of root ancestor IDs
 */
function findRoots(familySet, adjacencyList) {
  const roots = [];
  
  for (const personId of familySet) {
    const parents = getParents(personId, adjacencyList);
    // Check if any parent is in the family set
    const hasParentInFamily = parents.some(p => familySet.has(p));
    if (!hasParentInFamily) {
      roots.push(personId);
    }
  }
  
  return roots;
}

// ============================================================================
// PHASE 1: COLLECT FAMILY MEMBERS
// ============================================================================

/**
 * Collect all relevant family members starting from a person
 * - Traverse UP: ancestors and their siblings
 * - Current person's siblings
 * - Traverse DOWN: descendants
 * 
 * @param {number} startPersonId 
 * @param {Map} adjacencyList 
 * @returns {Set} Set of person IDs in the family tree
 */
function collectFamilyMembers(startPersonId, adjacencyList) {
  const familySet = new Set();
  const visitedUp = new Set();
  
  // Add current person
  familySet.add(startPersonId);
  
  // Add current person's siblings
  getSiblings(startPersonId, adjacencyList).forEach(id => familySet.add(id));
  
  // Traverse UP: collect ancestors and their siblings
  function traverseUp(personId) {
    if (visitedUp.has(personId)) return;
    visitedUp.add(personId);
    
    const parents = getParents(personId, adjacencyList);
    for (const parentId of parents) {
      familySet.add(parentId);
      
      // Add parent's siblings (aunts/uncles)
      getSiblings(parentId, adjacencyList).forEach(id => familySet.add(id));
      
      // Continue up
      traverseUp(parentId);
    }
  }
  
  traverseUp(startPersonId);
  
  // Traverse DOWN: collect descendants
  const visitedDown = new Set();
  
  function traverseDown(personId) {
    if (visitedDown.has(personId)) return;
    visitedDown.add(personId);
    
    const children = getChildren(personId, adjacencyList);
    for (const childId of children) {
      familySet.add(childId);
      traverseDown(childId);
    }
  }
  
  traverseDown(startPersonId);
  
  return familySet;
}

// ============================================================================
// PHASE 2: BUILD TREE STRUCTURE
// ============================================================================

/**
 * Convert graph to hierarchical tree structure for react-d3-tree
 * 
 * @param {Object} graph - Graph with nodes and edges
 * @param {number} startPersonId - ID of the person to start from
 * @returns {Object} Tree structure compatible with react-d3-tree
 */
export function graphToTree(graph, startPersonId) {
  const { nodes, edges } = graph;
  
  if (!nodes.find(n => n.id === startPersonId)) {
    return null;
  }
  
  // Build adjacency list with bidirectional relationships
  const adjacencyList = new Map();
  nodes.forEach(node => adjacencyList.set(node.id, []));
  
  edges.forEach(edge => {
    const relType = edge.type?.toLowerCase();
    
    // Store edge in both directions with correct relationship types
    adjacencyList.get(edge.from)?.push({
      nodeId: edge.to,
      type: edge.type,
      label: edge.label,
    });
    
    // Inverse relationship
    let inverseType = edge.type;
    if (isParentType(relType)) {
      inverseType = 'child';
    } else if (isChildType(relType)) {
      inverseType = 'parent';
    }
    
    adjacencyList.get(edge.to)?.push({
      nodeId: edge.from,
      type: inverseType,
      label: edge.label,
    });
  });
  
  // Phase 1: Collect all relevant family members
  const familySet = collectFamilyMembers(startPersonId, adjacencyList);
  
  // Phase 2: Find roots and build tree
  const roots = findRoots(familySet, adjacencyList);
  
  // Sort roots by birth date (oldest first)
  roots.sort((a, b) => {
    const nodeA = nodes.find(n => n.id === a);
    const nodeB = nodes.find(n => n.id === b);
    const dateA = nodeA?.birthDate;
    const dateB = nodeB?.birthDate;
    if (!dateA && !dateB) return 0;
    if (!dateA) return 1;
    if (!dateB) return -1;
    return new Date(dateA) - new Date(dateB);
  });
  
  // Build tree recursively
  const visited = new Set();
  
  function buildNode(personId) {
    if (visited.has(personId) || !familySet.has(personId)) {
      return null;
    }
    visited.add(personId);
    
    const node = nodes.find(n => n.id === personId);
    if (!node) return null;
    
    // Find children that are in our family set
    const childIds = getChildren(personId, adjacencyList)
      .filter(id => familySet.has(id) && !visited.has(id));
    
    // Sort children by birth date (oldest first)
    childIds.sort((a, b) => {
      const nodeA = nodes.find(n => n.id === a);
      const nodeB = nodes.find(n => n.id === b);
      const dateA = nodeA?.birthDate;
      const dateB = nodeB?.birthDate;
      if (!dateA && !dateB) return 0;
      if (!dateA) return 1;
      if (!dateB) return -1;
      return new Date(dateA) - new Date(dateB);
    });
    
    // Build child nodes
    const children = childIds
      .map(id => buildNode(id))
      .filter(Boolean);
    
    return {
      name: node.name || `Person ${node.id}`,
      attributes: {
        id: node.id,
        gender: node.gender || '',
        photo: node.photo || null,
        age: node.age,
        birthDate: node.birthDate || null,
      },
      children,
    };
  }
  
  // Build tree(s) from root(s)
  let tree;
  
  if (roots.length === 0) {
    // No roots found, use start person
    tree = buildNode(startPersonId);
  } else if (roots.length === 1) {
    // Single root
    tree = buildNode(roots[0]);
  } else {
    // Multiple roots - create hidden virtual root
    const rootTrees = roots.map(id => buildNode(id)).filter(Boolean);
    
    tree = {
      name: '',
      attributes: {
        id: 'virtual-root',
        isVirtualRoot: true,
        hidden: true,
      },
      children: rootTrees,
    };
  }
  
  return tree;
}

// ============================================================================
// RELATIONSHIP MAP UTILITIES
// ============================================================================

/**
 * Build relationship map from people data
 */
export function buildRelationshipMap(people) {
  const relationshipMap = new Map();
  
  people.forEach(person => {
    const relationships = person.acf?.relationships || [];
    const personRelationships = relationships.map(rel => {
      let typeSlug = '';
      
      if (rel.relationship_slug) {
        typeSlug = rel.relationship_slug.toLowerCase();
      } else if (typeof rel.relationship_type === 'object') {
        typeSlug = rel.relationship_type?.slug || rel.relationship_type?.name?.toLowerCase() || '';
      } else if (typeof rel.relationship_type === 'string') {
        typeSlug = rel.relationship_type.toLowerCase();
      }
      
      let relatedPersonId = null;
      if (typeof rel.related_person === 'object') {
        relatedPersonId = rel.related_person?.ID || rel.related_person?.id || rel.related_person?.term_id;
      } else if (typeof rel.related_person === 'number' || typeof rel.related_person === 'string') {
        relatedPersonId = parseInt(rel.related_person, 10);
      }
      
      return {
        related_person: relatedPersonId,
        relationship_type: rel.relationship_type,
        relationship_type_slug: typeSlug,
        relationship_name: rel.relationship_name || '',
        relationship_label: rel.relationship_label || '',
      };
    }).filter(rel => rel.related_person);
    
    relationshipMap.set(person.id, personRelationships);
  });
  
  return relationshipMap;
}

/**
 * Enrich relationships with type slugs from relationship types data
 */
export function enrichRelationshipsWithTypes(relationshipMap, relationshipTypes) {
  const typeMap = new Map();
  relationshipTypes.forEach(type => {
    typeMap.set(type.id, type.slug || type.name?.toLowerCase());
  });
  
  relationshipMap.forEach((relationships) => {
    relationships.forEach(rel => {
      if (!rel.relationship_type_slug && rel.relationship_type) {
        const typeId = typeof rel.relationship_type === 'object'
          ? rel.relationship_type.term_id || rel.relationship_type.id
          : rel.relationship_type;
        
        rel.relationship_type_slug = typeMap.get(typeId) || '';
      }
    });
  });
}
