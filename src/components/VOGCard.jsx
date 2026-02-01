import { ShieldCheck, ShieldAlert, ShieldX, Mail, FileCheck } from 'lucide-react';
import { format } from '@/utils/dateFormat';

/**
 * Calculate VOG status based on date
 * @param {string|null} vogDate - The VOG date in ISO format
 * @returns {Object|null} Status object with status, label, and color
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
  } else {
    return { status: 'expired', label: 'VOG verlopen', color: 'orange' };
  }
}

/**
 * VOG status card for person detail page
 * Shows VOG information only for current volunteers
 */
export default function VOGCard({ acfData }) {
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
  const StatusIcon = vogStatus.status === 'valid'
    ? ShieldCheck
    : vogStatus.status === 'expired'
      ? ShieldAlert
      : ShieldX;

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
        <h2 className="font-semibold">VOG Status</h2>
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
            <span className={emailSentDate ? 'text-gray-900 dark:text-gray-100' : 'text-gray-400 dark:text-gray-500 italic'}>
              {emailSentDate ? format(new Date(emailSentDate), 'd MMM yyyy') : 'Nog niet'}
            </span>
          </div>

          {/* Justis Submitted */}
          <div className="flex items-center gap-2">
            <FileCheck className={`w-4 h-4 ${justisSubmittedDate ? 'text-green-500' : 'text-gray-300 dark:text-gray-600'}`} />
            <span className="text-gray-600 dark:text-gray-400">
              Justis aanvraag:
            </span>
            <span className={justisSubmittedDate ? 'text-gray-900 dark:text-gray-100' : 'text-gray-400 dark:text-gray-500 italic'}>
              {justisSubmittedDate ? format(new Date(justisSubmittedDate), 'd MMM yyyy') : 'Nog niet'}
            </span>
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
