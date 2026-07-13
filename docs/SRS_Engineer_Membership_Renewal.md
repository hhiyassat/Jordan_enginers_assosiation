# وثيقة مواصفات متطلبات البرمجيات (SRS)
# Software Requirements Specification
## خدمة تجديد عضوية المهندس — نقابة المهندسين الأردنيين
## JEA Engineer Membership Renewal Service

| | |
|---|---|
| **المشروع / Project** | نظام الخدمات الرقمية — نقابة المهندسين الأردنيين (JEA Digital Services Platform / ESP v2) |
| **الخدمة / Service** | تجديد عضوية المهندس / Engineer Membership Renewal |
| **رمز الخدمة / Service Code** | `2001` |
| **رقم المعاملة النموذجي / Sample Transaction No.** | `JEA-26-2001-0001` |
| **الإصدار / Version** | 1.0 |
| **التاريخ / Date** | 13 يوليو 2026 / 13 July 2026 |
| **المُعِدّ / Prepared by** | Eqratech |
| **المنهجية / Methodology** | IEEE 830 / Eqratech EDA v1.1 (§6 Requirements Engineering) |
| **الرسوم / Fee** | **25 دينار أردني — ثابتة / 25 JOD — fixed** |

---

## ١. مقدمة / 1. Introduction

### ١.١ الغرض / 1.1 Purpose
تحدّد هذه الوثيقة المتطلبات الوظيفية وغير الوظيفية لخدمة **تجديد عضوية المهندس** الرقمية ضمن منصّة ESP v2 لنقابة المهندسين الأردنيين. تُغذّى هذه الوثيقة مباشرةً إلى مولّد المخططات (Schema Generator) لإنتاج خدمة رقمية عاملة دون كتابة أي شيفرة إضافية (BR-001).

This document specifies the functional and non-functional requirements for the digital **Engineer Membership Renewal** service on the JEA ESP v2 platform. It is written to be fed directly into the ESP v2 Schema Generator to produce a fully running e-service with no additional code (BR-001).

### ١.٢ النطاق / 1.2 Scope
تتيح الخدمة للمهندس المُسجَّل تجديد عضويته السنوية في النقابة إلكترونياً عبر:
- التحقق من الهوية برمز OTP عبر الرسائل النصية (لا كلمة مرور).
- تعبئة نموذج التجديد مع الحفظ التلقائي.
- رفع الوثائق المطلوبة إلى تخزين S3.
- دفع الرسوم الثابتة (25 دينار).
- إصدار **شهادة/بطاقة تجديد العضوية** بعد اعتماد جميع المراحل.
- التتبع العام لحالة المعاملة عبر رقم المعاملة.

The service allows a registered engineer to renew their annual JEA membership online: OTP identity verification, autosaved renewal form, S3 document upload, fixed-fee payment (25 JOD), and issuance of a **Membership Renewal Certificate/Card** after all stages are approved, with public status tracking by transaction reference.

**خارج النطاق / Out of scope:** العضوية الجديدة (تسجيل أول مرة)، ترقية الدرجة الهندسية، وإصدار شهادات الخبرة — هذه خدمات منفصلة.
New (first-time) membership, engineering-grade upgrades, and experience-certificate issuance are separate services.

### ١.٣ التعريفات والمختصرات / 1.3 Definitions & Acronyms
| المصطلح / Term | التعريف / Definition |
|---|---|
| JEA | نقابة المهندسين الأردنيين / Jordan Engineers Association |
| OTP | رمز التحقق لمرة واحدة / One-Time Password (SMS) |
| DLS | نظام الترخيص الرقمي / Digital Licensing System (identity provider) |
| GSB | ناقل الخدمات الحكومي / Government Service Bus |
| SLA | اتفاقية مستوى الخدمة / Service Level Agreement |
| العضو / Member | مهندس مُسجَّل لدى النقابة برقم عضوية فعّال أو منتهٍ |
| مرجع المعاملة / Transaction Ref | `JEA-{YY}-{ServiceCode:4}-{Seq:4}` |

### ١.٤ المراجع / 1.4 References
- سجل متطلبات ESP v2 (REQUIREMENTS.md v1.1)
- تقرير اجتماع متطلبات JEA (2026-07-12)
- منهجية Eqratech EDA v1.1 — §6 هندسة المتطلبات
- مخطط مرجعي: `schemas/business-license.json`

