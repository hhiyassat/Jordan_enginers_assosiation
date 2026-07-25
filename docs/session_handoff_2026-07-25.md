# Session Handoff — 2026-07-25

**وثيقة تسليم شاملة للنظام: المعمارية، المراجع، وتطبيق أحكام EDA.**

---

## 1. البنية العامة للنظام

نظام ESP v2 (Eqratech Services Platform / نظام نقابة المهندسين الأردنيين الرقمي) — منصّة خدمات إلكترونية للمكاتب الهندسية مبنيّة على **معمارية أربع طبقات فيزيائية**:

```
backend/                          frontend/src/
├── app/                          ├── platform/          ← الطبقة المحايدة (Platform Core)
│   (Auth, Users, Notifications,     (UI kit, utils, platform-only pages)
│    Audit, Middleware, Providers)
│
├── modules/                      ├── modules/           ← وحدات النطاق (Service Modules)
│   ├── JeaServices/              │   ├── JeaServices/     (28 route + application lifecycle)
│   ├── JeaProjects/              │   ├── JeaProjects/     (12 route + engineers/quotas)
│   ├── JeaDiscipline/            │   ├── JeaDiscipline/   (11 route + complaints/fines)
│   └── JeaDues/                  │   └── JeaDues/         (4 route + recurring dues)
│
├── plugins/                                             ← إمكانيات اختيارية (Plugins)
│   ├── AiSchema/                                          (Claude schema authoring)
│   └── Captcha/                                           (public-form challenge)
│
└── integrations/                 └── integrations/      ← محوّلات أنظمة خارجية (EIA)
    ├── Gsb/                          └── Nashmi/          (Nashmi + GSB webhook APIs)
    └── Nashmi/
```

### قواعد الاعتمادية (مُفعَّلة في CI)

- **Platform لا يستورد modules/plugins/integrations** — يُفرَض عبر
  `tests/Architecture/BoundariesTest::test_platform_does_not_import_service_modules`
- **Modules can read each other** كعقود موثَّقة (SM→SM DAG، بلا دورات)
- **Plugins/Integrations قد تعتمد على modules** (PLG→SM مسموح، SM→PLG ممنوع)
- **Frontend `dep-cruiser`** — 7 قواعد صارمة، 0 انتهاكات
- **PHPStan level 6** يعمل على `app/` (توسيع لباقي الشجرة مؤجَّل)

---

## 2. مصادر التطوير المرجعية

### 2.1 المصادر التنظيمية (النقابة)

| مصدر | مكانه | استُخدم في |
|------|-------|-----------|
| **كتاب التعليمات الفنية 2025** | `docs/كتاب_التعليمات_الفنية_2025.pdf` (5.3 MB، 311 صفحة) | كل ميزة تخصّ المكاتب الهندسية (رسوم، حصص، مخططات، إشراف، تأديب) |
| **ملخّص الكتاب** | `docs/manual-summary.md` | ربط كل JORD مُنفَّذ بصفحة الكتاب |
| **ملخّص اجتماع النقابة 12-07-2026** | `docs/JEA_Meeting_Summary_2026-07-12.md` | متطلّبات على مستوى النظام (FR-020، NFR-008) |
| **SRS تجديد العضوية** | `docs/SRS_Engineer_Membership_Renewal.md` | خدمة تجديد عضوية المهندس |

### 2.2 المصادر المنهجية

| مصدر | مكانه | استُخدم في |
|------|-------|-----------|
| **Eqratech IEEE-Aligned Decision Assurance Methodology v1.1** | مرجع خارجي | كل workflow operation موثَّق بـ 10 عناصر EDA |
| **EDA Decision Chain Mapping** | `docs/EDA_DECISION_CHAIN.md` | مصفوفة B-1..B-10 لكل عملية أساسية |
| **SRS-to-Service Hukm Engine** | `SRS-to-Service Hukm Engine.pdf` (جذر الريبو) | تصميم `WorkflowEngine` |
| **REQUIREMENTS.md** | جذر الريبو | 140 سطر — FR/NFR/BR/DATA/SEC/WF requirements |
| **BUILD_CONTRACT.md** | جذر الريبو | ترتيب middleware، refactor rules |
| **METHODOLOGY_AUDIT.md** | جذر الريبو | تدقيق التزام النظام بالمنهجية |

### 2.3 وثائق المعمارية (مخرجات الريفاكتور W1-W16)

