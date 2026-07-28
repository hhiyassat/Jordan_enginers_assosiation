# 🏗️ FSD Migration Task List — Jordan Engineers Association
> Feature-Sliced Design — النسخة النهائية المعتمدة
> تاريخ الإنشاء: 2026-07-28 | الحالة: جاهز للتنفيذ

---

## 📋 ملاحظة مهمة قبل البدء

- ✅ **كل task يجب أن تنتهي بـ Build ناجح** قبل الانتقال للتالية
- ✅ **لا تحذف الملف الأصلي** قبل التأكد من أن كل imports تشير للمكان الجديد
- ✅ **استخدم git commit صغيرة** بعد كل task
- ✅ قاعدة الاستيراد: دائماً عبر Public API (`@entities/application`) وليس المسار الداخلي
- ✅ لا تكسر أي test موجود — كل test file ينتقل بجانب ملفه

---

## 🔴 المرحلة الأولى: Bootstrap & Foundation (app/)

### [ ] Task 1 — إنشاء طبقة `app/` ونقل Bootstrap Files

**الهدف:** إنشاء `src/app/` كطبقة Bootstrap لتشغيل التطبيق فقط.

**الملفات المنقولة:**

| الملف الحالي | الموقع الجديد | ملاحظات |
|---|---|---|
| `src/main.tsx` | `src/app/main.tsx` | تحديث import paths |
| `src/App.tsx` | `src/app/App.tsx` | تحديث import paths |
| `src/index.css` | `src/app/styles/index.css` | — |

**خطوات التنفيذ:**
1. أنشئ المجلدات: `src/app/styles/`
2. انقل `src/main.tsx` → `src/app/main.tsx`
3. انقل `src/App.tsx` → `src/app/App.tsx`
4. انقل `src/index.css` → `src/app/styles/index.css`
5. حدّث `vite.config.ts` (أو `index.html`) ليشير إلى `src/app/main.tsx` كنقطة دخول
6. حدّث `src/app/main.tsx`: غيّر `import './index.css'` → `import './styles/index.css'`
7. حدّث `src/app/main.tsx`: غيّر `import './i18n'` → مسار i18n الجديد بعد Task 7

**التحقق:** `npm run build` ✅ | `npm run dev` يشتغل ✅

---

### [ ] Task 2 — إنشاء `src/app/router/` وتقسيم routes.tsx

**الهدف:** تقسيم `src/routes.tsx` (123 سطر، 10.5 KB) إلى ملفات router منظمة.

**الملفات المنشأة:**

| الملف الجديد | المحتوى |
|---|---|
| `src/app/router/index.tsx` | `AppRoutes` الجذر — يجمع فقط |
| `src/app/router/AdminRoutes.tsx` | routes الـ admin (AdminDashboard, AdminApplications, ServicesList, NewService, EditService, UserManagement, ServiceFeesAdmin, ComplaintsAdmin, LegalFinesAdmin, SupervisionTransfersAdmin, IntegrationCycles, IntegrationCycleDetail) |
| `src/app/router/ApplicantRoutes.tsx` | routes المتقدم (Dashboard, Apply, MyApplications, ApplicationDetail, ServiceList, CategoryServicesView) |
| `src/app/router/ReviewerRoutes.tsx` | routes المراجع (ReviewQueue, ReviewPanel, ReviewDashboard) |
| `src/app/router/guards.tsx` | منقول من `src/auth/guards.tsx` — نفس المحتوى |

**ملفات المصدر:**
- `src/routes.tsx`
- `src/auth/guards.tsx` + `src/auth/guards.test.tsx`

**خطوات التنفيذ:**
1. أنشئ `src/app/router/guards.tsx` بنسخ محتوى `src/auth/guards.tsx`
2. انقل `src/auth/guards.test.tsx` → `src/app/router/guards.test.tsx`
3. أنشئ `src/app/router/AdminRoutes.tsx` باستخراج الـ admin routes
4. أنشئ `src/app/router/ApplicantRoutes.tsx` باستخراج الـ applicant routes
5. أنشئ `src/app/router/ReviewerRoutes.tsx` باستخراج الـ reviewer routes
6. أنشئ `src/app/router/index.tsx` يستورد الثلاث ملفات ويصدّر `AppRoutes`
7. حدّث `src/app/App.tsx`: غيّر import من `'../routes'` → `'./router'`
8. احذف `src/routes.tsx` بعد التأكد من البناء

**التحقق:** `npm run build` ✅ | كل routes تعمل ✅

---

### [ ] Task 3 — إنشاء `src/app/providers/` ودمج الـ Providers

**الهدف:** نقل `queryClient.ts` وتجميع كل providers في `AppProviders.tsx`.

| الملف الحالي | الموقع الجديد |
|---|---|
| `src/api/queryClient.ts` (1.2 KB) | `src/app/providers/queryClient.ts` |

**الملفات المنشأة:**

| الملف الجديد | المحتوى |
|---|---|
| `src/app/providers/AppProviders.tsx` | يجمع: `QueryClientProvider`, `BrowserRouter`, `AuthProvider`, `ErrorBoundary`, `RouteSuspense` |

**خطوات التنفيذ:**
1. انقل `src/api/queryClient.ts` → `src/app/providers/queryClient.ts`
2. أنشئ `src/app/providers/AppProviders.tsx` يستورد كل الـ providers
3. حدّث `src/app/App.tsx`: استبدل inline providers باستيراد `<AppProviders>`
4. ابحث عن كل ملف يستورد من `../../api/queryClient` وحدّثه:
   ```powershell
   Select-String -Path "frontend/src/**/*.ts*" -Pattern "queryClient" -Recurse
   ```

**التحقق:** `npm run build` ✅ | التطبيق يشتغل ✅

---

## 🔴 المرحلة الثانية: Shared Layer

### [ ] Task 4 — إنشاء `src/shared/api/` ونقل http.ts

**الهدف:** نقل HTTP client الأساسي إلى shared.

| الملف الحالي | الموقع الجديد |
|---|---|
| `src/api/http.ts` (6.1 KB) | `src/shared/api/http.ts` |
| `src/api/client401.test.ts` (2.4 KB) | `src/shared/api/client401.test.ts` |
| `src/api/tokenStorage.test.ts` (2.3 KB) | `src/shared/api/tokenStorage.test.ts` |

**ملاحظة:** `src/api/client.ts` (1.5 KB) — راجع محتواه لدمجه مع http.ts (سيُحذف في Task 22).

**خطوات التنفيذ:**
1. أنشئ `src/shared/api/`
2. انقل الملفات الثلاثة
3. أنشئ `src/shared/api/index.ts` يُصدّر ما يحتاجه الخارج:
   ```ts
   export { http } from './http';
   ```
