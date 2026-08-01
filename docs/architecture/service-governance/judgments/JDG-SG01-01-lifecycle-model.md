JUDGMENT_ID=JDG-SG01-01
TITLE=Service lifecycle state model — column layout
SCOPE=architecture/service-governance/SG-01
OWNER=service-governance-remediation

الوضع=
`service_definitions.status` currently carries the enum `active|inactive|draft` (see migration `2025_01_01_000003_create_service_definitions_table.php:26`). `is_locked` boolean added later (`2026_07_19_063240_add_is_locked_to_service_definitions.php`). ServiceCatalogController filters public listing via `where('status','active')` at lines 180, 211. No UAT attestation, no publication timestamp, no suspension/retirement fields, no maker/checker fields exist.

تحرير_محل_النزاع=
Should the lifecycle be represented by (A) a single expanded status enum replacing the current three-value column, (B) attestation columns added alongside the existing status with lifecycle computed from them, or (C) a fully separate `service_lifecycle_states` table?

السبب=
This program (§Phase SG-01) mandates a lifecycle model that distinguishes at least eight states, plus attestation, publication, suspension, retirement, and publication reason fields.

الشرط=
Any chosen layout must (i) preserve existing controller filters and tests that reference `status='active'`, (ii) support maker-checker in SG-02, (iii) enable computed lifecycle derivation without querying twice, (iv) leave room for SG-03 version snapshots without further migration.

المانع=
Option (A) — replacing the enum — would break every controller filter, seeder, and test using `active|inactive|draft`. Option (C) — separate table — introduces an unjustified table for a single-row-per-service concept and complicates snapshots.

العلة=
Backward compatibility with existing runtime + minimum coherent model. The existing `status` field maps to the runtime meaning "is catalog-visible/hidden"; new columns track the governance attestation dimension separately.

القادح=
None. The augmentation approach preserves every current invariant.

الصحة=
Valid layout (Option B): retain `status` (mapped as the runtime catalog visibility summary), add ten attestation/lifecycle columns, compute the eight named lifecycle states from them.

الفساد=
Attempting to overload the existing three-value enum with more values without explicit renaming would be fasid — the field's semantic ownership becomes ambiguous.

البطلان=
Deleting the existing `status` column would be batil for backward compatibility.

الأثر=
(1) One migration adds ten columns. (2) `ServiceDefinition` model gains `lifecycle()` accessor. (3) The existing `status` column continues to represent runtime catalog visibility; the new `publication_status` column represents governance publication state. (4) The existing seeders and controllers continue to work; SG-02 will integrate the new fields as availability inputs.

البقايا=
RES-SG01-01: A downstream cleanup may collapse `status` into a computed accessor once every consumer is migrated to `publication_status`. Not in scope for this program.

التعارض=
Existing `status` (runtime visibility) vs new `publication_status` (governance). Superficial overlap.

الجمع=
Reconcilable — the two mean different things: `status='active'` says "row is catalog-visible if permissions allow"; `publication_status='PUBLISHED'` says "governance has approved and published this service". Both are needed until legacy consumers migrate.

الترجيح=
Tier-6 evidence (current implementation) requires preserving `status`. Tier-4 evidence (this program's target architecture) requires the new attestation columns. Both retained.

التوقف=
Not stopped.

EVIDENCE=
- backend/modules/JeaServices/Database/Migrations/2025_01_01_000003_create_service_definitions_table.php:26
- backend/modules/JeaServices/Http/Controllers/ServiceCatalogController.php:180,211
- backend/modules/JeaServices/Database/Seeders/ServicePlan2026Seeder.php:93

DECISION=
Add ten columns to `service_definitions`:
    uat_status         enum('NOT_SUBMITTED','PENDING','APPROVED','REJECTED') default 'NOT_SUBMITTED'
    uat_reference      string nullable
    uat_signed_at      timestamp nullable
    uat_signed_by      unsignedBigInteger nullable  (FK → users; nullOnDelete)
    publication_status enum('NOT_PUBLISHED','PUBLISHED','SUSPENDED','RETIRED') default 'NOT_PUBLISHED'
    published_at       timestamp nullable
    published_by       unsignedBigInteger nullable  (FK → users; nullOnDelete)
    effective_from     timestamp nullable
    suspended_at       timestamp nullable
    suspended_by       unsignedBigInteger nullable  (FK → users; nullOnDelete)
    suspension_reason  text nullable
    retired_at         timestamp nullable
    retired_by         unsignedBigInteger nullable  (FK → users; nullOnDelete)
    retirement_reason  text nullable
    publication_reason text nullable
Lifecycle is derived by ServiceDefinition::lifecycle() from a combination of: configuration structural validity, fee status, workflow status, uat_status, publication_status.

IMPLEMENTATION_EFFECT=
New migration. Model gains `lifecycle()`, `isPublished()`, `isSuspended()`, `isRetired()`, `hasUatApproval()`. No existing behaviour changes.

MIGRATION_EFFECT=
Additive columns with defaults — no data loss, no runtime impact. Existing rows default to `uat_status='NOT_SUBMITTED'` and `publication_status='NOT_PUBLISHED'`. **Historical preservation:** existing applications continue to reference their service_definitions row unchanged; publication_status default of NOT_PUBLISHED means SG-02 gates will treat them as `LEGACY_UNVERSIONED` and rely on the explicit legacy-transition policy defined in SG-03.

TEST_EVIDENCE=
Unit tests on model lifecycle derivation; migration test asserting default values on existing rows.

OPEN_RESIDUALS=
- RES-SG01-01 (cleanup of legacy `status` field — out of scope).
