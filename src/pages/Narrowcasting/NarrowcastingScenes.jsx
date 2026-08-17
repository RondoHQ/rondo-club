export default function NarrowcastingScene({ scene }) {
  if (scene.type === 'matches') return <MatchesScene items={scene.items} />;
  if (scene.type === 'rooms') return <RoomsScene items={scene.items} />;
  if (scene.type === 'cancellations') return <CancellationsScene items={scene.items} />;
  if (scene.type === 'results') return <ResultsScene items={scene.items} />;
  if (scene.type === 'announcement' || scene.type === 'fallback') return <AnnouncementScene scene={scene} />;
  if (scene.type === 'sponsor') return <SponsorScene scene={scene} />;
  if (scene.type === 'image') return <ImageScene scene={scene} />;
  if (scene.type === 'video') return <VideoScene scene={scene} />;
  if (scene.type === 'unavailable') return <UnavailableScene />;
  return <WelcomeScene message={scene.message} />;
}

function AnnouncementScene({ scene }) {
  return (
    <section className="max-w-[78vw] border-l-[0.5vw] border-[var(--scene-accent,var(--club-accent))] pl-[2.4vw]" style={{ '--scene-accent': scene.colors?.accent }}>
      {scene.title && <p className="text-[1.15vw] font-bold uppercase tracking-[0.24em] text-[var(--scene-accent,var(--club-accent-soft))]">{scene.title}</p>}
      <h2 className="mt-[0.9vw] whitespace-pre-line text-[4.2vw] font-bold leading-[1.06] tracking-tight">{scene.body || scene.message || scene.title}</h2>
      {scene.cta_text && <p className="mt-[1.6vw] text-[1.7vw] opacity-80">{scene.cta_text}</p>}
    </section>
  );
}

function SponsorScene({ scene }) {
  const logo = scene.media?.url || scene.sponsor?.logo_url;
  return (
    <section className="grid grid-cols-[minmax(0,1.05fr)_minmax(0,.95fr)] items-center gap-[5vw]">
      {logo && <div className="flex min-h-[38vh] items-center justify-center rounded-[1.4vw] bg-white p-[3vw] shadow-[0_1.5vw_5vw_rgba(0,0,0,.25)]"><img src={logo} alt={scene.media?.alt || scene.sponsor?.name || ''} className="max-h-[42vh] max-w-[39vw] object-contain" /></div>}
      <div className="max-w-[40vw] text-left">
        <p className="text-[1.15vw] font-bold uppercase tracking-[0.24em]" style={{ color: scene.colors?.accent || 'var(--club-accent-soft)' }}>Onze sponsor</p>
        <h2 className="mt-[0.9vw] text-[4vw] font-bold leading-[1.05] tracking-tight">{scene.sponsor?.name || scene.title}</h2>
        {scene.body && <p className="mt-[1.2vw] text-[1.7vw] opacity-85">{scene.body}</p>}
      </div>
    </section>
  );
}

function ImageScene({ scene }) {
  return <section className="flex items-center justify-center"><img src={scene.media?.url} alt={scene.media?.alt || scene.title || ''} className="max-h-[58vh] max-w-[88vw] object-contain" /></section>;
}

function VideoScene({ scene }) {
  return <section className="flex items-center justify-center"><video key={scene.media?.url} src={scene.media?.url} className="max-h-[60vh] max-w-[90vw]" autoPlay muted playsInline /></section>;
}

function SceneHeading({ eyebrow, title }) {
  return (
    <div className="mb-[2vw]">
      <p className="text-[1.05vw] font-bold uppercase tracking-[0.26em] text-[var(--club-accent-soft)]">{eyebrow}</p>
      <h2 className="mt-[0.5vw] text-[3.2vw] font-bold leading-none tracking-tight">{title}</h2>
    </div>
  );
}

function Team({ name, align = 'left' }) {
  return (
    <span className={`block min-w-0 truncate ${align === 'right' ? 'text-right' : ''}`}>{name}</span>
  );
}

function MatchesScene({ items }) {
  return (
    <section>
      <SceneHeading eyebrow="Programma" title="Wedstrijden vandaag" />
      <div className="overflow-hidden rounded-[1.1vw] border border-white/15 bg-black/20 shadow-[0_1vw_3vw_rgba(0,0,0,.14)] backdrop-blur-sm">
        {items.map((match) => (
          <div key={match.id} className="grid grid-cols-[7vw_1fr_10vw] items-center gap-[1.5vw] border-b border-white/10 px-[2vw] py-[0.85vw] last:border-b-0">
            <time className="font-mono text-[1.85vw] font-bold tabular-nums text-[var(--club-accent-soft)]">{match.time}</time>
            <p className={`grid min-w-0 grid-cols-[1fr_auto_1fr] items-center gap-[0.9vw] text-[1.45vw] font-semibold ${match.cancelled ? 'text-red-200 line-through' : ''}`}>
              <Team name={match.home_team} align="right" />
              <span className="rounded-full bg-white/8 px-[0.65vw] py-[0.18vw] text-[0.85vw] font-bold text-white/45">VS</span>
              <Team name={match.away_team} />
            </p>
            <div className="text-right">
              {match.cancelled ? (
                <span className="rounded-full bg-red-500/20 px-[1vw] py-[0.45vw] text-[1.1vw] font-semibold uppercase tracking-wide text-red-200">Afgelast</span>
              ) : (
                <span className="text-[1.3vw] font-bold text-white/85">{match.pitch || 'Veld volgt'}</span>
              )}
            </div>
          </div>
        ))}
      </div>
    </section>
  );
}

