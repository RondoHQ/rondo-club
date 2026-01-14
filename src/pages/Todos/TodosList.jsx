import { useState, useMemo } from 'react';
import { Link } from 'react-router-dom';
import { CheckSquare, Square, Clock, Pencil, Trash2, Plus, RotateCcw } from 'lucide-react';
import { useTodos, useUpdateTodo, useDeleteTodo } from '@/hooks/useDashboard';
import { useCreateActivity } from '@/hooks/usePeople';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';
import { format } from 'date-fns';
import { isTodoOverdue, getAwaitingDays, getAwaitingUrgencyClass } from '@/utils/timeline';
import TodoModal from '@/components/Timeline/TodoModal';
import GlobalTodoModal from '@/components/Timeline/GlobalTodoModal';
import CompleteTodoModal from '@/components/Timeline/CompleteTodoModal';
import QuickActivityModal from '@/components/Timeline/QuickActivityModal';

export default function TodosList() {
  useDocumentTitle('Todos');

  // Filter state - now matches API status values
  const [statusFilter, setStatusFilter] = useState('open'); // 'all' | 'open' | 'awaiting' | 'completed'

  const [editingTodo, setEditingTodo] = useState(null);
  const [showTodoModal, setShowTodoModal] = useState(false);
  const [showGlobalTodoModal, setShowGlobalTodoModal] = useState(false);

  // State for complete todo flow
  const [todoToComplete, setTodoToComplete] = useState(null);
  const [showCompleteModal, setShowCompleteModal] = useState(false);
  const [showActivityModal, setShowActivityModal] = useState(false);
  const [activityInitialData, setActivityInitialData] = useState(null);

  // Fetch todos with the current filter
  const { data: todos, isLoading } = useTodos(statusFilter);
  const updateTodo = useUpdateTodo();
  const deleteTodo = useDeleteTodo();
  const createActivity = useCreateActivity();

  // Filter is handled by API, so just use todos directly
  const filteredTodos = useMemo(() => {
    return todos || [];
  }, [todos]);

  const handleToggleTodo = (todo) => {
    // If it's an open todo, show the complete modal with options
    if (todo.status === 'open') {
      setTodoToComplete(todo);
      setShowCompleteModal(true);
      return;
    }

    // If it's an awaiting todo, clicking marks it complete
    if (todo.status === 'awaiting') {
      updateTodo.mutate({
        todoId: todo.id,
        data: { status: 'completed' },
      });
      return;
    }

    // If it's completed, reopen it
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

  const handleReopen = (todo) => {
    updateTodo.mutate({
      todoId: todo.id,
      data: { status: 'open' },
    });
  };

  const handleUpdateTodo = async (data) => {
    if (!editingTodo) return;

    await updateTodo.mutateAsync({
      todoId: editingTodo.id,
      data,
    });

    setShowTodoModal(false);
    setEditingTodo(null);
  };

  const handleDeleteTodo = async (todoId) => {
    if (!window.confirm('Are you sure you want to delete this todo?')) {
      return;
    }

    await deleteTodo.mutateAsync(todoId);
  };

  // Get the appropriate header text based on filter
  const getHeaderText = () => {
    const labels = {
      all: 'All todos',
      open: 'Open todos',
      awaiting: 'Awaiting response',
      completed: 'Completed todos',
    };
    return labels[statusFilter] || 'Todos';
  };

  // Get appropriate empty state message
  const getEmptyMessage = () => {
    const messages = {
      all: 'No todos found',
      open: 'No open todos',
      awaiting: 'No todos awaiting response',
      completed: 'No completed todos',
    };
    return messages[statusFilter] || 'No todos found';
  };

  // Get the icon for the header
  const getHeaderIcon = () => {
    if (statusFilter === 'completed') {
      return <CheckSquare className="w-5 h-5 mr-2 text-primary-600" />;
    }
    if (statusFilter === 'awaiting') {
      return <Clock className="w-5 h-5 mr-2 text-orange-600" />;
    }
    return <Square className="w-5 h-5 mr-2 text-gray-500" />;
  };

  if (isLoading) {
    return (
      <div className="flex items-center justify-center h-64">
        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600"></div>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <h1 className="text-2xl font-bold">Todos</h1>
        <div className="flex items-center gap-2">
          <button
            onClick={() => setShowGlobalTodoModal(true)}
            className="btn-primary text-sm flex items-center gap-2"
          >
            <Plus className="w-4 h-4" />
            Add todo
          </button>
        </div>
      </div>

      {/* Filter controls - now includes Awaiting as a primary tab */}
      <div className="flex flex-wrap items-center gap-3">
        <div className="flex rounded-lg border border-gray-200 p-0.5">
          <button
            onClick={() => setStatusFilter('open')}
            className={`px-3 py-1 text-sm rounded-md transition-colors ${
              statusFilter === 'open' ? 'bg-primary-100 text-primary-700' : 'text-gray-600 hover:text-gray-900'
            }`}
          >
            Open
          </button>
          <button
            onClick={() => setStatusFilter('awaiting')}
            className={`px-3 py-1 text-sm rounded-md transition-colors flex items-center gap-1 ${
              statusFilter === 'awaiting' ? 'bg-orange-100 text-orange-700' : 'text-gray-600 hover:text-gray-900'
            }`}
          >
            <Clock className="w-3.5 h-3.5" />
            Awaiting
          </button>
          <button
            onClick={() => setStatusFilter('completed')}
            className={`px-3 py-1 text-sm rounded-md transition-colors ${
              statusFilter === 'completed' ? 'bg-primary-100 text-primary-700' : 'text-gray-600 hover:text-gray-900'
            }`}
          >
            Completed
          </button>
          <button
            onClick={() => setStatusFilter('all')}
            className={`px-3 py-1 text-sm rounded-md transition-colors ${
              statusFilter === 'all' ? 'bg-primary-100 text-primary-700' : 'text-gray-600 hover:text-gray-900'
            }`}
          >
            All
          </button>
        </div>
      </div>

      {/* Filtered Todos */}
      <div className="card">
        <div className="p-4 border-b border-gray-200">
          <h2 className="font-semibold flex items-center">
            {getHeaderIcon()}
            {getHeaderText()} ({filteredTodos.length})
          </h2>
        </div>
        {filteredTodos.length > 0 ? (
          <div className="divide-y divide-gray-100">
            {filteredTodos.map((todo) => (
              <TodoItem
                key={todo.id}
                todo={todo}
                onToggle={handleToggleTodo}
                onReopen={handleReopen}
                onEdit={(todo) => {
                  setEditingTodo(todo);
                  setShowTodoModal(true);
                }}
                onDelete={handleDeleteTodo}
              />
            ))}
          </div>
        ) : (
          <div className="p-8 text-center">
            <CheckSquare className="w-12 h-12 text-gray-300 mx-auto mb-3" />
            <p className="text-gray-500">{getEmptyMessage()}</p>
            {statusFilter === 'open' && (
              <p className="text-sm text-gray-400 mt-1">
                Create todos from a person's detail page or click "Add todo"
              </p>
            )}
          </div>
        )}
      </div>

      {/* Edit Todo Modal */}
      <TodoModal
        isOpen={showTodoModal}
        onClose={() => {
          setShowTodoModal(false);
          setEditingTodo(null);
        }}
        onSubmit={handleUpdateTodo}
        isLoading={updateTodo.isPending}
        todo={editingTodo}
      />

      {/* Global Todo Modal (for creating new todos) */}
      <GlobalTodoModal
        isOpen={showGlobalTodoModal}
        onClose={() => setShowGlobalTodoModal(false)}
      />

      {/* Complete Todo Modal - now with three options */}
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

function TodoItem({ todo, onToggle, onReopen, onEdit, onDelete }) {
  const isOverdue = isTodoOverdue(todo);
  const awaitingDays = getAwaitingDays(todo);

  // Determine checkbox icon based on status
  const getStatusIcon = () => {
    if (todo.status === 'completed') {
      return <CheckSquare className="w-5 h-5 text-primary-600" />;
    }
    if (todo.status === 'awaiting') {
      return <Clock className="w-5 h-5 text-orange-500" />;
    }
    // Open
    return <Square className={`w-5 h-5 ${isOverdue ? 'text-red-600' : 'text-gray-400'}`} />;
  };

  // Get title for the toggle button
  const getToggleTitle = () => {
    if (todo.status === 'completed') return 'Reopen todo';
    if (todo.status === 'awaiting') return 'Mark as complete';
    return 'Complete todo';
  };

  return (
    <div className="flex items-start p-4 hover:bg-gray-50 transition-colors group">
      <button
        onClick={() => onToggle(todo)}
        className="mt-0.5 mr-3 flex-shrink-0"
        title={getToggleTitle()}
      >
        {getStatusIcon()}
      </button>

      <div className="flex-1 min-w-0">
        <p className={`text-sm ${
          todo.status === 'completed'
            ? 'line-through text-gray-400'
            : todo.status === 'awaiting'
            ? 'text-orange-700'
            : isOverdue
            ? 'text-red-600 font-medium'
            : 'text-gray-900'
        }`}>
          {todo.content}
        </p>

        <div className="flex flex-wrap items-center gap-3 mt-2">
          <Link
            to={`/people/${todo.person_id}`}
            className="flex items-center gap-2 text-xs text-primary-600 hover:text-primary-700 hover:underline"
          >
            {todo.person_thumbnail ? (
              <img
                src={todo.person_thumbnail}
                alt={todo.person_name}
                className="w-5 h-5 rounded-full object-cover"
              />
            ) : (
              <div className="w-5 h-5 bg-gray-200 rounded-full flex items-center justify-center">
                <span className="text-xs text-gray-500">{todo.person_name?.[0]}</span>
              </div>
            )}
            {todo.person_name}
          </Link>

          {/* Due date - only show prominently for open todos */}
          {todo.due_date && todo.status === 'open' && (
            <span className={`text-xs ${isOverdue ? 'text-red-600 font-medium' : 'text-gray-500'}`}>
              Due: {format(new Date(todo.due_date), 'MMM d, yyyy')}
              {isOverdue && ' (overdue)'}
            </span>
          )}

          {/* Awaiting indicator - shows how long we've been waiting */}
          {todo.status === 'awaiting' && awaitingDays !== null && (
            <span className={`text-xs px-2 py-0.5 rounded-full flex items-center gap-1 ${getAwaitingUrgencyClass(awaitingDays)}`}>
              <Clock className="w-3 h-3" />
              {awaitingDays === 0 ? 'Waiting since today' : `Waiting ${awaitingDays}d`}
            </span>
          )}
        </div>
      </div>

      <div className="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity ml-2">
        {/* Reopen button for awaiting/completed todos */}
        {todo.status !== 'open' && (
          <button
            onClick={() => onReopen(todo)}
            className="p-1 hover:bg-gray-100 rounded"
            title="Reopen todo"
          >
            <RotateCcw className="w-4 h-4 text-gray-400 hover:text-gray-600" />
          </button>
        )}
        <button
          onClick={() => onEdit(todo)}
          className="p-1 hover:bg-gray-100 rounded"
          title="Edit todo"
        >
          <Pencil className="w-4 h-4 text-gray-400 hover:text-gray-600" />
        </button>
        <button
          onClick={() => onDelete(todo.id)}
          className="p-1 hover:bg-red-50 rounded"
          title="Delete todo"
        >
          <Trash2 className="w-4 h-4 text-gray-400 hover:text-red-600" />
        </button>
      </div>
    </div>
  );
}
