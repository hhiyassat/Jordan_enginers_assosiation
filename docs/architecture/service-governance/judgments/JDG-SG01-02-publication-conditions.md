JUDGMENT_ID=JDG-SG01-02
TITLE=Publication صحة conditions and موانع
SCOPE=architecture/service-governance/SG-01
OWNER=service-governance-remediation

الوضع=
No publication policy exists. `status='active'` alone can be set by any admin via `ServiceCatalogController::store/update/updateStatus`, immediately exposing the service to public listing.

تحرير_محل_النزاع=
What is the minimum set of conditions that must be satisfied for a service to be publishable, and what are the blockers that must reject publication?

السبب=
Program §Phase SG-01 mandates explicit publication conditions and blockers so SG-02 can enforce them at runtime.

الشرط=
Publication may proceed only when ALL of these hold:
  - service_definitions row exists (catalog record exists)
  - schema JSON is structurally valid (SchemaStructureValidator passes)
  - fee is not PLACEHOLDER (no `type='fixed' amount=0` and no `source` matching the ServiceFeeDefaultsSeeder 50000 marker)
  - fee is not ABSENT and not UNKNOWN
  - workflow classification is not PLACEHOLDER (`placeholder_review` single-stage from ServicePlan2026Seeder placeholderSchema)
  - documents classification is not UNKNOWN
  - uat_status='APPROVED' with non-null uat_reference and uat_signed_at
  - publication decision exists (publication_reason non-null)
  - effective_from is either null (immediate) or reached (≤ now())

المانع=
Publication is blocked when ANY of these hold:
  - placeholder fee remains
  - placeholderSchema is still in place (workflow is single-stage placeholder_review)
  - uat_status != 'APPROVED'
  - uat_reference is null
  - required documents are UNKNOWN
  - workflow provenance is UNKNOWN
  - schema structure validation fails
  - effective_from is in the future

العلة=
Protection of applicants (no service that hasn't been JEA-approved should collect fees or issue certificates), auditability (every publication traceable to who published + why + when), historical reproducibility (published version snapshot required — enforced by SG-03).

القادح=
If any current active service ('status'='active') fails the above conditions, the current runtime is already exposing unapproved services to applicants. Given SG-00 evidence, ALL 57 services currently fail at least uat_status != 'APPROVED' — so the publication gate must not retroactively flip existing rows to UNPUBLISHED. Instead, the gate applies only to future transitions; existing rows remain in `publication_status='NOT_PUBLISHED'` while `status='active'` continues to control the legacy catalog-visibility path. SG-02 will introduce ServiceAvailabilityPolicy that combines both signals with a documented preference order.

الصحة=
Valid publication: all conditions met + maker-checker enforced (uat_signed_by ≠ published_by).

الفساد=
Publication with a placeholder fee is fasid — repairable by admin overriding fee before re-attempt.

البطلان=
Any publication attempt with missing UAT reference is batil — must not produce publication.

الأثر=
(1) `ServicePublicationPolicy` class returns a typed `PublicationDecision` with error codes. (2) `ServiceDefinition::isPublishable()` is a convenience wrapper. (3) SG-02 wires the policy into the admin endpoint that flips publication_status.

البقايا=
RES-SG01-02: SG-02 must decide the runtime preference order when a legacy `status='active'` service has `publication_status='NOT_PUBLISHED'`. Owner: SG-02. Default policy: for the SG-01/02 transition window, treat legacy-active services as available but log a governance warning; SG-03 completes the story with versioning.

التعارض=
57 legacy-active services with no UAT vs the publication gate.

الجمع=
Reconcile via transition window: existing `status='active'` rows remain visible until SG-03 completes; new publication attempts must satisfy the full policy.

الترجيح=
Runtime safety (tier 5) > current implementation (tier 6) for future transitions. Historical preservation (tier 5) > new gate for legacy rows.

التوقف=
Not stopped. The reconciliation preserves runtime and adds the gate for future changes.

EVIDENCE=
- backend/modules/JeaServices/Http/Controllers/ServiceCatalogController.php:109-158 (updateStatus flow — no gate)
- backend/modules/JeaServices/Database/Seeders/ServicePlan2026Seeder.php:93 (default status='active')
- backend/modules/JeaServices/Database/Seeders/ServiceFeeDefaultsSeeder.php:37 (50000 JOD marker)
- backend/modules/JeaServices/Database/Seeders/ServicePlan2026Seeder.php:389 (placeholder_review stage marker)

DECISION=
Create `Modules\JeaServices\Governance\ServicePublicationPolicy` returning `PublicationDecision`. Codes: PUB_OK, PUB_BLOCKED_PLACEHOLDER_FEE, PUB_BLOCKED_PLACEHOLDER_WORKFLOW, PUB_BLOCKED_MISSING_UAT, PUB_BLOCKED_MISSING_UAT_REFERENCE, PUB_BLOCKED_SCHEMA_STRUCTURE, PUB_BLOCKED_EFFECTIVE_FROM_FUTURE, PUB_BLOCKED_MISSING_REASON. Maker-checker enforced in the policy layer: uat_signed_by ≠ actor attempting to publish. Include reason codes and human-readable messages in the decision.

IMPLEMENTATION_EFFECT=
New class in `backend/modules/JeaServices/Governance/`. No controller wiring yet (SG-02 wires it).

MIGRATION_EFFECT=
None (policy is code-only).

TEST_EVIDENCE=
Unit tests: OK when all conditions met; each blocker code triggered by exactly one violation.

OPEN_RESIDUALS=
- RES-SG01-02 (transition-window preference order — decided in SG-02).