4. ابحث عن كل imports من `../api/http` أو `../../api/http` وحدّثها مؤقتاً

**التحقق:** `npm run build` ✅ | `npm test -- http` ✅

---

### [ ] Task 5 — إنشاء `src/shared/ui/` ونقل المكونات الأساسية

**الهدف:** نقل كل UI primitives المشتركة إلى shared.

**الملفات المنقولة من `src/platform/ui/`:**

| الملف الحالي | الموقع الجديد |
|---|---|
| `src/platform/ui/Button.tsx` | `src/shared/ui/Button/index.tsx` |
| `src/platform/ui/Button.test.tsx` | `src/shared/ui/Button/Button.test.tsx` |
| `src/platform/ui/Modal.tsx` | `src/shared/ui/Modal/index.tsx` |
| `src/platform/ui/FormField.tsx` | `src/shared/ui/FormField/index.tsx` |
| `src/platform/ui/FormField.test.tsx` | `src/shared/ui/FormField/FormField.test.tsx` |
| `src/platform/ui/Bilingual.tsx` | `src/shared/ui/Bilingual/index.tsx` |
| `src/platform/ui/Bilingual.test.tsx` | `src/shared/ui/Bilingual/Bilingual.test.tsx` |
| `src/platform/ui/PageHero.tsx` | `src/shared/ui/PageHero/index.tsx` |
| `src/platform/ui/SkipToContent.tsx` | `src/shared/ui/SkipToContent/index.tsx` |
| `src/platform/ui/ConfirmDialog.tsx` | `src/shared/ui/ConfirmDialog/index.tsx` |
| `src/platform/ui/ConfirmDialog.test.tsx` | `src/shared/ui/ConfirmDialog/ConfirmDialog.test.tsx` |

**الملفات المنقولة من `src/components/` و `src/platform/components/`:**

| الملف الحالي | الموقع الجديد |
|---|---|
| `src/components/JEALogo.tsx` | `src/shared/ui/JEALogo/index.tsx` |
| `src/platform/components/ErrorBoundary.tsx` | `src/shared/ui/ErrorBoundary/index.tsx` |
| `src/platform/components/ErrorBoundary.test.tsx` | `src/shared/ui/ErrorBoundary/ErrorBoundary.test.tsx` |

**ملفات تبقى في platform/ui (مرتبطة بـ domain — راجع مكانها لاحقاً):**
- `src/platform/ui/ManualReferenceIcon.tsx`
- `src/platform/ui/ReportsPanel.tsx`
- `src/platform/ui/PendingManualEditsTile.tsx`

**خطوات التنفيذ:**
1. أنشئ كل مجلد: `src/shared/ui/{Button,Modal,FormField,Bilingual,PageHero,SkipToContent,JEALogo,ErrorBoundary,ConfirmDialog}/`
2. انقل كل ملف إلى مجلده مع اختباره
3. أنشئ `src/shared/ui/index.ts`:
   ```ts
   export { Button } from './Button';
   export { Modal } from './Modal';
   export { FormField } from './FormField';
   export { Bilingual } from './Bilingual';
   export { PageHero } from './PageHero';
   export { SkipToContent } from './SkipToContent';
   export { JEALogo } from './JEALogo';
   export { ErrorBoundary } from './ErrorBoundary';
   export { ConfirmDialog } from './ConfirmDialog';
   ```

**التحقق:** `npm run build` ✅ | `npm test -- shared/ui` ✅

---

### [ ] Task 6 — إنشاء `src/shared/types/` وتقسيم ملفات الأنواع

**الهدف:** توزيع `src/types/` إلى ملفات منفصلة بحسب الـ domain.

**الملفات المصدر:**

| الملف الحالي | الحجم |
|---|---|
| `src/types/index.ts` | 663 B |
| `src/types/jea.ts` | 11.6 KB |
| `src/types/platform.ts` | 1.8 KB |

**الملفات الجديدة:**

| الملف الجديد | المحتوى المنقول من |
|---|---|
| `src/shared/types/application.ts` | types تخص Application/Workflow من `jea.ts` |
| `src/shared/types/service.ts` | types تخص Service/Schema من `jea.ts` |
| `src/shared/types/user.ts` | types تخص User/Engineer من `jea.ts` + `platform.ts` |
| `src/shared/types/schema.ts` | types تخص DynamicForm/Schema fields من `jea.ts` |
| `src/shared/types/index.ts` | `export * from './application'` إلخ |

**خطوات التنفيذ:**
1. افتح `src/types/jea.ts` وصنّف كل type حسب domain
2. أنشئ الملفات الأربعة بالتوزيع المناسب
3. أنشئ `src/shared/types/index.ts`
4. اجعل `src/types/index.ts` يعيد export مؤقتاً:
   ```ts
   export * from '../shared/types';
   ```

**التحقق:** `npm run build` ✅ | لا type errors ✅

---

### [ ] Task 7 — إنشاء `src/shared/lib/i18n/` ونقل i18n

**الهدف:** نقل `src/i18n/` بالكامل إلى `src/shared/lib/i18n/`.

| الملف الحالي | الموقع الجديد |
|---|---|
| `src/i18n/index.ts` (2.4 KB) | `src/shared/lib/i18n/index.ts` |
| `src/i18n/i18n.test.ts` (2.5 KB) | `src/shared/lib/i18n/i18n.test.ts` |
| `src/i18n/locales/` (المجلد كله) | `src/shared/lib/i18n/locales/` |

**خطوات التنفيذ:**
1. أنشئ `src/shared/lib/i18n/`
2. انقل الملفات + مجلد locales
3. حدّث `src/app/main.tsx`: غيّر `import './i18n'` → `import '../shared/lib/i18n'` (مؤقتاً حتى Task 23)
4. ابحث عن كل imports من `../../i18n`:
   ```powershell
   Select-String -Path "frontend/src/**/*.ts*" -Pattern "from.*i18n" -Recurse
   ```

**التحقق:** `npm run build` ✅ | `npm test -- i18n` ✅ | تبديل اللغة يعمل ✅

---

### [ ] Task 8 — إنشاء `src/shared/utils/` ونقل الـ Helpers

**الهدف:** جمع كل utility functions المشتركة.

