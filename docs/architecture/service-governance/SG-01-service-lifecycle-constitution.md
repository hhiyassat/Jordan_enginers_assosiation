# SG-01 · Service Lifecycle Constitution

**Program:** `ESP_V2_SERVICE_GOVERNANCE_VERSIONING_FOUNDATION`
**Phase:** SG-01
**Baseline HEAD:** `8d0cd170f6a5b4c5aa2a7f4c9de6c2c78bbc6b1e` (post SG-00)

Establishes the explicit service lifecycle. Distinguishes catalog existence from public availability and business approval. Does NOT wire the lifecycle into runtime gates — that is SG-02's responsibility.

## The eight named states

Defined in `backend/modules/JeaServices/Governance/ServiceLifecycleState.php`. Computed by `ServiceDefinition::lifecycle()` from the columns added by `2026_08_01_000010_add_lifecycle_governance_to_service_definitions.php`.

| State | Meaning | Signals |
|---|---|---|
| `DRAFT` | Row exists but no schema JSON, or schema is incomplete | `schema` missing one of the four top-level keys |
| `CONFIGURED` | Schema JSON has all four top-level keys | workflow / fields / documents / fee all present |
| `TECHNICALLY_VALIDATED` | Schema has at least one workflow stage and a fee `type` | Stronger structural check |
| `AWAITING_UAT` | UAT submitted, decision pending | `uat_status='PENDING'` |
| `UAT_APPROVED` | JEA has attested approval | `uat_status='APPROVED'` + `uat_reference` + `uat_signed_at` |
| `PUBLISHED` | Publicly available | `publication_status='PUBLISHED'` |
| `SUSPENDED` | Temporarily removed from public availability | `publication_status='SUSPENDED'` |
| `RETIRED` | Permanently removed (historical only) | `publication_status='RETIRED'` |

Evaluation order in `ServiceDefinition::lifecycle()`: retirement > suspension > publication > uat_approved > awaiting_uat > technically_validated > configured > draft. This ensures a retired service that was previously published still reports as RETIRED.

## Data model

Migration `2026_08_01_000010_add_lifecycle_governance_to_service_definitions.php` adds:

* `uat_status` enum(NOT_SUBMITTED, PENDING, APPROVED, REJECTED) default 'NOT_SUBMITTED'
* `uat_reference` string nullable
* `uat_signed_at` timestamp nullable
* `uat_signed_by` FK → users nullable
* `publication_status` enum(NOT_PUBLISHED, PUBLISHED, SUSPENDED, RETIRED) default 'NOT_PUBLISHED'
* `published_at` timestamp nullable
* `published_by` FK → users nullable
* `effective_from` timestamp nullable
* `suspended_at` timestamp nullable
* `suspended_by` FK → users nullable
* `suspension_reason` text nullable
* `retired_at` timestamp nullable
* `retired_by` FK → users nullable
* `retirement_reason` text nullable
* `publication_reason` text nullable

The pre-existing `status` column (active|inactive|draft) is **preserved** for backward compatibility with the ServiceCatalogController listing filter and every seeder. See `judgments/JDG-SG01-01-lifecycle-model.md` for the ترجيح.

## Publication conditions and blockers

Defined in `judgments/JDG-SG01-02-publication-conditions.md` and implemented in `ServicePublicationPolicy`. Publication may proceed only when:

* Schema is structurally valid.
* Fee is not the placeholder (amount=0 or the ServiceFeeDefaultsSeeder 50000 JOD marker).
* Workflow is not the `placeholder_review` single-stage from `ServicePlan2026Seeder::placeholderSchema`.
* `uat_status='APPROVED'` with non-null `uat_reference` and `uat_signed_at`.
* `publication_reason` is set.
* `effective_from` is null or in the past.
* Maker-checker: the actor attempting to publish differs from `uat_signed_by`.

Blockers are returned as typed reason codes in `PublicationDecision`:

* `PUB_BLOCKED_PLACEHOLDER_FEE`, `PUB_BLOCKED_PLACEHOLDER_WORKFLOW`, `PUB_BLOCKED_MISSING_UAT`, `PUB_BLOCKED_MISSING_UAT_REFERENCE`, `PUB_BLOCKED_SCHEMA_STRUCTURE`, `PUB_BLOCKED_EFFECTIVE_FROM_FUTURE`, `PUB_BLOCKED_MISSING_REASON`, `PUB_BLOCKED_MAKER_CHECKER`.

## Backward compatibility

* Every existing seeder continues to write `status='active'`. Every existing `where('status','active')` query continues to work.
* Existing rows default to `uat_status='NOT_SUBMITTED'` and `publication_status='NOT_PUBLISHED'`.
* SG-02 introduces `ServiceAvailabilityPolicy` that reconciles the two signals with a documented preference order for the transition window (RES-SG01-02).

## Files added

| File | Purpose |
|---|---|
| `backend/modules/JeaServices/Database/Migrations/2026_08_01_000010_add_lifecycle_governance_to_service_definitions.php` | Additive migration |
| `backend/modules/JeaServices/Governance/ServiceLifecycleState.php` | Eight-state constants |
| `backend/modules/JeaServices/Governance/PublicationDecision.php` | Typed policy verdict |
| `backend/modules/JeaServices/Governance/ServicePublicationPolicy.php` | Publication policy |
| `backend/tests/Unit/Governance/ServiceLifecycleTest.php` | 9 lifecycle-derivation tests |
| `backend/tests/Unit/Governance/ServicePublicationPolicyTest.php` | 13 policy tests |

## Files modified

| File | Change |
|---|---|
| `backend/modules/JeaServices/Models/ServiceDefinition.php` | Added 15 fillable + 5 casts + `hasUatApproval` / `isPublished` / `isSuspended` / `isRetired` / `lifecycle` / private schema-check helpers. PHPDoc property annotations for PHPStan. |

## Gates

| Gate | Result |
|---|---|
| Focused governance tests | PASS (22 / 22 / 39 assertions / 207ms) |
| Broader affected tests (Service/Application/Sanction filter) | PASS (182 / 183 / 1 skipped / 22.7s) |
| PHPStan on new code | PASS (0 errors) |

## Residuals

| RESIDUAL_ID | Owner | Status |
|---|---|---|
| RES-SG01-01 | SG-N/A | OPEN — collapse legacy `status` field once every consumer migrated (out of program scope) |
| RES-SG01-02 | SG-02 | OPEN — transition-window preference order between legacy `status='active'` and new `publication_status` |

## Verdict

**PASS** — Lifecycle constitution + publication policy + typed verdict + supporting tests are in place. Runtime enforcement is SG-02's responsibility.
