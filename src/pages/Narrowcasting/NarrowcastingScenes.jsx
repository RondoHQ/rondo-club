export default function NarrowcastingScene({ scene }) {
  if (scene.type === 'matches') return <MatchesScene items={scene.items} />;
  if (scene.type === 'rooms') return <RoomsScene items={scene.items} />;
  if (scene.type === 'cancellations') return <CancellationsScene items={scene.items} />;
  if (scene.type === 'results') return <ResultsScene items={scene.items} />;
  if (scene.type === 'unavailable') return <UnavailableScene />;
  return <WelcomeScene message={scene.message} />;
}

function SceneHeading({ eyebrow, title }) {
  return (
    <div className="mb-[2vw]">
      <p className="text-[1.2vw] font-semibold uppercase tracking-[0.22em] text-cyan-300">{eyebrow}</p>
      <h2 className="mt-[0.5vw] text-[3.2vw] font-semibold leading-none">{title}</h2>
    </div>
  );
}

function MatchesScene({ items }) {
  return (
    <section>
      <SceneHeading eyebrow="Programma" title="Wedstrijden vandaag" />
      <div className="overflow-hidden rounded-[1vw] border border-white/15 bg-slate-900/70">
        {items.map((match) => (
          <div key={match.id} className="grid grid-cols-[8vw_1fr_12vw] items-center gap-[1.5vw] border-b border-white/10 px-[2vw] py-[1.05vw] last:border-b-0">
            <time className="font-mono text-[2vw] font-semibold tabular-nums text-cyan-200">{match.time}</time>
            <p className={`truncate text-[1.65vw] font-medium ${match.cancelled ? 'text-red-200 line-through' : ''}`}>
              {match.home_team} <span className="px-[0.7vw] text-slate-400">–</span> {match.away_team}
            </p>
            <div className="text-right">
              {match.cancelled ? (
                <span className="rounded-full bg-red-500/20 px-[1vw] py-[0.45vw] text-[1.1vw] font-semibold uppercase tracking-wide text-red-200">Afgelast</span>
              ) : (
                <span className="text-[1.4vw] font-semibold text-slate-200">{match.pitch || 'Veld volgt'}</span>
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
          <article key={match.id} className="grid grid-cols-[6vw_1fr_1fr_8vw] items-center gap-[1.2vw] rounded-[0.9vw] border border-white/15 bg-slate-900/70 px-[1.7vw] py-[1vw]">
            <time className="font-mono text-[1.7vw] font-semibold text-cyan-200">{match.time}</time>
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
      <p className="truncate text-[1.35vw] font-medium">{team}</p>
      <p className="mt-[0.2vw] text-[1.05vw] text-slate-300">Kleedkamer <strong className="text-white">{room || 'volgt'}</strong></p>
    </div>
  );
}

function CancellationsScene({ items }) {
  return (
    <section>
      <SceneHeading eyebrow="Let op" title="Afgelaste wedstrijden" />
      <div className="space-y-[0.9vw]">
        {items.map((match) => (
          <article key={match.id} className="grid grid-cols-[8vw_1fr] items-center gap-[1.5vw] rounded-[0.9vw] border border-red-300/25 bg-red-500/10 px-[2vw] py-[1.25vw]">
            <time className="font-mono text-[2vw] font-semibold text-red-200">{match.time}</time>
            <div>
              <p className="text-[1.7vw] font-semibold">{match.home_team} – {match.away_team}</p>
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
      <div className="overflow-hidden rounded-[1vw] border border-white/15 bg-slate-900/70">
        {items.map((match) => (
          <div key={match.id} className="grid grid-cols-[1fr_9vw] items-center gap-[1.5vw] border-b border-white/10 px-[2vw] py-[1.05vw] last:border-b-0">
            <p className="truncate text-[1.65vw] font-medium">{match.home_team} <span className="px-[0.7vw] text-slate-400">–</span> {match.away_team}</p>
            <p className="text-right font-mono text-[2vw] font-semibold tabular-nums text-cyan-200">{match.result}</p>
          </div>
        ))}
      </div>
    </section>
  );
}

function WelcomeScene({ message }) {
  return (
    <section>
      <p className="max-w-[76vw] text-[4.5vw] font-semibold leading-[1.08] tracking-tight">{message}</p>
    </section>
  );
}

function UnavailableScene() {
  return (
    <section>
      <p className="text-[1.3vw] font-semibold uppercase tracking-[0.22em] text-amber-300">Tijdelijk niet beschikbaar</p>
      <h2 className="mt-[1vw] max-w-[75vw] text-[4vw] font-semibold leading-tight">De wedstrijdinformatie kan nu niet worden bijgewerkt</h2>
      <p className="mt-[1.5vw] text-[1.6vw] text-slate-300">Rondo probeert het automatisch opnieuw.</p>
    </section>
  );
}
