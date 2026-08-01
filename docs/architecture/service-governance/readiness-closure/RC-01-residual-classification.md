# RC-01 · Residual Classification

Every open residual from the foundation program is classified per the readiness vocabulary defined in the closure mandate §3. Judgment records per residual live in `readiness-closure/judgment-records/JDG-RC01-*.md`.

## Count reconciliation

Register entries at `93fa162`:

| Bucket | Count | IDs |
|---|---|---|
| CLOSED | 3 | RES-SG00-01, RES-SG00-04, RES-SG01-02 |
| OPEN | 12 | RES-SG00-02, RES-SG00-03, RES-SG01-01, RES-SG02-01, RES-SG02-02, RES-SG03-01, RES-SG03-02, RES-SG03-03, RES-SG04-01, RES-SG04-02, RES-SG05-01, RES-SG06-01 |
| **Total** | **15** | (matches foundation final report `OPEN=12 + CLOSED=3`) |

Foundation final report claimed `STOPPED_BUSINESS_DECISIONS=3`. Re-inspection: only **2 of the 12 open residuals are strictly business-authority stopped decisions** (RES-SG00-02 SRV-001 calculators, RES-SG00-03 UAT sign-off per service). The foundation report's third count was extension-contract deferral (RES-SG05-01), which is a technical judgment (proven-consumer test), not a business-authority decision. This closure program treats:

* `STOPPED_BUSINESS_DECISIONS = 2` (RES-SG00-02, RES-SG00-03).
* RES-SG05-01 = technical deferral pending second consumer (not a business decision).

## Classification table

| RESIDUAL_ID | DESCRIPTION | ORIGIN | OWNER | AFFECTED_CAPABILITY | CURRENT_EFFECT | TARGET-DOMAIN-START_EFFECT | PUBLICATION_EFFECT | PRODUCTION_EFFECT | CLASSIFICATION | CLOSURE_ACTION |
|---|---|---|---|---|---|---|---|---|---|---|
| RES-SG00-02 | SRV-001 calculators (WellsCount, NetDepth) lack JEA-signed source | JDG-SG00-02 | Product owner / JEA | Publication of SRV-001 target-canonical | PROVISIONAL marker present; runtime unchanged | Does not block target-domain **coding** — target-domain classes can be built against approved matrix + placeholders for provisional calculators | Blocks target-canonical publication until JEA signs | Blocks production of the target-canonical rule set | `BLOCKS_TARGET_DOMAIN_PUBLICATION_ONLY` + `BUSINESS_DECISION_STOPPED` | JEA signed decision + rule-version PROMOTION to APPROVED |
| RES-SG00-03 | Every `_UNAPPROVED` fee/workflow/document classification per SG-00 CSV pending JEA sign-off | JDG-SG00-03 | Product / JEA | Publication of any of the 57 services | LENIENT preference-order allows legacy `status='active'` visibility today | Does not block target-domain start (only SRV-001 target is in scope; other services untouched) | Blocks publication of the affected service | Blocks production canonical rollout of the affected service | `BLOCKS_TARGET_DOMAIN_PUBLICATION_ONLY` + `BUSINESS_DECISION_STOPPED` | Signed JEA decision per service |
| RES-SG01-01 | Legacy `status` column cleanup once every consumer migrated to `publication_status` | JDG-SG01-01 | out of scope | Code-organisation hygiene | Two columns coexist; both consulted | None — legacy column is preserved for backward compatibility | None | None (dev-hygiene only) | `NON_BLOCKING_LEGACY_RESIDUAL` | Future consolidation, not required |
| RES-SG02-01 | Ops dashboard counter for `AVAIL_LEGACY_STATUS_FALLBACK` verdict code | JDG-SG02-01 | ops | Observability | Verdicts carry the code; no dashboard aggregates it | None — observability-only, doesn't affect correctness | None | Non-blocking observability improvement | `NON_BLOCKING_LEGACY_RESIDUAL` | Ops dashboard when needed |
| RES-SG02-02 | Wire `ApplicationController::{store, submit}`, `PaymentsController::initiate`, `CertificatesController::download*` to consult ServiceAvailabilityPolicy | JDG-SG02-02 | this program | Application creation, submission, payment, certificate | Controllers currently rely on legacy `status='active'` filter alone; unapproved services with `status='active'` accept operations | **Would block target-domain start**: SG-02 mandate requires unapproved-service creation/submission/payment/certificate to be blocked before target-domain implementation depends on the availability verdict | Blocks strict-mode publication | Blocks strict-mode enforcement | **`BLOCKS_TARGET_DOMAIN_START`** — closed by RC-02 | Wiring implemented in RC-02 with tests |
| RES-SG03-01 | Extension-declaration snapshotting (schema-version already snapshots schema; declaration snapshot needs registry per-version binding) | JDG-SG03-01 | post-SG-06 | Full reproducibility | Schema snapshot captures configuration; extension declarations resolved at runtime | Doesn't block target-domain start (target class implements ServiceSubmissionPolicy; registry binding is per-service already) | Would surface once target-canonical publication supersedes LegacySrv001SubmissionPolicy | Blocks strict per-version extension binding | `BLOCKS_TARGET_DOMAIN_PUBLICATION_ONLY` | Registry refactor + declaration snapshot column |
| RES-SG03-02 | Draft-view UX hint "will be bound to version X at submit" | JDG-SG03-02 | UX follow-up | Applicant transparency | Draft users see current schema (correct behaviour); no forward-hint | None — UX only | None | Non-blocking UX polish | `NON_BLOCKING_LEGACY_RESIDUAL` | UX ticket |
| RES-SG03-03 | Manual per-application attachment procedure (rare-case) | JDG-SG03-03 | ops | Legacy application binding | Explicit LEGACY_UNVERSIONED classification exists | None | None | Non-blocking edge case | `NON_BLOCKING_LEGACY_RESIDUAL` | Ops procedure when needed |
| RES-SG04-01 | Per-service onboarding pattern for new rule definitions | JDG-SG04-01 | per-service onboarding | Adding rules for future services | Only SRV-001 rules seeded | None — the pattern IS the SRV-001 seeder; copy at target-canonical time | Needed when publishing another service | Needed at production per new service | `BLOCKS_TARGET_DOMAIN_PUBLICATION_ONLY` (for services other than SRV-001) | Copy `Srv001RulesSeeder` pattern per new service |
| RES-SG04-02 | Manual recalc UX + audit event definition | JDG-SG04-02 | ops follow-up | Manual-recalc admin flow | Writer supports it; no UX | None — admin can invoke programmatically if needed | None | Non-blocking future admin UX | `NON_BLOCKING_LEGACY_RESIDUAL` | UX + audit event ticket |
| RES-SG05-01 | Four deferred extension contracts (ServiceEligibilityPolicy / ServiceStageAction / ServiceFeeStrategy / ServiceIntegrationContributor) | JDG-SG05-01 | as-needed | Extension surface breadth | Two of six contracts implemented; four deferred | None — target-domain SRV-001 uses ServiceSubmissionPolicy + ServiceCalculationPolicy only | None | Non-blocking — extract on second-consumer trigger | `NON_BLOCKING_LEGACY_RESIDUAL` | Extract when a second consumer appears |
| RES-SG06-01 | Wire calling use case to consume LegacySrv001SubmissionPolicy typed decisions and replace Srv001Guard runtime path | JDG-SG06-01 | post-program | SRV-001 runtime path | Parallel LegacySrv001SubmissionPolicy proven; runtime still uses Srv001Guard | Does not block target-domain start — the target SRV-001 service version replaces both classes | Blocks post-target-canonical cleanup of legacy adapter | Not itself production-blocking | `NON_BLOCKING_LEGACY_RESIDUAL` (per RC-06 detailed analysis) | Removed when target SRV-001 replaces legacy; documented expiry condition |

