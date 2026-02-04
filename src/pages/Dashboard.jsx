import { useState, useEffect, useRef, useMemo, lazy, Suspense } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import {
  Users,
  Building2,
  Calendar,
  Plus,
  Sparkles,
  CheckSquare,
  Square,
  MessageCircle,
  Clock,
  CalendarClock,
  ChevronLeft,
  ChevronRight,
  FileCheck,
  Gavel,
} from 'lucide-react';
import { useQueryClient } from '@tanstack/react-query';
import {
  useDashboard,
  useTodos,
  useDashboardSettings,
  useUpdateDashboardSettings,
  DEFAULT_DASHBOARD_CARDS,
} from '@/hooks/useDashboard.js';
import { useDateMeetings } from '@/hooks/useMeetings.js';
import { useTodoCompletion } from '@/hooks/useTodoCompletion.js';
import { format, addDays, subDays, isToday } from '@/utils/dateFormat.js';
import { APP_NAME } from '@/constants/app.js';
import {
  isTodoOverdue,
  getAwaitingDays,
  getAwaitingUrgencyClass,
  getReminderUrgencyClass,
} from '@/utils/timeline.js';
import PersonAvatar from '@/components/PersonAvatar.jsx';
import DashboardCard from '@/components/DashboardCard.jsx';
import { useVOGCount } from '@/hooks/useVOGCount';
import { useDisciplineCasesCount } from '@/hooks/useDisciplineCases';
import { useCurrentUser } from '@/hooks/useCurrentUser';
import CompleteTodoModal from '@/components/Timeline/CompleteTodoModal.jsx';
import QuickActivityModal from '@/components/Timeline/QuickActivityModal.jsx';
import DashboardCustomizeModal from '@/components/DashboardCustomizeModal.jsx';
import PullToRefreshWrapper from '@/components/PullToRefreshWrapper.jsx';

const TodoModal = lazy(() => import('@/components/Timeline/TodoModal.jsx'));
const MeetingDetailModal = lazy(() => import('@/components/MeetingDetailModal.jsx'));

/**
 * Statistics card for dashboard header.
 */
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

/**
 * Person card with avatar, name, and optional labels.
 */
function PersonCard({ person }) {
  return (
    <Link
      to={`/people/${person.id}`}
      className="flex items-center p-3 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
    >
      <PersonAvatar
        thumbnail={person.thumbnail}
        name={person.name}
        firstName={person.first_name}
        size="lg"
      />
      <div className="ml-3 flex-1 min-w-0">
        <p className="text-sm font-medium text-gray-900 dark:text-gray-50 truncate">{person.name}</p>
        {person.labels?.length > 0 && (
          <p className="text-xs text-gray-500 dark:text-gray-400 truncate">{person.labels.join(', ')}</p>
        )}
      </div>
    </Link>
  );
}

/**
 * Reminder card showing upcoming important dates.
 */
function ReminderCard({ reminder }) {
  const daysUntil = reminder.days_until;
  const urgencyClass = getReminderUrgencyClass(daysUntil);
  const firstPersonId = reminder.related_people?.[0]?.id;
  const hasRelatedPeople = reminder.related_people?.length > 0;

  const cardContent = (
    <>
      <div className={`px-2 py-1 rounded text-xs font-medium ${urgencyClass}`}>
        {daysUntil === 0 ? 'Vandaag' : `${daysUntil}d`}
      </div>
      <div className="ml-3 flex-1 min-w-0">
        <p className="text-sm font-medium text-gray-900 dark:text-gray-50">{reminder.title}</p>
        <p className="text-xs text-gray-500 dark:text-gray-400">
          {format(new Date(reminder.next_occurrence), 'd MMMM yyyy')}
        </p>
      </div>
      {hasRelatedPeople && (
        <div className="flex -space-x-2 ml-3 flex-shrink-0">
          {reminder.related_people.slice(0, 3).map((person) => (
            <PersonAvatar
              key={person.id}
              thumbnail={person.thumbnail}
              name={person.name}
              size="lg"
              borderClassName="border-2 border-white dark:border-gray-800"
            />
          ))}
        </div>
      )}
    </>
  );

  const className = "flex items-center p-3 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors";

  if (hasRelatedPeople && firstPersonId) {
    return <Link to={`/people/${firstPersonId}`} className={className}>{cardContent}</Link>;
  }

  return <div className={className}>{cardContent}</div>;
}

