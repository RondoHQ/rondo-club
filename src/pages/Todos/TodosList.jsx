import { useState, useMemo } from 'react';
import { Link } from 'react-router-dom';
import { CheckSquare, Square, Pencil, Trash2, Plus, Clock } from 'lucide-react';
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

  // Filter state
  const [statusFilter, setStatusFilter] = useState('open'); // 'all' | 'open' | 'completed'
  const [awaitingFilter, setAwaitingFilter] = useState(false);

  const [editingTodo, setEditingTodo] = useState(null);
  const [showTodoModal, setShowTodoModal] = useState(false);
  const [showGlobalTodoModal, setShowGlobalTodoModal] = useState(false);

  // State for complete todo flow
  const [todoToComplete, setTodoToComplete] = useState(null);
  const [showCompleteModal, setShowCompleteModal] = useState(false);
  const [showActivityModal, setShowActivityModal] = useState(false);
  const [activityInitialData, setActivityInitialData] = useState(null);

  // Always fetch all todos including completed
  const { data: todos, isLoading } = useTodos(true);
  const updateTodo = useUpdateTodo();
  const deleteTodo = useDeleteTodo();
  const createActivity = useCreateActivity();

  // Filter todos based on status and awaiting filters
  const filteredTodos = useMemo(() => {
    if (!todos) return [];

    return todos.filter(todo => {
      // Status filter
      if (statusFilter === 'open' && todo.is_completed) return false;
      if (statusFilter === 'completed' && !todo.is_completed) return false;

      // Awaiting filter
      if (awaitingFilter && !todo.awaiting_response) return false;

      return true;
    });
  }, [todos, statusFilter, awaitingFilter]);
  
  const handleToggleTodo = (todo) => {
    // If completing a todo, show the complete modal
    if (!todo.is_completed) {
      setTodoToComplete(todo);
      setShowCompleteModal(true);
      return;
    }
    
    // If uncompleting, just update directly
    updateTodo.mutate({
      todoId: todo.id,
      data: {
        content: todo.content,
        due_date: todo.due_date,
        is_completed: false,
      },
    });
  };
  
  const handleJustComplete = () => {
    if (!todoToComplete) return;
    
    updateTodo.mutate({
      todoId: todoToComplete.id,
      data: {
        content: todoToComplete.content,
        due_date: todoToComplete.due_date,
        is_completed: true,
      },
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
        data: {
          content: todoToComplete.content,
          due_date: todoToComplete.due_date,
          is_completed: true,
        },
      });
      
      setShowActivityModal(false);
      setTodoToComplete(null);
      setActivityInitialData(null);
    } catch {
      alert('Failed to create activity. Please try again.');
    }
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
  
  // Get the appropriate header text based on filters
  const getHeaderText = () => {
    if (statusFilter === 'all') return 'All todos';
    if (statusFilter === 'completed') return 'Completed todos';
    return 'Open todos';
  };

  // Get appropriate empty state message
  const getEmptyMessage = () => {
    if (awaitingFilter) {
      if (statusFilter === 'open') return 'No open todos awaiting response';
      if (statusFilter === 'completed') return 'No completed todos were awaiting response';
      return 'No todos awaiting response';
    }
    if (statusFilter === 'completed') return 'No completed todos';
    if (statusFilter === 'open') return 'No open todos';
    return 'No todos found';
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

      {/* Filter controls */}
      <div className="flex flex-wrap items-center gap-3">
        {/* Status filter tabs */}
        <div className="flex rounded-lg border border-gray-200 p-0.5">
          <button
            onClick={() => setStatusFilter('all')}
            className={`px-3 py-1 text-sm rounded-md transition-colors ${
              statusFilter === 'all' ? 'bg-primary-100 text-primary-700' : 'text-gray-600 hover:text-gray-900'
            }`}
          >
            All
          </button>
          <button
            onClick={() => setStatusFilter('open')}
            className={`px-3 py-1 text-sm rounded-md transition-colors ${
              statusFilter === 'open' ? 'bg-primary-100 text-primary-700' : 'text-gray-600 hover:text-gray-900'
            }`}
          >
            Open
          </button>
          <button
            onClick={() => setStatusFilter('completed')}
            className={`px-3 py-1 text-sm rounded-md transition-colors ${
              statusFilter === 'completed' ? 'bg-primary-100 text-primary-700' : 'text-gray-600 hover:text-gray-900'
            }`}
          >
            Completed
          </button>
        </div>

        {/* Awaiting response toggle */}
        <button
          onClick={() => setAwaitingFilter(!awaitingFilter)}
          className={`flex items-center gap-1.5 px-3 py-1.5 text-sm rounded-lg border transition-colors ${
            awaitingFilter
              ? 'bg-orange-50 border-orange-200 text-orange-700'
              : 'border-gray-200 text-gray-600 hover:border-gray-300'
          }`}
        >
          <Clock className="w-4 h-4" />
          Awaiting
        </button>
      </div>

      {/* Filtered Todos */}
      <div className="card">
        <div className="p-4 border-b border-gray-200">
          <h2 className="font-semibold flex items-center">
            {statusFilter === 'completed' ? (
              <CheckSquare className="w-5 h-5 mr-2 text-primary-600" />
            ) : (
              <Square className="w-5 h-5 mr-2 text-gray-500" />
            )}
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
            {statusFilter === 'open' && !awaitingFilter && (
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
      
      {/* Complete Todo Modal */}
      <CompleteTodoModal
        isOpen={showCompleteModal}
        onClose={() => {
          setShowCompleteModal(false);
          setTodoToComplete(null);
        }}
        todo={todoToComplete}
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

function TodoItem({ todo, onToggle, onEdit, onDelete }) {
  const isOverdue = isTodoOverdue(todo);
  
  return (
    <div className="flex items-start p-4 hover:bg-gray-50 transition-colors group">
      <button
        onClick={() => onToggle(todo)}
        className="mt-0.5 mr-3 flex-shrink-0"
        title={todo.is_completed ? 'Mark as incomplete' : 'Mark as complete'}
      >
        {todo.is_completed ? (
          <CheckSquare className="w-5 h-5 text-primary-600" />
        ) : (
          <Square className={`w-5 h-5 ${isOverdue ? 'text-red-600' : 'text-gray-400'}`} />
        )}
      </button>
      
      <div className="flex-1 min-w-0">
        <p className={`text-sm ${todo.is_completed ? 'line-through text-gray-400' : isOverdue ? 'text-red-600 font-medium' : 'text-gray-900'}`}>
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
          
          {todo.due_date && (
            <span className={`text-xs ${isOverdue && !todo.is_completed ? 'text-red-600 font-medium' : 'text-gray-500'}`}>
              Due: {format(new Date(todo.due_date), 'MMM d, yyyy')}
              {isOverdue && !todo.is_completed && ' (overdue)'}
            </span>
          )}

          {/* Awaiting response indicator */}
          {todo.awaiting_response && (
            <span className={`text-xs px-2 py-0.5 rounded-full flex items-center gap-1 ${getAwaitingUrgencyClass(getAwaitingDays(todo))}`}>
              <Clock className="w-3 h-3" />
              {getAwaitingDays(todo) === 0 ? 'Awaiting today' : `Awaiting ${getAwaitingDays(todo)}d`}
            </span>
          )}
        </div>
      </div>
      
      <div className="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity ml-2">
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

