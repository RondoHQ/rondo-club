import { useRef } from 'react';
import { ShieldCheck, ShieldAlert, ShieldX, Mail, FileCheck, Bell, CalendarDays } from 'lucide-react';
import { format } from '@/utils/dateFormat';
import { useCurrentUser } from '@/hooks/useCurrentUser';
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
    <div className="flex items-center gap-2">
      <Icon className={`w-4 h-4 flex-shrink-0 ${hasValue ? 'text-green-500' : 'text-gray-300 dark:text-gray-600'}`} />
      <span className="text-gray-600 dark:text-gray-400 whitespace-nowrap flex-1">
        {label}
      </span>
      <input
        ref={inputRef}
        type="date"
        key={value || ''}
        defaultValue={value || ''}
        className="px-2 py-1 text-sm border rounded dark:bg-gray-700 dark:border-gray-600 text-gray-900 dark:text-gray-100 w-[160px] shrink-0"
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
export default function VOGCard({ acfData, personId, onUpdateField, isUpdating }) {
  const { data: currentUser } = useCurrentUser();

  // Hide card if user doesn't have VOG capability
  if (!currentUser?.can_access_vog) {
    return null;
  }

  // Check if person is a current volunteer (auto-calculated field)
  const isVolunteer = acfData?.['huidig_vrijwilliger'] === true || acfData?.['huidig_vrijwilliger'] === '1';

  // If not a volunteer, don't show the card
  if (!isVolunteer) {
    return null;
  }

  const vogDate = acfData?.vog_datum || acfData?.['datum_vog'];
  const vogStatus = calculateVogStatus(vogDate);

  // VOG process tracking fields
  const emailSentDate = acfData?.vog_email_sent_date;
  const justisSubmittedDate = acfData?.vog_justis_submitted_date;
  const reminderSentDate = acfData?.vog_reminder_sent_date;
  const hasValidVogDate = !!(vogDate && isValidDate(vogDate));

  // Determine which icon to show
  function getStatusIcon(status) {
    switch (status) {
      case 'valid':
        return ShieldCheck;
      case 'expired':
        return ShieldAlert;
      default:
        return ShieldX;
    }
  }
  const StatusIcon = getStatusIcon(vogStatus.status);

  const statusColorClass = {
    valid: 'text-green-600 dark:text-green-400',
    expired: 'text-amber-600 dark:text-amber-400',
    missing: 'text-red-600 dark:text-red-400',
  }[vogStatus.status];

  const bgColorClass = {
    valid: 'bg-green-50 dark:bg-green-900/20 border-green-200 dark:border-green-800',
    expired: 'bg-amber-50 dark:bg-amber-900/20 border-amber-200 dark:border-amber-800',
    missing: 'bg-red-50 dark:bg-red-900/20 border-red-200 dark:border-red-800',
  }[vogStatus.status];

  return (
    <div className="card p-6 mb-4">
      {/* Header */}
      <div className="flex items-center gap-2 mb-4">
        <StatusIcon className={`w-5 h-5 ${statusColorClass}`} />
        <h2 className="font-semibold text-brand-gradient">VOG Status</h2>
      </div>

      {/* Status Banner */}
      <div className={`flex items-center gap-2 p-3 mb-3 border rounded-lg ${bgColorClass}`}>
        <StatusIcon className={`w-5 h-5 ${statusColorClass} flex-shrink-0`} />
        <div>
          <span className={`font-medium ${statusColorClass}`}>
            {vogStatus.label}
          </span>
        </div>
      </div>

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

      {/* Show expiry date when valid */}
      {vogStatus.status === 'valid' && hasValidVogDate && (
        <div className="text-sm text-gray-500 dark:text-gray-400 mt-2">
          <span>Geldig tot: </span>
          <span className="text-gray-900 dark:text-gray-100">
            {format(new Date(new Date(vogDate).setFullYear(new Date(vogDate).getFullYear() + 3)), 'd MMM yyyy')}
          </span>
        </div>
      )}
    </div>
  );
}
