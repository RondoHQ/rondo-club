import { useEffect, useId, useRef, useState } from 'react';
import { ChevronDown, Check } from 'lucide-react';

/** Single selection from existing options; typing never changes the saved value. */
export default function SearchableSelect({ label, options, value, onChange, placeholder = 'Zoeken...', emptyMessage = 'Geen opties gevonden' }) {
  const id = useId();
  const [open, setOpen] = useState(false);
  const [query, setQuery] = useState('');
  const [activeIndex, setActiveIndex] = useState(0);
  const listRef = useRef(null);
  const selected = options.find((option) => option.id === value);
  const term = query.trim().toLocaleLowerCase('nl');
  const filtered = options.filter((option) => option.label.toLocaleLowerCase('nl').includes(term));
  const active = filtered[activeIndex];

  useEffect(() => {
    if (open) listRef.current?.children[activeIndex]?.scrollIntoView({ block: 'nearest' });
  }, [open, activeIndex, query]);

  function showOptions() {
    setQuery('');
    setActiveIndex(0);
    setOpen(true);
  }

  function choose(option) {
    onChange(option.id);
    setOpen(false);
    setQuery('');
  }

  function handleKeyDown(event) {
    if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
      event.preventDefault();
      if (!open) showOptions();
      else setActiveIndex((index) => Math.max(0, Math.min(filtered.length - 1, index + (event.key === 'ArrowDown' ? 1 : -1))));
    } else if (event.key === 'Enter' && open) {
      event.preventDefault();
      if (active) choose(active);
    } else if (event.key === 'Escape' && open) {
      event.preventDefault();
      event.stopPropagation();
      setOpen(false);
    }
  }

  return <div className="relative min-w-0 text-sm">
    <label htmlFor={id}>{label}</label>
    <div className="relative mt-1">
      <input
        id={id}
        role="combobox"
        aria-autocomplete="list"
        aria-expanded={open}
        aria-controls={`${id}-options`}
        aria-activedescendant={open && active ? `${id}-option-${active.id}` : undefined}
        autoComplete="off"
        value={open ? query : (selected?.label || '')}
        placeholder={placeholder}
        onFocus={showOptions}
        onClick={() => { if (!open) showOptions(); }}
        onBlur={() => setOpen(false)}
        onChange={(event) => { setQuery(event.target.value); setActiveIndex(0); setOpen(true); }}
        onKeyDown={handleKeyDown}
        className="input w-full pr-9"
      />
      <ChevronDown aria-hidden="true" className="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-500 dark:text-gray-400" />
    </div>
    {open && <div className="absolute z-20 mt-1 max-h-60 w-full overflow-y-auto rounded-lg border border-gray-300 bg-white dark:border-gray-600 dark:bg-gray-800">
      <ul id={`${id}-options`} role="listbox" aria-label={label} ref={listRef}>
        {filtered.map((option, index) => <li
          key={option.id}
          id={`${id}-option-${option.id}`}
          role="option"
          aria-selected={option.id === value}
          onMouseDown={(event) => event.preventDefault()}
          onClick={() => choose(option)}
          className={`flex cursor-pointer items-center justify-between gap-2 px-3 py-3 break-words hover:bg-gray-100 dark:hover:bg-gray-700 ${index === activeIndex ? 'bg-gray-100 dark:bg-gray-700' : ''} text-gray-900 dark:text-gray-100`}
        >
          <span className="min-w-0">{option.label}</span>
          {option.id === value && <Check aria-hidden="true" className="h-4 w-4 shrink-0" />}
        </li>)}
      </ul>
      {filtered.length === 0 && <p role="status" className="px-3 py-3 text-gray-600 dark:text-gray-300">{emptyMessage}</p>}
    </div>}
  </div>;
}
