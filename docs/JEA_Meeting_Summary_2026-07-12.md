# تقرير اجتماع متطلبات النظام — نقابة المهندسين الأردنيين
# JEA System Requirements Meeting Report

**المشروع / Project:** نظام الخدمات الرقمية — نقابة المهندسين الأردنيين (JEA Digital Services Platform)  
**التاريخ / Date:** 12 يوليو 2026 / 12 July 2026  
**المرحلة / Phase:** Phase 1 & 2 — Backend Architecture & General Technical Requirements  
**المُعِدّ / Prepared by:** Eqratech  

---

## ملخص الاجتماع / Meeting Summary

تم خلال الاجتماع تحديد المتطلبات الفنية العامة وعمارة البنية الخلفية (Backend Architecture) للمرحلتين الأولى والثانية من مشروع نظام الخدمات الرقمية لنقابة المهندسين الأردنيين. تم تحديد سبعة محاور تقنية رئيسية.

The meeting established the general technical requirements and backend architecture for Phases 1 & 2 of the JEA Digital Services System. Seven technical areas were defined.

---

## المتطلبات المستخرجة / Requirements Extracted

### ١. إدارة الهوية وأدوار المستخدمين / 1. Authentication & RBAC

**آلية التحقق / Authentication Mechanism:**
- يُعتمد بشكل كامل على رمز التحقق لمرة واحدة (OTP) عبر الرسائل النصية لتسجيل دخول المتقدمين — لا يوجد اسم مستخدم/كلمة مرور.
- Authentication for applicants is OTP-only via SMS; no username/password.

**صلاحية المدير / Admin Authority:**
- لوحة تحكم المدير (Admin Dashboard) هي نقطة الانطلاق الوحيدة لبدء الخطوة الأولى من أي خدمة رقمية لأي متقدم.
- The Admin Dashboard is the sole entry point for initiating the first workflow step of any digital service.

---

### ٢. دورة حياة المعاملة / 2. Transaction Lifecycle

- تتبع كامل لجميع مراحل المعاملة من البداية حتى إصدار الشهادة.
- Full end-to-end lifecycle tracking from application creation through certificate issuance.
- كل تحول في الحالة يُسجَّل في سجل التدقيق مع معرّف القاعدة والحالة السابقة والجديدة.
- Every status transition logged in audit log with rule_id, from_status, to_status.

---

### ٣. الحفظ التلقائي / 3. Autosave + Cache Memory

- يتم حفظ بيانات النموذج تلقائياً في ذاكرة التخزين المؤقت (Cache) عند كل تغيير في الحقول.
- Form data is autosaved to server-side cache on each field change.
- لا يتم نقل البيانات إلى قاعدة البيانات الدائمة إلا بعد موافقة صريحة من المستخدم (الإرسال النهائي).
- Data is only flushed to persistent DB on explicit user submit action.

---

### ٤. التنقل في النموذج / 4. Form Navigation (SaveAction)

- يجب أن يحتفظ النظام بحالة النموذج عند التنقل بين الخطوات.
- System must preserve form state when navigating between form steps.
- يُتاح العودة للخطوات السابقة دون فقدان البيانات.
- Back-navigation must not cause data loss.

---

### ٥. التكامل مع الأنظمة الخارجية / 5. Integration

- تكامل مع نظام DLS للتحقق من هوية المواطن في وحدة التتبع العام.
- Integration with DLS (Digital Licensing System) for citizen identity verification in the public tracking module.
- بوابة رسائل SMS خارجية لإرسال رموز OTP قابلة للتهيئة عبر متغيرات البيئة.
- External SMS gateway for OTP delivery, configurable via ENV variables.

---

### ٦. هيكل رقم المعاملة / 6. Transaction Number Architecture

**التنسيق / Format:** `JEA-{YY}-{ServiceCode:4}-{Seq:4}`

| الخانة / Slot | القيمة / Value | الوصف / Description |
|--------------|---------------|---------------------|
| 1-2 | `26` | السنة (آخر رقمين) / Year (last 2 digits) |
| 3-4 | `1234` | رمز الخدمة (4 أرقام) / Service code (4 digits) |
| 5-8 | `0001` | الرقم التسلسلي / Sequential number |

**مثال / Example:** `JEA-26-1234-0001`

> ⚠️ **ملاحظة / Note:** التنسيق الحالي في النظام هو `ESP-{Code}-{Date}-{Seq}` ويجب تغييره.  
> Current system format is `ESP-{Code}-{Date}-{Seq}` — must be updated.

---

### ٧. تخزين الملفات / 7. Backend Object Storage

**أنواع الملفات المقبولة / Accepted File Types:**
- PDF
- PNG / JPG / JPEG
- MP4 (جديد / **new**)

**بنية التخزين / Storage Architecture:** S3-compatible object storage (not local filesystem)

---

### ٨. وحدة التتبع العام / 8. Public Tracking Module

