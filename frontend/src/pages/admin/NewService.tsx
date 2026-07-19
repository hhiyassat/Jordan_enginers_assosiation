import React, { useRef, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { adminApi } from '../../api/client';
import { DynamicForm } from '../../engine/DynamicForm';
import { DocumentPreviewCard } from '../../engine/DocumentPreviewCard';
import type { ServiceSchema } from '../../types';
import { normalizeSaveError, type ApiError } from './saveErrorHelpers';

// ── Types ─────────────────────────────────────────────────────────────────────

type InputMode = 'text' | 'file';
type Tab       = 'input' | 'schema' | 'preview';
type Verdict = 'sahih' | 'fasid' | 'batil';
type Mode    = 'azimah' | 'rukhsa';

interface Blocker {
  type:       string;
  severity:   'high' | 'medium' | 'low';
  decision:   'halt_generation' | 'warn';
  message:    string;
  resolution: string;
}

interface ValidationReport {
  verdict:      Verdict;
  can_publish:  boolean;
  can_repair:   boolean;
  batil_nodes:  string[];
  fasid_nodes:  string[];
  total_issues: number;
  mode:         Mode;
}

interface GenerationAudit {
  generated_at:       string;
  duration_seconds:   number;
  model:              string;
  mode:               Mode;
  verdict:            Verdict;
  tokens_used:        number;
  hukm_ir_extracted:  boolean;
  hukm_ir_count:      number;
  schema_stats:       { fields: number; workflow_stages: number; documents: number; has_fee: boolean; has_certificate: boolean };
  traceability:       { total_nodes: number; traced_nodes: number; coverage_pct: number; fully_traced: boolean };
  blockers_detected:  number;
  fatal_blockers:     number;
  validation_issues:  number;
}

// ── Tabs ──────────────────────────────────────────────────────────────────────

const TABS: { id: Tab; label: string }[] = [
  { id: 'input',   label: '1 · الوصف والمتطلبات' },
  { id: 'schema',  label: '2 · المخطط JSON' },
  { id: 'preview', label: '3 · معاينة النموذج' },
];

// ── Verdict helpers ───────────────────────────────────────────────────────────

const VERDICT_CONFIG: Record<Verdict, { color: string; bg: string; border: string; icon: string; label: string; desc: string }> = {
  sahih: {
    color: 'text-green-700', bg: 'bg-green-50', border: 'border-green-300',
    icon: '✅', label: 'صحيح',
    desc: 'المخطط متوافق ومتتبَّع بالكامل — جاهز للنشر',
  },
  fasid: {
    color: 'text-amber-700', bg: 'bg-amber-50', border: 'border-amber-300',
    icon: '⚠️', label: 'فاسد',
    desc: 'يوجد نقص قابل للإصلاح — راجع التفاصيل وعدّل المخطط قبل التفعيل',
  },
  batil: {
    color: 'text-red-700', bg: 'bg-red-50', border: 'border-red-300',
    icon: '❌', label: 'باطل',
    desc: 'يوجد خلل جوهري — لا يمكن تفعيل الخدمة قبل الإصلاح',
  },
};

// ── Component ─────────────────────────────────────────────────────────────────

export function NewService() {
  const navigate = useNavigate();
  const { t, i18n } = useTranslation();
  const isRtl = i18n.language.startsWith('ar');

  // Input mode
  const [inputMode, setInputMode]     = useState<InputMode>('text');
  const [uploadFile, setUploadFile]   = useState<File | null>(null);
  const [nfrFile, setNfrFile]         = useState<File | null>(null);
  const [isDragging, setIsDragging]   = useState(false);
  const [isDraggingNfr, setIsDraggingNfr] = useState(false);
  const fileInputRef                  = useRef<HTMLInputElement>(null);
  const nfrInputRef                   = useRef<HTMLInputElement>(null);

  // Input
  const [srsText, setSrsText]         = useState('');
  const [serviceCode, setServiceCode] = useState('');
  const [mode, setMode]               = useState<Mode>('azimah');

  // Generation
  const [generating, setGenerating]         = useState(false);
  const [genError, setGenError]             = useState('');
  const [tokensUsed, setTokensUsed]         = useState<number | null>(null);
  const [verdict, setVerdict]               = useState<Verdict | null>(null);
  const [validationReport, setValidReport]  = useState<ValidationReport | null>(null);
  const [generationAudit, setAudit]         = useState<GenerationAudit | null>(null);
  const [blockers, setBlockers]             = useState<Blocker[]>([]);
  const [hukmIRCount, setHukmIRCount]       = useState<number | null>(null);

  // Schema editor
  const [schemaJson, setSchemaJson]     = useState('');
  const [jsonError, setJsonError]       = useState('');
  const [parsedSchema, setParsedSchema] = useState<ServiceSchema | null>(null);
  const [saveCode, setSaveCode]         = useState('');

  // Save
  const [saving, setSaving]       = useState(false);
  const [saveError, setSaveError] = useState('');
  // Per-field errors from the backend (dotted keys → human message). Kept
  // separate from saveError so both a summary and the specific field list
  // can render together — the top-level "المخطط لا يتوافق مع بنية ESP v2"
  // is useless without the specific field that broke.
  const [saveFieldErrors, setSaveFieldErrors] = useState<Record<string, string>>({});

  // UI panels
  const [showAudit, setShowAudit]       = useState(false);
  const [showIssues, setShowIssues]     = useState(true);
  const [tab, setTab]                   = useState<Tab>('input');

  // ── Handlers ───────────────────────────────────────────────────────────────

  const handleGenerate = async () => {
    if (inputMode === 'file' && !uploadFile) {
      setGenError('يرجى اختيار ملف SRS (DOCX أو PDF أو TXT)');
      return;
    }
    if (inputMode === 'text' && (!srsText.trim() || srsText.trim().length < 50)) {
      setGenError('يرجى إدخال وصف تفصيلي للخدمة (50 حرف على الأقل)');
      return;
    }
    setGenerating(true);
    setGenError('');
    setVerdict(null);
    setValidReport(null);
    setBlockers([]);
    setAudit(null);

    try {
      const r = inputMode === 'file' && uploadFile
        ? await adminApi.generateSchemaFromFile(uploadFile, nfrFile, serviceCode.trim() || undefined, mode)
        : await adminApi.generateSchema(srsText.trim(), serviceCode.trim() || undefined, mode);

      // Hukm response fields
      if (r.verdict)            setVerdict(r.verdict as Verdict);
      if (r.validation_report)  setValidReport(r.validation_report as unknown as ValidationReport);
      if (r.generation_audit)   setAudit(r.generation_audit as unknown as GenerationAudit);
      if (r.blockers)           setBlockers(r.blockers as Blocker[]);
      if (r.hukm_ir)            setHukmIRCount(Array.isArray(r.hukm_ir) ? r.hukm_ir.length : null);
      setTokensUsed(r.tokens_used);

      const pretty = JSON.stringify(r.schema, null, 2);
      setSchemaJson(pretty);
      setParsedSchema(r.schema as unknown as ServiceSchema);
      setSaveCode((r.schema as unknown as ServiceSchema).service_code ?? '');
      setJsonError('');
      setTab('schema');
    } catch (e: unknown) {
      const err = e as Error & { blockers?: Blocker[]; verdict?: Verdict };
      setGenError(err.message);
      // Blocker-halt: response came back as 422 with blockers
      if (err.blockers) setBlockers(err.blockers);
      if (err.verdict)  setVerdict(err.verdict as Verdict);
    } finally {
      setGenerating(false);
    }
  };

  const handleSchemaChange = (value: string) => {
    setSchemaJson(value);
    setJsonError('');
    try {
      const parsed = JSON.parse(value);
      setParsedSchema(parsed as ServiceSchema);
    } catch {
      setParsedSchema(null);
      setJsonError('JSON غير صالح — تحقق من الصياغة');
    }
  };

  const handleSave = async (status: 'draft' | 'active') => {
    if (!parsedSchema) {
      setJsonError('أصلح أخطاء JSON أولاً');
      setTab('schema');
      return;
    }
    // Block publishing if verdict is batil
    if (status === 'active' && verdict === 'batil') {
      setSaveError('لا يمكن تفعيل خدمة بحكم "باطل" — أصلح المشاكل أولاً');
      return;
    }

    setSaving(true);
    setSaveError('');
    setSaveFieldErrors({});
    const code = saveCode.trim() || parsedSchema.service_code;
    if (!code) {
      setSaveError('كود الخدمة مطلوب — أدخله في الحقل أدناه');
      setSaving(false);
      return;
    }

    try {
      await adminApi.saveService({
        code,
        name_ar:  parsedSchema.name_ar,
        name_en:  parsedSchema.name_en,
        currency: (parsedSchema.fee as Record<string, unknown>)?.currency as string ?? 'JOD',
        schema:   parsedSchema as unknown as Record<string, unknown>,
        status,
      });
      navigate('/admin/services', { state: { created: code } });
    } catch (err: unknown) {
      const { summary, fieldErrors } = normalizeSaveError(err as ApiError, code);
      setSaveError(summary);
      setSaveFieldErrors(fieldErrors);
      window.scrollTo({ top: 0, behavior: 'smooth' });
    } finally {
      setSaving(false);
    }
  };

  // ── Sub-components ─────────────────────────────────────────────────────────

  const VerdictBadge = ({ v }: { v: Verdict }) => {
    const cfg = VERDICT_CONFIG[v];
    return (
      <span className={`inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold ${cfg.bg} ${cfg.color} border ${cfg.border}`}>
        {cfg.icon} {cfg.label}
      </span>
    );
  };

  const BlockerPanel = ({ items }: { items: Blocker[] }) => {
    if (items.length === 0) return null;
    const fatal = items.filter(b => b.decision === 'halt_generation');
    const warns = items.filter(b => b.decision === 'warn');
    return (
      <div className="space-y-2">
        {fatal.length > 0 && (
          <div className="bg-red-50 border border-red-200 rounded-xl p-4 space-y-3">
            <p className="text-sm font-semibold text-red-700">🚫 موانع تمنع التوليد ({fatal.length})</p>
            {fatal.map((b, i) => (
              <div key={i} className="bg-white border border-red-200 rounded-lg p-3 text-sm space-y-1">
                <p className="font-medium text-red-700">{b.message}</p>
                <p className="text-red-500 text-xs">الحل: {b.resolution}</p>
              </div>
            ))}
          </div>
        )}
        {warns.length > 0 && (
          <div className="bg-amber-50 border border-amber-200 rounded-xl p-4 space-y-3">
            <p className="text-sm font-semibold text-amber-700">⚠️ تحذيرات ({warns.length})</p>
            {warns.map((b, i) => (
              <div key={i} className="bg-white border border-amber-200 rounded-lg p-3 text-sm space-y-1">
                <p className="font-medium text-amber-700">{b.message}</p>
                <p className="text-amber-600 text-xs">الاقتراح: {b.resolution}</p>
              </div>
            ))}
          </div>
        )}
      </div>
    );
  };

  const ValidationPanel = ({ report }: { report: ValidationReport }) => {
    const cfg = VERDICT_CONFIG[report.verdict];
    return (
      <div className={`rounded-xl border ${cfg.border} ${cfg.bg} p-4 space-y-3`}>
        {/* Verdict header */}
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-2">
            <VerdictBadge v={report.verdict} />
            <span className={`text-sm ${cfg.color}`}>{cfg.desc}</span>
          </div>
          {report.total_issues > 0 && (
            <button
              onClick={() => setShowIssues(v => !v)}
              className={`text-xs underline ${cfg.color}`}
            >
              {showIssues ? 'إخفاء التفاصيل' : `عرض ${report.total_issues} مشكلة`}
            </button>
          )}
        </div>

        {/* Issue lists */}
        {showIssues && (
          <>
            {report.batil_nodes.length > 0 && (
              <div className="space-y-1">
                <p className="text-xs font-semibold text-red-600">عقد باطلة (يجب إصلاحها):</p>
                <ul className="space-y-0.5">
                  {report.batil_nodes.map((n, i) => (
                    <li key={i} className="text-xs font-mono text-red-600 bg-red-100 rounded px-2 py-0.5">❌ {n}</li>
                  ))}
                </ul>
              </div>
            )}
            {report.fasid_nodes.length > 0 && (
              <div className="space-y-1">
                <p className="text-xs font-semibold text-amber-600">عقد فاسدة (قابلة للإصلاح):</p>
                <ul className="space-y-0.5">
                  {report.fasid_nodes.map((n, i) => (
                    <li key={i} className="text-xs font-mono text-amber-700 bg-amber-100 rounded px-2 py-0.5">⚠️ {n}</li>
                  ))}
                </ul>
              </div>
            )}
          </>
        )}
      </div>
    );
  };

  const AuditPanel = ({ audit }: { audit: GenerationAudit }) => (
    <div className="bg-gray-50 border border-gray-200 rounded-xl p-4 text-xs space-y-3">
      <div className="flex items-center justify-between">
        <p className="font-semibold text-gray-600">سجل التوليد</p>
        <button onClick={() => setShowAudit(false)} className="text-gray-400 hover:text-gray-600">✕</button>
      </div>
      <div className="grid grid-cols-2 gap-x-6 gap-y-1.5 text-gray-600" dir="ltr">
        <span className="text-gray-400">Mode</span>          <span className="font-mono">{audit.mode}</span>
        <span className="text-gray-400">Verdict</span>       <span className="font-mono">{audit.verdict}</span>
        <span className="text-gray-400">Duration</span>      <span className="font-mono">{audit.duration_seconds}s</span>
        <span className="text-gray-400">Tokens</span>        <span className="font-mono">{audit.tokens_used.toLocaleString()}</span>
        <span className="text-gray-400">HukmIR extracted</span> <span className="font-mono">{audit.hukm_ir_extracted ? `✓ (${audit.hukm_ir_count} reqs)` : '✗'}</span>
        <span className="text-gray-400">Fields</span>        <span className="font-mono">{audit.schema_stats.fields}</span>
        <span className="text-gray-400">Stages</span>        <span className="font-mono">{audit.schema_stats.workflow_stages}</span>
        <span className="text-gray-400">Documents</span>     <span className="font-mono">{audit.schema_stats.documents}</span>
        <span className="text-gray-400">Traceability</span>  <span className={`font-mono font-semibold ${audit.traceability.fully_traced ? 'text-green-600' : 'text-amber-600'}`}>
          {audit.traceability.traced_nodes}/{audit.traceability.total_nodes} ({audit.traceability.coverage_pct}%)
        </span>
        <span className="text-gray-400">Blockers</span>      <span className="font-mono">{audit.blockers_detected} ({audit.fatal_blockers} fatal)</span>
        <span className="text-gray-400">Issues</span>        <span className="font-mono">{audit.validation_issues}</span>
      </div>
    </div>
  );

  const SaveActions = () => {
    const isBatil = verdict === 'batil';
    return (
      <div className="flex gap-3 flex-wrap">
        <button
          onClick={() => handleSave('draft')}
          disabled={saving || !!jsonError || !parsedSchema || !saveCode.trim()}
          className="px-5 py-2.5 border-2 border-navy text-navy rounded-xl hover:bg-blue-50 disabled:opacity-50 text-sm font-medium"
        >
          {saving ? 'جارٍ الحفظ...' : '💾 حفظ كمسودة'}
        </button>
        <div className="relative group">
          <button
            onClick={() => handleSave('active')}
            disabled={saving || !!jsonError || !parsedSchema || !saveCode.trim() || isBatil}
            className={`px-5 py-2.5 rounded-xl text-sm font-medium disabled:opacity-50 ${
              isBatil
                ? 'bg-gray-300 text-gray-500 cursor-not-allowed'
                : 'bg-green-600 text-white hover:bg-green-700'
            }`}
          >
            {saving ? 'جارٍ...' : '🚀 حفظ وتفعيل'}
          </button>
          {isBatil && (
            <div className="absolute bottom-full mb-1 right-0 bg-gray-800 text-white text-xs rounded px-2 py-1 whitespace-nowrap opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none">
              مطلوب حكم صحيح أو فاسد للتفعيل
            </div>
          )}
        </div>
      </div>
    );
  };

  // ── Render ─────────────────────────────────────────────────────────────────

  return (
    <div className="max-w-5xl mx-auto px-4 py-8" dir={isRtl ? 'rtl' : 'ltr'}>

      {/* Header */}
      <div className="mb-6">
        <button onClick={() => navigate('/admin/services')} className="text-sm text-gray-400 hover:text-gray-600 mb-2">
          {t('editService.backToServices')}
        </button>
        <h1 className="text-2xl font-bold text-gray-900">{t('newService.title')}</h1>
        <p className="text-gray-500 text-sm mt-1">{t('newService.subtitle')}</p>
      </div>

      {/* Global save error — top-level message + specific field errors */}
      {(saveError || Object.keys(saveFieldErrors).length > 0) && (
        <div
          role="alert"
          className="mb-4 bg-red-50 border border-red-300 rounded-xl p-4 text-red-700 text-sm"
        >
          {saveError && <p className="font-semibold">❌ {saveError}</p>}
          {Object.keys(saveFieldErrors).length > 0 && (
            <ul className="mt-2 space-y-1 list-disc pr-5">
              {Object.entries(saveFieldErrors).map(([field, msg]) => (
                <li key={field}>
                  <code className="text-[11px] text-red-500 bg-red-100 rounded px-1 py-0.5" dir="ltr">
                    {field}
                  </code>
                  <span className="mr-2">{msg}</span>
                </li>
              ))}
            </ul>
          )}
        </div>
      )}

      {/* Tab bar */}
      <div className="flex border-b border-gray-200 mb-6 gap-1">
        {TABS.map(t => (
          <button
            key={t.id}
            onClick={() => setTab(t.id)}
            disabled={t.id !== 'input' && !schemaJson}
            className={`px-4 py-2.5 text-sm font-medium rounded-t-lg transition-colors disabled:opacity-40 disabled:cursor-not-allowed ${
              tab === t.id
                ? 'bg-white border border-b-white border-gray-200 -mb-px text-navy'
                : 'text-gray-500 hover:text-gray-700'
            }`}
          >
            {t.label}
            {t.id === 'schema' && verdict && (
              <span className="mr-2">
                <VerdictBadge v={verdict} />
              </span>
            )}
          </button>
        ))}

        <div className="mr-auto flex items-center gap-3 px-2">
          {tokensUsed && (
            <span className="text-xs text-gray-400">{tokensUsed.toLocaleString()} token</span>
          )}
          {generationAudit && (
            <button
              onClick={() => setShowAudit(v => !v)}
              className="text-xs text-gray-400 hover:text-gray-600 underline"
            >
              سجل التوليد
            </button>
          )}
        </div>
      </div>

      {/* Floating audit panel */}
      {showAudit && generationAudit && (
        <div className="mb-4">
          <AuditPanel audit={generationAudit} />
        </div>
      )}

      {/* ── Tab 1: SRS Input ─────────────────────────────────────── */}
      {tab === 'input' && (
        <div className="space-y-5">

          {/* Info banner */}
          <div className="bg-blue-50 border border-blue-200 rounded-xl p-4 text-sm text-blue-700 space-y-1">
            <p><strong>كيف يعمل:</strong> اكتب وصفاً تفصيلياً أو الصق نص SRS — سيُولِّد Claude مخططاً JSON كاملاً.</p>
            <p className="text-xs text-blue-600">
              كل عنصر في المخطط سيحمل <code className="bg-blue-100 px-1 rounded">requirement_source</code> مرتبطاً بنص SRS.
              المخطط سيُصنَّف تلقائياً: <strong>صحيح</strong> / <strong>فاسد</strong> / <strong>باطل</strong>.
            </p>
          </div>

          {/* Mode selector */}
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1.5">وضع التوليد</label>
            <div className="flex gap-3">
              {(['azimah', 'rukhsa'] as Mode[]).map(m => (
                <button
                  key={m}
                  onClick={() => setMode(m)}
                  className={`px-4 py-2 rounded-lg text-sm border transition-colors ${
                    mode === m
                      ? m === 'azimah'
                        ? 'bg-navy text-white border-navy'
                        : 'bg-amber-500 text-white border-amber-500'
                      : 'bg-white text-gray-600 border-gray-300 hover:border-gray-400'
                  }`}
                >
                  {m === 'azimah' ? '🏛 عزيمة — إنتاج' : '🔬 رخصة — نموذج أولي'}
                </button>
              ))}
            </div>
            <p className="text-xs text-gray-400 mt-1">
              {mode === 'azimah'
                ? 'جميع القواعد مُطبَّقة — الموانع الحرجة توقف التوليد'
                : 'بعض القواعد قابلة للتجاوز — للعروض والنماذج فقط، غير مؤهل للنشر'}
            </p>
          </div>

          {/* Service code */}
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1.5">كود الخدمة (اختياري)</label>
            <input
              value={serviceCode}
              onChange={e => setServiceCode(e.target.value)}
              className="w-full max-w-xs border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono focus:ring-2 focus:ring-blue-500 focus:outline-none"
              placeholder="مثال: ENG-REG-001"
              maxLength={20}
              dir="ltr"
            />
            <p className="text-xs text-gray-400 mt-1">إذا تركته فارغاً سيتولى الذكاء الاصطناعي اختياره — الحد الأقصى 20 حرف</p>
          </div>

          {/* Input mode toggle */}
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1.5">مصدر SRS</label>
            <div className="flex gap-2">
              {([['text', '✏️ نص مباشر'], ['file', '📎 رفع ملف']] as [InputMode, string][]).map(([m, label]) => (
                <button
                  key={m}
                  onClick={() => { setInputMode(m); setGenError(''); setBlockers([]); }}
                  className={`px-4 py-2 rounded-lg text-sm border transition-colors ${
                    inputMode === m
                      ? 'bg-navy text-white border-navy'
                      : 'bg-white text-gray-600 border-gray-300 hover:border-gray-400'
                  }`}
                >
                  {label}
                </button>
              ))}
            </div>
          </div>

          {/* SRS text (text mode) */}
          {inputMode === 'text' && (
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1.5">
                وصف الخدمة / نص SRS <span className="text-red-500">*</span>
              </label>
              <textarea
                value={srsText}
                onChange={e => { setSrsText(e.target.value); setGenError(''); setBlockers([]); }}
                className="w-full border border-gray-300 rounded-xl p-4 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none resize-none"
                rows={14}
                placeholder={`مثال:\nخدمة تسجيل المهندسين الجدد في نقابة المهندسين الأردنيين\n\nالهدف: تمكين خريجي الهندسة من تسجيل أنفسهم في النقابة إلكترونياً.\n\nالبيانات المطلوبة:\n- الاسم الكامل (عربي وإنجليزي)\n- رقم الهوية الوطنية\n- التخصص الهندسي\n- اسم الجامعة وسنة التخرج\n- رقم الهاتف والبريد الإلكتروني\n\nمراحل المراجعة:\n1. المراجعة الأولية - موظف إداري (24 ساعة)\n2. التحقق الفني - مدقق تقني (48 ساعة)\n\nالرسوم: 50 دينار أردني ثابت\n\nالمستندات: صورة الهوية، شهادة التخرج، شهادة معادلة (للخريجين من خارج الأردن)\n\nالشهادة: بطاقة عضوية صالحة لسنة واحدة`}
              />
              <p className="text-xs text-gray-400 mt-1">
                {srsText.length} حرف — الحد الأدنى 50، الأقصى 20,000
              </p>
            </div>
          )}

          {/* File upload (file mode) */}
          {inputMode === 'file' && (
            <div className="space-y-4">

              {/* Functional SRS */}
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1.5">
                  وثيقة المتطلبات الوظيفية (SRS) <span className="text-red-500">*</span>
                  <span className="font-normal text-gray-400 mr-1">— DOCX · PDF · TXT (حد أقصى 10 MB)</span>
                </label>
                <div
                  onClick={() => fileInputRef.current?.click()}
                  onDragOver={e => { e.preventDefault(); setIsDragging(true); }}
                  onDragLeave={() => setIsDragging(false)}
                  onDrop={e => {
                    e.preventDefault();
                    setIsDragging(false);
                    const f = e.dataTransfer.files[0];
                    if (f) { setUploadFile(f); setGenError(''); setBlockers([]); }
                  }}
                  className={`w-full border-2 border-dashed rounded-xl p-8 text-center cursor-pointer transition-colors ${
                    isDragging
                      ? 'border-blue-400 bg-blue-50'
                      : uploadFile
                        ? 'border-green-400 bg-green-50'
                        : 'border-gray-300 bg-gray-50 hover:border-gray-400 hover:bg-gray-100'
                  }`}
                >
                  {uploadFile ? (
                    <div className="space-y-1">
                      <p className="text-2xl">📄</p>
                      <p className="text-sm font-medium text-green-700">{uploadFile.name}</p>
                      <p className="text-xs text-green-600">{(uploadFile.size / 1024).toFixed(1)} KB — انقر للتغيير</p>
                    </div>
                  ) : (
                    <div className="space-y-2">
                      <p className="text-2xl">📋</p>
                      <p className="text-sm text-gray-600">اسحب وثيقة SRS هنا أو انقر للاختيار</p>
                      <p className="text-xs text-gray-400">DOCX · PDF · TXT</p>
                    </div>
                  )}
                </div>
                <input
                  ref={fileInputRef}
                  type="file"
                  accept=".docx,.doc,.pdf,.txt"
                  className="hidden"
                  onChange={e => {
                    const f = e.target.files?.[0];
                    if (f) { setUploadFile(f); setGenError(''); setBlockers([]); }
                    e.target.value = '';
                  }}
                />
                {uploadFile && (
                  <button onClick={() => setUploadFile(null)} className="mt-1.5 text-xs text-red-500 hover:text-red-700">
                    × إزالة الملف
                  </button>
                )}
              </div>

              {/* NFR document */}
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1.5">
                  وثيقة المتطلبات غير الوظيفية (NFR)
                  <span className="font-normal text-gray-400 mr-1">— اختياري · DOCX · PDF · TXT</span>
                </label>
                <div
                  onClick={() => nfrInputRef.current?.click()}
                  onDragOver={e => { e.preventDefault(); setIsDraggingNfr(true); }}
                  onDragLeave={() => setIsDraggingNfr(false)}
                  onDrop={e => {
                    e.preventDefault();
                    setIsDraggingNfr(false);
                    const f = e.dataTransfer.files[0];
                    if (f) setNfrFile(f);
                  }}
                  className={`w-full border-2 border-dashed rounded-xl p-8 text-center cursor-pointer transition-colors ${
                    isDraggingNfr
                      ? 'border-purple-400 bg-purple-50'
                      : nfrFile
                        ? 'border-purple-400 bg-purple-50'
                        : 'border-gray-300 bg-gray-50 hover:border-gray-400 hover:bg-gray-100'
                  }`}
                >
                  {nfrFile ? (
                    <div className="space-y-1">
                      <p className="text-2xl">📑</p>
                      <p className="text-sm font-medium text-purple-700">{nfrFile.name}</p>
                      <p className="text-xs text-purple-600">{(nfrFile.size / 1024).toFixed(1)} KB — انقر للتغيير</p>
                    </div>
                  ) : (
                    <div className="space-y-2">
                      <p className="text-2xl">📑</p>
                      <p className="text-sm text-gray-600">اسحب وثيقة NFR هنا أو انقر للاختيار</p>
                      <p className="text-xs text-gray-400">اختياري — سيُدمج مع SRS تلقائياً</p>
                    </div>
                  )}
                </div>
                <input
                  ref={nfrInputRef}
                  type="file"
                  accept=".docx,.doc,.pdf,.txt"
                  className="hidden"
                  onChange={e => {
                    const f = e.target.files?.[0];
                    if (f) setNfrFile(f);
                    e.target.value = '';
                  }}
                />
                {nfrFile && (
                  <button onClick={() => setNfrFile(null)} className="mt-1.5 text-xs text-red-500 hover:text-red-700">
                    × إزالة الملف
                  </button>
                )}
              </div>

            </div>
          )}

          {/* Blocker panel (shown when blocked before generation) */}
          {blockers.length > 0 && <BlockerPanel items={blockers} />}

          {genError && (
            <div className="bg-red-50 border border-red-200 rounded-xl p-4 text-red-700 text-sm">
              {genError}
            </div>
          )}

          <button
            onClick={handleGenerate}
            disabled={generating || (inputMode === 'text' ? srsText.trim().length < 50 : !uploadFile)}
            className="flex items-center gap-2 px-6 py-3 bg-navy text-white rounded-xl hover:bg-blue-800 disabled:opacity-50 text-sm font-medium"
          >
            {generating ? (
              <>
                <span className="animate-spin w-4 h-4 border-2 border-white border-t-transparent rounded-full" />
                جارٍ الاستخراج والتوليد... قد يستغرق 15–40 ثانية
              </>
            ) : (
              <>✨ توليد المخطط بالذكاء الاصطناعي</>
            )}
          </button>

          {hukmIRCount !== null && (
            <p className="text-xs text-gray-500">
              🔍 استُخرج {hukmIRCount} متطلب من SRS كـ HukmIR قبل التوليد
            </p>
          )}
        </div>
      )}

      {/* ── Tab 2: Schema JSON Editor ────────────────────────────── */}
      {tab === 'schema' && (
        <div className="space-y-4">

          {/* Verdict + validation report */}
          {validationReport && <ValidationPanel report={validationReport} />}

          {/* Blockers (warnings) */}
          {blockers.filter(b => b.decision === 'warn').length > 0 && (
            <BlockerPanel items={blockers.filter(b => b.decision === 'warn')} />
          )}

          <div className="flex items-center justify-between">
            <p className="text-sm text-gray-600">
              راجع المخطط وعدّل ما يلزم — كل عنصر يجب أن يحمل <code className="bg-gray-100 px-1 rounded text-xs">requirement_source</code>
            </p>
            <button
              onClick={() => setTab('preview')}
              disabled={!parsedSchema}
              className="text-sm text-blue-600 hover:text-blue-800 disabled:opacity-40"
            >
              معاينة النموذج →
            </button>
          </div>

          {jsonError && (
            <div className="bg-red-50 border border-red-200 rounded-lg p-3 text-red-600 text-xs font-mono">
              {jsonError}
            </div>
          )}

          <textarea
            value={schemaJson}
            onChange={e => handleSchemaChange(e.target.value)}
            className={`w-full border rounded-xl p-4 text-xs font-mono leading-relaxed focus:ring-2 focus:ring-blue-500 focus:outline-none resize-none bg-gray-900 text-green-400 ${
              jsonError ? 'border-red-400' : 'border-gray-700'
            }`}
            rows={30}
            dir="ltr"
            spellCheck={false}
          />

          {saveError && (
            <div className="bg-red-50 border border-red-200 rounded-xl p-4 text-red-700 text-sm">
              ❌ {saveError}
            </div>
          )}

          {/* Code field */}
          <div className="border border-gray-200 rounded-xl p-4 bg-gray-50 space-y-2">
            <label className="block text-xs font-medium text-gray-600">
              كود الخدمة <span className="text-red-500">*</span>
              <span className="font-normal text-gray-400 mr-1">— يجب أن يكون فريداً</span>
            </label>
            <input
              value={saveCode}
              onChange={e => { setSaveCode(e.target.value.toUpperCase()); setSaveError(''); }}
              className="w-full max-w-xs border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono focus:ring-2 focus:ring-blue-500 focus:outline-none bg-white"
              placeholder="مثال: ENG-REG-001"
              dir="ltr"
            />
          </div>

          <SaveActions />
        </div>
      )}

      {/* ── Tab 3: Live Form Preview ─────────────────────────────── */}
      {tab === 'preview' && (
        <div className="space-y-4">
          {!parsedSchema ? (
            <div className="text-center py-20 text-gray-400">
              <p>لا يوجد مخطط صالح للمعاينة — تحقق من صياغة JSON في التبويب السابق</p>
            </div>
          ) : (
            <>
              {/* Verdict reminder */}
              {verdict && (
                <div className={`rounded-xl border p-3 flex items-center gap-2 ${VERDICT_CONFIG[verdict].bg} ${VERDICT_CONFIG[verdict].border}`}>
                  <VerdictBadge v={verdict} />
                  <span className={`text-sm ${VERDICT_CONFIG[verdict].color}`}>{VERDICT_CONFIG[verdict].desc}</span>
                </div>
              )}

              {/* Service header */}
              <div className="bg-white rounded-xl border border-gray-200 p-5">
                <h2 className="text-lg font-bold text-gray-900">{parsedSchema.name_ar}</h2>
                <p className="text-sm text-gray-500 mt-0.5">{parsedSchema.name_en}</p>
                <div className="flex gap-3 mt-3 text-xs text-gray-500">
                  <span>🏷 {parsedSchema.service_code}</span>
                  <span>💰 {(() => {
                    const fee = parsedSchema.fee as Record<string, unknown>;
                    return fee?.type === 'fixed'
                      ? `${fee?.amount} ${fee?.currency ?? 'JOD'}`
                      : String(fee?.type ?? '');
                  })()}</span>
                  <span>🔄 {parsedSchema.workflow?.stages?.length ?? 0} مراحل</span>
                </div>
              </div>

              {/* Workflow */}
              {(parsedSchema.workflow?.stages?.length ?? 0) > 0 && (
                <div className="bg-white rounded-xl border border-gray-200 p-5">
                  <h3 className="text-sm font-semibold text-gray-800 mb-3">مسار المراجعة</h3>
                  <div className="flex flex-wrap gap-2">
                    {parsedSchema.workflow.stages.map((stage, i) => (
                      <div key={stage.id} className="flex items-center gap-2">
                        <span className="bg-blue-100 text-blue-700 text-xs px-3 py-1.5 rounded-full font-medium">
                          {i + 1}. {stage.label_ar}
                          <span className="text-blue-400 mr-1">({stage.role} · {stage.sla_hours}h)</span>
                        </span>
                        {i < parsedSchema.workflow.stages.length - 1 && (
                          <span className="text-gray-300 text-sm">←</span>
                        )}
                      </div>
                    ))}
                  </div>
                </div>
              )}

              {/* Live form */}
              <div className="bg-white rounded-xl border border-gray-200 overflow-hidden">
                <div className="bg-navy px-6 py-3">
                  <h3 className="text-white font-semibold text-sm">معاينة النموذج</h3>
                </div>
                <div className="p-5">
                  <DynamicForm schema={parsedSchema} values={{}} onChange={() => {}} disabled={false} />
                </div>
              </div>

              {/* Documents — real disabled upload widget so you see
                  exactly what the applicant will interact with. */}
              {(parsedSchema.documents?.length ?? 0) > 0 && (
                <div className="bg-white rounded-xl border border-gray-200 p-5">
                  <h3 className="text-sm font-semibold text-gray-800 mb-3">المستندات المطلوبة</h3>
                  <p className="text-xs text-gray-500 mb-3">
                    هذه هي واجهة رفع المستندات كما ستظهر للمتقدم في خطوة "المستندات". الرفع معطّل هنا لأنّه محرّر ومعاينة فقط.
                  </p>
                  <div className="space-y-3">
                    {parsedSchema.documents.map(doc => (
                      <DocumentPreviewCard key={doc.id} doc={doc} />
                    ))}
                  </div>
                </div>
              )}

              {saveError && (
                <div className="bg-red-50 border border-red-200 rounded-xl p-4 text-red-700 text-sm">
                  ❌ {saveError}
                </div>
              )}

              {/* Code field */}
              <div className="border border-gray-200 rounded-xl p-4 bg-gray-50 space-y-2">
                <label className="block text-xs font-medium text-gray-600">
                  كود الخدمة <span className="text-red-500">*</span>
                </label>
                <input
                  value={saveCode}
                  onChange={e => { setSaveCode(e.target.value.toUpperCase()); setSaveError(''); }}
                  className="w-full max-w-xs border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono focus:ring-2 focus:ring-blue-500 focus:outline-none bg-white"
                  placeholder="مثال: ENG-REG-001"
                  dir="ltr"
                />
              </div>

              <div className="flex gap-3 pt-2 flex-wrap">
                <button
                  onClick={() => setTab('schema')}
                  className="px-5 py-2.5 border border-gray-300 text-gray-600 rounded-xl hover:bg-gray-50 text-sm font-medium"
                >
                  ← تعديل المخطط
                </button>
                <SaveActions />
              </div>
            </>
          )}
        </div>
      )}
    </div>
  );
}