/**
 * Todo card for open tasks.
 */
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
        title={todo.status === 'completed' ? 'Heropenen' : 'Voltooien'}
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
          <PersonAvatar
            thumbnail={todo.person_thumbnail}
            name={todo.person_name}
            size="xs"
          />
          <span className="text-xs text-gray-500 dark:text-gray-400 truncate">{todo.person_name}</span>
        </div>
      </div>
      {todo.due_date && todo.status === 'open' && (
        <div className={`ml-3 text-xs text-right flex-shrink-0 ${isOverdue ? 'text-red-600 dark:text-red-300 font-medium' : 'text-gray-500 dark:text-gray-400'}`}>
          <div>{format(new Date(todo.due_date), 'd MMM')}</div>
          {isOverdue && <div className="text-red-600 dark:text-red-300">achterstallig</div>}
        </div>
      )}
    </button>
  );
}

/**
 * Todo card for awaiting response tasks.
 */
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
        title="Markeren als voltooid"
      >
        <CheckSquare className="w-5 h-5 text-orange-500 dark:text-orange-400" />
      </button>
      <div className="flex-1 min-w-0">
        <p className="text-sm font-medium text-gray-900 dark:text-gray-50 truncate">{todo.content}</p>
        <div className="flex items-center gap-2 mt-1">
          <PersonAvatar
            thumbnail={todo.person_thumbnail}
            name={todo.person_name}
            size="xs"
          />
          <span className="text-xs text-gray-500 dark:text-gray-400 truncate">{todo.person_name}</span>
        </div>
      </div>
      <div className={`ml-3 text-xs px-2 py-1 rounded-full flex items-center gap-1 ${urgencyClass}`}>
        <Clock className="w-3 h-3" />
        {days === 0 ? 'Vandaag' : `${days}d`}
      </div>
    </button>
  );
}

/**
 * Meeting card showing calendar events.
 */
function MeetingCard({ meeting, onClick, isNext }) {
  const now = new Date();
  const startTime = new Date(meeting.start_time);
  const endTime = new Date(meeting.end_time);
  const isPast = endTime < now;
  const isNow = startTime <= now && now <= endTime;

  const formattedStartTime = format(startTime, 'HH:mm');
  const formattedEndTime = format(endTime, 'HH:mm');

  // Filter out current user from matched people
  const currentUserPersonId = window.stadionConfig?.currentUserPersonId
    ? Number(window.stadionConfig.currentUserPersonId)
    : null;
  const filteredMatchedPeople = (meeting.matched_people || []).filter(
    (person) => !currentUserPersonId || person.person_id !== currentUserPersonId
  );

  const cardContent = (
    <>
      <div className={`text-sm font-medium w-16 flex-shrink-0 ${isNow ? 'font-semibold text-accent-600 dark:text-white/90' : 'text-accent-600 dark:text-accent-400'}`}>
        {meeting.all_day ? 'Hele dag' : <>{formattedStartTime} - <br />{formattedEndTime}</>}
      </div>
      <div className="flex-1 min-w-0">
        <p className={`text-sm font-medium truncate ${isNow ? 'text-accent-900 dark:text-white' : 'text-gray-900 dark:text-gray-50'}`}>
          {meeting.title}
        </p>
        {meeting.location && (
          <p className={`text-xs truncate ${isNow ? 'text-accent-700 dark:text-white/80' : 'text-gray-500 dark:text-gray-400'}`}>
            {meeting.location}
          </p>
        )}
      </div>
      {filteredMatchedPeople.length > 0 && (
        <div className="flex -space-x-2 ml-3 flex-shrink-0">
          {filteredMatchedPeople.slice(0, 3).map((person) => (
            <PersonAvatar
              key={person.person_id}
              thumbnail={person.thumbnail}
              name={person.name}
              size="md"
              borderClassName="border-2 border-white dark:border-gray-800"
            />
          ))}
        </div>
      )}
    </>
  );

  const buttonClasses = [
    'w-full flex items-center p-3 rounded-lg transition-colors text-left',
    isPast ? 'opacity-50' : '',
    isNow ? 'bg-accent-50 dark:bg-accent-800 ring-1 ring-accent-200 dark:ring-accent-700' : 'hover:bg-gray-50 dark:hover:bg-gray-700',
  ].filter(Boolean).join(' ');

  return (
    <button
      type="button"
      onClick={() => onClick(meeting)}
      className={buttonClasses}
      data-is-now={isNow && !meeting.all_day ? 'true' : undefined}
      data-is-next={isNext ? 'true' : undefined}
    >
      {cardContent}
    </button>
  );
}

