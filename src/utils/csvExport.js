/**
 * Escape a CSV cell value.
 * Wraps in double-quotes if the value contains semicolons, double-quotes, or newlines.
 * Escapes existing double-quotes by doubling them (RFC 4180).
 *
 * @param {string|number|null|undefined} value
 * @returns {string}
 */
export function escapeCell(value) {
  if (value === null || value === undefined) return '';
  const str = String(value);
  if (str.includes(';') || str.includes('"') || str.includes('\n') || str.includes('\r')) {
    return '"' + str.replace(/"/g, '""') + '"';
  }
  return str;
}

/**
 * Convert a 2D array of values into a CSV string using semicolon delimiter.
 * Uses semicolons for Dutch Excel compatibility.
 *
 * @param {Array<Array<string|number|null>>} rows - Array of rows, each row is an array of cells
 * @returns {string} CSV string
 */
export function buildCsv(rows) {
  return rows.map(row => row.map(escapeCell).join(';')).join('\r\n');
}

/**
 * Trigger a browser file download for a CSV string.
 * Uses BOM (Byte Order Mark) prefix for correct UTF-8 detection in Excel.
 *
 * @param {string} csvString - The CSV content
 * @param {string} filename - The filename for the download (include .csv extension)
 */
export function downloadCsv(csvString, filename) {
  // BOM ensures Excel opens UTF-8 correctly without asking about encoding
  const BOM = '\uFEFF';
  const blob = new Blob([BOM + csvString], { type: 'text/csv;charset=utf-8;' });
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.href = url;
  link.download = filename;
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
  URL.revokeObjectURL(url);
}
