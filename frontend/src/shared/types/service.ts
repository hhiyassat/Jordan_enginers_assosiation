import { ServiceSchema } from './schema';

export interface ServiceDefinition {
  id: number;
  code: string;
  parent_code?: string | null;
  subcategory_ar?: string | null;
  subcategory_en?: string | null;
  name_ar: string;
  name_en: string;
  description_ar?: string;
  description_en?: string;
  category?: string;
  base_fee?: number | string | null;
  sla_hours?: number | null;
  currency: string;
  schema?: ServiceSchema;
  status?: 'active' | 'inactive' | 'draft';
  phase?: 1 | 2 | 3 | 4 | 5 | null;
  variant_keys?: string[];
  is_locked?: boolean;
}
