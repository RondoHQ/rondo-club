import { useCallback, useEffect, useRef, useState } from 'react';
import { CircleAlert, MonitorUp, Square } from 'lucide-react';
import { useRouteTitle } from '@/hooks/useDocumentTitle';
import {
  emptySignal,
  getPresentationSignal,
  joinPresentationSession,
  PRESENTATION_POLL_INTERVAL_MS,
  sendPresentationSignal,
} from './presentationApi';

function candidatePayload(candidate) {
  return typeof candidate.toJSON === 'function' ? candidate.toJSON() : {
    candidate: candidate.candidate,
    sdpMid: candidate.sdpMid,
    sdpMLineIndex: candidate.sdpMLineIndex,
    usernameFragment: candidate.usernameFragment,
  };
}

export default function PresentationSender() {
  useRouteTitle('Presenteren');
  const sessionRef = useRef(null);
  const peerRef = useRef(null);
  const streamRef = useRef(null);
  const pollTimerRef = useRef(null);
  const remoteCandidateCountRef = useRef(0);
  const localSignalRef = useRef(emptySignal());
  const sendChainRef = useRef(Promise.resolve());
  const [code, setCode] = useState('');
  const [displayName, setDisplayName] = useState('');
  const [status, setStatus] = useState('code');
  const [error, setError] = useState('');
  const [entitlementEndsAt, setEntitlementEndsAt] = useState('');
  const [minutesRemaining, setMinutesRemaining] = useState(null);

  const clearMedia = useCallback(() => {
    window.clearTimeout(pollTimerRef.current);
    pollTimerRef.current = null;
    if (peerRef.current) {
      peerRef.current.onicecandidate = null;
      peerRef.current.onconnectionstatechange = null;
      peerRef.current.close();
      peerRef.current = null;
    }
    streamRef.current?.getTracks().forEach((track) => track.stop());
    streamRef.current = null;
    remoteCandidateCountRef.current = 0;
    localSignalRef.current = emptySignal();
  }, []);

  const publishSignal = useCallback(() => {
    const session = sessionRef.current;
    if (!session) return;
    const snapshot = {
      ...localSignalRef.current,
      candidates: [...localSignalRef.current.candidates],
    };
    sendChainRef.current = sendChainRef.current
      .catch(() => {})
      .then(() => sendPresentationSignal(session.session_id, session.token, snapshot))
      .catch((sendError) => setError(sendError.message));
  }, []);

  const stopSharing = useCallback(async (notifyReceiver = true) => {
    const session = sessionRef.current;
    if (notifyReceiver && session) {
      try {
        await sendPresentationSignal(session.session_id, session.token, {
          ...localSignalRef.current,
          candidates: [...localSignalRef.current.candidates],
          hangup: true,
        });
      } catch {
        // The receiver also detects a closed peer connection.
      }
    }
    clearMedia();
    sessionRef.current = null;
    setDisplayName('');
    setEntitlementEndsAt('');
    setStatus('code');
    setError('');
  }, [clearMedia]);

  useEffect(() => () => clearMedia(), [clearMedia]);

  const connectCode = async (event) => {
    event.preventDefault();
    setError('');
    setStatus('joining');
    try {
      const session = await joinPresentationSession(code);
      sessionRef.current = session;
      setDisplayName(session.display_name);
      setEntitlementEndsAt(session.entitlement_ends_at || '');
      setStatus('ready');
    } catch (joinError) {
      setStatus('code');
      setError(joinError.message);
    }
  };

  useEffect(() => {
    if (!entitlementEndsAt) {
      setMinutesRemaining(null);
      return undefined;
    }
    const updateRemaining = () => setMinutesRemaining(Math.max(0, Math.ceil((new Date(entitlementEndsAt).getTime() - Date.now()) / 60000)));
    updateRemaining();
    const timer = window.setInterval(updateRemaining, 30000);
    return () => window.clearInterval(timer);
  }, [entitlementEndsAt]);

  const pollReceiver = useCallback(async () => {
    const session = sessionRef.current;
    const peer = peerRef.current;
    if (!session || !peer) return;
    try {
      const response = await getPresentationSignal(session.session_id, session.token);
      if (response.entitlement_ends_at) setEntitlementEndsAt(response.entitlement_ends_at);
      const signal = response.signal;
      if (signal?.hangup) {
        await stopSharing(false);
        return;
      }
      if (signal?.description && !peer.remoteDescription) {
        await peer.setRemoteDescription(signal.description);
      }
      if (peer.remoteDescription) {
        const candidates = signal?.candidates || [];
        while (remoteCandidateCountRef.current < candidates.length) {
          await peer.addIceCandidate(candidates[remoteCandidateCountRef.current]);
          remoteCandidateCountRef.current += 1;
        }
      }
    } catch (pollError) {
      if (pollError.status === 401 || pollError.status === 410) {
        await stopSharing(false);
        setError('De presentatiesessie is verlopen. Voer de nieuwe schermcode in.');
        return;
      }
    }
    if (sessionRef.current && peerRef.current) {
      pollTimerRef.current = window.setTimeout(pollReceiver, PRESENTATION_POLL_INTERVAL_MS);
    }
  }, [stopSharing]);

  const startSharing = async () => {
    if (!sessionRef.current || !navigator.mediaDevices?.getDisplayMedia) {
      setError('Gebruik een actuele versie van Chrome of Edge om je scherm te delen.');
      return;
    }
    setError('');
    try {
      const stream = await navigator.mediaDevices.getDisplayMedia({
        video: { frameRate: { ideal: 30, max: 30 } },
        audio: true,
      });
      streamRef.current = stream;
      const peer = new RTCPeerConnection();
      peerRef.current = peer;
      stream.getTracks().forEach((track) => {
        peer.addTrack(track, stream);
      });
      stream.getVideoTracks()[0]?.addEventListener('ended', () => stopSharing(), { once: true });
      peer.onicecandidate = (event) => {
        if (!event.candidate) return;
        localSignalRef.current.candidates.push(candidatePayload(event.candidate));
        publishSignal();
      };
      peer.onconnectionstatechange = () => {
        if (peer.connectionState === 'connected') setStatus('presenting');
        if (peer.connectionState === 'failed') setError('De verbinding met het scherm is mislukt.');
      };

      const offer = await peer.createOffer();
      await peer.setLocalDescription(offer);
      localSignalRef.current.description = peer.localDescription.toJSON();
      setStatus('connecting');
      publishSignal();
      pollReceiver();
    } catch (captureError) {
      if (captureError.name !== 'NotAllowedError') setError(captureError.message);
      setStatus('ready');
    }
  };

  return (
    <div className="mx-auto max-w-2xl space-y-6">
      <header>
        <h1 className="text-2xl font-semibold text-gray-900 dark:text-gray-100">Presenteren op een scherm</h1>
        <p className="mt-1 text-gray-600 dark:text-gray-400">Deel zonder installatie een tabblad, venster of volledig scherm.</p>
      </header>

      {error && (
        <div className="flex items-start gap-3 rounded-lg border border-red-200 bg-red-50 p-4 text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-200">
          <CircleAlert className="mt-0.5 h-5 w-5 shrink-0" />
          <span>{error}</span>
        </div>
      )}

      <section className="card p-6">
        {(status === 'code' || status === 'joining') && (
          <form onSubmit={connectCode}>
            <label className="block">
              <span className="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Code op de tv</span>
              <input
                className="input w-full font-mono text-2xl tracking-[0.35em]"
                inputMode="numeric"
                pattern="[0-9]{6}"
                maxLength={6}
                autoComplete="one-time-code"
                value={code}
                onChange={(event) => setCode(event.target.value.replace(/\D/g, '').slice(0, 6))}
                placeholder="123456"
                required
                autoFocus
              />
            </label>
            <button type="submit" className="btn-primary mt-4" disabled={code.length !== 6 || status === 'joining'}>
              {status === 'joining' ? 'Verbinden…' : 'Met scherm verbinden'}
            </button>
          </form>
        )}

        {status === 'ready' && (
          <div>
            <p className="font-medium text-gray-900 dark:text-gray-100">Verbonden met {displayName}</p>
            <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">Kies in het volgende venster wat je wilt delen en vink eventueel audio delen aan.</p>
            <button type="button" className="btn-primary mt-4" onClick={startSharing}>
              <MonitorUp className="mr-2 h-4 w-4" />
              Kies scherm of venster
            </button>
          </div>
        )}

        {(status === 'connecting' || status === 'presenting') && (
          <div>
            <p className="font-medium text-gray-900 dark:text-gray-100">
              {status === 'presenting' ? `Je presenteert op ${displayName}` : `Verbinding maken met ${displayName}…`}
            </p>
            <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">Sluit deze pagina niet zolang je presenteert.</p>
            {minutesRemaining !== null && minutesRemaining <= 5 && (
              <p className="mt-3 rounded-lg bg-amber-50 p-3 text-sm font-medium text-amber-800 dark:bg-amber-950 dark:text-amber-200">
                Je presentatietoegang eindigt over {minutesRemaining} {minutesRemaining === 1 ? 'minuut' : 'minuten'}.
              </p>
            )}
            <button type="button" className="btn-danger mt-4" onClick={() => stopSharing()}>
              <Square className="mr-2 h-4 w-4" />
              Stoppen met presenteren
            </button>
          </div>
        )}
      </section>

      <p className="text-sm text-gray-500 dark:text-gray-400">Deze technische proef werkt het best wanneer laptop en tv hetzelfde netwerk gebruiken.</p>
    </div>
  );
}
