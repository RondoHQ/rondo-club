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

const pointerCenter = (points) => ({
  x: points.reduce((sum, point) => sum + point.x, 0) / points.length,
  y: points.reduce((sum, point) => sum + point.y, 0) / points.length,
});
const pointerDistance = (points) => points.length === 2
  ? Math.hypot(points[1].x - points[0].x, points[1].y - points[0].y) : 0;

/** Rebase whenever fingers join or leave so switching between pan and pinch cannot jump. */
export function beginPhotoGesture(dimensions, crop, points, viewport) {
  if (!points.length || !viewport.width) return null;
  const center = pointerCenter(points);
  const rect = photoCropRect(dimensions.width, dimensions.height, crop.zoom, crop.position);
  return {
    zoom: crop.zoom,
    distance: pointerDistance(points),
    viewport,
    anchor: {
      x: rect.x + (center.x - viewport.left) / viewport.width * rect.size,
      y: rect.y + (center.y - viewport.top) / viewport.width * rect.size,
    },
  };
}

/** Keep the source pixel under the fingers' midpoint fixed while zooming and moving. */
export function movePhotoGesture(dimensions, gesture, points) {
  const center = pointerCenter(points);
  const zoom = clamp(gesture.zoom * (gesture.distance > 0 ? pointerDistance(points) / gesture.distance : 1), 1, 4);
  const size = Math.min(dimensions.width, dimensions.height) / zoom;
  const { viewport, anchor } = gesture;
  const offset = (axis, edge, span) => span > 0
    ? clamp((anchor[axis] - (center[axis] - edge) / viewport.width * size) / span, 0, 1) : 0.5;
  return {
    zoom,
    position: {
      x: offset('x', viewport.left, dimensions.width - size),
      y: offset('y', viewport.top, dimensions.height - size),
    },
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
