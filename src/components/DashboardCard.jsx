import { Link } from 'react-router-dom';
import { ArrowRight } from 'lucide-react';

/**
 * Reusable dashboard card component with consistent header, optional count badge,
 * optional "view all" link, and scrollable content area.
 *
 * @param {Object} props
 * @param {string} props.title - Card title text
 * @param {React.ReactNode} props.icon - Icon component to display before title
 * @param {number} [props.count] - Optional count to display in parentheses
 * @param {string} [props.linkTo] - Optional "view all" link destination
 * @param {string} [props.linkText='Bekijk alles'] - Link text
 * @param {React.ReactNode} [props.headerActions] - Custom header actions (replaces link)
 * @param {string} [props.emptyMessage] - Message to show when children is empty/null
 * @param {React.ReactNode} props.children - Card content
 * @param {React.Ref} [props.contentRef] - Optional ref for the content container
 */
export default function DashboardCard({
  title,
  icon: Icon,
  count,
  linkTo,
  linkText = 'Bekijk alles',
  headerActions,
  emptyMessage,
  children,
  contentRef,
}) {
  const hasContent = children != null && children !== false;

  return (
    <div className="card">
      <div className="flex items-center justify-between p-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50">
        <h2 className="font-semibold flex items-center dark:text-gray-50">
          {Icon && <Icon className="w-5 h-5 mr-2 text-gray-500 dark:text-gray-400" />}
          {title}
          {count != null && count > 0 && (
            <span className="ml-1 text-gray-400 dark:text-gray-500 font-normal">
              ({count})
            </span>
          )}
        </h2>
        {headerActions ? (
          headerActions
        ) : linkTo ? (
          <Link
            to={linkTo}
            className="text-sm text-electric-cyan hover:text-bright-cobalt dark:text-electric-cyan dark:hover:text-electric-cyan-light flex items-center"
          >
            {linkText} <ArrowRight className="w-4 h-4 ml-1" />
          </Link>
        ) : null}
      </div>
      <div
        ref={contentRef}
        className="divide-y divide-gray-100 dark:divide-gray-700 h-[32vh] overflow-y-auto"
      >
        {hasContent ? (
          children
        ) : (
          <p className="p-4 text-sm text-gray-500 dark:text-gray-400 text-center">
            {emptyMessage}
          </p>
        )}
      </div>
    </div>
  );
}
