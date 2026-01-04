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
    nodes.set(startPersonId, {
      id: startPersonId,
      name: startPerson.name || startPerson.title?.rendered || startPerson.title || `Person ${startPersonId}`,
      gender: startPerson.acf?.gender || '',
      photo: startPerson.thumbnail || null,
      age: startPerson.age || null,
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
          nodes.set(relatedPersonId, {
            id: relatedPersonId,
            name: relatedPerson.name || relatedPerson.title?.rendered || relatedPerson.title || `Person ${relatedPersonId}`,
            gender: relatedPerson.acf?.gender || '',
            photo: relatedPerson.thumbnail || null,
            age: relatedPerson.age || null,
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
 * Convert graph to hierarchical tree structure for react-d3-tree
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
  
  // Build adjacency list
  const adjacencyList = new Map();
  nodes.forEach(node => {
    adjacencyList.set(node.id, []);
  });
  
  edges.forEach(edge => {
    adjacencyList.get(edge.from)?.push({
      nodeId: edge.to,
      type: edge.type,
      label: edge.label,
    });
    // Add reverse edge for bidirectional relationships
    adjacencyList.get(edge.to)?.push({
      nodeId: edge.from,
      type: edge.type,
      label: edge.label,
    });
  });
  
  // Build tree recursively
  const visited = new Set();
  
  function buildNode(nodeId, parentId = null) {
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
      
      const childNode = buildNode(neighbor.nodeId, nodeId);
      if (childNode) {
        children.push({
          ...childNode,
          relationshipType: neighbor.type,
          relationshipLabel: neighbor.label,
        });
      }
    }
    
    return {
      name: node.name,
      attributes: {
        id: node.id,
        gender: node.gender,
        photo: node.photo,
        age: node.age,
      },
      children: children.length > 0 ? children : undefined,
    };
  }
  
  return buildNode(rootNodeId);
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
      let typeSlug = '';
      if (typeof rel.relationship_type === 'object' && rel.relationship_type?.slug) {
        typeSlug = rel.relationship_type.slug;
      } else if (typeof rel.relationship_type === 'string') {
        typeSlug = rel.relationship_type;
      } else if (typeof rel.relationship_type === 'number') {
        // Need to look up the type - this will be handled by the caller
        typeSlug = '';
      }
      
      return {
        related_person: typeof rel.related_person === 'object' 
          ? rel.related_person?.ID || rel.related_person?.id 
          : rel.related_person,
        relationship_type: rel.relationship_type,
        relationship_type_slug: typeSlug,
        relationship_name: rel.relationship_name || '',
        relationship_label: rel.relationship_label || '',
      };
    });
    
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

