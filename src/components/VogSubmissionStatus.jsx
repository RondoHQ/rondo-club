import { FileCheck } from 'lucide-react';
import { format } from '@/utils/dateFormat';
import { VOG_SUBMISSION_LABELS } from '@/utils/vog';

export default function VogSubmissionStatus({ submission, vog, renewalText }) {
  const pending = ['checking', 'technical', 'review'].includes(submission.status);
  const title = submission.status === 'approved' && vog.status === 'expired' ? 'Je VOG is verlopen' : pending ? 'Je VOG is ontvangen' : VOG_SUBMISSION_LABELS[submission.status] || 'In behandeling';

  return (
    <section className="card p-5" role="status">
      <div className="flex items-start gap-3">
        <FileCheck className="w-5 h-5 mt-0.5 shrink-0 text-bright-cobalt dark:text-electric-cyan" aria-hidden="true" />
        <div className="min-w-0 space-y-2">
          <h2 className="font-semibold">{title}</h2>
          {submission.status === 'checking' && <p className="text-sm">We controleren je document. Je hoeft het niet opnieuw te uploaden.</p>}
          {submission.status === 'review' && <p className="text-sm">{submission.identity_check_required ? 'Je naam of geboortedatum op de VOG verschilt van de gegevens in je profiel. De VOG-coördinator controleert nog of het document bij jou hoort.' : 'De VOG-coördinator beoordeelt je document.'} Je hoeft het niet opnieuw te uploaden.</p>}
          {submission.status === 'awaiting_member' && <p className="text-sm">De VOG-coördinator heeft aanvullende informatie nodig. Je inzending blijft open. Lees hieronder wat er van je wordt gevraagd.</p>}
          {submission.status === 'needs_original' && <p className="text-sm">Upload hieronder de oorspronkelijke PDF uit de Berichtenbox van MijnOverheid. Gebruik geen screenshot of afdruk naar PDF. Kom je er niet uit? De VOG-coördinator kan je helpen.</p>}
          {submission.status === 'waiting_paper' && <p className="text-sm">Neem het originele papieren document mee naar de VOG-coördinator. Je scan is ontvangen; opnieuw uploaden is niet nodig.</p>}
          {submission.status === 'technical' && <p className="text-sm">De controle lukt nu niet. {submission.attempts < 3 ? 'We proberen het automatisch opnieuw.' : 'De VOG-coördinator kan de controle opnieuw starten.'} Je hoeft het document niet opnieuw te uploaden.</p>}
          {submission.status === 'approved' && <p className="text-sm">{submission.method === 'paper_original' ? 'Origineel op papier gecontroleerd.' : 'Digitaal gecontroleerd via Justid.'}</p>}
          {submission.status === 'expired' && <p className="text-sm">Je inzending is niet op tijd afgerond. Upload je VOG opnieuw om de controle te starten.</p>}
          {submission.identity_remembered && <p className="text-sm">Je bevestigde namen worden gebruikt bij volgende VOG-controles. Je naam in de ledenlijst blijft gelijk.</p>}
          {submission.note && <p className="text-sm whitespace-pre-line">{submission.note}</p>}
          {submission.status === 'approved' && vog.datum_vog && <p className="text-sm text-gray-600 dark:text-gray-300">Afgegeven op {format(vog.datum_vog, 'd MMMM yyyy')}.</p>}
          {submission.status === 'approved' && vog.status === 'valid' && vog.needs_renewal_reminder && <p className="text-sm text-amber-700 dark:text-amber-400">{renewalText}</p>}
          {vog.status === 'valid' && vog.expires_at && <p className="text-sm text-gray-600 dark:text-gray-300">{submission.status === 'approved' ? 'Je VOG is geldig tot' : 'Je eerder goedgekeurde VOG blijft geldig tot'} {format(vog.expires_at, 'd MMMM yyyy')}.</p>}
          {vog.status === 'expired' && vog.expires_at && <p className="text-sm text-gray-600 dark:text-gray-300">Je eerder goedgekeurde VOG is verlopen op {format(vog.expires_at, 'd MMMM yyyy')}.</p>}
        </div>
      </div>
    </section>
  );
}
