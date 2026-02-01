import { Coins, AlertTriangle, Users, Calendar } from 'lucide-react';
import { usePersonFee } from '@/hooks/useFees';

// Format currency in euros
function formatCurrency(amount, decimals = 0) {
  if (amount === null || amount === undefined) return '-';
  return new Intl.NumberFormat('nl-NL', {
    style: 'currency',
    currency: 'EUR',
    minimumFractionDigits: decimals,
    maximumFractionDigits: decimals,
  }).format(amount);
}

// Format percentage
function formatPercentage(rate) {
  return `${Math.round(rate * 100)}%`;
}

// Category labels
const categoryLabels = {
  mini: 'Mini',
  pupil: 'Pupil',
  junior: 'Junior',
  senior: 'Senior',
  recreant: 'Recreant',
  donateur: 'Donateur',
};

/**
 * Finances card showing membership fee details for a person
 */
export default function FinancesCard({ personId }) {
  const { data: feeData, isLoading } = usePersonFee(personId);

  // Don't render if loading or no data
  if (isLoading) {
    return (
      <div className="card p-6 mb-4">
        <div className="flex items-center gap-2 mb-3">
          <Coins className="w-5 h-5 text-gray-400" />
          <h2 className="font-semibold">Financieel</h2>
        </div>
        <div className="animate-pulse space-y-2">
          <div className="h-4 bg-gray-200 dark:bg-gray-700 rounded w-3/4"></div>
          <div className="h-4 bg-gray-200 dark:bg-gray-700 rounded w-1/2"></div>
        </div>
      </div>
    );
  }

  // Person not calculable - don't show card
  if (!feeData?.calculable) {
    return null;
  }

  const hasDiscount = feeData.family_discount_rate > 0;
  const hasProrata = feeData.prorata_percentage < 1.0;
  const hasNikkiData = feeData.nikki_total !== null;

  return (
    <div className="card p-6 mb-4">
      {/* Header */}
      <div className="flex items-center justify-between mb-4">
        <div className="flex items-center gap-2">
          <Coins className="w-5 h-5 text-gray-500 dark:text-gray-400" />
          <h2 className="font-semibold">Financieel</h2>
        </div>
        <span className="text-xs text-gray-400">{feeData.season}</span>
      </div>

      {/* Financial Block Warning */}
      {feeData.financiele_blokkade && (
        <div className="flex items-center gap-2 p-2 mb-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg">
          <AlertTriangle className="w-4 h-4 text-red-600 dark:text-red-400 flex-shrink-0" />
          <span className="text-sm font-medium text-red-700 dark:text-red-300">
            Financiele blokkade
          </span>
        </div>
      )}

      {/* Fee Breakdown */}
      <div className="space-y-3">
        {/* Category & Base Fee */}
        <div className="flex justify-between items-center">
          <span className="text-sm text-gray-500 dark:text-gray-400">
            {categoryLabels[feeData.category] || feeData.category}
            {feeData.leeftijdsgroep && (
              <span className="text-xs ml-1 text-gray-400">({feeData.leeftijdsgroep})</span>
            )}
          </span>
          <span className="text-sm font-medium">{formatCurrency(feeData.base_fee)}</span>
        </div>

        {/* Family Discount */}
        {hasDiscount && (
          <div className="flex justify-between items-center">
            <span className="text-sm text-gray-500 dark:text-gray-400 flex items-center gap-1">
              <Users className="w-3.5 h-3.5" />
              Gezinskorting ({formatPercentage(feeData.family_discount_rate)})
            </span>
            <span className="text-sm text-green-600 dark:text-green-400">
              -{formatCurrency(feeData.family_discount_amount)}
            </span>
          </div>
        )}

        {/* Family Info */}
        {feeData.family_size > 1 && (
          <div className="text-xs text-gray-400 dark:text-gray-500 pl-4">
            Positie {feeData.family_position} van {feeData.family_size} in gezin
          </div>
        )}

        {/* Pro-rata */}
        {hasProrata && (
          <div className="flex justify-between items-center">
            <span className="text-sm text-gray-500 dark:text-gray-400 flex items-center gap-1">
              <Calendar className="w-3.5 h-3.5" />
              Pro-rata ({formatPercentage(feeData.prorata_percentage)})
            </span>
            <span className="text-sm text-amber-600 dark:text-amber-400">
              {formatCurrency(feeData.final_fee)}
            </span>
          </div>
        )}

        {/* Final Fee */}
        <div className="flex justify-between items-center pt-2 border-t border-gray-100 dark:border-gray-700">
          <span className="text-sm font-medium text-gray-700 dark:text-gray-300">Contributie</span>
          <span className="text-base font-bold text-gray-900 dark:text-gray-100">
            {formatCurrency(feeData.final_fee)}
          </span>
        </div>

        {/* Nikki Data */}
        {hasNikkiData && (
          <>
            <div className="flex justify-between items-center pt-2 border-t border-gray-100 dark:border-gray-700">
              <span className="text-sm text-gray-500 dark:text-gray-400">Nikki totaal</span>
              <span className="text-sm">{formatCurrency(feeData.nikki_total, 2)}</span>
            </div>
            <div className="flex justify-between items-center">
              <span className="text-sm text-gray-500 dark:text-gray-400">Saldo</span>
              <span className={`text-sm font-medium ${
                feeData.nikki_saldo > 0
                  ? 'text-red-600 dark:text-red-400'
                  : 'text-green-600 dark:text-green-400'
              }`}>
                {formatCurrency(feeData.nikki_saldo, 2)}
              </span>
            </div>
          </>
        )}
      </div>
    </div>
  );
}