function RoomsScene({ items }) {
  return (
    <section>
      <SceneHeading eyebrow="Ontvangst" title="Velden en kleedkamers" />
      <div className="space-y-[0.8vw]">
        {items.map((match) => (
          <article key={match.id} className="grid grid-cols-[6vw_1fr_1fr_8vw] items-center gap-[1.2vw] rounded-[0.9vw] border border-white/15 bg-black/20 px-[1.7vw] py-[0.9vw] backdrop-blur-sm">
            <time className="font-mono text-[1.7vw] font-bold text-[var(--club-accent-soft)]">{match.time}</time>
            <Room team={match.home_team} room={match.dressing_rooms?.home} />
            <Room team={match.away_team} room={match.dressing_rooms?.away} />
            <div className="text-right">
              <p className="text-[1vw] uppercase tracking-wide text-slate-400">Veld</p>
              <p className="text-[1.55vw] font-semibold">{match.pitch || '—'}</p>
            </div>
          </article>
        ))}
      </div>
    </section>
  );
}

function Room({ team, room }) {
  return (
    <div className="min-w-0">
      <p className="text-[1.25vw] font-semibold"><Team name={team} /></p>
      <p className="mt-[0.25vw] text-[1vw] text-white/60">Kleedkamer <strong className="text-white">{room || 'volgt'}</strong></p>
    </div>
  );
}

function CancellationsScene({ items }) {
  return (
    <section>
      <SceneHeading eyebrow="Let op" title="Afgelaste wedstrijden" />
      <div className="space-y-[0.9vw]">
        {items.map((match) => (
          <article key={match.id} className="grid grid-cols-[8vw_1fr] items-center gap-[1.5vw] rounded-[0.9vw] border border-red-300/25 bg-red-500/12 px-[2vw] py-[1.05vw] backdrop-blur-sm">
            <time className="font-mono text-[2vw] font-semibold text-red-200">{match.time}</time>
            <div>
              <p className="grid grid-cols-[1fr_auto_1fr] items-center gap-[1vw] text-[1.55vw] font-semibold"><Team name={match.home_team} align="right" /><span className="text-white/40">–</span><Team name={match.away_team} /></p>
              <p className="mt-[0.2vw] text-[1.1vw] font-medium uppercase tracking-wide text-red-200">Afgelast</p>
            </div>
          </article>
        ))}
      </div>
    </section>
  );
}

function ResultsScene({ items }) {
  return (
    <section>
      <SceneHeading eyebrow="Terugblik" title="Recente uitslagen" />
      <div className="overflow-hidden rounded-[1vw] border border-white/15 bg-black/20 backdrop-blur-sm">
        {items.map((match) => (
          <div key={match.id} className="grid grid-cols-[1fr_9vw] items-center gap-[1.5vw] border-b border-white/10 px-[2vw] py-[1.05vw] last:border-b-0">
            <p className="grid min-w-0 grid-cols-[1fr_auto_1fr] items-center gap-[0.9vw] text-[1.45vw] font-semibold"><Team name={match.home_team} align="right" /><span className="text-white/40">–</span><Team name={match.away_team} /></p>
            <p className="text-right font-mono text-[2vw] font-bold tabular-nums text-[var(--club-accent-soft)]">{match.result}</p>
          </div>
        ))}
      </div>
    </section>
  );
}

function WelcomeScene({ message }) {
  return (
    <section className="max-w-[78vw] border-l-[0.55vw] border-[var(--club-accent)] pl-[2.5vw]">
      <p className="text-[1.1vw] font-bold uppercase tracking-[0.26em] text-[var(--club-accent-soft)]">Welkom</p>
      <p className="mt-[0.8vw] text-[4.7vw] font-bold leading-[1.04] tracking-tight">{message}</p>
    </section>
  );
}

function UnavailableScene() {
  return (
    <section>
      <p className="text-[1.3vw] font-semibold uppercase tracking-[0.22em] text-amber-300">Tijdelijk niet beschikbaar</p>
      <h2 className="mt-[1vw] max-w-[75vw] text-[4vw] font-semibold leading-tight">De wedstrijdinformatie kan nu niet worden bijgewerkt</h2>
      <p className="mt-[1.5vw] text-[1.6vw] text-white/65">Rondo probeert het automatisch opnieuw.</p>
    </section>
  );
}
