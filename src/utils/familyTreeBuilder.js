/**
 * Family Tree Builder Utilities
 * 
 * Builds tree structures from relationship data for family tree visualization
 */

// Family relationship type slugs
const FAMILY_RELATIONSHIP_TYPES = [
  // Immediate family
  'parent',
  'child',
  'sibling',
  'spouse',
  'partner',
  
  // Extended family
  'grandparent',
  'grandchild',
  'uncle',
  'aunt',
  'nephew',
  'niece',
  'cousin',
  
  // Step/in-law
  'stepparent',
  'stepchild',
  'stepsibling',
  'inlaw',
  
  // Other
  'godparent',
  'godchild',
];

/**
 * Check if a relationship type is a family relationship
 */
export function isFamilyRelationshipType(typeSlug) {
  return FAMILY_RELATIONSHIP_TYPES.includes(typeSlug?.toLowerCase());
}

/**
 * Build a graph structure from relationships
 * @param {number} startPersonId - The person to start from
 * @param {Array} allPeople - Array of all people objects
 * @param {Object} relationshipMap - Map of personId -> array of relationships
 * @returns {Object} Graph with nodes and edges
 */
export function buildFamilyGraph(startPersonId, allPeople, relationshipMap) {
  const nodes = new Map();
  const edges = [];
  const visited = new Set();
  
  // Create a map of person ID to person data
  const peopleMap = new Map();
  allPeople.forEach(person => {
    peopleMap.set(person.id, person);
  });
  
  // Queue for BFS traversal
  const queue = [{ personId: startPersonId, depth: 0 }];
  visited.add(startPersonId);
  
  // Add start person to nodes
  const startPerson = peopleMap.get(startPersonId);
  if (startPerson) {
    // Get person name - handle various formats
    let personName = startPerson.name;
    if (!personName) {
      personName = startPerson.title?.rendered || startPerson.title;
    }
    // Decode HTML entities if needed (only in browser environment)
    if (personName && typeof personName === 'string' && typeof document !== 'undefined') {
      const txt = document.createElement('textarea');
      txt.innerHTML = personName;
      personName = txt.value;
    }
    if (!personName) {
      personName = `Person ${startPersonId}`;
    }
    
    // Calculate age if we have birth date
    let age = null;
    if (startPerson.acf?.birth_date) {
      const birthDate = new Date(startPerson.acf.birth_date);
      if (!isNaN(birthDate.getTime())) {
        age = Math.floor((new Date() - birthDate) / (365.25 * 24 * 60 * 60 * 1000));
      }
    }
    
    nodes.set(startPersonId, {
      id: startPersonId,
      name: personName,
      gender: startPerson.acf?.gender || '',
      photo: startPerson.thumbnail || null,
      age: age,
      birthDate: startPerson.acf?.birth_date || null,
      person: startPerson,
    });
  }
  
  // Traverse relationships
  while (queue.length > 0) {
    const { personId, depth } = queue.shift();
    const relationships = relationshipMap.get(personId) || [];
    
    for (const rel of relationships) {
      // Only process family relationships
      // If slug is not set, we'll include it anyway (will be filtered later if needed)
      if (rel.relationship_type_slug && !isFamilyRelationshipType(rel.relationship_type_slug)) {
        continue;
      }
      
      const relatedPersonId = rel.related_person;
      if (!relatedPersonId) continue;
      
      // Skip if related person doesn't exist
      if (!peopleMap.has(relatedPersonId)) {
        continue;
      }
      
      // Add related person to nodes if not already added
      if (!nodes.has(relatedPersonId)) {
        const relatedPerson = peopleMap.get(relatedPersonId);
        if (relatedPerson) {
          // Get person name - handle various formats
          let personName = relatedPerson.name;
          if (!personName) {
            personName = relatedPerson.title?.rendered || relatedPerson.title;
          }
    // Decode HTML entities if needed (only in browser environment)
    if (personName && typeof personName === 'string' && typeof document !== 'undefined') {
      const txt = document.createElement('textarea');
      txt.innerHTML = personName;
      personName = txt.value;
    }
          if (!personName) {
            personName = `Person ${relatedPersonId}`;
          }
          
          // Calculate age if we have birth date
          let age = null;
          if (relatedPerson.acf?.birth_date) {
            const birthDate = new Date(relatedPerson.acf.birth_date);
            if (!isNaN(birthDate.getTime())) {
              age = Math.floor((new Date() - birthDate) / (365.25 * 24 * 60 * 60 * 1000));
            }
          }
          
          nodes.set(relatedPersonId, {
            id: relatedPersonId,
            name: personName,
            gender: relatedPerson.acf?.gender || '',
            photo: relatedPerson.thumbnail || null,
            age: age,
            birthDate: relatedPerson.acf?.birth_date || null,
            person: relatedPerson,
          });
        }
      }
      
      // Add edge (only if not already added)
      const edgeKey = `${personId}-${relatedPersonId}`;
      const reverseEdgeKey = `${relatedPersonId}-${personId}`;
      
      if (!edges.some(e => 
        (e.from === personId && e.to === relatedPersonId) ||
        (e.from === relatedPersonId && e.to === personId)
      )) {
        edges.push({
          from: personId,
          to: relatedPersonId,
          type: rel.relationship_type_slug,
          label: rel.relationship_name || rel.relationship_label || '',
        });
      }
      
      // Add to queue if not visited (prevent infinite loops)
      if (!visited.has(relatedPersonId)) {
        visited.add(relatedPersonId);
        queue.push({ personId: relatedPersonId, depth: depth + 1 });
      }
    }
  }
  
  return {
    nodes: Array.from(nodes.values()),
    edges: edges,
  };
}

