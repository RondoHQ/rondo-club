/**
 * Family Tree Builder Utilities
 * 
 * Builds tree structures from relationship data for family tree visualization
 */

// Family relationship type slugs - simplified to only parent/child for tree
const FAMILY_RELATIONSHIP_TYPES = [
  'parent',
  'child',
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
  return typeSlug?.toLowerCase() === 'parent';
}

/**
 * Check if relationship type indicates child (should be below)
 */
function isChildType(typeSlug) {
  return typeSlug?.toLowerCase() === 'child';
}

/**
 * Find the ultimate ancestor (person with no parents) by traversing up
 * Traverses upward following parent relationships until finding someone with no parents
 * @param {number} startPersonId - Person to start from
 * @param {Map} adjacencyList - Adjacency list of relationships
 * @param {Array} nodes - Array of all nodes
 * @returns {number} ID of the ultimate ancestor (oldest person with no parents)
 */
/**
 * Find the eldest ancestor by traversing UP from current person
 * Collects ALL ancestors (not just those with no parents) and returns the eldest by birth date
 * @param {number} startPersonId - Person to start from (current person)
 * @param {Map} adjacencyList - Adjacency list of relationships
 * @param {Array} nodes - Array of all nodes
 * @returns {number} ID of the eldest ancestor (oldest by birth date among all ancestors)
 */
function findUltimateAncestor(startPersonId, adjacencyList, nodes) {
  const visited = new Set();
  const allAncestors = new Set();
  const queue = [startPersonId];
  
  // Traverse UP from current person to collect ALL ancestors
  // Use BFS to traverse all parent relationships
  while (queue.length > 0) {
    const currentId = queue.shift();
    
    if (visited.has(currentId)) {
      continue; // Skip if already visited (cycle prevention)
    }
    visited.add(currentId);
    
    const neighbors = adjacencyList.get(currentId) || [];
    // Find parents: if current person has "child" relationship to someone, that someone is current's parent
    // OR if someone has "parent" relationship to current person, that someone is current's parent
    const parents = neighbors.filter(neighbor => {
      const relType = neighbor.type?.toLowerCase();
      // If current has "child" relationship to neighbor, neighbor is parent
      // OR if neighbor has "parent" relationship to current (stored as edge from neighbor to current)
      // We need to check the edge direction - if edge is FROM neighbor TO current with type "parent", neighbor is parent
      return isChildType(relType);
    });
    
    // Add all parents to ancestors set and queue for further traversal
    for (const parent of parents) {
      allAncestors.add(parent.nodeId);
      if (!visited.has(parent.nodeId)) {
        queue.push(parent.nodeId);
      }
    }
  }
  
  // Also include the starting person in the set of ancestors to consider
  allAncestors.add(startPersonId);
  
  // Convert to array and find the eldest by birth date
  const ancestorsArray = Array.from(allAncestors);
  
  if (ancestorsArray.length === 0) {
    // No ancestors found, return starting person
    return startPersonId;
  }
  
  // Sort by birth date (oldest first)
  ancestorsArray.sort((a, b) => {
    const nodeA = nodes.find(n => n.id === a);
    const nodeB = nodes.find(n => n.id === b);
    const dateA = nodeA?.birthDate;
    const dateB = nodeB?.birthDate;
    if (!dateA && !dateB) return 0;
    if (!dateA) return 1; // No date goes to end
    if (!dateB) return -1; // No date goes to end
    return new Date(dateA) - new Date(dateB); // Oldest first (earliest date first)
  });
  
  // Return the eldest ancestor (first in sorted array)
  return ancestorsArray[0];
}

/**
 * Convert graph to hierarchical tree structure for react-d3-tree
 * Traverses up to find ultimate ancestor, then builds tree downward
 * @param {Object} graph - Graph with nodes and edges
 * @param {number} startPersonId - ID of the person to start from
 * @returns {Object} Tree structure compatible with react-d3-tree
 */
