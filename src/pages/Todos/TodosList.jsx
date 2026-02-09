import { useState, useMemo } from 'react';
import { CheckSquare, Square, Clock, Plus, Info } from 'lucide-react';
import { useQueryClient } from '@tanstack/react-query';
import { useTodos, useUpdateTodo, useDeleteTodo } from '@/hooks/useDashboard';
import { useCreateActivity } from '@/hooks/usePeople';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';
import PullToRefreshWrapper from '@/components/PullToRefreshWrapper';
import TodoItem from '@/components/TodoItem.jsx';
import TodoModal from '@/components/Timeline/TodoModal';
import GlobalTodoModal from '@/components/Timeline/GlobalTodoModal';
import CompleteTodoModal from '@/components/Timeline/CompleteTodoModal';
import QuickActivityModal from '@/components/Timeline/QuickActivityModal';

export default function TodosList() {
  useDocumentTitle('Taken');

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
  const queryClient = useQueryClient();

  // Fetch todos with the current filter
  const { data: todos, isLoading } = useTodos(statusFilter);
  const updateTodo = useUpdateTodo();
  const deleteTodo = useDeleteTodo();
  const createActivity = useCreateActivity();

  const handleRefresh = async () => {
    await queryClient.invalidateQueries({ queryKey: ['todos'] });
  };

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

    // If it's an awaiting todo, show the complete modal (without awaiting option)
    if (todo.status === 'awaiting') {
      setTodoToComplete(todo);
      setShowCompleteModal(true);
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
      alert('Activiteit kon niet worden aangemaakt. Probeer het opnieuw.');
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
    if (!window.confirm('Weet je zeker dat je deze taak wilt verwijderen?')) {
      return;
    }

    await deleteTodo.mutateAsync(todoId);
  };

  // Get the appropriate header text based on filter
  const getHeaderText = () => {
    const labels = {
      all: 'Alle taken',
      open: 'Te doen',
      awaiting: 'Openstaand',
      completed: 'Afgeronde taken',
    };
    return labels[statusFilter] || 'Taken';
  };

  // Get appropriate empty state message
  const getEmptyMessage = () => {
    const messages = {
      all: 'Geen taken gevonden',
      open: 'Geen open taken',
      awaiting: 'Geen openstaande taken',
      completed: 'Geen afgeronde taken',
    };
    return messages[statusFilter] || 'Geen taken gevonden';
  };

  // Get the icon for the header
  const getHeaderIcon = () => {
    if (statusFilter === 'completed') {
      return <CheckSquare className="w-5 h-5 mr-2 text-electric-cyan dark:text-electric-cyan" />;
    }
    if (statusFilter === 'awaiting') {
      return <Clock className="w-5 h-5 mr-2 text-orange-600 dark:text-orange-400" />;
    }
    return <Square className="w-5 h-5 mr-2 text-gray-500 dark:text-gray-400" />;
  };

  if (isLoading) {
    return (
      <div className="flex items-center justify-center h-64">
        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-electric-cyan dark:border-electric-cyan"></div>
      </div>
    );
  }

  return (
    <PullToRefreshWrapper onRefresh={handleRefresh}>
      <div className="space-y-6">
        {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <h1 className="text-2xl font-bold">Taken</h1>
        <div className="flex items-center gap-2">
          <button
            onClick={() => setShowGlobalTodoModal(true)}
            className="btn-primary text-sm flex items-center gap-2"
          >
            <Plus className="w-4 h-4" />
            Taak toevoegen
          </button>
        </div>
      </div>

      {/* Personal tasks info message */}
      <div className="flex items-start gap-3 p-3 bg-blue-50 border border-blue-200 rounded-lg text-sm dark:bg-blue-900/30 dark:border-blue-700">
        <Info className="w-4 h-4 text-blue-600 dark:text-blue-400 flex-shrink-0 mt-0.5" />
        <p className="text-blue-700 dark:text-blue-300">
          Taken zijn alleen zichtbaar voor jou
        </p>
      </div>

      {/* Filter controls - now includes Awaiting as a primary tab */}
      <div className="flex flex-wrap items-center gap-3">
        <div className="flex rounded-lg border border-gray-200 dark:border-gray-700 p-0.5">
          <button
            onClick={() => setStatusFilter('open')}
            className={`px-3 py-1 text-sm rounded-md transition-colors ${
              statusFilter === 'open' ? 'bg-cyan-100 dark:bg-deep-midnight text-bright-cobalt dark:text-electric-cyan-light' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200'
            }`}
          >
            Te doen
          </button>
          <button
            onClick={() => setStatusFilter('awaiting')}
            className={`px-3 py-1 text-sm rounded-md transition-colors flex items-center gap-1 ${
              statusFilter === 'awaiting' ? 'bg-orange-100 dark:bg-orange-900/30 text-orange-700 dark:text-orange-300' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200'
            }`}
          >
            <Clock className="w-3.5 h-3.5" />
            Openstaand
          </button>
          <button
            onClick={() => setStatusFilter('completed')}
            className={`px-3 py-1 text-sm rounded-md transition-colors ${
              statusFilter === 'completed' ? 'bg-cyan-100 dark:bg-deep-midnight text-bright-cobalt dark:text-electric-cyan-light' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200'
            }`}
          >
            Afgerond
          </button>
          <button
            onClick={() => setStatusFilter('all')}
            className={`px-3 py-1 text-sm rounded-md transition-colors ${
              statusFilter === 'all' ? 'bg-cyan-100 dark:bg-deep-midnight text-bright-cobalt dark:text-electric-cyan-light' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200'
            }`}
          >
            Alle
          </button>
        </div>
      </div>

      {/* Filtered Todos */}
      <div className="card">
        <div className="p-4 border-b border-gray-200 dark:border-gray-700">
          <h2 className="font-semibold flex items-center">
            {getHeaderIcon()}
            {getHeaderText()} ({filteredTodos.length})
          </h2>
        </div>
        {filteredTodos.length > 0 ? (
          <div className="divide-y divide-gray-100 dark:divide-gray-700">
            {filteredTodos.map((todo) => (
              <TodoItem
                key={todo.id}
                todo={todo}
                variant="full"
                onToggle={handleToggleTodo}
                onReopen={handleReopen}
                onEdit={(t) => {
                  setEditingTodo(t);
                  setShowTodoModal(true);
                }}
                onDelete={handleDeleteTodo}
              />
            ))}
          </div>
        ) : (
          <div className="p-8 text-center">
            <CheckSquare className="w-12 h-12 text-gray-300 dark:text-gray-600 mx-auto mb-3" />
            <p className="text-gray-500 dark:text-gray-400">{getEmptyMessage()}</p>
            {statusFilter === 'open' && (
              <p className="text-sm text-gray-400 dark:text-gray-500 mt-1">
                Maak taken aan vanaf een ledenpagina of klik op "Taak toevoegen"
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

      {/* Complete Todo Modal - now with three options (awaiting option hidden if already awaiting) */}
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
        hideAwaitingOption={todoToComplete?.status === 'awaiting'}
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
    </PullToRefreshWrapper>
  );
}
