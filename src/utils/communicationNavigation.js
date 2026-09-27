import { CalendarClock, MonitorPlay, QrCode } from 'lucide-react';
import { canAccessFeature } from './featureToggles';

export const communicationNavigation = [
  { name: 'Planning', href: '/communicatie/planning', icon: CalendarClock, description: 'Plan berichten en houd per kanaal bij wat is gedeeld.', sectionCapability: 'can_access_communicatie' },
  { name: 'Club TV', href: '/narrowcasting', icon: MonitorPlay, description: 'Beheer de inhoud en schermen van Club TV.', sectionCapability: 'can_access_narrowcasting', requiresFeature: 'narrowcasting' },
  { name: 'App access', href: '/app-toegang', icon: QrCode, description: 'Stel toegang tot gedeelde apps in.', sectionCapability: 'can_access_app_access' },
];

export function canAccessCommunicationItem(item, user) {
  return Boolean(user?.[item.sectionCapability])
    && (!item.requiresFeature || canAccessFeature(item.requiresFeature, user?.is_admin));
}

export function canAccessCommunication(user) {
  return communicationNavigation.some((item) => canAccessCommunicationItem(item, user));
}