| الملف الحالي | الموقع الجديد |
|---|---|
| `src/modules/JeaServices/pages/saveErrorHelpers.ts` (2 KB) | `src/shared/utils/errorHelpers.ts` |
| `src/modules/JeaServices/pages/saveErrorHelpers.test.ts` (2.7 KB) | `src/shared/utils/errorHelpers.test.ts` |
| `src/platform/utils/errorMessage.ts` (958 B) | `src/shared/utils/errorMessage.ts` |
| `src/platform/utils/errorMessage.test.ts` (1.8 KB) | `src/shared/utils/errorMessage.test.ts` |
| `src/platform/utils/csv.ts` (1.8 KB) | `src/shared/utils/csv.ts` |
| `src/platform/utils/csv.test.ts` (2.2 KB) | `src/shared/utils/csv.test.ts` |
| `src/platform/utils/useSortableRows.ts` (1.9 KB) | `src/shared/utils/useSortableRows.ts` |
| `src/platform/utils/useSortableRows.test.ts` (2.4 KB) | `src/shared/utils/useSortableRows.test.ts` |
| `src/platform/utils/SortHeader.tsx` (1.4 KB) | `src/shared/utils/SortHeader.tsx` |

**ملفات تبقى (domain-specific — تنتقل لـ features لاحقاً):**
- `src/modules/JeaServices/pages/applyErrorHelpers.ts` → `features/apply-service/lib/`
- `src/modules/JeaServices/pages/missingRequiredDocs.ts` → `features/apply-service/lib/`

**خطوات التنفيذ:**
1. أنشئ `src/shared/utils/`
2. انقل الملفات مع اختباراتها
3. أنشئ `src/shared/utils/index.ts`

**التحقق:** `npm run build` ✅ | `npm test -- shared/utils` ✅

---

## 🟡 المرحلة الثالثة: Entities Layer

### [ ] Task 9 — إنشاء `src/entities/application/`

**الهدف:** تجميع كل ما يخص كيان الطلب (Application).

**هيكل المجلد:**
```
src/entities/application/
├── api/
│   └── index.ts
├── model/
│   ├── model.ts
│   └── hooks.ts
├── ui/
│   ├── ExpiryBadge/
│   └── PhaseBadge/
└── index.ts
```

| الملف الحالي | الموقع الجديد |
|---|---|
| `src/api/applications.ts` (3.4 KB) | `src/entities/application/api/index.ts` |
| `src/api/applicationsCreate.test.ts` (1.9 KB) | `src/entities/application/api/applicationsCreate.test.ts` |
| `src/modules/JeaServices/pages/applicationStatus.ts` (1.3 KB) | `src/entities/application/model/model.ts` |
| `src/modules/JeaServices/pages/applicationStatus.test.ts` (1.9 KB) | `src/entities/application/model/model.test.ts` |
| `src/components/ui/ExpiryBadge.tsx` (2.8 KB) | `src/entities/application/ui/ExpiryBadge/index.tsx` |
| `src/components/ui/ExpiryBadge.test.tsx` (3.1 KB) | `src/entities/application/ui/ExpiryBadge/ExpiryBadge.test.tsx` |
| `src/components/ui/PhaseBadge.tsx` (2.4 KB) | `src/entities/application/ui/PhaseBadge/index.tsx` |
| `src/components/ui/PhaseBadge.test.tsx` (2.3 KB) | `src/entities/application/ui/PhaseBadge/PhaseBadge.test.tsx` |

**إضافي:** استخرج application-related hooks من `src/api/hooks.ts` → `src/entities/application/model/hooks.ts`

**أنشئ `src/entities/application/index.ts`:**
```ts
export type { Application, ApplicationStatus } from './model/model';
export { useApplications, useApplicationById } from './model/hooks';
export { fetchApplications, createApplication } from './api';
export { ExpiryBadge } from './ui/ExpiryBadge';
export { PhaseBadge } from './ui/PhaseBadge';
```

**التحقق:** `npm run build` ✅ | `npm test -- entities/application` ✅

---

### [ ] Task 10 — إنشاء `src/entities/service/`

**الهدف:** تجميع كل ما يخص كيان الخدمة (Service).

```
src/entities/service/
├── api/
│   └── index.ts
├── model/
│   └── hooks.ts
├── ui/
│   └── ServiceInfoCard/
└── index.ts
```

| الملف الحالي | الموقع الجديد |
|---|---|
| `src/api/services.ts` (408 B) | `src/entities/service/api/index.ts` |
| `src/components/ui/ServiceInfoCard.tsx` (3.5 KB) | `src/entities/service/ui/ServiceInfoCard/index.tsx` |
| `src/components/ui/ServiceInfoCard.test.tsx` (2.7 KB) | `src/entities/service/ui/ServiceInfoCard/ServiceInfoCard.test.tsx` |

**إضافي:**
- استخرج service hooks من `src/api/hooks.ts` → `src/entities/service/model/hooks.ts`
- انقل الجزء المتعلق بـ services من `src/api/jea/admin.ts` → `src/entities/service/api/`

**التحقق:** `npm run build` ✅

---

### [ ] Task 11 — إنشاء `src/entities/workflow/`

**الهدف:** تجميع كل ما يخص Workflow والـ stages.

```
src/entities/workflow/
├── lib/
│   └── workflowRolePath.ts
├── ui/
│   ├── WorkflowStepper/
│   └── MiniStageTimeline/
└── index.ts
```

| الملف الحالي | الموقع الجديد |
|---|---|
| `src/engine/workflowRolePath.ts` (2.3 KB) | `src/entities/workflow/lib/workflowRolePath.ts` |
| `src/engine/workflowRolePath.test.ts` (3.6 KB) | `src/entities/workflow/lib/workflowRolePath.test.ts` |
| `src/components/ui/WorkflowStepper.tsx` (8.6 KB) | `src/entities/workflow/ui/WorkflowStepper/index.tsx` |
| `src/components/ui/WorkflowStepper.test.tsx` (4.9 KB) | `src/entities/workflow/ui/WorkflowStepper/WorkflowStepper.test.tsx` |
| `src/modules/JeaServices/pages/MiniStageTimeline.tsx` (2.6 KB) | `src/entities/workflow/ui/MiniStageTimeline/index.tsx` |
| `src/modules/JeaServices/pages/MiniStageTimeline.test.tsx` (2.4 KB) | `src/entities/workflow/ui/MiniStageTimeline/MiniStageTimeline.test.tsx` |

**التحقق:** `npm run build` ✅

---

### [ ] Task 12 — إنشاء `src/entities/quota/`

**الهدف:** تجميع كل ما يخص Quota والامتثال.

| الملف الحالي | الموقع الجديد |
|---|---|
| `src/components/ui/QuotaCard.tsx` (7.9 KB) | `src/entities/quota/ui/QuotaCard/index.tsx` |
| `src/components/ui/QuotaCard.test.tsx` (3 KB) | `src/entities/quota/ui/QuotaCard/QuotaCard.test.tsx` |
| `src/components/ui/ComplianceNotesBanner.tsx` (3 KB) | `src/entities/quota/ui/ComplianceNotesBanner/index.tsx` |
| `src/components/ui/ComplianceNotesBanner.test.tsx` (3.8 KB) | `src/entities/quota/ui/ComplianceNotesBanner/ComplianceNotesBanner.test.tsx` |

