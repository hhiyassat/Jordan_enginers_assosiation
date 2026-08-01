# Service Governance Residual Register

Every residual raised by a judgment record, tracked to closure.

| RESIDUAL_ID | Raised by | OWNER | RISK | BLOCKS_WHAT | STATUS | CLOSURE_EVIDENCE |
|---|---|---|---|---|---|---|
| RES-SG00-01 | JDG-SG00-01 | SG-03 | HIGH | SG-03 completion | CLOSED | Closed by JDG-SG03-01: schema-only snapshot |
| RES-SG03-01 | JDG-SG03-01 | post-SG-06 | MEDIUM | Full-reproducibility snapshot | OPEN | Extension-declaration snapshotting once registry supports per-version binding |
| RES-SG03-02 | JDG-SG03-02 | UX follow-up | LOW | (observability) | OPEN | Optional draft-view hint "will be bound to version X at submit" |
| RES-SG03-03 | JDG-SG03-03 | ops | LOW | (per-application manual review) | OPEN | Manual attachment procedure with maker-checker + audit |
| RES-SG04-01 | JDG-SG04-01 | per-service onboarding | LOW | Future services need rule definitions | OPEN | Copy Srv001RulesSeeder pattern per new service |
| RES-SG04-02 | JDG-SG04-02 | ops follow-up | LOW | Manual recalc UX | OPEN | Manual recalc UI + audit event definition |
| RES-SG05-01 | JDG-SG05-01 | as-needed | LOW | Deferred extension contracts | OPEN | Extract ServiceEligibilityPolicy / ServiceStageAction / ServiceFeeStrategy / ServiceIntegrationContributor when a second consumer appears |
| RES-SG00-02 | JDG-SG00-02 | Product owner / JEA | HIGH | Migration of SRV-001 from LEGACY_PILOT to canonical | OPEN | Signed calculator source attached to service UAT |
| RES-SG00-03 | JDG-SG00-03 | Product / JEA | MEDIUM | Publication of every service currently classified `_UNAPPROVED` | OPEN | Signed JEA decision per service |
| RES-SG00-04 | JDG-SG00-04 | SG-06 | LOW | SG-06 completion | CLOSED | SG-06 parallel implementation demonstrates the target pattern; runtime swap is RES-SG06-01 |
| RES-SG06-01 | JDG-SG06-01 | post-program | MEDIUM | Runtime consumption of typed decisions | OPEN | Wire calling use case to invoke LegacySrv001SubmissionPolicy and replace Srv001Guard runtime path |
| RES-SG01-01 | JDG-SG01-01 | (out of program scope) | LOW | Legacy `status` column cleanup | OPEN | Future cleanup once every consumer migrated to `publication_status` |
| RES-SG01-02 | JDG-SG01-02 | SG-02 | MEDIUM | SG-02 completion | CLOSED | Closed by JDG-SG02-01: LENIENT default with legacy fallback |
| RES-SG02-01 | JDG-SG02-01 | ops | LOW | (observability only) | OPEN | Dashboard counter for AVAIL_LEGACY_STATUS_FALLBACK |
| RES-SG02-02 | JDG-SG02-02 | closed by readiness-closure RC-02 | MEDIUM | Complete SG-02 enforcement surface | CLOSED | RC-02 wired ApplicationController::{store,submit}, PaymentsController::initiate, CertificatesController::issue. PaymentCallbackController + PaymentsController::confirm intentionally UNGATED (prior-obligation processing). Cert downloadPdf/downloadPdfAuthenticated intentionally UNGATED (historical viewing). 10 focused tests + 275 regression tests pass. |
| E2E-FLAKE-01 | RC-04 | frontend platform | LOW | Intermittent E2E flake on notifications.spec.ts | OPEN | Add explicit `waitForResponse('/api/v1/notifications*')` before dialog assertion; CI runs are not affected (reuseExistingServer=false) |
