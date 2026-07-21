import { useEffect, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { AlertCircle, CheckCircle2, DollarSign, Download, Lock, Search } from 'lucide-react';
import { adminApi } from '../../api/client';
import { useSortableRows } from '../../utils/useSortableRows';
import { downloadCsv } from '../../utils/csv';
import { SortHeader } from '../../utils/SortHeader';
import { errorMessage } from '../../utils/errorMessage';

/**
 * ServiceFeesAdmin — JORD-85
 *
 * A single admin grid for every service's fee block. The admin picks
 * `fixed | per_unit | free`, plus amount / basis+rate / nothing, and
 * saves through PATCH /admin/services/{id}/fee. The full schema is
 * never round-tripped from this page — just the fee sub-block.
 *
 * Placeholder default (50000 JOD) seeded by ServiceFeeDefaultsSeeder
 * shows a soft-warning badge so ops can spot rows that still need
 * a real number wired in. Locked rows are read-only — admin unlocks
 * from the main services page first.
 */

type FeeType = 'fixed' | 'per_unit' | 'free';

interface FeeRow {
  id: number;
  code: string;
  parent_code: string | null;
  name_ar: string;
  name_en: string;
  status: 'active' | 'inactive' | 'draft';
  is_locked: boolean;
  fee: {
    type?: FeeType;
    amount?: number;
    currency?: string;
    basis?: string;
    rate?: number;
    source?: string;
  } | null;
}

interface Draft {
  type:     FeeType;
  amount:   string;
  currency: string;
  basis:    string;
  rate:     string;
}

function draftFromRow(row: FeeRow): Draft {
  const f = row.fee ?? {};
  return {
    type:     (f.type as FeeType) ?? 'fixed',
    amount:   f.amount !== undefined ? String(f.amount) : '',
    currency: f.currency ?? 'JOD',
    basis:    f.basis ?? '',
    rate:     f.rate !== undefined ? String(f.rate) : '',
  };
}

const PLACEHOLDER_AMOUNT = 50000;

export function ServiceFeesAdmin() {
  const { i18n } = useTranslation();
  const isRtl = i18n.language.startsWith('ar');
  const isArabic = isRtl;

  const [rows, setRows]     = useState<FeeRow[]>([]);
  const [drafts, setDrafts] = useState<Record<number, Draft>>({});
  const [loading, setLoading] = useState(true);
  const [error, setError]     = useState('');
  const [flash, setFlash]     = useState('');
  const [savingId, setSavingId] = useState<number | null>(null);
  const [query, setQuery]     = useState('');
  const [tab, setTab]         = useState<'all' | 'placeholder' | 'set'>('all');

  const load = () => {
    setLoading(true);
    adminApi.listServiceFees()
      .then(r => {
        setRows(r.fees);
        const initial: Record<number, Draft> = {};
        r.fees.forEach(row => { initial[row.id] = draftFromRow(row); });
        setDrafts(initial);
      })
      .catch(e => setError(errorMessage(e)))
      .finally(() => setLoading(false));
  };
  useEffect(load, []);

  const filtered = useMemo(() => {
    const q = query.trim().toLowerCase();
    return rows.filter(r => {
      if (q && !r.code.toLowerCase().includes(q)
            && !r.name_ar.toLowerCase().includes(q)
            && !(r.name_en?.toLowerCase().includes(q))) return false;
      if (tab === 'placeholder') {
        return r.fee?.type === 'fixed' && Number(r.fee?.amount) === PLACEHOLDER_AMOUNT;
      }
      if (tab === 'set') {
        return !(r.fee?.type === 'fixed' && Number(r.fee?.amount) === PLACEHOLDER_AMOUNT);
      }
      return true;
    });
  }, [rows, query, tab]);

  const placeholderCount = rows.filter(r => r.fee?.type === 'fixed'
    && Number(r.fee?.amount) === PLACEHOLDER_AMOUNT).length;

  const sortColumns = useMemo(() => ([
    { key: 'code'     as const, get: (r: FeeRow) => r.code },
    { key: 'name'     as const, get: (r: FeeRow) => r.name_ar },
    { key: 'type'     as const, get: (r: FeeRow) => r.fee?.type ?? '' },
    { key: 'amount'   as const, get: (r: FeeRow) => r.fee?.amount ?? r.fee?.rate ?? null },
    { key: 'currency' as const, get: (r: FeeRow) => r.fee?.currency ?? '' },
  ]), []);
  const { sorted, sortKey, sortDir, toggle } = useSortableRows(filtered, sortColumns, 'code', 'asc');

  const handleExport = () => {
    downloadCsv(
      `service-fees-${new Date().toISOString().slice(0, 10)}`,
      sorted,
      [
        { header: 'Code',     get: r => r.code },
        { header: 'Category', get: r => r.parent_code ?? '' },
        { header: 'Name AR',  get: r => r.name_ar },
        { header: 'Name EN',  get: r => r.name_en },
        { header: 'Status',   get: r => r.status },
        { header: 'Fee type', get: r => r.fee?.type ?? '' },
        { header: 'Amount',   get: r => r.fee?.amount ?? '' },
        { header: 'Basis',    get: r => r.fee?.basis ?? '' },
        { header: 'Rate',     get: r => r.fee?.rate ?? '' },
        { header: 'Currency', get: r => r.fee?.currency ?? '' },
      ],
    );
  };

  const updateDraft = (id: number, patch: Partial<Draft>) => {
    setDrafts(prev => ({ ...prev, [id]: { ...prev[id], ...patch } }));
  };

  const handleSave = async (row: FeeRow) => {
    const d = drafts[row.id];
    setError('');
    if (d.type === 'fixed' && !d.amount) {
      setError(isArabic ? 'يرجى إدخال المبلغ.' : 'Amount is required.');
      return;
    }
    if (d.type === 'per_unit' && (!d.basis || !d.rate)) {
      setError(isArabic ? 'per_unit يتطلب الأساس والسعر.' : 'per_unit requires basis + rate.');
      return;
    }
    setSavingId(row.id);
    try {
      let payload: Parameters<typeof adminApi.updateServiceFee>[1];
      if (d.type === 'fixed') {
        payload = { type: 'fixed', amount: Number(d.amount), currency: d.currency };
      } else if (d.type === 'per_unit') {
        payload = { type: 'per_unit', basis: d.basis, rate: Number(d.rate), currency: d.currency };
      } else {
        payload = { type: 'free' };
      }
      await adminApi.updateServiceFee(row.id, payload);
      setFlash((isArabic ? 'تم حفظ رسوم ' : 'Saved fee for ') + row.code);
      load();
    } catch (e) {
      setError(errorMessage(e));
    } finally {
      setSavingId(null);
    }
  };

  if (loading) return (
    <div className="flex justify-center py-20">
      <div className="animate-spin w-8 h-8 border-4 border-blue-500 border-t-transparent rounded-full" />
    </div>
  );

  return (
    <div className="max-w-6xl mx-auto px-4 py-8" dir={isRtl ? 'rtl' : 'ltr'}>
      <header className="mb-6">
        <h1 className="text-2xl font-bold text-gray-900">
          {isArabic ? 'رسوم الخدمات' : 'Service Fees'}
        </h1>
        <p className="text-sm text-gray-500 mt-1">
          {isArabic
            ? 'تعديل رسوم كل خدمة (ثابت / لكل وحدة / مجاناً). القيم الافتراضية 50000 دينار حتى تُحدَّد من قبل الإدارة.'
            : 'Set the fee per service (fixed / per_unit / free). Defaults are 50000 JOD until admin overrides them.'}
        </p>
      </header>

      {flash && (
        <div role="status" className="mb-4 bg-emerald-50 border border-emerald-200 rounded-xl p-3 text-emerald-800 text-sm flex items-center gap-2">
          <CheckCircle2 size={14} aria-hidden="true" />
          {flash}
        </div>
      )}
      {error && (
        <div role="alert" className="mb-4 bg-red-50 border border-red-200 rounded-xl p-3 text-red-700 text-sm flex items-start gap-2">
          <AlertCircle size={16} className="mt-0.5 shrink-0" aria-hidden="true" />
          <span>{error}</span>
        </div>
      )}

      {/* Filter strip */}
      <section className="bg-white border border-gray-200 rounded-xl p-3 mb-4 flex flex-wrap gap-3 items-center">
        <div className="relative flex-1 min-w-[200px]">
          <Search size={14} className="absolute top-1/2 -translate-y-1/2 start-3 text-gray-400" aria-hidden="true" />
          <input
            type="text"
            value={query}
            onChange={e => setQuery(e.target.value)}
            placeholder={isArabic ? 'ابحث بالرمز أو الاسم' : 'Search by code or name'}
            className="w-full ps-9 pe-3 py-2 text-sm border border-gray-300 rounded-lg outline-none focus:border-jea-primary"
            data-testid="fee-search"
          />
        </div>
        <div className="flex gap-1 text-xs" data-testid="fee-tabs">
          {(['all', 'placeholder', 'set'] as const).map(t => (
            <button
              key={t}
              type="button"
              onClick={() => setTab(t)}
              data-testid={`fee-tab-${t}`}
              className={`px-3 py-1.5 rounded-lg font-semibold ${
                tab === t ? 'bg-jea-primary text-white' : 'bg-gray-100 text-gray-700'
              }`}
            >
              {t === 'all' && (isArabic ? 'الكل' : 'All')}
              {t === 'placeholder' && (isArabic ? `افتراضية (${placeholderCount})` : `Placeholder (${placeholderCount})`)}
              {t === 'set' && (isArabic ? 'محدَّدة' : 'Set')}
            </button>
          ))}
        </div>
        {sorted.length > 0 && (
          <button
            type="button"
            onClick={handleExport}
            data-testid="fees-export-csv"
            className="inline-flex items-center gap-1 px-2.5 py-1 text-xs border border-gray-300 rounded hover:bg-gray-50"
            title={isArabic ? 'تصدير CSV' : 'Export CSV'}
          >
            <Download size={12} aria-hidden="true" />
            {isArabic ? 'تصدير' : 'CSV'}
          </button>
        )}
      </section>

      <section className="bg-white border border-gray-200 rounded-xl overflow-hidden">
        {sorted.length === 0 ? (
          <div className="text-center py-14 text-gray-400" data-testid="fees-empty">
            <Search size={40} className="mx-auto mb-3 opacity-40" aria-hidden="true" />
            <p className="text-sm">{isArabic ? 'لا توجد خدمات مطابقة للتصفية.' : 'No services match the current filter.'}</p>
          </div>
        ) : (
          <table className="w-full text-sm" data-testid="fees-table">
            <thead className="bg-gray-50 text-xs text-gray-600 uppercase">
              <tr>
                <SortHeader label={isArabic ? 'الرمز' : 'Code'}         k="code"     sortKey={sortKey} sortDir={sortDir} onToggle={toggle} className="!px-4" />
                <SortHeader label={isArabic ? 'الاسم' : 'Name'}         k="name"     sortKey={sortKey} sortDir={sortDir} onToggle={toggle} className="!px-4" />
                <SortHeader label={isArabic ? 'النوع' : 'Type'}         k="type"     sortKey={sortKey} sortDir={sortDir} onToggle={toggle} className="!px-4" />
                <SortHeader label={isArabic ? 'المبلغ / السعر' : 'Amount / Rate'} k="amount"   sortKey={sortKey} sortDir={sortDir} onToggle={toggle} className="!px-4" />
                <SortHeader label={isArabic ? 'العملة' : 'Currency'}    k="currency" sortKey={sortKey} sortDir={sortDir} onToggle={toggle} className="!px-4" />
                <th className="px-4 py-2"></th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-100">
              {sorted.map(row => {
                const d = drafts[row.id] ?? draftFromRow(row);
                const isPlaceholder = row.fee?.type === 'fixed'
                  && Number(row.fee?.amount) === PLACEHOLDER_AMOUNT;
                return (
                  <tr key={row.id} data-testid={`fee-row-${row.id}`}>
                    <td className="px-4 py-3 font-mono text-xs">
                      {row.code}
                      {isPlaceholder && (
                        <span
                          className="ms-1 text-[10px] px-1.5 py-0.5 rounded bg-amber-100 text-amber-800"
                          data-testid={`placeholder-badge-${row.id}`}
                        >
                          {isArabic ? 'افتراضية' : 'placeholder'}
                        </span>
                      )}
                    </td>
                    <td className="px-4 py-3 text-xs text-gray-700 max-w-[240px]">
                      {isArabic ? row.name_ar : (row.name_en || row.name_ar)}
                    </td>
                    <td className="px-4 py-3">
                      <select
                        value={d.type}
                        onChange={e => updateDraft(row.id, { type: e.target.value as FeeType })}
                        disabled={row.is_locked}
                        className="text-xs border border-gray-300 rounded px-2 py-1"
                        data-testid={`fee-type-${row.id}`}
                      >
                        <option value="fixed">fixed</option>
                        <option value="per_unit">per_unit</option>
                        <option value="free">free</option>
                      </select>
                    </td>
                    <td className="px-4 py-3">
                      {d.type === 'fixed' && (
                        <input
                          type="number"
                          min={0}
                          step="0.01"
                          value={d.amount}
                          onChange={e => updateDraft(row.id, { amount: e.target.value })}
                          disabled={row.is_locked}
                          className="w-28 text-xs border border-gray-300 rounded px-2 py-1"
                          data-testid={`fee-amount-${row.id}`}
                        />
                      )}
                      {d.type === 'per_unit' && (
                        <div className="flex gap-1">
                          <input
                            type="text"
                            value={d.basis}
                            onChange={e => updateDraft(row.id, { basis: e.target.value })}
                            disabled={row.is_locked}
                            placeholder={isArabic ? 'الأساس (area_m2…)' : 'basis (area_m2…)'}
                            className="w-28 text-xs border border-gray-300 rounded px-2 py-1"
                            data-testid={`fee-basis-${row.id}`}
                          />
                          <input
                            type="number"
                            min={0}
                            step="0.001"
                            value={d.rate}
                            onChange={e => updateDraft(row.id, { rate: e.target.value })}
                            disabled={row.is_locked}
                            placeholder={isArabic ? 'السعر' : 'rate'}
                            className="w-20 text-xs border border-gray-300 rounded px-2 py-1"
                            data-testid={`fee-rate-${row.id}`}
                          />
                        </div>
                      )}
                      {d.type === 'free' && (
                        <span className="text-xs text-gray-500">{isArabic ? 'مجاناً' : 'no charge'}</span>
                      )}
                    </td>
                    <td className="px-4 py-3">
                      {d.type !== 'free' ? (
                        <input
                          type="text"
                          maxLength={3}
                          value={d.currency}
                          onChange={e => updateDraft(row.id, { currency: e.target.value.toUpperCase() })}
                          disabled={row.is_locked}
                          className="w-16 text-xs border border-gray-300 rounded px-2 py-1 uppercase"
                          data-testid={`fee-currency-${row.id}`}
                        />
                      ) : (
                        <span className="text-xs text-gray-300">—</span>
                      )}
                    </td>
                    <td className="px-4 py-3 text-end">
                      {row.is_locked ? (
                        <span className="inline-flex items-center gap-1 text-xs text-gray-500" data-testid={`fee-locked-${row.id}`}>
                          <Lock size={12} aria-hidden="true" />
                          {isArabic ? 'مقفلة' : 'locked'}
                        </span>
                      ) : (
                        <button
                          type="button"
                          onClick={() => handleSave(row)}
                          disabled={savingId === row.id}
                          data-testid={`fee-save-${row.id}`}
                          className="inline-flex items-center gap-1 px-3 py-1.5 text-xs bg-jea-primary text-white rounded-lg hover:opacity-90 disabled:opacity-50"
                        >
                          <DollarSign size={12} aria-hidden="true" />
                          {savingId === row.id
                            ? (isArabic ? 'جارٍ…' : 'Saving…')
                            : (isArabic ? 'حفظ' : 'Save')}
                        </button>
                      )}
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        )}
      </section>
    </div>
  );
}
