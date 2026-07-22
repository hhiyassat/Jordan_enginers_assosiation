import React, { useCallback, useEffect, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { ArrowLeft, ArrowRight, Plus, Building2, MapPin } from 'lucide-react';
import { engineersApi, projectsApi, type OfficeQuota } from '../../../api/client';
import type { Engineer, Project } from '../../../types';
import { PageHero } from '../../../platform/ui/PageHero';
import { Button } from '../../../platform/ui/Button';
import { Modal } from '../../../platform/ui/Modal';
import { TextField, FormField } from '../../../platform/ui/FormField';
import { QuotaCard } from '../../../components/ui/QuotaCard';
import { errorMessage } from '../../../platform/utils/errorMessage';

export function ProjectsList() {
  const { t, i18n } = useTranslation();
  const isRtl = i18n.language.startsWith('ar');
  const [projects, setProjects] = useState<Project[]>([]);
  const [loading, setLoading]   = useState(true);
  const [error, setError]       = useState('');
  const [showAdd, setShowAdd]   = useState(false);
  const [quota, setQuota]       = useState<OfficeQuota | null>(null);
  const [quotaLoading, setQuotaLoading] = useState(true);
  const [quotaError, setQuotaError]     = useState('');
  const navigate = useNavigate();

  const loadQuota = useCallback(() => {
    setQuotaLoading(true);
    setQuotaError('');
    projectsApi.quota()
      .then(setQuota)
      .catch(err => setQuotaError(errorMessage(err)))
      .finally(() => setQuotaLoading(false));
  }, []);

  // JORD-69 / JORD-77: wrap in useCallback so `reload` is stable and
  // can safely land in useEffect's dependency array (no stale
  // closure on re-render). Errors go through errorMessage() so
  // non-Error rejections don't crash the setter.
  const reload = useCallback(() => {
    setLoading(true);
    projectsApi.list()
      .then(r => setProjects(r.projects))
      .catch(err => setError(errorMessage(err)))
      .finally(() => setLoading(false));
    loadQuota();
  }, [loadQuota]);

  useEffect(() => { reload(); }, [reload]);

  return (
    <div className="flex flex-col h-full" dir={isRtl ? 'rtl' : 'ltr'}>
      <PageHero
        titleAr={t('projects.title')}
        titleEn={t('projects.title')}
        breadcrumb={
          <nav aria-label="breadcrumb" className="flex items-center gap-3 text-xs">
            <Link
              to="/services"
              className="flex items-center gap-1.5 text-white/80 hover:text-white transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-white/60 rounded"
            >
              <ArrowRight size={14} aria-hidden="true" className={isRtl ? '' : 'rotate-180'} />
              <span>{t('category.backToServices')}</span>
            </Link>
            <span className="text-white/30" aria-hidden="true">/</span>
            <span className="text-white font-bold">{t('projects.title')}</span>
          </nav>
        }
        actions={
          <Button
            variant="white"
            onClick={() => setShowAdd(true)}
            icon={<Plus size={15} />}
          >
            {t('projects.add')}
          </Button>
        }
      />

      <div className="flex-1 overflow-y-auto bg-jea-bg p-6 flex flex-col gap-6">
        <QuotaCard
          facet={quota?.totals ?? null}
          year={quota?.year}
          titleAr={t('dashboard.officeQuotaTotal')}
          titleEn={t('dashboard.officeQuotaTotal')}
          loading={quotaLoading}
          error={quotaError}
          onRetry={loadQuota}
        />

        {loading && (
          <div className="flex flex-col gap-4 max-w-3xl" aria-busy="true" aria-label={t('loading')}>
            {[1, 2, 3].map(i => (
              <div key={i} className="h-24 bg-white rounded-2xl border border-jea-border animate-pulse" />
            ))}
          </div>
        )}

        {!loading && error && (
          <div role="alert" className="rounded-xl border border-jea-danger/30 bg-white p-6 text-jea-danger max-w-3xl">
            {error}
          </div>
        )}

        {!loading && !error && projects.length === 0 && (
          <div className="rounded-xl border border-jea-border bg-white p-16 text-center text-jea-muted max-w-3xl">
            <p className="text-sm font-bold text-jea-text">{t('projects.empty')}</p>
            <p className="text-xs mt-1">{t('projects.emptyCta')}</p>
          </div>
        )}

        {!loading && !error && projects.length > 0 && (
          <ul className="flex flex-col gap-4 max-w-3xl" aria-label={t('projects.listAria')}>
            {projects.map(p => (
              <li key={p.id}>
                <ProjectCard project={p} onOpen={() => navigate(`/projects/${p.id}`)} />
              </li>
            ))}
          </ul>
        )}
      </div>

      <AddProjectModal
        open={showAdd}
        onClose={() => setShowAdd(false)}
        onCreated={() => { setShowAdd(false); reload(); }}
      />
    </div>
  );
}

function ProjectCard({ project, onOpen }: { project: Project; onOpen: () => void }) {
  const { t, i18n } = useTranslation();
  const isArabic = i18n.language.startsWith('ar');
  const projectName = isArabic ? (project.name_ar || project.name_en) : (project.name_en || project.name_ar);
  const statusPill =
    project.status === 'active'
      ? { label: t('projects.statusFilter.active'),   cls: 'bg-emerald-100 text-emerald-700 border-emerald-200' }
      : project.status === 'pending'
      ? { label: t('projects.statusFilter.pending'),  cls: 'bg-jea-accent text-jea-primary border-jea-border' }
      : { label: t('projects.statusFilter.archived'), cls: 'bg-gray-100 text-gray-500 border-gray-200' };

  return (
    <button
      onClick={onOpen}
      aria-label={t('projects.openAria', { name: projectName })}
      className={`w-full bg-white rounded-2xl border border-jea-border shadow-sm hover:shadow-md hover:border-jea-primary/40 hover:-translate-y-0.5 transition-all duration-200 ${isArabic ? 'text-right' : 'text-left'} overflow-hidden focus:outline-none focus-visible:ring-2 focus-visible:ring-jea-primary/40`}
    >
      <div className="h-1 bg-jea-primary" aria-hidden="true" />
      <div className="p-5 flex items-center gap-4">
        <div className="w-12 h-12 rounded-xl bg-jea-bg flex items-center justify-center shrink-0" aria-hidden="true">
          <Building2 size={22} className="text-jea-primary" />
        </div>
        <div className="flex-1 min-w-0">
          <div className="flex items-center gap-2 flex-wrap">
            <h3 className="text-base font-black text-jea-text">{projectName}</h3>
            <span
              className={`text-[10px] font-bold px-2 py-0.5 rounded-full border ${statusPill.cls}`}
              aria-label={statusPill.label}
            >
              {statusPill.label}
            </span>
          </div>
          <div className="flex items-center gap-3 mt-2 flex-wrap text-[11px] text-jea-muted">
            {project.city && (
              <span className="flex items-center gap-1">
                <MapPin size={11} aria-hidden="true" />
                <span>{project.city}</span>
              </span>
            )}
            {project.area_m2 != null && <span>{project.area_m2} m²</span>}
            {project.type && (
              <span className="bg-jea-accent text-jea-primary px-2 py-0.5 rounded-full font-semibold">
                {project.type}
              </span>
            )}
            {project.request_no && (
              <span>{t('projects.requestNo')} {project.request_no}</span>
            )}
          </div>
        </div>
        <ArrowLeft size={18} className={`text-jea-muted shrink-0 ${isArabic ? '' : 'rotate-180'}`} aria-hidden="true" />
      </div>
    </button>
  );
}

function AddProjectModal({
  open, onClose, onCreated,
}: {
  open: boolean;
  onClose: () => void;
  onCreated: () => void;
}) {
  const { t } = useTranslation();
  const [name_ar, setNameAr]     = useState('');
  const [name_en, setNameEn]     = useState('');
  const [type,    setType]       = useState('سكني');
  const [area_m2, setArea]       = useState('');
  const [city,    setCity]       = useState('');
  const [engineerId, setEngineerId] = useState<string>('');
  const [engineers, setEngineers]   = useState<Engineer[]>([]);
  const [engLoading, setEngLoading] = useState(false);
  const [saving,  setSaving]     = useState(false);
  const [error,   setError]      = useState('');

  // JORD-72/73: `engineerId` is read here as an "auto-select first"
  // guard, not as reactive input — the effect must not re-fire on
  // every keystroke. Read the current value from a ref so lint's
  // exhaustive-deps check is satisfied without pulling engineerId
  // into the deps array (the disable-next-line was a smell).
  const engineerIdRef = React.useRef(engineerId);
  useEffect(() => { engineerIdRef.current = engineerId; }, [engineerId]);
  useEffect(() => {
    if (!open) return;
    setEngLoading(true);
    engineersApi.list()
      .then(r => {
        setEngineers(r.engineers);
        // Auto-select first engineer if none chosen yet.
        if (r.engineers.length > 0 && !engineerIdRef.current) {
          setEngineerId(String(r.engineers[0].id));
        }
      })
      .catch(() => setEngineers([]))
      .finally(() => setEngLoading(false));
  }, [open]);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError('');
    if (!name_ar.trim()) { setError(t('projects.form.requiredName')); return; }
    if (!engineerId)     { setError(t('projects.form.requiredEngineer')); return; }
    setSaving(true);
    try {
      await projectsApi.create({
        engineer_id: parseInt(engineerId, 10),
        name_ar,
        name_en: name_en || null,
        type,
        area_m2: area_m2 ? parseInt(area_m2, 10) : null,
        city:    city || null,
      });
      onCreated();
    } catch (err: unknown) {
      setError(errorMessage(err) || t('projects.form.saveError'));
    } finally {
      setSaving(false);
    }
  };

  return (
    <Modal
      open={open}
      onClose={onClose}
      titleAr={t('projects.addTitle')}
      titleEn={t('projects.addTitle')}
      footer={
        <>
          <Button variant="ghost" onClick={onClose}>
            {t('common.cancel')}
          </Button>
          <Button type="submit" form="add-project-form" loading={saving}>
            {t('projects.form.save')}
          </Button>
        </>
      }
    >
      <form id="add-project-form" onSubmit={handleSubmit} className="flex flex-col gap-4" noValidate>
        {/* JORD-56 (PM): labelEn was `= label` for every field, so the
            bilingual FormField rendered the same string twice ("اسم المشروع · اسم المشروع").
            t() already localises to the current UI language, so we pass
            `label` only. Callers that legitimately want the bilingual
            AR + EN pair can still provide both. */}
        <TextField
          label={t('projects.form.nameAr')}
          value={name_ar}
          onChange={setNameAr}
          placeholder={t('projects.form.namePlaceholderAr')}
          required
        />
        <TextField
          label={t('projects.form.nameEn')}
          value={name_en}
          onChange={setNameEn}
          placeholder={t('projects.form.namePlaceholderEn')}
        />
        <div className="grid grid-cols-2 gap-4">
          <TextField
            label={t('projects.form.city')}
            value={city}
            onChange={setCity}
            placeholder={t('projects.form.cityPlaceholder')}
          />
          <TextField
            label={t('projects.form.area')}
            value={area_m2}
            onChange={setArea}
            type="number"
            placeholder={t('projects.form.areaPlaceholder')}
          />
        </div>

        <FormField label={t('projects.form.type')}>
          {props => (
            <select
              {...props}
              value={type}
              onChange={e => setType(e.target.value)}
              className="w-full border border-jea-border rounded-lg px-3 py-2.5 text-sm outline-none focus:border-jea-primary focus:ring-2 focus:ring-jea-primary/20 bg-white"
            >
              <option value="سكني">{t('projects.typeOptions.residential')}</option>
              <option value="تجاري">{t('projects.typeOptions.commercial')}</option>
              <option value="صناعي">{t('projects.typeOptions.industrial')}</option>
              <option value="حكومي">{t('projects.typeOptions.government')}</option>
              <option value="مختلط">{t('projects.typeOptions.mixed')}</option>
            </select>
          )}
        </FormField>

        <FormField label={t('projects.form.engineer')} required>
          {props => (
            <select
              {...props}
              value={engineerId}
              onChange={e => setEngineerId(e.target.value)}
              disabled={engLoading || engineers.length === 0}
              className="w-full border border-jea-border rounded-lg px-3 py-2.5 text-sm outline-none focus:border-jea-primary focus:ring-2 focus:ring-jea-primary/20 bg-white disabled:opacity-60"
            >
              {engLoading && <option value="">{t('projects.form.loadingEngineers')}</option>}
              {!engLoading && engineers.length === 0 && (
                <option value="">{t('projects.form.noEngineers')}</option>
              )}
              {engineers.map(e => (
                <option key={e.id} value={e.id}>
                  {e.name_ar} · {e.membership_number}
                  {e.annual_quota_m2 != null && ` (${e.annual_quota_m2} ${t('projects.form.yearSuffix')})`}
                </option>
              ))}
            </select>
          )}
        </FormField>

        {error && (
          <div role="alert" className="bg-red-50 border border-red-200 text-red-700 text-sm px-3 py-2 rounded-lg">
            {error}
          </div>
        )}
      </form>
    </Modal>
  );
}
