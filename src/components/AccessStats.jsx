import { Users } from 'lucide-react';

export default function AccessStats({ stats, isLoading }) {
  return (
    <div className="card p-5 space-y-4">
      <div className="flex items-center gap-2">
        <Users className="h-5 w-5 text-electric-cyan" />
        <h2 className="font-semibold text-gray-900 dark:text-gray-100">Binnengekomen</h2>
      </div>
      {isLoading && !stats ? (
        <p className="text-sm text-gray-500 dark:text-gray-400">Telling laden…</p>
      ) : (
        <>
          <div className="text-4xl font-bold text-gray-900 dark:text-gray-100">{stats?.total ?? 0}</div>
          <div className="grid grid-cols-2 gap-3 sm:grid-cols-5">
            {(stats?.breakdown || []).map((item) => (
              <div key={item.type} className="rounded-lg bg-gray-50 p-3 dark:bg-gray-800/70">
                <div className="text-2xl font-semibold text-gray-900 dark:text-gray-100">{item.count}</div>
                <div className="text-xs text-gray-500 dark:text-gray-400">{item.label}</div>
              </div>
            ))}
          </div>
        </>
      )}
    </div>
  );
}