/**
 * Check if relationship type indicates parent (should be above)
 */
function isParentType(typeSlug) {
  const parentTypes = ['parent', 'grandparent', 'stepparent', 'godparent'];
  return parentTypes.includes(typeSlug?.toLowerCase());
}

/**
 * Check if relationship type indicates child (should be below)
 */
function isChildType(typeSlug) {
  const childTypes = ['child', 'grandchild', 'stepchild', 'godchild'];
  return childTypes.includes(typeSlug?.toLowerCase());
}

/**
 * Convert graph to hierarchical tree structure for react-d3-tree
 * Parents go above, children go below, siblings on same level
 * @param {Object} graph - Graph with nodes and edges
 * @param {number} rootNodeId - ID of the root node
 * @returns {Object} Tree structure compatible with react-d3-tree
 */
export function graphToTree(graph, rootNodeId) {
  const { nodes, edges } = graph;
  
  // Find root node
  const rootNode = nodes.find(n => n.id === rootNodeId);
  if (!rootNode) {
    return null;
  }
  
  // Build adjacency list with relationship info
  const adjacencyList = new Map();
  nodes.forEach(node => {
    adjacencyList.set(node.id, []);
  });
  
  // Map to track which edge connects which nodes with what relationship
  const edgeMap = new Map();
  edges.forEach(edge => {
    const key1 = `${edge.from}-${edge.to}`;
    const key2 = `${edge.to}-${edge.from}`;
    edgeMap.set(key1, edge);
    edgeMap.set(key2, edge);
    
    adjacencyList.get(edge.from)?.push({
      nodeId: edge.to,
      type: edge.type,
      label: edge.label,
    });
    adjacencyList.get(edge.to)?.push({
      nodeId: edge.from,
      type: edge.type,
      label: edge.label,
    });
  });
  
  // Find parents of root and organize relationships
  // From root's perspective:
  // - If root has "child" relationship to X → root is child of X → X is parent of root → should be ABOVE
  // - If root has "parent" relationship to X → root is parent of X → X is child of root → should be BELOW
  const rootParents = [];  // People who are parents of root (should be above)
  const rootChildren = []; // People who are children of root (should be below)
  const rootSiblings = []; // Siblings, spouses, etc. (same level)
  const rootOthers = [];
  
  const rootNeighbors = adjacencyList.get(rootNodeId) || [];
  rootNeighbors.forEach(neighbor => {
    const relType = neighbor.type?.toLowerCase();
    
    // Check the relationship from root's perspective
    // If root has "child" relationship to someone, that person IS root's parent (should be above)
    // If root has "parent" relationship to someone, that person IS root's child (should be below)
    if (isChildType(relType)) {
      // Root is child of this person → this person is root's parent
      rootParents.push(neighbor);
    } else if (isParentType(relType)) {
      // Root is parent of this person → this person is root's child
      rootChildren.push(neighbor);
    } else if (['sibling', 'spouse', 'partner', 'cousin', 'stepsibling'].includes(relType)) {
      rootSiblings.push(neighbor);
    } else {
      rootOthers.push(neighbor);
    }
  });
  
  // Build tree recursively
  const visited = new Set();
  
  function buildNode(nodeId, parentId = null, relationshipType = null) {
    if (visited.has(nodeId)) {
      return null; // Prevent cycles
    }
    visited.add(nodeId);
    
    const node = nodes.find(n => n.id === nodeId);
    if (!node) return null;
    
    const children = [];
    const neighbors = adjacencyList.get(nodeId) || [];
    
    for (const neighbor of neighbors) {
      // Skip the parent to avoid going back up the tree
      if (neighbor.nodeId === parentId) continue;
      
      const childNode = buildNode(neighbor.nodeId, nodeId, neighbor.type);
      if (childNode) {
        children.push({
          ...childNode,
          relationshipType: neighbor.type,
          relationshipLabel: neighbor.label,
        });
      }
    }
    
    const treeNode = {
      name: node.name || `Person ${node.id}`,
      attributes: {
        id: node.id,
        gender: node.gender || '',
        photo: node.photo || null,
        age: node.age !== null && node.age !== undefined ? node.age : null,
        birthDate: node.birthDate || null,
      },
    };
    
    if (children.length > 0) {
      treeNode.children = children;
    }
    
    return treeNode;
  }
  
  // Build the tree structure
  // If root has parents, we need to restructure so parents appear above
  // react-d3-tree renders top-down, so parents need to be ancestors in the tree
  
  if (rootParents.length > 0) {
    // Restructure: make first parent the root, with current root as child
    const firstParent = rootParents[0];
    const parentNode = nodes.find(n => n.id === firstParent.nodeId);
    
    if (parentNode) {
      // Build tree starting from parent
      visited.clear();
      const parentTree = buildNode(firstParent.nodeId);
      
      // Add current root as a child of parent
      visited.clear();
      visited.add(firstParent.nodeId);
      const rootAsChild = buildNode(rootNodeId, firstParent.nodeId);
      
      if (rootAsChild) {
        // Add root's children, siblings, and others to rootAsChild
        const allRootChildren = [];
        
        // Add siblings and others as children of root
        [...rootSiblings, ...rootOthers, ...rootChildren].forEach(rel => {
          visited.clear();
          visited.add(rootNodeId);
          visited.add(firstParent.nodeId);
          const childNode = buildNode(rel.nodeId, rootNodeId, rel.type);
          if (childNode) {
            allRootChildren.push({
              ...childNode,
              relationshipType: rel.type,
              relationshipLabel: rel.label,
            });
          }
        });
        
        // Add other parents as siblings of first parent
        const otherParents = rootParents.slice(1).map(rel => {
          visited.clear();
          visited.add(firstParent.nodeId);
          visited.add(rootNodeId);
          const parentNode = buildNode(rel.nodeId, firstParent.nodeId, rel.type);
          return parentNode ? {
            ...parentNode,
            relationshipType: rel.type,
            relationshipLabel: rel.label,
          } : null;
        }).filter(Boolean);
        
        if (allRootChildren.length > 0) {
          rootAsChild.children = [...(rootAsChild.children || []), ...allRootChildren];
        }
        
        // Add root as child of parent, and other parents as siblings
        parentTree.children = [
          ...(parentTree.children || []),
          rootAsChild,
          ...otherParents,
        ];
        
        return parentTree;
      }
    }
  }
  
  // No parents, build normally from root
  visited.clear();
  const tree = buildNode(rootNodeId);
  
  // Organize children: parents first (if any), then siblings, then children
  if (tree && tree.children) {
    const organizedChildren = [];
    const parentChildren = [];
    const siblingChildren = [];
    const childChildren = [];
    const otherChildren = [];
    
    tree.children.forEach(child => {
      const relType = child.relationshipType?.toLowerCase();
      if (isParentType(relType)) {
        parentChildren.push(child);
      } else if (isChildType(relType)) {
        childChildren.push(child);
      } else if (['sibling', 'spouse', 'partner'].includes(relType)) {
        siblingChildren.push(child);
      } else {
        otherChildren.push(child);
      }
    });
    
    tree.children = [...parentChildren, ...siblingChildren, ...childChildren, ...otherChildren];
  }
  
  return tree;
}

