import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { prmApi } from '@/api/client';

const groups = [['unassigned', 'Nog indelen'], ['entree', 'Entree'], ['food', 'Food'], ['non_food', 'Non-food']];

export default function ProductIndeling() {
  const [search, setSearch] = useState('');
  const [filter, setFilter] = useState('all');
  const queryClient = useQueryClient();
  const products = useQuery({ queryKey: ['twelve', 'product-groups'], queryFn: async () => (await prmApi.getTwelve('product-groups')).data.products });
  const save = useMutation({
    mutationFn: (data) => prmApi.setTwelveProductGroup(data),
    onSuccess: async () => { await queryClient.invalidateQueries({ queryKey: ['twelve'] }); },
  });
  const rows = (products.data ?? []).filter(row => row.product.toLocaleLowerCase('nl').includes(search.toLocaleLowerCase('nl')) && (filter === 'all' || row.group === filter));
  const unassigned = (products.data ?? []).filter(row => row.group === 'unassigned').length;

  return <div className="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-5 space-y-4">
    <div><h2 className="text-lg font-semibold">Productindeling</h2><p className="text-sm text-gray-500 dark:text-gray-400 mt-1">Kies per product Entree, Food of Non-food. Wijzigingen worden direct opgeslagen en gelden ook voor eerdere rapporten. Nieuwe productnamen staan onder Nog indelen.</p></div>
    {products.isPending ? <p role="status">Producten laden…</p> : products.isError ? <div><p role="alert">Producten konden niet worden geladen.</p><button type="button" className="btn-secondary mt-3" onClick={() => products.refetch()}>Opnieuw proberen</button></div> : <>
      <div className="flex flex-wrap gap-4 items-end"><label className="text-sm">Zoek product<input type="search" className="input block mt-1" value={search} onChange={event => setSearch(event.target.value)} /></label><label className="text-sm">Indeling<select className="input block mt-1" value={filter} onChange={event => setFilter(event.target.value)}><option value="all">Alle producten</option>{groups.map(([value, label]) => <option key={value} value={value}>{label}</option>)}</select></label><p className="text-sm pb-2">{unassigned} producten nog in te delen</p></div>
      {save.isError && <p role="alert" className="text-sm text-red-700 dark:text-red-300">{save.error?.response?.data?.message || 'Opslaan mislukt. Kies de indeling opnieuw om het nogmaals te proberen.'}</p>}
      <p role="status" className="text-sm text-gray-500 dark:text-gray-400">{save.isPending ? 'Indeling opslaan…' : save.isSuccess ? 'Indeling opgeslagen.' : `${rows.length} producten`}</p>
      <div className="overflow-x-auto"><table className="w-full text-sm"><thead><tr><th className="text-left py-3 border-b border-gray-200 dark:border-gray-700">Product</th><th className="text-left py-3 border-b border-gray-200 dark:border-gray-700">Indeling</th></tr></thead><tbody>{rows.map(row => <tr key={row.id}><td className="py-3 border-b border-gray-100 dark:border-gray-700">{row.product}</td><td className="py-3 border-b border-gray-100 dark:border-gray-700"><select aria-label={`Indeling voor ${row.product}`} className="input" value={row.group} disabled={save.isPending} onChange={event => save.mutate({ id: row.id, group: event.target.value })}>{groups.map(([value, label]) => <option key={value} value={value}>{label}</option>)}</select></td></tr>)}</tbody></table></div>
      {!rows.length && <p className="text-sm">Geen producten gevonden.</p>}
    </>}
  </div>;
}
