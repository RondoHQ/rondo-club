import { useMemo, useState, useRef, useEffect } from 'react';
import { ZoomIn, ZoomOut, RotateCcw, Maximize2 } from 'lucide-react';
import Tree from 'react-d3-tree';
import PersonNode from './PersonNode';

/**
 * Tree Visualization Component
 * Renders a family tree using react-d3-tree
 */
export default function TreeVisualization({ treeData, onNodeClick }) {
  const [translate, setTranslate] = useState({ x: 0, y: 0 });
  const [dimensions, setDimensions] = useState({ width: 0, height: 0 });
  const [zoom, setZoom] = useState(1);
  const containerRef = useRef(null);
  
  // Calculate dimensions and center the tree
  useEffect(() => {
    if (containerRef.current) {
      const { width, height } = containerRef.current.getBoundingClientRect();
      setDimensions({ width, height });
      setTranslate({ x: width / 2, y: 80 });
    }
  }, []);
  
  // Handle window resize
  useEffect(() => {
    const handleResize = () => {
      if (containerRef.current) {
        const { width, height } = containerRef.current.getBoundingClientRect();
        setDimensions({ width, height });
        setTranslate({ x: width / 2, y: 80 });
      }
    };
    
    window.addEventListener('resize', handleResize);
    return () => window.removeEventListener('resize', handleResize);
  }, []);
  
  const handleZoomIn = () => {
    setZoom(prev => Math.min(prev + 0.2, 2));
  };
  
  const handleZoomOut = () => {
    setZoom(prev => Math.max(prev - 0.2, 0.5));
  };
  
  const handleReset = () => {
    setZoom(1);
    if (containerRef.current) {
      const { width, height } = containerRef.current.getBoundingClientRect();
      setTranslate({ x: width / 2, y: 80 });
    }
  };
  
  const handleNodeClick = (nodeData) => {
    if (onNodeClick) {
      onNodeClick(nodeData);
    }
  };
  
  // Custom node renderer
  // react-d3-tree passes: { nodeDatum, toggleNode, onNodeClick, foreignObjectWrapper }
  const renderCustomNode = ({ nodeDatum, toggleNode }) => {
    return (
      <PersonNode nodeDatum={nodeDatum} onClick={handleNodeClick} />
    );
  };
  
  if (!treeData) {
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
      <div ref={containerRef} className="w-full h-full min-h-[600px]">
        {dimensions.width > 0 && (
          <Tree
            data={treeData}
            orientation="vertical"
            translate={translate}
            zoom={zoom}
            dimensions={dimensions}
            renderCustomNodeElement={renderCustomNode}
            pathClassFunc={() => 'tree-link'}
            nodeSize={{ x: 220, y: 140 }}
            separation={{ siblings: 1.2, nonSiblings: 1.5 }}
            initialDepth={3}
            depthFactor={140}
            styles={{
              links: {
                stroke: '#b2b2b2',
                strokeWidth: 2,
              },
            }}
          />
        )}
      </div>
      
      <style>{`
        .tree-link {
          stroke: #b2b2b2;
          stroke-width: 2;
          fill: none;
        }
        .person-node {
          pointer-events: all;
        }
      `}</style>
    </div>
  );
}

