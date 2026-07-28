import React, { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { request } from '../../shared/api/http';

/**
 * PendingManualEditsTile
 * ──────────────────────
 * لوحة تظهر في admin dashboard تعرض عدد تعديلات النصوص النظامية المعلَّقة
 * والتي تحتاج مراجعة تقنية. تُخفى إذا العدد = 0. للـ admin/superuser فقط.
 *
 * البيانات من `GET /api/v1/manual-references?pending=1` (admin-only على السيرفر).
 */
export function PendingManualEditsTile() {
  const { i18n } = useTranslation();
  const isRtl = i18n.language.startsWith('ar');
  const [count, setCount] = useState<number | null>(null);

  useEffect(() => {
    request<{ references: unknown[] }>('GET', '/manual-references?pending=1')
      .then(r => setCount(r.references.length))
      .catch(() => setCount(0)); // silent fail — non-admin gets 403 → hide tile
  }, []);

  if (count === null || count === 0) return null;

  return (
    <div
      className="bg-amber-50 border-2 border-amber-300 rounded-xl p-4 flex items-center gap-3"
      dir={isRtl ? 'rtl' : 'ltr'}
      data-testid="pending-manual-edits-tile"
    >
      <span className="text-3xl">⚠️</span>
      <div>
        <p className="text-amber-900 font-semibold text-sm">
          {isRtl ? 'تعديلات معلَّقة على النصوص النظامية' : 'Pending manual-reference edits'}
        </p>
        <p className="text-amber-700 text-xs mt-1">
          {isRtl
            ? `${count} نص تم تعديله من قبل المسؤولين ويحتاج مراجعة تقنية لتحديث الكود.`
            : `${count} rule text edited by admins — needs technical review to align implementation.`}
        </p>
      </div>
    </div>
  );
}
