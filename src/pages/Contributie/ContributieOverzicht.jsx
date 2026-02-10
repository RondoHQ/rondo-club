import { useState } from 'react';
import { TrendingUp, Coins } from 'lucide-react';
import { useFeeSummary } from '@/hooks/useFees';
import { formatCurrency, getCategoryColor } from '@/utils/formatters';

// Get next season label from current season
function getNextSeasonLabel(currentSeason) {
  const startYear = parseInt(currentSeason.substring(0, 4));
  return `${startYear + 1}-${startYear + 2}`;
}

export function ContributieOverzicht() {
  const [isForecast, setIsForecast] = useState(false);

  const { data, isLoading, error } = useFeeSummary(
    isForecast ? { forecast: true } : {}
  );

  if (isLoading) {
    return (
      <div className="flex items-center justify-center h-64">
        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-electric-cyan dark:border-electric-cyan"></div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="card p-6 text-center">
        <p className="text-red-600 dark:text-red-400">
          Overzicht kon niet worden geladen.
        </p>
      </div>
    );
  }

  const aggregates = data?.aggregates ?? {};
  const categories = data?.categories ?? {};

  // Sort categories by sort_order
  const sortedCategories = Object.entries(aggregates).sort((a, b) => {
    const orderA = categories[a[0]]?.sort_order ?? 999;
    const orderB = categories[b[0]]?.sort_order ?? 999;
    return orderA - orderB;
  });

  // Grand totals
  const grandTotal = sortedCategories.reduce(
    (acc, [, agg]) => ({
      count: acc.count + agg.count,
      baseFee: acc.baseFee + agg.base_fee,
      familyDiscount: acc.familyDiscount + (agg.family_discount ?? 0),
      finalFee: acc.finalFee + agg.final_fee,
    }),
    { count: 0, baseFee: 0, familyDiscount: 0, finalFee: 0 }
  );

  return (
    <div className="space-y-4">
      {/* Season selector and member count */}
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-4">
          <div className="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
            <span>Seizoen:</span>
            <select
              value={isForecast ? 'forecast' : 'current'}
              onChange={(e) => setIsForecast(e.target.value === 'forecast')}
              className="btn-secondary appearance-none pr-12 bg-no-repeat hover:translate-y-0 hover:shadow-none"
              style={{
                backgroundImage: `url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='white'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E")`,
                backgroundSize: '1.25rem',
                backgroundPosition: 'right 0.75rem center',
                width: '220px',
              }}
            >
              <option value="current">{data?.season || '2025-2026'} (huidig)</option>
              <option value="forecast">
                {data?.season ? getNextSeasonLabel(data.season) : '2026-2027'} (prognose)
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
          <div className="text-sm text-gray-500 dark:text-gray-400">
            {data?.total ?? 0} leden
          </div>
        </div>
      </div>

      {/* Category overview table */}
      {sortedCategories.length === 0 ? (
        <div className="card p-6 text-center">
          <div className="flex justify-center mb-4">
            <div className="p-3 bg-gray-100 rounded-full dark:bg-gray-700">
              <Coins className="w-8 h-8 text-gray-400 dark:text-gray-500" />
            </div>
          </div>
          <h3 className="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-2">
            Geen categorieën gevonden
          </h3>
          <p className="text-sm text-gray-600 dark:text-gray-400">
            Er zijn geen leden met een berekenbare contributie.
          </p>
        </div>
      ) : (
        <div className="card overflow-x-auto">
          <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead className="bg-gray-50 dark:bg-gray-800">
              <tr>
                <th scope="col" className="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider bg-gray-50 dark:bg-gray-800">
                  Categorie
                </th>
                <th scope="col" className="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider bg-gray-50 dark:bg-gray-800">
                  Leden
                </th>
                <th scope="col" className="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider bg-gray-50 dark:bg-gray-800">
                  Basis totaal
                </th>
                <th scope="col" className="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider bg-gray-50 dark:bg-gray-800">
                  Familiekorting
                </th>
                <th scope="col" className="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider bg-gray-50 dark:bg-gray-800">
                  Netto totaal
                </th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-200 dark:divide-gray-700">
              {sortedCategories.map(([slug, agg], index) => (
                <tr
                  key={slug}
                  className={`hover:bg-gray-100 dark:hover:bg-gray-700 ${
                    index % 2 === 1 ? 'bg-gray-50 dark:bg-gray-800/50' : 'bg-white dark:bg-gray-800'
                  }`}
                >
                  <td className="px-4 py-3 whitespace-nowrap">
                    <span className={`inline-flex px-2 py-0.5 text-xs rounded-full ${getCategoryColor(categories[slug]?.sort_order)}`}>
                      {categories[slug]?.label ?? slug}
                    </span>
                  </td>
                  <td className="px-4 py-3 text-sm text-gray-700 dark:text-gray-300 text-right">
                    {agg.count}
                  </td>
                  <td className="px-4 py-3 text-sm text-gray-500 dark:text-gray-400 text-right">
                    {formatCurrency(agg.base_fee, 2)}
                  </td>
                  <td className="px-4 py-3 text-sm text-gray-500 dark:text-gray-400 text-right">
                    {agg.family_discount > 0 ? `- ${formatCurrency(agg.family_discount, 2)}` : formatCurrency(0, 2)}
                  </td>
                  <td className="px-4 py-3 text-sm font-medium text-gray-900 dark:text-gray-50 text-right">
                    {formatCurrency(agg.final_fee, 2)}
                  </td>
                </tr>
              ))}
            </tbody>
            <tfoot className="bg-gray-50 dark:bg-gray-800">
              <tr>
                <td className="px-4 py-3 text-sm font-medium text-gray-900 dark:text-gray-100">
                  Totaal
                </td>
                <td className="px-4 py-3 text-sm font-medium text-gray-900 dark:text-gray-100 text-right">
                  {grandTotal.count}
                </td>
                <td className="px-4 py-3 text-sm text-gray-500 dark:text-gray-400 text-right">
                  {formatCurrency(grandTotal.baseFee, 2)}
                </td>
                <td className="px-4 py-3 text-sm text-gray-500 dark:text-gray-400 text-right">
                  {grandTotal.familyDiscount > 0 ? `- ${formatCurrency(grandTotal.familyDiscount, 2)}` : formatCurrency(0, 2)}
                </td>
                <td className="px-4 py-3 text-sm font-bold text-gray-900 dark:text-gray-100 text-right">
                  {formatCurrency(grandTotal.finalFee, 2)}
                </td>
              </tr>
            </tfoot>
          </table>
        </div>
      )}
    </div>
  );
}
