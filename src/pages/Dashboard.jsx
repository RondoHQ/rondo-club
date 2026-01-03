import { Link } from 'react-router-dom';
import { Users, Building2, Calendar, Star, ArrowRight, Plus, Sparkles } from 'lucide-react';
import { useDashboard } from '@/hooks/useDashboard';
import { format, formatDistanceToNow } from 'date-fns';

function StatCard({ title, value, icon: Icon, href }) {
  return (
    <Link to={href} className="card p-6 hover:shadow-md transition-shadow">
      <div className="flex items-center justify-between">
        <div>
          <p className="text-sm font-medium text-gray-500">{title}</p>
          <p className="mt-1 text-3xl font-semibold">{value}</p>
        </div>
        <div className="p-3 bg-primary-50 rounded-lg">
          <Icon className="w-6 h-6 text-primary-600" />
        </div>
      </div>
    </Link>
  );
}

function PersonCard({ person }) {
  return (
    <Link 
      to={`/people/${person.id}`}
      className="flex items-center p-3 rounded-lg hover:bg-gray-50 transition-colors"
    >
      {person.thumbnail ? (
        <img 
          src={person.thumbnail} 
          alt={person.name}
          loading="lazy"
          className="w-10 h-10 rounded-full object-cover"
        />
      ) : (
        <div className="w-10 h-10 bg-gray-200 rounded-full flex items-center justify-center">
          <span className="text-sm font-medium text-gray-500">
            {person.first_name?.[0] || '?'}
          </span>
        </div>
      )}
      <div className="ml-3 flex-1 min-w-0">
        <p className="text-sm font-medium text-gray-900 truncate">{person.name}</p>
        {person.labels?.length > 0 && (
          <p className="text-xs text-gray-500 truncate">{person.labels.join(', ')}</p>
        )}
      </div>
      {person.is_favorite && (
        <Star className="w-4 h-4 text-yellow-400 fill-current" />
      )}
    </Link>
  );
}

function ReminderCard({ reminder }) {
  const daysUntil = reminder.days_until;

  let urgencyClass = 'bg-gray-100 text-gray-700';
  if (daysUntil === 0) {
    urgencyClass = 'bg-red-100 text-red-700';
  } else if (daysUntil <= 3) {
    urgencyClass = 'bg-orange-100 text-orange-700';
  } else if (daysUntil <= 7) {
    urgencyClass = 'bg-yellow-100 text-yellow-700';
  }

  // Get the full name from related people if available
  const personName = reminder.related_people?.[0]?.name || reminder.title;
  const firstPersonId = reminder.related_people?.[0]?.id;
  const hasRelatedPeople = reminder.related_people?.length > 0;

  const cardContent = (
    <>
      <div className={`px-2 py-1 rounded text-xs font-medium ${urgencyClass}`}>
        {daysUntil === 0 ? 'Today' : `${daysUntil}d`}
      </div>
      <div className="ml-3 flex-1 min-w-0">
        <p className="text-sm font-medium text-gray-900">{personName}</p>
        <p className="text-xs text-gray-500">
          {format(new Date(reminder.next_occurrence), 'MMMM d, yyyy')}
        </p>
        {hasRelatedPeople && (
          <div className="flex -space-x-2 mt-1">
            {reminder.related_people.slice(0, 3).map((person) => (
              person.thumbnail ? (
                <img
                  key={person.id}
                  src={person.thumbnail}
                  alt={person.name}
                  loading="lazy"
                  className="w-10 h-10 rounded-full border-2 border-white object-cover"
                />
              ) : (
                <div
                  key={person.id}
                  className="w-10 h-10 bg-gray-300 rounded-full border-2 border-white flex items-center justify-center"
                >
                  <span className="text-sm">{person.name?.[0]}</span>
                </div>
              )
            ))}
          </div>
        )}
      </div>
    </>
  );

  // Wrap in Link if there are related people, otherwise just a div
  if (hasRelatedPeople && firstPersonId) {
    return (
      <Link
        to={`/people/${firstPersonId}`}
        className="flex items-start p-3 rounded-lg hover:bg-gray-50 transition-colors"
      >
        {cardContent}
      </Link>
    );
  }

  return (
    <div className="flex items-start p-3 rounded-lg hover:bg-gray-50 transition-colors">
      {cardContent}
    </div>
  );
}

