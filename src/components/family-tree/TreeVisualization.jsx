import { useMemo, useState, useRef } from 'react';
import { ZoomIn, ZoomOut, RotateCcw } from 'lucide-react';
import ReactFamilyTree from 'react-family-tree';
import PersonNode from './PersonNode';

const NODE_WIDTH = 180;
const NODE_HEIGHT = 120;

/**
 * Tree Visualization Component
 * Renders a family tree using react-family-tree
 */
export default function TreeVisualization({ treeData, onNodeClick }) {
  const [zoom, setZoom] = useState(1);
  const containerRef = useRef(null);
  
  // Ensure we always have a valid nodes array, even if empty
  const safeNodes = useMemo(() => {
    // This will be set by the nodes useMemo below
    return [];
  }, []);
  
  // Convert tree structure to nodes array expected by react-family-tree
  // react-family-tree expects: { id: string, gender: Gender, parents: Relation[], children: Relation[], siblings: Relation[] }
  const nodes = useMemo(() => {
    if (!treeData || !treeData.attributes || !treeData.attributes.id) {
      return [];
    }
    
    const nodeMap = new Map(); // Map to store all nodes by ID
    const allNodes = [];
    
    // First pass: collect all nodes
    function collectNodes(node) {
      if (!node || !node.attributes || !node.attributes.id) {
        return;
      }
      
      const nodeId = String(node.attributes.id);
      if (nodeMap.has(nodeId)) {
        return; // Already collected
      }
      
      // Map gender to library's Gender enum
      let gender = 'male'; // default
      if (node.attributes.gender === 'female') {
        gender = 'female';
      }
      
      const nodeData = {
        id: nodeId,
        gender: gender,
        parents: [], // Always initialize as empty array
        children: [], // Always initialize as empty array
        siblings: [], // Always initialize as empty array
        spouses: [], // Library expects spouses array
        // Store additional data for our use
        _name: node.name || `Person ${nodeId}`,
        _photo: node.attributes.photo || null,
        _age: node.attributes.age !== null && node.attributes.age !== undefined ? node.attributes.age : null,
        _birthDate: node.attributes.birthDate || null,
      };
      
      // Ensure arrays are always defined (defensive programming)
      if (!Array.isArray(nodeData.parents)) nodeData.parents = [];
      if (!Array.isArray(nodeData.children)) nodeData.children = [];
      if (!Array.isArray(nodeData.siblings)) nodeData.siblings = [];
      if (!Array.isArray(nodeData.spouses)) nodeData.spouses = [];
      
      nodeMap.set(nodeId, nodeData);
      allNodes.push(nodeData);
      
      // Recursively collect children
      const children = node.children || [];
      if (Array.isArray(children) && children.length > 0) {
        children.forEach(child => {
          if (child && child.attributes && child.attributes.id) {
            collectNodes(child);
          }
        });
      }
    }
    
    // Second pass: build relationships
    function buildRelationships(node) {
      if (!node || !node.attributes || !node.attributes.id) {
        return;
      }
      
      const nodeId = String(node.attributes.id);
      const nodeData = nodeMap.get(nodeId);
      if (!nodeData) return;
      
      // Build children relationships
      const children = node.children || [];
      if (Array.isArray(children) && children.length > 0) {
        children.forEach(child => {
          if (child && child.attributes && child.attributes.id) {
            const childId = String(child.attributes.id);
            
            // Ensure arrays exist before pushing
            if (!Array.isArray(nodeData.children)) nodeData.children = [];
            
            // Check if child relation already exists to avoid duplicates
            const childExists = nodeData.children.some(c => c.id === childId);
            if (!childExists) {
              nodeData.children.push({
                id: childId,
                type: 'blood', // Default relationship type
              });
            }
            
            // Also add parent relationship to child (bidirectional)
            const childData = nodeMap.get(childId);
            if (childData) {
              if (!Array.isArray(childData.parents)) childData.parents = [];
              // Check if parent relation already exists to avoid duplicates
              const parentExists = childData.parents.some(p => p.id === nodeId);
              if (!parentExists) {
                childData.parents.push({
                  id: nodeId,
                  type: 'blood',
                });
              }
            } else {
              console.warn(`Child node ${childId} not found in nodeMap when building relationships`);
            }
          }
        });
      }
      
      // Recursively process children
      children.forEach(child => {
        if (child && child.attributes && child.attributes.id) {
          buildRelationships(child);
        }
      });
    }
    
    try {
      collectNodes(treeData);
      buildRelationships(treeData);
      
      // Build siblings relationships (children of same parents are siblings)
      allNodes.forEach(node => {
        // Ensure arrays exist
        if (!Array.isArray(node.parents)) node.parents = [];
        if (!Array.isArray(node.siblings)) node.siblings = [];
        
        if (node.parents.length > 0) {
          // Find all nodes with same parents
          const siblingIds = new Set();
          node.parents.forEach(parent => {
            if (parent && parent.id) {
              const parentNode = nodeMap.get(parent.id);
              if (parentNode && Array.isArray(parentNode.children)) {
                parentNode.children.forEach(child => {
                  if (child && child.id && child.id !== node.id) {
                    siblingIds.add(child.id);
                  }
                });
              }
            }
          });
          
          siblingIds.forEach(siblingId => {
            // Check if sibling relation already exists to avoid duplicates
            const siblingExists = node.siblings.some(s => s.id === siblingId);
            if (!siblingExists) {
              node.siblings.push({
                id: siblingId,
                type: 'blood',
              });
            }
          });
        }
      });
      
      // Create a Set of all valid node IDs for quick lookup
      const validNodeIds = new Set(allNodes.map(n => String(n.id)));
      
      // Final validation: ensure all nodes have all required arrays and valid structure
      // Also ensure all relation IDs reference nodes that actually exist
      const validatedNodes = allNodes.map(node => {
        // Ensure node has all required properties
        if (!node.id) {
          console.warn('Node missing id:', node);
          return null;
        }
        
        const nodeId = String(node.id);
        
        // Filter relations to only include IDs that exist in the nodes array
        const validParents = Array.isArray(node.parents) 
          ? node.parents
              .map(p => ({
                id: String(p.id || p),
                type: p.type || 'blood',
              }))
              .filter(p => validNodeIds.has(p.id))
          : [];
        
        const validChildren = Array.isArray(node.children)
          ? node.children
              .map(c => ({
                id: String(c.id || c),
                type: c.type || 'blood',
              }))
              .filter(c => validNodeIds.has(c.id))
          : [];
        
        const validSiblings = Array.isArray(node.siblings)
          ? node.siblings
              .map(s => ({
                id: String(s.id || s),
                type: s.type || 'blood',
              }))
              .filter(s => validNodeIds.has(s.id))
          : [];
        
        // Ensure spouses array exists (library requirement)
        const validSpouses = Array.isArray(node.spouses)
          ? node.spouses
              .map(s => ({
                id: String(s.id || s),
                type: s.type || 'married',
              }))
              .filter(s => validNodeIds.has(s.id))
          : [];
        
        return {
          id: nodeId,
          gender: node.gender || 'male',
          parents: validParents,
          children: validChildren,
          siblings: validSiblings,
          spouses: validSpouses, // Library expects spouses array
          // Preserve custom fields
          _name: node._name,
          _photo: node._photo,
          _age: node._age,
          _birthDate: node._birthDate,
        };
      }).filter(node => node !== null); // Remove any invalid nodes
      
      // Log detailed structure for debugging
      console.log('Converted nodes for react-family-tree:', validatedNodes);
      console.log('Number of nodes:', validatedNodes.length);
      console.log('Node IDs:', validatedNodes.map(n => n.id));
      
      // Log first node structure to verify format
      if (validatedNodes.length > 0) {
        console.log('First node structure:', JSON.stringify(validatedNodes[0], null, 2));
        console.log('First node parents:', validatedNodes[0].parents);
        console.log('First node children:', validatedNodes[0].children);
        console.log('First node siblings:', validatedNodes[0].siblings);
      }
      
      // Verify all nodes have required structure
      const invalidNodes = validatedNodes.filter(n => {
        return !n.id || 
               !n.gender || 
               !Array.isArray(n.parents) || 
               !Array.isArray(n.children) || 
               !Array.isArray(n.siblings);
      });
      
      if (invalidNodes.length > 0) {
        console.error('Invalid nodes found:', invalidNodes);
      }
      
      // Verify all relation IDs exist in nodes
      validatedNodes.forEach(node => {
        const allRelationIds = [
          ...node.parents.map(p => p.id),
          ...node.children.map(c => c.id),
          ...node.siblings.map(s => s.id),
        ];
        const missingIds = allRelationIds.filter(id => !validNodeIds.has(id));
        if (missingIds.length > 0) {
          console.warn(`Node ${node.id} has relations to non-existent nodes:`, missingIds);
        }
      });
      
      return validatedNodes;
    } catch (error) {
      console.error('Error converting tree to nodes:', error);
      return [];
    }
  }, [treeData]);
  
  const rootId = useMemo(() => {
    if (!treeData || !treeData.attributes || !treeData.attributes.id) return null;
    const root = String(treeData.attributes.id);
    console.log('Root ID:', root);
    if (nodes && Array.isArray(nodes)) {
      console.log('Root ID exists in nodes:', nodes.some(n => n && n.id === root));
    }
    return root; // react-family-tree expects string ID
  }, [treeData, nodes]);
  
  // Ensure nodes is always an array (never undefined)
  const validatedNodes = useMemo(() => {
    if (!nodes || !Array.isArray(nodes)) {
      console.warn('Nodes is not a valid array:', nodes);
      return [];
    }
    return nodes;
  }, [nodes]);
  
  const handleZoomIn = () => {
    setZoom(prev => Math.min(prev + 0.2, 2));
  };
  
  const handleZoomOut = () => {
    setZoom(prev => Math.max(prev - 0.2, 0.5));
  };
  
  const handleReset = () => {
    setZoom(1);
  };
  
  const handleNodeClick = (node) => {
    if (onNodeClick) {
      onNodeClick({ attributes: { id: parseInt(node.id, 10) } });
    }
  };
  
  if (!treeData || !validatedNodes || validatedNodes.length === 0) {
    return (
      <div className="flex items-center justify-center h-96">
        <p className="text-gray-500">No family tree data available</p>
      </div>
    );
  }
  
  return (
    <div className="relative w-full h-full">
      {/* Controls */}
      <div className="absolute top-4 right-4 z-10 flex gap-2 bg-white rounded-lg shadow-lg p-2">
        <button
          onClick={handleZoomIn}
          className="p-2 hover:bg-gray-100 rounded"
          title="Zoom In"
        >
          <ZoomIn className="w-4 h-4" />
        </button>
        <button
          onClick={handleZoomOut}
          className="p-2 hover:bg-gray-100 rounded"
          title="Zoom Out"
        >
          <ZoomOut className="w-4 h-4" />
        </button>
        <button
          onClick={handleReset}
          className="p-2 hover:bg-gray-100 rounded"
          title="Reset View"
        >
          <RotateCcw className="w-4 h-4" />
        </button>
      </div>
      
      {/* Tree Container */}
      <div 
        ref={containerRef} 
        className="w-full h-full min-h-[600px] relative overflow-auto"
        style={{ transform: `scale(${zoom})`, transformOrigin: 'top left' }}
      >
        {(() => {
          try {
            // Use validatedNodes which is guaranteed to be an array
            const nodesToUse = validatedNodes || [];
            
            if (!nodesToUse || nodesToUse.length === 0) {
              return (
                <div className="flex items-center justify-center h-full">
                  <p className="text-gray-500">No nodes to display</p>
                </div>
              );
            }
            
            if (!rootId) {
              return (
                <div className="flex items-center justify-center h-full">
                  <p className="text-gray-500">No root node found</p>
                </div>
              );
            }
            
            const rootExists = nodesToUse.some(n => n && n.id === rootId);
            if (!rootExists) {
              console.error('Root ID not found in nodes:', { rootId, nodeIds: nodesToUse.map(n => n?.id) });
              return (
                <div className="flex items-center justify-center h-full">
                  <p className="text-gray-500">Root node not found in nodes array</p>
                </div>
              );
            }
            
            // Create a deep copy to ensure immutability
            const safeNodes = JSON.parse(JSON.stringify(nodesToUse));
            
            // Final validation: ensure all nodes are valid and have all required arrays
            const finalNodes = safeNodes.map(node => {
              // Ensure all required arrays exist
              if (!Array.isArray(node.parents)) node.parents = [];
              if (!Array.isArray(node.children)) node.children = [];
              if (!Array.isArray(node.siblings)) node.siblings = [];
              if (!Array.isArray(node.spouses)) node.spouses = [];
              
              return node;
            }).filter(node => {
              const isValid = node && 
                             node.id && 
                             node.gender && 
                             Array.isArray(node.parents) && 
                             Array.isArray(node.children) && 
                             Array.isArray(node.siblings) &&
                             Array.isArray(node.spouses);
              if (!isValid) {
                console.warn('Filtering out invalid node:', node);
              }
              return isValid;
            });
            
            if (finalNodes.length === 0) {
              return (
                <div className="flex items-center justify-center h-full">
                  <p className="text-red-500">No valid nodes after filtering</p>
                </div>
              );
            }
            
            // Verify root still exists after filtering
            const rootStillExists = finalNodes.some(n => n.id === rootId);
            if (!rootStillExists) {
              console.error('Root node filtered out:', rootId);
              return (
                <div className="flex items-center justify-center h-full">
                  <p className="text-red-500">Root node was filtered out</p>
                </div>
              );
            }
            
            console.log('Final nodes being passed to ReactFamilyTree:', finalNodes.length);
            
            return (
              <ReactFamilyTree
                nodes={finalNodes}
                rootId={rootId}
                width={NODE_WIDTH}
                height={NODE_HEIGHT}
                  renderNode={(node) => {
                    if (!node || node.id === undefined || node.id === null) {
                      console.warn('Invalid node in renderNode:', node);
                      return null;
                    }
                    // react-family-tree uses transform: translate() with left/top multiplied by half dimensions
                    // The library calculates positions, we just need to apply them correctly
                    const left = node.left !== undefined && node.left !== null ? node.left * (NODE_WIDTH / 2) : 0;
                    const top = node.top !== undefined && node.top !== null ? node.top * (NODE_HEIGHT / 2) : 0;
                    
                    return (
                      <PersonNode
                        key={node.id}
                        node={node}
                        onClick={handleNodeClick}
                        style={{
                          width: `${NODE_WIDTH}px`,
                          height: `${NODE_HEIGHT}px`,
                          transform: `translate(${left}px, ${top}px)`,
                        }}
                      />
                    );
                  }}
              />
            );
          } catch (error) {
            console.error('Error rendering ReactFamilyTree:', error);
            return (
              <div className="flex items-center justify-center h-full">
                <p className="text-red-500">Error rendering family tree: {error.message}</p>
              </div>
            );
          }
        })()}
      </div>
    </div>
  );
}
