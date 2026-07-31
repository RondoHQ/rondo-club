import { useEffect, useState } from 'react';
import QRCode from 'qrcode';
import { X, Printer, ExternalLink } from 'lucide-react';

/**
 * QR sticker preview + print dialog for a taakuitleg entry.
 *
 * The QR encodes the public /uitleg/{slug} URL, which volunteers scan off a
 * printed sticker (e.g. on a frying pan) to read the instructions without
 * logging in. Printing happens through a hidden iframe so the sticker layout is
 * isolated from the SPA's styles and survives popup blockers.
 */
export default function QrPrintModal({ url, title, onClose }) {
  const [dataUrl, setDataUrl] = useState('');
  const [error, setError] = useState(false);

  useEffect(() => {
    let cancelled = false;
    QRCode.toDataURL(url, { width: 512, margin: 1, errorCorrectionLevel: 'M' })
      .then((src) => {
        if (!cancelled) setDataUrl(src);
      })
      .catch(() => {
        if (!cancelled) setError(true);
      });
    return () => {
      cancelled = true;
    };
  }, [url]);

  const handlePrint = () => {
    if (!dataUrl) return;

    const iframe = document.createElement('iframe');
    iframe.style.position = 'fixed';
    iframe.style.right = '0';
    iframe.style.bottom = '0';
    iframe.style.width = '0';
    iframe.style.height = '0';
    iframe.style.border = '0';
    document.body.appendChild(iframe);

    const esc = (s) =>
      String(s).replace(/[&<>"']/g, (c) => (
        { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[c]
      ));

    const doc = iframe.contentWindow.document;
    doc.open();
    doc.write(`<!DOCTYPE html><html lang="nl"><head><meta charset="utf-8"><title>${esc(title)}</title>
      <style>
        @page { margin: 12mm; }
        * { box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; margin: 0; }
        .sticker { width: 80mm; margin: 0 auto; text-align: center; border: 2px solid #0f172a; border-radius: 8px; padding: 8mm 6mm; }
        .sticker h1 { font-size: 16pt; margin: 0 0 4mm; color: #0f172a; }
        .sticker img { width: 56mm; height: 56mm; }
        .sticker .scan { font-size: 11pt; font-weight: 600; margin: 4mm 0 1mm; color: #0f172a; }
        .sticker .url { font-size: 8pt; color: #64748b; word-break: break-all; }
      </style></head><body>
      <div class="sticker">
        <h1>${esc(title)}</h1>
        <img src="${dataUrl}" alt="QR">
        <div class="scan">Scan voor uitleg</div>
        <div class="url">${esc(url)}</div>
      </div>
      </body></html>`);
    doc.close();

    const cleanup = () => setTimeout(() => iframe.remove(), 500);
    iframe.contentWindow.focus();
    iframe.contentWindow.onafterprint = cleanup;
    iframe.contentWindow.print();
  };

  return (
    <div
      className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
      onClick={onClose}
    >
      <div
        className="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-sm w-full p-6"
        onClick={(e) => e.stopPropagation()}
      >
        <div className="flex items-start justify-between mb-4">
          <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100">QR-code printen</h2>
          <button onClick={onClose} className="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
            <X className="w-5 h-5" />
          </button>
        </div>

        <p className="text-sm text-gray-600 dark:text-gray-400 mb-4">{title}</p>

        <div className="flex justify-center mb-4">
          {error ? (
            <p className="text-sm text-red-600">QR-code kon niet worden gegenereerd.</p>
          ) : dataUrl ? (
            <img src={dataUrl} alt="QR-code" className="w-48 h-48" />
          ) : (
            <div className="w-48 h-48 bg-gray-100 dark:bg-gray-700 animate-pulse rounded" />
          )}
        </div>

        <a
          href={url}
          target="_blank"
          rel="noopener noreferrer"
          className="flex items-center justify-center gap-1 text-xs text-gray-500 hover:text-electric-cyan break-all mb-4"
        >
          <ExternalLink className="w-3 h-3 flex-shrink-0" />
          {url}
        </a>

        <button
          onClick={handlePrint}
          disabled={!dataUrl}
          className="btn-primary w-full inline-flex items-center justify-center gap-2"
        >
          <Printer className="w-4 h-4" />
          Print sticker
        </button>
      </div>
    </div>
  );
}
