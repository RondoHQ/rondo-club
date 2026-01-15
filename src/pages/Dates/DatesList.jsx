import { useState } from 'react';
import { Link } from 'react-router-dom';
import { Plus, Calendar, Gift, Heart, Star } from 'lucide-react';
import { useReminders } from '@/hooks/useDashboard';
import { usePeople } from '@/hooks/usePeople';
import { useCreateDate } from '@/hooks/useDates';
import { format } from 'date-fns';
import ImportantDateModal from '@/components/ImportantDateModal';

const typeIcons = {
  birthday: Gift,
  wedding: Heart,
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
            loading="lazy"
            className="w-10 h-10 rounded-full object-cover flex-shrink-0"
          />
        ) : (
          <div className="w-10 h-10 rounded-full bg-gray-200 dark:bg-gray-600 flex items-center justify-center flex-shrink-0">
            <span className="text-sm font-medium text-gray-500 dark:text-gray-300">{person.name?.[0]}</span>
          </div>
        )}
        <span className="font-medium truncate">{person.name}</span>
        <span className="text-sm text-gray-500 dark:text-gray-400 capitalize flex-shrink-0">{dateType}</span>
      </Link>
    </div>
  );
}

function DateCard({ dates }) {
  // dates is an array of dates all on the same day
  const firstDate = dates[0];
  const daysUntil = firstDate.days_until;

  // Only show green for today, gray for all other dates
  const urgencyClass = daysUntil === 0 ? 'bg-green-100 dark:bg-green-500/20 text-green-700 dark:text-green-400' : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300';

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
    <div className="card p-4 break-inside-avoid mb-4">
      <div className="flex items-start gap-4">
        <div className={`w-14 h-14 rounded-lg ${urgencyClass} flex items-center justify-center flex-shrink-0`}>
          <span className="text-2xl font-bold leading-none">
            {format(new Date(firstDate.next_occurrence), 'd')}
          </span>
        </div>
        <div className="flex-1 min-w-0 divide-y divide-gray-100 dark:divide-gray-700">
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
  const [showDateModal, setShowDateModal] = useState(false);
  const [isCreatingDate, setIsCreatingDate] = useState(false);

  const { data: dates, isLoading, error } = useReminders(365);
  const { data: allPeople = [], isLoading: isPeopleLoading } = usePeople();

  // Create date mutation
  const createDateMutation = useCreateDate({
    onSuccess: () => setShowDateModal(false),
  });

  const handleCreateDate = async (data) => {
    setIsCreatingDate(true);
    try {
      await createDateMutation.mutateAsync(data);
    } finally {
      setIsCreatingDate(false);
    }
  };

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
        <p className="text-gray-600 dark:text-gray-400">
          {dates?.length || 0} upcoming dates
        </p>
        <button onClick={() => setShowDateModal(true)} className="btn-primary">
          <Plus className="w-4 h-4 md:mr-2" />
          <span className="hidden md:inline">Add date</span>
        </button>
      </div>
      
      {/* Loading */}
      {isLoading && (
        <div className="flex items-center justify-center h-64">
          <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-accent-600 dark:border-accent-400"></div>
        </div>
      )}
      
      {/* Error */}
      {error && (
        <div className="card p-6 text-center">
          <p className="text-red-600 dark:text-red-400">Failed to load dates.</p>
        </div>
      )}
      
      {/* Empty */}
      {!isLoading && !error && dates?.length === 0 && (
        <div className="card p-12 text-center">
          <Calendar className="w-12 h-12 text-gray-300 dark:text-gray-600 mx-auto mb-4" />
          <h3 className="text-lg font-medium mb-1">No important dates</h3>
          <p className="text-gray-500 dark:text-gray-400 mb-4">Add birthdays, anniversaries, and more.</p>
          <button onClick={() => setShowDateModal(true)} className="btn-primary">
            <Plus className="w-4 h-4 md:mr-2" />
            <span className="hidden md:inline">Add date</span>
          </button>
        </div>
      )}
      
      {/* Grouped by month */}
      {Object.entries(groupedDates).map(([month, dayGroups]) => (
        <div key={month}>
          <h2 className="text-2xl font-semibold text-gray-700 dark:text-gray-200 mb-4">{month}</h2>
          <div className="columns-1 md:columns-2 lg:columns-3 gap-4">
            {dayGroups.map((dayDates) => (
              <DateCard key={dayDates[0].next_occurrence} dates={dayDates} />
            ))}
          </div>
        </div>
      ))}
      
      {/* Date Modal */}
      <ImportantDateModal
        isOpen={showDateModal}
        onClose={() => setShowDateModal(false)}
        onSubmit={handleCreateDate}
        isLoading={isCreatingDate}
        allPeople={allPeople}
        isPeopleLoading={isPeopleLoading}
      />
    </div>
  );
}
