import type { ServiceDefinition } from '../../../shared/types';
import { request } from '../../../shared/api/http';

/**
 * Services domain (applicant-facing catalog list + detail).
 * Split out of client.ts (JORD-22).
 */
export const servicesApi = {
  list: () => request<{ services: ServiceDefinition[] }>('GET', '/services'),
  get:  (code: string) => request<{ service: ServiceDefinition }>('GET', `/services/${code}`),
};
