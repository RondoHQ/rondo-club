import { useEffect, useState } from 'react';
import { createRoot } from 'react-dom/client';
import PhotoCropModal from '../../src/components/PhotoCropModal';
import './photo-crop-preview.css';

export default function Fixture() {
  const [file, setFile] = useState(null);
  const [saved, setSaved] = useState(null);
  const [error, setError] = useState('');
  useEffect(() => () => { if (saved) URL.revokeObjectURL(saved.url); }, [saved]);
  const choosePhoto = (event) => {
    const selected = event.target.files?.[0];
    event.target.value = '';
    setError('');
    if (!selected) return;
    if (!selected.type.startsWith('image/')) {
      setError('Kies een afbeelding.');
      return;
    }
    if (selected.size > 5 * 1024 * 1024) {
      setError('Deze foto is groter dan 5 MB. Kies een kleinere foto of gebruik de voorbeeldfoto.');
      return;
    }
    setFile(selected);
  };
  const open = async () => {
    setError('');
    const canvas = document.createElement('canvas');
    canvas.width = 1200; canvas.height = 800;
    const context = canvas.getContext('2d');
    context.fillStyle = '#0891b2'; context.fillRect(0, 0, 400, 800);
    context.fillStyle = '#fbbf24'; context.fillRect(400, 0, 400, 800);
    context.fillStyle = '#be123c'; context.fillRect(800, 0, 400, 800);
    context.fillStyle = '#111827'; context.font = 'bold 70px sans-serif'; context.fillText('MIDDEN', 440, 420);
    canvas.toBlob(blob => setFile(new File([blob], 'test.png', { type: 'image/png' })));
  };
  return <main className="mx-auto min-h-screen max-w-lg bg-gray-50 px-5 py-8">
    <h1 className="mb-3 text-2xl font-semibold">Foto bijsnijden testen</h1>
    <p className="mb-5 text-gray-600">Open deze pagina in Chrome op je iPhone. Je foto blijft op dit toestel; deze test verandert geen profielfoto in Rondo of Sportlink.</p>
    <div className="flex flex-col items-start gap-3">
      <button className="btn-primary" onClick={open}>Voorbeeldfoto openen</button>
      <label className="btn-secondary relative cursor-pointer focus-within:outline-2 focus-within:outline-offset-2 focus-within:outline-cyan-600">
        Eigen foto kiezen
        <input type="file" accept="image/*" aria-label="Eigen foto kiezen" onChange={choosePhoto} className="absolute inset-0 w-full cursor-pointer opacity-0" />
      </label>
    </div>
    {error && <p role="alert" className="mt-3 text-red-600">{error}</p>}
    <section className="my-6 rounded-xl border border-gray-200 bg-white p-4">
      <h2 className="mb-3 font-semibold">Probeer deze vier stappen</h2>
      <ol className="list-decimal space-y-3 pl-5 text-sm">
        <li>Open de voorbeeldfoto en zoom in en uit met twee vingers op de foto.</li>
        <li>Til één vinger op en sleep verder. De uitsnede hoort niet te verspringen.</li>
        <li>Draai je telefoon, controleer de schuifbalk en kies <strong>Uitsnede herstellen</strong>.</li>
        <li>Maak opnieuw een uitsnede en kies <strong>Uitsnede opslaan</strong>. Het resultaat hoort overeen te komen met het voorbeeld.</li>
      </ol>
    </section>
    {file && <PhotoCropModal file={file} onClose={() => setFile(null)} onSave={async cropped => {
      const url = URL.createObjectURL(cropped);
      const image = new Image(); image.src = url; await image.decode();
      setSaved({ url, width: image.naturalWidth, height: image.naturalHeight, size: cropped.size, type: cropped.type });
      setFile(null);
    }} />}
    {saved && <section className="mt-6"><p role="status">Testresultaat: {saved.width} × {saved.height} pixels, {saved.type}, {saved.size} bytes</p><img className="mt-3 w-72" src={saved.url} alt="Opgeslagen uitsnede" /></section>}
  </main>;
}
createRoot(document.getElementById('root')).render(<Fixture />);