**أنشئ `src/entities/quota/index.ts`** يُصدّر `QuotaCard` و `ComplianceNotesBanner`.

**التحقق:** `npm run build` ✅

---

### [ ] Task 13 — إنشاء `src/entities/user/` و `src/entities/engineer/`

**الهدف:** تجميع كل ما يخص User وEngineer.

**`src/entities/user/`:**

| الملف الحالي | الموقع الجديد |
|---|---|
| `src/api/users.ts` (770 B) | `src/entities/user/api/index.ts` |
| `src/components/ui/RolePathBadge.tsx` (1.9 KB) | `src/entities/user/ui/RolePathBadge/index.tsx` |
| `src/components/ui/RolePathBadge.test.tsx` (1.8 KB) | `src/entities/user/ui/RolePathBadge/RolePathBadge.test.tsx` |

**`src/entities/engineer/`:**

| الملف الحالي | الموقع الجديد |
|---|---|
| `src/api/engineers.ts` (742 B) | `src/entities/engineer/api/index.ts` |

**إضافي:** انقل الجزء المتعلق بـ users/engineers من `src/api/jea/admin.ts` للـ entity المناسبة.

**التحقق:** `npm run build` ✅

---

## 🟡 المرحلة الرابعة: Features Layer

### [ ] Task 14 — إنشاء `src/features/auth/`

**الهدف:** تجميع كل منطق المصادقة.

```
src/features/auth/
├── api/
│   └── index.ts
├── model/
│   ├── AuthContext.tsx
│   └── AuthProvider.tsx
├── ui/
│   └── LoginPage.tsx
└── index.ts
```

| الملف الحالي | الموقع الجديد |
|---|---|
| `src/api/auth.ts` (1.4 KB) | `src/features/auth/api/index.ts` |
| `src/auth/AuthContext.tsx` (1.2 KB) | `src/features/auth/model/AuthContext.tsx` |
| `src/auth/AuthProvider.tsx` (9.9 KB) | `src/features/auth/model/AuthProvider.tsx` |
| `src/auth/AuthProvider.test.tsx` (7 KB) | `src/features/auth/model/AuthProvider.test.tsx` |
| `src/auth/LoginPage.tsx` (9.2 KB) | `src/features/auth/ui/LoginPage.tsx` |

**ملاحظة:** `src/auth/guards.tsx` انتقل في Task 2 → `src/app/router/guards.tsx`

**أنشئ `src/features/auth/index.ts`:**
```ts
export { AuthProvider } from './model/AuthProvider';
export { useAuth } from './model/AuthContext';
export type { AuthUser } from './model/AuthContext';
export { LoginPage } from './ui/LoginPage';
```

**حدّث** `src/app/App.tsx` و `src/app/router/guards.tsx` ليستوردا من `@features/auth`.

**التحقق:** `npm run build` ✅ | `npm test -- features/auth` ✅ | Login يعمل ✅

---

### [ ] Task 15 — إنشاء `src/features/notifications/`

**الهدف:** تجميع منطق الإشعارات.

| الملف الحالي | الموقع الجديد |
|---|---|
| `src/api/notifications.ts` (1.5 KB) | `src/features/notifications/api/index.ts` |
| `src/platform/components/NotificationBell.tsx` (6.9 KB) | `src/features/notifications/ui/NotificationBell/index.tsx` |
| `src/platform/components/NotificationBell.test.tsx` (4.8 KB) | `src/features/notifications/ui/NotificationBell/NotificationBell.test.tsx` |

**أنشئ `src/features/notifications/index.ts`** يُصدّر `NotificationBell`.

**التحقق:** `npm run build` ✅

---

### [ ] Task 16 — إنشاء `src/features/language-switch/`

**الهدف:** عزل منطق تبديل اللغة.

| الملف الحالي | الموقع الجديد |
|---|---|
| `src/platform/components/LanguageSwitcher.tsx` (1.9 KB) | `src/features/language-switch/ui/LanguageSwitcher/index.tsx` |
| `src/platform/components/LanguageSwitcher.test.tsx` (1.6 KB) | `src/features/language-switch/ui/LanguageSwitcher/LanguageSwitcher.test.tsx` |

**أنشئ `src/features/language-switch/index.ts`** يُصدّر `LanguageSwitcher`.

**التحقق:** `npm run build` ✅ | تبديل اللغة يعمل ✅

---

### [ ] Task 16b — إنشاء `src/features/apply-service/`

**الهدف:** تجميع منطق تقديم الطلب.

| الملف الحالي | الموقع الجديد |
|---|---|
| `src/modules/JeaServices/pages/applyErrorHelpers.ts` (4.4 KB) | `src/features/apply-service/lib/applyErrorHelpers.ts` |
| `src/modules/JeaServices/pages/applyErrorHelpers.test.ts` (4.9 KB) | `src/features/apply-service/lib/applyErrorHelpers.test.ts` |
| `src/modules/JeaServices/pages/missingRequiredDocs.ts` (1.4 KB) | `src/features/apply-service/lib/missingRequiredDocs.ts` |
| `src/modules/JeaServices/pages/missingRequiredDocs.test.ts` (3.5 KB) | `src/features/apply-service/lib/missingRequiredDocs.test.ts` |
| `src/api/myOffice.ts` (1.9 KB) | `src/features/apply-service/api/myOffice.ts` |

**أنشئ `src/features/apply-service/index.ts`.**

**التحقق:** `npm run build` ✅

---

### [ ] Task 16c — إنشاء `src/features/review-application/`

**الهدف:** تجميع منطق مراجعة الطلبات.

| الملف الحالي | الموقع الجديد |
|---|---|
| `src/api/review.ts` (1.9 KB) | `src/features/review-application/api/index.ts` |

**أنشئ `src/features/review-application/index.ts`.**

**التحقق:** `npm run build` ✅

---

### [ ] Task 16d — إنشاء `src/features/manage-services/`

**الهدف:** تجميع منطق إدارة الخدمات من جهة الـ Admin.

| الملف الحالي | الموقع الجديد |
|---|---|
| `src/api/admin.ts` (1.3 KB) | `src/features/manage-services/api/admin.ts` |
| `src/api/jea/admin.ts` (القسم المتعلق بالخدمات — 13.3 KB) | `src/features/manage-services/api/index.ts` |
| `src/api/jea/hooks.ts` (hooks المتعلقة بالخدمات — 6.1 KB) | `src/features/manage-services/model/hooks.ts` |
| `src/api/integration.ts` (2.1 KB) | `src/features/manage-services/api/integration.ts` |
| `src/api/projects.ts` (1.3 KB) | `src/features/manage-services/api/projects.ts` |

