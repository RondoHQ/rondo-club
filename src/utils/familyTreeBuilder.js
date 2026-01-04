/**
 * Family Tree Builder Utilities
 * 
 * Builds tree structures from relationship data for family tree visualization.
 * 
 * Note: react-d3-tree only supports single-parent hierarchies. In family trees,
 * children have two parents. This implementation shows one lineage path; the
 * other parent appears in the tree but children only connect to one parent.
 */

const FAMILY_RELATIONSHIP_TYPES = ['parent', 'child'];

export function isFamilyRelationshipType(typeSlug) {
  return FAMILY_RELATIONSHIP_TYPES.includes(typeSlug?.toLowerCase());
}

function isParentType(typeSlug) {
  return typeSlug?.toLowerCase() === 'parent';
}

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
  
  const startPerson = peopleMap.get(startPersonId);
  if (startPerson) {
    nodes.set(startPersonId, extractPersonData(startPerson, startPersonId));
  }
  
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
// HELPER FUNCTIONS
// ============================================================================

function getParents(personId, adjacencyList) {
  const neighbors = adjacencyList.get(personId) || [];
  return neighbors.filter(n => isParentType(n.type)).map(n => n.nodeId);
}

function getChildren(personId, adjacencyList) {
  const neighbors = adjacencyList.get(personId) || [];
  return neighbors.filter(n => isChildType(n.type)).map(n => n.nodeId);
}

function getSiblings(personId, adjacencyList) {
  const parents = getParents(personId, adjacencyList);
  const siblings = new Set();
  
  for (const parentId of parents) {
    const children = getChildren(parentId, adjacencyList);
    children.forEach(childId => {
      if (childId !== personId) siblings.add(childId);
    });
  }
  
  return Array.from(siblings);
}

// ============================================================================
// COLLECT FAMILY MEMBERS
// ============================================================================

function collectFamilyMembers(startPersonId, adjacencyList) {
  const familySet = new Set();
  const visitedUp = new Set();
  
  familySet.add(startPersonId);
  getSiblings(startPersonId, adjacencyList).forEach(id => familySet.add(id));
  
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
// BUILD TREE - Direct lineage from start person upward, then downward
// ============================================================================

/**
 * Build tree showing ancestors above and descendants below the start person.
 * Due to tree structure limitations, each child connects to only one parent.
 */
export function graphToTree(graph, startPersonId) {
  const { nodes, edges } = graph;
  
  if (!nodes.find(n => n.id === startPersonId)) {
    return null;
  }
  
  // Build adjacency list
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
    if (isParentType(relType)) inverseType = 'child';
    else if (isChildType(relType)) inverseType = 'parent';
    
    adjacencyList.get(edge.to)?.push({
      nodeId: edge.from,
      type: inverseType,
      label: edge.label,
    });
  });
  
  // Collect relevant family members
  const familySet = collectFamilyMembers(startPersonId, adjacencyList);
  
  // Find the eldest ancestor by traversing up from start person
  function findEldestAncestor(personId, visited = new Set()) {
    if (visited.has(personId)) return null;
    visited.add(personId);
    
    const parents = getParents(personId, adjacencyList).filter(id => familySet.has(id));
    
    if (parents.length === 0) {
      // This person has no parents - they're a root
      return personId;
    }
    
    // Find eldest among all ancestor paths
    let eldest = null;
    let eldestDate = null;
    
    for (const parentId of parents) {
      const ancestor = findEldestAncestor(parentId, visited);
      if (ancestor) {
        const ancestorNode = nodes.find(n => n.id === ancestor);
        const birthDate = ancestorNode?.birthDate ? new Date(ancestorNode.birthDate) : null;
        
        if (!eldest || (birthDate && (!eldestDate || birthDate < eldestDate))) {
          eldest = ancestor;
          eldestDate = birthDate;
        }
      }
    }
    
    return eldest || personId;
  }
  
  const rootId = findEldestAncestor(startPersonId);
  
  // Build tree from root downward
  const visited = new Set();
  
  function buildNode(personId) {
    if (visited.has(personId) || !familySet.has(personId)) {
      return null;
    }
    visited.add(personId);
    
    const node = nodes.find(n => n.id === personId);
    if (!node) return null;
    
    // Get children in family set
    const childIds = getChildren(personId, adjacencyList)
      .filter(id => familySet.has(id) && !visited.has(id));
    
    // Sort by birth date
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
    
    const children = childIds.map(id => buildNode(id)).filter(Boolean);
    
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
  
  // Build from the eldest ancestor
  const tree = buildNode(rootId);
  
  // If there are unvisited family members (from other lineages), we need to include them
  // Build additional trees for unvisited roots
  const unvisitedRoots = [];
  for (const personId of familySet) {
    if (!visited.has(personId)) {
      const parents = getParents(personId, adjacencyList).filter(id => familySet.has(id));
      const hasUnvisitedParent = parents.some(p => !visited.has(p));
      if (!hasUnvisitedParent) {
        // This is a root of an unvisited lineage
        unvisitedRoots.push(personId);
      }
    }
  }
  
  // Sort unvisited roots by birth date
  unvisitedRoots.sort((a, b) => {
    const nodeA = nodes.find(n => n.id === a);
    const nodeB = nodes.find(n => n.id === b);
    const dateA = nodeA?.birthDate;
    const dateB = nodeB?.birthDate;
    if (!dateA && !dateB) return 0;
    if (!dateA) return 1;
    if (!dateB) return -1;
    return new Date(dateA) - new Date(dateB);
  });
  
  // Build trees for other lineages
  const additionalTrees = unvisitedRoots.map(id => buildNode(id)).filter(Boolean);
  
  if (additionalTrees.length === 0) {
    return tree;
  }
  
  // Combine all trees - the main tree plus additional lineages
  // Since we can't have a true multi-root tree, we need to structure this carefully
  // Return just the main tree for now - the other lineages will be separate
  // TODO: Consider alternative visualization for multiple lineages
  
  return tree;
}

// ============================================================================
// RELATIONSHIP MAP UTILITIES
// ============================================================================

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
