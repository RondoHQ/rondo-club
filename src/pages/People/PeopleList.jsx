import { useState, useMemo, useRef, useEffect } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { Plus, Filter, X, Check, ArrowUp, ArrowDown, Square, CheckSquare, MinusSquare, ChevronDown, Lock, Users, Building2, Tag } from 'lucide-react';
import { usePeople, useCreatePerson, useBulkUpdatePeople } from '@/hooks/usePeople';
import { useWorkspaces } from '@/hooks/useWorkspaces';
import { useQuery } from '@tanstack/react-query';
import { wpApi, prmApi } from '@/api/client';
import { getTeamName } from '@/utils/formatters';
import PersonEditModal from '@/components/PersonEditModal';
import CustomFieldColumn from '@/components/CustomFieldColumn';

// Helper function to get current team ID from person's work history
function getCurrentTeamId(person) {
  const workHistory = person.acf?.work_history || [];
  if (workHistory.length === 0) return null;
  
  // First, try to find current position
  const currentJob = workHistory.find(job => job.is_current && job.team);
  if (currentJob) return currentJob.team;
  
  // Otherwise, get the most recent (by start_date)
  const jobsWithTeam = workHistory
    .filter(job => job.team)
    .sort((a, b) => {
      const dateA = a.start_date ? new Date(a.start_date) : new Date(0);
      const dateB = b.start_date ? new Date(b.start_date) : new Date(0);
      return dateB - dateA; // Most recent first
    });
  
  return jobsWithTeam.length > 0 ? jobsWithTeam[0].team : null;
}

function PersonListRow({ person, teamName, workspaces, listViewFields, isSelected, onToggleSelection, isOdd }) {
  const assignedWorkspaces = person.acf?._assigned_workspaces || [];
  const workspaceNames = assignedWorkspaces
    .map(wsId => {
      // Try both number and string comparison
      const numId = typeof wsId === 'string' ? parseInt(wsId, 10) : wsId;
      const found = workspaces.find(ws => ws.id === numId);
      return found?.title;
    })
    .filter(Boolean)
    .join(', ');

  return (
    <tr className={`hover:bg-gray-100 dark:hover:bg-gray-700 ${isOdd ? 'bg-gray-50 dark:bg-gray-800/50' : 'bg-white dark:bg-gray-800'}`}>
      <td className="pl-4 pr-2 py-3 w-10">
        <button
          onClick={(e) => { e.preventDefault(); onToggleSelection(person.id); }}
          className="text-gray-400 hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300"
        >
          {isSelected ? (
            <CheckSquare className="w-5 h-5 text-accent-600 dark:text-accent-400" />
          ) : (
            <Square className="w-5 h-5" />
          )}
        </button>
      </td>
      <td className="w-10 px-2 py-3">
        <Link to={`/people/${person.id}`} className="flex items-center justify-center">
          {person.thumbnail ? (
            <img
              src={person.thumbnail}
              alt=""
              className="w-8 h-8 rounded-full object-cover"
            />
          ) : (
            <div className="w-8 h-8 bg-gray-200 dark:bg-gray-600 rounded-full flex items-center justify-center">
              <span className="text-sm font-medium text-gray-500 dark:text-gray-300">
                {person.first_name?.[0] || '?'}
              </span>
            </div>
          )}
        </Link>
      </td>
      <td className="px-4 py-3 whitespace-nowrap">
        <Link to={`/people/${person.id}`} className="flex items-center">
          <span className="text-sm font-medium text-gray-900 dark:text-gray-50">
            {person.first_name || ''}
            {person.is_deceased && <span className="ml-1 text-gray-500 dark:text-gray-400">&#8224;</span>}
          </span>
        </Link>
      </td>
      <td className="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
        {person.last_name || '-'}
      </td>
      <td className="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
        {teamName || '-'}
      </td>
      <td className="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
        {workspaceNames || '-'}
      </td>
      <td className="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
        {person.labels && person.labels.length > 0 ? (
          <div className="flex flex-wrap gap-1">
            {person.labels.slice(0, 3).map((label) => (
              <span
                key={label}
                className="inline-flex px-2 py-0.5 text-xs rounded-full bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300"
              >
                {label}
              </span>
            ))}
            {person.labels.length > 3 && (
              <span className="text-xs text-gray-400 dark:text-gray-500">+{person.labels.length - 3} meer</span>
            )}
          </div>
        ) : (
          '-'
        )}
      </td>
      {listViewFields.map(field => (
        <td key={field.key} className="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
          <CustomFieldColumn field={field} value={person.acf?.[field.name]} />
        </td>
      ))}
    </tr>
  );
}

// Sortable header component for clickable column headers
function SortableHeader({ field, label, currentSortField, currentSortOrder, onSort }) {
  const isActive = currentSortField === field;

  return (
    <th scope="col" className="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider bg-gray-50 dark:bg-gray-800">
      <button
        onClick={() => onSort(field)}
        className="flex items-center gap-1 hover:text-gray-700 dark:hover:text-gray-200 cursor-pointer"
      >
        {label}
        {isActive && (
          currentSortOrder === 'asc' ? (
            <ArrowUp className="w-3 h-3" />
          ) : (
            <ArrowDown className="w-3 h-3" />
          )
        )}
      </button>
    </th>
  );
}

function PersonListView({ people, teamMap, workspaces, listViewFields, selectedIds, onToggleSelection, onToggleSelectAll, isAllSelected, isSomeSelected, sortField, sortOrder, onSort }) {
  return (
    <div className="card overflow-x-auto max-h-[calc(100vh-12rem)] overflow-y-auto">
      <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
        <thead className="bg-gray-50 dark:bg-gray-800 sticky top-0 z-10">
          <tr className="shadow-sm dark:shadow-gray-900/50">
            <th scope="col" className="pl-4 pr-2 py-3 w-10 bg-gray-50 dark:bg-gray-800">
              <button
                onClick={onToggleSelectAll}
                className="text-gray-400 hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300"
                title={isAllSelected ? 'Deselect all' : 'Select all'}
              >
                {isAllSelected ? (
                  <CheckSquare className="w-5 h-5 text-accent-600 dark:text-accent-400" />
                ) : isSomeSelected ? (
                  <MinusSquare className="w-5 h-5 text-accent-600 dark:text-accent-400" />
                ) : (
                  <Square className="w-5 h-5" />
                )}
              </button>
            </th>
            <th scope="col" className="w-10 px-2 bg-gray-50 dark:bg-gray-800"></th>
            <SortableHeader field="first_name" label="Voornaam" currentSortField={sortField} currentSortOrder={sortOrder} onSort={onSort} />
            <SortableHeader field="last_name" label="Achternaam" currentSortField={sortField} currentSortOrder={sortOrder} onSort={onSort} />
            <SortableHeader field="organization" label="Team" currentSortField={sortField} currentSortOrder={sortOrder} onSort={onSort} />
            <SortableHeader field="workspace" label="Workspace" currentSortField={sortField} currentSortOrder={sortOrder} onSort={onSort} />
            <SortableHeader field="labels" label="Labels" currentSortField={sortField} currentSortOrder={sortOrder} onSort={onSort} />
            {listViewFields.map(field => (
              <SortableHeader
                key={field.key}
                field={`custom_${field.name}`}
                label={field.label}
                currentSortField={sortField}
                currentSortOrder={sortOrder}
                onSort={onSort}
              />
            ))}
          </tr>
        </thead>
        <tbody className="divide-y divide-gray-200 dark:divide-gray-700">
          {people.map((person, index) => (
            <PersonListRow
              key={person.id}
              person={person}
              teamName={teamMap[person.id]}
              workspaces={workspaces}
              listViewFields={listViewFields}
              isSelected={selectedIds.has(person.id)}
              onToggleSelection={onToggleSelection}
              isOdd={index % 2 === 1}
            />
          ))}
        </tbody>
      </table>
    </div>
  );
}

// Bulk Visibility Modal Component
function BulkVisibilityModal({ isOpen, onClose, selectedCount, onSubmit, isLoading }) {
  const [selectedVisibility, setSelectedVisibility] = useState('private');

  // Reset selection when modal opens
  useEffect(() => {
    if (isOpen) {
      setSelectedVisibility('private');
    }
  }, [isOpen]);

  if (!isOpen) return null;

  const visibilityOptions = [
    {
      value: 'private',
      label: 'Privé',
      description: 'Alleen jij kunt deze leden zien',
      icon: Lock
    },
    {
      value: 'workspace',
      label: 'Workspace',
      description: 'Deel met workspace-leden',
      icon: Users
    },
  ];

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
      <div className="bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full max-w-md mx-4">
        <div className="flex items-center justify-between p-4 border-b dark:border-gray-700">
          <h2 className="text-lg font-semibold dark:text-gray-50">Zichtbaarheid wijzigen</h2>
          <button
            onClick={onClose}
            className="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
            disabled={isLoading}
          >
            <X className="w-5 h-5" />
          </button>
        </div>

        <div className="p-4 space-y-4">
          <p className="text-sm text-gray-600 dark:text-gray-300">
            Kies zichtbaarheid voor {selectedCount} {selectedCount === 1 ? 'lid' : 'leden'}:
          </p>

          <div className="space-y-2">
            {visibilityOptions.map((option) => {
              const Icon = option.icon;
              const isSelected = selectedVisibility === option.value;
              return (
                <button
                  key={option.value}
                  type="button"
                  onClick={() => setSelectedVisibility(option.value)}
                  disabled={isLoading}
                  className={`w-full flex items-start gap-3 p-3 rounded-lg border-2 text-left transition-colors ${
                    isSelected
                      ? 'border-accent-500 bg-accent-50 dark:bg-accent-800'
                      : 'border-gray-200 dark:border-gray-600 hover:border-gray-300 dark:hover:border-gray-500'
                  }`}
                >
                  <Icon className={`w-5 h-5 mt-0.5 ${isSelected ? 'text-accent-600 dark:text-accent-400' : 'text-gray-400 dark:text-gray-500'}`} />
                  <div className="flex-1">
                    <div className={`text-sm font-medium ${isSelected ? 'text-accent-900 dark:text-accent-100' : 'text-gray-900 dark:text-gray-50'}`}>
                      {option.label}
                    </div>
                    <div className="text-xs text-gray-500 dark:text-gray-400">{option.description}</div>
                  </div>
                  {isSelected && <Check className="w-5 h-5 text-accent-600 dark:text-accent-400 mt-0.5" />}
                </button>
              );
            })}
          </div>
        </div>

        <div className="flex justify-end gap-2 p-4 border-t dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50">
          <button
            type="button"
            onClick={onClose}
            className="btn-secondary"
            disabled={isLoading}
          >
            Annuleren
          </button>
          <button
            type="button"
            onClick={() => onSubmit(selectedVisibility)}
            className="btn-primary"
            disabled={isLoading}
          >
            {isLoading ? 'Toepassen...' : `Toepassen op ${selectedCount} ${selectedCount === 1 ? 'lid' : 'leden'}`}
          </button>
        </div>
      </div>
    </div>
  );
}

