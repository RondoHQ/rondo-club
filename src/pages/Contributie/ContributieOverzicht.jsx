import { useEffect, useState } from 'react';
import { Coins, FileText, Loader2 } from 'lucide-react';
import { useFeeSummary, useBulkInvoiceJob, feeKeys } from '@/hooks/useFees';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { prmApi } from '@/api/client';
import { formatCurrency, getCategoryColor } from '@/utils/formatters';
import SeasonSelector from './SeasonSelector';

export function ContributieOverzicht() {
  const [isForecast, setIsForecast] = useState(false);
  const queryClient = useQueryClient();

  const config = window.rondoConfig || {};
  const isAdmin = config.isAdmin || false;

  const { data, isLoading, error } = useFeeSummary(
    isForecast ? { forecast: true } : {}
  );

  // Poll bulk invoice job status
  const { data: jobStatus } = useBulkInvoiceJob(isAdmin);

  // Read billing method from fee summary
  const billingMethod = data?.billing_method ?? 'nikki';
  const pendingInvoiceCount = data?.pending_invoice_count ?? 0;

  // Mutation to start bulk invoice job
  const startBulkJob = useMutation({
    mutationFn: () => prmApi.startBulkInvoiceJob({ season: data?.season, confirmed: true }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: feeKeys.bulkJob });
    },
  });

  useEffect(() => {
    if (jobStatus?.status === 'done') {
      queryClient.invalidateQueries({ queryKey: feeKeys.summary({}) });
      queryClient.invalidateQueries({ queryKey: feeKeys.list({}) });
      queryClient.invalidateQueries({ queryKey: ['invoices'] });
    }
  }, [jobStatus?.status, jobStatus?.finished_at, queryClient]);

  const handleStartBulkJob = () => {
    const memberLabel = pendingInvoiceCount === 1 ? '1 lid' : `${pendingInvoiceCount} leden`;
    const confirmed = window.confirm(
      `Weet je zeker dat je voor ${memberLabel} in seizoen ${data?.season} een conceptfactuur wilt aanmaken? ` +
      'Rondo maakt voor alle leden die nog geen contributiefactuur hebben een conceptfactuur aan.'
    );

    if (confirmed) {
      startBulkJob.mutate();
    }
  };

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
      feeAfterDiscount: acc.feeAfterDiscount + (agg.fee_after_discount ?? 0),
      prorataAmount: acc.prorataAmount + (agg.prorata_amount ?? 0),
      finalFee: acc.finalFee + agg.final_fee,
    }),
    { count: 0, baseFee: 0, familyDiscount: 0, feeAfterDiscount: 0, prorataAmount: 0, finalFee: 0 }
  );

  return (
    <div className="space-y-4">
      {/* Season selector and member count */}
      <div className="flex items-center justify-between flex-wrap gap-3">
        <SeasonSelector
          season={data?.season}
          isForecast={isForecast}
          onForecastChange={setIsForecast}
          memberCount={data?.total ?? 0}
        />
        {/* Bulk invoice creation button */}
        {isAdmin && billingMethod === 'rondo' && !isForecast && (pendingInvoiceCount > 0 || jobStatus?.status === 'running') && (
          <button
            onClick={handleStartBulkJob}
            disabled={jobStatus?.status === 'running' || startBulkJob.isPending}
            className="btn-primary gap-2"
          >
            {(jobStatus?.status === 'running' || startBulkJob.isPending) ? (
              <Loader2 className="w-4 h-4 animate-spin" />
            ) : (
              <FileText className="w-4 h-4" />
            )}
            Maak facturen{pendingInvoiceCount > 0 ? ` (${pendingInvoiceCount})` : ''}
          </button>
        )}
      </div>

      {/* Bulk job progress */}
      {jobStatus && ['running', 'error'].includes(jobStatus.status) && !isForecast && (
        <div className="card p-4">
          {jobStatus.status === 'running' && (
            <div className="flex items-center gap-3">
              <Loader2 className="w-5 h-5 animate-spin text-electric-cyan" />
              <span className="text-sm text-gray-700 dark:text-gray-300">
                {jobStatus.created + jobStatus.skipped} van {jobStatus.total} facturen verwerkt
                ({jobStatus.created} aangemaakt, {jobStatus.skipped} overgeslagen)
              </span>
            </div>
          )}
          {jobStatus.status === 'error' && (
            <div className="text-sm text-red-600 dark:text-red-400">
              Er is een fout opgetreden bij het aanmaken van facturen.
            </div>
          )}
        </div>
      )}

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
        <div
          className="card !overflow-x-auto"
          data-horizontal-scroll="true"
        >
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
                  Na korting
                </th>
                <th scope="col" className="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider bg-gray-50 dark:bg-gray-800">
                  Pro-rata
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
                  <td className="px-4 py-3 text-sm text-gray-500 dark:text-gray-400 text-right">
                    {formatCurrency(agg.fee_after_discount, 2)}
                  </td>
                  <td className="px-4 py-3 text-sm text-gray-500 dark:text-gray-400 text-right">
                    {agg.prorata_amount > 0 ? `- ${formatCurrency(agg.prorata_amount, 2)}` : formatCurrency(0, 2)}
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
                <td className="px-4 py-3 text-sm text-gray-500 dark:text-gray-400 text-right">
                  {formatCurrency(grandTotal.feeAfterDiscount, 2)}
                </td>
                <td className="px-4 py-3 text-sm text-gray-500 dark:text-gray-400 text-right">
                  {grandTotal.prorataAmount > 0 ? `- ${formatCurrency(grandTotal.prorataAmount, 2)}` : formatCurrency(0, 2)}
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
