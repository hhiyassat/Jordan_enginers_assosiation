# ESP-v2 — نموذج الخدمة المعماري والتشغيلي (Handoff)

**اسم الـHandoff:** `ESP_V2_SERVICE_ARCHITECTURE_RUNTIME_MODEL`
**تاريخ الفحص:** 2026-08-01
**نقطة البدء (HEAD):** `f3fc366d8effed8f11fa2787fb6629a339ebfbfb`
**الفرع:** `remediation/architecture-security-production-readiness`
**طبيعة العمل:** فحص للقراءة فقط، بدون أي تعديل على ملفات التشغيل.

هذه الوثيقة تجيب على سؤال محدد: **ما هي «الخدمة» في ESP-v2 فعليًا، وكيف تُعرَّف وتُفعَّل وتُنفَّذ وتُختبر وتتطور، وأي أجزاء منها عامة (Generic) وأي أجزاء خاصة بالخدمة (Service-Specific)؟**

جميع الاستنتاجات مبنية على أدلة نصية داخل مستودع الشيفرة عند HEAD المذكور أعلاه. أرقام السطور والملفات موثقة في `10-Handoff-Evidence-Index.md`. المصفوفات الخام في الملفات `02` إلى `05` من هذا المجلد.

---

## 1) الإجابة القصيرة

**الخدمة في ESP-v2 هي: صفٌّ (Row) في جدول `service_definitions` عمود `schema` فيه (نوع JSON) هو مصدر الحقيقة الوحيد وقت التشغيل.** يقرأ هذا الـJSON مُحرِّك عام مشترك بين كل الخدمات، ولا يوجد داخل الشيفرة الإنتاجية سوى خدمة واحدة فقط (SRV-001) لها كود يخصّها بشكل صريح.

ما نستنتجه من هذا الوصف:

* عدد الصفوف الحالي: **57 خدمة** (JEA-PROJ ×12، JEA-SURV ×14، JEA-FIN ×6، JEA-CERT ×6، JEA-ENG ×2، JEA-DEC ×4، JEA-MISC ×13) موزعة على 7 فئات أب.
* عدد الخدمات ذات **Guard خاص** (تحقق قبل الإرسال مُخصَّص للخدمة): **1 فقط** — `Srv001Guard`.
* عدد الخدمات ذات **حقول حقيقية** (Fields) مُوقَّعة في `Srv001PilotSeeder`: **1 فقط** — SRV-001.
* عدد الخدمات ذات **رسوم حقيقية** (Fee غير الـPlaceholder): **22 خدمة** (12 DRW-P + SRV-001..006 + SRV-007 + SRV-012 + DRW-P-006 solar). الباقي (**35 خدمة**) يحمل رسم Placeholder = 50,000 دينار.
* عدد الخدمات ذات **Workflow حقيقي** مأخوذ من مخطط تدفق: **7 خدمات** (SRV-001، SRV-002، SRV-007، SRV-008، SRV-009، SRV-012، SRV-014). الباقي (**50 خدمة**) يستخدم Workflow قالبي من `CatalogWorkflowsSeeder`.
* الواجهة الأمامية (Frontend): **صفر Conditional على service_code** في كل الشيفرة الإنتاجية. عرض النموذج ديناميكي تماماً من الـschema.

## 2) طبقات المُحرِّك (ما هو عام؟)

الطبقات التالية **لا تعرف اسم أي خدمة**؛ كلها تعمل من الـschema:

| الطبقة | الملف | الدور |
|---|---|---|
| المُوجِّه (Routes + Controllers) | `ApplicationController`, `ServiceCatalogController`, `PaymentsController`, ...إلخ | تستقبل الطلب، تُحمِّل `ServiceDefinition`، تنادي المُحرِّك |
| مُحقِّق الـSchema | `SchemaValidator::validateData / validateDocuments` | يجول على `schema.fields[]` و`schema.documents[]` |
| حاسبة الرسوم | `FeeCalculator::calculateBreakdown` | تتفرَّع على `schema.fee.type` بين خمسة أنواع: `fixed`, `tiered`, `formula`, `matrix`, `per_unit` |
| مُحرِّك سير العمل | `WorkflowEngine::submit / claim / decide / issueCertificate` | يقود المراحل من `schema.workflow.stages[]` |
| خط الأنابيب العرضي | `CrossCuttingSubmissionPipeline` | يشغِّل `CadastralConflictGuard`، `OwnerMatchClearanceGuard`، `SanctionGuard`، `CapacityGuard` بصرف النظر عن الخدمة |
| توليد الأرقام (المرجعية والشهادات) | `application_counters` + `certificate_counters` + `lockForUpdate` | نمط H-02 المتزامن |
| البوابة المالية | `PaymentGateway` (Interface) + `MockPaymentGateway` / `SignedTestPaymentGateway` | Abstraction لا تعرف الخدمة |
| المصادقة متعددة المستأجرين | `BelongsToOrganization` trait + `OrganizationScope` | فرض `organization_id` على كل استعلام |
| الواجهة الأمامية | `frontend/src/modules/JeaServices/pages/Apply.tsx` | Renderer عام يقرأ الـschema |

