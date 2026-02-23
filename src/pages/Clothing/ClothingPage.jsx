import { useEffect, useMemo, useRef, useState } from 'react';
import { Download, Plus, Shirt, RotateCcw, X, Undo2 } from 'lucide-react';
import { useQuery } from '@tanstack/react-query';
import TabButton from '@/components/TabButton';
import { usePerson } from '@/hooks/usePeople';
import { useSearch } from '@/hooks/useDashboard';
import {
  useClothingAssignments,
  useClothingItems,
  useClothingOverview,
  useClothingPersonProfile,
  useCreateClothingAssignment,
  useCreateClothingItem,
  useDeleteClothingItem,
  useClothingSettings,
} from '@/hooks/useClothing';
import { wpApi, prmApi } from '@/api/client';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';

export default function ClothingPage() {
  useDocumentTitle('Kleding');

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

  const [personSearch, setPersonSearch] = useState('');
  const [selectedPersonId, setSelectedPersonId] = useState(null);
  const [isPersonOpen, setIsPersonOpen] = useState(false);
  const [showIssueForm, setShowIssueForm] = useState(false);
  const [issueForm, setIssueForm] = useState({ item_id: '', size: '' });

  const personInputRef = useRef(null);
  const personDropdownRef = useRef(null);

  const itemMap = useMemo(() => {
    const map = new Map();
    items.forEach((item) => map.set(item.id, item));
    return map;
  }, [items]);

  const searchTerm = personSearch.trim();
  const effectiveSearchTerm = searchTerm.length >= 3 ? searchTerm : '';
  const { data: searchResults } = useSearch(effectiveSearchTerm);

  const personOptions = useMemo(() => {
    const results = searchResults?.people || [];
    return results.map((person) => ({
      id: person.id,
      label: person.name || person.title || `Lid ${person.id}`,
    }));
  }, [searchResults]);

  const filteredPersonOptions = useMemo(() => personOptions.slice(0, 20), [personOptions]);

  const { data: selectedPerson } = usePerson(selectedPersonId);

  const selectedPersonTeamId = useMemo(() => {
    const workHistory = selectedPerson?.acf?.work_history;
    if (!Array.isArray(workHistory)) return 0;

    const currentJobWithTeam = workHistory.find((job) => job?.is_current && job?.team);
    if (!currentJobWithTeam?.team) return 0;

    if (typeof currentJobWithTeam.team === 'object') {
      return Number(currentJobWithTeam.team.ID || currentJobWithTeam.team.id || 0);
    }

    return Number(currentJobWithTeam.team || 0);
  }, [selectedPerson]);

  const { data: selectedPersonTeamData } = useQuery({
    queryKey: ['clothing', 'person-team', selectedPersonTeamId],
    queryFn: async () => {
      const response = await wpApi.getEntity(selectedPersonTeamId);
      return response.data;
    },
    enabled: selectedPersonTeamId > 0,
  });

  const teamName = selectedPersonTeamData?.name || 'Geen team gevonden';

  const { data: personProfile } = useClothingPersonProfile(selectedPersonId, {
    enabled: !!selectedPersonId,
  });

  const selectedIssueItem = useMemo(
    () => items.find((item) => String(item.id) === String(issueForm.item_id)),
    [items, issueForm.item_id]
  );

  const sizeOptions = useMemo(() => selectedIssueItem?.available_sizes || [], [selectedIssueItem]);

  useEffect(() => {
    setIssueForm((prev) => {
      if (!prev.size) return prev;
      if (sizeOptions.includes(prev.size)) return prev;
      return { ...prev, size: '' };
    });
  }, [sizeOptions]);

  useEffect(() => {
    const handleClickOutside = (event) => {
      if (
        personDropdownRef.current &&
        !personDropdownRef.current.contains(event.target) &&
        personInputRef.current &&
        !personInputRef.current.contains(event.target)
      ) {
        setIsPersonOpen(false);
      }
    };

    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, []);

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
    setSelectedPersonId(null);
    setShowIssueForm(false);
    setIssueForm({ item_id: '', size: '' });
    setIsPersonOpen(true);
  };

  const handleSelectPerson = (person) => {
    setPersonSearch(person.label);
    setSelectedPersonId(person.id);
    setShowIssueForm(false);
    setIssueForm({ item_id: '', size: '' });
    setIsPersonOpen(false);
  };

  const handleIssueNewItem = async (e) => {
    e.preventDefault();
    if (!selectedPersonId) {
      alert('Selecteer eerst een lid.');
      return;
    }

    try {
      await createAssignment.mutateAsync({
        person_id: Number(selectedPersonId),
        item_id: Number(issueForm.item_id),
        size: issueForm.size,
        condition: 'nieuw',
        in_or_out: 'out',
        date: new Date().toISOString().slice(0, 10),
        season: settings?.current_season || '',
      });
      setIssueForm({ item_id: '', size: '' });
      setShowIssueForm(false);
    } catch (error) {
      alert(error?.response?.data?.message || 'Uitgifte kon niet worden opgeslagen.');
    }
  };

  const handleReturnItem = async (itemRow) => {
    if (!selectedPersonId) {
      return;
    }

    try {
      await createAssignment.mutateAsync({
        person_id: Number(selectedPersonId),
        item_id: Number(itemRow.item_id),
        size: itemRow.size,
        condition: 'goed',
        in_or_out: 'in',
        date: new Date().toISOString().slice(0, 10),
        season: settings?.current_season || itemRow.season || '',
      });
    } catch (error) {
      alert(error?.response?.data?.message || 'Retour kon niet worden opgeslagen.');
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

          <div className="card p-6 space-y-4">
            <h2 className="font-semibold text-brand-gradient flex items-center gap-2">
              <RotateCcw className="w-5 h-5" />
              Uitgifte / inname registreren
            </h2>

            <div className="relative max-w-xl">
              <input
                ref={personInputRef}
                className="input"
                value={personSearch}
                onChange={(e) => handlePersonSearchChange(e.target.value)}
                onFocus={() => setIsPersonOpen(true)}
                placeholder="Zoek lid..."
              />
              {(personSearch || selectedPersonId) && (
                <button
                  type="button"
                  onClick={() => {
                    setPersonSearch('');
                    setSelectedPersonId(null);
                    setShowIssueForm(false);
                    setIssueForm({ item_id: '', size: '' });
                    setIsPersonOpen(false);
                  }}
                  className="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                  aria-label="Lid wissen"
                >
                  <X className="w-4 h-4" />
                </button>
              )}

              {isPersonOpen && (
                <div
                  ref={personDropdownRef}
                  className="absolute z-20 w-full mt-1 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-lg max-h-56 overflow-y-auto"
                >
                  {searchTerm.length < 3 ? (
                    <p className="px-3 py-2 text-sm text-gray-500">Typ minimaal 3 tekens om te zoeken</p>
                  ) : filteredPersonOptions.length > 0 ? (
                    filteredPersonOptions.map((person) => (
                      <button
                        key={person.id}
                        type="button"
                        onClick={() => handleSelectPerson(person)}
                        className="w-full text-left px-3 py-2 text-sm hover:bg-gray-50 dark:hover:bg-gray-700"
                      >
                        {person.label}
                      </button>
                    ))
                  ) : (
                    <p className="px-3 py-2 text-sm text-gray-500">Geen leden gevonden</p>
                  )}
                </div>
              )}
            </div>

            {selectedPerson && (
              <div className="space-y-4">
                <div className="card p-4 bg-gray-50 dark:bg-gray-800/60">
                  <div className="flex items-center gap-4">
                    {selectedPerson.thumbnail ? (
                      <img
                        src={selectedPerson.thumbnail}
                        alt={selectedPerson.name}
                        className="w-14 h-14 rounded-full object-cover"
                      />
                    ) : (
                      <div className="w-14 h-14 rounded-full bg-gray-200 dark:bg-gray-700" />
                    )}
                    <div>
                      <p className="font-semibold">{selectedPerson.name}</p>
                      <p className="text-sm text-gray-600 dark:text-gray-300">Team: {teamName}</p>
                    </div>
                  </div>
                </div>

                <div className="card p-4">
                  <div className="flex items-center justify-between mb-3">
                    <h3 className="font-semibold text-brand-gradient">Huidige kleding</h3>
                    <button
                      type="button"
                      className="btn-secondary text-sm"
                      onClick={() => setShowIssueForm((prev) => !prev)}
                    >
                      <Plus className="w-4 h-4 mr-1" />
                      Nieuw item uitgeven
                    </button>
                  </div>

                  {(personProfile?.current_items || []).length === 0 ? (
                    <p className="text-sm text-gray-500">Dit lid heeft momenteel geen uitgegeven items.</p>
                  ) : (
                    <div className="space-y-2">
                      {personProfile.current_items.map((row) => (
                        <div key={row.id} className="flex items-center justify-between border-b border-gray-100 dark:border-gray-800 py-2">
                          <div>
                            <p className="text-sm font-medium">{row.item_name}</p>
                            <p className="text-xs text-gray-500">Maat {row.size}</p>
                          </div>
                          <button
                            type="button"
                            className="btn-secondary text-xs"
                            onClick={() => handleReturnItem(row)}
                            disabled={createAssignment.isPending}
                          >
                            <Undo2 className="w-3.5 h-3.5 mr-1" />
                            Retourneren
                          </button>
                        </div>
                      ))}
                    </div>
                  )}
                </div>

                {showIssueForm && (
                  <form onSubmit={handleIssueNewItem} className="card p-4 space-y-3">
                    <h4 className="font-medium">Nieuw item uitgeven</h4>
                    <select
                      className="input"
                      value={issueForm.item_id}
                      onChange={(e) => setIssueForm((prev) => ({ ...prev, item_id: e.target.value, size: '' }))}
                      required
                    >
                      <option value="">Selecteer item</option>
                      {items.map((item) => (
                        <option key={item.id} value={item.id}>{item.name}</option>
                      ))}
                    </select>
                    <select
                      className="input"
                      value={issueForm.size}
                      onChange={(e) => setIssueForm((prev) => ({ ...prev, size: e.target.value }))}
                      required
                      disabled={!issueForm.item_id || sizeOptions.length === 0}
                    >
                      <option value="">{issueForm.item_id ? 'Selecteer maat' : 'Kies eerst een item'}</option>
                      {sizeOptions.map((size) => (
                        <option key={size} value={size}>{size}</option>
                      ))}
                    </select>
                    <div>
                      <button type="submit" className="btn-primary text-sm" disabled={createAssignment.isPending}>
                        Uitgeven
                      </button>
                    </div>
                  </form>
                )}
              </div>
            )}
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
