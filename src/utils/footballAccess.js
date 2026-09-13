import { canAccessFootballItem, footballNavigation } from './footballNavigation';

/** Whether a user can see any page in the football navigation section. */
export function canAccessFootball(user) {
  return footballNavigation.some((item) => canAccessFootballItem(item, user));
}
