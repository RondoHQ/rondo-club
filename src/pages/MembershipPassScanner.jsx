import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { Link } from 'react-router-dom';
import { Camera, CameraOff, CheckCircle2, XCircle } from 'lucide-react';
import { prmApi } from '@/api/client';
import jsQR from 'jsqr';

export default function MembershipPassScanner() {
  const videoRef = useRef(null);
  const streamRef = useRef(null);
  const detectorRef = useRef(null);
  const canvasRef = useRef(null);
  const rafRef = useRef(0);
  const isDetectingRef = useRef(false);

  const [isCameraActive, setIsCameraActive] = useState(false);
  const [cameraError, setCameraError] = useState('');
  const [scanError, setScanError] = useState('');
  const [result, setResult] = useState(null);
  const [supportedFormats, setSupportedFormats] = useState([]);

  const hasBarcodeDetector = typeof window !== 'undefined' && 'BarcodeDetector' in window;

  const canScanQr = useMemo(() => {
    if (hasBarcodeDetector && supportedFormats.includes('qr_code')) {
      return true;
    }
    // jsQR fallback support.
    return typeof jsQR === 'function';
  }, [hasBarcodeDetector, supportedFormats]);

  const stopCamera = useCallback(() => {
    if (rafRef.current) {
      cancelAnimationFrame(rafRef.current);
      rafRef.current = 0;
    }

    if (streamRef.current) {
      streamRef.current.getTracks().forEach((track) => track.stop());
      streamRef.current = null;
    }

    if (videoRef.current) {
      videoRef.current.srcObject = null;
    }

    setIsCameraActive(false);
  }, []);

  const verifyToken = useCallback(async (rawToken) => {
    const cleanedToken = String(rawToken || '').trim();
    if (!cleanedToken) {
      return;
    }

    setScanError('');
    try {
      const response = await prmApi.verifyMembershipPassQrToken(cleanedToken);
      setResult(response.data);
    } catch (error) {
      setResult(null);
      setScanError(error?.response?.data?.message || 'Verificatie van QR-token mislukt.');
    }
  }, []);

  const detectFrame = useCallback(async () => {
    if (!videoRef.current) {
      return;
    }

    if (videoRef.current.readyState >= 2 && !isDetectingRef.current) {
      isDetectingRef.current = true;
      try {
        let value = '';

        if (detectorRef.current) {
          const barcodes = await detectorRef.current.detect(videoRef.current);
          if (barcodes?.length) {
            value = String(barcodes[0]?.rawValue || '').trim();
          }
        } else if (canvasRef.current) {
          const canvas = canvasRef.current;
          const video = videoRef.current;
          const width = video.videoWidth || 0;
          const height = video.videoHeight || 0;

          if (width > 0 && height > 0) {
            canvas.width = width;
            canvas.height = height;
            const context = canvas.getContext('2d', { willReadFrequently: true });
            if (context) {
              context.drawImage(video, 0, 0, width, height);
              const imageData = context.getImageData(0, 0, width, height);
              const decoded = jsQR(imageData.data, width, height, { inversionAttempts: 'dontInvert' });
              value = String(decoded?.data || '').trim();
            }
          }
        }

        if (value) {
          stopCamera();
          await verifyToken(value);
          return;
        }
      } catch {
        // Continue loop silently; camera stream stays active.
      } finally {
        isDetectingRef.current = false;
      }
    }

    rafRef.current = requestAnimationFrame(detectFrame);
  }, [stopCamera, verifyToken]);

  const startCamera = useCallback(async () => {
    setCameraError('');
    setScanError('');

    if (!canScanQr) {
      setCameraError('QR-camera is niet beschikbaar in deze browser.');
      return;
    }

    try {
      const stream = await navigator.mediaDevices.getUserMedia({
        video: {
          facingMode: { ideal: 'environment' },
          width: { ideal: 1280 },
          height: { ideal: 720 },
        },
        audio: false,
      });

      streamRef.current = stream;
      if (videoRef.current) {
        videoRef.current.srcObject = stream;
        await videoRef.current.play();
      }

      setIsCameraActive(true);
      rafRef.current = requestAnimationFrame(detectFrame);
    } catch (error) {
      setCameraError(error?.message || 'Camera kon niet gestart worden.');
      stopCamera();
    }
  }, [canScanQr, detectFrame, stopCamera]);

  useEffect(() => {
    async function loadFormats() {
      if (!hasBarcodeDetector) {
        return;
      }
      try {
        const formats = await window.BarcodeDetector.getSupportedFormats();
        setSupportedFormats(Array.isArray(formats) ? formats : []);
        if (Array.isArray(formats) && formats.includes('qr_code')) {
          detectorRef.current = new window.BarcodeDetector({ formats: ['qr_code'] });
        }
      } catch {
        setSupportedFormats([]);
      }
    }
    loadFormats();
  }, [hasBarcodeDetector]);

  useEffect(() => stopCamera, [stopCamera]);

  const isActiveMembership = result?.membership?.status === 'active';
  const resultPhoto = result?.person?.photo_thumbnail || result?.person?.thumbnail || '';
  const resultKnvbId = result?.person?.knvb_id || result?.person?.['knvb-id'] || '';

  return (
    <div className="space-y-6">
      <div className="card p-6 space-y-4">
        <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100">Camera</h2>
        <div className="rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700 bg-black/90">
          <video ref={videoRef} className="w-full max-h-80 object-cover" playsInline muted />
          <canvas ref={canvasRef} className="hidden" aria-hidden="true" />
        </div>

        <div className="flex flex-wrap gap-2">
          {!isCameraActive ? (
            <button type="button" className="btn-primary inline-flex items-center gap-2" onClick={startCamera}>
              <Camera className="w-4 h-4" />
              Start camera
            </button>
          ) : (
            <button type="button" className="btn-secondary inline-flex items-center gap-2" onClick={stopCamera}>
              <CameraOff className="w-4 h-4" />
              Stop camera
            </button>
          )}
        </div>

        {!canScanQr && (
          <p className="text-sm text-amber-700 dark:text-amber-400">
            Deze browser ondersteunt geen QR-detectie via camera.
          </p>
        )}

        {cameraError && <p className="text-sm text-red-600 dark:text-red-400">{cameraError}</p>}

        {scanError && (
          <div className="rounded-lg border border-red-200 bg-red-50 dark:border-red-900/40 dark:bg-red-900/20 p-4 text-sm text-red-700 dark:text-red-300">
            {scanError}
          </div>
        )}
      </div>

      {result && (
        <div className="card p-6 space-y-4">
          <div className={`flex items-center gap-2 font-semibold ${isActiveMembership ? 'text-green-700 dark:text-green-400' : 'text-red-700 dark:text-red-400'}`}>
            {isActiveMembership ? <CheckCircle2 className="w-5 h-5" /> : <XCircle className="w-5 h-5" />}
            {isActiveMembership ? 'Geldige ledenpas' : 'Ongeldige ledenpas'}
          </div>
          {!isActiveMembership ? (
            <div className="text-sm font-medium text-red-700 dark:text-red-400">
              Geen lid meer
            </div>
          ) : null}

          <div className="flex items-center gap-4">
            {resultPhoto ? (
              <img
                src={resultPhoto}
                alt={result.person?.name || 'Lid'}
                className="h-16 w-16 rounded-full object-cover border border-gray-200 dark:border-gray-700"
              />
            ) : (
              <div className="h-16 w-16 rounded-full bg-gray-200 dark:bg-gray-700 border border-gray-200 dark:border-gray-700" aria-hidden="true" />
            )}
            <div>
              <div className="text-lg font-semibold text-gray-900 dark:text-gray-100">{result.person?.name || '-'}</div>
              <div className="text-sm text-gray-500 dark:text-gray-400">KNVB ID: {resultKnvbId || '-'}</div>
            </div>
          </div>

          {result.person?.id ? (
            <Link to={`/people/${result.person.id}`} className="btn-secondary inline-flex items-center gap-2">
              Open lidprofiel
            </Link>
          ) : null}
        </div>
      )}
    </div>
  );
}