---

## ٢. الوصف العام / 2. General Description

### ٢.١ سياق النظام / 2.1 System Context
الخدمة وحدة (Service Definition) واحدة مُعرَّفة بملف مخطط JSON على منصّة ESP v2 متعددة المستأجرين (NFR-002). تعتمد على محرّك سير العمل (WorkflowEngine)، ومُحقّق المخطط (SchemaValidator)، وسجلّ التدقيق (AuditLog)، وخدمة الحفظ المؤقت (DraftCacheService)، وبوابة SMS، وتكامل DLS.

The service is a single schema-defined Service Definition on the multi-tenant ESP v2 platform, reusing the shared WorkflowEngine, SchemaValidator, AuditLog, DraftCacheService, SMS gateway, and DLS integration.

### ٢.٢ أدوار المستخدمين / 2.2 User Roles
| الدور / Role | الوصف / Description |
|---|---|
| **المهندس (المتقدم) / Engineer (Applicant)** | يعبّئ نموذج التجديد ويرفع الوثائق ويدفع الرسوم. دخول OTP فقط (NFR-007). |
| **موظف النقابة / Staff** | المراجعة الأولية لبيانات ووثائق التجديد. |
| **المدقّق / Auditor** | التحقق من عدم وجود التزامات مالية متأخرة أو إجراءات تأديبية معلّقة. |
| **المدير / Admin** | نقطة الانطلاق الوحيدة لبدء الخطوة الأولى (FR-020)، تأكيد الدفع، وإصدار الشهادة. |
| **المواطن/العضو (تتبع عام) / Public** | تتبع حالة المعاملة برقم المرجع + OTP (FR-019). |

### ٢.٣ القيود / 2.3 Constraints
- اللغة الأساسية عربية (RTL) والثانوية إنجليزية (NFR-003)، وامتثال WCAG 2.1 AA (NFR-004).
- جميع قيود منصّة JEA غير الوظيفية إلزامية (انظر القسم ٤).
- العملة: الدينار الأردني (JOD) حصراً.
- لا تُنشأ هجرة قاعدة بيانات جديدة؛ تُخزَّن بيانات النموذج في عمود JSON (DATA-001).

### ٢.٤ الافتراضات والتبعيات / 2.4 Assumptions & Dependencies
- توفّر بوابة SMS خارجية لإرسال OTP (INT-002).
- توفّر خدمة DLS للتحقق من الهوية في التتبع العام (INT-001).
- امتلاك المهندس رقم عضوية سابق وهاتف محمول مُسجَّل لدى النقابة.

---

## ٣. المتطلبات الوظيفية / 3. Functional Requirements

### ٣.١ الهوية والدخول / 3.1 Authentication
| ID | المتطلب / Requirement | الأولوية |
|----|-----------------------|:--------:|
| FR-MR-001 | يُدخل المهندس رقم هاتفه المحمول ويستلم رمز OTP عبر SMS للتحقق من هويته قبل بدء الطلب. | Must |
| FR-MR-002 | يُرفض الدخول باسم مستخدم/كلمة مرور للمتقدمين نهائياً (توافق NFR-007). | Must |

### ٣.٢ إنشاء الطلب وتعبئته / 3.2 Application Creation & Form Entry
| ID | المتطلب / Requirement | الأولوية |
|----|-----------------------|:--------:|
| FR-MR-003 | يستطيع المهندس إنشاء طلب تجديد وربطه برقم عضويته السابق. | Must |
| FR-MR-004 | يعرض النظام النموذج مقسّماً إلى أقسام: بيانات العضو، بيانات الاتصال، التخصص، الإقرارات. | Must |
| FR-MR-005 | تُحفظ بيانات النموذج تلقائياً في الذاكرة المؤقتة عند كل تغيير حقل (توافق NFR-009). | Must |
| FR-MR-006 | يحتفظ النظام بحالة النموذج عند التنقل بين الأقسام دون فقدان بيانات (SaveAction). | Must |
| FR-MR-007 | يتحقق النظام من صحة الحقول (رقم العضوية، الرقم الوطني 10 أرقام، الهاتف `07XXXXXXXX`، البريد) ويعيد أخطاءً على مستوى الحقل عند الفشل. | Must |
| FR-MR-008 | يعرض النظام قيمة الرسوم الثابتة (25 ديناراً) للعضو قبل الإرسال. | Must |