// Bulk Workspace Modal Component
function BulkWorkspaceModal({ isOpen, onClose, selectedCount, workspaces, onSubmit, isLoading }) {
  const [selectedWorkspaceIds, setSelectedWorkspaceIds] = useState([]);

  // Reset selection when modal opens
  useEffect(() => {
    if (isOpen) {
      setSelectedWorkspaceIds([]);
    }
  }, [isOpen]);

  if (!isOpen) return null;

  const handleWorkspaceToggle = (workspaceId) => {
    setSelectedWorkspaceIds(prev =>
      prev.includes(workspaceId)
        ? prev.filter(id => id !== workspaceId)
        : [...prev, workspaceId]
    );
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
      <div className="bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full max-w-md mx-4">
        <div className="flex items-center justify-between p-4 border-b dark:border-gray-700">
          <h2 className="text-lg font-semibold dark:text-gray-50">Toewijzen aan workspace</h2>
          <button
            onClick={onClose}
            className="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
            disabled={isLoading}
          >
            <X className="w-5 h-5" />
          </button>
        </div>

        <div className="p-4 space-y-4">
          <p className="text-sm text-gray-600 dark:text-gray-300">
            Selecteer workspaces voor {selectedCount} {selectedCount === 1 ? 'lid' : 'leden'}:
          </p>

          {workspaces.length === 0 ? (
            <div className="text-center py-6 text-gray-500 dark:text-gray-400">
              <Users className="w-8 h-8 mx-auto mb-2 text-gray-400 dark:text-gray-500" />
              <p className="text-sm">Geen workspaces beschikbaar.</p>
              <p className="text-xs">Maak eerst een workspace aan om deze functie te gebruiken.</p>
            </div>
          ) : (
            <div className="space-y-2 max-h-64 overflow-y-auto">
              {workspaces.map((workspace) => {
                const isChecked = selectedWorkspaceIds.includes(workspace.id);
                return (
                  <button
                    key={workspace.id}
                    type="button"
                    onClick={() => handleWorkspaceToggle(workspace.id)}
                    disabled={isLoading}
                    className={`w-full flex items-center gap-3 p-3 rounded-lg border-2 text-left transition-colors ${
                      isChecked
                        ? 'border-accent-500 bg-accent-50 dark:bg-accent-800'
                        : 'border-gray-200 dark:border-gray-600 hover:border-gray-300 dark:hover:border-gray-500'
                    }`}
                  >
                    <div className={`flex items-center justify-center w-5 h-5 border-2 rounded ${
                      isChecked ? 'bg-accent-600 border-accent-600' : 'border-gray-300 dark:border-gray-500'
                    }`}>
                      {isChecked && <Check className="w-3 h-3 text-white" />}
                    </div>
                    <div className="flex-1">
                      <div className="text-sm font-medium text-gray-900 dark:text-gray-50">{workspace.title}</div>
                      <div className="text-xs text-gray-500 dark:text-gray-400">{workspace.member_count} leden</div>
                    </div>
                  </button>
                );
              })}
            </div>
          )}
        </div>

        <div className="flex justify-end gap-2 p-4 border-t dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50">
          <button
            type="button"
            onClick={onClose}
            className="btn-secondary"
            disabled={isLoading}
          >
            Annuleren
          </button>
          <button
            type="button"
            onClick={() => onSubmit(selectedWorkspaceIds)}
            className="btn-primary"
            disabled={isLoading || workspaces.length === 0}
          >
            {isLoading ? 'Toewijzen...' : `Toewijzen aan ${selectedCount} ${selectedCount === 1 ? 'lid' : 'leden'}`}
          </button>
        </div>
      </div>
    </div>
  );
}

