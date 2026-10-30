import { formatPersonSurname } from '../../utils/formatters.js';
import { getCurrentTeamId } from './peopleListUtils.js';
import { SHIFT_COLUMNS, shiftColumnValue } from './shiftProgress.js';
import { getFirstEmail, getFirstPhone, getPrimaryAddress, formatStreetLine, getMembershipTypeLabel, getCharacteristicBadges } from './peopleListValues.js';

function textValue(value) {
  if (value == null) return '';
  if (Array.isArray(value)) return value.map(textValue).filter(value => value !== '').join(', ');
  if (typeof value === 'object') return value.post_title || value.title?.rendered || value.label || value.filename || value.url || (value.ID || value.id ? `#${value.ID || value.id}` : '');
  return value;
}

function customValue(field, value) {
  if (value == null || value === '') return '';
  if (field.type === 'true_false') return value === true || value === 1 || value === '1' ? (field.ui_on_text || 'Ja') : (field.ui_off_text || 'Nee');
  if (field.type === 'date') return String(value).slice(0, 10);
  if (field.type === 'wysiwyg') {
    const plain = String(value).replace(/<[^>]*>/g, '').trim();
    const textarea = document.createElement('textarea');
    textarea.innerHTML = plain;
    return textarea.value;
  }
  if (field.type === 'relationship') {
    return (Array.isArray(value) ? value : [value]).filter(Boolean).map(item => typeof item === 'object' ? textValue(item) : `#${item}`).join(', ');
  }
  return textValue(value);
}

export function peopleExportValue(person, column, customFields, teamNames) {
  const id = column.id;
  if (SHIFT_COLUMNS.includes(id)) {
    const value = shiftColumnValue(person.shift_progress, id);
    return id === 'shift_status' && person.shift_progress?.family ? `${value} (Inclusief gezin)` : value;
  }
  switch (id) {
    case 'first_name': return person.first_name || '';
    case 'last_name': return formatPersonSurname(person.infix, person.last_name);
    case 'company_name': return person.fields?.company_name || '';
    case 'type_lid': return getMembershipTypeLabel(person);
    case 'characteristics': return getCharacteristicBadges(person.characteristics).map(badge => badge.label).join(', ');
    case 'email': return getFirstEmail(person) || '';
    case 'phone': return getFirstPhone(person) || '';
    case 'team': return teamNames[getCurrentTeamId(person)] || '';
    case 'birthdate': return person.fields?.birthdate || '';
    case 'modified': return person.modified?.slice(0, 10) || '';
    case 'address': return formatStreetLine(getPrimaryAddress(person));
    case 'postal_code':
    case 'city':
    case 'country': return getPrimaryAddress(person)?.[id] || '';
    default: {
      const field = customFields[id] || column;
      return customValue(field, person.fields?.[field.canonical_name || field.name || id]);
    }
  }
}

/** Column metadata comes from the same permission-filtered preferences as the table. */
export function buildPeopleExportRows(people, columns, customFields = {}, teamNames = {}) {
  const safeCell = value => typeof value === 'string' && /^[\s]*[=+@-]/.test(value) ? `'${value}` : value;
  return [
    columns.map(column => column.label),
    ...people.map(person => columns.map(column => peopleExportValue(person, column, customFields, teamNames))),
  ].map(row => row.map(safeCell));
}
