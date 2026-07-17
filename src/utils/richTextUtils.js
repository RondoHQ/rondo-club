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

/**
 * Make every link in a trusted rich-text fragment open in a new tab.
 */
export function openHtmlLinksInNewTab(html) {
  if (!html || typeof html !== 'string') return html;

  return html.replace(/<a\b([^>]*)>/gi, (tag, attributes) => {
    const targetPattern = /\btarget\s*=\s*(?:"[^"]*"|'[^']*'|[^\s>]+)/i;
    const relPattern = /\brel\s*=\s*(?:"([^"]*)"|'([^']*)'|([^\s>]+))/i;
    const attributesWithTarget = targetPattern.test(attributes)
      ? attributes.replace(targetPattern, 'target="_blank"')
      : `${attributes} target="_blank"`;
    const relMatch = attributesWithTarget.match(relPattern);

    if (!relMatch) {
      return `<a${attributesWithTarget} rel="noopener noreferrer">`;
    }

    const relValues = new Set((relMatch[1] || relMatch[2] || relMatch[3] || '').split(/\s+/).filter(Boolean));
    relValues.add('noopener');
    relValues.add('noreferrer');

    return `<a${attributesWithTarget.replace(relPattern, `rel="${[...relValues].join(' ')}"`)}>`;
  });
}
