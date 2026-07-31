import { lazy, Suspense } from 'react';
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
  HeartHandshake,
  Award,
  FileCheck,
  Gavel,
} from 'lucide-react';
import { useQueryClient } from '@tanstack/react-query';
import {
  useDashboard,
  useUpdateDashboardSettings,
  DEFAULT_DASHBOARD_CARDS,
} from '@/hooks/useDashboard.js';
import { useTodoCompletion } from '@/hooks/useTodoCompletion.js';
import { format } from '@/utils/dateFormat.js';
import { APP_NAME } from '@/constants/app.js';
import {
  isTodoOverdue,
  getReminderUrgencyClass,
} from '@/utils/timeline.js';
import PersonAvatar from '@/components/PersonAvatar.jsx';
import DashboardCard from '@/components/DashboardCard.jsx';
import CompleteTodoModal from '@/components/Timeline/CompleteTodoModal.jsx';
import QuickActivityModal from '@/components/Timeline/QuickActivityModal.jsx';
import DashboardCustomizeModal from '@/components/DashboardCustomizeModal.jsx';
import PullToRefreshWrapper from '@/components/PullToRefreshWrapper.jsx';

const TodoModal = lazy(() => import('@/components/Timeline/TodoModal.jsx'));

/**
 * Statistics card for dashboard header.
 */
