export const VOG_SUBMISSION_LABELS = {
  checking: 'Je VOG wordt gecontroleerd', technical: 'De controle lukt nu niet',
  review: 'De VOG-coördinator beoordeelt je document', needs_original: 'Origineel nodig',
  waiting_paper: 'Scan ontvangen; laat het originele papier zien', approved: 'Je VOG is goedgekeurd',
  rejected: 'Je inzending is afgewezen', expired: 'De inzending is verlopen', replaced: 'Inzending vervangen',
};

export async function refreshVog(client) {
  await Promise.all(['vog', 'people', 'person', 'shifts', 'volunteer'].map(key => client.invalidateQueries({ queryKey: [key] })));
}