/**
 * Build relationship map from people data
 * @param {Array} people - Array of people with relationships
 * @returns {Map} Map of personId -> array of relationships
 */
export function buildRelationshipMap(people) {
  const relationshipMap = new Map();
  
  people.forEach(person => {
    const relationships = person.acf?.relationships || [];
    const personRelationships = relationships.map(rel => {
      // Get relationship type slug
      // The REST API expands relationships and may include relationship_slug directly
      let typeSlug = '';
      
      // Check for expanded relationship_slug field (from REST API expansion)
      // This is set by expand_person_relationships in class-rest-api.php
      if (rel.relationship_slug) {
        typeSlug = rel.relationship_slug.toLowerCase();
      } else if (typeof rel.relationship_type === 'object') {
        // Could be term object with slug property
        typeSlug = rel.relationship_type?.slug || rel.relationship_type?.name?.toLowerCase() || '';
      } else if (typeof rel.relationship_type === 'string') {
        typeSlug = rel.relationship_type.toLowerCase();
      } else if (typeof rel.relationship_type === 'number') {
        // Need to look up the type - this will be handled by enrichRelationshipsWithTypes
        typeSlug = '';
      }
      
      // Get related person ID - handle various formats
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
    }).filter(rel => rel.related_person); // Filter out invalid relationships
    
    relationshipMap.set(person.id, personRelationships);
  });
  
  return relationshipMap;
}

/**
 * Enrich relationships with type slugs from relationship types data
 * @param {Map} relationshipMap - Relationship map
 * @param {Array} relationshipTypes - Array of relationship type objects
 */
export function enrichRelationshipsWithTypes(relationshipMap, relationshipTypes) {
  const typeMap = new Map();
  relationshipTypes.forEach(type => {
    typeMap.set(type.id, type.slug || type.name?.toLowerCase());
  });
  
  relationshipMap.forEach((relationships, personId) => {
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

