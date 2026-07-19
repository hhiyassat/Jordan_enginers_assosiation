import React from 'react';
import type { Project } from '../../types';

/**
 * Rendered above the Apply schema form when the applicant reached the
 * page via /projects/{id}/… . Shows the project's immutable fields
 * (name, contract, request, area, city, type) as read-only rows so the
 * applicant confirms which project they're filing under without being
 * able to type-fix or spoof them. The actual project ↔ application link
 * is enforced server-side via the project_id posted to /applications —
 * this block is a UX hint, not the security boundary.
 */
export function ProjectContextHeader({ project }: { project: Project }) {
  const rows: Array<[string, string | number | null | undefined]> = [
    ['اسم المشروع',  project.name_ar],
    ['Project (EN)', project.name_en],
    ['رقم العقد',    project.contract_no],
    ['رقم الطلب',    project.request_no],
    ['المساحة (م²)', project.area_m2],
    ['المدينة',      project.city],
    ['النوع',        project.type],
  ];
  const visible = rows.filter(([, v]) => v !== null && v !== undefined && v !== '');

  return (
    <div
      className="border border-blue-200 bg-blue-50/60 rounded-xl p-4"
      role="region"
      aria-label="معلومات المشروع (للقراءة فقط)"
    >
      <div className="flex items-center justify-between mb-3">
        <h2 className="text-sm font-bold text-gray-800">معلومات المشروع</h2>
        <span className="text-[10px] uppercase tracking-wide px-2 py-0.5 rounded-full bg-white border border-blue-200 text-blue-700">
          للقراءة فقط
        </span>
      </div>
      <dl className="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-2 text-sm">
        {visible.map(([label, value]) => (
          <div key={label} className="flex items-baseline gap-2">
            <dt className="text-gray-500 min-w-[110px]">{label}</dt>
            <dd className="font-semibold text-gray-900" dir="auto">{String(value)}</dd>
          </div>
        ))}
      </dl>
    </div>
  );
}
