import { useState } from 'react';
import { Lock, Users, ChevronDown, Check } from 'lucide-react';
import { useWorkspaces } from '@/hooks/useWorkspaces';

export default function VisibilitySelector({
  value = 'private',           // Current visibility value
  workspaces = [],             // Selected workspace IDs
  onChange,                    // Called with { visibility, workspaces }
  disabled = false
}) {
  const [isOpen, setIsOpen] = useState(false);
  const { data: availableWorkspaces = [], isLoading } = useWorkspaces();

  const visibilityOptions = [
    {
      value: 'private',
      label: 'Private',
      description: 'Only you can see this',
      icon: Lock
    },
    {
      value: 'workspace',
      label: 'Workspace',
      description: 'Share with workspace members',
      icon: Users
    },
  ];

  const currentOption = visibilityOptions.find(opt => opt.value === value) || visibilityOptions[0];
  const CurrentIcon = currentOption.icon;

  const handleVisibilityChange = (newVisibility) => {
    onChange?.({
      visibility: newVisibility,
      workspaces: newVisibility === 'private' ? [] : workspaces
    });
    if (newVisibility === 'private') {
      setIsOpen(false);
    }
  };

  const handleWorkspaceToggle = (workspaceId) => {
    const newWorkspaces = workspaces.includes(workspaceId)
      ? workspaces.filter(id => id !== workspaceId)
      : [...workspaces, workspaceId];
    onChange?.({ visibility: value, workspaces: newWorkspaces });
  };

  return (
    <div className="relative">
      <label className="block text-sm font-medium text-gray-700 mb-1">
        Visibility
      </label>

      {/* Dropdown trigger */}
      <button
        type="button"
        onClick={() => !disabled && setIsOpen(!isOpen)}
        disabled={disabled}
        className="w-full flex items-center justify-between px-3 py-2 border border-gray-300 rounded-lg bg-white text-left focus:ring-2 focus:ring-primary-500 focus:border-primary-500 disabled:bg-gray-100 disabled:cursor-not-allowed"
      >
        <div className="flex items-center gap-2">
          <CurrentIcon className="w-4 h-4 text-gray-500" />
          <span className="text-sm text-gray-900">{currentOption.label}</span>
        </div>
        <ChevronDown className={`w-4 h-4 text-gray-400 transition-transform ${isOpen ? 'rotate-180' : ''}`} />
      </button>

      {/* Dropdown menu */}
      {isOpen && (
        <div className="absolute z-50 mt-1 w-full bg-white border border-gray-200 rounded-lg shadow-lg">
          {/* Visibility options */}
          <div className="p-2 border-b border-gray-100">
            {visibilityOptions.map((option) => {
              const Icon = option.icon;
              const isSelected = value === option.value;
              return (
                <button
                  key={option.value}
                  type="button"
                  onClick={() => handleVisibilityChange(option.value)}
                  className={`w-full flex items-start gap-3 p-2 rounded-md text-left hover:bg-gray-50 ${isSelected ? 'bg-primary-50' : ''}`}
                >
                  <Icon className={`w-4 h-4 mt-0.5 ${isSelected ? 'text-primary-600' : 'text-gray-400'}`} />
                  <div className="flex-1">
                    <div className={`text-sm font-medium ${isSelected ? 'text-primary-900' : 'text-gray-900'}`}>
                      {option.label}
                    </div>
                    <div className="text-xs text-gray-500">{option.description}</div>
                  </div>
                  {isSelected && <Check className="w-4 h-4 text-primary-600 mt-0.5" />}
                </button>
              );
            })}
          </div>

          {/* Workspace selection (only when visibility is 'workspace') */}
          {value === 'workspace' && (
            <div className="p-2">
              <div className="text-xs font-medium text-gray-500 uppercase tracking-wide mb-2 px-2">
                Select Workspaces
              </div>
              {isLoading ? (
                <div className="px-2 py-3 text-sm text-gray-500">Loading workspaces...</div>
              ) : availableWorkspaces.length === 0 ? (
                <div className="px-2 py-3 text-sm text-gray-500">
                  No workspaces available. Create one first.
                </div>
              ) : (
                <div className="max-h-48 overflow-y-auto">
                  {availableWorkspaces.map((workspace) => {
                    const isChecked = workspaces.includes(workspace.id);
                    return (
                      <button
                        key={workspace.id}
                        type="button"
                        onClick={() => handleWorkspaceToggle(workspace.id)}
                        className="w-full flex items-center gap-3 p-2 rounded-md text-left hover:bg-gray-50"
                      >
                        <div className={`flex items-center justify-center w-4 h-4 border-2 rounded ${isChecked ? 'bg-primary-600 border-primary-600' : 'border-gray-300'}`}>
                          {isChecked && <Check className="w-3 h-3 text-white" />}
                        </div>
                        <div className="flex-1">
                          <div className="text-sm text-gray-900">{workspace.title}</div>
                          <div className="text-xs text-gray-500">{workspace.member_count} members</div>
                        </div>
                      </button>
                    );
                  })}
                </div>
              )}
            </div>
          )}

          {/* Close button for workspace selection */}
          {value === 'workspace' && (
            <div className="p-2 border-t border-gray-100">
              <button
                type="button"
                onClick={() => setIsOpen(false)}
                className="w-full px-3 py-2 text-sm font-medium text-primary-600 hover:bg-primary-50 rounded-md"
              >
                Done
              </button>
            </div>
          )}
        </div>
      )}

      {/* Selected workspaces summary */}
      {value === 'workspace' && workspaces.length > 0 && !isOpen && (
        <div className="mt-1 text-xs text-gray-500">
          Shared with {workspaces.length} workspace{workspaces.length !== 1 ? 's' : ''}
        </div>
      )}
    </div>
  );
}
