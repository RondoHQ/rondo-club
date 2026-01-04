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
    
    let genderSymbol = '';
    switch (person.acf?.gender) {
      case 'male': genderSymbol = '♂'; break;
      case 'female': genderSymbol = '♀'; break;
      case 'non_binary':
      case 'other':
      case 'prefer_not_to_say':
        genderSymbol = '⚧'; break;
    }
    
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
      
      const edgeKey1 = `${personId}-${relatedPersonId}`;
      const edgeKey2 = `${relatedPersonId}-${personId}`;
      const edgeExists = edges.some(e => 
        `${e.from}-${e.to}` === edgeKey1 || `${e.from}-${e.to}` === edgeKey2
      );
      
      if (!edgeExists) {
        const relType = rel.relationship_type_slug?.toLowerCase();
        
        if (isParentType(relType)) {
          edges.push({
            from: relatedPersonId,
            to: personId,
            type: 'parent-child',
          });
        } else if (isChildType(relType)) {
          edges.push({
            from: personId,
            to: relatedPersonId,
            type: 'parent-child',
          });
        } else if (isSpouseType(relType)) {
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
 * Calculates levels based on generation relative to start person:
 * - Start person = generation 0
 * - Parents = generation -1 (above)
 * - Grandparents = generation -2
 * - Children = generation +1 (below)
 * - Spouses share the same level as their partner
 */
export function graphToVisFormat(graph, startPersonId) {
  const { nodes, edges } = graph;
  
  if (nodes.length === 0) return { nodes: [], edges: [] };
  
  // Build adjacency lists
  const childToParents = new Map();
  const parentToChildren = new Map();
  const spouseOf = new Map(); // person -> [spouse IDs]
  
  nodes.forEach(node => {
    childToParents.set(node.id, []);
    parentToChildren.set(node.id, []);
    spouseOf.set(node.id, []);
  });
  
  edges.forEach(edge => {
    if (edge.type === 'parent-child') {
      childToParents.get(edge.to)?.push(edge.from);
      parentToChildren.get(edge.from)?.push(edge.to);
    } else if (edge.type === 'spouse') {
      spouseOf.get(edge.from)?.push(edge.to);
      spouseOf.get(edge.to)?.push(edge.from);
    }
  });
  
  // Calculate generations relative to start person using BFS
  const generations = new Map();
  const visited = new Set();
  const queue = [{ id: startPersonId, generation: 0 }];
  generations.set(startPersonId, 0);
  visited.add(startPersonId);
  
  while (queue.length > 0) {
    const { id, generation } = queue.shift();
    
    // Parents are one generation up (-1)
    const parents = childToParents.get(id) || [];
    for (const parentId of parents) {
      if (!visited.has(parentId)) {
        visited.add(parentId);
        generations.set(parentId, generation - 1);
        queue.push({ id: parentId, generation: generation - 1 });
      }
    }
    
    // Children are one generation down (+1)
    const children = parentToChildren.get(id) || [];
    for (const childId of children) {
      if (!visited.has(childId)) {
        visited.add(childId);
        generations.set(childId, generation + 1);
        queue.push({ id: childId, generation: generation + 1 });
      }
    }
    
    // Spouses are same generation
    const spouses = spouseOf.get(id) || [];
    for (const spouseId of spouses) {
      if (!visited.has(spouseId)) {
        visited.add(spouseId);
        generations.set(spouseId, generation);
        queue.push({ id: spouseId, generation: generation });
      }
    }
  }
  
  // Handle any unvisited nodes (shouldn't happen in a connected graph)
  nodes.forEach(node => {
    if (!generations.has(node.id)) {
      generations.set(node.id, 0);
    }
  });
  
  // Normalize generations to positive levels (0 = topmost)
  const minGeneration = Math.min(...generations.values());
  const levels = new Map();
  generations.forEach((gen, id) => {
    levels.set(id, gen - minGeneration);
  });
  
  // Generate placeholder image for people without photos
  function generatePlaceholderImage(name, isStartPerson) {
    const initials = name ? name.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2) : '?';
    const bgColor = isStartPerson ? '%23fef3c7' : '%23e5e7eb';
    const textColor = isStartPerson ? '%23b45309' : '%23374151';
    const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="90" height="90" viewBox="0 0 90 90">
      <circle cx="45" cy="45" r="45" fill="${bgColor}"/>
      <text x="45" y="55" text-anchor="middle" font-family="system-ui, sans-serif" font-size="28" font-weight="500" fill="${textColor}">${initials}</text>
    </svg>`;
    return 'data:image/svg+xml,' + encodeURIComponent(svg);
  }
  
  // Create vis.js nodes
  const visNodes = nodes.map(node => {
    const level = levels.get(node.id) || 0;
    const isStartPerson = node.id === startPersonId;
    
    let labelLines = [node.name];
    let infoLine = '';
    if (node.genderSymbol) infoLine += node.genderSymbol + ' ';
    if (node.age !== null) infoLine += node.age + ' ';
    if (node.formattedBirthDate) infoLine += node.formattedBirthDate;
    if (infoLine.trim()) labelLines.push(infoLine.trim());
    
    const image = node.photo || generatePlaceholderImage(node.name, isStartPerson);
    
    return {
      id: node.id,
      label: labelLines.join('\n'),
      level,
      shape: 'circularImage',
      image: image,
      size: 45, // 1.5x bigger (was 30)
      font: {
        size: 14,
        face: 'system-ui, -apple-system, sans-serif',
        vadjust: 8,
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
  
  // Create vis.js edges
  const visEdges = edges.map((edge, index) => {
    const isSpouse = edge.type === 'spouse';
    
    return {
      id: `edge-${index}`,
      from: edge.from,
      to: edge.to,
      arrows: { to: { enabled: false } },
      color: {
        color: isSpouse ? '#ec4899' : '#9ca3af',
        highlight: isSpouse ? '#db2777' : '#6b7280',
      },
      width: 2,
      dashes: isSpouse ? [5, 5] : false,
      smooth: false,
      // Spouse edges are very short to pull partners together
      // Parent-child edges are much longer to allow spreading
      length: isSpouse ? 50 : 300,
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
