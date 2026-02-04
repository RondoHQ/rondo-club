/**
 * Reusable loading spinner component.
 * Standardizes spinner appearance across the application.
 *
 * @param {Object} props
 * @param {string} [props.size='md'] - Size variant: 'sm', 'md', or 'lg'
 * @param {string} [props.className] - Additional CSS classes
 */
export default function LoadingSpinner({ size = 'md', className = '' }) {
  const sizeClasses = {
    sm: 'h-4 w-4',
    md: 'h-8 w-8',
    lg: 'h-12 w-12',
  };

  return (
    <div
      className={`animate-spin rounded-full border-b-2 border-accent-600 dark:border-accent-400 ${sizeClasses[size]} ${className}`}
    />
  );
}

/**
 * Full-page loading spinner with centered layout.
 * Use for route-level loading states.
 */
export function PageLoadingSpinner() {
  return (
    <div className="flex items-center justify-center min-h-screen bg-gray-50">
      <LoadingSpinner />
    </div>
  );
}

/**
 * Centered loading spinner for content areas.
 * Use for partial-page loading states.
 *
 * @param {Object} props
 * @param {string} [props.minHeight='50vh'] - Minimum height of the container
 */
export function ContentLoadingSpinner({ minHeight = '50vh' }) {
  return (
    <div className="flex items-center justify-center" style={{ minHeight }}>
      <LoadingSpinner />
    </div>
  );
}
