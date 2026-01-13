/**
 * Helper to check if content is empty (just empty paragraph tags)
 */
export function isRichTextEmpty(html) {
  if (!html) return true;
  const trimmed = html.trim();
  return trimmed === '' || trimmed === '<p></p>' || trimmed === '<p><br></p>';
}

/**
 * Helper to strip HTML tags for plain text preview
 */
export function stripHtmlTags(html) {
  if (!html) return '';
  return html.replace(/<[^>]*>/g, '').trim();
}
