import { Database } from 'lucide-react';
import { format } from '@/utils/dateFormat';

/**
 * Sportlink info card for person detail page
 * Shows Sportlink sync fields (lid-sinds, lid-tot, leeftijdsgroep, type-lid, datum-foto, isparent)
 */
export default function SportlinkCard({ acfData }) {
  // Get Sportlink field values
  const knvbId = acfData?.['knvb-id'];
  const lidSinds = acfData?.['lid-sinds'];
  const lidTot = acfData?.['lid-tot'];
  const leeftijdsgroep = acfData?.leeftijdsgroep;
  const typeLid = acfData?.['type-lid'];
  const datumFoto = acfData?.['datum-foto'];
  const isParent = acfData?.isparent;

  // Check if at least one field (other than isparent alone) is populated
  const hasData = knvbId || lidSinds || lidTot || leeftijdsgroep || typeLid || datumFoto;

  // Hide card if no Sportlink data
  if (!hasData) {
    return null;
  }

  // Field configuration with Dutch labels
  const fields = [
    { key: 'knvb-id', label: 'KNVB ID', value: knvbId, type: 'text' },
    { key: 'lid-sinds', label: 'Lid sinds', value: lidSinds, type: 'date' },
    { key: 'lid-tot', label: 'Lid tot', value: lidTot, type: 'date' },
    { key: 'leeftijdsgroep', label: 'Leeftijdsgroep', value: leeftijdsgroep, type: 'text' },
    { key: 'type-lid', label: 'Type lid', value: typeLid, type: 'text' },
    { key: 'datum-foto', label: 'Datum foto', value: datumFoto, type: 'date' },
    {
      key: 'isparent',
      label: 'Ouder van lid',
      value: isParent,
      type: 'boolean',
      // Only show isparent if other fields are present
      show: hasData
    },
  ];

  // Format field value based on type
  function formatValue(field) {
    if (!field.value && field.type !== 'boolean') {
      return null;
    }

    switch (field.type) {
      case 'date':
        return format(new Date(field.value), 'd MMM yyyy');
      case 'boolean':
        return (field.value === true || field.value === '1') ? 'Ja' : 'Nee';
      case 'text':
      default:
        return field.value;
    }
  }

  return (
    <div className="card p-6 mb-4">
      {/* Header */}
      <div className="flex items-center gap-2 mb-4">
        <Database className="w-5 h-5 text-bright-cobalt" />
        <h2 className="font-semibold text-brand-gradient">Sportlink</h2>
      </div>

      {/* Field list */}
      <dl className="space-y-2">
        {fields.map((field) => {
          // Skip fields without values (except boolean which we always render when visible)
          if (!field.value && field.type !== 'boolean') {
            return null;
          }

          // Skip isparent if show is false (shouldn't happen but defensive)
          if (field.key === 'isparent' && field.show === false) {
            return null;
          }

          const formattedValue = formatValue(field);

          // Don't render if formatting resulted in null
          if (formattedValue === null) {
            return null;
          }

          return (
            <div key={field.key} className="flex justify-between items-start gap-4">
              <dt className="text-sm text-gray-500 dark:text-gray-400">{field.label}</dt>
              <dd className="text-sm text-gray-900 dark:text-gray-100 text-right">
                {formattedValue}
              </dd>
            </div>
          );
        })}
      </dl>
    </div>
  );
}
