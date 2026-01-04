/**
 * Family Tree Builder Utilities
 * 
 * Builds graph structures from relationship data for vis.js family tree visualization.
 * vis.js supports proper graph structures where children can have multiple parents.
 */

const FAMILY_RELATIONSHIP_TYPES = ['parent', 'child', 'spouse', 'lover', 'partner'];

export function isFamilyRelationshipType(typeSlug) {
  return FAMILY_RELATIONSHIP_TYPES.includes(typeSlug?.toLowerCase());
}

function isParentType(typeSlug) {
  return typeSlug?.toLowerCase() === 'parent';
}

function isChildType(typeSlug) {
  return typeSlug?.toLowerCase() === 'child';
}

function isSpouseType(typeSlug) {
  const slug = typeSlug?.toLowerCase();
  return slug === 'spouse' || slug === 'lover' || slug === 'partner';
}

/**
 * Build a graph structure from relationships for vis.js
 * Returns nodes and edges arrays compatible with vis-network
 */
export function buildFamilyGraph(startPersonId, allPeople, relationshipMap) {
  const nodesMap = new Map();
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
    let birthDate = person.acf?.birth_date || null;
    if (birthDate) {
      const bd = new Date(birthDate);
      if (!isNaN(bd.getTime())) {
        age = Math.floor((new Date() - bd) / (365.25 * 24 * 60 * 60 * 1000));
      }
    }
    
    // Get gender symbol
    let genderSymbol = '';
    switch (person.acf?.gender) {
      case 'male': genderSymbol = '♂'; break;
      case 'female': genderSymbol = '♀'; break;
      case 'non_binary':
      case 'other':
      case 'prefer_not_to_say':
        genderSymbol = '⚧'; break;
    }
    
    // Format birth date
    let formattedBirthDate = '';
    if (birthDate) {
      try {
        const bd = new Date(birthDate);
        if (!isNaN(bd.getTime())) {
          const day = String(bd.getDate()).padStart(2, '0');
          const month = String(bd.getMonth() + 1).padStart(2, '0');
          const year = bd.getFullYear();
          formattedBirthDate = `${day}-${month}-${year}`;
        }
      } catch (e) {}
    }
    
    return {
      id: personId,
      name: personName,
      gender: person.acf?.gender || '',
      genderSymbol,
      photo: person.thumbnail || null,
      age,
      birthDate,
      formattedBirthDate,
    };
  }
  
  const startPerson = peopleMap.get(startPersonId);
  if (startPerson) {
    nodesMap.set(startPersonId, extractPersonData(startPerson, startPersonId));
  }
  
  // BFS to collect all connected family members
  while (queue.length > 0) {
    const { personId, depth } = queue.shift();
    const relationships = relationshipMap.get(personId) || [];
    
    for (const rel of relationships) {
      if (rel.relationship_type_slug && !isFamilyRelationshipType(rel.relationship_type_slug)) {
        continue;
      }
      
      const relatedPersonId = rel.related_person;
      if (!relatedPersonId || !peopleMap.has(relatedPersonId)) continue;
      
      if (!nodesMap.has(relatedPersonId)) {
        const relatedPerson = peopleMap.get(relatedPersonId);
        nodesMap.set(relatedPersonId, extractPersonData(relatedPerson, relatedPersonId));
      }
      
      // Add edge: parent -> child (arrow points from parent to child)
      // If rel.type is 'parent', then personId's parent is relatedPersonId
      // So the edge should be: relatedPersonId -> personId
      // If rel.type is 'child', then personId's child is relatedPersonId
      // So the edge should be: personId -> relatedPersonId
      
      const edgeKey1 = `${personId}-${relatedPersonId}`;
      const edgeKey2 = `${relatedPersonId}-${personId}`;
      const edgeExists = edges.some(e => 
        `${e.from}-${e.to}` === edgeKey1 || `${e.from}-${e.to}` === edgeKey2
      );
      
      if (!edgeExists) {
        const relType = rel.relationship_type_slug?.toLowerCase();
        
        if (isParentType(relType)) {
          // personId has relatedPersonId as parent
          // Edge: parent (relatedPersonId) -> child (personId)
          edges.push({
            from: relatedPersonId,
            to: personId,
            type: 'parent-child',
          });
        } else if (isChildType(relType)) {
          // personId has relatedPersonId as child
          // Edge: parent (personId) -> child (relatedPersonId)
          edges.push({
            from: personId,
            to: relatedPersonId,
            type: 'parent-child',
          });
        } else if (isSpouseType(relType)) {
          // Spouse/lover relationship - horizontal connection
          // Use consistent ordering to avoid duplicates
          const [first, second] = personId < relatedPersonId 
            ? [personId, relatedPersonId] 
            : [relatedPersonId, personId];
          edges.push({
            from: first,
            to: second,
            type: 'spouse',
          });
        }
      }
      
      if (!visited.has(relatedPersonId)) {
        visited.add(relatedPersonId);
        queue.push({ personId: relatedPersonId, depth: depth + 1 });
      }
    }
  }
  
  return {
    nodes: Array.from(nodesMap.values()),
    edges,
  };
}

/**
 * Convert graph to vis.js format with hierarchical levels
 * Assigns levels based on generation (parents above children)
 */