- يستطيع أي مواطن تتبع حالة طلبه عبر إدخال رقم المعاملة دون تسجيل دخول.
- Any citizen can track application status by entering the transaction reference number — no login required.
- يتم التحقق من هوية المواطن عبر OTP قبل الكشف عن تفاصيل الطلب.
- Citizen identity verified via OTP before application details are disclosed.
- تكامل مع نظام DLS.
- Integrates with DLS.

---

## تحليل الفجوات / Gap Analysis

مقارنة متطلبات JEA مع الكود الموجود في esp-v2:  
Comparison of JEA requirements against existing esp-v2 codebase:

| المتطلب / Requirement | الحالة / Status | تفاصيل الفجوة / Gap Details |
|----------------------|----------------|----------------------------|
| OTP-only login (NFR-007) | 🔴 **مفقود / Missing** | `AuthController` يعتمد على كلمة المرور. يجب إضافة `OtpController` + جدول `otp_codes` + SMS gateway service |
| Admin initiates step 1 (FR-020) | 🟡 **جزئي / Partial** | RBAC موجود لكن `WorkflowEngine` يسمح للمتقدم ببدء الخطوة الأولى. يجب تقييد `transitionTo('submitted')` للـ admin فقط |
| Transaction Lifecycle tracking | 🟢 **مغطى / Covered** | `WorkflowEngine` + `AuditLog` يغطيان هذا المتطلب بالكامل |
| Autosave + Cache (NFR-009) | 🔴 **مفقود / Missing** | الكود الحالي يحفظ مباشرة في DB. يجب إضافة `DraftCacheService` يستخدم Laravel Cache (Redis) |
| Form Navigation | 🟡 **جزئي / Partial** | `DynamicForm.tsx` موجود لكن لا يوجد حفظ حالة التنقل بين الخطوات |
| DLS Integration (INT-001) | 🔴 **مفقود / Missing** | يوجد GSB فقط. DLS مطلوب كـ service جديد |
| Transaction Number Format (NFR-008) | 🔴 **تغيير مطلوب / Change needed** | الصيغة الحالية: `ESP-BL-001-20260706-0001`. يجب تغييرها إلى `JEA-{YY}-{SSSS}-{NNNN}` |
| Object Storage S3 (NFR-010) | 🔴 **مفقود / Missing** | الكود يستخدم `local` disk. يجب تغيير `FILESYSTEM_DISK=s3` + إضافة `AWS_*` ENV vars |
| MP4 file upload (FR-018) | 🔴 **مفقود / Missing** | `ApplicationController` يقبل `pdf,jpg,jpeg,png,tiff,doc,docx` فقط — MP4 غير مدعوم |
| Public Tracking Module (FR-019) | 🔴 **مفقود / Missing** | يوجد فقط التحقق من الشهادة (`FR-013`). لا توجد وحدة تتبع عامة بـ OTP |

---

## ملخص الفجوات / Gap Summary

| الأولوية / Priority | العدد / Count | البنود / Items |
|--------------------|--------------|----------------|
| 🔴 مفقود كلياً / Fully missing | 6 | OTP login, Autosave/Cache, DLS, Object Storage, MP4, Public Tracking |
| 🟡 جزئي / Partial | 2 | Admin-initiates-step-1, Form Navigation |
| 🟢 مغطى / Covered | 2 | Transaction Lifecycle, RBAC base |

---

## الخطوات التالية المقترحة / Recommended Next Steps

1. **OTP Auth** — إضافة `OtpController`, جدول `otp_codes`, `SmsGatewayService`، وتعديل `AuthController` ليدعم مسار OTP للمتقدمين.
2. **Transaction Number** — تعديل `generateReferenceNumber()` في `Application` model لاستخدام تنسيق `JEA-{YY}-{SSSS}-{NNNN}`.
3. **Object Storage** — تغيير `FILESYSTEM_DISK` إلى `s3`، إضافة S3/MinIO ENV vars، تحديث `ApplicationController::uploadDocument()`.
4. **MP4 Support** — إضافة `mp4` إلى قائمة `mimes` في `ApplicationController` + رفع حد الحجم `max` للفيديو.
5. **Autosave/Cache** — بناء `DraftCacheService` (Laravel Cache/Redis)، إضافة endpoint `PUT /applications/{id}/cache`، تحديث `DynamicForm.tsx` لإرسال كل حقل عند التغيير.
6. **Public Tracking Module** — بناء `PublicTrackingController`، route عام `/track/{reference}`, OTP verification flow، DLS service stub.
7. **Admin Step-1 Gate** — تعديل `WorkflowEngine::transitionTo('submitted')` ليشترط دور `admin` أو استخدام Admin Dashboard فقط.

---

*تقرير معدّ بواسطة Eqratech | 13 يوليو 2026*  
*Report prepared by Eqratech | 13 July 2026*
