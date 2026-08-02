# TD-09 · UAT Readiness Assessment

**UAT_READINESS_VERDICT = NOT_UAT_READY**

## Entry criteria checklist

| Criterion | Status | Blocker |
|---|---|---|
| Signed SRS baseline 2.0 | NOT_MET | SRS v1.2 remains DRAFT_REVIEW |
| Approved detailed RTM | PARTIAL | RTM emitted; JEA product approval pending |
| Approved calculation `RuleVersion`s | NOT_MET | OD-11, OD-19, OD-20 unresolved (Wells + NetDepth PROVISIONAL) |
| Approved workflow version | NOT_MET | OD-18, OD-29, OD-31, OD-32, OD-33, OD-34 unresolved |
| Approved financial `RuleVersion`s | NOT_MET | OD-01, OD-10, OD-15, OD-17, OD-19, OD-35 unresolved |
| Approved attachment limits | NOT_MET | OD-24 unresolved |
| Oracle / DLS / BURA test adapters or exclusions | NOT_MET | Ports modelled; no signed contracts (OD-30) |
| Storage + malware-scan test adapters | NOT_MET | Ports interface-only |
| Payment sandbox evidence | NOT_MET | No sandbox contract signed |
| Test-role + permission matrix | PARTIAL | Roles enumerated in `test-data.md`; assignments pending JEA IAM |
| Test-data preparation plan | READY | See `test-data.md` |
| Environment deployment verified | PARTIAL | Local dev + Postgres 15 via docker-compose ready; UAT-dedicated env pending |
| RTM has no unexplained missing Must requirement | MET | All FR-SS-001..090 present |
| Deterministic acceptance scenarios cover in-scope areas | MET | 50 scenarios classified |

## Exit criteria (for a future UAT)

| Criterion | Definition |
|---|---|
| All EXECUTABLE_ACCEPTANCE scenarios pass in UAT env | 100% of green-classified scenarios pass on UAT-dedicated env |
| All BLOCKED_PENDING_* scenarios have signed exclusion or resolution | JEA product signs exclusion for each blocked scenario |
| Zero unexplained dual-run discrepancies | TD-10 dual-run comparison has 0 `UNEXPLAINED_DIFFERENCE` classifications |
| No production integration silently activated | Container binding audit + runtime probe confirms only legacy adapters bound |
| Data-integrity: legacy submissions still process | Post-UAT regression on production-shaped fixtures matches pre-UAT snapshot |

## Proposed UAT scope

**IN SCOPE (once entry criteria met):**
- Applicant creates + submits SRV-001 draft (legacy pilot path — TC-APP-001, TC-SUB-001)
- Government-sector rejection (TC-SUB-002)
- Cross-office isolation (existing regression)
- Applicant document upload + PDF/DWG magic-byte rejection (existing regression)
- ServiceDefinitionVersion binding + audit persistence (TC-BIND-001, TC-SUB-001)

**OUT OF SCOPE for first UAT:**
- Every scenario classified `BLOCKED_PENDING_OD` / `BLOCKED_PENDING_CONTRACT` / `BLOCKED_PENDING_ADAPTER`
- Target-domain calculation activation
- Payment / receipt / certificate flows
- BURA / Oracle / DLS integrations
- Any workflow transition beyond `submitted`

## Test-role matrix (proposed)

| Role | UAT users needed | Notes |
|---|---|---|
| applicant (engineering office) | 2 (orgA, orgB) | cross-office isolation |
| staff (offices-dept) | 1 | approve / return; not currently reachable via runtime (workflow inactive) |
| reviewer (first / second) | 2 | out of UAT scope until OD-34 signed |
| auditor | 1 | read-only audit trail verification |
| admin | 1 | seeder + service-version publication (out of UAT scope for target policy) |

## Test-data preparation

See `test-data.md`.

## Environment-readiness checklist

- Postgres 15-alpine test DB — READY (`docker compose up -d postgres`)
- Redis — READY (compose service)
- Storage: `Storage::fake()` for tests; **no** production S3/local disk configured
- Migrations applied — READY on ephemeral test DB
- Seeders `Srv001PilotSeeder` + `Srv001RulesSeeder` — READY
- UAT-dedicated environment (not shared with development) — **PENDING**