export function graphToVisFormat(graph, startPersonId) {
  const { nodes, edges } = graph;
  
  if (nodes.length === 0) return { nodes: [], edges: [] };
  
  // Build adjacency lists for traversal
  const childToParents = new Map(); // child -> [parent IDs]
  const parentToChildren = new Map(); // parent -> [child IDs]
  
  nodes.forEach(node => {
    childToParents.set(node.id, []);
    parentToChildren.set(node.id, []);
  });
  
  edges.forEach(edge => {
    // edge.from = parent, edge.to = child
    childToParents.get(edge.to)?.push(edge.from);
    parentToChildren.get(edge.from)?.push(edge.to);
  });
  
  // Calculate levels using BFS from roots (people with no parents)
  const levels = new Map();
  const roots = nodes.filter(n => childToParents.get(n.id)?.length === 0);
  
  // If no roots found, start from oldest person
  if (roots.length === 0) {
    const sorted = [...nodes].sort((a, b) => {
      if (!a.birthDate && !b.birthDate) return 0;
      if (!a.birthDate) return 1;
      if (!b.birthDate) return -1;
      return new Date(a.birthDate) - new Date(b.birthDate);
    });
    if (sorted.length > 0) {
      roots.push(sorted[0]);
    }
  }
  
  // Sort roots by birth date (oldest first)
  roots.sort((a, b) => {
    if (!a.birthDate && !b.birthDate) return 0;
    if (!a.birthDate) return 1;
    if (!b.birthDate) return -1;
    return new Date(a.birthDate) - new Date(b.birthDate);
  });
  
  // BFS to assign levels
  const queue = roots.map(r => ({ id: r.id, level: 0 }));
  roots.forEach(r => levels.set(r.id, 0));
  
  while (queue.length > 0) {
    const { id, level } = queue.shift();
    
    const children = parentToChildren.get(id) || [];
    for (const childId of children) {
      const existingLevel = levels.get(childId);
      const newLevel = level + 1;
      
      // Use the maximum level (furthest from root)
      if (existingLevel === undefined || newLevel > existingLevel) {
        levels.set(childId, newLevel);
        queue.push({ id: childId, level: newLevel });
      }
    }
  }
  
  // Handle any unvisited nodes (disconnected)
  nodes.forEach(node => {
    if (!levels.has(node.id)) {
      levels.set(node.id, 0);
    }
  });
  
  // Ensure spouses are on the same level
  // Find spouse pairs and adjust levels
  const spouseEdges = edges.filter(e => e.type === 'spouse');
  for (const edge of spouseEdges) {
    const level1 = levels.get(edge.from);
    const level2 = levels.get(edge.to);
    if (level1 !== undefined && level2 !== undefined && level1 !== level2) {
      // Put both on the higher level (smaller number = higher in tree)
      const minLevel = Math.min(level1, level2);
      levels.set(edge.from, minLevel);
      levels.set(edge.to, minLevel);
    }
  }
  
  // Generate placeholder image for people without photos
  function generatePlaceholderImage(name, isStartPerson) {
    const initials = name ? name.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2) : '?';
    const bgColor = isStartPerson ? '%23fef3c7' : '%23e5e7eb';
    const textColor = isStartPerson ? '%23b45309' : '%23374151';
    const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="60" height="60" viewBox="0 0 60 60">
      <circle cx="30" cy="30" r="30" fill="${bgColor}"/>
      <text x="30" y="38" text-anchor="middle" font-family="system-ui, sans-serif" font-size="20" font-weight="500" fill="${textColor}">${initials}</text>
    </svg>`;
    return 'data:image/svg+xml,' + encodeURIComponent(svg);
  }
  
  // Create vis.js nodes with levels and styling
  const visNodes = nodes.map(node => {
    const level = levels.get(node.id) || 0;
    const isStartPerson = node.id === startPersonId;
    
    // Build label with name, gender symbol, age, birth date
    let labelLines = [node.name];
    let infoLine = '';
    if (node.genderSymbol) infoLine += node.genderSymbol + ' ';
    if (node.age !== null) infoLine += node.age + ' ';
    if (node.formattedBirthDate) infoLine += node.formattedBirthDate;
    if (infoLine.trim()) labelLines.push(infoLine.trim());
    
    // Use circularImage for all nodes (consistent size)
    const image = node.photo || generatePlaceholderImage(node.name, isStartPerson);
    
    return {
      id: node.id,
      label: labelLines.join('\n'),
      level,
      shape: 'circularImage',
      image: image,
      size: 30,
      font: {
        size: 12,
        face: 'system-ui, -apple-system, sans-serif',
        vadjust: 5, // Push label down a bit
      },
      color: {
        border: isStartPerson ? '#f59e0b' : '#d1d5db',
        background: '#ffffff',
        highlight: {
          border: '#f59e0b',
          background: '#fef3c7',
        },
      },
      borderWidth: isStartPerson ? 3 : 2,
      data: node,
    };
  });
  
  // Create vis.js edges with different styles for parent-child vs spouse
  const visEdges = edges.map((edge, index) => {
    const isSpouse = edge.type === 'spouse';
    
    return {
      id: `edge-${index}`,
      from: edge.from,
      to: edge.to,
      arrows: {
        to: { enabled: false },
      },
      color: {
        color: isSpouse ? '#ec4899' : '#9ca3af', // Pink for spouse, gray for parent-child
        highlight: isSpouse ? '#db2777' : '#6b7280',
      },
      width: 2,
      dashes: isSpouse ? [5, 5] : false, // Dashed line for spouse
      smooth: false, // Straight lines
    };
  });
  
  return { nodes: visNodes, edges: visEdges };
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