## 3) ما هو الخاص بالخدمة؟ (SRV-001 فقط)

يوجد بالضبط **أربعة ملفات** إنتاجية تتفرَّع على SRV-001:

| الملف | الدور |
|---|---|
| `Srv001Guard.php` | تحقُّق مُخصَّص عند الإرسال: توجيه القطاع الحكومي (project_sector=حكومي → SRV-006)، تطبيق مصفوفة الاستكشاف (JORD-91)، وسم dérivé للدراسة الخاصة، وكتابة القيم المُشتقَّة داخل `app.data` |
| `WellsCountCalculator.php` | حساب عدد الآبار (نطاقات تتحدد بـarea_m2). **مؤقّت (PROVISIONAL)** — المصدر: محضر اجتماع 2026-07-26 §X، **غير مُعتمد من النقابة** |
| `NetDepthTable.php` | جدول العمق الصافي (ثلث/ثلثين/كامل بحسب عدد الطوابق). **مؤقّت** — المصدر: محضر اجتماع 2026-07-26 §XI؛ يوجد تناقض داخلي معروف: `third + two_thirds ≠ total` يحتاج توضيحاً من النقابة |
| `ExplorationRequirementMatrix.php` | يُشفِّر جدول 4-1 من «كتاب التعليمات الفنية 2025 ص 230-231». يُرجع SPECIAL_STUDY_REQUIRED للطوابق 9-15 و > 15 |

الربط بينها والمُحرِّك يجري عبر `ServiceSubmissionGuardRegistry` في `JeaServicesServiceProvider::register` (السطر 91):

```
[Srv001Guard::SERVICE_CODE => new Srv001Guard()]
```

هذا هو **المكان الوحيد** الذي يظهر فيه ثابت `SRV-001` في تركيب المُحرِّك.

## 4) دورة حياة الطلب (ما الذي يحدث بالفعل عند submit؟)

سلسلة الاستدعاءات عند إرسال طلب لأي خدمة (بالترتيب التي تجري به):

```
POST /api/v1/applications/{id}/submit
  → ApplicationController::submit()
      ↳ SchemaValidator::validateData          (عام)
      ↳ SchemaValidator::validateDocuments     (عام)
      ↳ CrossCuttingSubmissionPipeline::run
            ↳ CadastralConflictGuard           (عام، عبر المستأجرين)
            ↳ OwnerMatchClearanceGuard         (عام)
            ↳ SanctionGuard                    (عبر app(FQCN) من JeaDiscipline)
            ↳ CapacityGuard                    (عبر app(FQCN) من JeaProjects)
      ↳ ServiceSubmissionGuardRegistry::forService($code)?->validate($app)
            ↳ Srv001Guard::validate()          (خاص بالخدمة — SRV-001 فقط)
                  ↳ ExplorationRequirementMatrix::forFloorsAndArea()
                  ↳ WellsCountCalculator::compute()
                  ↳ NetDepthTable::forFloors()
                  ↳ يكتب القيم المُشتقة داخل app.data ويحفظ
      ↳ WorkflowEngine::submit()
            ↳ ينقل status: draft → submitted
            ↳ يحدد current_stage = المرحلة الأولى للمراجع
            ↳ يحسب sla_deadline من schema.workflow.stages[].sla_hours
```

المكوّنات المكتوبة **بالكامل بـSchema** (بلا كود خاص بالخدمة) تعمل بنفس هذه السلسلة. الفرق الوحيد أن `ServiceSubmissionGuardRegistry::forService($code)` تُرجع `null` وبالتالي تُتخطَّى الخطوة الأخيرة.