| وثيقة | ماذا تصف |
|-------|----------|
| `docs/architecture/00-baseline.md` | حالة الكود قبل الريفاكتور |
| `docs/architecture/01-refactoring-plan.md` | خطّة الـ 16 workstream |
| `docs/architecture/03-file-classification.md` | تصنيف كل ملف (PC/STC/SM/PLG/EIA/RED) |
| `docs/architecture/04-modules.md` | كيف تعمل الوحدات + العقود بينها |
| `docs/architecture/05-plugins-and-integrations.md` | الفرق بين الأنواع الثلاثة |
| `docs/architecture/06-adding-a-service-module.md` | دليل إضافة module جديد + الفخاخ المعروفة |
| `docs/architecture/07-adding-a-plugin.md` | دليل إضافة plugin/integration |

---

## 3. تطبيق أحكام EDA (Decision Assurance)

كل عملية تحويل حالة (state transition) في `WorkflowEngine` موثَّقة بمصفوفة الـ 10 عناصر من Appendix B للـ Methodology.

### 3.1 مصفوفة أحكام مثال — `submit()`

**Operation:** `WorkflowEngine::submit(Application $app, User $actor)` — `draft → submitted`  
**Business Rule ID:** `ESP-WF-001`

| Element | معناه | ما يُحقّقه في الكود | الموقع |
|---------|-------|--------------------|--------|
| **B-1 Origin** | المُقدِّم مالك المسوَّدة | `findAccessible()` scope بـ `applicant_id` | `ApplicationController` |
| **B-2 Legitimate Branch** | دور applicant مطلوب | `CheckRole` middleware | `role:applicant,...` |
| **B-3 Origin–Branch Relationship** | الطلب يعود للمُقدِّم | compound query | `findAccessible()` |
| **B-4 Qualifying Description** | كل حقول schema مطلوبة موجودة | `SchemaValidator::validateData()` | `Modules\JeaServices\Engine\SchemaValidator` |
| **B-5 Critical Difference Test** | `ALLOWED_TRANSITIONS['draft']` يحوي `submitted` | `transitionTo()` | `WorkflowEngine` |
| **B-6 Required Conditions** | كل المستندات المطلوبة مرفوعة | `validateDocuments()` | `SchemaValidator` |
| **B-7 Valid Cause Occurred** | HTTP POST صريح | `submit()` action | `ApplicationController` |
| **B-8 Blocker Check** | حالة draft أو modifications_requested | `isEditable()` | `Application` model |
| **B-9 Effect Recorded** | AuditLog داخل DB transaction | `AuditLog::record(..., 'rule_id' => 'ESP-WF-001')` | `WorkflowEngine::submit()` |
| **B-10 Residual Outcomes** | 422 مع field errors عند الفشل، submission عند النجاح | `SchemaValidator` + frontend | `Apply.tsx` step handler |

### 3.2 القواعد الموثَّقة كـ rule_id (كلها تظهر في AuditLog)