**ملاحظة:** `src/api/jea/admin.ts` ضخم (13 KB) — راجع محتواه بدقة للتقسيم الصحيح بين entities وfeatures.

**أنشئ `src/features/manage-services/index.ts`.**

**التحقق:** `npm run build` ✅

---

## 🟡 المرحلة الخامسة: Widgets Layer

### [ ] Task 17 — إنشاء `src/widgets/AppLayout/`, `AppHeader/`, `AppSidebar/`

**الهدف:** نقل `src/layout/` (8 ملفات) بالكامل إلى widgets منفصلة.

| الملف الحالي | الموقع الجديد |
|---|---|
| `src/layout/Layout.tsx` (2.9 KB) | `src/widgets/AppLayout/index.tsx` |
| `src/layout/RouteSuspense.tsx` (846 B) | `src/widgets/AppLayout/RouteSuspense.tsx` |
| `src/layout/Header.tsx` (2.6 KB) | `src/widgets/AppHeader/index.tsx` |
| `src/layout/pageTitle.ts` (1 KB) | `src/widgets/AppHeader/pageTitle.ts` |
| `src/layout/pageTitle.test.ts` (1.3 KB) | `src/widgets/AppHeader/pageTitle.test.ts` |
| `src/layout/SidebarContent.tsx` (2.6 KB) | `src/widgets/AppSidebar/index.tsx` |
| `src/layout/navItems.tsx` (4.3 KB) | `src/widgets/AppSidebar/navItems.tsx` |
| `src/layout/navItems.test.tsx` (5.3 KB) | `src/widgets/AppSidebar/navItems.test.tsx` |

**إضافي:** أنشئ `src/widgets/NotificationCenter/index.tsx` يجمع `NotificationBell` من `@features/notifications`.

**خطوات التنفيذ:**
1. أنشئ مجلدات `AppLayout/`, `AppHeader/`, `AppSidebar/`, `NotificationCenter/`
2. انقل الملفات مع تحديث imports:
   - `Header.tsx` يستورد `NotificationBell` من `@features/notifications`
   - `Header.tsx` يستورد `LanguageSwitcher` من `@features/language-switch`
3. أنشئ `index.ts` لكل widget
4. حدّث `src/app/router/index.tsx`: غيّر import من `./layout/Layout` → `@widgets/AppLayout`

**التحقق:** `npm run build` ✅ | الـ Layout يظهر صح ✅ | `npm test -- widgets` ✅

---

### [ ] Task 18 — إنشاء `src/widgets/ServiceEditor/`

**الهدف:** تقسيم `EditService.tsx` (28.9 KB) إلى مكونات منطقية.

```
src/widgets/ServiceEditor/
├── index.tsx
├── SchemaEditorTab.tsx
├── PreviewTab.tsx
├── AiAssistantTab.tsx
└── index.ts
```

**الملفات المصدر:**
- `src/modules/JeaServices/pages/EditService.tsx` (28.9 KB)
- `src/modules/JeaServices/pages/EditService.applyAndSave.test.tsx` (7 KB)

**خطوات التنفيذ:**
1. افتح `EditService.tsx` وحدد المنطق الخاص بكل tab
2. أنشئ `SchemaEditorTab.tsx`: قسم تحرير الـ schema/fields
3. أنشئ `PreviewTab.tsx`: قسم المعاينة
4. أنشئ `AiAssistantTab.tsx`: قسم الـ AI assistant
5. أنشئ `index.tsx`: يجمع الـ tabs ويديرها
6. انقل `EditService.applyAndSave.test.tsx` → `src/widgets/ServiceEditor/ServiceEditor.test.tsx`
7. أنشئ صفحة thin: `src/pages/admin/services/EditServicePage.tsx` تستورد `ServiceEditor` فقط

**التحقق:** `npm run build` ✅ | Edit service يعمل ✅

---

## 🟡 المرحلة السادسة: Engine & Pages

### [ ] Task 19 — تقسيم `DynamicForm.tsx` (18.4 KB) وإنشاء `src/shared/engine/`

**الهدف:** تقسيم DynamicForm إلى components منفصلة قابلة للصيانة.

```
src/shared/engine/DynamicForm/
├── index.tsx
├── FieldWrapper.tsx
├── FieldInput.tsx
├── DynamicSelect.tsx
└── validation.ts
```

| الملف الحالي | الموقع الجديد |
|---|---|
| `src/engine/DynamicForm.tsx` (18.4 KB) | مقسّم في `src/shared/engine/DynamicForm/` |
| `src/engine/DynamicForm.i18n.test.ts` (2.9 KB) | `src/shared/engine/DynamicForm/DynamicForm.i18n.test.ts` |
| `src/engine/DynamicForm.order.test.tsx` (2.4 KB) | `src/shared/engine/DynamicForm/DynamicForm.order.test.tsx` |
| `src/engine/DynamicForm.validateAll.test.ts` (4.2 KB) | `src/shared/engine/DynamicForm/DynamicForm.validateAll.test.ts` |
| `src/engine/DocumentUploader.tsx` (6 KB) | `src/shared/engine/DocumentUploader.tsx` |
| `src/engine/DocumentPreviewCard.tsx` (5.1 KB) | `src/shared/engine/DocumentPreviewCard/index.tsx` |
| `src/engine/DocumentPreviewCard.test.tsx` (4.5 KB) | `src/shared/engine/DocumentPreviewCard/DocumentPreviewCard.test.tsx` |
| `src/engine/workflowRolePath.ts` | → انتقل في Task 11 |

**خطوات التنفيذ:**
1. أنشئ `src/shared/engine/DynamicForm/`
2. افتح `DynamicForm.tsx` وحدد المسؤولية الواحدة لكل component
3. أنشئ الملفات الخمسة بالتقسيم المنطقي:
   - `FieldWrapper.tsx`: wrapper يحيط كل field (label, error, required indicator)
   - `FieldInput.tsx`: يعالج أنواع الـ inputs (text, number, date, textarea)
   - `DynamicSelect.tsx`: يعالج الـ select/multiselect مع async options
   - `validation.ts`: كل منطق التحقق
   - `index.tsx`: الـ DynamicForm الرئيسي يجمعها
4. أنشئ `src/shared/engine/index.ts`:
   ```ts
   export { DynamicForm } from './DynamicForm';
   export { DocumentUploader } from './DocumentUploader';
   export { DocumentPreviewCard } from './DocumentPreviewCard';
   ```

**التحقق:** `npm run build` ✅ | `npm test -- shared/engine` ✅ | النماذج الديناميكية تعمل ✅

---

### [ ] Task 20 — تقسيم `NewService.tsx` (42.8 KB)

