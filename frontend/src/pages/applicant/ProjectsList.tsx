import React, { useEffect, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { ArrowLeft, ArrowRight, Plus, Building2, MapPin } from 'lucide-react';
import { projectsApi } from '../../api/client';
import type { Project } from '../../types';
import { PageHero } from '../../components/ui/PageHero';
import { Button } from '../../components/ui/Button';
import { Modal } from '../../components/ui/Modal';
import { TextField, FormField } from '../../components/ui/FormField';

export function ProjectsList() {
  const [projects, setProjects] = useState<Project[]>([]);
  const [loading, setLoading]   = useState(true);
  const [error, setError]       = useState('');
  const [showAdd, setShowAdd]   = useState(false);
  const navigate = useNavigate();

  const reload = () => {
    setLoading(true);
    projectsApi.list()
      .then(r => setProjects(r.projects))
      .catch(e => setError(e.message))
      .finally(() => setLoading(false));
  };

  useEffect(reload, []);

  return (
    <div className="flex flex-col h-full" dir="rtl">
      <PageHero
        titleAr="مشاريعي"
        titleEn="My Projects"
        breadcrumb={
          <nav aria-label="breadcrumb" className="flex items-center gap-3 text-xs">
            <Link
              to="/services"
              className="flex items-center gap-1.5 text-white/60 hover:text-white transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-white/60 rounded"
            >
              <ArrowRight size={14} aria-hidden="true" />
              <span lang="ar">الخدمات الإلكترونية</span>
              <span className="sr-only" lang="en">E-Services</span>
            </Link>
            <span className="text-white/30" aria-hidden="true">/</span>
            <span className="text-white font-bold" lang="ar">مشاريعي</span>
          </nav>
        }
        actions={
          <Button
            variant="white"
            onClick={() => setShowAdd(true)}
            icon={<Plus size={15} />}
          >
            <span lang="ar">إضافة مشروع</span> · <span lang="en" dir="ltr">Add Project</span>
          </Button>
        }
      />

      <div className="flex-1 overflow-y-auto bg-jea-bg p-6">
        {loading && (
          <div className="flex flex-col gap-4 max-w-3xl" aria-busy="true" aria-label="جارٍ التحميل">
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
            <p className="text-sm font-bold text-jea-text" lang="ar">لا توجد مشاريع بعد</p>
            <p className="text-xs mt-1" lang="ar">أضف مشروعك الأول باستخدام زر «إضافة مشروع»</p>
            <p className="text-xs mt-1" lang="en" dir="ltr">No projects yet. Add your first project using the "Add Project" button above.</p>
          </div>
        )}

        {!loading && !error && projects.length > 0 && (
          <ul className="flex flex-col gap-4 max-w-3xl" aria-label="قائمة المشاريع">
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
  const statusPill =
    project.status === 'active'
      ? { ar: 'نشط',           en: 'Active',        cls: 'bg-emerald-100 text-emerald-700 border-emerald-200' }
      : project.status === 'pending'
      ? { ar: 'قيد المراجعة',  en: 'Under review',  cls: 'bg-jea-accent text-jea-primary border-jea-border' }
      : { ar: 'مؤرشف',         en: 'Archived',      cls: 'bg-gray-100 text-gray-500 border-gray-200' };

  return (
    <button
      onClick={onOpen}
      aria-label={`فتح مشروع ${project.name_ar}`}
      className="w-full bg-white rounded-2xl border border-jea-border shadow-sm hover:shadow-md hover:border-jea-primary/40 hover:-translate-y-0.5 transition-all duration-200 text-right overflow-hidden focus:outline-none focus-visible:ring-2 focus-visible:ring-jea-primary/40"
    >
      <div className="h-1 bg-jea-primary" aria-hidden="true" />
      <div className="p-5 flex items-center gap-4">
        <div className="w-12 h-12 rounded-xl bg-jea-bg flex items-center justify-center shrink-0" aria-hidden="true">
          <Building2 size={22} className="text-jea-primary" />
        </div>
        <div className="flex-1 min-w-0">
          <div className="flex items-center gap-2 flex-wrap">
            <h3 className="text-base font-black text-jea-text" lang="ar">{project.name_ar}</h3>
            <span
              className={`text-[10px] font-bold px-2 py-0.5 rounded-full border ${statusPill.cls}`}
              aria-label={`الحالة: ${statusPill.ar}`}
            >
              <span lang="ar">{statusPill.ar}</span>
            </span>
          </div>
          {project.name_en && (
            <p className="text-xs text-jea-muted mt-0.5" lang="en" dir="ltr">{project.name_en}</p>
          )}
          <div className="flex items-center gap-3 mt-2 flex-wrap text-[11px] text-jea-muted">
            {project.city && (
              <span className="flex items-center gap-1">
                <MapPin size={11} aria-hidden="true" />
                <span lang="ar">{project.city}</span>
              </span>
            )}
            {project.area_m2 != null && (
              <span>{project.area_m2} م²</span>
            )}
            {project.type && (
              <span className="bg-jea-accent text-jea-primary px-2 py-0.5 rounded-full font-semibold" lang="ar">
                {project.type}
              </span>
            )}
            {project.request_no && (
              <span><span lang="ar">طلب رقم</span> {project.request_no}</span>
            )}
          </div>
        </div>
        <ArrowLeft size={18} className="text-jea-muted shrink-0" aria-hidden="true" />
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
  const [name_ar, setNameAr]   = useState('');
  const [name_en, setNameEn]   = useState('');
  const [type,    setType]     = useState('سكني');
  const [area_m2, setArea]     = useState('');
  const [city,    setCity]     = useState('');
  const [saving,  setSaving]   = useState(false);
  const [error,   setError]    = useState('');

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError('');
    if (!name_ar.trim()) { setError('يرجى إدخال اسم المشروع'); return; }
    setSaving(true);
    try {
      await projectsApi.create({
        name_ar,
        name_en: name_en || null,
        type,
        area_m2: area_m2 ? parseInt(area_m2, 10) : null,
        city:    city || null,
      });
      onCreated();
    } catch (err: unknown) {
      setError((err as Error).message || 'خطأ أثناء الحفظ');
    } finally {
      setSaving(false);
    }
  };

  return (
    <Modal
      open={open}
      onClose={onClose}
      titleAr="إضافة مشروع جديد"
      titleEn="Add New Project"
      footer={
        <>
          <Button variant="ghost" onClick={onClose}>
            <span lang="ar">إلغاء</span> · <span lang="en" dir="ltr">Cancel</span>
          </Button>
          <Button type="submit" form="add-project-form" loading={saving}>
            <span lang="ar">حفظ المشروع</span> · <span lang="en" dir="ltr">Save</span>
          </Button>
        </>
      }
    >
      <form id="add-project-form" onSubmit={handleSubmit} className="flex flex-col gap-4" noValidate>
        <TextField
          label="اسم المشروع"
          labelEn="Project name"
          value={name_ar}
          onChange={setNameAr}
          placeholder="مثال: مبنى سكني في عمان"
          required
        />
        <TextField
          label="الاسم بالإنجليزية"
          labelEn="Name (English)"
          value={name_en}
          onChange={setNameEn}
          placeholder="e.g. Amman Residential"
        />
        <div className="grid grid-cols-2 gap-4">
          <TextField
            label="المدينة"
            labelEn="City"
            value={city}
            onChange={setCity}
            placeholder="عمان"
          />
          <TextField
            label="المساحة (م²)"
            labelEn="Area (m²)"
            value={area_m2}
            onChange={setArea}
            type="number"
            placeholder="120"
          />
        </div>

        <FormField label="نوع المشروع" labelEn="Project type">
          {props => (
            <select
              {...props}
              value={type}
              onChange={e => setType(e.target.value)}
              className="w-full border border-jea-border rounded-lg px-3 py-2.5 text-sm outline-none focus:border-jea-primary focus:ring-2 focus:ring-jea-primary/20 bg-white"
            >
              <option>سكني</option>
              <option>تجاري</option>
              <option>صناعي</option>
              <option>حكومي</option>
              <option>مختلط</option>
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
