import { getBoardSummary } from '@/utils/roleDashboard';

/** Reuse the permission-filtered workspace payload; hidden blocks stay hidden here too. */
export default function BoardSummary({ data, visibleBlocks }) {
  const items = getBoardSummary(data, visibleBlocks);
  if (!items.length) return null;

  return <dl className="dashboard-summary" aria-label="De club in cijfers">
    {items.map(item => <div key={item.label}>
      <dt>{item.label}</dt>
      <dd className={item.positive ? 'dashboard-summary-value is-positive' : 'dashboard-summary-value'}>{item.positive ? '+' : ''}{item.value.toLocaleString('nl-NL')}</dd>
      <dd className="dashboard-summary-context">{item.description}</dd>
    </div>)}
  </dl>;
}
