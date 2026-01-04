import { useMemo, useState, useRef, useEffect } from 'react';
import { ZoomIn, ZoomOut, RotateCcw } from 'lucide-react';
import Tree from 'react-d3-tree';
import PersonNode from './PersonNode';

const NODE_SIZE = { x: 200, y: 140 }; // Spacing between nodes
const NODE_WIDTH = 160;
const NODE_HEIGHT = 100;

/**
 * Tree Visualization Component
 * Renders a family tree using react-d3-tree
 */
export default function TreeVisualization({ treeData, onNodeClick }) {
  const [zoom, setZoom] = useState(0.8);
  const [translate, setTranslate] = useState({ x: 0, y: 0 });
  const containerRef = useRef(null);
  const treeWrapperRef = useRef(null);

  // Ensure treeData is properly formatted
  const formattedTreeData = useMemo(() => {
    if (!treeData) return null;
    
    // react-d3-tree expects the tree structure directly
    // Our treeData already has the correct format from familyTreeBuilder
    return treeData;
  }, [treeData]);

  const handleZoomIn = () => {
    setZoom(prev => Math.min(prev + 0.2, 2));
  };

  const handleZoomOut = () => {
    setZoom(prev => Math.max(prev - 0.2, 0.5));
  };

  const handleReset = () => {
    setZoom(1);
    if (containerRef.current) {
      const dimensions = containerRef.current.getBoundingClientRect();
      setTranslate({
        x: dimensions.width / 2,
        y: 80, // Top padding
      });
    }
  };

  // Auto-center and auto-zoom on mount and when treeData changes
  useEffect(() => {
    if (containerRef.current && treeData) {
      const dimensions = containerRef.current.getBoundingClientRect();
      // Center horizontally
      setTranslate({
        x: dimensions.width / 2,
        y: 80, // Top padding
      });
      
      // Auto-zoom to fit the tree
      // Estimate tree width based on depth (rough calculation)
      const estimateTreeWidth = (node, depth = 0) => {
        if (!node || !node.children || node.children.length === 0) {
          return NODE_SIZE.x;
        }
        const maxChildWidth = Math.max(...node.children.map(child => estimateTreeWidth(child, depth + 1)));
        return Math.max(NODE_SIZE.x, maxChildWidth * node.children.length);
      };
      
      const estimatedWidth = estimateTreeWidth(treeData);
      const containerWidth = dimensions.width - 40; // Account for padding
      const suggestedZoom = Math.min(0.8, containerWidth / estimatedWidth);
      setZoom(Math.max(0.4, suggestedZoom)); // Don't zoom out too much
    }
  }, [treeData]);

  const handleNodeClick = (nodeData) => {
    if (onNodeClick && nodeData?.attributes?.id) {
      onNodeClick({ attributes: { id: parseInt(nodeData.attributes.id, 10) } });
    }
  };

  if (!formattedTreeData) {
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
        style={{ padding: '20px' }}
      >
        <Tree
          data={formattedTreeData}
          orientation="vertical"
          translate={translate}
          zoom={zoom}
          nodeSize={NODE_SIZE}
          separation={{ siblings: 1.2, nonSiblings: 1.5 }}
          pathFunc="straight"
          renderCustomNodeElement={(rd3tProps) => {
            const { nodeDatum } = rd3tProps;
            
            // Skip rendering virtual root nodes entirely
            if (nodeDatum.attributes?.isVirtualRoot || 
                nodeDatum.attributes?.id === 'virtual-root' || 
                nodeDatum.attributes?.id === 'family-tree-root') {
              // Return an invisible placeholder so connectors still work
              return <g />;
            }
            
            const nodeData = {
              id: nodeDatum.attributes?.id,
              coupleIds: nodeDatum.attributes?.coupleIds,
              _name: nodeDatum.name,
              gender: nodeDatum.attributes?.gender,
              _photo: nodeDatum.attributes?.photo,
              _partnerPhoto: nodeDatum.attributes?.partnerPhoto,
              _age: nodeDatum.attributes?.age,
              _birthDate: nodeDatum.attributes?.birthDate,
              _isCouple: nodeDatum.attributes?.isCouple,
            };

            return (
              <g>
                <foreignObject
                  x={-NODE_WIDTH / 2}
                  y={-NODE_HEIGHT / 2}
                  width={NODE_WIDTH}
                  height={NODE_HEIGHT}
                  onClick={() => handleNodeClick(nodeDatum)}
                  style={{ cursor: 'pointer', overflow: 'visible' }}
                >
                  <PersonNode
                    node={nodeData}
                    onClick={() => handleNodeClick(nodeDatum)}
                  />
                </foreignObject>
              </g>
            );
          }}
          styles={{
            links: {
              stroke: '#3b82f6',
              strokeWidth: 2,
            },
            nodes: {
              node: {
                fill: 'transparent',
                stroke: 'transparent',
              },
              leafNode: {
                fill: 'transparent',
                stroke: 'transparent',
              },
            },
          }}
        />
      </div>
    </div>
  );
}
