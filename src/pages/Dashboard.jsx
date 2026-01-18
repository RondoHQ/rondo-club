import { useState, useEffect, lazy, Suspense } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { Users, Building2, Calendar, Star, ArrowRight, Plus, Sparkles, CheckSquare, Square, MessageCircle, Clock, CalendarClock, Settings, ChevronLeft, ChevronRight } from 'lucide-react';
import { useDashboard, useTodos, useUpdateTodo, useDashboardSettings, useUpdateDashboardSettings, DEFAULT_DASHBOARD_CARDS } from '@/hooks/useDashboard';
import { useCreateActivity } from '@/hooks/usePeople';
import { useDateMeetings } from '@/hooks/useMeetings';
import { format, addDays, subDays, isToday } from 'date-fns';
import { APP_NAME } from '@/constants/app';
import { isTodoOverdue, getAwaitingDays, getAwaitingUrgencyClass } from '@/utils/timeline';
import CompleteTodoModal from '@/components/Timeline/CompleteTodoModal';
import QuickActivityModal from '@/components/Timeline/QuickActivityModal';
import DashboardCustomizeModal from '@/components/DashboardCustomizeModal';

const TodoModal = lazy(() => import('@/components/Timeline/TodoModal'));
const MeetingDetailModal = lazy(() => import('@/components/MeetingDetailModal'));

function StatCard({ title, value, icon: Icon, href }) {
  return (
    <Link to={href} className="card p-4 hover:shadow-md dark:hover:shadow-gray-900/50 transition-shadow">
      <div className="flex items-center justify-between">
        <div>
          <p className="text-sm font-medium text-gray-500 dark:text-gray-400">{title}</p>
          <p className="mt-1 text-2xl font-semibold dark:text-gray-50">{value}</p>
        </div>
        <div className="p-2 bg-accent-50 dark:bg-gray-700 rounded-lg">
          <Icon className="w-5 h-5 text-accent-600 dark:text-accent-400" />
        </div>
      </div>
    </Link>
  );
}

function PersonCard({ person, hideStar = false }) {
  return (
    <Link
      to={`/people/${person.id}`}
      className="flex items-center p-3 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
    >
      {person.thumbnail ? (
        <img
          src={person.thumbnail}
          alt={person.name}
          loading="lazy"
          className="w-10 h-10 rounded-full object-cover"
        />
      ) : (
        <div className="w-10 h-10 bg-gray-200 dark:bg-gray-600 rounded-full flex items-center justify-center">
          <span className="text-sm font-medium text-gray-500 dark:text-gray-300">
            {person.first_name?.[0] || '?'}
          </span>
        </div>
      )}
      <div className="ml-3 flex-1 min-w-0">
        <p className="text-sm font-medium text-gray-900 dark:text-gray-50 truncate">{person.name}</p>
        {person.labels?.length > 0 && (
          <p className="text-xs text-gray-500 dark:text-gray-400 truncate">{person.labels.join(', ')}</p>
        )}
      </div>
      {person.is_favorite && !hideStar && (
        <Star className="w-4 h-4 text-yellow-400 fill-current" />
      )}
    </Link>
  );
}

