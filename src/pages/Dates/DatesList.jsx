import { Link } from 'react-router-dom';
import { Plus, Calendar, Gift, Heart, Star } from 'lucide-react';
import { useReminders } from '@/hooks/useDashboard';
import { format } from 'date-fns';

const typeIcons = {
  birthday: Gift,
  anniversary: Heart,
  memorial: Star,
  default: Calendar,
};

function DateCard({ date }) {
  const Icon = typeIcons[date.date_type?.[0]?.toLowerCase()] || typeIcons.default;
  const daysUntil = date.days_until;
  
  let urgencyClass = 'bg-gray-100 text-gray-600';
  if (daysUntil === 0) urgencyClass = 'bg-red-100 text-red-700';
  else if (daysUntil <= 3) urgencyClass = 'bg-orange-100 text-orange-700';
  else if (daysUntil <= 7) urgencyClass = 'bg-yellow-100 text-yellow-700';
  else if (daysUntil <= 14) urgencyClass = 'bg-blue-100 text-blue-700';
  
  return (
    <div className="card p-4">
      <div className="flex items-start gap-3">
        <div className={`p-2 rounded-lg ${urgencyClass}`}>
          <Icon className="w-5 h-5" />
        </div>
        <div className="flex-1 min-w-0">
          <h3 className="font-medium truncate">{date.title}</h3>
          <p className="text-sm text-gray-500">
            {format(new Date(date.next_occurrence), 'MMMM d, yyyy')}
          </p>
          <div className="flex items-center gap-2 mt-2">
            <span className={`text-xs px-2 py-0.5 rounded-full ${urgencyClass}`}>
              {daysUntil === 0 ? 'Today!' : `${daysUntil} days`}
            </span>
            {date.is_recurring && (
              <span className="text-xs text-gray-500">Yearly</span>
            )}
          </div>
          {date.related_people?.length > 0 && (
            <div className="flex -space-x-2 mt-2">
              {date.related_people.slice(0, 4).map((person) => (
                <Link
                  key={person.id}
                  to={`/people/${person.id}`}
                  className="w-6 h-6 rounded-full bg-gray-200 border-2 border-white flex items-center justify-center text-xs"
                  title={person.name}
                >
                  {person.thumbnail ? (
                    <img src={person.thumbnail} alt="" className="w-full h-full rounded-full object-cover" />
                  ) : (
                    person.name?.[0]
                  )}
                </Link>
              ))}
            </div>
          )}
        </div>
        <Link to={`/dates/${date.id}/edit`} className="text-gray-400 hover:text-gray-600">
          Edit
        </Link>
      </div>
    </div>
  );
}

export default function DatesList() {
  const { data: dates, isLoading, error } = useReminders(365);
  
  // Group by month
  const groupedDates = dates?.reduce((acc, date) => {
    const month = format(new Date(date.next_occurrence), 'MMMM yyyy');
    if (!acc[month]) acc[month] = [];
    acc[month].push(date);
    return acc;
  }, {}) || {};
  
  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <p className="text-gray-600">
          {dates?.length || 0} upcoming dates
        </p>
        <Link to="/dates/new" className="btn-primary">
          <Plus className="w-4 h-4 mr-2" />
          Add Date
        </Link>
      </div>
      
      {/* Loading */}
      {isLoading && (
        <div className="flex items-center justify-center h-64">
          <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600"></div>
        </div>
      )}
      
      {/* Error */}
      {error && (
        <div className="card p-6 text-center">
          <p className="text-red-600">Failed to load dates.</p>
        </div>
      )}
      
      {/* Empty */}
      {!isLoading && !error && dates?.length === 0 && (
        <div className="card p-12 text-center">
          <Calendar className="w-12 h-12 text-gray-300 mx-auto mb-4" />
          <h3 className="text-lg font-medium mb-1">No important dates</h3>
          <p className="text-gray-500 mb-4">Add birthdays, anniversaries, and more.</p>
          <Link to="/dates/new" className="btn-primary">
            <Plus className="w-4 h-4 mr-2" />
            Add Date
          </Link>
        </div>
      )}
      
      {/* Grouped by month */}
      {Object.entries(groupedDates).map(([month, monthDates]) => (
        <div key={month}>
          <h2 className="text-sm font-semibold text-gray-500 mb-3">{month}</h2>
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            {monthDates.map((date) => (
              <DateCard key={date.id} date={date} />
            ))}
          </div>
        </div>
      ))}
    </div>
  );
}
