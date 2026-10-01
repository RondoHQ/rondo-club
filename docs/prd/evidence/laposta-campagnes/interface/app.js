// Local design prototype. No Laposta API client, credentials, or send action.
const $ = (selector) => document.querySelector(selector);
const escape = (value) => String(value).replace(/[&<>"']/g, (c) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const storageKey = 'rondo-newsletter-design-v1';
const audienceFixtures = {
  leden: { name: 'Leden 1', segments: ['Nog niets gedaan of ingepland', 'Al diensten ingepland', 'Vrijwilligers'] },
  proef: { name: 'Voorbeeldlijst jeugd', segments: ['Onderbouw (voorbeeld)', 'Bovenbouw (voorbeeld)'] },
};
let template = '', profiles = {}, step = 'edit', exported = false, revision = 0, savedRange;
let selections = [{list:'', segment:''}];
const initial = {profile:'joost', subject:$('#subject').value, preheader:$('#preheader').value, heading:$('#heading').value, body:$('#body').innerHTML, audiences:[{list:'',segment:''}]};

function cleanHtml(html) {
  const doc = new DOMParser().parseFromString(html, 'text/html');
  const allowed = new Set(['P','DIV','BR','STRONG','B','EM','I','UL','OL','LI','A']);
  function render(node) {
    if (node.nodeType === Node.TEXT_NODE) return escape(node.textContent);
    if (node.nodeType !== Node.ELEMENT_NODE || ['SCRIPT','STYLE','IFRAME','OBJECT'].includes(node.tagName)) return '';
    const children = [...node.childNodes].map(render).join('');
    if (!allowed.has(node.tagName)) return children;
    if (node.tagName === 'BR') return '<br>';
    const tag = node.tagName.toLowerCase();
    const href = node.getAttribute('href') || '';
    if (tag === 'a') return /^https?:\/\//i.test(href) ? `<a href="${escape(href)}">${children}</a>` : children;
    return `<${tag}>${children}</${tag}>`;
  }
  return [...doc.body.childNodes].map(render).join('');
}
function snapshot() {
  return {profile:$('#profile').value, subject:$('#subject').value, preheader:$('#preheader').value, heading:$('#heading').value, body:cleanHtml($('#body').innerHTML), audiences:selections.map(s=>({...s}))};
}
function restore(data) {
  for (const id of ['profile','subject','preheader','heading']) if (typeof data[id] === 'string') $(`#${id}`).value = data[id];
  $('#body').innerHTML = cleanHtml(typeof data.body === 'string' ? data.body : initial.body);
  selections = Array.isArray(data.audiences) && data.audiences.length ? data.audiences.slice(0,2).map(s=>({list:typeof s.list==='string'?s.list:'',segment:typeof s.segment==='string'?s.segment:''})) : [{list:'',segment:''}];
  drawAudiences(); updatePreview();
}
function drawAudiences() {
  $('#audiences').innerHTML = selections.map((s, i) => {
    const list = audienceFixtures[s.list];
    return `<div class="audience-row"><label for="list-${i}">Lijst${selections.length > 1 ? ` ${i+1}` : ''}</label><select id="list-${i}" data-row="${i}" data-field="list"><option value="">Kies een lijst…</option>${Object.entries(audienceFixtures).filter(([id])=> id===s.list || !selections.some(x=>x.list===id)).map(([id,l])=>`<option value="${id}" ${id===s.list?'selected':''}>${escape(l.name)}</option>`).join('')}</select><label for="segment-${i}">Selectie binnen deze lijst</label><select id="segment-${i}" data-row="${i}" data-field="segment" ${list?'':'disabled'}><option value="">Kies een segment of de hele lijst…</option>${list ? `<optgroup label="Segmenten">${list.segments.map(name=>`<option value="${escape(name)}" ${s.segment===name?'selected':''}>${escape(name)}</option>`).join('')}</optgroup><option value="all" ${s.segment==='all'?'selected':''}>Hele lijst</option>` : ''}</select>${selections.length>1?`<button class="remove icon-button" data-remove="${i}" aria-label="Lijst ${i+1} verwijderen"><svg viewBox="0 0 24 24"><path d="m6 6 12 12M6 18 18 6"/></svg></button>`:''}</div>`;
  }).join('');
  $('#add-list').disabled = selections.length >= Object.keys(audienceFixtures).length;
}
function updatePreview() {
  const p = profiles[$('#profile').value];
  $('#sender').innerHTML = p ? `<div class="role">${escape(p.role)}</div><dl><dt>Van</dt><dd><strong>${escape(p.from_name)}</strong><br>${escape(p.from_email)}</dd><dt>Antwoord naar</dt><dd>${escape(p.reply_to)}</dd></dl>` : '<div class="error" style="margin:0">Er is nog geen ondertekeningsprofiel. Laat een beheerder de afzendergegevens instellen.</div>';
  $('#email').hidden = !p; $('#preview-unavailable').hidden = !!p;
  $('#inbox-from').textContent = p?.from_name || 'Afzender ontbreekt';
  $('#sender-initials').textContent = p ? (p.name === 'Joost de Valk' ? 'JV' : 'XN') : '?';
  $('#inbox-subject').textContent = $('#subject').value || 'Nog geen onderwerp';
  $('#inbox-preheader').textContent = $('#preheader').value;
  if (!p || !template) return;
  const fields = {DOCUMENT_TITLE:$('#subject').value, HEADING:$('#heading').value, SIGNATURE_URL:p.signature_url, SIGNATURE_HEIGHT:p.signature_height, SIGNER_NAME:p.name, SIGNER_ROLE:p.role};
  let html = template.replace(/%%([A-Z_]+)%%/g,(_,key)=>key==='BODY_HTML'?cleanHtml($('#body').innerHTML):escape(fields[key]||''));
  html = html.replace('{{voornaam,AWCer}}','Sam');
  html = html.replace('</head>', '<style>.text-td p{margin:0 0 12px!important}.text-td p:last-child{margin-bottom:0!important}</style></head>');
  // Preview links are intentionally inert. This iframe has no same-origin or script privileges.
  html = html.replace(/<head>/i,'<head><meta http-equiv="Content-Security-Policy" content="default-src \'none\'; img-src https:; style-src \'unsafe-inline\'; font-src https:;">');
  html = html.replace(/<a\b[^>]*>/gi,'<a>').replace(/<\/?(?:unsubscribe|webversion)>/gi,'');
  if ($('#preheader').value) html = html.replace(/<body([^>]*)>/i,`<body$1><div style="display:none;max-height:0;overflow:hidden">${escape($('#preheader').value)}</div>`);
  $('#email').srcdoc = html;
}
function change() {
  revision++; step='edit'; $('#errors').hidden=true;
  $('#draft-status').textContent=exported?'Lokale wijzigingen':'Lokaal concept';
  $('#save-status').textContent='Niet opgeslagen';
  updatePreview();
}
function showStep(next) {
  step=next;
  $('#edit-panel').hidden=next!=='edit'; $('#review-panel').hidden=next!=='review'; $('#success-panel').hidden=next!=='success';
  $('#next').hidden=next==='success'; $('#save').hidden=next==='success';
  $('#next').innerHTML=next==='review'?'Concept aanmaken in Laposta':'Controleren<svg viewBox="0 0 24 24"><path d="M5 12h14m-7-7 7 7-7 7"/></svg>';
  document.body.classList.remove('show-preview'); setView('edit');
  if(next!=='edit') $(`#${next}-panel`).focus();
  window.scrollTo({top:0,behavior:'instant'});
}
function validate() {
  const errors=[];
  if(!profiles[$('#profile').value]) errors.push('Kies een verantwoordelijke met een compleet ondertekeningsprofiel.');
  const listSet=new Set();
  for(const s of selections){
    const list=audienceFixtures[s.list];
    if(!list || (!list.segments.includes(s.segment)&&s.segment!=='all')) errors.push('Kies voor iedere lijst expliciet een segment of de hele lijst.');
    if(listSet.has(s.list)) errors.push('Gebruik iedere lijst maar één keer.');
    listSet.add(s.list);
  }
  if(!$('#subject').value.trim()) errors.push('Vul het onderwerp in.');
  if(!$('#heading').value.trim()) errors.push('Vul de titel in de nieuwsbrief in.');
  if(!$('#body').textContent.trim()) errors.push('Schrijf een bericht.');
  $('#errors').hidden=!errors.length;
  $('#errors').innerHTML=`<ul>${[...new Set(errors)].map(e=>`<li>${escape(e)}</li>`).join('')}</ul>`;
  if(errors.length){setView('edit'); $('#errors').scrollIntoView({block:'center',behavior:'instant'});}
  return !errors.length;
}
function review() {
  if(!validate())return;
  const s=snapshot(),p=profiles[s.profile];
  $('#review-details').innerHTML=`<dt>Verantwoordelijke en ondertekening</dt><dd>${escape(p.name)} · ${escape(p.role)}</dd><dt>Afzender</dt><dd>${escape(p.from_name)}<small>${escape(p.from_email)}<br>Antwoord naar: ${escape(p.reply_to)}</small></dd><dt>Doelgroep</dt><dd>${s.audiences.map(a=>`${escape(audienceFixtures[a.list].name)} → <strong>${escape(a.segment==='all'?'Hele lijst':a.segment)}</strong>`).join('<br>')}<small>Ontvangers controleren in Laposta.</small></dd><dt>Onderwerp</dt><dd>${escape(s.subject)}</dd><dt>Previewtekst</dt><dd>${escape(s.preheader||'Niet ingevuld')}</dd>`;
  showStep('review');$('#save-status').textContent='Klaar voor controle · demonstratie';
}
function setView(view) {
  document.body.classList.toggle('show-preview',view==='preview');
  document.querySelectorAll('[data-view]').forEach(b=>b.setAttribute('aria-pressed',String(b.dataset.view===view)));
}
$('#profile').addEventListener('change',change);
for(const id of ['subject','preheader','heading','body']) $(`#${id}`).addEventListener('input',change);
$('#audiences').addEventListener('change',e=>{const i=Number(e.target.dataset.row),field=e.target.dataset.field;if(!['list','segment'].includes(field))return;selections[i][field]=e.target.value;if(field==='list')selections[i].segment='';drawAudiences();change();document.querySelector(`#${field}-${i}`).focus();});
$('#audiences').addEventListener('click',e=>{const b=e.target.closest('[data-remove]');if(!b)return;selections.splice(Number(b.dataset.remove),1);drawAudiences();change();$('#add-list').focus();});
$('#add-list').addEventListener('click',()=>{selections.push({list:'',segment:''});drawAudiences();change();$(`#list-${selections.length-1}`).focus();});
$('#save').addEventListener('click',()=>{try{localStorage.setItem(storageKey,JSON.stringify(snapshot()));$('#save-status').textContent=`Opgeslagen in deze browser · ${new Date().toLocaleTimeString('nl-NL',{hour:'2-digit',minute:'2-digit'})}`;}catch{$('#save-status').textContent='Browseropslag niet beschikbaar; houd dit tabblad open.';}});
$('#next').addEventListener('click',()=>{
  if(step==='edit'){review();return;}
  if(step!=='review'||!validate())return;
  $('#next').disabled=true;$('#next').textContent='Concept voorbereiden…';$('#save-status').textContent='Demonstratie wordt uitgevoerd…';
  const currentRevision=revision;
  setTimeout(()=>{if(currentRevision!==revision){$('#next').disabled=false;return;}exported=true;$('#draft-status').textContent='Demonstratie voltooid';$('#save-status').textContent='Geen echte campagne aangemaakt';$('#item-state').textContent='Demonstratie voltooid · nog niet afgerond';$('#next').disabled=false;showStep('success');},650);
});
$('#back-edit').addEventListener('click',()=>showStep('edit'));
$('#edit-again').addEventListener('click',()=>showStep('edit'));
$('#back-item').addEventListener('click',()=>$('#item-dialog').showModal());
for(const b of document.querySelectorAll('[data-close]')) b.addEventListener('click',()=>document.getElementById(b.dataset.close).close());
for(const b of document.querySelectorAll('[data-view]')) b.addEventListener('click',()=>setView(b.dataset.view));
for(const mode of ['desktop','mobile']) $(`#${mode}`).addEventListener('click',()=>{$('#email').classList.toggle('mobile',mode==='mobile');$('#desktop').setAttribute('aria-pressed',String(mode==='desktop'));$('#mobile').setAttribute('aria-pressed',String(mode==='mobile'));});
$('#theme').addEventListener('click',()=>{const dark=document.body.classList.toggle('dark');$('#theme').setAttribute('aria-label',dark?'Lichte weergave':'Donkere weergave');});
$('#reset').addEventListener('click',()=>{revision++;exported=false;try{localStorage.removeItem(storageKey);}catch{}restore(initial);$('#draft-status').textContent='Lokaal concept';$('#save-status').textContent='Alleen in dit prototype';$('#errors').hidden=true;$('#next').disabled=false;$('#item-state').textContent='Lokaal concept · nog niet afgerond';showStep('edit');});
for(const b of document.querySelectorAll('[data-command]')){b.addEventListener('mousedown',e=>e.preventDefault());b.addEventListener('click',()=>{$('#body').focus();document.execCommand(b.dataset.command,false);change();});}
$('#body').addEventListener('paste',e=>{e.preventDefault();document.execCommand('insertText',false,e.clipboardData.getData('text/plain'));change();});
$('#add-link').addEventListener('mousedown',e=>e.preventDefault());
$('#add-link').addEventListener('click',()=>{const selection=window.getSelection();savedRange=selection.rangeCount&&$('#body').contains(selection.anchorNode)?selection.getRangeAt(0).cloneRange():null;$('#link-text').value=savedRange?.toString()||'';$('#link-url').value='';$('#link-dialog').showModal();});
$('#link-form').addEventListener('submit',e=>{e.preventDefault();const url=$('#link-url').value;if(!/^https?:\/\//i.test(url)){$('#link-url').setCustomValidity('Gebruik een webadres dat begint met https:// of http://.');$('#link-url').reportValidity();return;}$('#link-dialog').close();$('#body').focus();if(savedRange){const s=window.getSelection();s.removeAllRanges();s.addRange(savedRange);}document.execCommand('insertHTML',false,`<a href="${escape(url)}">${escape($('#link-text').value)}</a>`);change();});
$('#link-url').addEventListener('input',()=>$('#link-url').setCustomValidity(''));
try {
  const responses=await Promise.all([fetch('../base-template.html'),fetch('../profiles.json')]);
  if(responses.some(r=>!r.ok))throw new Error('Prototypebestanden ontbreken');
  [template,profiles]=await Promise.all([responses[0].text(),responses[1].json()]);
  let data=initial;try{const stored=localStorage.getItem(storageKey);if(stored){data=JSON.parse(stored);$('#save-status').textContent='Lokaal concept uit deze browser geladen';}}catch{}
  restore(data);
} catch {
  $('#errors').hidden=false;$('#errors').textContent='Het prototype kon de template niet laden. Open het via de lokale previewserver en herlaad de pagina.';$('#next').disabled=true;
}
