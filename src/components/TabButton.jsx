/**
 * Reusable tab button component for navigation tabs.
 * Provides consistent styling for active/inactive states.
 */

/**
 * Props for TabButton component.
 * @typedef {Object} TabButtonProps
 * @property {string} label - Text label for the tab
 * @property {boolean} isActive - Whether this tab is currently active
 * @property {Function} onClick - Click handler for the tab
 */

/**
 * TabButton component displays a navigation tab with consistent styling.
 *
 * @param {TabButtonProps} props
 */
export default function TabButton({ label, isActive, onClick }) {
  return (
    <button
      onClick={onClick}
      className={`pb-3 text-sm font-medium border-b-2 transition-colors ${
        isActive
          ? 'border-accent-600 text-accent-600'
          : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 hover:border-gray-300'
      }`}
    >
      {label}
    </button>
  );
}