function EmptyState() {
  return (
    <div className="card p-12 text-center">
      <div className="flex justify-center mb-4">
        <div className="p-4 bg-primary-50 rounded-full">
          <Sparkles className="w-12 h-12 text-primary-600" />
        </div>
      </div>
      <h2 className="text-2xl font-semibold text-gray-900 mb-2">Welcome to Personal CRM!</h2>
      <p className="text-gray-600 mb-8 max-w-md mx-auto">
        Get started by adding your first contact, company, or important date. Your dashboard will populate as you add more information.
      </p>
      <div className="flex flex-col sm:flex-row gap-4 justify-center">
        <Link
          to="/people/new"
          className="inline-flex items-center px-6 py-3 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors"
        >
          <Plus className="w-5 h-5 mr-2" />
          Add Your First Person
        </Link>
        <Link
          to="/companies/new"
          className="inline-flex items-center px-6 py-3 bg-white text-gray-700 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors"
        >
          <Plus className="w-5 h-5 mr-2" />
          Add Your First Company
        </Link>
      </div>
    </div>
  );
}

export default function Dashboard() {
  const { data, isLoading, error } = useDashboard();
  
  if (isLoading) {
    return (
      <div className="flex items-center justify-center h-64">
        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600"></div>
      </div>
    );
  }
  
  if (error) {
    // Check if it's a network/API error vs empty state
    const isNetworkError = error?.response?.status >= 500 || !error?.response;
    
    return (
      <div className="card p-8 text-center">
        <div className="text-red-600 mb-2">
          {isNetworkError ? (
            <>
              <p className="font-medium mb-1">Failed to load dashboard data</p>
              <p className="text-sm text-gray-600">
                Please check your connection and try refreshing the page.
              </p>
            </>
          ) : (
            <>
              <p className="font-medium mb-1">Unable to load dashboard</p>
              <p className="text-sm text-gray-600">
                {error?.response?.data?.message || 'An error occurred while loading your data.'}
              </p>
            </>
          )}
        </div>
      </div>
    );
  }
  
  const { stats, recent_people, upcoming_reminders, favorites } = data || {};
  const totalItems = (stats?.total_people || 0) + (stats?.total_companies || 0) + (stats?.total_dates || 0);
  const isEmpty = totalItems === 0;
  
  if (isEmpty) {
    return (
      <div className="space-y-6">
        {/* Stats - still show them even when empty */}
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
          <StatCard
            title="Total People"
            value={0}
            icon={Users}
            href="/people"
          />
          <StatCard
            title="Companies"
            value={0}
            icon={Building2}
            href="/companies"
          />
          <StatCard
            title="Important Dates"
            value={0}
            icon={Calendar}
            href="/dates"
          />
        </div>
        
        {/* Empty State */}
        <EmptyState />
      </div>
    );
  }
  
  return (
    <div className="space-y-6">
      {/* Stats */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <StatCard
          title="Total People"
          value={stats?.total_people || 0}
          icon={Users}
          href="/people"
        />
        <StatCard
          title="Companies"
          value={stats?.total_companies || 0}
          icon={Building2}
          href="/companies"
        />
        <StatCard
          title="Important Dates"
          value={stats?.total_dates || 0}
          icon={Calendar}
          href="/dates"
        />
      </div>
      
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Upcoming Reminders */}
        <div className="card">
          <div className="flex items-center justify-between p-4 border-b border-gray-200">
            <h2 className="font-semibold">Upcoming Reminders</h2>
            <Link 
              to="/dates" 
              className="text-sm text-primary-600 hover:text-primary-700 flex items-center"
            >
              View all
              <ArrowRight className="w-4 h-4 ml-1" />
            </Link>
          </div>
          <div className="divide-y divide-gray-100">
            {upcoming_reminders?.length > 0 ? (
              upcoming_reminders.map((reminder) => (
                <ReminderCard key={reminder.id} reminder={reminder} />
              ))
            ) : (
              <p className="p-4 text-sm text-gray-500 text-center">
                No upcoming reminders
              </p>
            )}
          </div>
        </div>
        
        {/* Recent People */}
        <div className="card">
          <div className="flex items-center justify-between p-4 border-b border-gray-200">
            <h2 className="font-semibold">Recent People</h2>
            <Link 
              to="/people" 
              className="text-sm text-primary-600 hover:text-primary-700 flex items-center"
            >
              View all
              <ArrowRight className="w-4 h-4 ml-1" />
            </Link>
          </div>
          <div className="divide-y divide-gray-100">
            {recent_people?.length > 0 ? (
              recent_people.map((person) => (
                <PersonCard key={person.id} person={person} />
              ))
            ) : (
              <p className="p-4 text-sm text-gray-500 text-center">
                No people yet. <Link to="/people/new" className="text-primary-600">Add someone</Link>
              </p>
            )}
          </div>
        </div>
      </div>
      
      {/* Favorites */}
      {favorites?.length > 0 && (
        <div className="card">
          <div className="flex items-center justify-between p-4 border-b border-gray-200">
            <h2 className="font-semibold">Favorites</h2>
          </div>
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2 p-2">
            {favorites.map((person) => (
              <PersonCard key={person.id} person={person} />
            ))}
          </div>
        </div>
      )}
    </div>
  );
}
