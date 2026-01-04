import { useMemo } from 'react';
import { useParams, Link, useNavigate } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { ArrowLeft } from 'lucide-react';
import { usePerson, usePeople } from '@/hooks/usePeople';
import { wpApi } from '@/api/client';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';
import TreeVisualization from '@/components/family-tree/TreeVisualization';
import {
  buildFamilyGraph,
  buildRelationshipMap,
  enrichRelationshipsWithTypes,
  graphToTree,
} from '@/utils/familyTreeBuilder';

export default function FamilyTree() {
  const { id } = useParams();
  const navigate = useNavigate();
  const { data: person, isLoading: isLoadingPerson } = usePerson(id);
  const { data: allPeople = [], isLoading: isLoadingPeople } = usePeople();
  
  // Fetch relationship types to enrich relationships
  const { data: relationshipTypes = [] } = useQuery({
    queryKey: ['relationship-types'],
    queryFn: async () => {
      const response = await wpApi.getRelationshipTypes();
      return response.data;
    },
  });
  
  useDocumentTitle(
    person ? `Family Tree - ${person.name || person.title?.rendered || person.title || 'Person'}` : 'Family Tree'
  );
  
  // Build tree data
  const treeData = useMemo(() => {
    if (!person || !allPeople.length) {
      return null;
    }
    
    try {
      // Build relationship map
      const relationshipMap = buildRelationshipMap(allPeople);
      
      // Enrich with relationship type slugs if we have relationship types
      if (relationshipTypes.length > 0) {
        enrichRelationshipsWithTypes(relationshipMap, relationshipTypes);
      }
      
      // Build graph
      const graph = buildFamilyGraph(parseInt(id, 10), allPeople, relationshipMap);
      
      // Debug: log graph structure
      console.log('Family tree graph:', graph);
      
      // Convert to tree structure
      const tree = graphToTree(graph, parseInt(id, 10));
      
      // Debug: log tree structure
      console.log('Family tree structure:', tree);
      
      return tree;
    } catch (error) {
      console.error('Error building family tree:', error);
      return null;
    }
  }, [person, allPeople, relationshipTypes, id]);
  
  const handleNodeClick = (nodeData) => {
    const personId = nodeData.attributes?.id;
    if (personId) {
      navigate(`/people/${personId}`);
    }
  };
  
  if (isLoadingPerson || isLoadingPeople) {
    return (
      <div className="flex items-center justify-center h-64">
        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600"></div>
      </div>
    );
  }
  
  if (!person) {
    return (
      <div className="card p-6 text-center">
        <p className="text-red-600 mb-4">Failed to load person.</p>
        <Link to="/people" className="btn-secondary">Back to People</Link>
      </div>
    );
  }
  
  return (
    <div className="max-w-full mx-auto space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-4">
          <Link
            to={`/people/${id}`}
            className="flex items-center text-gray-600 hover:text-gray-900"
          >
            <ArrowLeft className="w-4 h-4 mr-2" />
            Back to Person
          </Link>
          <h1 className="text-2xl font-bold">
            Family Tree: {person.name || person.title?.rendered || person.title || `Person ${id}`}
          </h1>
        </div>
      </div>
      
      {/* Tree Visualization */}
      <div className="card p-6">
        <div className="w-full" style={{ height: '800px' }}>
          {treeData ? (
            <>
              {/* Debug info - remove in production */}
              {process.env.NODE_ENV === 'development' && (
                <div className="mb-4 p-2 bg-gray-100 rounded text-xs">
                  <strong>Debug:</strong> Tree has {JSON.stringify(treeData).length} chars. 
                  Root name: {treeData?.name || 'N/A'}
                </div>
              )}
              <TreeVisualization treeData={treeData} onNodeClick={handleNodeClick} />
            </>
          ) : (
            <div className="flex items-center justify-center h-full">
              <div className="text-center">
                <p className="text-gray-500 mb-2">No family relationships found.</p>
                <p className="text-sm text-gray-400">
                  Add family relationships to see the family tree.
                </p>
                {process.env.NODE_ENV === 'development' && (
                  <div className="mt-4 p-2 bg-gray-100 rounded text-xs text-left">
                    <strong>Debug:</strong><br />
                    Person loaded: {person ? 'Yes' : 'No'}<br />
                    People count: {allPeople.length}<br />
                    Relationship types: {relationshipTypes.length}
                  </div>
                )}
              </div>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}

