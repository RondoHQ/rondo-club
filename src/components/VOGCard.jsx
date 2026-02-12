import { useState } from 'react';
import { ShieldCheck, ShieldAlert, ShieldX, Mail, FileCheck } from 'lucide-react';
import { format } from '@/utils/dateFormat';
import { useCurrentUser } from '@/hooks/useCurrentUser';

/**
 * Calculate VOG status based on date
 * @param {string|null} vogDate - The VOG date in ISO format
 * @returns {Object} Status object with status, label, and color
 */
function calculateVogStatus(vogDate) {
  if (!vogDate) {
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
 * VOG status card for person detail page
 * Shows VOG information only for current volunteers
 */
export default function VOGCard({ acfData, personId, onUpdateField, isUpdating }) {
  const { data: currentUser } = useCurrentUser();
  const [editingField, setEditingField] = useState(null);

  // Hide card if user doesn't have VOG capability
  if (!currentUser?.can_access_vog) {
    return null;
  }

  // Check if person is a current volunteer (auto-calculated field)
  const isVolunteer = acfData?.['huidig-vrijwilliger'] === true || acfData?.['huidig-vrijwilliger'] === '1';

  // If not a volunteer, don't show the card
  if (!isVolunteer) {
    return null;
  }

  const vogDate = acfData?.vog_datum || acfData?.['datum-vog'];
  const vogStatus = calculateVogStatus(vogDate);

  // VOG process tracking fields
  const emailSentDate = acfData?.vog_email_sent_date;
  const justisSubmittedDate = acfData?.vog_justis_submitted_date;

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
          {vogDate && (
            <span className="text-sm text-gray-500 dark:text-gray-400 ml-2">
              ({format(new Date(vogDate), 'd MMM yyyy')})
            </span>
          )}
        </div>
      </div>

      {/* Show process status when VOG is missing or expired */}
      {(vogStatus.status === 'missing' || vogStatus.status === 'expired') && (
        <div className="space-y-2 text-sm">
          {/* Email Sent */}
          <div className="flex items-center gap-2">
            <Mail className={`w-4 h-4 ${emailSentDate ? 'text-green-500' : 'text-gray-300 dark:text-gray-600'}`} />
            <span className="text-gray-600 dark:text-gray-400">
              E-mail verzonden:
            </span>
            {editingField === 'vog_email_sent_date' ? (
              <input
                type="date"
                defaultValue={emailSentDate || ''}
                className="px-2 py-1 text-sm border rounded dark:bg-gray-700 dark:border-gray-600"
                autoFocus
                disabled={isUpdating}
                onChange={(e) => {
                  if (e.target.value) {
                    onUpdateField('vog_email_sent_date', e.target.value);
                  }
                  setEditingField(null);
                }}
                onBlur={() => setEditingField(null)}
              />
            ) : (
              <button
                onClick={() => setEditingField('vog_email_sent_date')}
                disabled={isUpdating || !personId}
                className="text-gray-900 dark:text-gray-100 hover:text-brand-primary dark:hover:text-brand-secondary underline decoration-dotted disabled:opacity-50"
              >
                {emailSentDate ? format(new Date(emailSentDate), 'd MMM yyyy') : 'Nog niet'}
              </button>
            )}
          </div>

          {/* Justis Submitted */}
          <div className="flex items-center gap-2">
            <FileCheck className={`w-4 h-4 ${justisSubmittedDate ? 'text-green-500' : 'text-gray-300 dark:text-gray-600'}`} />
            <span className="text-gray-600 dark:text-gray-400">
              Justis aanvraag:
            </span>
            {editingField === 'vog_justis_submitted_date' ? (
              <input
                type="date"
                defaultValue={justisSubmittedDate || ''}
                className="px-2 py-1 text-sm border rounded dark:bg-gray-700 dark:border-gray-600"
                autoFocus
                disabled={isUpdating}
                onChange={(e) => {
                  if (e.target.value) {
                    onUpdateField('vog_justis_submitted_date', e.target.value);
                  }
                  setEditingField(null);
                }}
                onBlur={() => setEditingField(null)}
              />
            ) : (
              <button
                onClick={() => setEditingField('vog_justis_submitted_date')}
                disabled={isUpdating || !personId}
                className="text-gray-900 dark:text-gray-100 hover:text-brand-primary dark:hover:text-brand-secondary underline decoration-dotted disabled:opacity-50"
              >
                {justisSubmittedDate ? format(new Date(justisSubmittedDate), 'd MMM yyyy') : 'Nog niet'}
              </button>
            )}
          </div>
        </div>
      )}

      {/* Show valid VOG details */}
      {vogStatus.status === 'valid' && vogDate && (
        <div className="text-sm text-gray-500 dark:text-gray-400">
          <span>Geldig tot: </span>
          <span className="text-gray-900 dark:text-gray-100">
            {format(new Date(new Date(vogDate).setFullYear(new Date(vogDate).getFullYear() + 3)), 'd MMM yyyy')}
          </span>
        </div>
      )}
    </div>
  );
}
