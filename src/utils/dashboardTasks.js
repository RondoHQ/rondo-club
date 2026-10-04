/** Apply a confirmed task update before refreshing the dashboard workspace. */
export async function syncDashboardTask(queryClient, task) {
  // An older in-flight workspace response must not restore the previous status.
  await queryClient.cancelQueries({ queryKey: ['role-dashboard'] });
  queryClient.setQueriesData({ queryKey: ['role-dashboard'] }, workspace => {
    if (!workspace?.tasks) return workspace;
    return {
      ...workspace,
      tasks: workspace.tasks.flatMap(current => {
        if (current.id !== task.id) return [current];
        return task.status === 'open' ? [{ ...current, ...task }] : [];
      }),
    };
  });
}
