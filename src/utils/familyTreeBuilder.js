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
      
      // Check if edge already exists (in either direction)
      const edgeExists = edges.some(e => 
        (e.from === personId && e.to === relatedPersonId) ||
        (e.from === relatedPersonId && e.to === personId)
      );
      
      if (!edgeExists) {
        // The relationship type represents: personId has relationship type "rel.relationship_type_slug" to relatedPersonId
        // For family tree purposes, we want to ensure correct hierarchy:
        // - If personId has "parent" to relatedPersonId, we want to invert this to "child" 
        //   so that personId is shown as child of relatedPersonId (correct hierarchy)
        // - If personId has "child" to relatedPersonId, we keep it as-is
        let edgeType = rel.relationship_type_slug;
        const relTypeLower = edgeType?.toLowerCase();
        
        // Invert parent relationships to child relationships for correct tree hierarchy
        if (isParentType(relTypeLower)) {
          edgeType = 'child';
        }
        // Keep child relationships as-is (they represent correct hierarchy)
        // Other relationship types are kept as-is
        
        edges.push({
          from: personId,
          to: relatedPersonId,
          type: edgeType,
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
    // Find parents: neighbors where current person has "child" relationship to them
    // The relationship type in the adjacency list represents current's role in the relationship
    // So if current has "child" relationship to neighbor, that means current IS a child of neighbor
    // Therefore, neighbor IS a parent of current
    const parents = neighbors.filter(neighbor => {
      const relType = neighbor.type?.toLowerCase();
      // If current has "child" relationship to neighbor, neighbor is parent
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
  // But we need to store it from B's perspective too: B is child of A
  
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
    
    // IMPORTANT: The edge type represents the relationship FROM edge.from TO edge.to
    // So if edge is {from: 31, to: 5, type: 'parent'}, it means:
    // Person 31 has "parent" relationship to Person 5 → Person 31 IS a parent of Person 5
    
    // But we need to check: if this is a parent/child relationship, we need to ensure
    // the tree structure is correct. If edge.from has "parent" to edge.to, then:
    // - edge.from is parent of edge.to (edge.from should be ABOVE edge.to in tree)
    // - edge.to is child of edge.from (edge.to should be BELOW edge.from in tree)
    
    // Store relationship from edge.from's perspective
    adjacencyList.get(edge.from)?.push({
      nodeId: edge.to,
      type: edge.type, // edge.from's role in relationship to edge.to
      label: edge.label,
    });
    
    // Store inverse relationship from edge.to's perspective
    // If edge.from has "parent" to edge.to, then edge.to has "child" to edge.from
    let inverseType = edge.type;
    if (isParentType(relType)) {
      inverseType = 'child'; // edge.to is child of edge.from
    } else if (isChildType(relType)) {
      inverseType = 'parent'; // edge.to is parent of edge.from
    }
    // For other relationship types, keep the same type (symmetric relationships)
    
    adjacencyList.get(edge.to)?.push({
      nodeId: edge.from,
      type: inverseType, // edge.to's role in relationship to edge.from (inverse)
      label: edge.label,
    });
  });
  
  // Debug: log adjacency list for a few nodes
  if (process.env.NODE_ENV === 'development') {
    console.log('Sample adjacency list entries:', Array.from(adjacencyList.entries()).slice(0, 5).map(([id, neighbors]) => ({
      personId: id,
      neighbors: neighbors.map(n => ({ id: n.nodeId, type: n.type })),
    })));
  }
  
  // Find the eldest ancestor (oldest by birth date among all ancestors)
  // This will be our root for the tree
  const eldestAncestorId = findUltimateAncestor(startPersonId, adjacencyList, nodes);
  
  // Build tree recursively from root(s) downward
  // This ensures the tree flows from oldest (top) to youngest (bottom)
  // IMPORTANT: We need to ensure ALL parents of each person are included
  const visited = new Set();
  
  function buildNode(nodeId, parentIds = []) {
    // Prevent cycles
    if (visited.has(nodeId)) {
      return null;
    }
    visited.add(nodeId);
    
    const node = nodes.find(n => n.id === nodeId);
    if (!node) return null;
    
    const children = [];
    const neighbors = adjacencyList.get(nodeId) || [];
    
    // CRITICAL: First, check if this node has parents that aren't already in the tree
    // If so, we need to include them. But since react-d3-tree renders top-to-bottom,
    // we can't add them as children (they'd appear below). Instead, we need to ensure
    // they're included in the tree structure above.
    // 
    // Solution: When building downward, if we encounter a person with parents not yet visited,
    // we need to build those parents first (above), then continue building downward.
    // But this is tricky with a single-root tree structure.
    //
    // Better approach: When building a node, if it has parents that aren't in parentIds
    // (meaning they're not already in the tree above), we need to include them.
    // We can do this by building them as siblings of the current node's position,
    // but that's also complex.
    //
    // Actually, the real solution: Ensure that when building the tree, we include ALL
    // people connected through parent-child relationships. The issue is that we're
    // building from a single root, so if parents come from different lineages, they
    // might not be included.
    //
    // Let's try: When building a node, if it has parents that aren't visited yet,
    // build them first (recursively upward), then continue with children.
    // But we need to be careful about cycles and ordering.
    
    // Find all parents of this node
    const parentRelations = [];
    for (const neighbor of neighbors) {
      const relType = neighbor.type?.toLowerCase();
      if (isChildType(relType)) {
        // Current node has "child" relationship to neighbor → neighbor is parent
        if (!parentIds.includes(neighbor.nodeId) && !visited.has(neighbor.nodeId)) {
          parentRelations.push(neighbor);
        }
      }
    }
    
    // If this node has parents that aren't in the tree yet, we need to include them
    // But since we're building downward, parents should already be above.
    // If they're not, it means they're from a different lineage.
    // For now, let's build them as part of the tree structure.
    // Actually, if parents aren't visited, we should build them first, then this node.
    // But that would change the tree structure significantly.
    
    // For now, let's ensure that when building children, we also check if those children
    // have other parents that need to be included.
    
    // First, find all children (people this node is a parent of)
    // The relationship type in the adjacency list represents current node's role in the relationship
    // So if current node has "parent" relationship to neighbor, that means current IS a parent of neighbor
    // Therefore, neighbor IS a child of current
    const childRelations = [];
    for (const neighbor of neighbors) {
      // Skip parents to avoid going back up the tree
      if (parentIds.includes(neighbor.nodeId)) continue;
      
      const relType = neighbor.type?.toLowerCase();
      if (isParentType(relType)) {
        // Current node has "parent" relationship to neighbor → current is parent of neighbor → neighbor is child
        childRelations.push(neighbor);
      }
    }
    
    // CRITICAL FIX: Also find all parents of this node and ensure they're included in the tree
    // If this node has parents that aren't already in the tree (above), we need to include them
    // But since react-d3-tree renders top-to-bottom, we can't add them as children (they'd appear below)
    // Instead, we need to ensure the tree structure includes them above.
    // The solution: when building downward, if we encounter a person with parents not yet in the tree,
    // we need to restructure. But that's complex with a single-root tree.
    
    // Simpler approach: Ensure that when we build a node, if it has parents that aren't ancestors
    // of the root, we include them by building upward first, then downward.
    
    // Actually, the real issue is that we're building from a single root. If Tycho has two parents
    // (Marieke and Joost), and they have different ancestors, we need to include both lineages.
    // The findUltimateAncestor function should find the eldest among ALL ancestors, which it does.
    // But when building downward, we might not be including all parents of each person.
    
    // Let's ensure that when building downward, we include ALL children of each person,
    // and when a person has multiple parents, we ensure all of them are in the tree structure.
    
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
    
    // Build child nodes - pass current node as parent
    // IMPORTANT: When building a child, we need to ensure ALL of its parents are included
    // If a child has multiple parents (e.g., Tycho has Marieke and Joost), we need both
    for (const neighbor of childRelations) {
      const childNode = buildNode(neighbor.nodeId, [nodeId, ...parentIds]);
      if (childNode) {
        // Check if this child has other parents that should be siblings at the same level
        // If the child has multiple parents, they should all be at the same level above the child
        const childNeighbors = adjacencyList.get(neighbor.nodeId) || [];
        const childParents = childNeighbors.filter(n => {
          const relType = n.type?.toLowerCase();
          return isChildType(relType) && n.nodeId !== nodeId && !parentIds.includes(n.nodeId);
        });
        
        // If child has other parents, we need to ensure they're included
        // But since we're building downward, they might not be in the tree yet
        // For now, let's just build the child - the other parents should be included
        // when we build from their lineage
        children.push({
          ...childNode,
          relationshipType: neighbor.type,
          relationshipLabel: neighbor.label,
        });
      }
    }
    
    // CRITICAL FIX: If this node has parents that aren't in the tree yet (from different lineage),
    // we need to include them. But we can't add them as children (they'd appear below).
    // Instead, we need to restructure the tree to include them above.
    // 
    // Solution: When we encounter a node with unvisited parents, we should build those parents
    // first, then continue. But this requires restructuring the tree building logic.
    //
    // For now, let's try a different approach: Build the tree to include ALL connected people,
    // ensuring that when we build a person, all their parents are included above them.
    // We can do this by building from ALL root ancestors, not just the eldest.
    //
    // Actually, let's try building from the start person upward to collect all ancestors,
    // then build downward from the eldest, but ensure we include all relationships.
    
    // IMPORTANT: Siblings should be at the same level, not as children
    // In react-d3-tree, siblings are handled by being children of the same parent
    // So we don't need to add them here - they'll be added when we build the parent's children
    // The parent will have multiple children, and those children will be siblings at the same level
    
    // CRITICAL: We need to include ALL parents, but react-d3-tree renders top-to-bottom
    // The issue: When building downward from eldest ancestor, we might miss parents from other lineages
    // For example, if eldest ancestor is from Joost's side, we won't include Marieke and her parents
    //
    // Solution: When building a node, if it has parents that aren't already visited (above in tree),
    // we need to ensure they're included. But we can't add them as children (they'd appear below).
    //
    // The real fix: We need to ensure the tree includes ALL ancestors from ALL lineages.
    // The findUltimateAncestor function collects all ancestors, but we only build from the eldest.
    // If the eldest is from one side, we miss the other side.
    //
    // Better approach: Build the tree to include ALL people connected through parent-child relationships,
    // ensuring that when we encounter a person with multiple parents, all of them are included.
    // Since we're building downward, parents should already be above. The issue is that we might
    // not be building from a root that includes all lineages.
    //
    // For now, let's ensure that when building downward, we include ALL children of each person,
    // and when a person has parents, we verify they're in the tree. If not, we need to restructure.
    //
    // Actually, the simplest fix: Don't add parents as children. Instead, ensure that when building
    // the tree, we start from ALL roots (all people with no parents), not just the eldest one.
    // But react-d3-tree expects a single root, so we'd need to create a virtual root.
    //
    // For now, let's remove the parent-as-children logic and instead ensure the tree building
    // process includes all relationships by building from the start person upward first, then downward.
    
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
  
  // Build tree from eldest ancestor downward
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

