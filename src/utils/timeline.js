import { format, formatDistanceToNow, isToday, isYesterday, isThisWeek, parseISO } from 'date-fns';

/**
 * Group timeline items by date buckets
 * Todos are separated and sorted by due date
 * @param {Array} timeline - Array of timeline items
 * @returns {Object} Grouped timeline items with separate todos section
 */
export function groupTimelineByDate(timeline) {
  if (!timeline || timeline.length === 0) {
    return {
      todos: [],
      today: [],
      yesterday: [],
      thisWeek: [],
      older: [],
    };
  }

  const groups = {
    todos: [],
    today: [],
    yesterday: [],
    thisWeek: [],
    older: [],
  };

  timeline.forEach((item) => {
    // Todos go into their own section
    if (item.type === 'todo') {
      groups.todos.push(item);
      return;
    }

    const itemDate = parseISO(item.created);
    // Combine activity_date and activity_time if both exist
    let displayDateStr = item.activity_date || item.created;
    if (item.activity_date && item.activity_time) {
      displayDateStr = `${item.activity_date}T${item.activity_time}`;
    }
    const displayDate = parseISO(displayDateStr);

    if (isToday(displayDate)) {
      groups.today.push(item);
    } else if (isYesterday(displayDate)) {
      groups.yesterday.push(item);
    } else if (isThisWeek(displayDate)) {
      groups.thisWeek.push(item);
    } else {
      groups.older.push(item);
    }
  });

  // Sort todos: incomplete first (by due date), then completed
  groups.todos.sort((a, b) => {
    // Completed todos go to the bottom
    if (a.is_completed && !b.is_completed) return 1;
    if (!a.is_completed && b.is_completed) return -1;

    // For incomplete todos, sort by due date (earliest first)
    // Todos without due date go after those with due dates
    if (!a.is_completed && !b.is_completed) {
      if (a.due_date && b.due_date) {
        return new Date(a.due_date) - new Date(b.due_date);
      }
      if (a.due_date && !b.due_date) return -1;
      if (!a.due_date && b.due_date) return 1;
    }

    // For completed todos, sort by most recently completed (newest first)
    return new Date(b.created) - new Date(a.created);
  });

  return groups;
}

/**
 * Format timeline date (relative + absolute)
 * @param {string} dateString - ISO date string (can include time as YYYY-MM-DDTHH:MM)
 * @returns {string} Formatted date string
 */
export function formatTimelineDate(dateString) {
  if (!dateString) return '';

  try {
    const date = parseISO(dateString);
    const hasTime = dateString.includes('T') && dateString.length > 10;
    const timeFormat = hasTime ? ' at HH:mm' : '';

    // If it's today, show relative time
    if (isToday(date)) {
      return formatDistanceToNow(date, { addSuffix: true });
    }
    
    // If it's yesterday, show "Yesterday" with optional time
    if (isYesterday(date)) {
      return hasTime ? format(date, `'Yesterday' 'at' HH:mm`) : 'Yesterday';
    }

    // Otherwise show formatted date with optional time
    return format(date, hasTime ? `MMM d, yyyy 'at' HH:mm` : 'MMM d, yyyy');
  } catch (error) {
    return dateString;
  }
}

/**
 * Get activity type icon component name
 * @param {string} type - Activity type
 * @returns {string} Icon name from lucide-react
 */
export function getActivityTypeIcon(type) {
  const iconMap = {
    call: 'Phone',
    email: 'Mail',
    chat: 'MessageCircle',
    meeting: 'Users',
    coffee: 'Coffee',
    lunch: 'Utensils',
    note: 'FileText',
  };

  return iconMap[type] || 'Circle';
}

/**
 * Get activity type human-readable label
 * @param {string} type - Activity type
 * @returns {string} Human-readable label
 */
export function getActivityTypeLabel(type) {
  const labelMap = {
    call: 'Phone call',
    email: 'Email',
    chat: 'Chat',
    meeting: 'Meeting',
    coffee: 'Coffee',
    lunch: 'Lunch',
    note: 'Note',
  };

  return labelMap[type] || type || 'Activity';
}

/**
 * Check if a todo is overdue
 * @param {Object} todo - Todo item
 * @returns {boolean} True if todo is overdue
 */
export function isTodoOverdue(todo) {
  if (!todo.due_date || todo.is_completed) {
    return false;
  }

  try {
    const dueDate = parseISO(todo.due_date);
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    dueDate.setHours(0, 0, 0, 0);

    return dueDate < today;
  } catch (error) {
    return false;
  }
}

/**
 * Get CSS classes for todo status
 * @param {Object} todo - Todo item
 * @returns {string} CSS classes
 */
export function getTodoStatusClass(todo) {
  const classes = [];

  if (todo.is_completed) {
    classes.push('opacity-60', 'line-through');
  } else if (isTodoOverdue(todo)) {
    classes.push('text-red-600', 'font-medium');
  }

  return classes.join(' ');
}