function StatCard({ title, value, icon: Icon, href }) {
  return (
    <Link to={href} className="card p-4 hover:shadow-md dark:hover:shadow-gray-900/50 transition-shadow">
      <div className="flex items-center justify-between mb-2">
        <p className="text-sm font-medium text-gray-500 dark:text-gray-400">{title}</p>
        <div className="p-2 bg-cyan-50 dark:bg-gray-700 rounded-lg">
          <Icon className="w-5 h-5 text-electric-cyan dark:text-electric-cyan" />
        </div>
      </div>
      <div className="text-center">
        <p className="text-2xl font-semibold dark:text-gray-50">{value}</p>
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

  const occYear = parseInt(reminder.next_occurrence?.substring(0, 4), 10);
  const birthYear = reminder.date_value ? parseInt(reminder.date_value.substring(0, 4), 10) : null;
  const isBirthday = reminder.is_recurring && !reminder.year_unknown && birthYear && birthYear < occYear;
  const displayDate = isBirthday ? reminder.date_value : reminder.next_occurrence;
  const ageSuffix = isBirthday ? ` (wordt ${occYear - birthYear})` : '';

  const cardContent = (
    <>
      <div className={`px-2 py-1 rounded text-xs font-medium ${urgencyClass}`}>
        {daysUntil === 0 ? 'Vandaag' : `${daysUntil}d`}
      </div>
      <div className="ml-3 flex-1 min-w-0">
        <p className="text-sm font-medium text-gray-900 dark:text-gray-50">{reminder.title}</p>
        <p className="text-xs text-gray-500 dark:text-gray-400">
          {format(new Date(displayDate), 'd MMMM yyyy')}{ageSuffix}
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
 * Anniversary card showing upcoming jubilees.
 */
function AnniversaryCard({ anniversary }) {
  const daysUntil = anniversary.days_until;
  const urgencyClass = getReminderUrgencyClass(daysUntil);
  const person = anniversary.person;
  const typeLabel = anniversary.type === 'volunteer' ? 'Vrijwilliger' : 'Lid';

  return (
    <Link
      to={`/people/${person.id}`}
      className="flex items-center p-3 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
    >
      <div className={`px-2 py-1 rounded text-xs font-medium ${urgencyClass}`}>
        {daysUntil === 0 ? 'Vandaag' : `${daysUntil}d`}
      </div>
      <div className="ml-3 flex-1 min-w-0">
        <p className="text-sm font-medium text-gray-900 dark:text-gray-50 truncate">{person.name}</p>
        <p className="text-xs text-gray-500 dark:text-gray-400 truncate">
          {anniversary.milestone_label} {typeLabel.toLowerCase()} · {format(new Date(anniversary.anniversary_date), 'd MMMM yyyy')}
        </p>
      </div>
      <span className="ml-2 px-2 py-1 rounded-full text-[10px] font-medium bg-cyan-50 text-cyan-700 dark:bg-gray-700 dark:text-cyan-300">
        {typeLabel}
      </span>
    </Link>
  );
}

/**
 * Todo card for open tasks.
 */
function TodoCard({ todo, onToggle, onView }) {
  const isOverdue = isTodoOverdue(todo);
  const relatedPersons = todo.persons?.length
    ? todo.persons
    : todo.person_id
      ? [{ id: todo.person_id, name: todo.person_name, thumbnail: todo.person_thumbnail }]
      : [];

  return (
    <div
      role="button"
      tabIndex={0}
      onClick={() => onView(todo)}
      onKeyDown={(e) => {
        if (e.key === 'Enter' || e.key === ' ') {
          e.preventDefault();
          onView(todo);
        }
      }}
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
          <CheckSquare className="w-5 h-5 text-electric-cyan dark:text-electric-cyan" />
        ) : (
          <Square className={`w-5 h-5 ${isOverdue ? 'text-red-600 dark:text-red-300' : 'text-gray-400 dark:text-gray-500'}`} />
        )}
      </button>
      <div className="flex-1 min-w-0">
        <p className={`text-sm font-medium ${todo.status === 'completed' ? 'line-through text-gray-400 dark:text-gray-500' : isOverdue ? 'text-red-600 dark:text-red-300' : 'text-gray-900 dark:text-gray-50'}`}>
          {todo.content}
        </p>
        {relatedPersons.length > 0 && (
          <div className="flex flex-wrap items-center gap-2 mt-1">
            {relatedPersons.slice(0, 3).map((person) => (
              <Link
                key={person.id}
                to={`/people/${person.id}`}
                onClick={(e) => e.stopPropagation()}
                className="inline-flex items-center gap-1.5 text-xs text-electric-cyan dark:text-electric-cyan hover:underline"
              >
                <PersonAvatar
                  thumbnail={person.thumbnail}
                  name={person.name}
                  size="xs"
                />
                <span className="truncate max-w-28">{person.name}</span>
              </Link>
            ))}
            {relatedPersons.length > 3 && (
              <span className="text-xs text-gray-500 dark:text-gray-400">
                +{relatedPersons.length - 3}
              </span>
            )}
          </div>
        )}
      </div>
      {todo.due_date && todo.status === 'open' && (
        <div className={`ml-3 text-xs text-right flex-shrink-0 ${isOverdue ? 'text-red-600 dark:text-red-300 font-medium' : 'text-gray-500 dark:text-gray-400'}`}>
          <div>{format(new Date(todo.due_date), 'd MMM')}</div>
          {isOverdue && <div className="text-red-600 dark:text-red-300">achterstallig</div>}
        </div>
      )}
    </div>
  );
}

/**
 * Empty state shown when no data exists.
 */
function EmptyState() {
  return (
    <div className="card p-12 text-center">
      <div className="flex justify-center mb-4">
        <div className="p-4 bg-cyan-50 dark:bg-gray-700 rounded-full">
          <Sparkles className="w-12 h-12 text-electric-cyan dark:text-electric-cyan" />
        </div>
      </div>
      <h2 className="text-2xl font-semibold text-brand-gradient mb-2">Welkom bij {APP_NAME}!</h2>
      <p className="text-gray-600 dark:text-gray-300 mb-8 max-w-md mx-auto">
        Begin met het toevoegen van je eerste lid, team of datum. Je dashboard vult zich naarmate je meer informatie toevoegt.
      </p>
      <div className="flex flex-col sm:flex-row gap-4 justify-center">
        <Link
          to="/people/new"
          className="inline-flex items-center px-6 py-3 bg-electric-cyan text-white rounded-lg hover:bg-bright-cobalt dark:hover:bg-electric-cyan transition-colors"
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
            <div className="max-h-[50vh] lg:h-[32vh] overflow-y-auto">
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
function VOGStatCard({ vogCounts }) {
  const notSubmittedToJustis = vogCounts?.not_submitted_to_justis || 0;
  const submittedToJustis = vogCounts?.submitted_to_justis || 0;

  // Total people needing VOG
  const totaal = notSubmittedToJustis + submittedToJustis;

  return (
    <Link to="/vog" className="card p-4 hover:shadow-md dark:hover:shadow-gray-900/50 transition-shadow">
      <div className="flex items-center justify-between mb-2">
        <p className="text-sm font-medium text-gray-500 dark:text-gray-400">VOG Status</p>
        <div className="p-2 bg-cyan-50 dark:bg-gray-700 rounded-lg">
          <FileCheck className="w-5 h-5 text-electric-cyan dark:text-electric-cyan" />
        </div>
      </div>
      <div className="flex items-center justify-between gap-4">
        <div className="text-center flex-1">
          <p className="text-xl font-semibold dark:text-gray-50">{notSubmittedToJustis}</p>
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
function TuchtzakenStatCard({ count }) {
  return (
    <StatCard title="Tuchtzaken" value={count} icon={Gavel} href="/tuchtzaken" />
  );
}

/**
 * Stats row component for the dashboard header.
 */
function StatsRow({ stats }) {
  return (
    <div className="grid grid-cols-2 lg:grid-cols-5 gap-4">
      <StatCard title="Totaal leden" value={stats?.total_people || 0} icon={Users} href="/people" />
      <StatCard title="Vrijwilligers" value={stats?.total_volunteers || 0} icon={HeartHandshake} href="/people?vrijwilliger=1" />
      <StatCard title="Teams" value={stats?.total_teams || 0} icon={Building2} href="/teams" />
      {stats?.vog_counts && (
        <VOGStatCard vogCounts={stats.vog_counts} />
      )}
      {stats?.discipline_case_count !== null && stats?.discipline_case_count !== undefined ? (
        <TuchtzakenStatCard count={stats.discipline_case_count} />
      ) : (
        <StatCard title="Open taken" value={stats?.open_todos_count || 0} icon={CheckSquare} href="/todos" />
      )}
    </div>
  );
}

export default function Dashboard() {
  const { data, isLoading, error } = useDashboard();
  const updateDashboardSettings = useUpdateDashboardSettings();
  const queryClient = useQueryClient();

  // Use the todo completion hook
  const todoCompletion = useTodoCompletion();

  // URL params for customize modal
  const [searchParams, setSearchParams] = useSearchParams();
  const showCustomizeModal = searchParams.get('customize') === 'true';

  // Refresh handler for pull-to-refresh
  const handleRefresh = async () => {
    await Promise.all([
      queryClient.invalidateQueries({ queryKey: ['dashboard'] }),
      queryClient.invalidateQueries({ queryKey: ['reminders'] }),
    ]);
  };

  // Modal handlers
  const closeCustomizeModal = () => setSearchParams({});

  const handleSaveSettings = (settings) => {
    updateDashboardSettings.mutate(settings, {
      onSuccess: () => closeCustomizeModal(),
    });
  };

  // Loading state
  if (isLoading) {
    return <DashboardSkeleton />;
  }

  // Error state
  if (error) {
    return <DashboardError error={error} />;
  }

  const { stats, recent_people, upcoming_reminders, upcoming_anniversaries, recently_contacted, open_todos: openTodos, dashboard_settings: dashboardSettings } = data || {};
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

  const dashboardTodos = openTodos || [];

  // Card renderers
  const cardRenderers = {
    stats: () => <StatsRow key="stats" stats={stats} />,

    reminders: () => (
      <DashboardCard
        key="reminders"
        title="Verjaardagen"
        icon={Calendar}
        count={upcoming_reminders?.length}
        emptyMessage="Geen komende verjaardagen"
      >
        {upcoming_reminders?.length > 0 &&
          upcoming_reminders.map((reminder) => <ReminderCard key={reminder.id} reminder={reminder} />)}
      </DashboardCard>
    ),

    anniversaries: () => (
      <DashboardCard
        key="anniversaries"
        title="Jubilarissen"
        icon={Award}
        count={upcoming_anniversaries?.length}
        emptyMessage="Geen aankomende jubilarissen"
      >
        {upcoming_anniversaries?.length > 0 &&
          upcoming_anniversaries.map((anniversary) => (
            <AnniversaryCard key={anniversary.id} anniversary={anniversary} />
          ))}
      </DashboardCard>
    ),

    todos: () => (
      <DashboardCard
        key="todos"
        title="Open taken"
        icon={CheckSquare}
        count={stats?.open_todos_count || 0}
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

    'recent-contacted': () => (
      <DashboardCard
        key="recent-contacted"
        title="Recent berichten"
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
            Nog geen leden. <Link to="/people/new" className="text-electric-cyan dark:text-electric-cyan">Voeg iemand toe</Link>
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
