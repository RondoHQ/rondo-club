import { useEffect, useState, useRef, useMemo } from 'react';
import { X, Check } from 'lucide-react';

/**
 * Searchable multi-select component with chips
 *
 * @param {Array} options - Array of { id, label } objects
 * @param {Array} selectedIds - Array of currently selected IDs
 * @param {Function} onChange - Callback with updated ID array when selection changes
 * @param {String} placeholder - Search input placeholder (default: "Zoeken...")
 * @param {String} emptyMessage - Message when no options match (default: "Geen opties gevonden")
 */
export default function SearchableMultiSelect({
  options,
  selectedIds = [],
  onChange,
  placeholder = "Zoeken...",
  emptyMessage = "Geen opties gevonden"
}) {
  const [searchTerm, setSearchTerm] = useState('');
  const [isOpen, setIsOpen] = useState(false);
  const containerRef = useRef(null);

  // Filter options based on search term
  const filteredOptions = useMemo(() => {
    if (!searchTerm) return options;
    const term = searchTerm.toLowerCase();
    return options.filter(option => option.label.toLowerCase().includes(term));
  }, [options, searchTerm]);

  // Get selected options for chips
  const selectedOptions = useMemo(() => {
    return options.filter(option => selectedIds.includes(option.id));
  }, [options, selectedIds]);

  // Handle click outside to close dropdown
  useEffect(() => {
    const handleClickOutside = (event) => {
      if (containerRef.current && !containerRef.current.contains(event.target)) {
        setIsOpen(false);
      }
    };

    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, []);

  // Toggle option selection
  const toggleOption = (optionId) => {
    const isSelected = selectedIds.includes(optionId);
    const newIds = isSelected
      ? selectedIds.filter(id => id !== optionId)
      : [...selectedIds, optionId];
    onChange(newIds);
    // Clear search term and keep dropdown open for quick multi-selection
    setSearchTerm('');
  };

  // Remove chip
  const removeChip = (optionId) => {
    onChange(selectedIds.filter(id => id !== optionId));
  };

  const handleInputChange = (e) => {
    setSearchTerm(e.target.value);
    setIsOpen(true);
  };

  const handleFocus = () => {
    setIsOpen(true);
  };

  return (
    <div ref={containerRef} className="relative">
      {/* Selected chips area */}
      {selectedOptions.length > 0 && (
        <div className="flex flex-wrap gap-1.5 mb-2">
          {selectedOptions.map(option => (
            <span
              key={option.id}
              className="inline-flex items-center gap-1 px-2 py-1 text-sm rounded-md bg-cyan-50 dark:bg-cyan-900/30 text-electric-cyan dark:text-cyan-300 border border-cyan-200 dark:border-cyan-800"
            >
              {option.label}
              <button
                type="button"
                onClick={() => removeChip(option.id)}
                className="hover:bg-cyan-100 dark:hover:bg-cyan-800/50 rounded-sm"
              >
                <X className="w-3 h-3" />
              </button>
            </span>
          ))}
        </div>
      )}

      {/* Search input */}
      <div className="relative">
        <input
          type="text"
          value={searchTerm}
          onChange={handleInputChange}
          onFocus={handleFocus}
          placeholder={placeholder}
          className="w-full px-3 py-2 rounded-md border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-electric-cyan focus:ring-electric-cyan sm:text-sm"
        />
      </div>

      {/* Dropdown list */}
      {isOpen && (
        <div className="absolute z-20 w-full mt-1 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-lg max-h-48 overflow-y-auto">
          {filteredOptions.length > 0 ? (
            filteredOptions.map(option => {
              const isSelected = selectedIds.includes(option.id);
              return (
                <button
                  key={option.id}
                  type="button"
                  onClick={() => toggleOption(option.id)}
                  className={`w-full text-left px-3 py-2 text-sm flex items-center justify-between hover:bg-gray-100 dark:hover:bg-gray-700 ${
                    isSelected
                      ? 'bg-cyan-50 dark:bg-cyan-900/20 text-electric-cyan dark:text-cyan-300'
                      : 'text-gray-900 dark:text-gray-100'
                  }`}
                >
                  <span>{option.label}</span>
                  {isSelected && <Check className="w-4 h-4" />}
                </button>
              );
            })
          ) : (
            <div className="px-3 py-2 text-sm text-gray-500 dark:text-gray-400">
              {emptyMessage}
            </div>
          )}
        </div>
      )}
    </div>
  );
}
