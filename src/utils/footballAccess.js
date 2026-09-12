/** Whether a user can see any page in the football navigation section. */
export function canAccessFootball(user) {
  return Boolean(
    user?.is_admin || user?.is_kader || user?.can_access_kaderlijst ||
    user?.can_manage_training || user?.can_manage_tournaments ||
    user?.can_access_fairplay || user?.can_access_toegangscontrole
  );
}
