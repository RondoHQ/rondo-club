// Isolated spike renderer. No network calls; not a production content editor.
import { readFileSync, writeFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';

const profileId = process.argv[2];
const output = process.argv[3];
const profiles = JSON.parse(readFileSync(new URL('./profiles.json', import.meta.url), 'utf8'));
if (!Object.hasOwn(profiles, profileId ?? '') || !output) {
  throw new Error('Usage: node render.mjs joost|xander /path/to/output.html');
}
const profile = profiles[profileId];
const escape = (value) => String(value).replace(/[&<>"']/g, (char) => ({
  '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
}[char]));
const fields = {
  DOCUMENT_TITLE: 'Rondo API proef',
  HEADING: 'Rondo campagneproef',
  SIGNATURE_URL: profile.signature_url,
  SIGNATURE_HEIGHT: profile.signature_height,
  SIGNER_NAME: profile.name,
  SIGNER_ROLE: profile.role,
};
const body = '<p>Dit is een technisch concept voor de Rondo-campagnefunctie. De doelgroep is een lege testlijst.</p><p><strong>Eén template</strong> met wisselende ondertekening.</p>';
const template = readFileSync(new URL('./base-template.html', import.meta.url), 'utf8');
const html = template.replace(/%%([A-Z_]+)%%/g, (_, key) => {
  // Only this hardcoded fixture is trusted HTML. User content needs sanitization.
  if (key === 'BODY_HTML') return body;
  if (!Object.hasOwn(fields, key)) throw new Error(`Unknown placeholder: ${key}`);
  return escape(fields[key]);
});
// Laposta removes the final newline during import.
writeFileSync(output, html.trimEnd());
console.log(`Rendered ${profileId} from ${fileURLToPath(new URL('./base-template.html', import.meta.url))}`);
