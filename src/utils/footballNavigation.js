import { CalendarDays, ChartPie, Gavel, Shield, Trophy, Users } from 'lucide-react';

export const footballNavigation = [
  { name: 'Teams', href: '/teams', icon: Shield, description: 'Bekijk teams en hun spelers en staf.', capabilities: ['is_kader'] },
  { name: 'Kaderlijst', href: '/kaderlijst', icon: Users, description: 'Vind trainers, leiders en andere kaderleden.', capabilities: ['can_access_kaderlijst'] },
  { name: 'Trainingsschema', href: '/trainingsschema', icon: CalendarDays, description: 'Bekijk wanneer en waar teams trainen.', capabilities: ['is_kader', 'can_manage_training'] },
  { name: 'Toernooien', href: '/toernooien', icon: Trophy, description: 'Bekijk en beheer de toernooien van de club.', capabilities: ['can_manage_tournaments'] },
  { name: 'Toegangsstatistieken', href: '/toegangsstatistieken', icon: ChartPie, description: 'Bekijk de geregistreerde bezoeken aan de club.', capabilities: ['can_access_toegangscontrole'] },
  { name: 'Tuchtzaken', href: '/tuchtzaken', icon: Gavel, description: 'Bekijk en behandel tuchtzaken.', capabilities: ['can_access_fairplay'] },
];

export function canAccessFootballItem(item, user) {
  return Boolean(user?.is_admin || item.capabilities.some((capability) => user?.[capability]));
}
