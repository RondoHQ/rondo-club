import { useMemo } from 'react';
import { Link } from 'react-router-dom';
import { Plus, Star, Filter } from 'lucide-react';
import { usePeople } from '@/hooks/usePeople';
import { useQueries } from '@tanstack/react-query';
import { wpApi } from '@/api/client';

// Helper function to get current company ID from person's work history
function getCurrentCompanyId(person) {
  const workHistory = person.acf?.work_history || [];
  if (workHistory.length === 0) return null;
  
  // First, try to find current position
  const currentJob = workHistory.find(job => job.is_current && job.company);
  if (currentJob) return currentJob.company;
  
  // Otherwise, get the most recent (by start_date)
  const jobsWithCompany = workHistory
    .filter(job => job.company)
    .sort((a, b) => {
      const dateA = a.start_date ? new Date(a.start_date) : new Date(0);
      const dateB = b.start_date ? new Date(b.start_date) : new Date(0);
      return dateB - dateA; // Most recent first
    });
  
  return jobsWithCompany.length > 0 ? jobsWithCompany[0].company : null;
}

function PersonCard({ person, companyName }) {
  return (
    <Link 
      to={`/people/${person.id}`}
      className="card p-4 hover:shadow-md transition-shadow"
    >
      <div className="flex items-start">
        {person.thumbnail ? (
          <img 
            src={person.thumbnail} 
            alt={person.name}
            className="w-12 h-12 rounded-full object-cover"
          />
        ) : (
          <div className="w-12 h-12 bg-gray-200 rounded-full flex items-center justify-center">
            <span className="text-lg font-medium text-gray-500">
              {person.first_name?.[0] || '?'}
            </span>
          </div>
        )}
        <div className="ml-3 flex-1 min-w-0">
          <div className="flex items-center">
            <h3 className="text-sm font-medium text-gray-900 truncate">
              {person.name}
            </h3>
            {person.is_favorite && (
              <Star className="w-4 h-4 ml-1 text-yellow-400 fill-current flex-shrink-0" />
            )}
          </div>
          {companyName && (
            <p className="text-xs text-gray-500 mt-0.5 truncate">
              {companyName}
            </p>
          )}
          {person.labels?.length > 0 && (
            <div className="flex flex-wrap gap-1 mt-1">
              {person.labels.slice(0, 3).map((label) => (
                <span 
                  key={label}
                  className="inline-flex px-2 py-0.5 text-xs rounded-full bg-gray-100 text-gray-600"
                >
                  {label}
                </span>
              ))}
            </div>
          )}
        </div>
      </div>
    </Link>
  );
}

export default function PeopleList() {
  const { data: people, isLoading, error } = usePeople();
  
  // Sort people by last name alphabetically
  const sortedPeople = useMemo(() => {
    if (!people) return [];
    
    return [...people].sort((a, b) => {
      const lastNameA = (a.acf?.last_name || a.last_name || '').toLowerCase();
      const lastNameB = (b.acf?.last_name || b.last_name || '').toLowerCase();
      
      // If last names are equal, sort by first name
      if (lastNameA === lastNameB) {
        const firstNameA = (a.acf?.first_name || a.first_name || '').toLowerCase();
        const firstNameB = (b.acf?.first_name || b.first_name || '').toLowerCase();
        return firstNameA.localeCompare(firstNameB);
      }
      
      return lastNameA.localeCompare(lastNameB);
    });
  }, [people]);

  // Collect all company IDs
  const companyIds = useMemo(() => {
    if (!sortedPeople) return [];
    const ids = sortedPeople
      .map(person => getCurrentCompanyId(person))
      .filter(Boolean);
    // Remove duplicates
    return [...new Set(ids)];
  }, [sortedPeople]);

  // Fetch company names
  const companyQueries = useQueries({
    queries: companyIds.map(companyId => ({
      queryKey: ['company', companyId],
      queryFn: async () => {
        const response = await wpApi.getCompany(companyId);
        return response.data;
      },
      enabled: !!companyId,
    })),
  });

  // Create a map of company ID to company name
  const companyMap = useMemo(() => {
    const map = {};
    companyQueries.forEach((query, index) => {
      if (query.data) {
        map[companyIds[index]] = query.data.title?.rendered || query.data.title || '';
      }
    });
    return map;
  }, [companyQueries, companyIds]);

  // Create a map of person ID to company name
  const personCompanyMap = useMemo(() => {
    const map = {};
    sortedPeople.forEach(person => {
      const companyId = getCurrentCompanyId(person);
      if (companyId && companyMap[companyId]) {
        map[person.id] = companyMap[companyId];
      }
    });
    return map;
  }, [sortedPeople, companyMap]);
  
  return (
    <div className="space-y-4">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div className="flex gap-2">
          <button className="btn-secondary">
            <Filter className="w-4 h-4 mr-2" />
            Filter
          </button>
          <Link to="/people/new" className="btn-primary">
            <Plus className="w-4 h-4 mr-2" />
            Add Person
          </Link>
        </div>
      </div>
      
      {/* Loading state */}
      {isLoading && (
        <div className="flex items-center justify-center h-64">
          <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600"></div>
        </div>
      )}
      
      {/* Error state */}
      {error && (
        <div className="card p-6 text-center">
          <p className="text-red-600">Failed to load people.</p>
        </div>
      )}
      
      {/* Empty state */}
      {!isLoading && !error && people?.length === 0 && (
        <div className="card p-12 text-center">
          <div className="w-12 h-12 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <Plus className="w-6 h-6 text-gray-400" />
          </div>
          <h3 className="text-lg font-medium text-gray-900 mb-1">No people found</h3>
          <p className="text-gray-500 mb-4">
            Get started by adding your first person.
          </p>
          <Link to="/people/new" className="btn-primary">
            <Plus className="w-4 h-4 mr-2" />
            Add Person
          </Link>
        </div>
      )}
      
      {/* People grid */}
      {!isLoading && !error && sortedPeople?.length > 0 && (
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
          {sortedPeople.map((person) => (
            <PersonCard 
              key={person.id} 
              person={person} 
              companyName={personCompanyMap[person.id]}
            />
          ))}
        </div>
      )}
    </div>
  );
}