// Bulk Organization Modal Component
function BulkOrganizationModal({ isOpen, onClose, selectedCount, teams, onSubmit, isLoading }) {
  const [selectedTeamId, setSelectedTeamId] = useState(null);
  const [searchQuery, setSearchQuery] = useState('');

  // Reset when modal opens
  useEffect(() => {
    if (isOpen) {
      setSelectedTeamId(null);
      setSearchQuery('');
    }
  }, [isOpen]);

  if (!isOpen) return null;

  // Filter teams by search query
  const filteredTeams = (teams || []).filter(team =>
    team.name.toLowerCase().includes(searchQuery.toLowerCase())
  );

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
      <div className="bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full max-w-md mx-4">
        <div className="flex items-center justify-between p-4 border-b dark:border-gray-700">
          <h2 className="text-lg font-semibold dark:text-gray-50">Team instellen</h2>
          <button onClick={onClose} className="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300" disabled={isLoading}>
            <X className="w-5 h-5" />
          </button>
        </div>

        <div className="p-4 space-y-4">
          <p className="text-sm text-gray-600 dark:text-gray-300">
            Stel huidig team in voor {selectedCount} {selectedCount === 1 ? 'lid' : 'leden'}:
          </p>

          {/* Search input */}
          <input
            type="text"
            placeholder="Teams zoeken..."
            value={searchQuery}
            onChange={(e) => setSearchQuery(e.target.value)}
            className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-50 rounded-lg text-sm focus:ring-accent-500 focus:border-accent-500"
          />

          {/* Option to clear organization */}
          <button
            type="button"
            onClick={() => setSelectedTeamId('clear')}
            disabled={isLoading}
            className={`w-full flex items-center gap-3 p-3 rounded-lg border-2 text-left transition-colors ${
              selectedTeamId === 'clear'
                ? 'border-accent-500 bg-accent-50 dark:bg-accent-800'
                : 'border-gray-200 dark:border-gray-600 hover:border-gray-300 dark:hover:border-gray-500'
            }`}
          >
            <X className={`w-5 h-5 ${selectedTeamId === 'clear' ? 'text-accent-600 dark:text-accent-400' : 'text-gray-400 dark:text-gray-500'}`} />
            <div className="flex-1">
              <div className="text-sm font-medium text-gray-900 dark:text-gray-50">Team verwijderen</div>
              <div className="text-xs text-gray-500 dark:text-gray-400">Verwijder huidig team van geselecteerde leden</div>
            </div>
            {selectedTeamId === 'clear' && <Check className="w-5 h-5 text-accent-600 dark:text-accent-400" />}
          </button>

          {/* Team list */}
          <div className="space-y-2 max-h-64 overflow-y-auto">
            {filteredTeams.length === 0 ? (
              <p className="text-sm text-gray-500 dark:text-gray-400 text-center py-4">
                {searchQuery ? 'Geen teams gevonden voor je zoekopdracht' : 'Geen teams gevonden'}
              </p>
            ) : (
              filteredTeams.map((team) => {
                const isSelected = selectedTeamId === team.id;
                return (
                  <button
                    key={team.id}
                    type="button"
                    onClick={() => setSelectedTeamId(team.id)}
                    disabled={isLoading}
                    className={`w-full flex items-center gap-3 p-3 rounded-lg border-2 text-left transition-colors ${
                      isSelected
                        ? 'border-accent-500 bg-accent-50 dark:bg-accent-800'
                        : 'border-gray-200 dark:border-gray-600 hover:border-gray-300 dark:hover:border-gray-500'
                    }`}
                  >
                    <Building2 className={`w-5 h-5 ${isSelected ? 'text-accent-600 dark:text-accent-400' : 'text-gray-400 dark:text-gray-500'}`} />
                    <div className="flex-1">
                      <div className="text-sm font-medium text-gray-900 dark:text-gray-50">{team.name}</div>
                    </div>
                    {isSelected && <Check className="w-5 h-5 text-accent-600 dark:text-accent-400" />}
                  </button>
                );
              })
            )}
          </div>
        </div>

        <div className="flex justify-end gap-2 p-4 border-t dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50">
          <button type="button" onClick={onClose} className="btn-secondary" disabled={isLoading}>
            Annuleren
          </button>
          <button
            type="button"
            onClick={() => onSubmit(selectedTeamId === 'clear' ? null : selectedTeamId)}
            className="btn-primary"
            disabled={isLoading || selectedTeamId === null}
          >
            {isLoading ? 'Toepassen...' : `Toepassen op ${selectedCount} ${selectedCount === 1 ? 'lid' : 'leden'}`}
          </button>
        </div>
      </div>
    </div>
  );
}