| Rule ID | العملية |
|---------|---------|
| `ESP-WF-001` | Application submit |
| `ESP-WF-002` | Reviewer claim |
| `ESP-WF-003..010` | Decide/approve/reject/modify/certificate operations |
| `ESP-WF-011` | Reviewer release (PR#1) |
| `ESP-MANUAL-EDIT` | تعديل نص manual reference من admin |
| `ESP-MANUAL-ACK` | acknowledge لإنهاء pending flag |
| `ESP-SCHEMA-001` | Schema structure validation before persist |

### 3.3 كيف يُطبَّق الحكم أثناء التطوير

1. كل ميزة جديدة تبدأ بتحديد **الـ Business Rule ID** (ESP-XXX-###)
2. الحكم يُطبَّق عبر **10 عناصر EDA** — لا يُقبل أي عنصر ناقص
3. كل تحويل حالة يمرّ عبر `WorkflowEngine::transitionTo()` الذي يفرض `ALLOWED_TRANSITIONS`
4. كل تعديل يُسجَّل في `AuditLog` مع `rule_id` — يُمكن التتبّع الكامل عبر
   ```
   SELECT * FROM audit_logs WHERE extra->>'rule_id' = 'ESP-WF-001';
   ```

---

## 4. نظام Manual References (الجديد — 2026-07-24)

آخر ميزة رئيسية أُضيفت: **ربط كل قاعدة مُنفَّذة بالمقطع النظامي من الكتاب**.

### 4.1 المكوّنات

| مكوّن | موقع | الوظيفة |
|-------|------|---------|
| جدول `manual_references` | `modules/JeaServices/Database/Migrations/2026_07_24_...` | تخزين النصوص + الربط + pending flag |
| `ManualReference` model | `modules/JeaServices/Models/` | Eloquent, `isEdited()` helper |
| `ManualReferenceController` | `modules/JeaServices/Http/Controllers/` | GET (public) / PATCH (admin) / POST ack |
| `ManualReferencesSeeder` | `modules/JeaServices/Database/Seeders/` | 19 قاعدة أوليّة من الكتاب |
| `ManualReferenceLinksSeeder` | نفس المجلد | يربط `manual_reference_id` على `schema.fields` |
| `<ManualReferenceIcon />` | `platform/ui/` | أيقونة (?) + popover + admin edit |
| `<PendingManualEditsTile />` | نفس المجلد | لوحة إشعار على admin dashboard |
| `docs/manual-summary.md` | `docs/` | المصدر النصّي — كل ما يُنفَّذ من الكتاب |

### 4.2 التدفّق (Workflow)

```
1. المستخدم يفتح Apply page
2. DynamicForm يعرض حقلاً يحمل manual_reference_id
3. أيقونة (?) تظهر بجانب اسم الحقل
4. عند الضغط:
   - GET /api/v1/manual-references/{id}
   - popover يعرض النص العربي + فصل/صفحة
5. Admin/superuser يرى زر "تعديل" في نفس الـ popover
6. عند تعديل + حفظ:
   - PATCH /api/v1/admin/manual-references/{id}
   - يُرفَع needs_reimplementation=true
   - AuditLog يسجّل ESP-MANUAL-EDIT
7. admin dashboard يعرض PendingManualEditsTile → count > 0
8. الفريق التقني يراجع → يُطبّق التعديل في الكود
9. POST /ack → needs_reimplementation=false
   - text_ar_original يُحدَّث ليصير baseline جديد
```

### 4.3 حكم الميزة عبر EDA

**Rule ID:** `ESP-MANUAL-EDIT`

| Element | الحل |
|---------|------|
| B-1 Origin | admin/superuser المسجَّل الدخول |
| B-2 Legitimate Branch | role admin/superuser عبر middleware |
| B-3 Relationship | admin يعود لنفس المنظّمة (`organization_id`) |
| B-4 Description | text_ar ≥ 10 حرف |
| B-5 Difference | النص الجديد ≠ الحالي (unchanged→noop) |
| B-6 Conditions | reference موجود بالـ id |
| B-7 Cause | PATCH صريح |
| B-8 Blocker | none (idempotent edits) |
| B-9 Effect | AuditLog + needs_reimplementation=true |
| B-10 Residuals | pending tile يظهر، seeder re-runs يحترم التعديل |

---

## 5. الاختبارات (كضمان الأحكام)

| مستوى | إطار | عدد | ما يضمن |
|-------|------|-----|---------|
| Backend | PHPUnit | 575 (566 قبل + 9 جديدة) | كل rule_id مُطبَّق عبر الطبقات |
| Frontend | Vitest | 416 (410 + 6 جديدة) | كل مكوّن UI يستجيب للأحكام |
| Architecture | PHPUnit boundary | 11 | القواعد المعمارية مُفروضة |
| Dep-cruiser | eslint-style | 7 rules | حدود Frontend |

**التغطية النموذجية لعملية:**
- Unit test على الـ Engine (SchemaValidator, WorkflowEngine)
- Feature test على الـ Controller (HTTP + auth + response envelope)
- Vitest على الـ Component (UI + interaction)

---

## 6. حالة الديبلوي والبيئات

### 6.1 الفروع (Branches)

- `origin` = `hhiyassat/code-generation` — التطوير الرئيسي (main فقط)
- `jea` = `hhiyassat/Jordan_enginers_assosiation` — repository الديبلوي
- على كل push للـ origin main → يجب `git push jea main` للـ deploy

### 6.2 Hostinger Server (`srv1841200`)

| البند | القيمة |
|-------|--------|
| مسار الكود | `/var/www/html/Jordan_enginers_assosiation` |
| PHP | **8.3.32** (⚠️ symfony 8.1 يحتاج 8.4 — استخدم `--ignore-platform-req=php`) |
| PM2 | `jea-backend` (port 8002) + `jea-frontend` (Vite dev port 5173) |
| Nginx | reverse proxy لكلا الـ ports |
| DB | SQLite (`database/database.sqlite`) |
| deploy script | `~/run.sh` (تحديثه في `session_handoff_2026-07-22.md` §Part 2) |

### 6.3 خطوات ديبلوي كاملة

```bash
# On laptop:
git push jea main

# On server (one line each):
cd /var/www/html/Jordan_enginers_assosiation
git pull
cd backend
composer install --no-dev -o --ignore-platform-req=php  # only if composer.json changed
php artisan migrate --force                             # only if new migrations
php artisan db:seed --class='...'                       # per seeder needed
php artisan optimize:clear
pm2 restart all
```

---

## 7. الدين المعماري الموثَّق (لا يمنع الشحن)

| العنصر | التفاصيل | المسار |
|--------|----------|--------|
| **PC_ALLOWLIST** (8 ملفات) | ملفات platform تستورد modules — لكل ملف مسار تقاعد | `BoundariesTest::PC_ALLOWLIST` |
| **الأعمدة المُهاجرة** (3) | JEA columns على users/organizations | `PlatformMigrationsOnlyTest::KNOWN_EXCEPTIONS` |
| **`ApiResponse` adoption** | Helper موجود، endpoints قديمة لم تُحدَّث | `Http/Responses/ApiResponse.php` |
| **PHPStan scope** | يعمل على `app/` فقط، `modules/plugins/integrations` مؤجَّلة | `phpstan.neon` |
| **ModuleRegistry runtime** | `routes.tsx` + `navItems.tsx` ما زالت god-lists | Frontend refactor مستقبلي |
| **PHP 8.3 vs symfony 8.1** | يحتاج إمّا PHP 8.4 على server أو downgrade symfony في composer.json | `session_handoff_2026-07-22.md` §Part 2 |

---

## 8. خارطة القرارات الحديثة (لتذكير الجلسة القادمة)

- ✅ 16-workstream refactor مُنجَز (PR #3 merged `e122573`)
- ✅ Deploy على Hostinger عامل
- ✅ Manual reference system مُنجَز ومُنشَر (`12aa02f`, `1d0e11e`, `32a5d83`)
- ✅ auto-fill Apply form من Project (خيار المستخدم: fill + allow override)
- ✅ 3 bugs latent اكتُشفت وأُصلحت (DynamicForm sections, Apply auto-fill, SchemaValidator options_endpoint)

---

## 9. مراجع سريعة للاستخدام اليومي

**البحث عن ticket:**
```bash
git log --all --grep='JORD-XX' --oneline
```

**البحث عن Rule ID في الكود:**
```bash
grep -rn "ESP-WF-001" backend/modules/
```

**AuditLog query:**
```sql
SELECT * FROM audit_logs WHERE extra->>'rule_id' = 'ESP-XXX-###' ORDER BY created_at DESC;
```

**تحقّق حالة manual reference:**
```bash
php artisan tinker --execute="echo Modules\\JeaServices\\Models\\ManualReference::where('needs_reimplementation',true)->count();"
```

---

## 10. للجلسة القادمة

الأولويات المُقترَحة بترتيب النفع:

1. **إكمال manual-summary.md** — أُضيفت 19 قاعدة فقط من ~300 صفحة. توسيع التغطية يُثري النظام تدريجياً.
2. **حل مشكلة PHP 8.3 vs symfony 8.1 نهائياً** — إمّا ترقية PHP على السيرفر أو downgrade في composer.json (Path C من session_handoff_2026-07-22).
3. **تقاعد PC_ALLOWLIST entries** — كل entry معه retirement path موثَّق.
4. **توسيع PHPStan إلى modules/plugins/integrations** — يكشف debt مخفي.
5. **ModuleRegistry runtime pattern** على الفرونت — يحذف god-lists نهائياً.

---

*ملاحظة الأسلوب:* هذا النظام مبنيّ على مبدأ **"كل قاعدة مؤسَّسة تنظيمياً + موثَّقة تقنياً + مضمونة بالاختبار + قابلة للمراجعة"**. أي إضافة جديدة يجب أن تتبع نفس الدورة: rule_id → EDA mapping → tests → manual reference (إذا كانت النقابة تفرض القاعدة).
