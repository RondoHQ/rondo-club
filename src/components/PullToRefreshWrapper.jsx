import PullToRefresh from 'react-simple-pull-to-refresh';

/**
 * Wrapper component providing pull-to-refresh with Stadion styling.
 *
 * @param {Function} onRefresh - Async function called on refresh, must return Promise
 * @param {boolean} isPullable - Whether pull-to-refresh is enabled (default: true)
 * @param {React.ReactNode} children - Content to wrap
 */
export default function PullToRefreshWrapper({
  onRefresh,
  isPullable = true,
  children
}) {
  // Stadion-style spinner matching existing loading patterns
  const refreshingContent = (
    <div className="flex justify-center py-4">
      <div className="animate-spin rounded-full h-6 w-6 border-b-2 border-electric-cyan dark:border-electric-cyan" />
    </div>
  );

  // Subtle indicator while pulling - down arrow
  const pullingContent = (
    <div className="flex justify-center py-4 text-gray-400 dark:text-gray-500">
      <svg className="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 14l-7 7m0 0l-7-7m7 7V3" />
      </svg>
    </div>
  );

  return (
    <PullToRefresh
      onRefresh={onRefresh}
      isPullable={isPullable}
      pullDownThreshold={67}
      maxPullDownDistance={95}
      resistance={1}
      refreshingContent={refreshingContent}
      pullingContent={pullingContent}
      className="min-h-full"
    >
      {children}
    </PullToRefresh>
  );
}