## 5) الطبقة الأمامية (Frontend) — الحكم النهائي

**عرض النموذج ديناميكي 100%**: 21 ملفاً فُحصت (`/tmp/svc-frontend.txt`)، منها 15 «Renderer عام»، و4 «قدرات عامة»، و1 «تعريفات أنواع الـSchema»، و**صفر** ملف خاص بخدمة معينة. لا يوجد أي `if (code === 'SRV-001')` في الإنتاج.

الاستثناء الوحيد: `Apply.tsx` يعبئ الحقلين `area_m2` و`governorate` تلقائياً من سياق `project` عند وجود `project_id`. هذان مُعرِّفا حقلين (Field IDs) مُدمَجَان في الكود بحكم العُرف، لا شرط تفرُّع على الخدمة. تم توثيقه كمخاطرة R-12 مع مقترح `auto_fill_from` في الـschema.

## 6) البذور (Seeders) وسياسة الإصدار

* **الصفوف الأساسية:** `ServicePlan2026Seeder` يُنشئ 57 خدمة + 7 فئات أب مع `placeholderSchema` (فارغ) ورسم Placeholder = 50,000 دينار.
* **التخصيصات (تُطبَّق بعد الأساس، بالترتيب):**
  * `Srv001PilotSeeder` — SRV-001 (28 حقلاً، وثيقتان)
  * `SurveyWorkflowsSeeder` — 7 خدمات لها Workflow حقيقي مأخوذ من ملفات drawio تحت `flowcahrt/`
  * `CatalogWorkflowsSeeder` — 43 قالب Workflow للخدمات الباقية (drawings, financial, certificate, engineer, board, directResponse, inspection ...)
  * `DrawingFeeMatrixSeeder` — 12 خدمة DRW-P (governorate × building_class)
  * `SolarFeeSeeder` — DRW-P-006 (4 دنانير/كيلوواط)
  * `ExcavationFeeSeeder` — SRV-007 + SRV-012 (3.5 دينار/م²)
  * `SiteSurveyFeesSeeder` — SRV-001..006 (150 فلس/م.ط + 1%)
  * `DrawingsDocumentsSeeder` — قائمة 15 وثيقة لـDRW-P-001..010

**لا يوجد جدول إصدارات (schema_version / service_definition_versions).** بمعنى: الطلبات المفتوحة تشاهد الـschema الحالي وقت `show`، وليس الـschema وقت الإنشاء. الحماية الوحيدة اليوم هي علم `is_locked` + اتفاق عمل غير مبرمج (نسخ → تعديل النسخة → استبدال الكود النشط).

**قِيَم مُجمَّدة داخل صفّ الطلب:**

| الحقل | يُجمَّد عند |
|---|---|
| `fee_amount` | submit — لا يتأثر بأي تعديل لاحق على schema.fee |
| `reference_number` | create |
| `sla_deadline` | submit |
| `data` | يبقى قابلاً للتعديل في مسودّة أو `modifications_requested`، والقيم المُشتقة تُعاد حسابتها على كل إرسال |

## 7) نموذج بيانات الطلب (Data ownership)

24 جدولاً تتقاسمها 5 وحدات (JeaServices, JeaProjects, JeaDiscipline, JeaDues, Nashmi Integration). الملكية المنطقية موثقة كاملة في `05-Service-Data-Ownership-Register.csv`. أهم القرارات:

* **`service_definitions`** يملكها JeaServices وتُقرأ عبر الوحدات (بلا `organization_id` — كتالوج مشترك عبر النقابة).
* **`applications`** يملكها JeaServices. المُدَخِّلون: `ApplicationController` و`WorkflowEngine` و`Srv001Guard`. المُطالعون: `PaymentCallbackController` و`CertificatesController` و`ReviewQueueController` و`SanctionGuard` (من JeaDiscipline) و`CapacityGuard`/`QuotaLedger` (من JeaProjects).
* **الاتصال بين وحدات JEA** يجري بأربعة مواضع خفية من نمط `app(FQCN)` (BL-DG-14 في backlog التنظيف). موثَّق في مخاطرة R-09.
* **قواعد التنافس:**
  * توليد `reference_number` و`certificate_number` — آمن (H-02 lockForUpdate).
  * استهلاك الحصة عند الاعتماد — آمن (Unique idempotent).
  * تحويل الإشراف عند العقوبة — **غير ذرّي**، توصية بتحويلها إلى Job مؤجَّل (R-06).
  * تكرار Callback الدفع — آمن (unique + insertOrIgnore).

