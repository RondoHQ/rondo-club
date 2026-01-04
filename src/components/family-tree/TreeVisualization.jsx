import { useEffect, useRef, useCallback } from 'react';
import { useNavigate } from 'react-router-dom';
import { Network } from 'vis-network';
import { DataSet } from 'vis-data';
import { ZoomIn, ZoomOut, RotateCcw } from 'lucide-react';

/**
 * Tree Visualization Component
 * Renders a family tree using vis.js Network
 * Supports multiple parents per child (proper family tree structure)
 */
export default function TreeVisualization({ graphData, startPersonId, onNodeClick }) {
  const containerRef = useRef(null);
  const networkRef = useRef(null);
  const navigate = useNavigate();

  const handleNodeClick = useCallback((nodeId) => {
    if (onNodeClick) {
      onNodeClick(nodeId);
    } else {
      navigate(`/people/${nodeId}`);
    }
  }, [onNodeClick, navigate]);

  useEffect(() => {
    if (!containerRef.current || !graphData || !graphData.nodes || graphData.nodes.length === 0) {
      return;
    }

    // Create DataSets
    const nodesDataSet = new DataSet(graphData.nodes);
    const edgesDataSet = new DataSet(graphData.edges);

    // Network options
    const options = {
      layout: {
        hierarchical: {
          enabled: true,
          direction: 'UD', // Up-Down (parents above children)
          sortMethod: 'hubsize', // Better for keeping spouses together
          levelSeparation: 180, // More space between generations
          nodeSpacing: 180, // More space between nodes on same level
          treeSpacing: 250, // More space between disconnected trees
          blockShifting: true,
          edgeMinimization: true,
          parentCentralization: true,
        },
      },
      nodes: {
        shape: 'box',
        margin: 10,
        font: {
          size: 14,
          face: 'system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif',
          color: '#1f2937',
        },
        color: {
          border: '#d1d5db',
          background: '#ffffff',
          highlight: {
            border: '#f59e0b',
            background: '#fef3c7',
          },
          hover: {
            border: '#9ca3af',
            background: '#f9fafb',
          },
        },
        borderWidth: 2,
        borderWidthSelected: 3,
        shadow: {
          enabled: true,
          color: 'rgba(0,0,0,0.1)',
          size: 5,
          x: 0,
          y: 2,
        },
      },
      edges: {
        color: {
          color: '#9ca3af',
          highlight: '#6b7280',
          hover: '#6b7280',
        },
        width: 2,
        smooth: {
          enabled: true,
          type: 'cubicBezier',
          forceDirection: 'vertical',
          roundness: 0.4,
        },
        arrows: {
          to: {
            enabled: false,
          },
        },
      },
      physics: {
        enabled: false, // Disable physics for hierarchical layout
      },
      interaction: {
        hover: true,
        tooltipDelay: 200,
        zoomView: true,
        dragView: true,
        dragNodes: false, // Keep nodes in place
        navigationButtons: false,
        keyboard: {
          enabled: true,
          bindToWindow: false,
        },
      },
    };

    // Create network
    const network = new Network(
      containerRef.current,
      { nodes: nodesDataSet, edges: edgesDataSet },
      options
    );

    networkRef.current = network;

    // Handle click events
    network.on('click', (params) => {
      if (params.nodes.length > 0) {
        const nodeId = params.nodes[0];
        handleNodeClick(nodeId);
      }
    });

    // Handle double-click for centering
    network.on('doubleClick', (params) => {
      if (params.nodes.length > 0) {
        network.focus(params.nodes[0], {
          scale: 1,
          animation: {
            duration: 500,
            easingFunction: 'easeInOutQuad',
          },
        });
      }
    });

    // Fit the network after stabilization
    network.once('stabilized', () => {
      network.fit({
        animation: {
          duration: 500,
          easingFunction: 'easeInOutQuad',
        },
      });
    });

    // Focus on start person after initial render
    setTimeout(() => {
      if (startPersonId && network) {
        network.focus(startPersonId, {
          scale: 0.8,
          animation: {
            duration: 500,
            easingFunction: 'easeInOutQuad',
          },
        });
      }
    }, 100);

    // Cleanup
    return () => {
      if (network) {
        network.destroy();
      }
    };
  }, [graphData, startPersonId, handleNodeClick]);

  const handleZoomIn = () => {
    if (networkRef.current) {
      const scale = networkRef.current.getScale();
      networkRef.current.moveTo({
        scale: scale * 1.3,
        animation: { duration: 300, easingFunction: 'easeInOutQuad' },
      });
    }
  };

  const handleZoomOut = () => {
    if (networkRef.current) {
      const scale = networkRef.current.getScale();
      networkRef.current.moveTo({
        scale: scale / 1.3,
        animation: { duration: 300, easingFunction: 'easeInOutQuad' },
      });
    }
  };

  const handleReset = () => {
    if (networkRef.current) {
      networkRef.current.fit({
        animation: { duration: 500, easingFunction: 'easeInOutQuad' },
      });
    }
  };

  const handleFocusStart = () => {
    if (networkRef.current && startPersonId) {
      networkRef.current.focus(startPersonId, {
        scale: 1,
        animation: { duration: 500, easingFunction: 'easeInOutQuad' },
      });
    }
  };

  if (!graphData || !graphData.nodes || graphData.nodes.length === 0) {
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
          title="Fit All"
        >
          <RotateCcw className="w-4 h-4" />
        </button>
      </div>

      {/* Info text */}
      <div className="absolute bottom-4 left-4 z-10 text-xs text-gray-500 bg-white/80 px-2 py-1 rounded">
        Click a person to view details • Double-click to center • Scroll to zoom
      </div>

      {/* Network Container */}
      <div
        ref={containerRef}
        className="w-full h-full min-h-[600px]"
        style={{ background: '#fafafa' }}
      />
    </div>
  );
}
