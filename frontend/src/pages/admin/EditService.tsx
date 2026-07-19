import React, { useEffect, useRef, useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { Lock, Unlock } from 'lucide-react';
import { adminApi } from '../../api/client';
import { DynamicForm } from '../../engine/DynamicForm';
import { DocumentPreviewCard } from '../../engine/DocumentPreviewCard';
import type { ServiceDefinition, ServiceSchema } from '../../types';

type Tab = 'schema' | 'preview' | 'ai';

interface ChatMessage {
  role: 'user' | 'assistant';
  text: string;
  explanation?: string;
  changes?: string[];
  updatedSchema?: Record<string, unknown>;
  applied?: boolean;   // schema replaced in local buffer
  saved?: boolean;     // PUT /services/{id} completed successfully
  savedError?: string; // last save-side error to display inline
}

const STATUS_CONFIG: Record<string, { label: string; color: string }> = {
  active:   { label: 'نشطة',  color: 'bg-green-100 text-green-700' },
  draft:    { label: 'مسودة', color: 'bg-yellow-100 text-yellow-700' },
  inactive: { label: 'معطلة', color: 'bg-gray-100 text-gray-500' },
};

export function EditService() {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();

  const [service, setService]       = useState<ServiceDefinition | null>(null);
  const [loading, setLoading]       = useState(true);
  const [tab, setTab]               = useState<Tab>('schema');

  const [schemaJson, setSchemaJson]     = useState('');
  const [jsonError, setJsonError]       = useState('');
  const [parsedSchema, setParsedSchema] = useState<ServiceSchema | null>(null);

  const [saving, setSaving]         = useState(false);
  const [saveError, setSaveError]   = useState('');
  const [activating, setActivating] = useState(false);

  // Chat state
  const [messages, setMessages]       = useState<ChatMessage[]>([]);
  const [chatInput, setChatInput]     = useState('');
  const [chatLoading, setChatLoading] = useState(false);
  const [chatError, setChatError]     = useState('');
  const chatBottomRef                 = useRef<HTMLDivElement>(null);

  // Load service
  useEffect(() => {
    if (!id) return;
    adminApi.getService(Number(id))
      .then(r => {
        setService(r.service);
        const pretty = JSON.stringify(r.service.schema, null, 2);
        setSchemaJson(pretty);
        setParsedSchema(r.service.schema as unknown as ServiceSchema);
      })
      .catch(e => setSaveError((e as Error).message))
      .finally(() => setLoading(false));
  }, [id]);

  useEffect(() => {
    chatBottomRef.current?.scrollIntoView({ behavior: 'smooth' });
  }, [messages]);

  const handleSchemaChange = (value: string) => {
    setSchemaJson(value);
    setJsonError('');
    try {
      setParsedSchema(JSON.parse(value) as ServiceSchema);
    } catch {
      setParsedSchema(null);
      setJsonError('JSON غير صالح — تحقق من الصياغة');
    }
  };

  const handleSave = async (newStatus?: 'active' | 'inactive' | 'draft') => {
    if (!parsedSchema || !service) return;
    setSaving(true);
    setSaveError('');
    try {
      await adminApi.updateService(service.id, {
        name_ar:  parsedSchema.name_ar,
        name_en:  parsedSchema.name_en,
        schema:   parsedSchema as unknown as Record<string, unknown>,
        ...(newStatus ? { status: newStatus } : {}),
      });
      navigate('/admin/services', { state: { saved: service.code } });
    } catch (err: unknown) {
      setSaveError((err as Error).message);
      window.scrollTo({ top: 0, behavior: 'smooth' });
    } finally {
      setSaving(false);
    }
  };

  const handleActivate = async () => {
    if (!service) return;
    setActivating(true);
    setSaveError('');
    try {
      await adminApi.updateServiceStatus(service.id, 'active');
      navigate('/admin/services', { state: { saved: service.code } });
    } catch (err: unknown) {
      setSaveError((err as Error).message);
    } finally {
      setActivating(false);
    }
  };

  // Chat
  const handleChatSend = async () => {
    const text = chatInput.trim();
    if (!text || chatLoading || !parsedSchema) return;
    setChatInput('');
    setChatError('');
    setMessages(prev => [...prev, { role: 'user', text }]);
    setChatLoading(true);
    try {
      const r = await adminApi.chatUpdateSchema(
        parsedSchema as unknown as Record<string, unknown>,
        text,
      );
      setMessages(prev => [...prev, {
        role: 'assistant',
        text: r.explanation,
        explanation: r.explanation,
        changes: r.changes,
        updatedSchema: r.updated_schema,
        applied: false,
      }]);
    } catch (err: unknown) {
      setChatError((err as Error).message);
    } finally {
      setChatLoading(false);
    }
  };

  const applyChange = (msgIndex: number, updatedSchema: Record<string, unknown>) => {
    handleSchemaChange(JSON.stringify(updatedSchema, null, 2));
    setMessages(prev => prev.map((m, i) => i === msgIndex ? { ...m, applied: true } : m));
  };

  /**
   * Apply + save in one click from the AI panel. Users kept forgetting
   * to hit the Save button after "تطبيق التغييرات على المخطط", so the
   * schema stayed in the local buffer and the applicant flow never saw
   * the new documents/fields. This wraps applyChange + adminApi.updateService
   * and stays on the page so the admin can keep chatting.
   */
  const applyAndSaveChange = async (msgIndex: number, updatedSchema: Record<string, unknown>) => {
    if (!service) return;
    // Local apply first — the schema editor + preview update immediately.
    handleSchemaChange(JSON.stringify(updatedSchema, null, 2));
    setMessages(prev => prev.map((m, i) => i === msgIndex ? { ...m, applied: true, savedError: undefined } : m));

    try {
      const r = await adminApi.updateService(service.id, {
        name_ar: (updatedSchema as Record<string, string>).name_ar ?? service.name_ar,
        name_en: (updatedSchema as Record<string, string>).name_en ?? service.name_en,
        schema:  updatedSchema,
      });
      // Refresh the local service so is_locked, updated_at, etc. reflect DB truth.
      setService(r.service);
      setMessages(prev => prev.map((m, i) => i === msgIndex ? { ...m, saved: true } : m));
    } catch (err) {
      const apiErr = err as Error & { errors?: Record<string, string | string[]> };
      const firstFieldError = apiErr.errors ? Object.values(apiErr.errors)[0] : undefined;
      const firstMsg = Array.isArray(firstFieldError) ? firstFieldError[0] : (firstFieldError ?? apiErr.message);
      setMessages(prev => prev.map((m, i) => i === msgIndex ? { ...m, savedError: firstMsg ?? 'حدث خطأ أثناء الحفظ' } : m));
    }
  };

  if (loading) return (
    <div className="flex justify-center py-20">
      <div className="animate-spin w-8 h-8 border-4 border-blue-500 border-t-transparent rounded-full" />
    </div>
  );

  if (!service) return (
    <div className="p-8 text-center text-red-600">خدمة غير موجودة</div>
  );

  const status = service.status ?? 'draft';
  const st = STATUS_CONFIG[status] ?? { label: status, color: 'bg-gray-100 text-gray-600' };

  return (
    <div className="max-w-5xl mx-auto px-4 py-8" dir="rtl">

      {/* Header */}
      <div className="mb-6">
        <button onClick={() => navigate('/admin/services')} className="text-sm text-gray-400 hover:text-gray-600 mb-2">
          → رجوع لقائمة الخدمات
        </button>
        <div className="flex items-start justify-between gap-4">
          <div>
            <h1 className="text-2xl font-bold text-gray-900">{service.name_ar}</h1>
            <div className="flex items-center gap-3 mt-1">
              <span className="font-mono text-xs text-gray-400">{service.code}</span>
              <span className={`text-xs px-2.5 py-0.5 rounded-full font-medium ${st.color}`}>
                {st.label}
              </span>
            </div>
          </div>
          {service.status !== 'active' ? (
            <button
              onClick={handleActivate}
              disabled={activating}
              className="px-5 py-2.5 bg-green-600 text-white rounded-xl hover:bg-green-700 disabled:opacity-50 text-sm font-medium flex items-center gap-2"
            >
              {activating
                ? <><span className="animate-spin w-4 h-4 border-2 border-white border-t-transparent rounded-full" /> جارٍ...</>
                : '🚀 تفعيل هذه الخدمة'
              }
            </button>
          ) : (
            <span className="text-sm text-green-600 font-medium">✅ الخدمة نشطة</span>
          )}
        </div>
      </div>

      {saveError && (
        <div className="mb-4 bg-red-50 border border-red-300 rounded-xl p-4 text-red-700 text-sm font-medium">
          ❌ {saveError}
        </div>
      )}

      {/* Lock banner — every save endpoint refuses with 423 while is_locked
          is true, so surface the state prominently and offer inline unlock. */}
      {service.is_locked && (
        <div
          className="mb-4 bg-amber-50 border border-amber-300 rounded-xl p-4 flex items-center justify-between gap-3"
          role="status"
        >
          <div className="flex items-center gap-2 text-sm text-amber-900">
            <Lock size={16} aria-hidden="true" />
            <span>الخدمة مقفلة للتعديل. افتح القفل للسماح بحفظ التغييرات.</span>
          </div>
          <button
            onClick={async () => {
              try {
                const r = await adminApi.unlockService(service.id);
                setService(prev => prev ? { ...prev, is_locked: r.service.is_locked } : prev);
              } catch (e) {
                setSaveError((e as Error).message);
              }
            }}
            className="inline-flex items-center gap-1 px-3 py-1.5 text-xs bg-amber-600 text-white rounded-lg hover:bg-amber-700 font-semibold"
          >
            <Unlock size={12} aria-hidden="true" /> فتح القفل
          </button>
        </div>
      )}

      {/* Tabs */}
      <div className="flex border-b border-gray-200 mb-6 gap-1">
        {([
          ['schema',  '1 · المخطط JSON'],
          ['preview', '2 · معاينة النموذج'],
          ['ai',      '3 · 🤖 مساعد الذكاء الاصطناعي'],
        ] as [Tab, string][]).map(([t, label]) => (
          <button
            key={t}
            onClick={() => setTab(t)}
            disabled={t !== 'schema' && !parsedSchema}
            className={`px-4 py-2.5 text-sm font-medium rounded-t-lg transition-colors disabled:opacity-40 ${
              tab === t
                ? t === 'ai'
                  ? 'bg-white border border-b-white border-gray-200 -mb-px text-purple-700'
                  : 'bg-white border border-b-white border-gray-200 -mb-px text-navy'
                : 'text-gray-500 hover:text-gray-700'
            }`}
          >
            {label}
          </button>
        ))}
      </div>

      {/* ── Tab 1: Schema editor ── */}
      {tab === 'schema' && (
        <div className="space-y-4">
          <p className="text-sm text-gray-500">
            عدّل المخطط مباشرةً — أو استخدم تبويب <strong>مساعد الذكاء الاصطناعي</strong> لإجراء التعديلات بالعربية.
          </p>

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

          <div className="flex gap-3 pt-2">
            <button
              onClick={() => handleSave()}
              disabled={saving || !!jsonError || !parsedSchema || service.is_locked}
              className="px-5 py-2.5 border-2 border-navy text-navy rounded-xl hover:bg-blue-50 disabled:opacity-50 text-sm font-medium"
            >
              {saving ? 'جارٍ الحفظ...' : '💾 حفظ التعديلات'}
            </button>
            {service.status !== 'active' && (
              <button
                onClick={() => handleSave('active')}
                disabled={saving || !!jsonError || !parsedSchema || service.is_locked}
                className="px-5 py-2.5 bg-green-600 text-white rounded-xl hover:bg-green-700 disabled:opacity-50 text-sm font-medium"
              >
                {saving ? 'جارٍ...' : '🚀 حفظ وتفعيل'}
              </button>
            )}
          </div>
        </div>
      )}

      {/* ── Tab 2: Preview ── */}
      {tab === 'preview' && parsedSchema && (
        <div className="space-y-4">
          <div className="bg-green-50 border border-green-200 rounded-xl p-4 text-sm text-green-700">
            ✅ معاينة حية — هذا ما سيراه المتقدم
          </div>

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
                    {i < parsedSchema.workflow.stages.length - 1 && <span className="text-gray-300">←</span>}
                  </div>
                ))}
              </div>
            </div>
          )}

          <div className="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <div className="bg-navy px-6 py-3">
              <h3 className="text-white font-semibold text-sm">معاينة النموذج</h3>
            </div>
            <div className="p-5">
              <DynamicForm schema={parsedSchema} values={{}} onChange={() => {}} disabled={false} />
            </div>
          </div>

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

          <div className="flex gap-3 pt-2">
            <button onClick={() => setTab('schema')} className="px-5 py-2.5 border border-gray-300 text-gray-600 rounded-xl hover:bg-gray-50 text-sm font-medium">
              ← تعديل المخطط
            </button>
            <button onClick={() => handleSave()} disabled={saving || service.is_locked} className="px-5 py-2.5 border-2 border-navy text-navy rounded-xl hover:bg-blue-50 disabled:opacity-50 text-sm font-medium">
              {saving ? 'جارٍ الحفظ...' : '💾 حفظ التعديلات'}
            </button>
            {service.status !== 'active' && (
              <button onClick={() => handleSave('active')} disabled={saving || service.is_locked} className="px-5 py-2.5 bg-green-600 text-white rounded-xl hover:bg-green-700 disabled:opacity-50 text-sm font-medium">
                {saving ? 'جارٍ...' : '🚀 حفظ وتفعيل'}
              </button>
            )}
          </div>
        </div>
      )}

      {/* ── Tab 3: AI Assistant ── */}
      {tab === 'ai' && parsedSchema && (
        <div className="space-y-4">

          {/* Instruction banner */}
          <div className="bg-purple-50 border border-purple-200 rounded-xl p-4 text-sm text-purple-800">
            <strong>كيف يعمل:</strong> اكتب طلب التعديل بالعربية أو الإنجليزية — سيقترح الذكاء الاصطناعي التغيير ويعطيك زر لتطبيقه على المخطط مباشرةً. التغييرات لا تُحفظ تلقائياً — اضغط <strong>حفظ التعديلات</strong> بعد المراجعة.
          </div>

          {/* Example prompts */}
          {messages.length === 0 && (
            <div className="bg-white rounded-xl border border-gray-200 p-5">
              <p className="text-xs font-medium text-gray-500 mb-3">أمثلة — اضغط لاستخدامها مباشرةً:</p>
              <div className="grid grid-cols-2 gap-2">
                {[
                  'أضف حقل رقم الهاتف كحقل اختياري',
                  'اجعل حقل العنوان إلزامياً',
                  'أضف مرحلة مراجعة قانونية (auditor) بعد المرحلة الأخيرة',
                  'أضف وثيقة "صورة الهوية" مطلوبة',
                  'غيّر مدة SLA للمرحلة الأولى إلى 48 ساعة',
                  'أضف قسم جديد "المعلومات المالية"',
                  'أزل حقل البريد الإلكتروني',
                  'غيّر الرسوم إلى 75 دينار',
                ].map(ex => (
                  <button
                    key={ex}
                    onClick={() => setChatInput(ex)}
                    className="text-right text-xs px-3 py-2.5 bg-gray-50 rounded-lg border border-gray-200 hover:border-purple-300 hover:bg-purple-50 hover:text-purple-700 transition-colors"
                  >
                    {ex}
                  </button>
                ))}
              </div>
            </div>
          )}

          {/* Messages */}
          <div className="space-y-3 min-h-24">
            {messages.map((msg, i) => (
              <div key={i} className={`flex ${msg.role === 'user' ? 'justify-start' : 'justify-end'}`}>
                {msg.role === 'user' ? (
                  <div className="max-w-xl bg-white border border-gray-200 rounded-2xl rounded-tl-sm px-4 py-3 text-sm text-gray-800">
                    {msg.text}
                  </div>
                ) : (
                  <div className="max-w-2xl bg-purple-50 border border-purple-200 rounded-2xl rounded-tr-sm px-5 py-4 space-y-3">
                    <p className="text-sm text-purple-900 font-medium">{msg.explanation}</p>
                    {msg.changes && msg.changes.length > 0 && (
                      <ul className="text-xs text-purple-700 space-y-1" dir="ltr">
                        {msg.changes.map((c, ci) => (
                          <li key={ci} className="flex items-start gap-1.5">
                            <span className="text-purple-400 mt-0.5">•</span>
                            <span>{c}</span>
                          </li>
                        ))}
                      </ul>
                    )}
                    {msg.updatedSchema && (
                      <div className="space-y-2">
                        {/* Primary path — apply + persist in one click.
                            Users forgot the "then click Save" step when
                            these were two separate buttons across two tabs. */}
                        <button
                          onClick={() => applyAndSaveChange(i, msg.updatedSchema!)}
                          disabled={msg.saved || service.is_locked}
                          className={`w-full py-2.5 rounded-xl text-sm font-semibold transition-colors ${
                            msg.saved
                              ? 'bg-green-100 text-green-700 cursor-default'
                              : service.is_locked
                                ? 'bg-gray-200 text-gray-400 cursor-not-allowed'
                                : 'bg-purple-700 text-white hover:bg-purple-800'
                          }`}
                        >
                          {msg.saved
                            ? '✅ تم التطبيق والحفظ في قاعدة البيانات'
                            : service.is_locked
                              ? '🔒 الخدمة مقفلة — افتح القفل أولاً'
                              : '⚡ تطبيق التغييرات وحفظها'}
                        </button>
                        {/* Escape hatch — apply to the local buffer only
                            so the admin can inspect / hand-edit the JSON
                            before persisting. */}
                        {!msg.applied && !msg.saved && (
                          <button
                            onClick={() => applyChange(i, msg.updatedSchema!)}
                            className="w-full py-2 rounded-xl text-xs text-purple-700 border border-purple-200 hover:bg-purple-50"
                          >
                            تطبيق محلي فقط (للمراجعة قبل الحفظ)
                          </button>
                        )}
                        {msg.applied && !msg.saved && !msg.savedError && (
                          <p className="text-[11px] text-gray-500 text-center">
                            التغييرات في المحرر — راجع تبويب المخطط JSON ثم احفظ.
                          </p>
                        )}
                        {msg.savedError && (
                          <p className="text-xs text-red-600 bg-red-50 border border-red-200 rounded-lg p-2" role="alert">
                            ❌ {msg.savedError}
                          </p>
                        )}
                      </div>
                    )}
                  </div>
                )}
              </div>
            ))}

            {chatLoading && (
              <div className="flex justify-end">
                <div className="bg-purple-50 border border-purple-200 rounded-2xl rounded-tr-sm px-5 py-4 flex items-center gap-3">
                  <span className="animate-spin w-5 h-5 border-2 border-purple-500 border-t-transparent rounded-full" />
                  <span className="text-sm text-purple-700">يفكر الذكاء الاصطناعي...</span>
                </div>
              </div>
            )}

            <div ref={chatBottomRef} />
          </div>

          {/* Error */}
          {chatError && (
            <div className="bg-red-50 border border-red-200 rounded-xl p-3 text-red-700 text-sm">
              ❌ {chatError}
            </div>
          )}

          {/* Input area */}
          <div className="bg-white rounded-xl border border-gray-200 p-4">
            <label className="block text-xs font-medium text-gray-500 mb-2">اكتب طلب التعديل</label>
            <div className="flex gap-3">
              <textarea
                value={chatInput}
                onChange={e => setChatInput(e.target.value)}
                onKeyDown={e => {
                  if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    handleChatSend();
                  }
                }}
                placeholder="مثال: أضف حقل نوع النشاط التجاري كقائمة منسدلة بخيارات (تجاري، صناعي، خدمي)"
                disabled={chatLoading}
                className="flex-1 border border-gray-300 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-purple-400 focus:outline-none disabled:opacity-50 resize-none"
                rows={3}
                dir="rtl"
              />
              <button
                onClick={handleChatSend}
                disabled={chatLoading || !chatInput.trim()}
                className="px-5 py-3 bg-purple-700 text-white rounded-xl hover:bg-purple-800 disabled:opacity-50 text-sm font-medium self-end transition-colors"
              >
                {chatLoading ? '⏳' : 'إرسال'}
              </button>
            </div>
            <p className="text-xs text-gray-400 mt-2">Enter للإرسال · Shift+Enter لسطر جديد</p>
          </div>

          {/* Save reminder */}
          <div className="flex gap-3 pt-2">
            <button onClick={() => setTab('schema')} className="px-5 py-2.5 border border-gray-300 text-gray-600 rounded-xl hover:bg-gray-50 text-sm font-medium">
              ← مراجعة المخطط
            </button>
            <button onClick={() => handleSave()} disabled={saving || !!jsonError || !parsedSchema || service.is_locked} className="px-5 py-2.5 border-2 border-navy text-navy rounded-xl hover:bg-blue-50 disabled:opacity-50 text-sm font-medium">
              {saving ? 'جارٍ الحفظ...' : '💾 حفظ التعديلات'}
            </button>
            {service.status !== 'active' && (
              <button onClick={() => handleSave('active')} disabled={saving || service.is_locked} className="px-5 py-2.5 bg-green-600 text-white rounded-xl hover:bg-green-700 disabled:opacity-50 text-sm font-medium">
                🚀 حفظ وتفعيل
              </button>
            )}
          </div>
        </div>
      )}
    </div>
  );
}
