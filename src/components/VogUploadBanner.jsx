import { Link } from 'react-router-dom';
import { FileCheck, ChevronRight } from 'lucide-react';

export default function VogUploadBanner() {
  return (
    <Link
      to="/profile/vog"
      className="flex min-h-11 shrink-0 items-center justify-between gap-3 border-b border-cyan-200 bg-cyan-50 px-4 py-3 text-sm font-semibold text-cyan-800 hover:bg-cyan-100 focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-cyan-700 lg:px-6 dark:border-cyan-800 dark:bg-cyan-950 dark:text-cyan-200 dark:hover:bg-cyan-900 dark:focus-visible:outline-cyan-300"
    >
      <span className="flex items-center gap-2">
        <FileCheck className="size-5 shrink-0" aria-hidden="true" />
        Upload je VOG hier
      </span>
      <ChevronRight className="size-4 shrink-0" aria-hidden="true" />
    </Link>
  );
}
