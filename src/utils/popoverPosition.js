export function shouldPlacePopoverBelow(
  anchorRect,
  viewportHeight,
  preferredHeight,
  margin = 16,
  gap = 8
) {
  const availableBelow = viewportHeight - anchorRect.bottom - gap - margin;
  const availableAbove = anchorRect.top - gap - margin;

  return availableBelow >= Math.min(preferredHeight, viewportHeight * 0.4)
    || availableBelow >= availableAbove;
}
