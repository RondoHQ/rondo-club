import { useMemo, lazy, Suspense } from 'react';
import { useParams, Link, useNavigate } from 'react-router-dom';
import { useQuery, useQueries } from '@tanstack/react-query';
import { ArrowLeft } from 'lucide-react';
import { usePerson, usePeople } from '@/hooks/usePeople';
import { wpApi, prmApi } from '@/api/client';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';

const TreeVisualization = lazy(() => import('@/components/family-tree/TreeVisualization'));
import {
  buildFamilyGraph,
  buildRelationshipMap,
  enrichRelationshipsWithTypes,
  graphToVisFormat,
} from '@/utils/familyTreeBuilder';

export default function FamilyTree() {
  const { id } = useParams();
  const navigate = useNavigate();
  const personId = parseInt(id, 10);
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

  // Get all person IDs for fetching dates
  const allPersonIds = useMemo(() => {
    return allPeople.map(p => p.id).filter(Boolean);
  }, [allPeople]);

  // Fetch dates for all people to check deceased status
  const personDatesQueries = useQueries({
    queries: allPersonIds.map(pid => ({
      queryKey: ['person-dates', pid],
      queryFn: async () => {
        const response = await prmApi.getPersonDates(pid);
        return response.data;
      },
      enabled: !!pid,
      staleTime: 5 * 60 * 1000, // Cache for 5 minutes
    })),
  });

  // Create a map of person ID to deceased status
  const personDeceasedMap = useMemo(() => {
    const map = {};
    personDatesQueries.forEach((query, index) => {
      if (query.data && allPersonIds[index]) {
        const pid = allPersonIds[index];
        const hasDiedDate = query.data.some(d => {
          const dateType = Array.isArray(d.date_type) ? d.date_type[0] : d.date_type;
          return dateType?.toLowerCase() === 'died';
        });
        map[pid] = hasDiedDate;
      }
    });
    return map;
  }, [personDatesQueries, allPersonIds]);
  
  useDocumentTitle(
    person ? `Family Tree - ${person.name || person.title?.rendered || person.title || 'Person'}` : 'Family Tree'
  );
  
  // Build graph data for vis.js
  const graphData = useMemo(() => {
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
      const graph = buildFamilyGraph(personId, allPeople, relationshipMap);
      
      if (!graph || graph.nodes.length === 0) {
        return null;
      }
      
      // Convert to vis.js format with hierarchical levels
      const visData = graphToVisFormat(graph, personId, personDeceasedMap);
      
      return visData;
    } catch {
      return null;
    }
  }, [person, allPeople, relationshipTypes, personId, personDeceasedMap]);
  
  const handleNodeClick = (nodeId) => {
    navigate(`/people/${nodeId}`);
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
            <ArrowLeft className="w-4 h-4 md:mr-2" />
            <span className="hidden md:inline">Back to Person</span>
          </Link>
          <h1 className="text-2xl font-bold">
            Family Tree: {person.name || person.title?.rendered || person.title || `Person ${id}`}
          </h1>
        </div>
      </div>
      
      {/* Tree Visualization */}
      <div className="card p-6">
        <div className="w-full" style={{ height: '800px' }}>
          {graphData && graphData.nodes && graphData.nodes.length > 0 ? (
            <Suspense fallback={
              <div className="flex items-center justify-center h-96">
                <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600"></div>
                <span className="ml-3 text-gray-500">Loading visualization...</span>
              </div>
            }>
              <TreeVisualization
                graphData={graphData}
                startPersonId={personId}
                onNodeClick={handleNodeClick}
              />
            </Suspense>
          ) : (
            <div className="flex items-center justify-center h-full">
              <div className="text-center">
                <p className="text-gray-500 mb-2">No family relationships found.</p>
                <p className="text-sm text-gray-400">
                  Add parent or child relationships to see the family tree.
                </p>
              </div>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