/**
 * Empty state shown when no data exists.
 */
function EmptyState() {
  return (
    <div className="card p-12 text-center">
      <div className="flex justify-center mb-4">
        <div className="p-4 bg-accent-50 dark:bg-gray-700 rounded-full">
          <Sparkles className="w-12 h-12 text-accent-600 dark:text-accent-400" />
        </div>
      </div>
      <h2 className="text-2xl font-semibold text-gray-900 dark:text-gray-50 mb-2">Welkom bij {APP_NAME}!</h2>
      <p className="text-gray-600 dark:text-gray-300 mb-8 max-w-md mx-auto">
        Begin met het toevoegen van je eerste lid, team of datum. Je dashboard vult zich naarmate je meer informatie toevoegt.
      </p>
      <div className="flex flex-col sm:flex-row gap-4 justify-center">
        <Link
          to="/people/new"
          className="inline-flex items-center px-6 py-3 bg-accent-600 text-white rounded-lg hover:bg-accent-700 dark:hover:bg-accent-500 transition-colors"
        >
          <Plus className="w-5 h-5 mr-2" />
          Voeg je eerste lid toe
        </Link>
        <Link
          to="/teams/new"
          className="inline-flex items-center px-6 py-3 bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-200 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors"
        >
          <Plus className="w-5 h-5 mr-2" />
          Voeg je eerste team toe
        </Link>
      </div>
    </div>
  );
}

/**
 * Loading skeleton for dashboard.
 */
