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
 * @param {number} startPersonId - Person to start from
 * @param {Map} adjacencyList - Adjacency list of relationships
 * @param {Array} nodes - Array of all nodes
 * @returns {number} ID of the ultimate ancestor, or startPersonId if no parents found
 */
function findUltimateAncestor(startPersonId, adjacencyList, nodes) {
  const visited = new Set();
  let currentId = startPersonId;
  
  // Traverse up until we find someone with no parents
  while (true) {
    if (visited.has(currentId)) {
      // Cycle detected, return current
      return currentId;
    }
    visited.add(currentId);
    
    const neighbors = adjacencyList.get(currentId) || [];
    const parents = neighbors.filter(neighbor => {
      const relType = neighbor.type?.toLowerCase();
      // If current person has "child" relationship to someone, that person is current's parent
      return isChildType(relType);
    });
    
    if (parents.length === 0) {
      // No parents found, this is the ultimate ancestor
      return currentId;
    }
    
    // Move up to first parent (we'll handle multiple parents in tree building)
    currentId = parents[0].nodeId;
  }
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
  
  edges.forEach(edge => {
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
  
  // Find the ultimate ancestor (person with no parents)
  const ultimateAncestorId = findUltimateAncestor(startPersonId, adjacencyList, nodes);
  
  // Build tree recursively from ultimate ancestor downward
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
    
    for (const neighbor of neighbors) {
      // Skip the parent to avoid going back up the tree
      if (neighbor.nodeId === parentId) continue;
      
      // Only process parent/child relationships
      const relType = neighbor.type?.toLowerCase();
      if (!isParentType(relType) && !isChildType(relType)) {
        continue; // Skip non-parent/child relationships
      }
      
      // Determine if this neighbor should be a child in the tree
      // If current node has "parent" relationship to neighbor → neighbor is child
      // If current node has "child" relationship to neighbor → neighbor is parent (skip, already traversed up)
      let shouldAddAsChild = false;
      
      if (isParentType(relType)) {
        // Current node has "parent" relationship to neighbor → neighbor is child of current
        shouldAddAsChild = true;
      } else if (isChildType(relType)) {
        // Current node has "child" relationship to neighbor → neighbor is parent of current
        // We're building downward, so we skip parents (they're already above)
        shouldAddAsChild = false;
      }
      
      if (shouldAddAsChild) {
        const childNode = buildNode(neighbor.nodeId, nodeId);
        if (childNode) {
          children.push({
            ...childNode,
            relationshipType: neighbor.type,
            relationshipLabel: neighbor.label,
          });
        }
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
  
  // Build tree from ultimate ancestor
  visited.clear();
  const tree = buildNode(ultimateAncestorId);
  
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

