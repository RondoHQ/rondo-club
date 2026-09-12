const clamp = (value, min, max) => Math.max(min, Math.min(max, value));

/** Square crop in source pixels, shared by the preview and JPEG export. */
export function photoCropRect(width, height, zoom, position) {
  const size = Math.min(width, height) / clamp(zoom, 1, 4);
  return {
    x: (width - size) * clamp(position.x, 0, 1),
    y: (height - size) * clamp(position.y, 0, 1),
    size,
  };
}

export async function exportPhotoCrop(image, rect) {
  const canvas = document.createElement('canvas');
  // Avoid enlarging small originals. Strip metadata by exporting only the crop.
  canvas.width = canvas.height = Math.max(1, Math.min(800, Math.round(rect.size)));
  const context = canvas.getContext('2d');
  if (!context) throw new Error('De browser kan deze foto niet voorbereiden.');
  context.fillStyle = '#fff';
  context.fillRect(0, 0, canvas.width, canvas.height);
  context.drawImage(image, rect.x, rect.y, rect.size, rect.size, 0, 0, canvas.width, canvas.height);
  const blob = await new Promise(resolve => canvas.toBlob(resolve, 'image/jpeg', 0.92));
  if (!blob) throw new Error('De uitsnede kon niet worden opgeslagen.');
  return new File([blob], 'pasfoto.jpg', { type: 'image/jpeg' });
}