function ReminderCard({ reminder }) {
  const daysUntil = reminder.days_until;

  let urgencyClass = 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300';
  if (daysUntil === 0) {
    urgencyClass = 'bg-green-100 text-green-700 dark:bg-green-500/20 dark:text-green-400';
  } else if (daysUntil <= 3) {
    urgencyClass = 'bg-orange-100 text-orange-700 dark:bg-orange-500/20 dark:text-orange-400';
  } else if (daysUntil <= 7) {
    urgencyClass = 'bg-yellow-100 text-yellow-700 dark:bg-yellow-500/20 dark:text-yellow-400';
  }

  const firstPersonId = reminder.related_people?.[0]?.id;
  const hasRelatedPeople = reminder.related_people?.length > 0;

  const cardContent = (
    <>
      <div className={`px-2 py-1 rounded text-xs font-medium ${urgencyClass}`}>
        {daysUntil === 0 ? 'Today' : `${daysUntil}d`}
      </div>
      <div className="ml-3 flex-1 min-w-0">
        <p className="text-sm font-medium text-gray-900 dark:text-gray-50">{reminder.title}</p>
        <p className="text-xs text-gray-500 dark:text-gray-400">
          {format(new Date(reminder.next_occurrence), 'MMMM d, yyyy')}
        </p>
      </div>
      {hasRelatedPeople && (
        <div className="flex -space-x-2 ml-3 flex-shrink-0">
          {reminder.related_people.slice(0, 3).map((person) => (
            person.thumbnail ? (
              <img
                key={person.id}
                src={person.thumbnail}
                alt={person.name}
                loading="lazy"
                className="w-10 h-10 rounded-full border-2 border-white dark:border-gray-800 object-cover"
              />
            ) : (
              <div
                key={person.id}
                className="w-10 h-10 bg-gray-300 dark:bg-gray-600 rounded-full border-2 border-white dark:border-gray-800 flex items-center justify-center"
              >
                <span className="text-sm dark:text-gray-300">{person.name?.[0]}</span>
              </div>
            )
          ))}
        </div>
      )}
    </>
  );

  // Wrap in Link if there are related people, otherwise just a div
  if (hasRelatedPeople && firstPersonId) {
    return (
      <Link
        to={`/people/${firstPersonId}`}
        className="flex items-center p-3 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
      >
        {cardContent}
      </Link>
    );
  }

  return (
    <div className="flex items-center p-3 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
      {cardContent}
    </div>
  );
}

function TodoCard({ todo, onToggle, onView }) {
  const isOverdue = isTodoOverdue(todo);

  return (
    <button
      type="button"
      onClick={() => onView(todo)}
      className="w-full flex items-start p-3 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors group text-left"
    >
      <button
        type="button"
        onClick={(e) => {
          e.stopPropagation();
          onToggle(todo);
        }}
        className="mt-0.5 mr-3 flex-shrink-0"
        title={todo.status === 'completed' ? 'Reopen' : 'Complete'}
      >
        {todo.status === 'completed' ? (
          <CheckSquare className="w-5 h-5 text-accent-600 dark:text-accent-400" />
        ) : (
          <Square className={`w-5 h-5 ${isOverdue ? 'text-red-600 dark:text-red-300' : 'text-gray-400 dark:text-gray-500'}`} />
        )}
      </button>
      <div className="flex-1 min-w-0">
        <p className={`text-sm font-medium ${todo.status === 'completed' ? 'line-through text-gray-400 dark:text-gray-500' : isOverdue ? 'text-red-600 dark:text-red-300' : 'text-gray-900 dark:text-gray-50'}`}>
          {todo.content}
        </p>
        <div className="flex items-center gap-2 mt-1">
          {todo.person_thumbnail ? (
            <img
              src={todo.person_thumbnail}
              alt={todo.person_name}
              loading="lazy"
              className="w-5 h-5 rounded-full object-cover"
            />
          ) : (
            <div className="w-5 h-5 bg-gray-200 dark:bg-gray-600 rounded-full flex items-center justify-center">
              <span className="text-xs text-gray-500 dark:text-gray-300">{todo.person_name?.[0]}</span>
            </div>
          )}
          <span className="text-xs text-gray-500 dark:text-gray-400 truncate">{todo.person_name}</span>
        </div>
      </div>
      {todo.due_date && todo.status === 'open' && (
        <div className={`ml-3 text-xs text-right flex-shrink-0 ${isOverdue ? 'text-red-600 dark:text-red-300 font-medium' : 'text-gray-500 dark:text-gray-400'}`}>
          <div>{format(new Date(todo.due_date), 'MMM d')}</div>
          {isOverdue && <div className="text-red-600 dark:text-red-300">overdue</div>}
        </div>
      )}
    </button>
  );
}

