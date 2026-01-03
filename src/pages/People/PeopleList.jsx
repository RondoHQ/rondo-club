import { useState } from 'react';
import { Link } from 'react-router-dom';
import { Plus, Search, Star, Filter } from 'lucide-react';
import { usePeople } from '@/hooks/usePeople';

function PersonCard({ person }) {
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
  const [search, setSearch] = useState('');
  const { data: people, isLoading, error } = usePeople({ search });
  
  return (
    <div className="space-y-4">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div className="relative flex-1 max-w-md">
          <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
          <input
            type="search"
            placeholder="Search people..."
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            className="input pl-9"
          />
        </div>
        
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
            <Search className="w-6 h-6 text-gray-400" />
          </div>
          <h3 className="text-lg font-medium text-gray-900 mb-1">No people found</h3>
          <p className="text-gray-500 mb-4">
            {search ? 'Try a different search term.' : 'Get started by adding your first person.'}
          </p>
          {!search && (
            <Link to="/people/new" className="btn-primary">
              <Plus className="w-4 h-4 mr-2" />
              Add Person
            </Link>
          )}
        </div>
      )}
      
      {/* People grid */}
      {!isLoading && !error && people?.length > 0 && (
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
          {people.map((person) => (
            <PersonCard key={person.id} person={person} />
          ))}
        </div>
      )}
    </div>
  );
}
