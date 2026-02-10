import { TrendingUp } from 'lucide-react';

function getNextSeasonLabel(currentSeason) {
  const startYear = parseInt(currentSeason.substring(0, 4));
  return `${startYear + 1}-${startYear + 2}`;
}

export default function SeasonSelector({ season, isForecast, onForecastChange, memberCount }) {
  return (
    <div className="flex items-center gap-4">
      <div className="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
        <span>Seizoen:</span>
        <select
          value={isForecast ? 'forecast' : 'current'}
          onChange={(e) => onForecastChange(e.target.value === 'forecast')}
          className="btn-secondary appearance-none pr-12 bg-no-repeat hover:translate-y-0 hover:shadow-none"
          style={{
            backgroundImage: `url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='white'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E")`,
            backgroundSize: '1.25rem',
            backgroundPosition: 'right 0.75rem center',
            width: '220px',
          }}
        >
          <option value="current">{season || '2025-2026'} (huidig)</option>
          <option value="forecast">
            {season ? getNextSeasonLabel(season) : '2026-2027'} (prognose)
          </option>
        </select>
      </div>
      {isForecast && (
        <div className="inline-flex items-center gap-2 px-3 py-1.5 bg-blue-50 border border-blue-200 rounded-lg text-sm text-blue-700 dark:bg-blue-900/30 dark:border-blue-700 dark:text-blue-300">
          <TrendingUp className="w-4 h-4" />
          <span className="font-medium">Prognose</span>
          <span className="text-blue-600 dark:text-blue-400">
            (o.b.v. huidige ledenstand)
          </span>
        </div>
      )}
      {memberCount !== undefined && (
        <div className="text-sm text-gray-500 dark:text-gray-400">
          {memberCount} leden
        </div>
      )}
    </div>
  );
}
