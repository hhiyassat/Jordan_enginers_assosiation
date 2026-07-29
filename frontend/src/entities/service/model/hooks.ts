import { useQuery } from '@tanstack/react-query';
import { servicesApi } from '../api';

/**
 * Service entity hooks — split out of api/jea/hooks.ts (FSD Task 10).
 */

export function useServices() {
  return useQuery({
    queryKey: ['services', 'list'],
    queryFn:  async () => (await servicesApi.list()).services,
  });
}

export function useService(code: string | undefined) {
  return useQuery({
    queryKey: ['services', 'detail', code],
    queryFn:  async () => (await servicesApi.get(code!)).service,
    enabled: !!code,
  });
}