## 8) نقاط التوسّع الحالية (Extension Points)

خمسة مواضع تتطلَّب مساهمة **كود** لا مجرَّد بيانات schema:

1. Guard خاص بالخدمة → إضافة صنف يتبع `ServiceSubmissionGuard` وتسجيله في `ServiceSubmissionGuardRegistry`.
2. حاسبات قبل الحفظ (Calculators) → دوال نقيَّة يستدعيها الـGuard، بلا وصول للـDB.
3. Stage Actions جديدة → توسيع `StageActions::run($action)` بفرع جديد.
4. عائلة رسوم جديدة → توسيع `FeeCalculator::calculateBreakdown` بحالة `type` جديدة.
5. نوع إشعار جديد → إضافة دالة `emit*` جديدة في `JeaNotificationService`.

عقد «حزمة خدمة» موحّد (folder-per-service) يمثِّل هذه النقاط بشكل صريح مقترَح تفصيلاً في `09-Proposed-Service-Package-Contract.md`. **لم تُنفَّذ أي تغييرات فعلية**؛ التوثيق مقتصر على الوصف.

## 9) الفرق بين ما هو مُنجَز اليوم وما هو مطلوب

هذا التصنيف مطلوب بحكم بنود التسليم (CURRENT_IMPLEMENTATION vs CURRENT_RUNTIME_BEHAVIOR vs BUSINESS_APPROVAL_STATUS vs TARGET_REQUIREMENT vs MIGRATION_REQUIREMENT).

### ما هو مُنجَز فعلاً (CURRENT_IMPLEMENTATION):

* هيكل «مُحرِّك عام + بيانات schema لكل خدمة» موجود ويعمل.
* SRV-001 مكتمل من طرف إلى طرف (Fields + Documents + Fee + Workflow + Guard + Calculators + Tests).
* الطبقة الأمامية ديناميكية تماماً.
* دفع + Callbacks + شهادات مُوقَّعة بـHMAC + توليد أرقام آمن للتزامن.
* ExplorationRequirementMatrix مُشفَّرة من مرجع فني معتمد (كتاب التعليمات الفنية 2025).

### ما هو السلوك الفعلي وقت التشغيل (CURRENT_RUNTIME_BEHAVIOR):

* 56 خدمة تقبل الإرسال بدون فرض قواعد أعمال خاصة بها (لا Guard، لا Fields حقيقية، Workflow قالبي).
* 35 خدمة تعرض رسم Placeholder = 50,000 دينار على المستخدم إذا فُتحت اليوم في الإنتاج.
* الطلبات المفتوحة تشاهد الـschema الحالي، بلا لقطة (Snapshot) عند الإرسال.
* القيم المُشتقَّة (wells_count، net_depth) تُحفَظ بلا `rule_version` — أي تعديل مستقبلي لن يترك أثراً على الطلبات القديمة.

### الوضع الاعتمادي من النقابة (BUSINESS_APPROVAL_STATUS):

* SRV-001 (نموذج) — pilot، الحقول والوثائق ومصفوفة الاستكشاف مُوقَّعة.
* `WellsCountCalculator` + `NetDepthTable` — **غير مُعتمدَين من النقابة** (مصدر: محضر اجتماع).
* CERT-006 — أعيد إحياؤه بناءً على قرار منتَج من النقابة.
* MSC-013 — مُسقَط عمداً (مكتبة وثائق، ليست Workflow).
* بقية الحالات UAT لكل خدمة **غير مُتتبَّعة في الكود**.

### المتطلَّب الهدف (TARGET_REQUIREMENT):

* كل خدمة تُفتح للجمهور يجب أن يكون لها: (أ) Fields حقيقية، (ب) Workflow مأخوذ من مصدر رسمي، (ج) رسم حقيقي (ليس Placeholder)، (د) Guards حيث تُملي المرجعية، (هـ) اختبار Submit كامل.
* Snapshot للـschema عند الإرسال + `rule_version` لكل قيمة مُشتقة.
* حزمة خدمة بمجلد موحّد يُصرِّح بنقاط التوسّع.

### متطلَّبات الترحيل (MIGRATION_REQUIREMENT):

