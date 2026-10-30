import TrainingWeek from '@/pages/Training/TrainingWeek';

export default function TeamTraining({ teamId, variant = 'default' }) {
  return <TrainingWeek key={teamId} teamId={teamId} variant={variant} />;
}
