import { useState } from 'react';
import { Link } from 'react-router-dom';
import { Users, Building2, Calendar, Star, ArrowRight, Plus, Sparkles, CheckSquare, Square, MessageCircle, Clock } from 'lucide-react';
import { useDashboard, useTodos, useUpdateTodo } from '@/hooks/useDashboard';
import { useCreateActivity } from '@/hooks/usePeople';
import { format, formatDistanceToNow } from 'date-fns';
import { APP_NAME } from '@/constants/app';
import { isTodoOverdue, getAwaitingDays, getAwaitingUrgencyClass } from '@/utils/timeline';
import CompleteTodoModal from '@/components/Timeline/CompleteTodoModal';
import QuickActivityModal from '@/components/Timeline/QuickActivityModal';

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

function PersonCard({ person, hideStar = false }) {
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
      {person.is_favorite && !hideStar && (
        <Star className="w-4 h-4 text-yellow-400 fill-current" />
      )}
    </Link>
  );
}

function ReminderCard({ reminder }) {
  const daysUntil = reminder.days_until;

  let urgencyClass = 'bg-gray-100 text-gray-700';
  if (daysUntil === 0) {
    urgencyClass = 'bg-green-100 text-green-700';
  } else if (daysUntil <= 3) {
    urgencyClass = 'bg-orange-100 text-orange-700';
  } else if (daysUntil <= 7) {
    urgencyClass = 'bg-yellow-100 text-yellow-700';
  }

  const firstPersonId = reminder.related_people?.[0]?.id;
  const hasRelatedPeople = reminder.related_people?.length > 0;

  const cardContent = (
    <>
      <div className={`px-2 py-1 rounded text-xs font-medium ${urgencyClass}`}>
        {daysUntil === 0 ? 'Today' : `${daysUntil}d`}
      </div>
      <div className="ml-3 flex-1 min-w-0">
        <p className="text-sm font-medium text-gray-900">{reminder.title}</p>
        <p className="text-xs text-gray-500">
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
    </>
  );

  // Wrap in Link if there are related people, otherwise just a div
  if (hasRelatedPeople && firstPersonId) {
    return (
      <Link
        to={`/people/${firstPersonId}`}
        className="flex items-center p-3 rounded-lg hover:bg-gray-50 transition-colors"
      >
        {cardContent}
      </Link>
    );
  }

  return (
    <div className="flex items-center p-3 rounded-lg hover:bg-gray-50 transition-colors">
      {cardContent}
    </div>
  );
}

function TodoCard({ todo, onToggle }) {
  const isOverdue = isTodoOverdue(todo);

  return (
    <Link
      to={`/people/${todo.person_id}`}
      className="flex items-start p-3 rounded-lg hover:bg-gray-50 transition-colors group"
    >
      <button
        onClick={(e) => {
          e.preventDefault();
          e.stopPropagation();
          onToggle(todo);
        }}
        className="mt-0.5 mr-3 flex-shrink-0"
        title={todo.status === 'completed' ? 'Reopen' : 'Complete'}
      >
        {todo.status === 'completed' ? (
          <CheckSquare className="w-5 h-5 text-primary-600" />
        ) : (
          <Square className={`w-5 h-5 ${isOverdue ? 'text-red-600' : 'text-gray-400'}`} />
        )}
      </button>
      <div className="flex-1 min-w-0">
        <p className={`text-sm font-medium ${todo.status === 'completed' ? 'line-through text-gray-400' : isOverdue ? 'text-red-600' : 'text-gray-900'}`}>
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
            <div className="w-5 h-5 bg-gray-200 rounded-full flex items-center justify-center">
              <span className="text-xs text-gray-500">{todo.person_name?.[0]}</span>
            </div>
          )}
          <span className="text-xs text-gray-500 truncate">{todo.person_name}</span>
        </div>
      </div>
      {todo.due_date && todo.status === 'open' && (
        <div className={`ml-3 text-xs text-right flex-shrink-0 ${isOverdue ? 'text-red-600 font-medium' : 'text-gray-500'}`}>
          <div>{format(new Date(todo.due_date), 'MMM d')}</div>
          {isOverdue && <div className="text-red-600">overdue</div>}
        </div>
      )}
    </Link>
  );
}

