import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { userManagementApi } from '../api';

/**
 * User entity hooks — split out of api/platform/hooks.ts (FSD Task 13).
 */

export function useUsers() {
  return useQuery({
    queryKey: ['users', 'list'],
    queryFn:  async () => (await userManagementApi.list()).users,
    // JORD-24: keep the presence dot fresh without a page reload.
    refetchInterval: 30_000,
    staleTime: 15_000,
  });
}

export function useUpdateUser() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (vars: { id: number; data: Parameters<typeof userManagementApi.update>[1] }) =>
      userManagementApi.update(vars.id, vars.data),
    onSuccess: () => { void qc.invalidateQueries({ queryKey: ['users'] }); },
  });
}
