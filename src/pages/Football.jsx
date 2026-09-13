import { ChevronRight } from 'lucide-react';
import { Link } from 'react-router-dom';
import { useCurrentUser } from '@/hooks/useCurrentUser';
import { canAccessFootballItem, footballNavigation } from '@/utils/footballNavigation';

export default function Football() {
  const { data: currentUser } = useCurrentUser();

  return (
    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
      {footballNavigation.filter((item) => canAccessFootballItem(item, currentUser)).map((item) => (
        <Link
          key={item.href}
          to={item.href}
          className="card group flex items-start gap-4 p-5 transition-shadow hover:shadow-md focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-bright-cobalt"
        >
          <div className="shrink-0 rounded-lg bg-cyan-50 p-2 dark:bg-gray-700">
            <item.icon aria-hidden="true" className="h-6 w-6 text-bright-cobalt dark:text-electric-cyan" />
          </div>
          <div className="min-w-0 flex-1">
            <h2 className="font-semibold text-gray-900 dark:text-gray-100">{item.name}</h2>
            <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">{item.description}</p>
          </div>
          <ChevronRight aria-hidden="true" className="mt-2 h-5 w-5 shrink-0 text-gray-400 group-hover:text-bright-cobalt dark:group-hover:text-electric-cyan" />
        </Link>
      ))}
    </div>
  );
}
