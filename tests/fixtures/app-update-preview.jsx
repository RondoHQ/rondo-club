import { createRoot } from 'react-dom/client';
import { lazy, Suspense, useState } from 'react';
import { ReloadPrompt } from '../../src/components/ReloadPrompt';
import '../../src/index.css';

const LazyPage = lazy(() => import('./app-update-preview-page.jsx'));

function Preview() {
  const [open, setOpen] = useState(false);
  return (
    <main className="p-8">
      <h1>App {__UPDATE_FIXTURE_RELEASE__}</h1>
      <label>Onopgeslagen tekst <input className="border" /></label>
      <button onClick={() => setOpen(true)}>Open pagina</button>
      {open && <Suspense fallback="Laden…"><LazyPage /></Suspense>}
      <ReloadPrompt />
    </main>
  );
}

createRoot(document.getElementById('root')).render(<Preview />);