function AwaitingTodoCard({ todo, onToggle, onView }) {
  const days = getAwaitingDays(todo);
  const urgencyClass = getAwaitingUrgencyClass(days);

  return (
    <button
      type="button"
      onClick={() => onView(todo)}
      className="w-full flex items-start p-3 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors group text-left"
    >
      <button
        type="button"
        onClick={(e) => {
          e.stopPropagation();
          onToggle(todo);
        }}
        className="mt-0.5 mr-3 flex-shrink-0"
        title="Mark as complete"
      >
        <CheckSquare className="w-5 h-5 text-orange-500 dark:text-orange-400" />
      </button>
      <div className="flex-1 min-w-0">
        <p className="text-sm font-medium text-gray-900 dark:text-gray-50 truncate">{todo.content}</p>
        <div className="flex items-center gap-2 mt-1">
          {todo.person_thumbnail ? (
            <img
              src={todo.person_thumbnail}
              alt={todo.person_name}
              loading="lazy"
              className="w-5 h-5 rounded-full object-cover"
            />
          ) : (
            <div className="w-5 h-5 bg-gray-200 dark:bg-gray-600 rounded-full flex items-center justify-center">
              <span className="text-xs text-gray-500 dark:text-gray-300">{todo.person_name?.[0]}</span>
            </div>
          )}
          <span className="text-xs text-gray-500 dark:text-gray-400 truncate">{todo.person_name}</span>
        </div>
      </div>
      <div className={`ml-3 text-xs px-2 py-1 rounded-full flex items-center gap-1 ${urgencyClass}`}>
        <Clock className="w-3 h-3" />
        {days === 0 ? 'Today' : `${days}d`}
      </div>
    </button>
  );
}

function MeetingCard({ meeting, onClick }) {
  // Time-based status detection
  const now = new Date();
  const startTime = new Date(meeting.start_time);
  const endTime = new Date(meeting.end_time);
  const isPast = endTime < now;
  const isNow = startTime <= now && now <= endTime;

  // Format time display in 24h format
  const formattedTime = format(startTime, 'HH:mm');

  // Filter out current user from matched people
  const currentUserPersonId = window.prmConfig?.currentUserPersonId;
  const filteredMatchedPeople = (meeting.matched_people || []).filter(
    person => !currentUserPersonId || person.person_id !== currentUserPersonId
  );

  // Card content shows: time, title, matched people avatars
  const cardContent = (
    <>
      <div className={`text-sm font-medium text-accent-600 dark:text-accent-400 w-14 flex-shrink-0 ${isNow ? 'font-semibold' : ''}`}>
        {meeting.all_day ? 'All day' : formattedTime}
      </div>
      <div className="flex-1 min-w-0">
        <p className="text-sm font-medium text-gray-900 dark:text-gray-50 truncate">{meeting.title}</p>
        {meeting.location && (
          <p className="text-xs text-gray-500 dark:text-gray-400 truncate">{meeting.location}</p>
        )}
      </div>
      {filteredMatchedPeople.length > 0 && (
        <div className="flex -space-x-2 ml-3 flex-shrink-0">
          {filteredMatchedPeople.slice(0, 3).map((person) => (
            person.thumbnail ? (
              <img key={person.person_id} src={person.thumbnail} alt={person.name} loading="lazy"
                className="w-8 h-8 rounded-full border-2 border-white dark:border-gray-800 object-cover" />
            ) : (
              <div key={person.person_id}
                className="w-8 h-8 bg-gray-300 dark:bg-gray-600 rounded-full border-2 border-white dark:border-gray-800 flex items-center justify-center">
                <span className="text-xs dark:text-gray-300">{person.name?.[0]}</span>
              </div>
            )
          ))}
        </div>
      )}
    </>
  );

  // Build conditional classes for time-based styling
  const buttonClasses = [
    'w-full flex items-center p-3 rounded-lg transition-colors text-left',
    isPast ? 'opacity-50' : '',
    isNow ? 'bg-accent-50 dark:bg-accent-900/30 ring-1 ring-accent-200 dark:ring-accent-700' : 'hover:bg-gray-50 dark:hover:bg-gray-700',
  ].filter(Boolean).join(' ');

  // Always clickable button to open modal
  return (
    <button
      type="button"
      onClick={() => onClick(meeting)}
      className={buttonClasses}
    >
      {cardContent}
    </button>
  );
}

function EmptyState() {
  return (
    <div className="card p-12 text-center">
      <div className="flex justify-center mb-4">
        <div className="p-4 bg-accent-50 dark:bg-gray-700 rounded-full">
          <Sparkles className="w-12 h-12 text-accent-600 dark:text-accent-400" />
        </div>
      </div>
      <h2 className="text-2xl font-semibold text-gray-900 dark:text-gray-50 mb-2">Welcome to {APP_NAME}!</h2>
      <p className="text-gray-600 dark:text-gray-300 mb-8 max-w-md mx-auto">
        Get started by adding your first contact, organization, or important date. Your dashboard will populate as you add more information.
      </p>
      <div className="flex flex-col sm:flex-row gap-4 justify-center">
        <Link
          to="/people/new"
          className="inline-flex items-center px-6 py-3 bg-accent-600 text-white rounded-lg hover:bg-accent-700 dark:hover:bg-accent-500 transition-colors"
        >
          <Plus className="w-5 h-5 mr-2" />
          Add Your First Person
        </Link>
        <Link
          to="/companies/new"
          className="inline-flex items-center px-6 py-3 bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-200 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors"
        >
          <Plus className="w-5 h-5 mr-2" />
          Add Your First Organization
        </Link>
      </div>
    </div>
  );
}