**الهدف:** تقسيم أكبر ملف في المشروع إلى أجزاء منطقية.

```
src/widgets/ServiceEditor/NewServiceForm/
├── index.tsx
├── BasicInfoSection.tsx
├── SchemaBuilderSection.tsx
├── WorkflowSection.tsx
├── QuotaSection.tsx
└── SubmitSection.tsx
```

**الملف المصدر:** `src/modules/JeaServices/pages/NewService.tsx` (42.8 KB)

**خطوات التنفيذ:**
1. افتح `NewService.tsx` وحدد الـ sections الرئيسية
2. أنشئ ملف لكل section منطقية
3. `index.tsx`: يجمع الـ sections ويدير الـ state المشترك
4. أنشئ صفحة thin: `src/pages/admin/services/NewServicePage.tsx` تستورد `NewServiceForm` فقط

**التحقق:** `npm run build` ✅ | إنشاء خدمة جديدة يعمل ✅

---

### [ ] Task 21 — تنظيف `pages/` — جعل كل صفحة Thin

**الهدف:** كل صفحة تكون thin (تجميع فقط، لا منطق).

**الصفحات الجديدة:**

| الملف الحالي | الصفحة الجديدة الـ thin |
|---|---|
| `src/modules/JeaServices/pages/AdminDashboard.tsx` (10.6 KB) | `src/pages/admin/DashboardPage.tsx` |
| `src/modules/JeaServices/pages/AdminApplications.tsx` (9 KB) | `src/pages/admin/ApplicationsPage.tsx` |
| `src/modules/JeaServices/pages/ServicesList.tsx` (11.1 KB) | `src/pages/admin/services/ServicesListPage.tsx` |
| `src/platform/pages/admin/UserManagement.tsx` (15.4 KB) | `src/pages/admin/UserManagementPage.tsx` |
| `src/modules/JeaServices/pages/ServiceFeesAdmin.tsx` (17.5 KB) | `src/pages/admin/ServiceFeesPage.tsx` |
| `src/modules/JeaDiscipline/pages/ComplaintsAdmin.tsx` (18 KB) | `src/pages/admin/discipline/ComplaintsPage.tsx` |
| `src/modules/JeaDiscipline/pages/LegalFinesAdmin.tsx` (21.1 KB) | `src/pages/admin/discipline/LegalFinesPage.tsx` |
| `src/modules/JeaDiscipline/pages/SupervisionTransfersAdmin.tsx` (20.9 KB) | `src/pages/admin/discipline/SupervisionTransfersPage.tsx` |
| `src/modules/JeaServices/pages/Dashboard.tsx` (12.8 KB) | `src/pages/applicant/DashboardPage.tsx` |
| `src/modules/JeaServices/pages/Apply.tsx` (30.7 KB) | `src/pages/applicant/ApplyPage.tsx` |
| `src/modules/JeaServices/pages/MyApplications.tsx` (9.8 KB) | `src/pages/applicant/MyApplicationsPage.tsx` |
| `src/modules/JeaServices/pages/ApplicationDetail.tsx` (18.7 KB) | `src/pages/applicant/ApplicationDetailPage.tsx` |
| `src/modules/JeaServices/pages/ServiceList.tsx` (5.8 KB) | `src/pages/applicant/ServiceListPage.tsx` |
| `src/modules/JeaServices/pages/CategoryServicesView.tsx` (11.7 KB) | `src/pages/applicant/CategoryServicesViewPage.tsx` |
| `src/modules/JeaServices/pages/reviewer/ReviewQueue.tsx` (6.5 KB) | `src/pages/reviewer/ReviewQueuePage.tsx` |
| `src/modules/JeaServices/pages/reviewer/ReviewPanel.tsx` (21.8 KB) | `src/pages/reviewer/ReviewPanelPage.tsx` |
| `src/modules/JeaServices/pages/reviewer/ReviewDashboard.tsx` (13.3 KB) | `src/pages/reviewer/ReviewDashboardPage.tsx` |
| `src/auth/LoginPage.tsx` | → انتقل في Task 14 → `src/pages/auth/LoginPage.tsx` (thin wrapper) |
| `src/pages/applicant/ApplicationDetail.tsx` (12.1 KB) | → دمجه مع ApplicationDetailPage أعلاه |

**الصفحات المتبقية (حدد معالجتها):**
- `src/modules/JeaProjects/pages/` (11 ملف) → `src/pages/projects/` أو إبقاؤها منفصلة
- `src/modules/JeaDues/pages/OfficeDues.tsx` → `src/pages/admin/dues/OfficeDuesPage.tsx`
- `src/integrations/Nashmi/pages/` → `src/pages/admin/integration/`
- `src/platform/pages/auth/ChangeCredentials.tsx` → `src/pages/auth/ChangeCredentialsPage.tsx`
- `src/platform/pages/auth/Profile.tsx` → `src/pages/auth/ProfilePage.tsx`

> ⚠️ **`Apply.tsx` (30.7 KB)** — كبير جداً، يحتاج تقسيماً إضافياً مثل NewService. قد ينتقل بعضه لـ `features/apply-service/`.

**خطوات التنفيذ:**
1. أنشئ مجلدات `pages/{admin,applicant,reviewer,auth,projects}/`
2. لكل صفحة: أنشئ thin wrapper يستورد المحتوى الرئيسي من widget/feature
3. حدّث `src/app/router/` لاستيراد الصفحات من المسارات الجديدة

**التحقق:** `npm run build` ✅ | كل routes تعمل ✅

---

## 🔵 المرحلة السابعة: Cleanup

### [ ] Task 22 — حذف `src/api/client.ts` وتوحيد الـ Imports

**الهدف:** إزالة الملف القديم وضمان استخدام `http.ts` فقط.

**خطوات التنفيذ:**
1. افتح `src/api/client.ts` (1.5 KB) وراجع محتواه
2. إذا كان يصدّر axios instance → دمجه مع `src/shared/api/http.ts`
3. ابحث عن كل imports:
   ```powershell
   Select-String -Path "frontend/src" -Pattern "from.*['\"].*client['\"]" -Recurse
   ```
4. عدّل كل import لتشير لـ `@shared/api`
5. احذف `src/api/client.ts`

**التحقق:** `npm run build` ✅ | لا يوجد import من `client.ts` ✅

---

### [ ] Task 23 — تحديث `tsconfig.json` وإضافة Path Aliases

**الهدف:** تعريف aliases لكل طبقة FSD.

