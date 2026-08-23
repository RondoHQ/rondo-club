import { useEffect, useRef, useState } from 'react';
import {
  createPresentationSession,
  emptySignal,
  getPresentationSignal,
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

export default function PresentationReceiver({ enabled, deviceToken, displayName, roomPresentation }) {
  const videoRef = useRef(null);
  const [code, setCode] = useState('');
  const [remoteStream, setRemoteStream] = useState(null);
  const [status, setStatus] = useState('starting');
  const [bookingWindow, setBookingWindow] = useState(null);
  const [controlledUnavailable, setControlledUnavailable] = useState(false);

  useEffect(() => {
    if (videoRef.current) videoRef.current.srcObject = remoteStream;
  }, [remoteStream]);

  useEffect(() => {
    if (!enabled || !deviceToken) return undefined;

    let cancelled = false;
    let peer = null;
    let session = null;
    let pollTimer = null;
    let refreshTimer = null;
    let restartTimer = null;
    let remoteCandidateCount = 0;
    let restarting = false;
    let localSignal = emptySignal();
    let sendChain = Promise.resolve();

    const clearTimers = () => {
      window.clearTimeout(pollTimer);
      window.clearTimeout(refreshTimer);
      window.clearTimeout(restartTimer);
    };

    const closePeer = () => {
      if (peer) {
        peer.onicecandidate = null;
        peer.ontrack = null;
        peer.onconnectionstatechange = null;
        peer.close();
        peer = null;
      }
      setRemoteStream(null);
      setBookingWindow(null);
      remoteCandidateCount = 0;
      localSignal = emptySignal();
    };

    const publishSignal = () => {
      if (!session || cancelled) return;
      const activeSession = session;
      const snapshot = {
        ...localSignal,
        candidates: [...localSignal.candidates],
      };
      sendChain = sendChain
        .catch(() => {})
        .then(() => sendPresentationSignal(activeSession.session_id, activeSession.token, snapshot))
        .catch(() => {
          if (!cancelled) setStatus('error');
        });
    };

    const scheduleRestart = (delay = 1000) => {
      if (cancelled || restarting) return;
      restarting = true;
      window.clearTimeout(pollTimer);
      window.clearTimeout(refreshTimer);
      closePeer();
      setCode('');
      setStatus('starting');
      restartTimer = window.setTimeout(() => {
        restarting = false;
        startSession();
      }, delay);
    };

    const createPeer = () => {
      peer = new RTCPeerConnection();
      peer.onicecandidate = (event) => {
        if (!event.candidate) return;
        localSignal.candidates.push(candidatePayload(event.candidate));
        publishSignal();
      };
      peer.ontrack = (event) => {
        const stream = event.streams[0] || new MediaStream([event.track]);
        setRemoteStream(stream);
        setStatus('presenting');
      };
      peer.onconnectionstatechange = () => {
        if (peer?.connectionState === 'connected') setStatus('presenting');
        if (peer?.connectionState === 'failed') scheduleRestart();
        if (peer?.connectionState === 'disconnected') scheduleRestart(3000);
      };
      return peer;
    };

    const applySenderSignal = async (signal) => {
      if (!signal || signal.hangup) {
        if (signal?.hangup) scheduleRestart();
        return;
      }
      if (!signal.description) return;

      window.clearTimeout(refreshTimer);
      const activePeer = peer || createPeer();
      if (!activePeer.remoteDescription) {
        setStatus('connecting');
        await activePeer.setRemoteDescription(signal.description);
        const answer = await activePeer.createAnswer();
        await activePeer.setLocalDescription(answer);
        localSignal.description = activePeer.localDescription.toJSON();
        publishSignal();
      }

      const candidates = signal.candidates || [];
      while (remoteCandidateCount < candidates.length) {
        await activePeer.addIceCandidate(candidates[remoteCandidateCount]);
        remoteCandidateCount += 1;
      }
    };

    const poll = async () => {
      if (cancelled || restarting || !session) return;
      try {
        const response = await getPresentationSignal(session.session_id, session.token);
        await applySenderSignal(response.signal);
      } catch (error) {
        if (error.status === 401 || error.status === 410) {
          scheduleRestart();
          return;
        }
      }
      if (!cancelled && !restarting) pollTimer = window.setTimeout(poll, PRESENTATION_POLL_INTERVAL_MS);
    };

    async function startSession() {
      try {
        session = await createPresentationSession(deviceToken);
        if (cancelled) return;
        setControlledUnavailable(false);
        setCode(session.code);
        setBookingWindow(session.booking_id ? {
          roomName: session.room_name,
          startsAt: session.booking_starts_at,
          endsAt: session.booking_ends_at,
        } : null);
        setStatus('waiting');
        const refreshDelay = Math.max(1000, new Date(session.code_expires_at).getTime() - Date.now() - 5000);
        refreshTimer = window.setTimeout(() => scheduleRestart(0), refreshDelay);
        poll();
      } catch (error) {
        if (!cancelled) {
          setControlledUnavailable(error.status === 403);
          setStatus('error');
          restartTimer = window.setTimeout(() => {
            restarting = false;
            startSession();
          }, 10000);
        }
      }
    }

    startSession();
    return () => {
      cancelled = true;
      clearTimers();
      closePeer();
    };
  }, [deviceToken, enabled]);

  if (!enabled) return null;

  if (roomPresentation?.controlled && (!roomPresentation?.active || controlledUnavailable) && !code) return null;

  if (status === 'presenting' && remoteStream) {
    return (
      <div className="absolute inset-0 z-50 flex items-center justify-center bg-black">
        <video ref={videoRef} autoPlay playsInline className="h-full w-full object-contain" aria-label={`Presentatie op ${displayName}`} />
      </div>
    );
  }

  if (status === 'connecting') {
    return (
      <div className="absolute inset-0 z-50 flex items-center justify-center bg-slate-950 text-white">
        <p className="text-[2.4vw] font-semibold">Presentatie verbinden…</p>
      </div>
    );
  }

  return (
    <aside className="absolute bottom-[1.5vw] left-[1.8vw] z-40 rounded-[0.8vw] border border-white/20 bg-slate-950/90 px-[1.2vw] py-[0.9vw] text-white shadow-2xl backdrop-blur">
      <p className="text-[0.85vw] font-medium uppercase tracking-[0.12em] text-white/65">
        {bookingWindow?.roomName ? `${bookingWindow.roomName} gereserveerd` : 'Scherm delen'}
      </p>
      {code ? (
        <div className="mt-[0.3vw] flex items-baseline gap-[0.8vw]">
          <strong className="font-mono text-[2vw] tracking-[0.2em]">{code}</strong>
          <span className="text-[0.9vw] text-white/70">rondo.svawc.nl/presenteren</span>
          {bookingWindow?.endsAt && <span className="text-[0.9vw] text-white/70">tot {new Intl.DateTimeFormat('nl-NL', { hour: '2-digit', minute: '2-digit' }).format(new Date(bookingWindow.endsAt))}</span>}
        </div>
      ) : (
        <p className="mt-[0.3vw] text-[1vw] text-white/70">
          {status === 'error' ? 'Tijdelijk niet beschikbaar' : 'Code ophalen…'}
        </p>
      )}
    </aside>
  );
}
