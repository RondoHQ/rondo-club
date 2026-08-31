import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { Link } from 'react-router-dom';
import {
  AlertTriangle,
  CalendarClock,
  Camera,
  CameraOff,
  CheckCircle2,
  Users,
  XCircle,
} from 'lucide-react';
import jsQR from 'jsqr';
import {
  useAccessEventMatches,
  useAccessEventStats,
  useScanAccessEvent,
  useSelectAccessEvent,
} from '@/hooks/useAccessEvents';
import {
  ACCESS_EVENT_SELECTION_KEY,
  chooseAccessMatch,
  createStoredAccessMatch,
  readStoredAccessMatch,
} from '@/utils/accessEvents';

function getInvalidPassMessage(reason) {
  if (reason === 'revoked') return 'Deze pas is ingetrokken.';
  if (reason === 'no_pass_right') return 'Geen geldig pasrecht.';
  if (reason === 'unclaimed') return 'Deze gastpas is nog niet geregistreerd.';
  if (reason === 'host_ineligible') return 'De speler kan momenteel geen gastpassen gebruiken.';
  if (reason === 'wrong_match') return 'Deze gastpas is alleen geldig bij thuiswedstrijden van AWC 1.';
  return 'Geen lid meer.';
}

function formatMatchDate(value) {
  if (!value) return '';
  return new Intl.DateTimeFormat('nl-NL', {
    weekday: 'short',
    day: 'numeric',
    month: 'short',
    hour: '2-digit',
    minute: '2-digit',
  }).format(new Date(value));
}

function formatScanTime(value) {
  if (!value) return '';
  return new Intl.DateTimeFormat('nl-NL', {
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
  }).format(new Date(value));
}

function MatchSelector({
  feed,
  isLoading,
  error,
  selectedEvent,
  isSelecting,
  selectionError,
  manualSelectionOpen,
  onSelect,
  onChange,
}) {
  const matches = (feed?.matches || []).filter((match) => match.is_selectable);
  const activeCount = matches.filter((match) => match.is_active).length;

  if (selectedEvent && !manualSelectionOpen) {
    return (
      <div className="card p-5 space-y-3">
        <div className="flex items-start justify-between gap-4">
          <div className="flex min-w-0 items-start gap-3">
            <CalendarClock className="mt-0.5 h-5 w-5 shrink-0 text-electric-cyan" />
            <div className="min-w-0">
              <div className="text-sm text-gray-500 dark:text-gray-400">Scannen voor</div>
              <h2 className="font-semibold text-gray-900 dark:text-gray-100">
                {selectedEvent.home_team} – {selectedEvent.away_team}
              </h2>
              <div className="text-sm text-gray-600 dark:text-gray-400">
                {formatMatchDate(selectedEvent.starts_at)}
                {selectedEvent.pitch ? ` · ${selectedEvent.pitch}` : ''}
              </div>
            </div>
          </div>
          <button type="button" className="btn-tertiary shrink-0" onClick={onChange}>
            Wijzigen
          </button>
        </div>
      </div>
    );
  }

  return (
    <div className="card p-5 space-y-3">
      <div className="flex items-center gap-2">
        <CalendarClock className="h-5 w-5 text-electric-cyan" />
        <h2 className="font-semibold text-gray-900 dark:text-gray-100">Wedstrijd</h2>
      </div>

      {isLoading || isSelecting ? (
        <p className="text-sm text-gray-600 dark:text-gray-400">
          {isSelecting ? 'Wedstrijd selecteren…' : 'Sportlink-programma laden…'}
        </p>
      ) : null}

      {!isLoading && !isSelecting && matches.length > 0 ? (
        <>
          {activeCount > 1 ? (
            <p className="text-sm text-amber-700 dark:text-amber-400">
              Er zijn meerdere wedstrijden actief. Kies één keer voor welke wedstrijd je scant.
            </p>
          ) : (
            <p className="text-sm text-gray-600 dark:text-gray-400">
              Kies een wedstrijd wanneer automatische selectie niet mogelijk is.
            </p>
          )}
          <select
            className="input"
            defaultValue=""
            onChange={(event) => event.target.value && onSelect(event.target.value)}
          >
            <option value="" disabled>Wedstrijd kiezen…</option>
            {matches.map((match) => (
              <option key={match.id} value={match.id}>
                {formatMatchDate(match.starts_at)} · {match.home_team} – {match.away_team}
                {match.pitch ? ` · ${match.pitch}` : ''}
              </option>
            ))}
          </select>
        </>
      ) : null}

      {!isLoading && !isSelecting && matches.length === 0 ? (
        <p className="text-sm text-amber-700 dark:text-amber-400">
          Geen thuiswedstrijd beschikbaar. Je kunt wel passen controleren; scans worden dan niet
          in wedstrijdstatistieken opgenomen.
        </p>
      ) : null}

      {feed?.source?.stale ? (
        <p className="text-xs text-amber-700 dark:text-amber-400">
          Sportlink is tijdelijk niet actueel; de laatst bekende wedstrijden worden getoond.
        </p>
      ) : null}

      {error || selectionError ? (
        <p className="text-sm text-red-600 dark:text-red-400">
          {selectionError?.response?.data?.message
            || error?.response?.data?.message
            || 'Wedstrijden konden niet worden geladen.'}
        </p>
      ) : null}
    </div>
  );
}