export default function Dashboard() {
  const { data, isLoading, error } = useDashboard();
  const { data: openTodos } = useTodos('open');
  const { data: awaitingTodos } = useTodos('awaiting');
  const { data: dashboardSettings } = useDashboardSettings();
  const updateTodo = useUpdateTodo();
  const createActivity = useCreateActivity();
  const updateDashboardSettings = useUpdateDashboardSettings();

  // Date navigation state for meetings widget
  const [selectedDate, setSelectedDate] = useState(new Date());
  const { data: meetingsData } = useDateMeetings(selectedDate);

  // Meetings data for selected date
  const dateMeetings = meetingsData?.meetings || [];
  const hasCalendarConnections = meetingsData?.has_connections ?? false;

  // Date navigation handlers
  const handlePrevDay = () => setSelectedDate(d => subDays(d, 1));
  const handleNextDay = () => setSelectedDate(d => addDays(d, 1));
  const handleToday = () => setSelectedDate(new Date());

  // State for complete todo flow
  const [todoToComplete, setTodoToComplete] = useState(null);
  const [showCompleteModal, setShowCompleteModal] = useState(false);
  const [showActivityModal, setShowActivityModal] = useState(false);
  const [activityInitialData, setActivityInitialData] = useState(null);

  // State for viewing/editing todos
  const [todoToView, setTodoToView] = useState(null);
  const [showTodoModal, setShowTodoModal] = useState(false);

  // State for meeting detail modal
  const [selectedMeeting, setSelectedMeeting] = useState(null);
  const [showMeetingModal, setShowMeetingModal] = useState(false);

  // URL params for customize modal
  const [searchParams, setSearchParams] = useSearchParams();
  const showCustomizeModal = searchParams.get('customize') === 'true';

  const closeCustomizeModal = () => {
    setSearchParams({});
  };

  // Handle saving dashboard settings
  const handleSaveSettings = (settings) => {
    updateDashboardSettings.mutate(settings, {
      onSuccess: () => closeCustomizeModal(),
    });
  };

  // Get visible cards in order from settings
  const visibleCards = dashboardSettings?.visible_cards || DEFAULT_DASHBOARD_CARDS;
  const cardOrder = dashboardSettings?.card_order || DEFAULT_DASHBOARD_CARDS;

  const handleToggleTodo = (todo) => {
    // If it's an open todo, show the complete modal with options
    if (todo.status === 'open') {
      setTodoToComplete(todo);
      setShowCompleteModal(true);
      return;
    }

    // If awaiting, mark as completed directly
    if (todo.status === 'awaiting') {
      updateTodo.mutate({
        todoId: todo.id,
        data: { status: 'completed' },
      });
      return;
    }

    // If completed, reopen
    if (todo.status === 'completed') {
      updateTodo.mutate({
        todoId: todo.id,
        data: { status: 'open' },
      });
    }
  };

  const handleMarkAwaiting = () => {
    if (!todoToComplete) return;

    updateTodo.mutate({
      todoId: todoToComplete.id,
      data: { status: 'awaiting' },
    });

    setShowCompleteModal(false);
    setTodoToComplete(null);
  };

  const handleJustComplete = () => {
    if (!todoToComplete) return;

    updateTodo.mutate({
      todoId: todoToComplete.id,
      data: { status: 'completed' },
    });

    setShowCompleteModal(false);
    setTodoToComplete(null);
  };

  const handleCompleteAsActivity = () => {
    if (!todoToComplete) return;

    // Prepare initial data for activity modal
    const today = new Date().toISOString().split('T')[0];
    setActivityInitialData({
      content: todoToComplete.content,
      activity_date: today,
      activity_type: 'note',
      participants: [],
    });

    setShowCompleteModal(false);
    setShowActivityModal(true);
  };

  const handleCreateActivity = async (data) => {
    if (!todoToComplete) return;

    try {
      // Create the activity
      await createActivity.mutateAsync({
        personId: todoToComplete.person_id,
        data
      });

      // Mark the todo as complete
      await updateTodo.mutateAsync({
        todoId: todoToComplete.id,
        data: { status: 'completed' },
      });

      setShowActivityModal(false);
      setTodoToComplete(null);
      setActivityInitialData(null);
    } catch {
      alert('Failed to create activity. Please try again.');
    }
  };

  const handleViewTodo = (todo) => {
    setTodoToView(todo);
    setShowTodoModal(true);
  };

  const handleUpdateTodo = (data) => {
    if (!todoToView) return;
    updateTodo.mutate({
      todoId: todoToView.id,
      data,
    }, {
      onSuccess: () => {
        setShowTodoModal(false);
        setTodoToView(null);
      }
    });
  };

  if (isLoading) {
    return (
      <div className="space-y-6">
        {/* Stats skeleton - full width grid */}
        <div className="grid grid-cols-2 lg:grid-cols-5 gap-4">
          {[...Array(5)].map((_, i) => (
            <div key={i} className="card p-4">
              <div className="animate-pulse">
                <div className="h-4 bg-gray-200 dark:bg-gray-700 rounded w-20 mb-2"></div>
                <div className="h-6 bg-gray-200 dark:bg-gray-700 rounded w-12"></div>
              </div>
            </div>
          ))}
        </div>

        {/* Widget skeletons - 3 column grid */}
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
          {[...Array(6)].map((_, i) => (
            <div key={i} className="card">
              <div className="flex items-center justify-between p-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50">
                <div className="h-5 bg-gray-200 dark:bg-gray-700 rounded w-32 animate-pulse"></div>
              </div>
              <div className="h-[32vh] overflow-y-auto">
                {[...Array(3)].map((_, j) => (
                  <div key={j} className="p-3 border-b border-gray-100 dark:border-gray-700 last:border-0">
                    <div className="animate-pulse flex items-center">
                      <div className="w-10 h-10 bg-gray-200 dark:bg-gray-700 rounded-full"></div>
                      <div className="ml-3 flex-1">
                        <div className="h-4 bg-gray-200 dark:bg-gray-700 rounded w-3/4 mb-2"></div>
                        <div className="h-3 bg-gray-200 dark:bg-gray-700 rounded w-1/2"></div>
                      </div>
                    </div>
                  </div>
                ))}
              </div>
            </div>
          ))}
        </div>
      </div>
    );
  }
  
  if (error) {
    // Check if it's a network/API error vs empty state
    const isNetworkError = error?.response?.status >= 500 || !error?.response;

    return (
      <div className="card p-8 text-center">
        <div className="text-red-600 dark:text-red-400 mb-2">
          {isNetworkError ? (
            <>
              <p className="font-medium mb-1">Failed to load dashboard data</p>
              <p className="text-sm text-gray-600 dark:text-gray-300">
                Please check your connection and try refreshing the page.
              </p>
            </>
          ) : (
            <>
              <p className="font-medium mb-1">Unable to load dashboard</p>
              <p className="text-sm text-gray-600 dark:text-gray-300">
                {error?.response?.data?.message || 'An error occurred while loading your data.'}
              </p>
            </>
          )}
        </div>
      </div>
    );
  }
  
  const { stats, recent_people, upcoming_reminders, favorites, recently_contacted } = data || {};
  const totalItems = (stats?.total_people || 0) + (stats?.total_companies || 0) + (stats?.total_dates || 0);
  const isEmpty = totalItems === 0;
  
  if (isEmpty) {
    return (
      <div className="space-y-6">
        {/* Stats - still show them even when empty */}
        <div className="grid grid-cols-2 lg:grid-cols-5 gap-4">
          <StatCard
            title="Total people"
            value={0}
            icon={Users}
            href="/people"
          />
          <StatCard
            title="Organizations"
            value={0}
            icon={Building2}
            href="/companies"
          />
          <StatCard
            title="Events"
            value={0}
            icon={Calendar}
            href="/dates"
          />
          <StatCard
            title="Open todos"
            value={0}
            icon={CheckSquare}
            href="/todos"
          />
          <StatCard
            title="Awaiting"
            value={0}
            icon={Clock}
            href="/todos?status=awaiting"
          />
        </div>

        {/* Empty State */}
        <EmptyState />
      </div>
    );
  }
  
  // Limit todos to 5 for dashboard
  const dashboardTodos = openTodos?.slice(0, 5) || [];
  const dashboardAwaitingTodos = awaitingTodos?.slice(0, 5) || [];

  // Card renderers - each returns JSX for a single card
  const cardRenderers = {
    'stats': () => (
      <div key="stats" className="grid grid-cols-2 lg:grid-cols-5 gap-4">
        <StatCard title="Total people" value={stats?.total_people || 0} icon={Users} href="/people" />
        <StatCard title="Organizations" value={stats?.total_companies || 0} icon={Building2} href="/companies" />
        <StatCard title="Events" value={stats?.total_dates || 0} icon={Calendar} href="/dates" />
        <StatCard title="Open todos" value={stats?.open_todos_count || 0} icon={CheckSquare} href="/todos" />
        <StatCard title="Awaiting" value={stats?.awaiting_todos_count || 0} icon={Clock} href="/todos?status=awaiting" />
      </div>
    ),
    'reminders': () => (
      <div key="reminders" className="card">
        <div className="flex items-center justify-between p-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50">
          <h2 className="font-semibold flex items-center dark:text-gray-50">
            <Calendar className="w-5 h-5 mr-2 text-gray-500 dark:text-gray-400" />
            Upcoming reminders {upcoming_reminders?.length > 0 && <span className="ml-1 text-gray-400 dark:text-gray-500 font-normal">({upcoming_reminders.length})</span>}
          </h2>
          <Link to="/dates" className="text-sm text-accent-600 hover:text-accent-700 dark:text-accent-400 dark:hover:text-accent-300 flex items-center">
            View all <ArrowRight className="w-4 h-4 ml-1" />
          </Link>
        </div>
        <div className="divide-y divide-gray-100 dark:divide-gray-700 h-[32vh] overflow-y-auto">
          {upcoming_reminders?.length > 0 ? (
            upcoming_reminders.map((reminder) => <ReminderCard key={reminder.id} reminder={reminder} />)
          ) : (
            <p className="p-4 text-sm text-gray-500 dark:text-gray-400 text-center">No upcoming reminders</p>
          )}
        </div>
      </div>
    ),
    'todos': () => (
      <div key="todos" className="card">
        <div className="flex items-center justify-between p-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50">
          <h2 className="font-semibold flex items-center dark:text-gray-50">
            <CheckSquare className="w-5 h-5 mr-2 text-gray-500 dark:text-gray-400" />
            Open todos {openTodos?.length > 0 && <span className="ml-1 text-gray-400 dark:text-gray-500 font-normal">({openTodos.length})</span>}
          </h2>
          <Link to="/todos" className="text-sm text-accent-600 hover:text-accent-700 dark:text-accent-400 dark:hover:text-accent-300 flex items-center">
            View all <ArrowRight className="w-4 h-4 ml-1" />
          </Link>
        </div>
        <div className="divide-y divide-gray-100 dark:divide-gray-700 h-[32vh] overflow-y-auto">
          {dashboardTodos.length > 0 ? (
            dashboardTodos.map((todo) => <TodoCard key={todo.id} todo={todo} onToggle={handleToggleTodo} onView={handleViewTodo} />)
          ) : (
            <p className="p-4 text-sm text-gray-500 dark:text-gray-400 text-center">No open todos</p>
          )}
        </div>
      </div>
    ),
    'awaiting': () => (
      <div key="awaiting" className="card">
        <div className="flex items-center justify-between p-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50">
          <h2 className="font-semibold flex items-center dark:text-gray-50">
            <Clock className="w-5 h-5 mr-2 text-gray-500 dark:text-gray-400" />
            Awaiting response {awaitingTodos?.length > 0 && <span className="ml-1 text-gray-400 dark:text-gray-500 font-normal">({awaitingTodos.length})</span>}
          </h2>
          <Link to="/todos?status=awaiting" className="text-sm text-accent-600 hover:text-accent-700 dark:text-accent-400 dark:hover:text-accent-300 flex items-center">
            View all <ArrowRight className="w-4 h-4 ml-1" />
          </Link>
        </div>
        <div className="divide-y divide-gray-100 dark:divide-gray-700 h-[32vh] overflow-y-auto">
          {dashboardAwaitingTodos.length > 0 ? (
            dashboardAwaitingTodos.map((todo) => <AwaitingTodoCard key={todo.id} todo={todo} onToggle={handleToggleTodo} onView={handleViewTodo} />)
          ) : (
            <p className="p-4 text-sm text-gray-500 dark:text-gray-400 text-center">No awaiting responses</p>
          )}
        </div>
      </div>
    ),
    'meetings': () => hasCalendarConnections ? (
      <div key="meetings" className="card">
        <div className="flex items-center justify-between p-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50">
          <h2 className="font-semibold flex items-center dark:text-gray-50">
            <CalendarClock className="w-5 h-5 mr-2 text-gray-500 dark:text-gray-400" />
            {isToday(selectedDate) ? "Today's meetings" : format(selectedDate, 'EEEE, MMMM d')} {dateMeetings.length > 0 && <span className="ml-1 text-gray-400 dark:text-gray-500 font-normal">({dateMeetings.length})</span>}
          </h2>
          <div className="flex items-center gap-1">
            {!isToday(selectedDate) && (
              <button
                onClick={handleToday}
                className="px-2 py-1 text-xs font-medium text-accent-600 dark:text-accent-400 hover:bg-accent-50 dark:hover:bg-accent-800 dark:hover:text-accent-100 rounded transition-colors"
              >
                Today
              </button>
            )}
            <button
              onClick={handlePrevDay}
              className="p-1.5 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded transition-colors"
              aria-label="Previous day"
            >
              <ChevronLeft className="w-4 h-4" />
            </button>
            <button
              onClick={handleNextDay}
              className="p-1.5 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded transition-colors"
              aria-label="Next day"
            >
              <ChevronRight className="w-4 h-4" />
            </button>
          </div>
        </div>
        <div className="divide-y divide-gray-100 dark:divide-gray-700 h-[32vh] overflow-y-auto">
          {dateMeetings.length > 0 ? (
            dateMeetings.map((meeting) => (
              <MeetingCard
                key={meeting.id}
                meeting={meeting}
                onClick={(m) => {
                  setSelectedMeeting(m);
                  setShowMeetingModal(true);
                }}
              />
            ))
          ) : (
            <p className="p-4 text-sm text-gray-500 dark:text-gray-400 text-center">
              {isToday(selectedDate)
                ? 'No meetings scheduled for today'
                : `No meetings on ${format(selectedDate, 'MMMM d')}`}
            </p>
          )}
        </div>
      </div>
    ) : null,
    'recent-contacted': () => (
      <div key="recent-contacted" className="card">
        <div className="flex items-center justify-between p-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50">
          <h2 className="font-semibold flex items-center dark:text-gray-50">
            <MessageCircle className="w-5 h-5 mr-2 text-gray-500 dark:text-gray-400" />
            Recently contacted
          </h2>
        </div>
        <div className="divide-y divide-gray-100 dark:divide-gray-700 h-[32vh] overflow-y-auto">
          {recently_contacted?.length > 0 ? (
            recently_contacted.map((person) => <PersonCard key={person.id} person={person} />)
          ) : (
            <p className="p-4 text-sm text-gray-500 dark:text-gray-400 text-center">No recent activities yet</p>
          )}
        </div>
      </div>
    ),
    'recent-edited': () => (
      <div key="recent-edited" className="card">
        <div className="flex items-center justify-between p-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50">
          <h2 className="font-semibold flex items-center dark:text-gray-50">
            <Users className="w-5 h-5 mr-2 text-gray-500 dark:text-gray-400" />
            Recently edited
          </h2>
          <Link to="/people" className="text-sm text-accent-600 hover:text-accent-700 dark:text-accent-400 dark:hover:text-accent-300 flex items-center">
            View all <ArrowRight className="w-4 h-4 ml-1" />
          </Link>
        </div>
        <div className="divide-y divide-gray-100 dark:divide-gray-700 h-[32vh] overflow-y-auto">
          {recent_people?.length > 0 ? (
            recent_people.map((person) => <PersonCard key={person.id} person={person} />)
          ) : (
            <p className="p-4 text-sm text-gray-500 dark:text-gray-400 text-center">
              No people yet. <Link to="/people/new" className="text-accent-600 dark:text-accent-400">Add someone</Link>
            </p>
          )}
        </div>
      </div>
    ),
    'favorites': () => (
      <div key="favorites" className="card">
        <div className="flex items-center justify-between p-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50">
          <h2 className="font-semibold flex items-center dark:text-gray-50">
            <Star className="w-5 h-5 mr-2 text-gray-500 dark:text-gray-400 fill-current" />
            Favorites {favorites?.length > 0 && <span className="ml-1 text-gray-400 dark:text-gray-500 font-normal">({favorites.length})</span>}
          </h2>
        </div>
        <div className="divide-y divide-gray-100 dark:divide-gray-700 h-[32vh] overflow-y-auto">
          {favorites?.length > 0 ? (
            favorites.slice(0, 5).map((person) => <PersonCard key={person.id} person={person} hideStar={true} />)
          ) : (
            <p className="p-4 text-sm text-gray-500 dark:text-gray-400 text-center">No favorites yet</p>
          )}
        </div>
      </div>
    ),
  };

  // Filter cards to show based on visibility settings, maintaining order
  const orderedVisibleCards = cardOrder.filter((cardId) => visibleCards.includes(cardId));

  // Group cards into segments for rendering - stats renders full-width, others in a grid
  // This allows stats to appear in any position while maintaining its full-width layout
  const renderCardSegments = () => {
    const segments = [];
    let currentGroup = [];

    orderedVisibleCards.forEach((cardId) => {
      if (cardId === 'stats') {
        // If there are cards before stats, render them as a grid
        if (currentGroup.length > 0) {
          segments.push(
            <div key={`grid-${segments.length}`} className="grid grid-cols-1 lg:grid-cols-3 gap-6">
              {currentGroup.map((id) => cardRenderers[id]?.())}
            </div>
          );
          currentGroup = [];
        }
        // Render stats full-width
        segments.push(cardRenderers['stats']());
      } else {
        currentGroup.push(cardId);
      }
    });

    // Render any remaining cards after stats
    if (currentGroup.length > 0) {
      segments.push(
        <div key={`grid-${segments.length}`} className="grid grid-cols-1 lg:grid-cols-3 gap-6">
          {currentGroup.map((id) => cardRenderers[id]?.())}
        </div>
      );
    }

    return segments;
  };

  return (
    <div className="space-y-6 -mb-4 lg:-mb-6">
      {/* Render cards in segments respecting order */}
      {renderCardSegments()}

      {/* Complete Todo Modal */}
      <CompleteTodoModal
        isOpen={showCompleteModal}
        onClose={() => {
          setShowCompleteModal(false);
          setTodoToComplete(null);
        }}
        todo={todoToComplete}
        onAwaiting={handleMarkAwaiting}
        onComplete={handleJustComplete}
        onCompleteAsActivity={handleCompleteAsActivity}
      />
      
      {/* Activity Modal (for converting todo to activity) */}
      <QuickActivityModal
        isOpen={showActivityModal}
        onClose={() => {
          setShowActivityModal(false);
          setTodoToComplete(null);
          setActivityInitialData(null);
        }}
        onSubmit={handleCreateActivity}
        isLoading={createActivity.isPending}
        personId={todoToComplete?.person_id}
        initialData={activityInitialData}
      />

      {/* Todo Modal (for viewing/editing todos) */}
      <Suspense fallback={null}>
        <TodoModal
          isOpen={showTodoModal}
          onClose={() => {
            setShowTodoModal(false);
            setTodoToView(null);
          }}
          onSubmit={handleUpdateTodo}
          isLoading={updateTodo.isPending}
          todo={todoToView}
        />

        {/* Meeting Detail Modal */}
        <MeetingDetailModal
          isOpen={showMeetingModal}
          onClose={() => {
            setShowMeetingModal(false);
            setSelectedMeeting(null);
          }}
          meeting={selectedMeeting}
        />
      </Suspense>

      {/* Dashboard Customize Modal */}
      <DashboardCustomizeModal
        isOpen={showCustomizeModal}
        onClose={closeCustomizeModal}
        settings={dashboardSettings}
        onSave={handleSaveSettings}
        isSaving={updateDashboardSettings.isPending}
      />
    </div>
  );
}