function AwaitingTodoCard({ todo, onToggle }) {
  const days = getAwaitingDays(todo);
  const urgencyClass = getAwaitingUrgencyClass(days);

  return (
    <Link
      to={`/people/${todo.person_id}`}
      className="flex items-start p-3 rounded-lg hover:bg-gray-50 transition-colors group"
    >
      <button
        onClick={(e) => {
          e.preventDefault();
          e.stopPropagation();
          onToggle(todo);
        }}
        className="mt-0.5 mr-3 flex-shrink-0"
        title="Mark as complete"
      >
        <CheckSquare className="w-5 h-5 text-orange-500" />
      </button>
      <div className="flex-1 min-w-0">
        <p className="text-sm font-medium text-gray-900 truncate">{todo.content}</p>
        <div className="flex items-center gap-2 mt-1">
          {todo.person_thumbnail ? (
            <img
              src={todo.person_thumbnail}
              alt={todo.person_name}
              loading="lazy"
              className="w-5 h-5 rounded-full object-cover"
            />
          ) : (
            <div className="w-5 h-5 bg-gray-200 rounded-full flex items-center justify-center">
              <span className="text-xs text-gray-500">{todo.person_name?.[0]}</span>
            </div>
          )}
          <span className="text-xs text-gray-500 truncate">{todo.person_name}</span>
        </div>
      </div>
      <div className={`ml-3 text-xs px-2 py-1 rounded-full flex items-center gap-1 ${urgencyClass}`}>
        <Clock className="w-3 h-3" />
        {days === 0 ? 'Today' : `${days}d`}
      </div>
    </Link>
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
      <h2 className="text-2xl font-semibold text-gray-900 mb-2">Welcome to {APP_NAME}!</h2>
      <p className="text-gray-600 mb-8 max-w-md mx-auto">
        Get started by adding your first contact, organization, or important date. Your dashboard will populate as you add more information.
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
  const updateTodo = useUpdateTodo();
  const createActivity = useCreateActivity();

  // State for complete todo flow
  const [todoToComplete, setTodoToComplete] = useState(null);
  const [showCompleteModal, setShowCompleteModal] = useState(false);
  const [showActivityModal, setShowActivityModal] = useState(false);
  const [activityInitialData, setActivityInitialData] = useState(null);

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
  
  return (
    <div className="space-y-6">
      {/* Stats */}
      <div className="grid grid-cols-2 lg:grid-cols-5 gap-4">
        <StatCard
          title="Total people"
          value={stats?.total_people || 0}
          icon={Users}
          href="/people"
        />
        <StatCard
          title="Organizations"
          value={stats?.total_companies || 0}
          icon={Building2}
          href="/companies"
        />
        <StatCard
          title="Events"
          value={stats?.total_dates || 0}
          icon={Calendar}
          href="/dates"
        />
        <StatCard
          title="Open todos"
          value={stats?.open_todos_count || 0}
          icon={CheckSquare}
          href="/todos"
        />
        <StatCard
          title="Awaiting"
          value={stats?.awaiting_todos_count || 0}
          icon={Clock}
          href="/todos?status=awaiting"
        />
      </div>
      
      {/* Row 1: Upcoming Reminders + Open Todos + Awaiting Response */}
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Upcoming Reminders */}
        <div className="card">
          <div className="flex items-center justify-between p-4 border-b border-gray-200">
            <h2 className="font-semibold flex items-center">
              <Calendar className="w-5 h-5 mr-2 text-gray-500" />
              Upcoming reminders
            </h2>
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

        {/* Open Todos */}
        <div className="card">
          <div className="flex items-center justify-between p-4 border-b border-gray-200">
            <h2 className="font-semibold flex items-center">
              <CheckSquare className="w-5 h-5 mr-2 text-gray-500" />
              Open todos
            </h2>
            <Link
              to="/todos"
              className="text-sm text-primary-600 hover:text-primary-700 flex items-center"
            >
              View all
              <ArrowRight className="w-4 h-4 ml-1" />
            </Link>
          </div>
          <div className="divide-y divide-gray-100">
            {dashboardTodos.length > 0 ? (
              dashboardTodos.map((todo) => (
                <TodoCard key={todo.id} todo={todo} onToggle={handleToggleTodo} />
              ))
            ) : (
              <p className="p-4 text-sm text-gray-500 text-center">
                No open todos
              </p>
            )}
          </div>
        </div>

        {/* Awaiting Response */}
        <div className="card">
          <div className="flex items-center justify-between p-4 border-b border-gray-200">
            <h2 className="font-semibold flex items-center">
              <Clock className="w-5 h-5 mr-2 text-gray-500" />
              Awaiting response
            </h2>
            <Link
              to="/todos?status=awaiting"
              className="text-sm text-primary-600 hover:text-primary-700 flex items-center"
            >
              View all
              <ArrowRight className="w-4 h-4 ml-1" />
            </Link>
          </div>
          <div className="divide-y divide-gray-100">
            {dashboardAwaitingTodos.length > 0 ? (
              dashboardAwaitingTodos.map((todo) => (
                <AwaitingTodoCard key={todo.id} todo={todo} onToggle={handleToggleTodo} />
              ))
            ) : (
              <p className="p-4 text-sm text-gray-500 text-center">
                No awaiting responses
              </p>
            )}
          </div>
        </div>
      </div>

      {/* Row 2: Favorites + Recently Contacted + Recently Edited */}
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Favorites */}
        <div className="card">
          <div className="flex items-center justify-between p-4 border-b border-gray-200">
            <h2 className="font-semibold flex items-center">
              <Star className="w-5 h-5 mr-2 text-gray-500 fill-current" />
              Favorites
            </h2>
          </div>
          {favorites?.length > 0 ? (
            <div className="divide-y divide-gray-100">
              {favorites.slice(0, 5).map((person) => (
                <PersonCard key={person.id} person={person} hideStar={true} />
              ))}
            </div>
          ) : (
            <p className="p-4 text-sm text-gray-500 text-center">
              No favorites yet
            </p>
          )}
        </div>
        
        {/* Recently Contacted */}
        <div className="card">
          <div className="flex items-center justify-between p-4 border-b border-gray-200">
            <h2 className="font-semibold flex items-center">
              <MessageCircle className="w-5 h-5 mr-2 text-gray-500" />
              Recently contacted
            </h2>
          </div>
          {recently_contacted?.length > 0 ? (
            <div className="divide-y divide-gray-100">
              {recently_contacted.map((person) => (
                <PersonCard key={person.id} person={person} />
              ))}
            </div>
          ) : (
            <p className="p-4 text-sm text-gray-500 text-center">
              No recent activities yet
            </p>
          )}
        </div>
        
        {/* Recently Edited People */}
        <div className="card">
          <div className="flex items-center justify-between p-4 border-b border-gray-200">
            <h2 className="font-semibold flex items-center">
              <Users className="w-5 h-5 mr-2 text-gray-500" />
              Recently edited
            </h2>
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
    </div>
  );
}
