import { useMemo, useState, useRef, useEffect } from 'react';
import { ZoomIn, ZoomOut, RotateCcw } from 'lucide-react';
import ReactFamilyTree from 'react-family-tree';
import PersonNode from './PersonNode';

const NODE_WIDTH = 160;
const NODE_HEIGHT = 100;

/**
 * Tree Visualization Component
 * Renders a family tree using react-family-tree
 */
export default function TreeVisualization({ treeData, onNodeClick }) {
  const [zoom, setZoom] = useState(1);
  const containerRef = useRef(null);
  
  // Convert tree structure to flat nodes array with parentId
  const nodes = useMemo(() => {
    if (!treeData || !treeData.attributes || !treeData.attributes.id) {
      return [];
    }
    
    const flatNodes = [];
    
    function traverse(node, parentId = null) {
      // Validate node structure
      if (!node || !node.attributes || !node.attributes.id) {
        return;
      }
      
      const nodeData = {
        id: node.attributes.id,
        parentId: parentId,
        name: node.name || `Person ${node.attributes.id}`,
        gender: node.attributes.gender || '',
        photo: node.attributes.photo || null,
        age: node.attributes.age !== null && node.attributes.age !== undefined ? node.attributes.age : null,
        birthDate: node.attributes.birthDate || null,
      };
      
      flatNodes.push(nodeData);
      
      // Safely handle children array
      if (Array.isArray(node.children) && node.children.length > 0) {
        node.children.forEach(child => {
          if (child && child.attributes && child.attributes.id) {
            traverse(child, node.attributes.id);
          }
        });
      }
    }
    
    traverse(treeData);
    return flatNodes;
  }, [treeData]);
  
  const rootId = useMemo(() => {
    if (!treeData) return null;
    return treeData.attributes.id;
  }, [treeData]);
  
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
      onNodeClick({ attributes: node });
    }
  };
  
  if (!treeData || nodes.length === 0) {
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
        {nodes.length > 0 && rootId && (
          <ReactFamilyTree
            nodes={nodes}
            rootId={rootId}
            width={NODE_WIDTH}
            height={NODE_HEIGHT}
            renderNode={(node) => {
              if (!node || !node.id) return null;
              return (
                <PersonNode
                  key={node.id}
                  node={node}
                  onClick={handleNodeClick}
                  style={{
                    position: 'absolute',
                    left: `${node.left || 0}px`,
                    top: `${node.top || 0}px`,
                    width: `${NODE_WIDTH}px`,
                    height: `${NODE_HEIGHT}px`,
                  }}
                />
              );
            }}
          />
        )}
      </div>
    </div>
  );
}