### ٣.٣ الوثائق / 3.3 Documents
| ID | المتطلب / Requirement | الأولوية |
|----|-----------------------|:--------:|
| FR-MR-009 | يرفع المهندس الوثائق المطلوبة إلى فتحات مُعرَّفة بالمخطط (هوية وطنية، بطاقة العضوية السابقة، صورة شخصية). | Must |
| FR-MR-010 | تُخزَّن جميع الملفات على تخزين S3 (توافق NFR-010) ويُتحقَّق من نوع MIME والحجم مقابل حدود المخطط. | Must |
| FR-MR-011 | تقبل فتحات الرفع الصيغ: PDF، JPG/JPEG، PNG، وMP4 عند الحاجة (توافق FR-018). | Must |

### ٣.٤ الإرسال وسير العمل / 3.4 Submission & Workflow
| ID | المتطلب / Requirement | الأولوية |
|----|-----------------------|:--------:|
| FR-MR-012 | تُنقل بيانات النموذج من الذاكرة المؤقتة إلى قاعدة البيانات الدائمة فقط عند الإرسال الصريح من المستخدم. | Must |
| FR-MR-013 | لوحة تحكم المدير هي نقطة الانطلاق الوحيدة لبدء الخطوة الأولى من سير العمل (توافق FR-020). | Must |
| FR-MR-014 | يمرّ الطلب بمراحل: المراجعة الأولية (موظف) ← التحقق من الالتزامات (مدقّق) ← تأكيد الدفع ← إصدار الشهادة. | Must |
| FR-MR-015 | يستطيع المُراجع اتخاذ قرار: اعتماد / رفض / طلب تعديلات. | Must |
| FR-MR-016 | عند «طلب تعديلات» يعود الطلب إلى حالة `submitted` عند إعادة الإرسال (توافق BR-007). | Must |
| FR-MR-017 | يستخدم استلام المُراجع قفلاً (lockForUpdate) لمنع المطالبات المتزامنة. | Must |

### ٣.٥ الدفع والشهادة / 3.5 Payment & Certificate
| ID | المتطلب / Requirement | الأولوية |
|----|-----------------------|:--------:|
| FR-MR-018 | يؤكّد الموظف/المدير المخوّل دفع الرسوم الثابتة (25 ديناراً) للطلبات المعتمدة. | Must |
| FR-MR-019 | تُصدر شهادة/بطاقة تجديد العضوية فقط بعد اعتماد جميع المراحل وتأكيد الدفع (توافق BR-006). | Must |
| FR-MR-020 | تُصبح الشهادة الصادرة قابلة للتحقق عبر نقطة نهاية عامة برمز QR موقّع (SHA-256 HMAC). | Must |
| FR-MR-021 | حالتا «مرفوض» و«شهادة صادرة» نهائيتان — لا انتقالات لاحقة (توافق BR-008). | Must |

### ٣.٦ التتبع العام / 3.6 Public Tracking
| ID | المتطلب / Requirement | الأولوية |
|----|-----------------------|:--------:|
| FR-MR-022 | يستطيع أي عضو تتبع حالة معاملته بإدخال رقم المرجع `JEA-26-2001-XXXX` دون تسجيل دخول. | Must |
| FR-MR-023 | يُتحقَّق من هوية المتتبِّع عبر OTP وخدمة DLS قبل الكشف عن تفاصيل الطلب (توافق FR-019، INT-001). | Must |

---

## ٤. المتطلبات غير الوظيفية / 4. Non-Functional Requirements

متطلبات منصّة JEA التالية **ثابتة وإلزامية** لجميع الخدمات الرقمية (v1.1 / 2026-07-12):