* R-01: إضافة عمود `schema_version` + جدول `service_definition_versions` (لقطة عند الإرسال).
* R-02: سِجِلّ قواعد (Rule Registry) صغير، وختم القيم المُشتقة بـ`__rule_version`.
* R-03: قائمة أولوية للخدمات التالية بعد SRV-001 (تعتمد على قرار منتَج + توفُّر مرجع رسمي).
* R-04: تصعيد `WellsCountCalculator` و`NetDepthTable` للنقابة للحصول على مرجع مُوقَّع + حل التناقض `third + two_thirds ≠ total`.
* R-09: تنفيذ BL-DG-14 (سجل مساهمات موسَّع + أحداث نطاق للحياة).
* R-10: رفض النشر إذا بقيت رسوم Placeholder على أي خدمة نشطة.

المصفوفة الكاملة للمخاطر مع خيارات الترميم موجودة في `08-Service-Architecture-Risks-and-Decisions.md`.

## 10) القرارات المُجمَّدة (لا يُعاد فتحها بلا سبب جديد)

المجموعات التالية موثَّقة في `docs/remediation/cleanup-sprint/justified-duplication-register.md`:

* DG-02 تقسيم تأكيد الدفع (يدوي/Webhook) — منفصلان لضمان دليل الدفع.
* DG-03 عدَّاد المرجع vs عدَّاد الشهادة — منفصلان (مفاتيح ومنطق مختلف).
* DG-05 `SchemaValidator` vs `Srv001Guard` — عام مقابل خاص بالخدمة.
* DG-06 `JeaNotificationService` vs Platform `NotificationService` — حدود H-07.
* DG-11 `CapacityGuard` (بوابة قراءة) vs `QuotaLedger` (كتابة) — لأن `Application::booted::deleted` يعتمد على الجانب الكتابي مستقلاً.
* DG-12 `FeeCalculator` (رسوم schema) vs `QuotaLedger::overflowSurchargeFor` (رسم زائد على الحصة) — مصادر بيانات مختلفة.

هذه ليست قابلة للدمج تحت هذا التسليم.

## 11) الثقة والحدود المعرفية

| المقولة | مستوى الثقة | مصدر التحقق |
|---|---|---|
| «schema JSON هو مصدر الحقيقة الوحيد» | عالية | تعليق مايجريشن `2025_01_01_000003_create_service_definitions_table.php` + تتبُّع كل المستهلكين |
| «SRV-001 هي الخدمة الوحيدة ذات كود خاص» | عالية | تدقيق `/tmp/svc-runtime.txt` + تدقيق `ServiceSubmissionGuardRegistry` |
| «الواجهة الأمامية ديناميكية بالكامل» | عالية | تدقيق `/tmp/svc-frontend.txt` — 21 ملفاً، 0 تفرُّع على service code |
| «الرسم Placeholder ينطبق على 35 خدمة» | عالية | استنتاج من `/tmp/svc-inventory.txt` (57 خدمة − 22 خدمة ذات رسم حقيقي) |
| «سلامة تزامن العدَّادات» | عالية | `RealConcurrencyOnPostgresTest` (pcntl_fork على Postgres 15) |
| «توفُّر SanctionGuard لتعامل `effective_until = NULL`» | متوسطة | يحتاج مراجعة كود مستهدَفة لتوثيقها كاختبار |
| «Provenance مصفوفة الاستكشاف مُعتمَد فنياً» | عالية | ملف الصنف يستشهد بكتاب التعليمات الفنية 2025 ص 230-231 |
| «Provenance `WellsCountCalculator` و`NetDepthTable`» | منخفضة (مؤقّت) | ملف الصنف يستشهد بمحضر اجتماع فقط — لا اعتماد نقابي |

## 12) خلاصة عمل التسليم

هذا التسليم لم يُنفّذ أي تعديل على شيفرة التشغيل. الملفات المُنتَجة تحت `docs/handoff/service-architecture/` هي عشر ملفات وثائقية فقط:

