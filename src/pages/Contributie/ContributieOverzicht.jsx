import { useState } from 'react';
import { Coins } from 'lucide-react';
import { useFeeSummary } from '@/hooks/useFees';
import { formatCurrency, getCategoryColor } from '@/utils/formatters';
import SeasonSelector from './SeasonSelector';

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
        <SeasonSelector
          season={data?.season}
          isForecast={isForecast}
          onForecastChange={setIsForecast}
          memberCount={data?.total ?? 0}
        />
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
