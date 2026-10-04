import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import api from '@/api/client';

function BankAccountForm({ account, endpoint, queryKey }) {
  const client = useQueryClient();
  const [iban, setIban] = useState(account.iban || '');
  const [holder, setHolder] = useState(account.bank_account_holder || '');
  const save = useMutation({
    mutationFn: () => api.patch(endpoint, { iban: iban || null, bank_account_holder: holder || null }),
    onSuccess: ({ data }) => { client.setQueryData(queryKey, data); setIban(data.iban || ''); setHolder(data.bank_account_holder || ''); },
  });
  return <form onSubmit={(event) => { event.preventDefault(); save.mutate(); }} className="space-y-4">
    <label className="block text-sm">IBAN<input className="input mt-1 w-full" autoComplete="off" value={iban} onChange={(event) => setIban(event.target.value)} disabled={!account.can_edit || save.isPending} /></label>
    <label className="block text-sm">Naam rekeninghouder<input className="input mt-1 w-full" maxLength={70} autoComplete="off" value={holder} onChange={(event) => setHolder(event.target.value)} disabled={!account.can_edit || save.isPending} /></label>
    {save.error ? <p role="alert" className="text-sm text-red-600">{save.error.response?.data?.message || 'Opslaan mislukt.'}</p> : null}
    {save.isSuccess ? <p role="status" className="text-sm text-green-700">Bankgegevens opgeslagen.</p> : null}
    {account.can_edit ? <button className="btn-primary" disabled={save.isPending}>{save.isPending ? 'Opslaan…' : 'Bankgegevens opslaan'}</button> : <p className="text-sm text-gray-500">Dit profiel is alleen-lezen.</p>}
  </form>;
}

export default function BankAccountCard({ personId }) {
  const endpoint = personId ? `/rondo/v1/people/${personId}/bank-account` : '/rondo/v1/user/profile-bank-account';
  const queryKey = ['bank-account', personId || 'self'];
  const query = useQuery({ queryKey, queryFn: () => api.get(endpoint).then(({ data }) => data), retry: false, gcTime: 0 });
  return <section className="card p-6 mt-4">
    <h2 className="text-lg font-semibold mb-4">Bankgegevens</h2>
    {query.isPending ? <p role="status">Laden…</p> : query.error ? <p role="alert">{query.error.response?.data?.message || 'Bankgegevens konden niet worden geladen.'}</p> : <BankAccountForm account={query.data} endpoint={endpoint} queryKey={queryKey} />}
  </section>;
}
