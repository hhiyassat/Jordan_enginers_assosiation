import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { applicationsApi } from '../api';

/**
 * Application entity hooks — split out of api/jea/hooks.ts (FSD Task 9).
 */

export function useMyApplications() {
  return useQuery({
    queryKey: ['applications', 'mine'],
    queryFn:  async () => (await applicationsApi.list()).applications,
  });
}

export function useApplication(id: number | undefined) {
  return useQuery({
    queryKey: ['applications', 'detail', id],
    queryFn:  () => applicationsApi.get(id!),
    enabled: id !== undefined,
  });
}

export function useReviewQueue() {
  return useQuery({
    queryKey: ['review', 'queue'],
    queryFn:  async () => (await applicationsApi.reviewQueue()).applications,
  });
}

export function useCreateApplication() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (vars: { service_code: string; data: Record<string, unknown>; project_id?: number }) =>
      applicationsApi.create(vars.service_code, vars.data, vars.project_id),
    onSuccess: () => { void qc.invalidateQueries({ queryKey: ['applications'] }); },
  });
}

export function useSubmitApplication() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => applicationsApi.submit(id),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: ['applications'] });
      void qc.invalidateQueries({ queryKey: ['review', 'queue'] });
      void qc.invalidateQueries({ queryKey: ['admin', 'applications'] });
    },
  });
}

export function useClaimApplication() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => applicationsApi.claim(id),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: ['review'] });
      void qc.invalidateQueries({ queryKey: ['applications'] });
    },
  });
}
