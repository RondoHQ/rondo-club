import { buildMatchdayScenes } from './matchdayScenes';

const dynamicTypes = new Set(['matches', 'rooms', 'cancellations', 'results']);

export function buildPlaylistScenes(manifest, feed, fallbackMessage) {
  if (!manifest?.scenes?.length) return buildMatchdayScenes(feed, fallbackMessage);

  const matchday = buildMatchdayScenes(feed, fallbackMessage);
  const scenes = [];

  manifest.scenes.forEach((item) => {
    if (dynamicTypes.has(item.type)) {
      const sceneType = item.type === 'rooms' ? 'matches' : item.type;
      matchday.filter((scene) => scene.type === sceneType).forEach((scene, page) => {
        scenes.push({ ...scene, id: `${item.id}-${page}`, duration_seconds: item.duration_seconds, colors: item.colors });
      });
      return;
    }
    scenes.push({ ...item, message: item.body || item.title });
  });

  const useful = scenes.filter((scene) => scene.type !== 'fallback' || scene.item_id);
  if (useful.length) return useful;
  if (scenes.length) return scenes;
  return [{ type: 'welcome', message: fallbackMessage || 'Vandaag is er geen actuele Club TV-informatie', duration_seconds: 12 }];
}
