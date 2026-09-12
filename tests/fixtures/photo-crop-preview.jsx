import React, { useState } from 'react';
import { createRoot } from 'react-dom/client';
import PhotoCropModal from '../../src/components/PhotoCropModal';
import '../../src/index.css';

function Fixture() {
  const [file, setFile] = useState(null);
  const [saved, setSaved] = useState(null);
  const open = async () => {
    const canvas = document.createElement('canvas');
    canvas.width = 1200; canvas.height = 800;
    const context = canvas.getContext('2d');
    context.fillStyle = '#0891b2'; context.fillRect(0, 0, 400, 800);
    context.fillStyle = '#fbbf24'; context.fillRect(400, 0, 400, 800);
    context.fillStyle = '#be123c'; context.fillRect(800, 0, 400, 800);
    context.fillStyle = '#111827'; context.font = 'bold 70px sans-serif'; context.fillText('MIDDEN', 440, 420);
    canvas.toBlob(blob => setFile(new File([blob], 'test.png', { type: 'image/png' })));
  };
  return <main className="min-h-screen bg-gray-50 p-8">
    <h1 className="mb-4 text-xl font-semibold">Foto bijsnijden — lokale test</h1>
    <button className="btn-primary" onClick={open}>Testfoto openen</button>
    {file && <PhotoCropModal file={file} onClose={() => setFile(null)} onSave={async cropped => {
      const url = URL.createObjectURL(cropped);
      const image = new Image(); image.src = url; await image.decode();
      setSaved({ url, width: image.naturalWidth, height: image.naturalHeight, size: cropped.size, type: cropped.type });
      setFile(null);
    }} />}
    {saved && <section className="mt-6"><p role="status">Opgeslagen: {saved.width} × {saved.height} pixels, {saved.type}, {saved.size} bytes</p><img className="mt-3 w-72" src={saved.url} alt="Opgeslagen uitsnede" /></section>}
  </main>;
}
createRoot(document.getElementById('root')).render(<Fixture />);