## Verdict classification summary

| Classification | Count | IDs |
|---|---|---|
| CLOSED (in foundation) | 3 | RES-SG00-01, RES-SG00-04, RES-SG01-02 |
| **BLOCKS_TARGET_DOMAIN_START** | 1 | RES-SG02-02 |
| BLOCKS_TARGET_DOMAIN_PUBLICATION_ONLY | 3 | RES-SG00-02, RES-SG00-03, RES-SG04-01 (per-non-SRV-001 service), RES-SG03-01 (post-canonical) |
| BUSINESS_DECISION_STOPPED | 2 | RES-SG00-02, RES-SG00-03 (overlap with above — same items are both stopped-decisions and publication-blockers) |
| NON_BLOCKING_LEGACY_RESIDUAL | 6 | RES-SG01-01, RES-SG02-01, RES-SG03-02, RES-SG03-03, RES-SG04-02, RES-SG05-01, RES-SG06-01 |

**Only one residual (RES-SG02-02) blocks target-domain START.** Every other open item either blocks only publication (waiting on JEA sign-off) or is non-blocking legacy debt.

## Detailed judgment records

One judgment record per residual under `readiness-closure/judgment-records/`. The high-signal records are:

* `JDG-RC01-02-RES-SG02-02.md` — the sole target-start blocker.
* `JDG-RC01-06-RES-SG06-01.md` — the legacy Eloquent-write residual (also analysed in RC-06).
* `JDG-RC01-01-RES-SG00-02.md`, `JDG-RC01-03-RES-SG00-03.md` — the two stopped business decisions.

The remaining residuals share reasoning patterns (non-blocking legacy debt); their records are shorter but complete.