function DashboardSkeleton() {
  return (
    <div className="space-y-6">
      <div className="grid grid-cols-2 lg:grid-cols-5 gap-4">
        {[...Array(5)].map((_, i) => (
          <div key={i} className="card p-4">
            <div className="animate-pulse">
              <div className="h-4 bg-gray-200 dark:bg-gray-700 rounded w-20 mb-2" />
              <div className="h-6 bg-gray-200 dark:bg-gray-700 rounded w-12" />
            </div>
          </div>
        ))}
      </div>
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {[...Array(6)].map((_, i) => (
          <div key={i} className="card">
            <div className="flex items-center justify-between p-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50">
              <div className="h-5 bg-gray-200 dark:bg-gray-700 rounded w-32 animate-pulse" />
            </div>
            <div className="h-[32vh] overflow-y-auto">
              {[...Array(3)].map((_, j) => (
                <div key={j} className="p-3 border-b border-gray-100 dark:border-gray-700 last:border-0">
                  <div className="animate-pulse flex items-center">
                    <div className="w-10 h-10 bg-gray-200 dark:bg-gray-700 rounded-full" />
                    <div className="ml-3 flex-1">
                      <div className="h-4 bg-gray-200 dark:bg-gray-700 rounded w-3/4 mb-2" />
                      <div className="h-3 bg-gray-200 dark:bg-gray-700 rounded w-1/2" />
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

/**
 * Error state for dashboard.
 */
function DashboardError({ error }) {
  const isNetworkError = error?.response?.status >= 500 || !error?.response;

  return (
    <div className="card p-8 text-center">
      <div className="text-red-600 dark:text-red-400 mb-2">
        {isNetworkError ? (
          <>
            <p className="font-medium mb-1">Dashboard data kon niet worden geladen</p>
            <p className="text-sm text-gray-600 dark:text-gray-300">
              Controleer je verbinding en ververs de pagina.
            </p>
          </>
        ) : (
          <>
            <p className="font-medium mb-1">Dashboard kon niet worden geladen</p>
            <p className="text-sm text-gray-600 dark:text-gray-300">
              {error?.response?.data?.message || 'Er is een fout opgetreden bij het laden van je gegevens.'}
            </p>
          </>
        )}
      </div>
    </div>
  );
}

/**
 * VOG statistics card showing two counts:
 * - Aan te vragen: people not yet submitted to Justis
 * - In afwachting: total people on the VOG list
 */
function VOGStatCard() {
  const { needsVog, emailSent, justisSubmitted } = useVOGCount();

  // People not yet in Justis (need to request)
  const aanTeVragen = needsVog + emailSent;
  // Total people on VOG list
  const totaal = needsVog + emailSent + justisSubmitted;

  return (
    <Link to="/vog" className="card p-4 hover:shadow-md dark:hover:shadow-gray-900/50 transition-shadow">
      <div className="flex items-center justify-between mb-2">
        <p className="text-sm font-medium text-gray-500 dark:text-gray-400">VOG Status</p>
        <div className="p-2 bg-accent-50 dark:bg-gray-700 rounded-lg">
          <FileCheck className="w-5 h-5 text-accent-600 dark:text-accent-400" />
        </div>
      </div>
      <div className="flex items-center justify-between gap-4">
        <div className="text-center flex-1">
          <p className="text-xl font-semibold dark:text-gray-50">{aanTeVragen}</p>
          <p className="text-xs text-gray-400 dark:text-gray-500">Aan te vragen</p>
        </div>
        <div className="text-center flex-1 border-l border-gray-200 dark:border-gray-600 pl-4">
          <p className="text-xl font-semibold dark:text-gray-50">{totaal}</p>
          <p className="text-xs text-gray-400 dark:text-gray-500">In afwachting</p>
        </div>
      </div>
    </Link>
  );
}

/**
 * Tuchtzaken statistics card showing the count of discipline cases.
 */
function TuchtzakenStatCard() {
  const { count } = useDisciplineCasesCount();

  return (
    <StatCard title="Tuchtzaken" value={count} icon={Gavel} href="/tuchtzaken" />
  );
}

/**
 * Stats row component for the dashboard header.
 */
function StatsRow({ stats }) {
  const { data: currentUser } = useCurrentUser();
  const canAccessVOG = currentUser?.can_access_vog ?? false;
  const canAccessFairplay = currentUser?.can_access_fairplay ?? false;

  return (
    <div className="grid grid-cols-2 lg:grid-cols-5 gap-4">
      <StatCard title="Totaal leden" value={stats?.total_people || 0} icon={Users} href="/people" />
      <StatCard title="Teams" value={stats?.total_teams || 0} icon={Building2} href="/teams" />
      {canAccessVOG ? (
        <VOGStatCard />
      ) : (
        <StatCard title="Herinneringen" value={stats?.total_dates || 0} icon={Calendar} href="/dates" />
      )}
      {canAccessFairplay ? (
        <TuchtzakenStatCard />
      ) : (
        <StatCard title="Open taken" value={stats?.open_todos_count || 0} icon={CheckSquare} href="/todos" />
      )}
      <StatCard title="In afwachting" value={stats?.awaiting_todos_count || 0} icon={Clock} href="/todos?status=awaiting" />
    </div>
  );
}

export default function Dashboard() {
  const { data, isLoading, error } = useDashboard();
  const { data: openTodos } = useTodos('open');
  const { data: awaitingTodos } = useTodos('awaiting');
  const { data: dashboardSettings } = useDashboardSettings();
  const updateDashboardSettings = useUpdateDashboardSettings();
  const queryClient = useQueryClient();

  // Use the todo completion hook
  const todoCompletion = useTodoCompletion();

  // Date navigation state for meetings widget
  const [selectedDate, setSelectedDate] = useState(new Date());
  const { data: meetingsData } = useDateMeetings(selectedDate);

  // Meeting modal state
  const [selectedMeeting, setSelectedMeeting] = useState(null);
  const [showMeetingModal, setShowMeetingModal] = useState(false);

  // URL params for customize modal
  const [searchParams, setSearchParams] = useSearchParams();
  const showCustomizeModal = searchParams.get('customize') === 'true';

  // Meetings data
  const dateMeetings = useMemo(() => meetingsData?.meetings || [], [meetingsData?.meetings]);
  const hasCalendarConnections = meetingsData?.has_connections ?? false;

  // Find the next upcoming meeting
  const nextMeetingId = useMemo(() => {
    if (!isToday(selectedDate)) return null;
    const now = new Date();
    const nextMeeting = dateMeetings.find((m) => {
      if (m.all_day) return false;
      return new Date(m.start_time) > now;
    });
    return nextMeeting?.id || null;
  }, [dateMeetings, selectedDate]);

  // Ref for auto-scroll to current/next meeting
  const meetingsContainerRef = useRef(null);

  // Auto-scroll to current or next meeting
  useEffect(() => {
    if (!isToday(selectedDate) || !meetingsContainerRef.current) return;

    const timer = setTimeout(() => {
      let targetMeeting = meetingsContainerRef.current?.querySelector('[data-is-now="true"]');
      if (!targetMeeting) {
        targetMeeting = meetingsContainerRef.current?.querySelector('[data-is-next="true"]');
      }
      if (targetMeeting) {
        targetMeeting.scrollIntoView({ behavior: 'smooth', block: 'center' });
      }
    }, 100);

    return () => clearTimeout(timer);
  }, [selectedDate, dateMeetings]);

  // Date navigation handlers
  const handlePrevDay = () => setSelectedDate((d) => subDays(d, 1));
  const handleNextDay = () => setSelectedDate((d) => addDays(d, 1));
  const handleToday = () => setSelectedDate(new Date());

  // Refresh handler for pull-to-refresh
  const handleRefresh = async () => {
    await Promise.all([
      queryClient.invalidateQueries({ queryKey: ['dashboard'] }),
      queryClient.invalidateQueries({ queryKey: ['reminders'] }),
      queryClient.invalidateQueries({ queryKey: ['todos'] }),
    ]);
  };

  // Modal handlers
  const closeCustomizeModal = () => setSearchParams({});

  const handleSaveSettings = (settings) => {
    updateDashboardSettings.mutate(settings, {
      onSuccess: () => closeCustomizeModal(),
    });
  };

  const handleMeetingClick = (meeting) => {
    setSelectedMeeting(meeting);
    setShowMeetingModal(true);
  };

  const closeMeetingModal = () => {
    setShowMeetingModal(false);
    setSelectedMeeting(null);
  };

  // Loading state
  if (isLoading) {
    return <DashboardSkeleton />;
  }

  // Error state
  if (error) {
    return <DashboardError error={error} />;
  }

  const { stats, recent_people, upcoming_reminders, recently_contacted } = data || {};
  const totalItems = (stats?.total_people || 0) + (stats?.total_teams || 0) + (stats?.total_dates || 0);
  const isEmpty = totalItems === 0;

  // Empty state
  if (isEmpty) {
    return (
      <div className="space-y-6">
        <StatsRow stats={stats} />
        <EmptyState />
      </div>
    );
  }

  // Dashboard settings
  const visibleCards = dashboardSettings?.visible_cards || DEFAULT_DASHBOARD_CARDS;
  const cardOrder = dashboardSettings?.card_order || DEFAULT_DASHBOARD_CARDS;
  const orderedVisibleCards = cardOrder.filter((cardId) => visibleCards.includes(cardId));

  // Limit todos for dashboard display
  const dashboardTodos = openTodos?.slice(0, 5) || [];
  const dashboardAwaitingTodos = awaitingTodos?.slice(0, 5) || [];

  // Meeting card header actions
  const meetingsHeaderActions = (
    <div className="flex items-center gap-1">
      {!isToday(selectedDate) && (
        <button
          onClick={handleToday}
          className="px-2 py-1 text-xs font-medium text-accent-600 dark:text-accent-400 hover:bg-accent-50 dark:hover:bg-accent-800 dark:hover:text-accent-100 rounded transition-colors"
        >
          Vandaag
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
  );

  // Card renderers
  const cardRenderers = {
    stats: () => <StatsRow key="stats" stats={stats} />,

    reminders: () => (
      <DashboardCard
        key="reminders"
        title="Komende herinneringen"
        icon={Calendar}
        count={upcoming_reminders?.length}
        linkTo="/dates"
        emptyMessage="Geen komende herinneringen"
      >
        {upcoming_reminders?.length > 0 &&
          upcoming_reminders.map((reminder) => <ReminderCard key={reminder.id} reminder={reminder} />)}
      </DashboardCard>
    ),

    todos: () => (
      <DashboardCard
        key="todos"
        title="Open taken"
        icon={CheckSquare}
        count={openTodos?.length}
        linkTo="/todos"
        emptyMessage="Geen open taken"
      >
        {dashboardTodos.length > 0 &&
          dashboardTodos.map((todo) => (
            <TodoCard
              key={todo.id}
              todo={todo}
              onToggle={todoCompletion.handleToggleTodo}
              onView={todoCompletion.handleViewTodo}
            />
          ))}
      </DashboardCard>
    ),

    awaiting: () => (
      <DashboardCard
        key="awaiting"
        title="Openstaand"
        icon={Clock}
        count={awaitingTodos?.length}
        linkTo="/todos?status=awaiting"
        emptyMessage="Geen openstaande reacties"
      >
        {dashboardAwaitingTodos.length > 0 &&
          dashboardAwaitingTodos.map((todo) => (
            <AwaitingTodoCard
              key={todo.id}
              todo={todo}
              onToggle={todoCompletion.handleToggleTodo}
              onView={todoCompletion.handleViewTodo}
            />
          ))}
      </DashboardCard>
    ),

    meetings: () =>
      hasCalendarConnections ? (
        <DashboardCard
          key="meetings"
          title={isToday(selectedDate) ? 'Afspraken vandaag' : format(selectedDate, 'EEEE d MMMM')}
          icon={CalendarClock}
          count={dateMeetings.length}
          headerActions={meetingsHeaderActions}
          emptyMessage={
            isToday(selectedDate)
              ? 'Geen afspraken gepland voor vandaag'
              : `Geen afspraken op ${format(selectedDate, 'd MMMM')}`
          }
          contentRef={meetingsContainerRef}
        >
          {dateMeetings.length > 0 &&
            dateMeetings.map((meeting) => (
              <MeetingCard
                key={meeting.id}
                meeting={meeting}
                isNext={meeting.id === nextMeetingId}
                onClick={handleMeetingClick}
              />
            ))}
        </DashboardCard>
      ) : null,

    'recent-contacted': () => (
      <DashboardCard
        key="recent-contacted"
        title="Recent gecontacteerd"
        icon={MessageCircle}
        emptyMessage="Nog geen recente activiteiten"
      >
        {recently_contacted?.length > 0 &&
          recently_contacted.map((person) => <PersonCard key={person.id} person={person} />)}
      </DashboardCard>
    ),

    'recent-edited': () => (
      <DashboardCard
        key="recent-edited"
        title="Recent bewerkt"
        icon={Users}
        linkTo="/people"
        emptyMessage={
          <>
            Nog geen leden. <Link to="/people/new" className="text-accent-600 dark:text-accent-400">Voeg iemand toe</Link>
          </>
        }
      >
        {recent_people?.length > 0 &&
          recent_people.map((person) => <PersonCard key={person.id} person={person} />)}
      </DashboardCard>
    ),
  };

  // Render cards in segments (stats full-width, others in grid)
  function renderCardSegments() {
    const segments = [];
    let currentGroup = [];

    orderedVisibleCards.forEach((cardId) => {
      if (cardId === 'stats') {
        if (currentGroup.length > 0) {
          segments.push(
            <div key={`grid-${segments.length}`} className="grid grid-cols-1 lg:grid-cols-3 gap-6">
              {currentGroup.map((id) => cardRenderers[id]?.())}
            </div>
          );
          currentGroup = [];
        }
        segments.push(cardRenderers.stats());
      } else {
        currentGroup.push(cardId);
      }
    });

    if (currentGroup.length > 0) {
      segments.push(
        <div key={`grid-${segments.length}`} className="grid grid-cols-1 lg:grid-cols-3 gap-6">
          {currentGroup.map((id) => cardRenderers[id]?.())}
        </div>
      );
    }

    return segments;
  }

  return (
    <PullToRefreshWrapper onRefresh={handleRefresh}>
      <div className="space-y-6 -mb-4 lg:-mb-6">
        {renderCardSegments()}

        <CompleteTodoModal
          isOpen={todoCompletion.showCompleteModal}
          onClose={todoCompletion.closeCompleteModal}
          todo={todoCompletion.todoToComplete}
          onAwaiting={todoCompletion.handleMarkAwaiting}
          onComplete={todoCompletion.handleJustComplete}
          onCompleteAsActivity={todoCompletion.handleCompleteAsActivity}
          hideAwaitingOption={todoCompletion.todoToComplete?.status === 'awaiting'}
        />

        <QuickActivityModal
          isOpen={todoCompletion.showActivityModal}
          onClose={todoCompletion.closeActivityModal}
          onSubmit={todoCompletion.handleCreateActivity}
          isLoading={todoCompletion.isCreatingActivity}
          personId={todoCompletion.todoToComplete?.person_id}
          initialData={todoCompletion.activityInitialData}
        />

        <Suspense fallback={null}>
          <TodoModal
            isOpen={todoCompletion.showTodoModal}
            onClose={todoCompletion.closeTodoModal}
            onSubmit={todoCompletion.handleUpdateTodo}
            isLoading={todoCompletion.isUpdatingTodo}
            todo={todoCompletion.todoToView}
          />

          <MeetingDetailModal
            isOpen={showMeetingModal}
            onClose={closeMeetingModal}
            meeting={selectedMeeting}
          />
        </Suspense>

        <DashboardCustomizeModal
          isOpen={showCustomizeModal}
          onClose={closeCustomizeModal}
          settings={dashboardSettings}
          onSave={handleSaveSettings}
          isSaving={updateDashboardSettings.isPending}
        />
      </div>
    </PullToRefreshWrapper>
  );
}