// Bulk Labels Modal Component
function BulkLabelsModal({ isOpen, onClose, selectedCount, labels, onSubmit, isLoading }) {
  const [mode, setMode] = useState('add'); // 'add' or 'remove'
  const [selectedLabelIds, setSelectedLabelIds] = useState([]);

  // Reset when modal opens
  useEffect(() => {
    if (isOpen) {
      setMode('add');
      setSelectedLabelIds([]);
    }
  }, [isOpen]);

  if (!isOpen) return null;

  const handleLabelToggle = (labelId) => {
    setSelectedLabelIds(prev =>
      prev.includes(labelId)
        ? prev.filter(id => id !== labelId)
        : [...prev, labelId]
    );
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
      <div className="bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full max-w-md mx-4">
        <div className="flex items-center justify-between p-4 border-b dark:border-gray-700">
          <h2 className="text-lg font-semibold dark:text-gray-50">Labels beheren</h2>
          <button onClick={onClose} className="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300" disabled={isLoading}>
            <X className="w-5 h-5" />
          </button>
        </div>

        <div className="p-4 space-y-4">
          <p className="text-sm text-gray-600 dark:text-gray-300">
            {mode === 'add' ? 'Voeg labels toe aan' : 'Verwijder labels van'} {selectedCount} {selectedCount === 1 ? 'lid' : 'leden'}:
          </p>

          {/* Mode toggle */}
          <div className="flex rounded-lg border border-gray-200 dark:border-gray-600 p-1">
            <button
              type="button"
              onClick={() => { setMode('add'); setSelectedLabelIds([]); }}
              className={`flex-1 px-3 py-1.5 text-sm font-medium rounded-md transition-colors ${
                mode === 'add' ? 'bg-accent-100 dark:bg-accent-900/50 text-accent-700 dark:text-accent-300' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700'
              }`}
            >
              Labels toevoegen
            </button>
            <button
              type="button"
              onClick={() => { setMode('remove'); setSelectedLabelIds([]); }}
              className={`flex-1 px-3 py-1.5 text-sm font-medium rounded-md transition-colors ${
                mode === 'remove' ? 'bg-red-100 dark:bg-red-900/50 text-red-700 dark:text-red-300' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700'
              }`}
            >
              Labels verwijderen
            </button>
          </div>

          {/* Label list */}
          {(!labels || labels.length === 0) ? (
            <div className="text-center py-6 text-gray-500 dark:text-gray-400">
              <Tag className="w-8 h-8 mx-auto mb-2 text-gray-400 dark:text-gray-500" />
              <p className="text-sm">Geen labels beschikbaar.</p>
              <p className="text-xs">Maak eerst labels aan om deze functie te gebruiken.</p>
            </div>
          ) : (
            <div className="space-y-2 max-h-64 overflow-y-auto">
              {labels.map((label) => {
                const isChecked = selectedLabelIds.includes(label.id);
                return (
                  <button
                    key={label.id}
                    type="button"
                    onClick={() => handleLabelToggle(label.id)}
                    disabled={isLoading}
                    className={`w-full flex items-center gap-3 p-3 rounded-lg border-2 text-left transition-colors ${
                      isChecked
                        ? mode === 'add' ? 'border-accent-500 bg-accent-50 dark:bg-accent-800' : 'border-red-500 bg-red-50 dark:bg-red-900/30'
                        : 'border-gray-200 dark:border-gray-600 hover:border-gray-300 dark:hover:border-gray-500'
                    }`}
                  >
                    <div className={`flex items-center justify-center w-5 h-5 border-2 rounded ${
                      isChecked
                        ? mode === 'add' ? 'bg-accent-600 border-accent-600' : 'bg-red-600 border-red-600'
                        : 'border-gray-300 dark:border-gray-500'
                    }`}>
                      {isChecked && <Check className="w-3 h-3 text-white" />}
                    </div>
                    <span className="text-sm font-medium text-gray-900 dark:text-gray-50">{label.name}</span>
                  </button>
                );
              })}
            </div>
          )}
        </div>

        <div className="flex justify-end gap-2 p-4 border-t dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50">
          <button type="button" onClick={onClose} className="btn-secondary" disabled={isLoading}>
            Annuleren
          </button>
          <button
            type="button"
            onClick={() => onSubmit(mode, selectedLabelIds)}
            className={mode === 'add' ? 'btn-primary' : 'bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 disabled:opacity-50'}
            disabled={isLoading || selectedLabelIds.length === 0}
          >
            {isLoading
              ? (mode === 'add' ? 'Toevoegen...' : 'Verwijderen...')
              : `${selectedLabelIds.length} label${selectedLabelIds.length === 1 ? '' : 's'} ${mode === 'add' ? 'toevoegen' : 'verwijderen'}`
            }
          </button>
        </div>
      </div>
    </div>
  );
}

