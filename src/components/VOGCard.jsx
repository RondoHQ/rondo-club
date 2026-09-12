import { useRef, useState } from 'react';
import { Mail, FileCheck, Bell, CalendarDays, Pencil } from 'lucide-react';
import DocumentStatusBadge from '@/components/DocumentStatusBadge';
import { format } from '@/utils/dateFormat';
import { isValidDate } from '@/utils/formatters';

/**
 * Calculate VOG status based on date
 * @param {string|null} vogDate - The VOG date in ISO format
 * @returns {Object} Status object with status, label, and color
 */
function calculateVogStatus(vogDate) {
  if (!vogDate || !isValidDate(vogDate)) {
    return { status: 'missing', label: 'Geen VOG', color: 'red' };
  }

  const vogDateObj = new Date(vogDate);
  const threeYearsAgo = new Date();
  threeYearsAgo.setFullYear(threeYearsAgo.getFullYear() - 3);

  if (vogDateObj >= threeYearsAgo) {
    return { status: 'valid', label: 'VOG geldig', color: 'green' };
  }

  return { status: 'expired', label: 'VOG verlopen', color: 'orange' };
}

/**
 * Inline editable date field — always shows an <input type="date">.
 * Saves on change when a valid date is selected.
 */
function DateField({ icon: Icon, label, value, fieldName, onUpdateField, isUpdating, personId }) {
  const hasValue = !!(value && isValidDate(value));
  const inputRef = useRef(null);

  const save = () => {
    const val = inputRef.current?.value;
    if (val && /^\d{4}-\d{2}-\d{2}$/.test(val) && new Date(val).getFullYear() > 2000 && val !== (value || '')) {
      onUpdateField(fieldName, val);
    }
  };

  return (
    <div className="flex flex-wrap items-center gap-2">
      <Icon className={`w-4 h-4 flex-shrink-0 ${hasValue ? 'text-green-500' : 'text-gray-300 dark:text-gray-600'}`} />
      <span className="text-gray-600 dark:text-gray-400 whitespace-nowrap flex-1">
        {label}
      </span>
      <input
        aria-label={label}
        ref={inputRef}
        type="date"
        key={value || ''}
        defaultValue={value || ''}
        className="px-2 py-1 text-sm border rounded dark:bg-gray-700 dark:border-gray-600 text-gray-900 dark:text-gray-100 w-[160px] max-w-full"
        disabled={isUpdating || !personId}
        onChange={save}
        onBlur={save}
      />
    </div>
  );
}

/**
 * VOG status card for person detail page
 * Shows VOG information only for current volunteers
 */
export default function VOGCard({ fieldData, personId, onUpdateField, isUpdating }) {
  const [editing, setEditing] = useState(false);

  const vogDate = fieldData?.vog_datum || fieldData?.['datum_vog'];
  const vogStatus = calculateVogStatus(vogDate);

  // VOG process tracking fields
  const emailSentDate = fieldData?.vog_email_sent_date;
  const justisSubmittedDate = fieldData?.vog_justis_submitted_date;
  const reminderSentDate = fieldData?.vog_reminder_sent_date;
  const hasValidVogDate = !!(vogDate && isValidDate(vogDate));

  return (
    <section aria-label="VOG">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <h3 className="text-sm font-medium text-gray-900 dark:text-gray-100">VOG</h3>
        <DocumentStatusBadge status={vogStatus.status} />
      </div>
      {vogStatus.status === 'valid' && hasValidVogDate && (
        <p className="mt-2 text-sm text-gray-600 dark:text-gray-400">
          Geldig tot {format(new Date(new Date(vogDate).setFullYear(new Date(vogDate).getFullYear() + 3)), 'd MMMM yyyy')}
        </p>
      )}
      <div className="mt-2 flex flex-wrap items-center justify-between gap-2">
        <span className="text-sm text-gray-500 dark:text-gray-400">
          {hasValidVogDate ? `Afgegeven op ${format(new Date(vogDate), 'd MMM yyyy')}` : 'Geen VOG geregistreerd'}
        </span>
        {onUpdateField && (
          <button type="button" onClick={() => setEditing(!editing)} aria-expanded={editing} className="btn-tertiary text-sm" aria-label={editing ? 'VOG bewerken sluiten' : 'VOG bewerken'}>
            <Pencil className="w-3.5 h-3.5" aria-hidden="true" />{editing ? 'Sluiten' : 'Bewerken'}
          </button>
        )}
      </div>
      {editing && (
        <div className="mt-3 space-y-3">
          {/* VOG Date (editable) */}
          <div className="space-y-2 text-sm mb-3">
            <DateField
              icon={CalendarDays}
              label="Datum VOG:"
              value={vogDate}
              fieldName="datum_vog"
              onUpdateField={onUpdateField}
              isUpdating={isUpdating}
              personId={personId}
            />
          </div>

          {/* Show process status when VOG is missing or expired */}
          {(vogStatus.status === 'missing' || vogStatus.status === 'expired') && (
            <div className="space-y-2 text-sm">
              <DateField
                icon={Mail}
                label="E-mail verzonden:"
                value={emailSentDate}
                fieldName="vog_email_sent_date"
                onUpdateField={onUpdateField}
                isUpdating={isUpdating}
                personId={personId}
              />
              <DateField
                icon={FileCheck}
                label="Justis aanvraag:"
                value={justisSubmittedDate}
                fieldName="vog_justis_submitted_date"
                onUpdateField={onUpdateField}
                isUpdating={isUpdating}
                personId={personId}
              />
              <DateField
                icon={Bell}
                label="Herinnering:"
                value={reminderSentDate}
                fieldName="vog_reminder_sent_date"
                onUpdateField={onUpdateField}
                isUpdating={isUpdating}
                personId={personId}
              />
            </div>
          )}
        </div>
      )}
      {!editing && vogStatus.status !== 'valid' && (emailSentDate || justisSubmittedDate || reminderSentDate) && (
        <details className="mt-3 text-sm">
          <summary className="cursor-pointer text-bright-cobalt dark:text-electric-cyan">Aanvraagstatus</summary>
          <dl className="mt-2 space-y-2">
            {[["E-mail verzonden", emailSentDate], ["Justis aanvraag", justisSubmittedDate], ["Herinnering", reminderSentDate]].filter(([, value]) => value && isValidDate(value)).map(([label, value]) => (
              <div key={label} className="flex flex-wrap justify-between gap-2"><dt className="text-gray-500 dark:text-gray-400">{label}</dt><dd>{format(new Date(value), 'd MMM yyyy')}</dd></div>
            ))}
          </dl>
        </details>
      )}
    </section>
  );
}