function AccessStats({ stats, isLoading }) {
  return (
    <div className="card p-5 space-y-4">
      <div className="flex items-center gap-2">
        <Users className="h-5 w-5 text-electric-cyan" />
        <h2 className="font-semibold text-gray-900 dark:text-gray-100">Binnengekomen</h2>
      </div>
      {isLoading && !stats ? (
        <p className="text-sm text-gray-500 dark:text-gray-400">Telling laden…</p>
      ) : (
        <>
          <div className="text-4xl font-bold text-gray-900 dark:text-gray-100">{stats?.total ?? 0}</div>
          <div className="grid grid-cols-2 gap-3 sm:grid-cols-5">
            {(stats?.breakdown || []).map((item) => (
              <div key={item.type} className="rounded-lg bg-gray-50 p-3 dark:bg-gray-800/70">
                <div className="text-2xl font-semibold text-gray-900 dark:text-gray-100">{item.count}</div>
                <div className="text-xs text-gray-500 dark:text-gray-400">{item.label}</div>
              </div>
            ))}
          </div>
        </>
      )}
    </div>
  );
}

export default function MembershipPassScanner() {
  const videoRef = useRef(null);
  const streamRef = useRef(null);
  const detectorRef = useRef(null);
  const canvasRef = useRef(null);
  const rafRef = useRef(0);
  const isDetectingRef = useRef(false);
  const storedSelectionRef = useRef(
    typeof window !== 'undefined' ? readStoredAccessMatch(window.localStorage) : null
  );
  const selectionAttemptRef = useRef('');

  const [isCameraActive, setIsCameraActive] = useState(false);
  const [cameraError, setCameraError] = useState('');
  const [scanError, setScanError] = useState('');
  const [result, setResult] = useState(null);
  const [supportedFormats, setSupportedFormats] = useState([]);
  const [selectedEvent, setSelectedEvent] = useState(null);
  const [manualSelectionOpen, setManualSelectionOpen] = useState(false);

  const matchesQuery = useAccessEventMatches();
  const {
    mutate: selectEvent,
    isPending: isSelecting,
    error: selectionError,
    reset: resetSelection,
  } = useSelectAccessEvent();
  const statsQuery = useAccessEventStats(selectedEvent?.id);
  const { mutateAsync: scanEvent, isPending: isScanning } = useScanAccessEvent();

  const hasBarcodeDetector = typeof window !== 'undefined' && 'BarcodeDetector' in window;
  const canScanQr = useMemo(() => {
    if (hasBarcodeDetector && supportedFormats.includes('qr_code')) return true;
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
    if (videoRef.current) videoRef.current.srcObject = null;
    setIsCameraActive(false);
  }, []);

  const chooseMatch = useCallback((sourceId) => {
    const match = (matchesQuery.data?.matches || []).find((candidate) => candidate.id === sourceId);
    if (!match) return;

    selectionAttemptRef.current = sourceId;
    resetSelection();
    selectEvent(sourceId, {
      onSuccess: (data) => {
        setSelectedEvent(data.event);
        setManualSelectionOpen(false);
        storedSelectionRef.current = createStoredAccessMatch(match, matchesQuery.data?.local_date);
        try {
          window.localStorage.setItem(
            ACCESS_EVENT_SELECTION_KEY,
            JSON.stringify(storedSelectionRef.current)
          );
        } catch {
          // Device storage is optional; the current scanner session still works.
        }
      },
    });
  }, [matchesQuery.data, resetSelection, selectEvent]);

  useEffect(() => {
    if (!matchesQuery.data || selectedEvent || manualSelectionOpen || isSelecting) return;
    const match = chooseAccessMatch(
      matchesQuery.data.matches,
      storedSelectionRef.current,
      matchesQuery.data.local_date
    );
    if (!match || selectionAttemptRef.current === match.id) return;
    chooseMatch(match.id);
  }, [chooseMatch, isSelecting, manualSelectionOpen, matchesQuery.data, selectedEvent]);

  const changeMatch = useCallback(() => {
    stopCamera();
    setResult(null);
    setSelectedEvent(null);
    setManualSelectionOpen(true);
    selectionAttemptRef.current = '';
    storedSelectionRef.current = null;
    try {
      window.localStorage.removeItem(ACCESS_EVENT_SELECTION_KEY);
    } catch {
      // Device storage is optional.
    }
  }, [stopCamera]);

  const verifyToken = useCallback(async (rawToken) => {
    const cleanedToken = String(rawToken || '').trim();
    if (!cleanedToken) return;
    setScanError('');
    try {
      const data = await scanEvent({ eventId: selectedEvent?.id, token: cleanedToken });
      setResult(data);
    } catch (error) {
      setResult(null);
      setScanError(
        error?.response?.data?.message
        || 'Geldigheid kan niet worden gecontroleerd. Controleer je internetverbinding.'
      );
    }
  }, [scanEvent, selectedEvent?.id]);

  const detectFrame = useCallback(async () => {
    if (!videoRef.current) return;
    if (videoRef.current.readyState >= 2 && !isDetectingRef.current) {
      isDetectingRef.current = true;
      try {
        let value = '';
        if (detectorRef.current) {
          const barcodes = await detectorRef.current.detect(videoRef.current);
          if (barcodes?.length) value = String(barcodes[0]?.rawValue || '').trim();
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
        // Continue the detection loop while the camera stream is active.
      } finally {
        isDetectingRef.current = false;
      }
    }
    rafRef.current = requestAnimationFrame(detectFrame);
  }, [stopCamera, verifyToken]);

  const startCamera = useCallback(async () => {
    setCameraError('');
    setScanError('');
    setResult(null);
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
      if (!hasBarcodeDetector) return;
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

  const isActiveMembership = result?.valid === true;
  const isDuplicate = isActiveMembership && result?.admission?.duplicate === true;
  const isGuest = result?.pass_type === 'guest';
  const resultPhoto = result?.person?.photo_thumbnail || result?.person?.thumbnail || '';
  const resultKnvbId = result?.person?.knvb_id || result?.person?.['knvb_id'] || '';
  const isSponsor = result?.person?.is_sponsor === true;
  const resultCompanyName = result?.person?.company_name || '';

  return (
    <div className="space-y-6">
      <MatchSelector
        feed={matchesQuery.data}
        isLoading={matchesQuery.isLoading}
        error={matchesQuery.error}
        selectedEvent={selectedEvent}
        isSelecting={isSelecting}
        selectionError={selectionError}
        manualSelectionOpen={manualSelectionOpen}
        onSelect={chooseMatch}
        onChange={changeMatch}
      />

      <div className="card p-6 space-y-4">
        <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100">Camera</h2>
        <div className="rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700 bg-black/90">
          <video ref={videoRef} className="w-full max-h-80 object-cover" playsInline muted />
          <canvas ref={canvasRef} className="hidden" aria-hidden="true" />
        </div>
        <div className="flex flex-wrap gap-2">
          {!isCameraActive ? (
            <button
              type="button"
              className="btn-primary gap-2"
              onClick={startCamera}
              disabled={isScanning}
            >
              <Camera className="w-4 h-4" />
              {isScanning ? 'Pas controleren…' : 'Start camera'}
            </button>
          ) : (
            <button type="button" className="btn-secondary gap-2" onClick={stopCamera}>
              <CameraOff className="w-4 h-4" />
              Stop camera
            </button>
          )}
        </div>
        {!selectedEvent ? (
          <p className="text-sm text-gray-600 dark:text-gray-400">
            Zonder wedstrijd wordt de pas wel gecontroleerd, maar de scan niet meegeteld.
          </p>
        ) : null}
        {!canScanQr ? (
          <p className="text-sm text-amber-700 dark:text-amber-400">
            Deze browser ondersteunt geen QR-detectie via camera.
          </p>
        ) : null}
        {cameraError ? <p className="text-sm text-red-600 dark:text-red-400">{cameraError}</p> : null}
        {scanError ? (
          <div className="rounded-lg border border-red-200 bg-red-50 dark:border-red-900/40 dark:bg-red-900/20 p-4 text-sm text-red-700 dark:text-red-300">
            {scanError}
          </div>
        ) : null}
      </div>

      {result ? (
        <div className="card p-6 space-y-4">
          <div className={`flex items-center gap-2 font-semibold ${
            isDuplicate
              ? 'text-amber-700 dark:text-amber-400'
              : isActiveMembership
                ? 'text-green-700 dark:text-green-400'
                : 'text-red-700 dark:text-red-400'
          }`}>
            {isDuplicate
              ? <AlertTriangle className="w-5 h-5" />
              : isActiveMembership
                ? <CheckCircle2 className="w-5 h-5" />
                : <XCircle className="w-5 h-5" />}
            {isDuplicate
              ? 'Pas al gescand'
              : isActiveMembership
                ? selectedEvent
                  ? 'Toegang geregistreerd'
                  : 'Geldige ledenpas'
                : isGuest ? 'Ongeldige gastpas' : 'Ongeldige ledenpas'}
          </div>

          {isDuplicate ? (
            <div className="text-sm font-medium text-amber-700 dark:text-amber-400">
              {isGuest ? 'Deze gastpas' : 'Deze persoon'} telde al mee
              {result.admission?.scanned_at ? ` om ${formatScanTime(result.admission.scanned_at)}` : ''}.
            </div>
          ) : null}
          {!isActiveMembership ? (
            <div className="text-sm font-medium text-red-700 dark:text-red-400">
              {getInvalidPassMessage(result.reason)}
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
              <div className="text-lg font-semibold text-gray-900 dark:text-gray-100">
                {isGuest ? result.guest?.name || '-' : result.person?.name || '-'}
              </div>
              <div className="text-sm text-gray-500 dark:text-gray-400">
                {isGuest
                  ? `Gast van ${result.guest?.host_name || '-'}`
                  : isSponsor ? `Bedrijf: ${resultCompanyName || '-'}` : `KNVB ID: ${resultKnvbId || '-'}`}
              </div>
            </div>
          </div>

          {result.person?.id ? (
            <Link to={`/people/${result.person.id}`} className="btn-tertiary gap-2">
              Open lidprofiel
            </Link>
          ) : null}
          {isGuest && result.guest?.host_person_id ? (
            <Link to={`/people/${result.guest.host_person_id}`} className="btn-tertiary gap-2">
              Open spelersprofiel
            </Link>
          ) : null}
        </div>
      ) : null}

      {selectedEvent ? <AccessStats stats={statsQuery.data} isLoading={statsQuery.isLoading} /> : null}
    </div>
  );
}
