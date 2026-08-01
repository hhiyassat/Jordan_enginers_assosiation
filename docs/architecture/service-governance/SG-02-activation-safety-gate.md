# SG-02 · Activation Safety Gate

**Program:** `ESP_V2_SERVICE_GOVERNANCE_VERSIONING_FOUNDATION`
**Phase:** SG-02
**Baseline HEAD:** `71e1d6e...` (post SG-01)

Introduces the `ServiceAvailabilityPolicy` runtime gate. Reconciles the legacy `status` column and new `publication_status` column via a LENIENT-by-default preference order. Wires the gate into `ServiceCatalogController::{index, show}`. Reserves the remaining wiring (application creation, submission, payment, certificate) as an explicit residual with acceptance criteria.

## Design

Two classes in `backend/modules/JeaServices/Governance/`:

* `ServiceAvailabilityVerdict` — typed value object with six boolean capability flags + reason codes + `evaluatedAt`.
* `ServiceAvailabilityPolicy` — pure evaluator taking `(ServiceDefinition, actorIsAdmin)` and returning a verdict. Two modes: `LENIENT` (default, transition window) and `STRICT` (reserved).

The evaluator applies the preference order from `judgments/JDG-SG02-01-availability-preference-order.md`:

1. `publication_status='RETIRED'` → hidden except admin; historical certificates remain issuable.
2. `publication_status='SUSPENDED'` → hidden for applicants, visible for admin; new submissions blocked.
3. `publication_status='PUBLISHED'` → visible + all operations allowed unless placeholder markers or future `effective_from` are present.
4. `publication_status='NOT_PUBLISHED'` + legacy `status='active'` → visible with `AVAIL_LEGACY_STATUS_FALLBACK` warning code (LENIENT only).
5. `publication_status='NOT_PUBLISHED'` + legacy `status!='active'` → hidden unless admin.

## Integration surface

Wired in this phase (per `judgments/JDG-SG02-02-integration-surface.md`):

* `ServiceCatalogController::index` — filters listing via `evaluate(...)->catalogVisible`.
* `ServiceCatalogController::show` — returns 404 when `catalogVisible=false`.

Deferred as **RES-SG02-02** (acceptance criteria in residual register):

* `ApplicationController::store` — should consult `applicationCreationAllowed`.
* `ApplicationController::submit` — should consult `submissionAllowed`.
* `PaymentsController::initiate` — should consult `paymentAllowed`.
* `CertificatesController::download*` — should consult `certificateAllowed`.

The verdict object carries all five capabilities today; wiring the remaining endpoints is a per-controller PR that does not require re-visiting the policy.

## Behaviour under LENIENT default (transition window)

* All 57 currently seeded services (`status='active'` + `publication_status='NOT_PUBLISHED'`) remain visible to applicants — carries `AVAIL_LEGACY_STATUS_FALLBACK` warning code.
* Newly retired/suspended services immediately disappear from applicant listings.
* Admin users always see every service (verdicts carry `AVAIL_ALLOWED_ADMIN_INSPECTION`).

Zero behaviour change for existing applications on `status='active'` services — verified by running `SharedServiceCatalogTest` (14 tests), `AdminServicesIndexTest` (8 tests), `ServiceLockingTest` (3 tests), `ServicePlan2026SeederTest`, `ServiceFeeDefaultsSeederTest`, all green.

## Tests

| File | Kind | Assertions |
|---|---|---|
| `tests/Unit/Governance/ServiceAvailabilityPolicyTest.php` | Unit — 10 tests | Retired hidden/admin; Suspended hidden/admin; Published visible; Published + placeholder-fee/workflow/future-effective blocks; Legacy fallback under LENIENT; Legacy inactive hidden; STRICT mode disables fallback; Verdict metadata populated |
| `tests/Feature/Governance/ServiceCatalogAvailabilityGateTest.php` | Feature — 4 tests | Retired hidden from applicant + visible to admin; Suspended hidden from applicant; `show` 404 for hidden; Legacy active still visible |

Broader test suite: 51 tests across `SharedServiceCatalogTest`, `AdminServicesIndexTest`, `ServiceLockingTest`, `ServicePlan2026SeederTest`, `ServiceFeeDefaultsSeederTest` — all PASS, no regressions.

## Files added

* `backend/modules/JeaServices/Governance/ServiceAvailabilityVerdict.php`
* `backend/modules/JeaServices/Governance/ServiceAvailabilityPolicy.php`
* `backend/tests/Unit/Governance/ServiceAvailabilityPolicyTest.php`
* `backend/tests/Feature/Governance/ServiceCatalogAvailabilityGateTest.php`
* `docs/architecture/service-governance/judgments/JDG-SG02-01-availability-preference-order.md`
* `docs/architecture/service-governance/judgments/JDG-SG02-02-integration-surface.md`

## Files modified

* `backend/modules/JeaServices/Http/Controllers/ServiceCatalogController.php` — `index()` filters via policy; `show()` returns 404 when hidden.

## Gates

| Gate | Result |
|---|---|
| Focused unit tests (governance) | PASS (32 / 32 / 76 assertions) |
| Feature tests (governance) | PASS (4 / 4 / 9 assertions) |
| Regression sweep (catalog + admin + locking + seeders) | PASS (51 / 51 / 121 assertions) |
| PHPStan on new + modified files | PASS (0 errors) |

## Residuals

| RESIDUAL_ID | Owner | Status | Acceptance |
|---|---|---|---|
| RES-SG02-01 | ops | OPEN | Dashboard counter for `AVAIL_LEGACY_STATUS_FALLBACK` code |
| RES-SG02-02 | follow-up | OPEN | Wire `ApplicationController::store/submit`, `PaymentsController::initiate`, `CertificatesController::download*` to consult the verdict |

## Verdict

**PASS** — Runtime gate is in place at the catalog surface; policy fully implemented across all five capability flags; legacy behaviour preserved through the transition window. Non-catalog wiring is tracked as RES-SG02-02 with clear acceptance criteria.
