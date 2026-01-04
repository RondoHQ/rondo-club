/**
 * Family Tree Builder Utilities
 * 
 * Builds tree structures from relationship data for family tree visualization.
 * 
 * Approach:
 * 1. Collect all relevant family members (ancestors, siblings, descendants)
 * 2. Build tree from root ancestors downward, pairing couples together
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
 */
function getParents(personId, adjacencyList) {
  const neighbors = adjacencyList.get(personId) || [];
  return neighbors
    .filter(n => isParentType(n.type))
    .map(n => n.nodeId);
}

/**
 * Get children of a person from adjacency list
 */
function getChildren(personId, adjacencyList) {
  const neighbors = adjacencyList.get(personId) || [];
  return neighbors
    .filter(n => isChildType(n.type))
    .map(n => n.nodeId);
}

/**
 * Get siblings of a person (other children of their parents)
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
 * Find the partner/spouse of a person based on shared children
 */
function getPartner(personId, adjacencyList, familySet) {
  const myChildren = getChildren(personId, adjacencyList).filter(id => familySet.has(id));
  if (myChildren.length === 0) return null;
  
  // Find someone who shares children with this person
  for (const childId of myChildren) {
    const childParents = getParents(childId, adjacencyList).filter(id => familySet.has(id));
    const partner = childParents.find(id => id !== personId);
    if (partner) return partner;
  }
  
  return null;
}

/**
 * Find root ancestors - people with no parents in the family set
 * For couples, returns only one person (they'll be paired later)
 */
function findRoots(familySet, adjacencyList, nodes) {
  const roots = [];
  const processed = new Set();
  
  for (const personId of familySet) {
    if (processed.has(personId)) continue;
    
    const parents = getParents(personId, adjacencyList);
    const hasParentInFamily = parents.some(p => familySet.has(p));
    
    if (!hasParentInFamily) {
      // This person is a root (no parents in family set)
      roots.push(personId);
      processed.add(personId);
      
      // Also mark their partner as processed so we don't add them separately
      const partner = getPartner(personId, adjacencyList, familySet);
      if (partner && !hasParentInFamily) {
        processed.add(partner);
      }
    }
  }
  
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
  
  return roots;
}

// ============================================================================
// PHASE 1: COLLECT FAMILY MEMBERS
// ============================================================================

/**
 * Collect all relevant family members starting from a person
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
      getSiblings(parentId, adjacencyList).forEach(id => familySet.add(id));
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
 * Couples are shown together as a single node with combined name
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
    
    adjacencyList.get(edge.from)?.push({
      nodeId: edge.to,
      type: edge.type,
      label: edge.label,
    });
    
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
  const roots = findRoots(familySet, adjacencyList, nodes);
  
  // Build tree recursively with couple pairing
  const visited = new Set();
  
  function buildNode(personId) {
    if (visited.has(personId) || !familySet.has(personId)) {
      return null;
    }
    
    const node = nodes.find(n => n.id === personId);
    if (!node) return null;
    
    // Check if this person has a partner (shares children with someone)
    const partnerId = getPartner(personId, adjacencyList, familySet);
    const partnerNode = partnerId ? nodes.find(n => n.id === partnerId) : null;
    
    // Mark both as visited
    visited.add(personId);
    if (partnerId) visited.add(partnerId);
    
    // Determine the display name
    let displayName = node.name;
    let displayPhoto = node.photo;
    let coupleIds = [personId];
    
    if (partnerNode && !visited.has(partnerId)) {
      // This shouldn't happen since we marked partner as visited above
    }
    
    if (partnerNode) {
      // Show as couple: "Person & Partner"
      displayName = `${node.name} & ${partnerNode.name}`;
      coupleIds = [personId, partnerId];
    }
    
    // Get all children of this person (and partner if exists)
    const myChildren = new Set(getChildren(personId, adjacencyList));
    if (partnerId) {
      getChildren(partnerId, adjacencyList).forEach(id => myChildren.add(id));
    }
    
    // Filter to only children in family set and not yet visited
    const childIds = Array.from(myChildren)
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
      name: displayName,
      attributes: {
        id: personId,
        coupleIds,
        gender: node.gender || '',
        photo: displayPhoto,
        partnerPhoto: partnerNode?.photo || null,
        age: node.age,
        birthDate: node.birthDate || null,
        isCouple: !!partnerNode,
      },
      children,
    };
  }
  
  // Build tree from roots
  let tree;
  
  if (roots.length === 0) {
    tree = buildNode(startPersonId);
  } else if (roots.length === 1) {
    tree = buildNode(roots[0]);
  } else {
    // Multiple roots - build each and combine
    // First, group roots that are couples
    const rootTrees = [];
    const processedRoots = new Set();
    
    for (const rootId of roots) {
      if (processedRoots.has(rootId)) continue;
      
      const tree = buildNode(rootId);
      if (tree) {
        rootTrees.push(tree);
        processedRoots.add(rootId);
        
        // If this root has a partner, they're already processed via buildNode
        const partner = getPartner(rootId, adjacencyList, familySet);
        if (partner) processedRoots.add(partner);
      }
    }
    
    if (rootTrees.length === 1) {
      tree = rootTrees[0];
    } else if (rootTrees.length > 1) {
      // Still have multiple separate lineages
      // Create a minimal connector - but we need to make it invisible
      tree = {
        name: '',
        attributes: {
          id: 'family-tree-root',
          isVirtualRoot: true,
        },
        children: rootTrees,
      };
    }
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
