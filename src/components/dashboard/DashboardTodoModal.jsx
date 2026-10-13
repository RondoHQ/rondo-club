import { Component, lazy, Suspense, useEffect, useRef } from 'react';

const TodoModal = lazy(() => import('@/components/Timeline/TodoModal'));

function TaskLoadMessage({ onClose, failed = false }) {
  const dialog = useRef(null);

  useEffect(() => {
    const element = dialog.current;
    element.showModal();
    return () => element.close();
  }, []);

  return <dialog ref={dialog} onCancel={onClose} aria-labelledby="dashboard-task-load-title" className="m-auto w-full max-w-md rounded-lg bg-white p-6 text-gray-900 shadow-xl backdrop:bg-black/50 dark:bg-gray-800 dark:text-gray-100">
    <h2 id="dashboard-task-load-title" className="text-lg font-semibold">{failed ? 'Taak kon niet worden geladen' : 'Taak laden…'}</h2>
    <p role={failed ? 'alert' : 'status'} className="mt-3 text-sm">{failed ? 'Herlaad de pagina en open de taak opnieuw. Je kunt dit venster ook sluiten om het dashboard te blijven gebruiken.' : 'Het taakvenster wordt geladen.'}</p>
    <div className="mt-5 flex justify-end gap-3">
      <button type="button" className="btn-secondary" onClick={onClose}>Sluiten</button>
      {failed && <button type="button" className="btn-primary" onClick={() => window.location.reload()}>Pagina herladen</button>}
    </div>
  </dialog>;
}

class TaskErrorBoundary extends Component {
  state = { failed: false };

  static getDerivedStateFromError() {
    return { failed: true };
  }

  render() {
    // React's root onCaughtError handler retains the existing GlitchTip reporting.
    return this.state.failed ? <TaskLoadMessage failed onClose={this.props.onClose} /> : this.props.children;
  }
}

export default function DashboardTodoModal({ isOpen, ...props }) {
  if (!isOpen) return null;

  return <TaskErrorBoundary onClose={props.onClose}>
    <Suspense fallback={<TaskLoadMessage onClose={props.onClose} />}>
      <TodoModal isOpen {...props} />
    </Suspense>
  </TaskErrorBoundary>;
}