| ID | المتطلب / Requirement | الأولوية |
|----|-----------------------|:--------:|
| NFR-007 | مصادقة المتقدمين عبر OTP/SMS فقط — لا اسم مستخدم/كلمة مرور. | Must |
| NFR-008 | صيغة رقم المعاملة: `JEA-{YY}-{ServiceCode:4}-{Seq:4}` — مثال `JEA-26-2001-0001`. | Must |
| NFR-009 | حفظ تلقائي لبيانات النموذج في الذاكرة المؤقتة عند كل تغيير؛ التثبيت في قاعدة البيانات عند الإرسال الصريح فقط. | Must |
| NFR-010 | تخزين جميع الملفات المرفوعة على S3-compatible object storage — لا نظام ملفات محلي. | Must |
| NFR-003 | جميع نصوص الواجهة ثنائية اللغة (عربي RTL أساسي، إنجليزي ثانوي). | Must |
| NFR-004 | امتثال الواجهة لمعيار WCAG 2.1 AA. | Must |
| NFR-MR-001 | زمن استجابة نقاط القراءة ≤ 500 مللي ثانية تحت الحمل الطبيعي. | Should |
| NFR-MR-002 | دعم متعدد المستأجرين من قاعدة شيفرة واحدة (NFR-002). | Must |
| NFR-MR-003 | الاحتفاظ بسجل التدقيق لمدة ٧ سنوات كحد أدنى (NFR-006). | Must |

---

## ٥. متطلبات الأمان / 5. Security Requirements

| ID | المتطلب / Requirement | الأولوية |
|----|-----------------------|:--------:|
| SEC-MR-001 | تُفرض صلاحيات RBAC عند طبقة الوسيط (middleware) بنمط fail-closed (403 عند غياب الدور). | Must |
| SEC-MR-002 | تُحجب الحقول الحساسة (الرقم الوطني، الرموز) في سجلات التدقيق وسجلات api_access. | Must |
| SEC-MR-003 | يتحقّق كل رفع من نوع MIME والحجم مقابل حدود المخطط. | Must |
| SEC-MR-004 | تحديد المعدّل: 5/دقيقة لطلب OTP، 60/دقيقة لمسارات المصادقة، 120/دقيقة عام. | Must |
| SEC-MR-005 | ترويسات أمان لجميع الاستجابات: HSTS، CSP، X-Frame-Options، X-Content-Type-Options، Referrer-Policy، Permissions-Policy. | Must |
| SEC-MR-006 | كل انتقال حالة داخل `DB::transaction()` ويكتب سجل تدقيق (rule_id، from_status، to_status، input_snapshot). | Must |
| SEC-MR-007 | سجل التدقيق ملحق فقط (append-only) — لا UPDATE ولا DELETE. | Must |
| SEC-MR-008 | صلاحية رمز OTP محدودة زمنياً ولمحاولات معدودة قبل إعادة الإصدار. | Must |

---

## ٦. متطلبات التكامل / 6. Integration Requirements

| ID | المتطلب / Requirement | الأولوية |
|----|-----------------------|:--------:|
| INT-001 | تكامل وحدة التتبع العام مع DLS للتحقق من هوية المواطن قبل الكشف عن حالة الطلب. | Must |
| INT-002 | تسليم OTP عبر بوابة SMS خارجية قابلة للتهيئة عبر متغيرات البيئة (ENV). | Must |
| INT-MR-001 | (اختياري) التحقق من رقم العضوية مقابل سجل الأعضاء المركزي في النقابة قبل قبول التجديد. | Should |

---

## ٧. نماذج البيانات / 7. Data Models

**الكيان: طلب التجديد / Entity: RenewalApplication** — تُخزَّن حقول النموذج في عمود `data` من نوع JSON (DATA-001).

| الحقل / Field | النوع / Type | ملاحظات / Notes |
|---|---|---|
| `membership_number` | text | رقم العضوية السابق — مطلوب |
| `full_name_ar` | text | الاسم الرباعي بالعربية — مطلوب |
| `full_name_en` | text | الاسم بالإنجليزية — اختياري |
| `national_id` | text | 10 أرقام، نمط `^[0-9]{10}$` — مطلوب، محجوب في السجلات |
| `engineering_branch` | select | التخصص الهندسي (مدني، معماري، كهرباء، ميكانيك، ...) — مطلوب |
| `grade` | select | الدرجة (مزاول، مشرف، خبير) — مطلوب |
| `phone` | text | نمط `^07[0-9]{8}$` — مطلوب |
| `email` | email | اختياري |
| `workplace` | text | جهة العمل الحالية — اختياري |
| `governorate` | select | محافظة الإقامة — مطلوب |
| `arrears_declaration` | radio | إقرار بعدم وجود التزامات مالية متأخرة — مطلوب |
| `code_of_conduct_ack` | checkbox | إقرار الالتزام بميثاق السلوك المهني — مطلوب |

**كيانات مشتركة على مستوى المنصّة / Shared platform entities:** `ServiceDefinition`، `Application` (status, reference_number)، `AuditLog` (append-only)، `Certificate` (qr_token). جميع سجلات المستخدمين تستخدم الحذف الناعم (DATA-004).

