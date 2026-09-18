export const SHIFT_COLUMNS = ['shift_status', 'shift_planned', 'shift_completed', 'shift_required'];

export const SHIFT_STATUS_LABELS = {
  not_started: 'Nog niets ingepland of afgerond',
  insufficient: 'Nog onvoldoende ingepland',
  planned: 'Voldoende ingepland',
  completed: 'Voldaan',
  exempt: 'Vrijgesteld',
};

export function shiftColumnValue(progress, columnId) {
  if (progress === undefined) return '…';
  if (!progress) return columnId === 'shift_status' ? 'Geen verplichting' : '-';
  if (columnId === 'shift_status') return SHIFT_STATUS_LABELS[progress.status] || '-';
  if (progress.status === 'exempt') return '-';
  return progress[columnId.replace('shift_', '')] ?? '-';
}
