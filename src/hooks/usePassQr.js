import { useEffect, useState } from 'react';
import QRCode from 'qrcode';

const QR_OPTIONS = {
  width: 1024, margin: 2, errorCorrectionLevel: 'M',
  color: { dark: '#111827', light: '#ffffff' },
};

// Shared by the browser pass and the packaged mobile pass. Never persist the QR token.
export function usePassQr(token) {
  const [result, setResult] = useState({ token: '', url: '', error: false });
  useEffect(() => {
    let active = true;
    if (token) {
      QRCode.toDataURL(token, QR_OPTIONS).then(
        (url) => { if (active) setResult({ token, url, error: false }); },
        () => { if (active) setResult({ token, url: '', error: true }); },
      );
    }
    return () => { active = false; };
  }, [token]);
  return result.token === token ? result : { url: '', error: false };
}