---

## ٨. مخطط سير العمل / 8. Workflow

نقطة البدء: **لوحة تحكم المدير** (`first_step_actor: admin` — FR-020). محرّك الحالة مُعرَّف بثابت `ALLOWED_TRANSITIONS`؛ `transitionTo()` نقطة التحوّل الوحيدة، وكل تحوّل داخل معاملة قاعدة بيانات ويكتب سجل تدقيق.

| # | المرحلة / Stage | الدور / Role | SLA | الإجراءات / Actions |
|:-:|---|---|:--:|---|
| 1 | المراجعة الأولية / Initial Review | staff | 24h | approve · reject · request_modifications |
| 2 | التحقق من الالتزامات / Obligations Check | auditor | 48h | approve · reject · request_modifications |
| 3 | تأكيد الدفع / Payment Confirmation | admin | 24h | confirm_payment |
| 4 | إصدار الشهادة / Certificate Issuance | admin | 24h | issue_certificate |

**مسارات الحالة / Status flow:**
`draft → submitted → initial_review → obligations_check → approved → payment_confirmed → certificate_issued`
مع مسار `modifications_requested → (resubmit) → submitted`، والحالتان النهائيتان: `rejected`، `certificate_issued`.

---

## ٩. متطلبات الرسوم / 9. Fee Structure

| البند / Item | القيمة / Value |
|---|---|
| **نوع الرسوم / Fee type** | ثابتة / **fixed** |
| **المبلغ / Amount** | **25.000** |
| **العملة / Currency** | **JOD (دينار أردني)** |
| توقيت الدفع / Payment timing | بعد اعتماد المرحلتين ١ و٢ وقبل إصدار الشهادة |
| احتساب الرسوم / Computation | من إعداد الرسوم في المخطط، لا في الشيفرة (توافق BR-003) |

```json
"fee": { "type": "fixed", "amount": 25, "currency": "JOD" }
```

---

## ١٠. متطلبات الشهادة / 10. Certificate Requirements

| البند / Item | القيمة / Value |
|---|---|
| العنوان (عربي) / Title (AR) | شهادة تجديد عضوية المهندس |
| العنوان (إنجليزي) / Title (EN) | Engineer Membership Renewal Certificate |
| مدة الصلاحية / Validity | 12 شهراً / 12 months |
| التحقق / Verification | نقطة نهاية عامة برمز QR موقّع (SHA-256 HMAC — DATA-005) |
| الحقول على الشهادة / Fields on cert | `membership_number`, `full_name_ar`, `full_name_en`, `engineering_branch`, `grade`, رقم المعاملة، تاريخ الإصدار وتاريخ الانتهاء |
| شرط الإصدار / Issuance condition | اعتماد جميع المراحل + تأكيد الدفع (BR-006) |

---

## ملحق: قابلية التوليد الآلي / Appendix: Schema-Generation Readiness

هذه الوثيقة جاهزة للتغذية المباشرة إلى أداة `generate_service_schema` في خادم MCP `esp-schema-generator`. القيم الثابتة المتوقّعة في المخطط الناتج:

```
service_code:              "2001"
auth:                      { type: "otp", otp_channel: "sms" }        // NFR-007 / INT-002
transaction_number:        { format: "JEA-{YY}-{service_code}-{seq}", service_code_digits: 4, seq_digits: 4 }  // NFR-008
autosave:                  { enabled: true, storage: "cache", flush_on: "submit" }  // NFR-009
storage:                   { backend: "s3" }                          // NFR-010
public_tracking:           { enabled: true, verify_via: "otp", identity_provider: "dls" }  // FR-019 / INT-001
workflow.first_step_actor: "admin"                                    // FR-020
fee:                       { type: "fixed", amount: 25, currency: "JOD" }
```

> **الخطوة التالية / Next step:** استدعاء `generate_service_schema` بنص هذه الوثيقة و`service_code="2001"`، ثم `validate_schema` للتأكد من امتثال JEA NFR، ثم `save_schema_to_esp` بحالة `draft`.

---

*وثيقة SRS معدّة بواسطة Eqratech وفق منهجية EDA v1.1 | 13 يوليو 2026*
*SRS prepared by Eqratech per EDA v1.1 methodology | 13 July 2026*
