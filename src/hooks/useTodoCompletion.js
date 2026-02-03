import { useState, useCallback } from 'react';
import { useUpdateTodo } from '@/hooks/useDashboard.js';
import { useCreateActivity } from '@/hooks/usePeople.js';

/**
 * Custom hook to manage todo completion workflow including:
 * - Marking todos as awaiting, completed, or converting to activity
 * - Managing modal state for the completion flow
 * - Handling the activity creation when completing as activity
 *
 * @returns {Object} Todo completion state and handlers
 */
export function useTodoCompletion() {
  const updateTodo = useUpdateTodo();
  const createActivity = useCreateActivity();

  // State for complete todo flow
  const [todoToComplete, setTodoToComplete] = useState(null);
  const [showCompleteModal, setShowCompleteModal] = useState(false);
  const [showActivityModal, setShowActivityModal] = useState(false);
  const [activityInitialData, setActivityInitialData] = useState(null);

  // State for viewing/editing todos
  const [todoToView, setTodoToView] = useState(null);
  const [showTodoModal, setShowTodoModal] = useState(false);

  /**
   * Handle toggling a todo's completion state.
   * Opens the completion modal for open/awaiting todos,
   * or reopens completed todos directly.
   */
  const handleToggleTodo = useCallback((todo) => {
    if (todo.status === 'open' || todo.status === 'awaiting') {
      setTodoToComplete(todo);
      setShowCompleteModal(true);
      return;
    }

    if (todo.status === 'completed') {
      updateTodo.mutate({
        todoId: todo.id,
        data: { status: 'open' },
      });
    }
  }, [updateTodo]);

  /**
   * Mark the current todo as awaiting response.
   */
  const handleMarkAwaiting = useCallback(() => {
    if (!todoToComplete) return;

    updateTodo.mutate({
      todoId: todoToComplete.id,
      data: { status: 'awaiting' },
    });

    setShowCompleteModal(false);
    setTodoToComplete(null);
  }, [todoToComplete, updateTodo]);

  /**
   * Mark the current todo as completed without creating an activity.
   */
  const handleJustComplete = useCallback(() => {
    if (!todoToComplete) return;

    updateTodo.mutate({
      todoId: todoToComplete.id,
      data: { status: 'completed' },
    });

    setShowCompleteModal(false);
    setTodoToComplete(null);
  }, [todoToComplete, updateTodo]);

  /**
   * Start the flow to complete the todo and create an activity.
   * Opens the activity modal with prefilled data.
   */
  const handleCompleteAsActivity = useCallback(() => {
    if (!todoToComplete) return;

    const today = new Date().toISOString().split('T')[0];
    setActivityInitialData({
      content: todoToComplete.content,
      activity_date: today,
      activity_type: 'note',
      participants: [],
    });

    setShowCompleteModal(false);
    setShowActivityModal(true);
  }, [todoToComplete]);

  /**
   * Create an activity and mark the todo as completed.
   */
  const handleCreateActivity = useCallback(async (data) => {
    if (!todoToComplete) return;

    try {
      await createActivity.mutateAsync({
        personId: todoToComplete.person_id,
        data,
      });

      await updateTodo.mutateAsync({
        todoId: todoToComplete.id,
        data: { status: 'completed' },
      });

      setShowActivityModal(false);
      setTodoToComplete(null);
      setActivityInitialData(null);
    } catch {
      alert('Activiteit aanmaken mislukt. Probeer het opnieuw.');
    }
  }, [todoToComplete, createActivity, updateTodo]);

  /**
   * Open the todo detail modal to view/edit a todo.
   */
  const handleViewTodo = useCallback((todo) => {
    setTodoToView(todo);
    setShowTodoModal(true);
  }, []);

  /**
   * Update a todo from the detail modal.
   */
  const handleUpdateTodo = useCallback((data) => {
    if (!todoToView) return;

    updateTodo.mutate(
      {
        todoId: todoToView.id,
        data,
      },
      {
        onSuccess: () => {
          setShowTodoModal(false);
          setTodoToView(null);
        },
      }
    );
  }, [todoToView, updateTodo]);

  /**
   * Close the complete modal and reset state.
   */
  const closeCompleteModal = useCallback(() => {
    setShowCompleteModal(false);
    setTodoToComplete(null);
  }, []);

  /**
   * Close the activity modal and reset state.
   */
  const closeActivityModal = useCallback(() => {
    setShowActivityModal(false);
    setTodoToComplete(null);
    setActivityInitialData(null);
  }, []);

  /**
   * Close the todo detail modal and reset state.
   */
  const closeTodoModal = useCallback(() => {
    setShowTodoModal(false);
    setTodoToView(null);
  }, []);

  return {
    // Complete modal state
    todoToComplete,
    showCompleteModal,
    closeCompleteModal,
    handleToggleTodo,
    handleMarkAwaiting,
    handleJustComplete,
    handleCompleteAsActivity,

    // Activity modal state
    showActivityModal,
    closeActivityModal,
    handleCreateActivity,
    activityInitialData,
    isCreatingActivity: createActivity.isPending,

    // Todo detail modal state
    todoToView,
    showTodoModal,
    closeTodoModal,
    handleViewTodo,
    handleUpdateTodo,
    isUpdatingTodo: updateTodo.isPending,
  };
}
