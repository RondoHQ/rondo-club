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

function PersonDateEntry({ person, dateType }) {
  return (
    <div className="flex items-center gap-3 py-2">
      <Link to={`/people/${person.id}`} className="flex items-center gap-3 flex-1 min-w-0 hover:opacity-80">
        {person.thumbnail ? (
          <img
            src={person.thumbnail}
            alt={person.name}
            className="w-10 h-10 rounded-full object-cover flex-shrink-0"
          />
        ) : (
          <div className="w-10 h-10 rounded-full bg-gray-200 flex items-center justify-center flex-shrink-0">
            <span className="text-sm font-medium text-gray-500">{person.name?.[0]}</span>
          </div>
        )}
        <span className="font-medium truncate">{person.name}</span>
        <span className="text-sm text-gray-500 capitalize flex-shrink-0">{dateType}</span>
      </Link>
    </div>
  );
}

function DateCard({ dates }) {
  // dates is an array of dates all on the same day
  const firstDate = dates[0];
  const daysUntil = firstDate.days_until;

  let urgencyClass = 'bg-gray-100 text-gray-600';
  if (daysUntil === 0) urgencyClass = 'bg-red-100 text-red-700';
  else if (daysUntil <= 3) urgencyClass = 'bg-orange-100 text-orange-700';
  else if (daysUntil <= 7) urgencyClass = 'bg-yellow-100 text-yellow-700';
  else if (daysUntil <= 14) urgencyClass = 'bg-blue-100 text-blue-700';

  // Collect all people from all dates on this day
  const allPeople = [];
  dates.forEach(date => {
    const dateType = date.date_type?.[0] || 'Date';
    if (date.related_people?.length > 0) {
      date.related_people.forEach(person => {
        allPeople.push({ person, dateType });
      });
    } else {
      // If no related people, show the date title as a fallback
      allPeople.push({
        person: { id: null, name: date.title, thumbnail: null },
        dateType
      });
    }
  });

  return (
    <div className="card p-4">
      <div className="flex items-start gap-4">
        <div className={`w-14 h-14 rounded-lg ${urgencyClass} flex flex-col items-center justify-center flex-shrink-0`}>
          <span className="text-2xl font-bold leading-none">
            {format(new Date(firstDate.next_occurrence), 'd')}
          </span>
          <span className={`text-xs px-2 py-0.5 rounded-full mt-1`}>
            {daysUntil === 0 ? 'Today!' : `${daysUntil}d`}
          </span>
        </div>
        <div className="flex-1 min-w-0 divide-y divide-gray-100">
          {allPeople.map(({ person, dateType }, index) => (
            <PersonDateEntry
              key={person.id ? `${person.id}-${dateType}` : `${index}-${dateType}`}
              person={person}
              dateType={dateType}
            />
          ))}
        </div>
      </div>
    </div>
  );
}

export default function DatesList() {
  const { data: dates, isLoading, error } = useReminders(365);

  // First group by exact date (day), then by month for display
  const groupedByDay = dates?.reduce((acc, date) => {
    const dayKey = format(new Date(date.next_occurrence), 'yyyy-MM-dd');
    if (!acc[dayKey]) acc[dayKey] = [];
    acc[dayKey].push(date);
    return acc;
  }, {}) || {};

  // Now group the day groups by month
  const groupedDates = Object.entries(groupedByDay).reduce((acc, [dayKey, dayDates]) => {
    const month = format(new Date(dayKey), 'MMMM yyyy');
    if (!acc[month]) acc[month] = [];
    acc[month].push(dayDates); // Push the array of dates for this day
    return acc;
  }, {});
  
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
      {Object.entries(groupedDates).map(([month, dayGroups]) => (
        <div key={month}>
          <h2 className="text-sm font-semibold text-gray-500 mb-3">{month}</h2>
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            {dayGroups.map((dayDates) => (
              <DateCard key={dayDates[0].next_occurrence} dates={dayDates} />
            ))}
          </div>
        </div>
      ))}
    </div>
  );
}