1. `01-ESP-v2-Service-Architecture-Runtime-Model-Handoff.md` — هذه الوثيقة.
2. `02-Service-Inventory-and-Maturity-Matrix.csv` — 57 خدمة × 20 عاموداً.
3. `03-Service-Component-Reachability-Matrix.csv` — كل مكوّن تشغيلي + مصدر ربطه بمسار HTTP.
4. `04-Service-Commonality-and-Variability-Matrix.csv` — ما هو عام مقابل ما هو خاص بالخدمة.
5. `05-Service-Data-Ownership-Register.csv` — 24 جدولاً × ملكيتها وقواعد تنافسها.
6. `06-Service-Definition-Source-of-Truth-Analysis.md` — لماذا الـschema JSON هو مصدر الحقيقة.
7. `07-Seeder-and-Versioning-Policy-Analysis.md` — البذور، الإصدار، وثغرات الإصدار.
8. `08-Service-Architecture-Risks-and-Decisions.md` — 13 مخاطرة (R-01..R-13) + القرارات المُجمَّدة.
9. `09-Proposed-Service-Package-Contract.md` — عقد حزمة خدمة (اقتراح فقط).
10. `10-Handoff-Evidence-Index.md` — فهرس الأدلة الكامل.

Commit وثائقي واحد فقط تم إنشاؤه بعد اكتمال كل التسليمات (رقم الـcommit مذكور في كتلة النهاية الحقائقية أدناه).

---

## الكتلة الحقائقية النهائية (Factual Ending Block)

```
HANDOFF_NAME=ESP_V2_SERVICE_ARCHITECTURE_RUNTIME_MODEL
START_HEAD=f3fc366d8effed8f11fa2787fb6629a339ebfbfb
FINAL_HEAD=<recorded post-commit>
DOCUMENTATION_COMMIT=<recorded post-commit>
BRANCH=remediation/architecture-security-production-readiness

TOTAL_SERVICES=57
TOTAL_PARENT_CATEGORIES=7
SERVICES_WITH_SERVICE_SPECIFIC_GUARD=1
SERVICES_WITH_REAL_WORKFLOW=7
SERVICES_WITH_TEMPLATE_WORKFLOW=50
SERVICES_WITH_REAL_FEE=22
SERVICES_WITH_PLACEHOLDER_FEE=35
SERVICES_WITH_REAL_FIELDS=1
SERVICES_WITH_SEEDED_DOCUMENTS=11 (DRW-P-001..010 + SRV-001)
FRONTEND_SERVICE_CODE_BRANCHES=0
BACKEND_SERVICE_CODE_BRANCHES=3 (Srv001Guard::validate; ServiceSubmissionGuardRegistry dispatch map; WorkflowEngine::generateCertificateNumber formatting only)
CROSS_CUTTING_GUARDS_COUNT=4 (CadastralConflictGuard, OwnerMatchClearanceGuard, SanctionGuard, CapacityGuard)
SERVICE_SPECIFIC_CALCULATORS_COUNT=3 (all SRV-001: WellsCountCalculator PROVISIONAL, NetDepthTable PROVISIONAL, ExplorationRequirementMatrix JEA-cited)

DATA_TABLES_MAPPED=24
DATA_MODULES=5 (JeaServices, JeaProjects, JeaDiscipline, JeaDues, Nashmi Integration)
SCHEMA_VERSIONING_TABLE_PRESENT=NO
RULE_VERSION_TABLE_PRESENT=NO
CALCULATION_SNAPSHOT_TABLE_PRESENT=NO

MATURITY_CLASSIFICATION:
  FULLY_WIRED_END_TO_END=1 (SRV-001)
  WORKFLOW_REAL_BUT_NO_FIELDS_OR_GUARD=6 (SRV-002, SRV-007, SRV-008, SRV-009, SRV-012, SRV-014)
  CATALOG_ONLY_WITH_TEMPLATE_WORKFLOW=50

RISK_COUNT=13 (R-01..R-13)
KNOWN_LIMITATIONS_ACCEPTED_BY_DESIGN=2 (R-05 fee immutability; R-13 multi-tenant scope enforcement pattern)
UNAPPROVED_CALCULATORS_COUNT=2 (WellsCountCalculator, NetDepthTable — both cite meeting minutes)

CONFIDENCE_SCHEMA_IS_SOURCE_OF_TRUTH=HIGH
CONFIDENCE_ONLY_SRV_001_HAS_SPECIFIC_CODE=HIGH
CONFIDENCE_FRONTEND_IS_FULLY_DYNAMIC=HIGH
CONFIDENCE_UNAPPROVED_CALCULATOR_PROVENANCE=MEDIUM

CODE_CHANGES_MADE=0
TEST_CHANGES_MADE=0
SEEDER_CHANGES_MADE=0
MIGRATION_CHANGES_MADE=0
COMMITS_CREATED=1 (documentation-only)
PUSH_PERFORMED=NO
TAG_CREATED=NO
```
