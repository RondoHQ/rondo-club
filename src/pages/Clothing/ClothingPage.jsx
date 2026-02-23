import { useMemo, useState } from 'react';
import { Download, Plus, Shirt, RotateCcw } from 'lucide-react';
import TabButton from '@/components/TabButton';
import { usePeople } from '@/hooks/usePeople';
import {
  useClothingAssignments,
  useClothingItems,
  useClothingOverview,
  useCreateClothingAssignment,
  useCreateClothingItem,
  useDeleteClothingItem,
  useClothingSettings,
} from '@/hooks/useClothing';
import { prmApi } from '@/api/client';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';

const CONDITION_OPTIONS = ['new', 'good', 'fair'];

export default function ClothingPage() {
  useDocumentTitle('Kleding');

  const { data: people = [] } = usePeople();
  const { data: items = [], isLoading: loadingItems } = useClothingItems();
  const { data: assignments = [], isLoading: loadingAssignments } = useClothingAssignments();
  const { data: overview } = useClothingOverview();
  const { data: settings } = useClothingSettings();

  const createItem = useCreateClothingItem();
  const deleteItem = useDeleteClothingItem();
  const createAssignment = useCreateClothingAssignment();

  const [activeTab, setActiveTab] = useState('overview');

  const [itemForm, setItemForm] = useState({
    name: '',
    brand: '',
    category: '',
    available_sizes: '',
    season: '',
    color: '',
    unit_cost: '',
    deposit_amount: '',
    active: true,
  });

  const [assignmentForm, setAssignmentForm] = useState({
    person_id: '',
    item_id: '',
    size: '',
    condition: 'good',
    in_or_out: 'out',
    date: new Date().toISOString().slice(0, 10),
    season: '',
    deposit_paid: false,
    deposit_returned: false,
    notes: '',
    override_reason: '',
  });

  const [personSearch, setPersonSearch] = useState('');

  const itemMap = useMemo(() => {
    const map = new Map();
    items.forEach((item) => map.set(item.id, item));
    return map;
  }, [items]);

  const sortedPeople = useMemo(
    () => [...people].sort((a, b) => (a.name || '').localeCompare(b.name || 'nl')),
    [people]
  );

  const personOptions = useMemo(
    () => sortedPeople.map((person) => ({ id: person.id, label: `${person.name} (${person.id})` })),
    [sortedPeople]
  );

  const handleCreateItem = async (e) => {
    e.preventDefault();
    try {
      await createItem.mutateAsync({
        ...itemForm,
        category: itemForm.category.split(',').map((v) => v.trim()).filter(Boolean),
        available_sizes: itemForm.available_sizes.split(',').map((v) => v.trim()).filter(Boolean),
        unit_cost: itemForm.unit_cost ? parseFloat(itemForm.unit_cost) : 0,
        deposit_amount: itemForm.deposit_amount ? parseFloat(itemForm.deposit_amount) : 0,
      });
      setItemForm({
        name: '',
        brand: '',
        category: '',
        available_sizes: '',
        season: '',
        color: '',
        unit_cost: '',
        deposit_amount: '',
        active: true,
      });
    } catch (error) {
      alert(error?.response?.data?.message || 'Kledingitem kon niet worden opgeslagen.');
    }
  };

  const handleCreateAssignment = async (e) => {
    e.preventDefault();
    if (!assignmentForm.person_id) {
      alert('Kies een lid uit de suggestielijst.');
      return;
    }

    try {
      await createAssignment.mutateAsync({
        ...assignmentForm,
        person_id: parseInt(assignmentForm.person_id, 10),
        item_id: parseInt(assignmentForm.item_id, 10),
        season: assignmentForm.season || settings?.current_season || '',
      });
      setAssignmentForm((prev) => ({
        ...prev,
        notes: '',
        override_reason: '',
      }));
    } catch (error) {
      alert(error?.response?.data?.message || 'Transactie kon niet worden opgeslagen.');
    }
  };

  const handleExport = async () => {
    try {
      const response = await prmApi.exportClothingCsv();
      const blob = new Blob([response.data], { type: 'text/csv;charset=utf-8;' });
      const url = URL.createObjectURL(blob);
      const link = document.createElement('a');
      link.href = url;
      link.setAttribute('download', 'kleding-overzicht.csv');
      document.body.appendChild(link);
      link.click();
      link.remove();
      URL.revokeObjectURL(url);
    } catch {
      alert('Export mislukt.');
    }
  };

  const handlePersonSearchChange = (value) => {
    setPersonSearch(value);

    const match = value.match(/\((\d+)\)\s*$/);
    if (match) {
      setAssignmentForm((prev) => ({ ...prev, person_id: match[1] }));
    } else {
      setAssignmentForm((prev) => ({ ...prev, person_id: '' }));
    }
  };

  return (
    <div className="space-y-6">
      <div className="border-b border-gray-200 dark:border-gray-700">
        <nav className="-mb-px flex space-x-8" aria-label="Kleding tabs">
          <TabButton label="Overzicht" isActive={activeTab === 'overview'} onClick={() => setActiveTab('overview')} />
          <TabButton label="Items" isActive={activeTab === 'items'} onClick={() => setActiveTab('items')} />
          <TabButton label="Transacties" isActive={activeTab === 'transactions'} onClick={() => setActiveTab('transactions')} />
        </nav>
      </div>

      {activeTab === 'overview' && (
        <div className="space-y-6">
          <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div className="card p-4">
              <p className="text-xs text-gray-500">Items</p>
              <p className="text-2xl font-semibold">{overview?.total_items ?? 0}</p>
            </div>
            <div className="card p-4">
              <p className="text-xs text-gray-500">Uitgegeven</p>
              <p className="text-2xl font-semibold">{overview?.distributed ?? 0}</p>
            </div>
            <div className="card p-4">
              <p className="text-xs text-gray-500">Ingenomen</p>
              <p className="text-2xl font-semibold">{overview?.returned ?? 0}</p>
            </div>
            <div className="card p-4">
              <p className="text-xs text-gray-500">Openstaand</p>
              <p className="text-2xl font-semibold">{overview?.outstanding ?? 0}</p>
            </div>
          </div>

          <div className="card p-6">
            <h2 className="font-semibold text-brand-gradient mb-3">Regels</h2>
            <p className="text-sm text-gray-600 dark:text-gray-300">
              Eligibility beheer je via <strong>Instellingen → Kleding</strong>.
            </p>
          </div>
        </div>
      )}

      {activeTab === 'items' && (
        <div className="space-y-6">
          <form onSubmit={handleCreateItem} className="card p-6 space-y-3">
            <h2 className="font-semibold text-brand-gradient flex items-center gap-2">
              <Shirt className="w-5 h-5" />
              Kledingitem toevoegen
            </h2>
            <input className="input" placeholder="Naam" value={itemForm.name} onChange={(e) => setItemForm((v) => ({ ...v, name: e.target.value }))} required />
            <input className="input" placeholder="Merk" value={itemForm.brand} onChange={(e) => setItemForm((v) => ({ ...v, brand: e.target.value }))} />
            <input className="input" placeholder="Categorieen (comma)" value={itemForm.category} onChange={(e) => setItemForm((v) => ({ ...v, category: e.target.value }))} />
            <input className="input" placeholder="Maten (bijv. S,M,L,152)" value={itemForm.available_sizes} onChange={(e) => setItemForm((v) => ({ ...v, available_sizes: e.target.value }))} />
            <div className="grid grid-cols-2 gap-3">
              <input className="input" placeholder="Seizoen" value={itemForm.season} onChange={(e) => setItemForm((v) => ({ ...v, season: e.target.value }))} />
              <input className="input" placeholder="Kleur" value={itemForm.color} onChange={(e) => setItemForm((v) => ({ ...v, color: e.target.value }))} />
            </div>
            <div className="grid grid-cols-2 gap-3">
              <input type="number" step="0.01" className="input" placeholder="Inkoopprijs" value={itemForm.unit_cost} onChange={(e) => setItemForm((v) => ({ ...v, unit_cost: e.target.value }))} />
              <input type="number" step="0.01" className="input" placeholder="Borg" value={itemForm.deposit_amount} onChange={(e) => setItemForm((v) => ({ ...v, deposit_amount: e.target.value }))} />
            </div>
            <button type="submit" className="btn-primary text-sm" disabled={createItem.isPending}>
              <Plus className="w-4 h-4 mr-1" />
              Item opslaan
            </button>
          </form>

          <div className="card p-6 overflow-auto">
            <h2 className="font-semibold text-brand-gradient mb-4">Kledingitems</h2>
            {loadingItems ? (
              <p className="text-sm text-gray-500">Laden...</p>
            ) : (
              <table className="w-full text-sm">
                <thead>
                  <tr className="text-left border-b border-gray-200 dark:border-gray-700">
                    <th className="py-2">Naam</th>
                    <th className="py-2">Categorie</th>
                    <th className="py-2">Maten</th>
                    <th className="py-2">Borg</th>
                    <th className="py-2"></th>
                  </tr>
                </thead>
                <tbody>
                  {items.map((item) => (
                    <tr key={item.id} className="border-b border-gray-100 dark:border-gray-800">
                      <td className="py-2">{item.name}</td>
                      <td className="py-2">{(item.category || []).join(', ')}</td>
                      <td className="py-2">{(item.available_sizes || []).join(', ')}</td>
                      <td className="py-2">{item.deposit_amount || 0}</td>
                      <td className="py-2 text-right">
                        <button
                          type="button"
                          onClick={() => {
                            if (window.confirm('Item verwijderen?')) {
                              deleteItem.mutate(item.id);
                            }
                          }}
                          className="btn-secondary text-xs"
                        >
                          Verwijder
                        </button>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            )}
          </div>
        </div>
      )}

      {activeTab === 'transactions' && (
        <div className="space-y-6">
          <div className="flex justify-end">
            <button onClick={handleExport} className="btn-secondary text-sm">
              <Download className="w-4 h-4 mr-1" />
              Exporteer CSV
            </button>
          </div>

          <form onSubmit={handleCreateAssignment} className="card p-6 space-y-3">
            <h2 className="font-semibold text-brand-gradient flex items-center gap-2">
              <RotateCcw className="w-5 h-5" />
              Uitgifte / inname registreren
            </h2>
            <div>
              <input
                list="clothing-person-options"
                className="input"
                value={personSearch}
                onChange={(e) => handlePersonSearchChange(e.target.value)}
                placeholder="Selecteer lid (typ om te zoeken)"
                required
              />
              <datalist id="clothing-person-options">
                {personOptions.map((person) => (
                  <option key={person.id} value={person.label} />
                ))}
              </datalist>
            </div>
            <select className="input" value={assignmentForm.item_id} onChange={(e) => setAssignmentForm((v) => ({ ...v, item_id: e.target.value }))} required>
              <option value="">Selecteer item</option>
              {items.map((item) => (
                <option key={item.id} value={item.id}>{item.name}</option>
              ))}
            </select>
            <div className="grid grid-cols-2 gap-3">
              <input className="input" placeholder="Maat" value={assignmentForm.size} onChange={(e) => setAssignmentForm((v) => ({ ...v, size: e.target.value }))} required />
              <select className="input" value={assignmentForm.condition} onChange={(e) => setAssignmentForm((v) => ({ ...v, condition: e.target.value }))}>
                {CONDITION_OPTIONS.map((value) => <option key={value} value={value}>{value}</option>)}
              </select>
            </div>
            <div className="grid grid-cols-2 gap-3">
              <select className="input" value={assignmentForm.in_or_out} onChange={(e) => setAssignmentForm((v) => ({ ...v, in_or_out: e.target.value }))}>
                <option value="out">Uit</option>
                <option value="in">In</option>
              </select>
              <input type="date" className="input" value={assignmentForm.date} onChange={(e) => setAssignmentForm((v) => ({ ...v, date: e.target.value }))} />
            </div>
            <div className="grid grid-cols-2 gap-3">
              <input
                className="input"
                placeholder="Seizoen"
                value={assignmentForm.season || settings?.current_season || ''}
                onChange={(e) => setAssignmentForm((v) => ({ ...v, season: e.target.value }))}
              />
              {settings?.eligibility_enabled ? (
                <input className="input" placeholder="Override reden (optioneel)" value={assignmentForm.override_reason} onChange={(e) => setAssignmentForm((v) => ({ ...v, override_reason: e.target.value }))} />
              ) : (
                <div className="input text-gray-500">Eligibility uitgeschakeld</div>
              )}
            </div>
            <textarea className="input min-h-20" placeholder="Notities" value={assignmentForm.notes} onChange={(e) => setAssignmentForm((v) => ({ ...v, notes: e.target.value }))} />
            <button type="submit" className="btn-primary text-sm" disabled={createAssignment.isPending}>
              Opslaan
            </button>
          </form>

          <div className="card p-6 overflow-auto">
            <h2 className="font-semibold text-brand-gradient mb-4">Transacties</h2>
            {loadingAssignments ? (
              <p className="text-sm text-gray-500">Laden...</p>
            ) : (
              <table className="w-full text-sm">
                <thead>
                  <tr className="text-left border-b border-gray-200 dark:border-gray-700">
                    <th className="py-2">Datum</th>
                    <th className="py-2">Lid</th>
                    <th className="py-2">Item</th>
                    <th className="py-2">Maat</th>
                    <th className="py-2">Type</th>
                    <th className="py-2">Conditie</th>
                    <th className="py-2">Seizoen</th>
                  </tr>
                </thead>
                <tbody>
                  {assignments.map((row) => (
                    <tr key={row.id} className="border-b border-gray-100 dark:border-gray-800">
                      <td className="py-2">{row.date}</td>
                      <td className="py-2">{row.person_name}</td>
                      <td className="py-2">{row.item_name || itemMap.get(row.item_id)?.name || row.item_id}</td>
                      <td className="py-2">{row.size}</td>
                      <td className="py-2">{row.in_or_out}</td>
                      <td className="py-2">{row.condition}</td>
                      <td className="py-2">{row.season}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            )}
          </div>
        </div>
      )}
    </div>
  );
}