export function graphToTree(graph, startPersonId) {
  const { nodes, edges } = graph;
  
  // Find start node
  const startNode = nodes.find(n => n.id === startPersonId);
  if (!startNode) {
    return null;
  }
  
  // Build adjacency list with relationship info
  const adjacencyList = new Map();
  nodes.forEach(node => {
    adjacencyList.set(node.id, []);
  });
  
  // Build adjacency list with correct relationship types
  // IMPORTANT: The edge type represents the relationship FROM the "from" person TO the "to" person
  // So if edge is A -> B with type "parent", it means: A is parent of B
  // Therefore:
  // - A's neighbors include B with type "parent" (A is parent of B) ✓
  // - B's neighbors include A with type "child" (B is child of A) ✓
  
  // Debug: log edges to understand the structure
  if (process.env.NODE_ENV === 'development') {
    console.log('Edges in graph:', edges.map(e => ({
      from: e.from,
      to: e.to,
      type: e.type,
    })));
  }
  
  edges.forEach(edge => {
    const relType = edge.type?.toLowerCase();
    
    // Add edge from -> to with the original type
    // This represents: edge.from has relationship type "relType" to edge.to
    adjacencyList.get(edge.from)?.push({
      nodeId: edge.to,
      type: edge.type,
      label: edge.label,
    });
    
    // Add reverse edge with inverse type
    // If edge.from has "parent" relationship to edge.to, then edge.to has "child" relationship to edge.from
    let inverseType = edge.type;
    if (isParentType(relType)) {
      inverseType = 'child';
    } else if (isChildType(relType)) {
      inverseType = 'parent';
    }
    // For other relationship types, keep the same type (symmetric relationships)
    
    adjacencyList.get(edge.to)?.push({
      nodeId: edge.from,
      type: inverseType,
      label: edge.label,
    });
  });
  
  // Debug: log adjacency list for a few nodes
  if (process.env.NODE_ENV === 'development') {
    console.log('Sample adjacency list entries:', Array.from(adjacencyList.entries()).slice(0, 3).map(([id, neighbors]) => ({
      personId: id,
      neighbors: neighbors.map(n => ({ id: n.nodeId, type: n.type })),
    })));
  }
  
  // Find the eldest ancestor (oldest by birth date among all ancestors)
  const eldestAncestorId = findUltimateAncestor(startPersonId, adjacencyList, nodes);
  
  // Build tree recursively from eldest ancestor downward
  // This ensures the tree flows from oldest (top) to youngest (bottom)
  const visited = new Set();
  
  function buildNode(nodeId, parentId = null) {
    // Prevent cycles
    if (visited.has(nodeId)) {
      return null;
    }
    visited.add(nodeId);
    
    const node = nodes.find(n => n.id === nodeId);
    if (!node) return null;
    
    const children = [];
    const neighbors = adjacencyList.get(nodeId) || [];
    
    // First, find all children (people this node is a parent of)
    const childRelations = [];
    for (const neighbor of neighbors) {
      // Skip the parent to avoid going back up the tree
      if (neighbor.nodeId === parentId) continue;
      
      const relType = neighbor.type?.toLowerCase();
      if (isParentType(relType)) {
        // Current node has "parent" relationship to neighbor → neighbor is child of current
        childRelations.push(neighbor);
      }
    }
    
    // Sort children by birth date (oldest first)
    childRelations.sort((a, b) => {
      const nodeA = nodes.find(n => n.id === a.nodeId);
      const nodeB = nodes.find(n => n.id === b.nodeId);
      const dateA = nodeA?.birthDate;
      const dateB = nodeB?.birthDate;
      if (!dateA && !dateB) return 0;
      if (!dateA) return 1; // No date goes to end
      if (!dateB) return -1; // No date goes to end
      return new Date(dateA) - new Date(dateB); // Oldest first
    });
    
    // Build child nodes
    for (const neighbor of childRelations) {
      const childNode = buildNode(neighbor.nodeId, nodeId);
      if (childNode) {
        children.push({
          ...childNode,
          relationshipType: neighbor.type,
          relationshipLabel: neighbor.label,
        });
      }
    }
    
    // Now find siblings (people who share the same parents)
    // If we have a parent, find other children of that parent
    if (parentId) {
      const parentNeighbors = adjacencyList.get(parentId) || [];
      const siblingRelations = [];
      
      for (const neighbor of parentNeighbors) {
        // Skip self and parent
        if (neighbor.nodeId === nodeId || neighbor.nodeId === parentId) continue;
        
        const relType = neighbor.type?.toLowerCase();
        // If parent has "parent" relationship to neighbor, that neighbor is a sibling (child of same parent)
        if (isParentType(relType)) {
          siblingRelations.push({
            nodeId: neighbor.nodeId,
            type: 'sibling',
            label: 'Sibling',
          });
        }
      }
      
      // Sort siblings by birth date (oldest first)
      siblingRelations.sort((a, b) => {
        const nodeA = nodes.find(n => n.id === a.nodeId);
        const nodeB = nodes.find(n => n.id === b.nodeId);
        const dateA = nodeA?.birthDate;
        const dateB = nodeB?.birthDate;
        if (!dateA && !dateB) return 0;
        if (!dateA) return 1;
        if (!dateB) return -1;
        return new Date(dateA) - new Date(dateB);
      });
      
      // Add siblings as children (they'll be rendered at the same level)
      // Only add siblings that haven't been visited yet
      for (const sibling of siblingRelations) {
        if (!visited.has(sibling.nodeId)) {
          const siblingNode = buildNode(sibling.nodeId, parentId);
          if (siblingNode) {
            children.push({
              ...siblingNode,
              relationshipType: 'sibling',
              relationshipLabel: 'Sibling',
            });
          }
        }
      }
    }
    
    // Sort children by birth date (oldest first)
    // react-d3-tree renders from left to right, so oldest should be first in array
    const sortedChildren = children.slice().sort((a, b) => {
      const dateA = a.attributes?.birthDate || a.attributes?.birth_date;
      const dateB = b.attributes?.birthDate || b.attributes?.birth_date;
      if (!dateA && !dateB) return 0;
      if (!dateA) return 1; // No date goes to end
      if (!dateB) return -1; // No date goes to end
      return new Date(dateA) - new Date(dateB); // Oldest first (earliest date first)
    });
    
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
    
    // Always include children array, even if empty (react-family-tree expects it)
    // Use sorted children to ensure oldest appears first
    treeNode.children = sortedChildren.length > 0 ? sortedChildren : [];
    
    return treeNode;
  }
  
  // Build tree from eldest ancestor
  // This will include the current person and all siblings along the path
  visited.clear();
  const tree = buildNode(eldestAncestorId);
  
  // Verify that the current person is included in the tree
  // If not, it means there's a disconnect in the relationships
  // (shouldn't happen if relationships are properly defined)
  if (tree) {
    // Helper function to check if a person ID exists in the tree
    function personInTree(node, personId) {
      if (!node) return false;
      if (node.attributes?.id === personId) return true;
      if (node.children) {
        for (const child of node.children) {
          if (personInTree(child, personId)) return true;
        }
      }
      return false;
    }
    
    // If current person is not in tree, log a warning
    // This shouldn't happen if relationships are correct
    if (!personInTree(tree, startPersonId)) {
      console.warn(`Current person ${startPersonId} not found in tree built from eldest ancestor ${eldestAncestorId}. This may indicate missing parent-child relationships.`);
    }
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

