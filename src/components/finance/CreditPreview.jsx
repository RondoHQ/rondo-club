import { formatCurrency } from '@/utils/formatters';

export default function CreditPreview({ preview }) {
  return preview && <div aria-live="polite" className="border-t border-gray-200 dark:border-gray-700 pt-4 space-y-3">
    {preview.line_items.map((item, index) => <div key={index} className="flex justify-between gap-4 text-sm"><span>{item.description}</span><span className="shrink-0 tabular-nums">{formatCurrency(item.amount, 2)}</span></div>)}
    <p className="font-semibold">Creditbedrag: {formatCurrency(preview.amount, 2)}</p>
    <p className="text-sm text-gray-600 dark:text-gray-300">Je slaat een concept op. Versturen doe je daarna op de factuurpagina. Een eventuele terugbetaling voer je apart uit.</p>
  </div>;
}

