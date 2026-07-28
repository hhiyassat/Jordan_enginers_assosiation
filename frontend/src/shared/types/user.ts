export interface Organization {
  id: number;
  name_ar: string;
  name_en: string;
  slug: string;
}

export interface User {
  id: number;
  name: string;
  email: string;
  phone?: string | null;
  role: 'applicant' | 'staff' | 'auditor' | 'admin' | 'superuser';
  organization_id: number;
  must_change_password?: boolean;
  can_manage_users?: boolean;
  is_active?: boolean;
  created_at?: string;
  presence?: 'online' | 'idle' | 'offline';
  last_seen_at?: string | null;
}

export interface Engineer {
  id: number;
  organization_id: number;
  office_user_id: number;
  name_ar: string;
  name_en?: string | null;
  membership_number: string;
  specialization?: string | null;
  phone?: string | null;
  email?: string | null;
  annual_quota_m2?: number | null;
  is_active: boolean;
  created_at: string;
  updated_at: string;
}

export interface Project {
  id: number;
  organization_id: number;
  owner_user_id: number;
  engineer_id?: number | null;
  engineer?: Pick<Engineer, 'id' | 'name_ar' | 'name_en' | 'membership_number'> | null;
  name_ar: string;
  name_en?: string | null;
  type?: string | null;
  area_m2?: number | null;
  city?: string | null;
  contract_no?: string | null;
  request_no?: string | null;
  status: 'active' | 'pending' | 'archived';
  created_at: string;
  updated_at: string;
}
