import { useState } from 'react';
import { Link } from 'react-router-dom';
import { CheckSquare, Square, Pencil, Trash2, Plus, Filter } from 'lucide-react';
import { useTodos, useUpdateTodo, useDeleteTodo } from '@/hooks/useDashboard';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';
import { format } from 'date-fns';
import { isTodoOverdue } from '@/utils/timeline';
import TodoModal from '@/components/Timeline/TodoModal';
import GlobalTodoModal from '@/components/Timeline/GlobalTodoModal';

export default function TodosList() {
  useDocumentTitle('Todos');
  
  const [showCompleted, setShowCompleted] = useState(false);
  const [editingTodo, setEditingTodo] = useState(null);
  const [showTodoModal, setShowTodoModal] = useState(false);
  const [showGlobalTodoModal, setShowGlobalTodoModal] = useState(false);
  
  const { data: todos, isLoading } = useTodos(showCompleted);
  const updateTodo = useUpdateTodo();
  const deleteTodo = useDeleteTodo();
  
  const handleToggleTodo = (todo) => {
    updateTodo.mutate({
      todoId: todo.id,
      data: {
        content: todo.content,
        due_date: todo.due_date,
        is_completed: !todo.is_completed,
      },
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
  
  if (isLoading) {
    return (
      <div className="flex items-center justify-center h-64">
        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600"></div>
      </div>
    );
  }
  
  // Group todos by status
  const incompleteTodos = todos?.filter(t => !t.is_completed) || [];
  const completedTodos = todos?.filter(t => t.is_completed) || [];
  
  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <h1 className="text-2xl font-bold">Todos</h1>
        <div className="flex items-center gap-2">
          <button
            onClick={() => setShowCompleted(!showCompleted)}
            className={`btn-secondary text-sm flex items-center gap-2 ${showCompleted ? 'bg-primary-50 border-primary-300' : ''}`}
          >
            <Filter className="w-4 h-4" />
            {showCompleted ? 'Hide completed' : 'Show completed'}
          </button>
          <button
            onClick={() => setShowGlobalTodoModal(true)}
            className="btn-primary text-sm flex items-center gap-2"
          >
            <Plus className="w-4 h-4" />
            Add todo
          </button>
        </div>
      </div>
      
      {/* Incomplete Todos */}
      <div className="card">
        <div className="p-4 border-b border-gray-200">
          <h2 className="font-semibold flex items-center">
            <Square className="w-5 h-5 mr-2 text-gray-500" />
            Open todos ({incompleteTodos.length})
          </h2>
        </div>
        {incompleteTodos.length > 0 ? (
          <div className="divide-y divide-gray-100">
            {incompleteTodos.map((todo) => (
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
            <p className="text-gray-500">No open todos</p>
            <p className="text-sm text-gray-400 mt-1">
              Create todos from a person's detail page
            </p>
          </div>
        )}
      </div>
      
      {/* Completed Todos */}
      {showCompleted && completedTodos.length > 0 && (
        <div className="card">
          <div className="p-4 border-b border-gray-200">
            <h2 className="font-semibold flex items-center">
              <CheckSquare className="w-5 h-5 mr-2 text-primary-600" />
              Completed todos ({completedTodos.length})
            </h2>
          </div>
          <div className="divide-y divide-gray-100">
            {completedTodos.map((todo) => (
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
        </div>
      )}
      
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

