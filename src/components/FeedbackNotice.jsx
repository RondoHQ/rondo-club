export default function FeedbackNotice({ notice = window.rondoConfig?.feedbackNotice }) {
  if (!notice?.enabled) return null;

  const parts = (notice.text || '').split('{email}');
  const emailLink = (
    <a className="font-medium underline break-all" href={`mailto:${notice.email}`}>
      {notice.email}
    </a>
  );

  return (
    <aside className="rounded-lg border border-cyan-200 bg-cyan-50 p-4 text-sm text-cyan-950 dark:border-cyan-800 dark:bg-cyan-950/40 dark:text-cyan-100" aria-label={notice.title}>
      <h3 className="font-semibold mb-2">{notice.title}</h3>
      <p className="whitespace-pre-wrap">
        {parts.map((part, index) => (
          <span key={index}>
            {index > 0 ? emailLink : null}
            {part}
          </span>
        ))}
      </p>
      {parts.length === 1 ? <p className="mt-2">{emailLink}</p> : null}
    </aside>
  );
}