export default function PeopleList() {
  const [isFilterOpen, setIsFilterOpen] = useState(false);
  const [selectedLabels, setSelectedLabels] = useState([]);
  const [selectedBirthYear, setSelectedBirthYear] = useState('');
  const [lastModifiedFilter, setLastModifiedFilter] = useState('');
  const [ownershipFilter, setOwnershipFilter] = useState('all'); // 'all', 'mine', 'shared'
  const [selectedWorkspaceFilter, setSelectedWorkspaceFilter] = useState('');
  const [sortField, setSortField] = useState('first_name'); // 'first_name' or 'last_name'
  const [sortOrder, setSortOrder] = useState('asc'); // 'asc' or 'desc'
  const [selectedIds, setSelectedIds] = useState(new Set());
  const [showPersonModal, setShowPersonModal] = useState(false);
  const [isCreatingPerson, setIsCreatingPerson] = useState(false);
  const [showBulkDropdown, setShowBulkDropdown] = useState(false);
  const [showBulkVisibilityModal, setShowBulkVisibilityModal] = useState(false);
  const [showBulkWorkspaceModal, setShowBulkWorkspaceModal] = useState(false);
  const [showBulkOrganizationModal, setShowBulkOrganizationModal] = useState(false);
  const [showBulkLabelsModal, setShowBulkLabelsModal] = useState(false);
  const [bulkActionLoading, setBulkActionLoading] = useState(false);
  const filterRef = useRef(null);
  const dropdownRef = useRef(null);
  const bulkDropdownRef = useRef(null);
  const navigate = useNavigate();

  const { data: people, isLoading, error } = usePeople();
  const { data: workspaces = [] } = useWorkspaces();
  const bulkUpdateMutation = useBulkUpdatePeople();

  // Get current user ID from stadionConfig
  const currentUserId = window.stadionConfig?.userId;
  
  // Create person mutation (using shared hook)
  const createPersonMutation = useCreatePerson({
    onSuccess: (result) => {
      setShowPersonModal(false);
      navigate(`/people/${result.id}`);
    },
  });
  
  const handleCreatePerson = async (data) => {
    setIsCreatingPerson(true);
    try {
      await createPersonMutation.mutateAsync(data);
    } finally {
      setIsCreatingPerson(false);
    }
  };
  
  // Fetch person labels
  const { data: labelsData } = useQuery({
    queryKey: ['person-labels'],
    queryFn: async () => {
      const response = await wpApi.getPersonLabels();
      return response.data;
    },
  });

  const availableLabels = labelsData?.map(label => label.name) || [];
  // Labels with IDs for the bulk modal
  const availableLabelsWithIds = labelsData || [];

  // Fetch custom field definitions for list view columns
  const { data: customFields = [] } = useQuery({
    queryKey: ['custom-fields-metadata', 'person'],
    queryFn: async () => {
      const response = await prmApi.getCustomFieldsMetadata('person');
      return response.data;
    },
  });

  // Filter to list-view-enabled fields, sorted by order
  const listViewFields = useMemo(() => {
    return customFields
      .filter(f => f.show_in_list_view)
      .sort((a, b) => (a.list_view_order || 999) - (b.list_view_order || 999));
  }, [customFields]);

  // Fetch all teams for bulk organization modal (sorted alphabetically)
  const { data: allTeamsData } = useQuery({
    queryKey: ['teams', 'all'],
    queryFn: async () => {
      const response = await wpApi.getTeams({ per_page: 100 });
      return response.data
        .map(team => ({
          id: team.id,
          name: getTeamName(team),
        }))
        .sort((a, b) => a.name.localeCompare(b.name));
    },
  });
  
  // Get available birth years from people data
  const availableBirthYears = useMemo(() => {
    if (!people) return [];
    const years = people
      .map(p => p.birth_year)
      .filter(year => year !== null && year !== undefined);
    // Sort descending (most recent first)
    return [...new Set(years)].sort((a, b) => b - a);
  }, [people]);
  
  // Close dropdown when clicking outside
  useEffect(() => {
    const handleClickOutside = (event) => {
      if (
        dropdownRef.current &&
        !dropdownRef.current.contains(event.target) &&
        filterRef.current &&
        !filterRef.current.contains(event.target)
      ) {
        setIsFilterOpen(false);
      }
      // Also close bulk dropdown when clicking outside
      if (
        bulkDropdownRef.current &&
        !bulkDropdownRef.current.contains(event.target)
      ) {
        setShowBulkDropdown(false);
      }
    };

    document.addEventListener('mousedown', handleClickOutside);
    return () => {
      document.removeEventListener('mousedown', handleClickOutside);
    };
  }, []);
  
  // Filter and sort people
  const filteredAndSortedPeople = useMemo(() => {
    if (!people) return [];
    
    let filtered = [...people];
    
    // Apply label filters
    if (selectedLabels.length > 0) {
      filtered = filtered.filter(person => {
        const personLabels = person.labels || [];
        return selectedLabels.some(label => personLabels.includes(label));
      });
    }
    
    // Apply birth year filter
    if (selectedBirthYear) {
      const year = parseInt(selectedBirthYear, 10);
      filtered = filtered.filter(person => person.birth_year === year);
    }
    
    // Apply last modified filter
    if (lastModifiedFilter) {
      const now = new Date();
      let cutoffDate;

      switch (lastModifiedFilter) {
        case '7':
          cutoffDate = new Date(now.setDate(now.getDate() - 7));
          break;
        case '30':
          cutoffDate = new Date(now.setDate(now.getDate() - 30));
          break;
        case '90':
          cutoffDate = new Date(now.setDate(now.getDate() - 90));
          break;
        case '365':
          cutoffDate = new Date(now.setDate(now.getDate() - 365));
          break;
        default:
          cutoffDate = null;
      }

      if (cutoffDate) {
        filtered = filtered.filter(person => {
          if (!person.modified) return false;
          const modifiedDate = new Date(person.modified);
          return modifiedDate >= cutoffDate;
        });
      }
    }

    // Apply ownership filter
    if (ownershipFilter === 'mine') {
      filtered = filtered.filter(person => person.author === currentUserId);
    } else if (ownershipFilter === 'shared') {
      filtered = filtered.filter(person => person.author !== currentUserId);
    }

    // Apply workspace filter
    if (selectedWorkspaceFilter) {
      filtered = filtered.filter(person => {
        const assignedWorkspaces = person.acf?._assigned_workspaces || [];
        return assignedWorkspaces.includes(parseInt(selectedWorkspaceFilter));
      });
    }

    return filtered;
  }, [people, selectedLabels, selectedBirthYear, lastModifiedFilter, ownershipFilter, selectedWorkspaceFilter, currentUserId]);

  const hasActiveFilters = selectedLabels.length > 0 || selectedBirthYear || lastModifiedFilter || ownershipFilter !== 'all' || selectedWorkspaceFilter;
  
  const handleLabelToggle = (label) => {
    setSelectedLabels(prev => 
      prev.includes(label)
        ? prev.filter(l => l !== label)
        : [...prev, label]
    );
  };
  
  const clearFilters = () => {
    setSelectedLabels([]);
    setSelectedBirthYear('');
    setLastModifiedFilter('');
    setOwnershipFilter('all');
    setSelectedWorkspaceFilter('');
  };

  // Selection helper functions
  const toggleSelection = (personId) => {
    setSelectedIds(prev => {
      const next = new Set(prev);
      if (next.has(personId)) {
        next.delete(personId);
      } else {
        next.add(personId);
      }
      return next;
    });
  };

  const toggleSelectAll = () => {
    if (selectedIds.size === filteredAndSortedPeople.length) {
      setSelectedIds(new Set());
    } else {
      setSelectedIds(new Set(filteredAndSortedPeople.map(p => p.id)));
    }
  };

  const clearSelection = () => setSelectedIds(new Set());

  const isAllSelected = filteredAndSortedPeople.length > 0 &&
    selectedIds.size === filteredAndSortedPeople.length;
  const isSomeSelected = selectedIds.size > 0 &&
    selectedIds.size < filteredAndSortedPeople.length;

  // Clear selection when filters change or data changes
  useEffect(() => {
    setSelectedIds(new Set());
  }, [selectedLabels, selectedBirthYear, lastModifiedFilter, ownershipFilter, selectedWorkspaceFilter, people]);

  // Collect all team IDs
  const teamIds = useMemo(() => {
    if (!filteredAndSortedPeople) return [];
    const ids = filteredAndSortedPeople
      .map(person => getCurrentTeamId(person))
      .filter(Boolean);
    // Remove duplicates
    return [...new Set(ids)];
  }, [filteredAndSortedPeople]);

  // Batch fetch all teams at once instead of individual queries
  const { data: teamsData } = useQuery({
    queryKey: ['teams', 'batch', teamIds.sort().join(',')],
    queryFn: async () => {
      if (teamIds.length === 0) return [];
      // Fetch all teams in one request
      const response = await wpApi.getTeams({ 
        per_page: 100,
        include: teamIds.join(','),
      });
      return response.data;
    },
    enabled: teamIds.length > 0,
  });

  // Create a map of team ID to team name
  const teamMap = useMemo(() => {
    const map = {};
    if (teamsData) {
      teamsData.forEach(team => {
        map[team.id] = getTeamName(team);
      });
    }
    return map;
  }, [teamsData]);

  // Create a map of person ID to team name
  const personTeamMap = useMemo(() => {
    const map = {};
    filteredAndSortedPeople.forEach(person => {
      const teamId = getCurrentTeamId(person);
      if (teamId && teamMap[teamId]) {
        map[person.id] = teamMap[teamId];
      }
    });
    return map;
  }, [filteredAndSortedPeople, teamMap]);

  // Helper to get workspace names for a person (used in sorting)
  const getPersonWorkspaceNames = (person) => {
    const assignedWorkspaces = person.acf?._assigned_workspaces || [];
    return assignedWorkspaces
      .map(wsId => {
        const numId = typeof wsId === 'string' ? parseInt(wsId, 10) : wsId;
        const found = workspaces.find(ws => ws.id === numId);
        return found?.title;
      })
      .filter(Boolean)
      .join(', ');
  };

  // Sort the filtered people
  const sortedPeople = useMemo(() => {
    if (!filteredAndSortedPeople) return [];

    return [...filteredAndSortedPeople].sort((a, b) => {
      let valueA, valueB;

      if (sortField === 'modified') {
        // Sort by last modified date
        valueA = a.modified ? new Date(a.modified).getTime() : 0;
        valueB = b.modified ? new Date(b.modified).getTime() : 0;
        const comparison = valueA - valueB;
        return sortOrder === 'asc' ? comparison : -comparison;
      }

      if (sortField === 'organization') {
        // Sort by team name (from personTeamMap)
        valueA = (personTeamMap[a.id] || '').toLowerCase();
        valueB = (personTeamMap[b.id] || '').toLowerCase();
        // Empty values sort last
        if (!valueA && valueB) return sortOrder === 'asc' ? 1 : -1;
        if (valueA && !valueB) return sortOrder === 'asc' ? -1 : 1;
        if (!valueA && !valueB) return 0;
        const comparison = valueA.localeCompare(valueB);
        return sortOrder === 'asc' ? comparison : -comparison;
      }

      if (sortField === 'workspace') {
        // Sort by workspace names
        valueA = getPersonWorkspaceNames(a).toLowerCase();
        valueB = getPersonWorkspaceNames(b).toLowerCase();
        // Empty values sort last
        if (!valueA && valueB) return sortOrder === 'asc' ? 1 : -1;
        if (valueA && !valueB) return sortOrder === 'asc' ? -1 : 1;
        if (!valueA && !valueB) return 0;
        const comparison = valueA.localeCompare(valueB);
        return sortOrder === 'asc' ? comparison : -comparison;
      }

      if (sortField === 'labels') {
        // Sort by first label name
        valueA = (a.labels?.[0] || '').toLowerCase();
        valueB = (b.labels?.[0] || '').toLowerCase();
        // Empty values sort last
        if (!valueA && valueB) return sortOrder === 'asc' ? 1 : -1;
        if (valueA && !valueB) return sortOrder === 'asc' ? -1 : 1;
        if (!valueA && !valueB) return 0;
        const comparison = valueA.localeCompare(valueB);
        return sortOrder === 'asc' ? comparison : -comparison;
      }

      if (sortField.startsWith('custom_')) {
        // Handle custom field sorting
        const fieldName = sortField.replace('custom_', '');
        const fieldMeta = listViewFields.find(f => f.name === fieldName);
        valueA = a.acf?.[fieldName];
        valueB = b.acf?.[fieldName];

        // Handle different field types
        if (fieldMeta?.type === 'number') {
          valueA = parseFloat(valueA) || 0;
          valueB = parseFloat(valueB) || 0;
          return sortOrder === 'asc' ? valueA - valueB : valueB - valueA;
        }

        if (fieldMeta?.type === 'date') {
          valueA = valueA ? new Date(valueA).getTime() : 0;
          valueB = valueB ? new Date(valueB).getTime() : 0;
          return sortOrder === 'asc' ? valueA - valueB : valueB - valueA;
        }

        // Text comparison for other types
        valueA = String(valueA || '').toLowerCase();
        valueB = String(valueB || '').toLowerCase();

        // Empty values sort last
        if (!valueA && valueB) return sortOrder === 'asc' ? 1 : -1;
        if (valueA && !valueB) return sortOrder === 'asc' ? -1 : 1;
        if (!valueA && !valueB) return 0;

        const comparison = valueA.localeCompare(valueB);
        return sortOrder === 'asc' ? comparison : -comparison;
      }

      // Default: first_name or last_name
      if (sortField === 'first_name') {
        valueA = (a.acf?.first_name || a.first_name || '').toLowerCase();
        valueB = (b.acf?.first_name || b.first_name || '').toLowerCase();
      } else {
        valueA = (a.acf?.last_name || a.last_name || '').toLowerCase();
        valueB = (b.acf?.last_name || b.last_name || '').toLowerCase();
      }

      // If values are equal, sort by the other field as tiebreaker
      if (valueA === valueB) {
        const tiebreakerA = sortField === 'first_name'
          ? (a.acf?.last_name || a.last_name || '').toLowerCase()
          : (a.acf?.first_name || a.first_name || '').toLowerCase();
        const tiebreakerB = sortField === 'first_name'
          ? (b.acf?.last_name || b.last_name || '').toLowerCase()
          : (b.acf?.first_name || b.first_name || '').toLowerCase();

        const tiebreakerResult = tiebreakerA.localeCompare(tiebreakerB);
        return sortOrder === 'asc' ? tiebreakerResult : -tiebreakerResult;
      }

      const comparison = valueA.localeCompare(valueB);
      return sortOrder === 'asc' ? comparison : -comparison;
    });
  }, [filteredAndSortedPeople, sortField, sortOrder, personTeamMap, workspaces, listViewFields]);

  return (
    <div className="space-y-4">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div className="flex flex-wrap items-center gap-2">
          {/* Sort Controls */}
          <div className="flex items-center gap-2 border border-gray-200 dark:border-gray-600 rounded-lg p-1">
            <select
              value={sortField}
              onChange={(e) => setSortField(e.target.value)}
              className="text-sm border-0 bg-transparent dark:text-gray-200 focus:ring-0 focus:outline-none cursor-pointer"
            >
              <option value="first_name">Voornaam</option>
              <option value="last_name">Achternaam</option>
              <option value="modified">Laatst gewijzigd</option>
              <option value="organization">Team</option>
              <option value="workspace">Workspace</option>
              <option value="labels">Labels</option>
            </select>
            <div className="h-4 w-px bg-gray-300 dark:bg-gray-600"></div>
            <button
              onClick={() => setSortOrder(sortOrder === 'asc' ? 'desc' : 'asc')}
              className="p-1 hover:bg-gray-100 dark:hover:bg-gray-700 rounded transition-colors"
              title={`Sorteer ${sortOrder === 'asc' ? 'aflopend' : 'oplopend'}`}
            >
              {sortOrder === 'asc' ? (
                <ArrowUp className="w-4 h-4 text-gray-600 dark:text-gray-300" />
              ) : (
                <ArrowDown className="w-4 h-4 text-gray-600 dark:text-gray-300" />
              )}
            </button>
          </div>

          <div className="relative" ref={filterRef}>
            <button 
              onClick={() => setIsFilterOpen(!isFilterOpen)}
              className={`btn-secondary ${hasActiveFilters ? 'bg-accent-50 text-accent-700 border-accent-200' : ''}`}
            >
              <Filter className="w-4 h-4 md:mr-2" />
              <span className="hidden md:inline">Filter</span>
              {hasActiveFilters && (
                <span className="ml-2 px-1.5 py-0.5 bg-accent-600 text-white text-xs rounded-full">
                  {selectedLabels.length + (selectedBirthYear ? 1 : 0) + (lastModifiedFilter ? 1 : 0)}
                </span>
              )}
            </button>
            
            {/* Filter Dropdown */}
            {isFilterOpen && (
              <div
                ref={dropdownRef}
                className="absolute top-full left-0 mt-2 w-64 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-lg z-50"
              >
                <div className="p-4 space-y-4">
                  {/* Labels Filter */}
                  {availableLabels.length > 0 && (
                    <div>
                      <h3 className="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-2">
                        Labels
                      </h3>
                      <div className="space-y-1 max-h-48 overflow-y-auto">
                        {availableLabels.map(label => (
                          <label
                            key={label}
                            className="flex items-center cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700 p-1 rounded"
                          >
                            <input
                              type="checkbox"
                              checked={selectedLabels.includes(label)}
                              onChange={() => handleLabelToggle(label)}
                              className="sr-only"
                            />
                            <div className={`flex items-center justify-center w-5 h-5 border-2 rounded mr-3 ${
                              selectedLabels.includes(label)
                                ? 'bg-accent-600 border-accent-600'
                                : 'border-gray-300 dark:border-gray-500'
                            }`}>
                              {selectedLabels.includes(label) && (
                                <Check className="w-3 h-3 text-white" />
                              )}
                            </div>
                            <span className="text-sm text-gray-700 dark:text-gray-200">{label}</span>
                          </label>
                        ))}
                      </div>
                    </div>
                  )}

                  {/* Birth Year Filter */}
                  {availableBirthYears.length > 0 && (
                    <div>
                      <h3 className="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-2">
                        Geboortejaar
                      </h3>
                      <select
                        value={selectedBirthYear}
                        onChange={(e) => setSelectedBirthYear(e.target.value)}
                        className="w-full text-sm border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-50 rounded-lg px-3 py-2 focus:ring-accent-500 focus:border-accent-500"
                      >
                        <option value="">Alle jaren</option>
                        {availableBirthYears.map(year => (
                          <option key={year} value={year}>{year}</option>
                        ))}
                      </select>
                    </div>
                  )}

                  {/* Last Modified Filter */}
                  <div>
                    <h3 className="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-2">
                      Laatst gewijzigd
                    </h3>
                    <select
                      value={lastModifiedFilter}
                      onChange={(e) => setLastModifiedFilter(e.target.value)}
                      className="w-full text-sm border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-50 rounded-lg px-3 py-2 focus:ring-accent-500 focus:border-accent-500"
                    >
                      <option value="">Alle tijden</option>
                      <option value="7">Laatste 7 dagen</option>
                      <option value="30">Laatste 30 dagen</option>
                      <option value="90">Laatste 90 dagen</option>
                      <option value="365">Laatste jaar</option>
                    </select>
                  </div>

                  {/* Ownership Filter */}
                  <div>
                    <h3 className="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-2">
                      Eigenaar
                    </h3>
                    <div className="space-y-1">
                      {[
                        { value: 'all', label: 'Alle leden' },
                        { value: 'mine', label: 'Mijn leden' },
                        { value: 'shared', label: 'Gedeeld met mij' },
                      ].map(option => (
                        <label
                          key={option.value}
                          className="flex items-center cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700 p-1 rounded"
                        >
                          <input
                            type="radio"
                            name="ownership"
                            value={option.value}
                            checked={ownershipFilter === option.value}
                            onChange={(e) => setOwnershipFilter(e.target.value)}
                            className="sr-only"
                          />
                          <div className={`flex items-center justify-center w-4 h-4 border-2 rounded-full mr-3 ${
                            ownershipFilter === option.value
                              ? 'border-accent-600'
                              : 'border-gray-300 dark:border-gray-500'
                          }`}>
                            {ownershipFilter === option.value && (
                              <div className="w-2 h-2 bg-accent-600 rounded-full" />
                            )}
                          </div>
                          <span className="text-sm text-gray-700 dark:text-gray-200">{option.label}</span>
                        </label>
                      ))}
                    </div>
                  </div>

                  {/* Workspace Filter */}
                  {workspaces.length > 0 && (
                    <div>
                      <h3 className="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-2">
                        Workspace
                      </h3>
                      <select
                        value={selectedWorkspaceFilter}
                        onChange={(e) => setSelectedWorkspaceFilter(e.target.value)}
                        className="w-full text-sm border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-50 rounded-lg px-3 py-2 focus:ring-accent-500 focus:border-accent-500"
                      >
                        <option value="">Alle workspaces</option>
                        {workspaces.map(ws => (
                          <option key={ws.id} value={ws.id}>{ws.title}</option>
                        ))}
                      </select>
                    </div>
                  )}

                  {/* Clear Filters */}
                  {hasActiveFilters && (
                    <button
                      onClick={clearFilters}
                      className="w-full text-sm text-accent-600 dark:text-accent-400 hover:text-accent-700 dark:hover:text-accent-300 font-medium pt-2 border-t border-gray-200 dark:border-gray-700"
                    >
                      Alle filters wissen
                    </button>
                  )}
                </div>
              </div>
            )}
          </div>

          {/* Active Filter Chips */}
          {hasActiveFilters && (
            <div className="flex flex-wrap gap-2">
              {selectedLabels.map(label => (
                <span
                  key={label}
                  className="inline-flex items-center gap-1 px-2 py-1 bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-full text-xs"
                >
                  {label}
                  <button
                    onClick={() => handleLabelToggle(label)}
                    className="hover:text-gray-600 dark:hover:text-gray-300"
                  >
                    <X className="w-3 h-3" />
                  </button>
                </span>
              ))}
              {selectedBirthYear && (
                <span className="inline-flex items-center gap-1 px-2 py-1 bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-full text-xs">
                  Geboren {selectedBirthYear}
                  <button
                    onClick={() => setSelectedBirthYear('')}
                    className="hover:text-gray-600 dark:hover:text-gray-300"
                  >
                    <X className="w-3 h-3" />
                  </button>
                </span>
              )}
              {lastModifiedFilter && (
                <span className="inline-flex items-center gap-1 px-2 py-1 bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-full text-xs">
                  Gewijzigd: {lastModifiedFilter === '7' ? 'laatste 7 dagen' :
                             lastModifiedFilter === '30' ? 'laatste 30 dagen' :
                             lastModifiedFilter === '90' ? 'laatste 90 dagen' : 'laatste jaar'}
                  <button
                    onClick={() => setLastModifiedFilter('')}
                    className="hover:text-gray-600 dark:hover:text-gray-300"
                  >
                    <X className="w-3 h-3" />
                  </button>
                </span>
              )}
              {ownershipFilter !== 'all' && (
                <span className="inline-flex items-center gap-1 px-2 py-1 bg-accent-100 dark:bg-accent-900/50 text-accent-800 dark:text-accent-200 rounded-full text-xs">
                  {ownershipFilter === 'mine' ? 'Mijn leden' : 'Gedeeld met mij'}
                  <button onClick={() => setOwnershipFilter('all')} className="hover:text-accent-600 dark:hover:text-accent-300">
                    <X className="w-3 h-3" />
                  </button>
                </span>
              )}
              {selectedWorkspaceFilter && (
                <span className="inline-flex items-center gap-1 px-2 py-1 bg-blue-100 dark:bg-blue-900/50 text-blue-800 dark:text-blue-200 rounded-full text-xs">
                  {workspaces.find(ws => ws.id === parseInt(selectedWorkspaceFilter))?.title || 'Workspace'}
                  <button onClick={() => setSelectedWorkspaceFilter('')} className="hover:text-blue-600 dark:hover:text-blue-300">
                    <X className="w-3 h-3" />
                  </button>
                </span>
              )}
            </div>
          )}
          
          <button onClick={() => setShowPersonModal(true)} className="btn-primary">
            <Plus className="w-4 h-4 md:mr-2" />
            <span className="hidden md:inline">Lid toevoegen</span>
          </button>
        </div>
      </div>
      
      {/* Loading state */}
      {isLoading && (
        <div className="flex items-center justify-center h-64">
          <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-accent-600 dark:border-accent-400"></div>
        </div>
      )}

      {/* Error state */}
      {error && (
        <div className="card p-6 text-center">
          <p className="text-red-600 dark:text-red-400">Leden konden niet worden geladen.</p>
        </div>
      )}

      {/* Empty state */}
      {!isLoading && !error && people?.length === 0 && (
        <div className="card p-12 text-center">
          <div className="w-12 h-12 bg-gray-100 dark:bg-gray-700 rounded-full flex items-center justify-center mx-auto mb-4">
            <Plus className="w-6 h-6 text-gray-400 dark:text-gray-500" />
          </div>
          <h3 className="text-lg font-medium text-gray-900 dark:text-gray-50 mb-1">Geen leden gevonden</h3>
          <p className="text-gray-500 dark:text-gray-400 mb-4">
            Voeg je eerste lid toe om te beginnen.
          </p>
          <button onClick={() => setShowPersonModal(true)} className="btn-primary">
            <Plus className="w-4 h-4 md:mr-2" />
            <span className="hidden md:inline">Lid toevoegen</span>
          </button>
        </div>
      )}

      {/* Selection toolbar - sticky */}
      {selectedIds.size > 0 && (
        <div className="sticky top-0 z-20 flex items-center justify-between bg-accent-50 dark:bg-accent-800 border border-accent-200 dark:border-accent-700 rounded-lg px-4 py-2 shadow-sm">
          <span className="text-sm text-accent-800 dark:text-accent-200 font-medium">
            {selectedIds.size} {selectedIds.size === 1 ? 'lid' : 'leden'} geselecteerd
          </span>
          <div className="flex items-center gap-3">
            {/* Bulk Actions Dropdown */}
            <div className="relative" ref={bulkDropdownRef}>
              <button
                onClick={() => setShowBulkDropdown(!showBulkDropdown)}
                className="flex items-center gap-1 px-3 py-1.5 text-sm font-medium text-accent-700 dark:text-accent-200 bg-white dark:bg-gray-800 border border-accent-300 dark:border-accent-600 rounded-md hover:bg-accent-50 dark:hover:bg-gray-700"
              >
                Acties
                <ChevronDown className={`w-4 h-4 transition-transform ${showBulkDropdown ? 'rotate-180' : ''}`} />
              </button>
              {showBulkDropdown && (
                <div className="absolute right-0 mt-1 w-48 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-lg z-50">
                  <div className="py-1">
                    <button
                      onClick={() => {
                        setShowBulkDropdown(false);
                        setShowBulkVisibilityModal(true);
                      }}
                      className="w-full flex items-center gap-2 px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700"
                    >
                      <Lock className="w-4 h-4" />
                      Zichtbaarheid wijzigen...
                    </button>
                    <button
                      onClick={() => {
                        setShowBulkDropdown(false);
                        setShowBulkWorkspaceModal(true);
                      }}
                      className="w-full flex items-center gap-2 px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700"
                    >
                      <Users className="w-4 h-4" />
                      Toewijzen aan workspace...
                    </button>
                    <button
                      onClick={() => {
                        setShowBulkDropdown(false);
                        setShowBulkOrganizationModal(true);
                      }}
                      className="w-full flex items-center gap-2 px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700"
                    >
                      <Building2 className="w-4 h-4" />
                      Team instellen...
                    </button>
                    <button
                      onClick={() => {
                        setShowBulkDropdown(false);
                        setShowBulkLabelsModal(true);
                      }}
                      className="w-full flex items-center gap-2 px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700"
                    >
                      <Tag className="w-4 h-4" />
                      Labels beheren...
                    </button>
                  </div>
                </div>
              )}
            </div>
            <button
              onClick={clearSelection}
              className="text-sm text-accent-600 dark:text-accent-400 hover:text-accent-800 dark:hover:text-accent-300 font-medium"
            >
              Selectie wissen
            </button>
          </div>
        </div>
      )}

      {/* People list */}
      {!isLoading && !error && sortedPeople?.length > 0 && (
        <PersonListView
          people={sortedPeople}
          teamMap={personTeamMap}
          workspaces={workspaces}
          listViewFields={listViewFields}
          selectedIds={selectedIds}
          onToggleSelection={toggleSelection}
          onToggleSelectAll={toggleSelectAll}
          isAllSelected={isAllSelected}
          isSomeSelected={isSomeSelected}
          sortField={sortField}
          sortOrder={sortOrder}
          onSort={(field) => {
            if (field === sortField) {
              setSortOrder(sortOrder === 'asc' ? 'desc' : 'asc');
            } else {
              setSortField(field);
              setSortOrder('asc');
            }
          }}
        />
      )}

      {/* No results with filters */}
      {!isLoading && !error && people?.length > 0 && sortedPeople?.length === 0 && (
        <div className="card p-12 text-center">
          <div className="w-12 h-12 bg-gray-100 dark:bg-gray-700 rounded-full flex items-center justify-center mx-auto mb-4">
            <Filter className="w-6 h-6 text-gray-400 dark:text-gray-500" />
          </div>
          <h3 className="text-lg font-medium text-gray-900 dark:text-gray-50 mb-1">Geen leden vinden die aan je filters voldoen</h3>
          <p className="text-gray-500 dark:text-gray-400 mb-4">
            Pas je filters aan om meer resultaten te zien.
          </p>
          <button onClick={clearFilters} className="btn-secondary">
            Filters wissen
          </button>
        </div>
      )}
      
      {/* Person Modal */}
      <PersonEditModal
        isOpen={showPersonModal}
        onClose={() => setShowPersonModal(false)}
        onSubmit={handleCreatePerson}
        isLoading={isCreatingPerson}
      />

      {/* Bulk Visibility Modal */}
      <BulkVisibilityModal
        isOpen={showBulkVisibilityModal}
        onClose={() => setShowBulkVisibilityModal(false)}
        selectedCount={selectedIds.size}
        onSubmit={async (visibility) => {
          setBulkActionLoading(true);
          try {
            await bulkUpdateMutation.mutateAsync({
              ids: Array.from(selectedIds),
              updates: { visibility }
            });
            clearSelection();
            setShowBulkVisibilityModal(false);
          } finally {
            setBulkActionLoading(false);
          }
        }}
        isLoading={bulkActionLoading}
      />

      {/* Bulk Workspace Modal */}
      <BulkWorkspaceModal
        isOpen={showBulkWorkspaceModal}
        onClose={() => setShowBulkWorkspaceModal(false)}
        selectedCount={selectedIds.size}
        workspaces={workspaces}
        onSubmit={async (workspaceIds) => {
          setBulkActionLoading(true);
          try {
            await bulkUpdateMutation.mutateAsync({
              ids: Array.from(selectedIds),
              updates: { assigned_workspaces: workspaceIds }
            });
            clearSelection();
            setShowBulkWorkspaceModal(false);
          } finally {
            setBulkActionLoading(false);
          }
        }}
        isLoading={bulkActionLoading}
      />

      {/* Bulk Organization Modal */}
      <BulkOrganizationModal
        isOpen={showBulkOrganizationModal}
        onClose={() => setShowBulkOrganizationModal(false)}
        selectedCount={selectedIds.size}
        teams={allTeamsData || []}
        onSubmit={async (teamId) => {
          setBulkActionLoading(true);
          try {
            await bulkUpdateMutation.mutateAsync({
              ids: Array.from(selectedIds),
              updates: { organization_id: teamId }
            });
            clearSelection();
            setShowBulkOrganizationModal(false);
          } finally {
            setBulkActionLoading(false);
          }
        }}
        isLoading={bulkActionLoading}
      />

      {/* Bulk Labels Modal */}
      <BulkLabelsModal
        isOpen={showBulkLabelsModal}
        onClose={() => setShowBulkLabelsModal(false)}
        selectedCount={selectedIds.size}
        labels={availableLabelsWithIds}
        onSubmit={async (mode, labelIds) => {
          setBulkActionLoading(true);
          try {
            const updates = mode === 'add'
              ? { labels_add: labelIds }
              : { labels_remove: labelIds };
            await bulkUpdateMutation.mutateAsync({
              ids: Array.from(selectedIds),
              updates
            });
            clearSelection();
            setShowBulkLabelsModal(false);
          } finally {
            setBulkActionLoading(false);
          }
        }}
        isLoading={bulkActionLoading}
      />
    </div>
  );
}
