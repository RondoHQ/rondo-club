import { useState, useMemo, useRef, useEffect } from 'react';
import { Link } from 'react-router-dom';
import { Plus, Star, Filter, X, Check } from 'lucide-react';
import { usePeople } from '@/hooks/usePeople';
import { useQueries, useQuery } from '@tanstack/react-query';
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
            loading="lazy"
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
  const [isFilterOpen, setIsFilterOpen] = useState(false);
  const [showFavoritesOnly, setShowFavoritesOnly] = useState(false);
  const [selectedLabels, setSelectedLabels] = useState([]);
  const filterRef = useRef(null);
  const dropdownRef = useRef(null);
  
  const { data: people, isLoading, error } = usePeople();
  
  // Fetch person labels
  const { data: labelsData } = useQuery({
    queryKey: ['person-labels'],
    queryFn: async () => {
      const response = await wpApi.getPersonLabels();
      return response.data;
    },
  });
  
  const availableLabels = labelsData?.map(label => label.name) || [];
  
  // Close dropdown when clicking outside
  useEffect(() => {
    const handleClickOutside = (event) => {
      if (
        dropdownRef.current &&
        !dropdownRef.current.contains(event.target) &&
        filterRef.current &&
        !filterRef.current.contains(event.target)
      ) {
        setIsFilterOpen(false);
      }
    };
    
    document.addEventListener('mousedown', handleClickOutside);
    return () => {
      document.removeEventListener('mousedown', handleClickOutside);
    };
  }, []);
  
  // Filter and sort people
  const filteredAndSortedPeople = useMemo(() => {
    if (!people) return [];
    
    let filtered = [...people];
    
    // Apply favorites filter
    if (showFavoritesOnly) {
      filtered = filtered.filter(person => person.is_favorite);
    }
    
    // Apply label filters
    if (selectedLabels.length > 0) {
      filtered = filtered.filter(person => {
        const personLabels = person.labels || [];
        return selectedLabels.some(label => personLabels.includes(label));
      });
    }
    
    // Sort by last name alphabetically
    return filtered.sort((a, b) => {
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
  }, [people, showFavoritesOnly, selectedLabels]);
  
  const hasActiveFilters = showFavoritesOnly || selectedLabels.length > 0;
  
  const handleLabelToggle = (label) => {
    setSelectedLabels(prev => 
      prev.includes(label)
        ? prev.filter(l => l !== label)
        : [...prev, label]
    );
  };
  
  const clearFilters = () => {
    setShowFavoritesOnly(false);
    setSelectedLabels([]);
  };

  // Collect all company IDs
  const companyIds = useMemo(() => {
    if (!filteredAndSortedPeople) return [];
    const ids = filteredAndSortedPeople
      .map(person => getCurrentCompanyId(person))
      .filter(Boolean);
    // Remove duplicates
    return [...new Set(ids)];
  }, [filteredAndSortedPeople]);

  // Batch fetch all companies at once instead of individual queries
  const { data: companiesData } = useQuery({
    queryKey: ['companies', 'batch', companyIds.sort().join(',')],
    queryFn: async () => {
      if (companyIds.length === 0) return [];
      // Fetch all companies in one request
      const response = await wpApi.getCompanies({ 
        per_page: 100,
        include: companyIds.join(','),
      });
      return response.data;
    },
    enabled: companyIds.length > 0,
  });

  // Create a map of company ID to company name
  const companyMap = useMemo(() => {
    const map = {};
    if (companiesData) {
      companiesData.forEach(company => {
        map[company.id] = company.title?.rendered || company.title || '';
      });
    }
    return map;
  }, [companiesData]);

  // Create a map of person ID to company name
  const personCompanyMap = useMemo(() => {
    const map = {};
    filteredAndSortedPeople.forEach(person => {
      const companyId = getCurrentCompanyId(person);
      if (companyId && companyMap[companyId]) {
        map[person.id] = companyMap[companyId];
      }
    });
    return map;
  }, [filteredAndSortedPeople, companyMap]);
  
  return (
    <div className="space-y-4">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div className="flex flex-wrap items-center gap-2">
          <div className="relative" ref={filterRef}>
            <button 
              onClick={() => setIsFilterOpen(!isFilterOpen)}
              className={`btn-secondary ${hasActiveFilters ? 'bg-primary-50 text-primary-700 border-primary-200' : ''}`}
            >
              <Filter className="w-4 h-4 mr-2" />
              Filter
              {hasActiveFilters && (
                <span className="ml-2 px-1.5 py-0.5 bg-primary-600 text-white text-xs rounded-full">
                  {selectedLabels.length + (showFavoritesOnly ? 1 : 0)}
                </span>
              )}
            </button>
            
            {/* Filter Dropdown */}
            {isFilterOpen && (
              <div
                ref={dropdownRef}
                className="absolute top-full left-0 mt-2 w-64 bg-white border border-gray-200 rounded-lg shadow-lg z-50"
              >
                <div className="p-4 space-y-4">
                  {/* Favorites Filter */}
                  <div>
                    <label className="flex items-center cursor-pointer">
                      <input
                        type="checkbox"
                        checked={showFavoritesOnly}
                        onChange={(e) => setShowFavoritesOnly(e.target.checked)}
                        className="sr-only"
                      />
                      <div className={`flex items-center justify-center w-5 h-5 border-2 rounded mr-3 ${
                        showFavoritesOnly 
                          ? 'bg-primary-600 border-primary-600' 
                          : 'border-gray-300'
                      }`}>
                        {showFavoritesOnly && (
                          <Check className="w-3 h-3 text-white" />
                        )}
                      </div>
                      <div className="flex items-center">
                        <Star className="w-4 h-4 mr-2 text-yellow-400 fill-current" />
                        <span className="text-sm font-medium text-gray-900">Favorites only</span>
                      </div>
                    </label>
                  </div>
                  
                  {/* Labels Filter */}
                  {availableLabels.length > 0 && (
                    <div>
                      <h3 className="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">
                        Labels
                      </h3>
                      <div className="space-y-1 max-h-48 overflow-y-auto">
                        {availableLabels.map(label => (
                          <label
                            key={label}
                            className="flex items-center cursor-pointer hover:bg-gray-50 p-1 rounded"
                          >
                            <input
                              type="checkbox"
                              checked={selectedLabels.includes(label)}
                              onChange={() => handleLabelToggle(label)}
                              className="sr-only"
                            />
                            <div className={`flex items-center justify-center w-5 h-5 border-2 rounded mr-3 ${
                              selectedLabels.includes(label)
                                ? 'bg-primary-600 border-primary-600'
                                : 'border-gray-300'
                            }`}>
                              {selectedLabels.includes(label) && (
                                <Check className="w-3 h-3 text-white" />
                              )}
                            </div>
                            <span className="text-sm text-gray-700">{label}</span>
                          </label>
                        ))}
                      </div>
                    </div>
                  )}
                  
                  {/* Clear Filters */}
                  {hasActiveFilters && (
                    <button
                      onClick={clearFilters}
                      className="w-full text-sm text-primary-600 hover:text-primary-700 font-medium pt-2 border-t border-gray-200"
                    >
                      Clear all filters
                    </button>
                  )}
                </div>
              </div>
            )}
          </div>
          
          {/* Active Filter Chips */}
          {hasActiveFilters && (
            <div className="flex flex-wrap gap-2">
              {showFavoritesOnly && (
                <span className="inline-flex items-center gap-1 px-2 py-1 bg-primary-100 text-primary-800 rounded-full text-xs">
                  <Star className="w-3 h-3" />
                  Favorites
                  <button
                    onClick={() => setShowFavoritesOnly(false)}
                    className="hover:text-primary-600"
                  >
                    <X className="w-3 h-3" />
                  </button>
                </span>
              )}
              {selectedLabels.map(label => (
                <span
                  key={label}
                  className="inline-flex items-center gap-1 px-2 py-1 bg-gray-100 text-gray-800 rounded-full text-xs"
                >
                  {label}
                  <button
                    onClick={() => handleLabelToggle(label)}
                    className="hover:text-gray-600"
                  >
                    <X className="w-3 h-3" />
                  </button>
                </span>
              ))}
            </div>
          )}
          
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
      {!isLoading && !error && filteredAndSortedPeople?.length > 0 && (
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
          {filteredAndSortedPeople.map((person) => (
            <PersonCard 
              key={person.id} 
              person={person} 
              companyName={personCompanyMap[person.id]}
            />
          ))}
        </div>
      )}
      
      {/* No results with filters */}
      {!isLoading && !error && people?.length > 0 && filteredAndSortedPeople?.length === 0 && (
        <div className="card p-12 text-center">
          <div className="w-12 h-12 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <Filter className="w-6 h-6 text-gray-400" />
          </div>
          <h3 className="text-lg font-medium text-gray-900 mb-1">No people match your filters</h3>
          <p className="text-gray-500 mb-4">
            Try adjusting your filters to see more results.
          </p>
          <button onClick={clearFilters} className="btn-secondary">
            Clear filters
          </button>
        </div>
      )}
    </div>
  );
}