**تحديث `frontend/tsconfig.json`:**
```json
{
  "compilerOptions": {
    "paths": {
      "@app/*":      ["./src/app/*"],
      "@pages/*":    ["./src/pages/*"],
      "@widgets/*":  ["./src/widgets/*"],
      "@features/*": ["./src/features/*"],
      "@entities/*": ["./src/entities/*"],
      "@shared/*":   ["./src/shared/*"]
    }
  }
}
```

**تحديث `frontend/vite.config.ts`:**
```ts
import path from 'path';
// في resolve.alias:
'@app':      path.resolve(__dirname, './src/app'),
'@pages':    path.resolve(__dirname, './src/pages'),
'@widgets':  path.resolve(__dirname, './src/widgets'),
'@features': path.resolve(__dirname, './src/features'),
'@entities': path.resolve(__dirname, './src/entities'),
'@shared':   path.resolve(__dirname, './src/shared'),
```

**احذف** alias `@engine` القديم من كلا الملفين إن وُجد.

**التحقق:** `npm run build` ✅ | TypeScript لا يُبلّغ عن path errors ✅

---

### [ ] Task 24 — توحيد ملفات الاختبار

**الهدف:** كل test file يكون بجانب ملفه الأصلي.

**خطوات التنفيذ:**
1. تحقق من كل test file لم ينتقل بعد:
   ```powershell
   Get-ChildItem -Path "frontend/src" -Recurse -Filter "*.test.*" | Where-Object { $_.DirectoryName -notmatch $_.BaseName.Replace('.test','') }
   ```
2. انقل أي test file إلى نفس مجلد الملف الأصلي
3. حدّث `vitest.config.ts` إن كان يرفرنس مسارات قديمة

**التحقق:** `npm test` كل الاختبارات تجتاز ✅

---

## 🟢 المرحلة الثامنة: Engine Relocation

### [ ] Task 25 — تنظيف `src/engine/` النهائي

**الهدف:** التأكد من أن `src/engine/` فارغ تماماً وحذفه.

> ⚠️ معظم الملفات انتقلت في Task 19 (DynamicForm) و Task 11 (workflowRolePath).

**خطوات التنفيذ:**
1. تحقق من الملفات المتبقية:
   ```powershell
   Get-ChildItem -Path "frontend/src/engine" -Recurse
   ```
2. انقل أي ملف متبقٍّ إلى `src/shared/engine/`
3. احذف `src/engine/` بالكامل
4. ابحث عن كل imports من `@engine`:
   ```powershell
   Select-String -Path "frontend/src" -Pattern "@engine|from.*['\"].*\/engine" -Recurse
   ```
5. حدّث كل import → `@shared/engine`
6. احذف alias `@engine` من `tsconfig.json` و `vite.config.ts`

**التحقق:** `npm run build` ✅ | لا يوجد أي import من `@engine` ✅

---

## 🟢 المرحلة التاسعة: Public API لكل Slice

### [ ] Task 26 — مراجعة وإتمام `index.ts` لكل Entity

**الهدف:** كل entity تصدّر فقط ما هو مطلوب خارجياً.

**الملفات:**
```
src/entities/application/index.ts
src/entities/service/index.ts
src/entities/user/index.ts
src/entities/engineer/index.ts
src/entities/workflow/index.ts
src/entities/quota/index.ts
```

**مثال `src/entities/application/index.ts`:**
```ts
export type { Application, ApplicationStatus, ApplicationPhase } from './model/model';
export { useApplications, useApplicationById } from './model/hooks';
export { fetchApplications, createApplication, updateApplication } from './api';
export { ExpiryBadge } from './ui/ExpiryBadge';
export { PhaseBadge } from './ui/PhaseBadge';
```

**القواعد:**
- لا `export *` عشوائية
- لا تصدّر implementation details
- كل ما يحتاجه خارج الـ entity يجب أن يكون هنا

**التحقق:** `npm run build` ✅ | لا type errors ✅

---

### [ ] Task 27 — مراجعة وإتمام `index.ts` لكل Feature

**الهدف:** كل feature تصدّر فقط واجهتها الخارجية.

**الملفات:**
```
src/features/auth/index.ts
src/features/apply-service/index.ts
src/features/review-application/index.ts
src/features/manage-services/index.ts
src/features/notifications/index.ts
src/features/language-switch/index.ts
```

**مثال `src/features/auth/index.ts`:**
```ts
export { AuthProvider } from './model/AuthProvider';
export { useAuth } from './model/AuthContext';
export type { AuthUser, AuthState } from './model/AuthContext';
export { LoginPage } from './ui/LoginPage';
// لا تصدّر api functions مباشرة — استخدمها داخلياً فقط
```

**التحقق:** `npm run build` ✅

---

### [ ] Task 28 — الاستبدال الشامل للـ Imports + حذف المجلدات القديمة

**الهدف:** توحيد كل imports لتستخدم الـ Public API + aliases.

**الاستبدالات المطلوبة:**

```ts
// ❌ قديم
import { AuthProvider } from '../../auth/AuthProvider';
import { Button } from '../../platform/ui/Button';
import { http } from '../../api/http';
import { ExpiryBadge } from '../../components/ui/ExpiryBadge';

// ✅ جديد
import { AuthProvider } from '@features/auth';
import { Button } from '@shared/ui';
import { http } from '@shared/api';
import { ExpiryBadge } from '@entities/application';
```

**خطوات التنفيذ:**
1. أنشئ script PowerShell للبحث عن كل import paths القديمة:
   ```powershell
   Select-String -Path "frontend/src" -Pattern "from.*['\"].*(\/auth\/|\/api\/|\/layout\/|\/components\/|\/engine\/|\/modules\/|\/platform\/|\/i18n\/|\/types\/)" -Recurse
   ```
2. استبدل يدوياً أو باستخدام sed/regex
3. شغّل `npm run build` وأصلح كل خطأ
4. شغّل `npm test` وتأكد من نجاح كل الاختبارات
5. **بعد التأكد التام:** احذف المجلدات القديمة:
   ```powershell
   Remove-Item -Recurse -Force frontend/src/api
   Remove-Item -Recurse -Force frontend/src/auth
   Remove-Item -Recurse -Force frontend/src/layout
   Remove-Item -Recurse -Force frontend/src/components
   Remove-Item -Recurse -Force frontend/src/engine
   Remove-Item -Recurse -Force frontend/src/modules
   Remove-Item -Recurse -Force frontend/src/platform
   Remove-Item -Recurse -Force frontend/src/i18n
   Remove-Item -Recurse -Force frontend/src/types
   Remove-Item -Recurse -Force frontend/src/integrations
   ```

> ⚠️ **لا تنفّذ هذه الخطوة الأخيرة إلا بعد `npm run build` ✅ + `npm test` ✅**

**التحقق:** `npm run build` ✅ | `npm test` ✅ | لا imports من المسارات القديمة ✅

---

