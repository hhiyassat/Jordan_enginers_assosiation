# RC-02 · Activation Gate Closure (closes RES-SG02-02)

Foundation program left `RES-SG02-02` open: `ServiceAvailabilityPolicy` was consulted only in `ServiceCatalogController::{index, show}`. Four other entry points (creation, submission, payment initiation, certificate issuance) still relied on the legacy `where('status','active')` filter alone. This report closes the gap.

Judgment: `judgment-records/JDG-RC01-02-RES-SG02-02.md`.

## Gate wiring — exact controller-by-controller state

| Entry point | Capability consulted | Behaviour |
|---|---|---|
| `ServiceCatalogController::index` | `catalogVisible` | (foundation SG-02 — unchanged) |
| `ServiceCatalogController::show` | `catalogVisible` | (foundation SG-02 — unchanged) |
| `ApplicationController::store` | `applicationCreationAllowed` | **new** — 422 with reason codes when denied |
| `ApplicationController::submit` | `submissionAllowed` | **new** — 422 with reason codes when denied |
| `PaymentsController::initiate` | `paymentAllowed` | **new** — 422 with reason codes when denied |
| `PaymentsController::confirm` | (intentionally none) | admin manual reconciliation of a prior demand — must not be blocked by later state change |
| `PaymentCallbackController::handle` | (intentionally none) | system callback honouring a prior payment demand — must not be blocked by later state change |
| `CertificatesController::issue` | `submissionAllowed` | **new** — 422 with reason codes; `submissionAllowed` is the closest capability flag for "actively processing new workflow output" |
| `CertificatesController::downloadPdf` | (intentionally none) | historical viewing must remain available (mandate) |
| `CertificatesController::downloadPdfAuthenticated` | (intentionally none) | same — historical viewing |

## Behaviour matrix (LENIENT default mode)

| Service state | Creation | Submission | Payment initiate | Cert issue | Historical download | Payment callback | Manual confirm |
|---|---|---|---|---|---|---|---|
| PUBLISHED (approved) | allowed | allowed | allowed | allowed | allowed | allowed | allowed |
| SUSPENDED | rejected | rejected | rejected | rejected | allowed | allowed | allowed |
| RETIRED | rejected | rejected | rejected | rejected | allowed | allowed | allowed |
| NOT_PUBLISHED + legacy `status='active'` (57 seeded services) | allowed w/ AVAIL_LEGACY_STATUS_FALLBACK | allowed w/ warning | allowed w/ warning | allowed w/ warning | allowed | allowed | allowed |
| NOT_PUBLISHED + legacy `status='inactive'` | rejected (admin can inspect via show) | rejected | rejected | rejected | allowed | allowed | allowed |

The mandate condition "Do not block payment callbacks merely because the service was later suspended when a valid payment demand already exists" is satisfied by leaving `PaymentCallbackController::handle` and `PaymentsController::confirm` unguarded. The two ungated paths process a **prior obligation** (a payment demand already created via `initiate`); the gate stops NEW obligations, not the settlement of existing ones.

## Historical viewing preservation

The mandate condition "Historical viewing must remain available" is satisfied by:

* `ServiceAvailabilityPolicy` returning `certificateAllowed=true` on RETIRED and SUSPENDED (foundation SG-02 — unchanged).
* `CertificatesController::downloadPdf` and `downloadPdfAuthenticated` — no policy consultation added.
* `ApplicationController::show` — no policy consultation added (was already unguarded; historical apps remain viewable).

## Legacy unversioned application preservation

A draft application created BEFORE the availability policy was wired sits on `status='active' + publication_status='NOT_PUBLISHED'`. Under LENIENT mode:

* Its owner can still `update` and `submit` (verdicts return `submissionAllowed=true` via `AVAIL_LEGACY_STATUS_FALLBACK`).
* Only if the ADMIN transitions the service to SUSPENDED/RETIRED before the applicant submits does the gate reject — and then only for that specific pending draft.

This matches the mandate: "existing draft" can proceed under the transition-window fallback; only a service that has been actively withdrawn blocks further action on drafts against it.

## Tests

`backend/tests/Feature/Governance/ActivationGateEndToEndTest.php` — 10 tests, 16 assertions, all pass:

* `test_store_rejects_creation_on_retired_service` — routing to a non-active row (404 path)
* `test_store_rejects_creation_when_publication_retired` — legacy status='active' + publication_status='RETIRED' → 422 with `AVAIL_HIDDEN_RETIRED`
* `test_store_allows_creation_on_legacy_active_service` — LENIENT fallback path → 201
* `test_store_rejects_creation_when_service_suspended` → 422 with `AVAIL_HIDDEN_SUSPENDED_FOR_APPLICANT`
* `test_submit_rejects_when_service_becomes_retired_between_creation_and_submit` — legacy → RETIRED transition, submit blocked
* `test_payment_initiate_rejects_when_service_suspended_and_no_prior_demand` → 422
* `test_payment_initiate_allowed_on_legacy_active_service` → 201
* `test_certificate_issuance_rejected_on_retired_service` → 422 with `AVAIL_HIDDEN_RETIRED`
* `test_payment_callback_processes_prior_demand_even_when_service_suspended` — source-level assertion that `PaymentCallbackController` does not import `ServiceAvailabilityPolicy` (documented invariant)
* `test_payment_confirm_manual_reconciliation_not_gated` — source-level assertion that `PaymentsController` references the policy exactly once (inside `initiate`)

## Gates

| Gate | Result |
|---|---|
| Focused activation-gate tests | PASS (10/10/16 assertions) |
| Governance-adjacent regression (Governance/Srv001/Workflow/ServiceCatalog/Application/Payment/Certificate/Submission/SharedCatalog) | PASS (275/277/2 skipped/1262 assertions) |
| PHPStan (full baseline, `--memory-limit=1G`) | PASS (0 errors) |

## Files added

* `backend/tests/Feature/Governance/ActivationGateEndToEndTest.php` (10 tests)
* `docs/architecture/service-governance/readiness-closure/judgment-records/JDG-RC01-02-RES-SG02-02.md`

## Files modified

* `backend/modules/JeaServices/Http/Controllers/ApplicationController.php` (+2 gates: store, submit)
* `backend/modules/JeaServices/Http/Controllers/PaymentsController.php` (+1 gate: initiate)
* `backend/modules/JeaServices/Http/Controllers/CertificatesController.php` (+1 gate: issue)
* `docs/architecture/service-governance/service-governance-residual-register.md` (RES-SG02-02 → CLOSED)

## Verdict

**RES-SG02-02 CLOSED**. All five entry points listed in the SG-02 mandate are wired through the availability policy. Prior-obligation paths (callback, manual confirm) intentionally excluded per the closure mandate. Historical download paths intentionally excluded per the mandate.