## 🟢 المرحلة العاشرة: Import Boundaries Enforcement

### [ ] Task 29 — تثبيت أداة Linting للـ Boundaries

**الهدف:** تثبيت أداة تفرض قواعد الاستيراد تلقائياً.

**الخيارات:**

| الأداة | الأمر | الملاحظة |
|---|---|---|
| `@feature-sliced/steiger` | `npm install --save-dev steiger @feature-sliced/steiger-plugin` | مخصصة FSD — **الموصى به** |
| `eslint-plugin-boundaries` | `npm install --save-dev eslint-plugin-boundaries` | أعم |

**خطوات التنفيذ:**
1. شغّل أمر التثبيت المختار في `frontend/`
2. أنشئ `frontend/steiger.config.ts`:
   ```ts
   import { defineConfig } from 'steiger';
   import fsd from '@feature-sliced/steiger-plugin';
   export default defineConfig([...fsd.configs.recommended]);
   ```
3. تحقق من الـ configuration:
   ```powershell
   npx steiger ./src
   ```

**التحقق:** `npx steiger ./src` يشتغل بدون config errors ✅

---

### [ ] Task 30 — كتابة قواعد الـ ESLint Boundaries

**الهدف:** تعريف قواعد تمنع الاستيراد المحظور تلقائياً.

**إذا استخدمت `eslint-plugin-boundaries`، أضف لـ `.eslintrc.js`:**
```js
rules: {
  'boundaries/element-types': ['error', {
    default: 'disallow',
    rules: [
      { from: 'app',      allow: ['pages', 'widgets', 'features', 'entities', 'shared'] },
      { from: 'pages',    allow: ['widgets', 'features', 'entities', 'shared'] },
      { from: 'widgets',  allow: ['features', 'entities', 'shared'] },
      { from: 'features', allow: ['entities', 'shared'] },
      { from: 'entities', allow: ['shared'] },
      { from: 'shared',   allow: [] },
    ]
  }]
}
```

**التحقق:**
- محاولة import محظور (مثل `features/auth` تستورد من `features/notifications`) تظهر كـ ESLint error ✅
- `npm run lint` لا يخرج بـ 0 violations على الكود النظيف ✅

---

### [ ] Task 31 — تشغيل الأداة وتصليح الـ Violations

**الهدف:** التأكد من عدم وجود أي import محظور.

**خطوات التنفيذ:**
1. شغّل: `npx steiger ./src`
2. وثّق كل violation
3. لكل violation، قرر:
   - Cross-feature import → مرّر عبر entity مشتركة في `shared`
   - Cross-entity import → استخدم `shared/types`
   - Page تستورد implementation detail → أنشئ widget يجمعهما
4. كرّر حتى 0 violations

**التحقق:** `npx steiger ./src` — 0 violations ✅ | `npm run build` ✅

---

## 🟢 المرحلة الحادية عشرة: توحيد Segments

### [ ] Task 32 — مراجعة وتوحيد بنية Segments لكل Slice

**الهدف:** كل entity وfeature تلتزم ببنية segments موحدة.

**البنية المطلوبة:**
```
<slice>/
├── ui/       ← React components فقط
├── model/    ← state, hooks, business logic
├── api/      ← server calls فقط
├── lib/      ← utilities, helpers, pure functions
└── index.ts  ← Public API
```

**قائمة الفحص:**

| Slice | ui | model | api | lib | index.ts |
|---|---|---|---|---|---|
| entities/application | [ ] | [ ] | [ ] | [ ] | [ ] |
| entities/service | [ ] | [ ] | [ ] | [ ] | [ ] |
| entities/user | [ ] | [ ] | [ ] | [ ] | [ ] |
| entities/engineer | [ ] | [ ] | [ ] | [ ] | [ ] |
| entities/workflow | [ ] | [ ] | [ ] | [ ] | [ ] |
| entities/quota | [ ] | [ ] | [ ] | [ ] | [ ] |
| features/auth | [ ] | [ ] | [ ] | [ ] | [ ] |
| features/apply-service | [ ] | [ ] | [ ] | [ ] | [ ] |
| features/review-application | [ ] | [ ] | [ ] | [ ] | [ ] |
| features/manage-services | [ ] | [ ] | [ ] | [ ] | [ ] |
| features/notifications | [ ] | [ ] | [ ] | [ ] | [ ] |
| features/language-switch | [ ] | [ ] | [ ] | [ ] | [ ] |

**خطوات التنفيذ:**
1. لكل slice: تحقق من وجود كل segment
2. أنشئ أي segment مفقود (مجلد فارغ مع `.gitkeep` أو ملف بـ `// empty` إن لزم)
3. تأكد أن لا ملف موجود خارج هذه الـ segments مباشرة في مجلد الـ slice

**التحقق:** كل slice يحتوي على `ui/`, `model/`, `api/`, `lib/`, `index.ts` ✅

---

## 📊 ملخص التاسكات

| # | المرحلة | عدد التاسكات | الأولوية |
|---|---|---|---|
| 1-3 | Bootstrap & Foundation | 3 | 🔴 حرجة |
| 4-8 | Shared Layer | 5 | 🔴 حرجة |
| 9-13 | Entities Layer | 5 | 🟡 عالية |
| 14-16d | Features Layer | 7 | 🟡 عالية |
| 17-18 | Widgets Layer | 2 | 🟡 عالية |
| 19-21 | Engine & Pages | 3 | 🟡 عالية |
| 22-24 | Cleanup | 3 | 🔵 متوسطة |
| 25 | Engine Relocation | 1 | 🟢 نهائية |
| 26-28 | Public API | 3 | 🟢 نهائية |
| 29-31 | Boundaries | 3 | 🟢 نهائية |
| 32 | Segments Audit | 1 | 🟢 نهائية |
| **—** | **الإجمالي** | **36 task** | — |

---

## ⚠️ تحذيرات مهمة

> **لا تنفّذ Task 28 (حذف المجلدات القديمة)** إلا بعد أن يجتاز `npm run build` + `npm test` بنجاح كامل.

> **`src/api/jea/admin.ts` (13.3 KB)** — ضخم، راجع محتواه بدقة قبل توزيعه على entities/features.

> **`src/modules/JeaServices/pages/Apply.tsx` (30.7 KB)** — يحتاج تقسيماً إضافياً في Task 21 مثل NewService.

> **`src/modules/JeaProjects/pages/`** و **`src/modules/JeaDues/pages/`** و **`src/integrations/Nashmi/`** — خارج نطاق الـ FSD plan الأساسي. قرر كيف تتعامل معها قبل Task 21.

---

*إجمالي التاسكات: 36 | الملفات المتأثرة: ~230 ملف | تاريخ الإنشاء: 2026-07-28*
