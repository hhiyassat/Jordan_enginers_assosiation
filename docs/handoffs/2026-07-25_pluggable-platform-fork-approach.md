# Handoff — ESP Core: Fork for Isolation, Converge Through Packages

**Version:** 2 (amended after engineering review)  
**Prepared:** 2026-07-25  
**For:** Management review  
**Prepared by:** Development team  
**Decision required by:** Management approval before P0 start

---

## Executive Summary / الملخّص التنفيذي

**EN.** We recommend isolating the ESP Core Pluggable Platform transformation into a new repository (`esp-core`) cloned from `esp-v2`. The two repositories evolve independently for the 6-11 month platform program. Convergence happens **through packages**, not through a git merge: at the end of the program, JEA production runs as `ESP Core + JEA Organization Pack + JEA Service Packages + JEA Integrations + JEA Configuration`. This isolates JEA production from runtime risk during the transformation while making the "clean-core" test (Constitutional Rule C-02) achievable.

**AR.** نُوصي بعزل برنامج تحويل ESP إلى منصّة قابلة للتركيب في ريبو جديد (`esp-core`) مأخوذ من `esp-v2`. يتطوّر المستودعان بشكل مستقل طوال 6-11 شهراً. **يتم التقارب عبر الحزم، لا عبر دمج الـ git**: في نهاية البرنامج، يعمل نظام النقابة كـ `ESP Core + JEA Organization Pack + JEA Service Packages + JEA Integrations + JEA Configuration`. هذا يعزل النظام الإنتاجي عن مخاطر التشغيل أثناء التحويل، ويجعل اختبار "الـ Core النظيف" (القاعدة الدستورية C-02) قابلاً للتحقيق.

---

## 1. Background — Where we are

The ESP v2 codebase currently:
- Serves JEA production (~90k members, 80k buildings/year target)
- Just completed a 16-workstream architectural refactor (Jul 2026)
- Has physical separation between platform / modules / plugins / integrations
- Has enforcement in CI (dep-cruiser + architecture tests)

The **ESP Core spec v0.1** (received 2026-07-25) proposes transforming this codebase into a **business-neutral, installable, multi-organization service platform** — comparable in ambition to Moodle. This transformation is:
- 46 weeks of dedicated work (10 phases, 30 workstreams)
- Constitutionally required to preserve JEA production throughout (spec §3.2)
- Substantial in blast radius — touches nearly every part of the codebase

**Key question:** how do we do this without exposing JEA production to platform-experiment risk?

---

## 2. The Choice — Three Approaches Compared

### Option A: Transform In-Place (single repo)

Continue on `esp-v2` and evolve it into the pluggable platform. JEA production and platform development share one codebase.

| Pros | Cons |
|------|------|
| Single codebase, no divergence to manage | JEA production exposed to platform experiments |
| Every change immediately visible to JEA users | Hotfix requests slow platform work |
| No convergence step later | Feature-flags become the only safety net |
| | Rollback of platform change also affects JEA |

**Risk profile:** HIGH runtime risk.

### Option B: Fork for Isolation, Converge Through Packages (recommended)

Clone `esp-v2` to a new repo `esp-core`. Both projects run independently. Once ESP Core reaches maturity (proven at end of P3, scaled through P6), JEA migrates to consume ESP Core as **an installation of packages** — not as a git merge. The final JEA production is `Core + Organization Pack + Service Packages + Integrations + Configuration`.

| Pros | Cons |
|------|------|
| No direct runtime risk to JEA production from ESP Core development | Two codebases to maintain during the transition |
| Platform team works with full architectural freedom | Drift risk between the two — requires disciplined governance |
| Clear decoupling of business (JEA) vs. platform (Core) work | Requires one-way source-of-truth policy per fix category (see §7) |
| Enables the constitutional "clean core" test — Core boots with zero JEA code | Convergence is architectural, not automatic — must be rehearsed early |
| Reduces political pressure — platform work isn't blocked by JEA tickets | Requires environment ladder from P1 (see §8) |

**Risk profile:** LOW-MEDIUM. Runtime isolated from production. Drift is manageable with the governance in §7.

### Option C: Wait

Postpone the platform transformation. Focus on JEA delivery.

| Pros | Cons |
|------|------|
| Zero disruption | Loses window to build a reusable, marketable platform |
| Team focus on JEA | Every additional JEA feature deepens JEA-specific coupling |
| No new cost | Cost of future transformation grows monotonically |
| | No differentiator — locked into a single customer |

**Risk profile:** LOW technically, HIGH strategically.

---

## 3. Recommendation — Option B

### Why isolation + convergence-through-packages is the right call

1. **JEA production is contractually critical** — 90k members depend on it. Runtime experiments are unacceptable in that environment.
2. **The convergence path is architecturally intended but must be proven incrementally** — the spec envisions JEA production running as `Core + Packs + Packages`. This is the intent, but it's a claim to be proven at P3 (first rehearsal), not a foregone conclusion.
3. **Platform transformation is a long-horizon strategic investment** — 6-11 months of work needs a stable environment, not one interrupted by daily JEA firefighting.
4. **Architectural experiments are risk-free at runtime** — Core in isolation lets us try patterns aggressively without production consequences. This preserves optionality.
5. **We already have the physical separation** — the 16-workstream refactor makes forking easy. The two projects start from the same, well-structured baseline.

### End-state model (convergence via packages, not merge)

```
                              JEA Production Distribution
                              ═══════════════════════════

                                    ESP Core
                                       +
                              JEA Organization Pack
                                       +
                         JEA Service Packages (services, projects,
                                       discipline, dues, ...)
                                       +
                            JEA Integrations (GSB, Nashmi, ...)
                                       +
                             JEA Configuration (branding, defaults,
                                       activation matrix)
                                       =
                              JEA Production Distribution
                              ═══════════════════════════

  Not: `git merge esp-core into esp-v2`
  But: `install packages onto ESP Core, activate for JEA organization`
```

### The two-repo model during transformation

```
┌──────────────────────────────────┐    ┌───────────────────────────────────┐
│  esp-v2 (current)                 │    │  esp-core (new)                    │
│  ─────────────────────────        │    │  ─────────────────────────         │
│  • JEA production                 │    │  • Platform transformation          │
│  • JEA-specific fixes/features    │    │  • Neutralization of Core           │
│  • Deploys to Hostinger           │    │  • Package system + SDK             │
│  • Team: JEA delivery             │    │  • Team: platform architecture      │
│  • Timeline: ongoing              │    │  • Timeline: 6-11 months            │
│                                   │    │                                    │
│  Repo: hhiyassat/code-generation  │    │  Repo: hhiyassat/esp-core (new)    │
│  Deploy: hhiyassat/               │    │  Deploy: environment ladder — CI    │
│    Jordan_enginers_assosiation    │    │    from P1, staging by P4 (§8)     │
└──────────────────────────────────┘    └───────────────────────────────────┘
                │                                            │
                │  Governance = §7 (one-way per category)   │
                └────────────────────────────────────────────┘
                                    │
                            ┌───────▼────────────────┐
                            │  Convergence rehearsals │
                            │  P3 — first proof        │
                            │  P6 — full migration     │
                            │       rehearsal          │
                            │  P10 — production        │
                            │        migration         │
                            └────────────────────────┘
```

---

## 4. Origin Baseline (must be recorded before fork)

Before creating the new repository, the following facts are pinned. The pinning itself becomes the first commit / tag on `esp-core` and is referenced in every subsequent architecture decision.

| Field | Value |
|-------|-------|
| `ORIGIN_REPOSITORY` | `hhiyassat/code-generation` (esp-v2) |
| `ORIGIN_COMMIT` | (to be set at fork time; latest `main` sha) |
| `ORIGIN_TAG` | `esp-core-origin-v0.1` (created on esp-v2 at fork time) |
| `FORK_DATE` | (to be recorded) |
| `DATABASE_SCHEMA_BASELINE` | Snapshot of all migrations up to origin commit; committed as `docs/origin/schema-baseline.sql` |
| `API_BASELINE` | OpenAPI dump of `/api/v1/*` at origin commit; committed as `docs/origin/api-baseline.yaml` |
| `TEST_BASELINE` | PHPUnit + Vitest counts + full pass log at origin; committed as `docs/origin/test-baseline.md` |
| `DEPENDENCY_LOCK_DIGESTS` | SHA-256 of `composer.lock` + `package-lock.json` at origin |

**Purpose:** every future divergence between esp-v2 and esp-core can be traced back to a well-defined common ancestor. Enables audit of "when did behavior X diverge" without archaeology.

---

## 5. Data Ownership Model (must be adopted before P0)

Every table in the current esp-v2 database is classified into one of five ownership categories. This classification governs migration ownership, uninstall policy, and data retention.

| Ownership | Meaning | Examples (from esp-v2 today) | Migration owner |
|-----------|---------|------------------------------|-----------------|
| `CORE_OWNED` | Platform-neutral tables required by every distribution | `users`, `organizations`, `notifications`, `audit_logs`, `sessions`, `personal_access_tokens` | ESP Core |
| `ORG_OWNED` | Per-organization configuration state | `organization_packages` (activation matrix, new in P4), branding, defaults | ESP Core |
| `PACKAGE_OWNED` | Business data specific to a service package | `applications`, `service_definitions`, `certificates`, `complaints`, `sanctions`, `legal_fines`, `office_ceilings`, `engineer_discipline_quotas`, `recurring_obligations`, `manual_references`, ... | The owning package |
| `INTEGRATION_OWNED` | Data owned by an external-system adapter | `gsb_call_logs`, `integration_cycles` | The owning integration adapter |
| `LEGACY_JEA` | Historical tables from esp-v2 that never got fully classified — reviewed case-by-case | (to be enumerated during P0 gap audit) | Case-by-case |

### Per-package data responsibilities

Every `PACKAGE_OWNED` table lives in a package. The owning package MUST provide:

1. **Migrations** — schema creation, additive changes, backward-compatible removals
2. **Rollback** — every migration has a reversible `down()` OR is documented as forward-only with explicit approval
3. **Upgrade path** — from version N to N+1, data transformation is tested + rehearsed
4. **Data retention policy** — per-record + per-table retention aligned with regulatory requirements
5. **Uninstall policy** — one of: `preserve` (data kept, foreign keys nulled), `archive` (moved to cold storage), `purge` (only with explicit operator confirmation + backup)
6. **Schema compatibility declaration** — declares which Core version + which dependent package versions the schema is valid against

### Constitutional consequence

- Cross-package direct table reads are prohibited (spec §4.2, C-05).
- Access to data owned by another package goes through the owner's contract (queries, events, commands).
- The `manual_references` table (currently in `jea-services`) becomes `CORE_OWNED` at P2 when the runtime is extracted.

---

## 6. Effort & Timeline

### Development effort — role coverage matrix

Effort is not measured in "engineers" alone but in role coverage. A schedule that assumes headcount without the right roles slips.

| Role | Coverage needed | Present in team? |
|------|-----------------|-------------------|
| Solution / platform architect | Continuous through all 10 phases | To confirm |
| Backend engineer (PHP/Laravel) | P1-P8 heavy, P9-P10 medium | Present (esp-v2 team) |
| Frontend runtime engineer | P5 heavy, P7 medium | To confirm — different skill from JEA UI dev |
| DevOps / infra engineer | P1 (CI), P4 (staging), P8 (signing), P9 (observability), P10 (production migration) | To confirm |
| Security / supply-chain engineer | P8 heavy, security review across all | Likely external / consultant |
| Migration engineer | P3, P6, P10 (rehearsals + production) | Can double-role with backend |
| QA / test automation | Continuous — every phase has test gates | Likely shared with esp-v2 team |
| Documentation / SDK author | P7 heavy + phase-end reports | Likely shared |

**Team implication:** headcount is not the constraint. **Role coverage** is. Any phase that lacks its required role must delay, not skip the gate. See §7 acceptance conditions.

### Phase-by-phase schedule (esp-core, revised order)

| Phase | What | Duration | Cumulative | Environment introduced |
|-------|------|----------|------------|-------------------------|
| P0 | Constitution + origin baseline + gap audit + ownership map | 2 weeks | 2 | dev only |
| P1 | Package contract + manifest + loader + version rules | 4 weeks | 6 | **CI pipeline** |
| P2 | Core runtime extraction + clean-core boot verification | 6 weeks | 12 | integration env |
| P3 | First real JEA package + **first JEA-on-Core rehearsal** | 3 weeks | 15 | disposable JEA-on-Core env |
| P4 | Organization activation + tenant boundaries | 5 weeks | 20 | **persistent staging** |
| P5 | Frontend extension + runtime registry | 4 weeks | 24 | staging |
| P6 | Remaining JEA modules converted to packages | 6 weeks | 30 | migration rehearsal env |
| P7 | SDK + CLI + templates + developer documentation | 4 weeks | 34 | staging |
| P8 | Admission + signing + supply-chain + security controls | 5 weeks | 39 | staging + security scan env |
| P9 | Operations console + observability + upgrade management | 4 weeks | 43 | staging + production-like observability |
| P10 | Full migration rehearsal + cutover + rollback + archival | 3 weeks | 46 | production migration |

**Total: 46 weeks (~11 months).** With appropriate role coverage, this can compress with parallelization (see §10). Without it, it slips.

---

## 7. Governance — One-Way Source-of-Truth per Fix Category

Bidirectional cherry-picking creates drift chaos. The following routing is mandatory and enforced by process.

| Fix category | Where the fix is authored first | Backport policy |
|--------------|-----------------------------------|-----------------|
| **JEA-specific business bug / feature** (only meaningful to JEA production) | `esp-v2` only | Never backported to `esp-core`. The equivalent logic will exist inside the JEA package(s) in the converged model. |
| **Common platform bug / architectural improvement** | `esp-core` is source of truth | Cut a versioned release in esp-core. Temporarily backport to `esp-v2` if urgent, with a ticket linking the backport commit to the esp-core release. Backport is retired at convergence. |
| **Urgent production security fix (JEA cannot wait)** | `esp-v2` immediately (production first) | Mandatory follow-up: file esp-core issue same day; equivalent patch committed to esp-core within one week. |

### Prohibited practices

- No automated / scheduled cherry-picking between repos.
- No commits that touch both repos in the same PR.
- No fix in one repo that lacks a labeled category and, where applicable, a link to its counterpart issue.

### Weekly reconciliation

A weekly 30-minute reconciliation reviews:
- Every commit in esp-v2 tagged `security` or `platform-relevant` in the past week.
- Every open `esp-v2-backport-pending` issue in esp-core.
- Divergence report (auto-generated `git diff` summary between the origin tag and the current heads of both repos).

---

## 8. Environment Ladder (starts at P1, not P10)

An earlier draft deferred all environments to P10. That is corrected here.

| Phase | Environment introduced | Purpose |
|-------|------------------------|---------|
| P0 | dev workstations only | Design + baseline capture |
| P1 | **CI pipeline** on esp-core | Every PR runs manifest validator + boundary tests + unit tests |
| P2 | Integration environment | Boots Core with zero business packages — verifies C-02 continuously |
| P3 | **Disposable JEA-on-Core** environment | First convergence rehearsal — proves the model with one real JEA package |
| P4 | **Persistent staging** | Multi-org activation testing |
| P5 | Staging with runtime registry | Frontend registry behavior validation |
| P6 | Migration rehearsal environment | Cloned production data, safe cutover practice |
| P7 | Staging (SDK usage validation) | Package template + CLI dogfooding |
| P8 | Security-scan environment | Signing pipeline + SAST + dependency scan |
| P9 | Production-like observability | Metrics + alerts calibrated |
| P10 | Production migration window | Cutover with rollback plan |

**Constraint:** no phase completes without its environment being operational + tested.

---

## 9. Risks & Mitigations (revised)

| Risk | Impact | Mitigation |
|------|--------|------------|
| Two codebases drift too far apart | HIGH | Weekly reconciliation (§7) + divergence report + one-way source-of-truth per category |
| JEA needs an urgent Core-side fix during transformation | MEDIUM | Backport is allowed but tracked (§7); backport debt visible in dashboard |
| Platform team gets pulled into JEA firefighting | HIGH | Clear team separation with management-enforced escalation path |
| **Convergence rehearsal at P3 fails or reveals unexpected blockers** | **HIGH** | Explicit exit criteria at P3 (§10). If P3 rehearsal fails, program pauses; no further phases advance until root cause is understood + plan revised. |
| Convergence rehearsal at P6 surprises with incompatibilities | MEDIUM | P3 catches most; P6 catches the rest. Rehearsal environment mirrors production. |
| Boss / stakeholders lose visibility on esp-core progress | MEDIUM | Monthly demo + per-phase handoff document + divergence report |
| Investment doesn't pay off (no second customer materializes) | LOW-MEDIUM | Even without external adoption, JEA benefits from a substantially cleaner codebase and reduced maintenance cost |
| Data-ownership classification wrong for some tables | MEDIUM | P0 gap audit is the checkpoint; every table classified before P1 starts |
| Role coverage gap (e.g., no security engineer for P8) | HIGH | Phase gate: cannot enter P8 without security-role coverage confirmed |

---

## 10. Decision Requested — Conditional Approval

### Recommended decision

```
DECISION = APPROVE OPTION B CONDITIONALLY
```

### The 10 mandatory conditions (must be satisfied before P0 completion)

1. **Pin the origin baseline** — create tag `esp-core-origin-v0.1` on esp-v2, capture schema/API/test/dependency baselines to `docs/origin/`.
2. **Ratify the package constitution** — publish and formally approve constitutional rules C-01..C-12 (spec §4.2) as a project ADR.
3. **Adopt the data ownership model** — every table classified into CORE_OWNED / ORG_OWNED / PACKAGE_OWNED / INTEGRATION_OWNED / LEGACY_JEA (see §5).
4. **Adopt one-way source-of-truth backport policy** — no bidirectional cherry-picking; category-based routing as in §7.
5. **Prohibit automated cross-repo commits** — enforced by process + branch protection rules.
6. **Execute first convergence rehearsal at P3** — proves ESP Core + one real JEA package boots and serves traffic. No further phases proceed until this succeeds.
7. **Stand up CI pipeline by P1** and persistent staging by P4 — no phase completes without its environment being live.
8. **Define exit criteria for every phase** — measurable, testable, gated. Documented in the phase-specific handoff. Advancement requires phase-owner sign-off.
9. **Define rollback plan for the P10 production migration** — before P10 starts, rollback is rehearsed and time-boxed.
10. **Assign one accountable role owner per track** — architecture, backend, frontend runtime, DevOps, security, migration, QA, docs. Coverage gap = phase pause.

### Other decisions the boss should confirm

- [ ] Approve scope option: A (in-place, not recommended) / B (fork, recommended) / C (wait)
- [ ] Approve team assignment — who works on esp-v2 (JEA) vs. esp-core (platform)?
- [ ] Confirm timeline realism — 46 weeks acceptable, or need reduced-scope options?
- [ ] Approve budget — additional roles + infrastructure for esp-core environments
- [ ] Confirm strategic intent — reusable/marketable platform, or internal-only?
- [ ] Grant permission to create GitHub repository — `hhiyassat/esp-core` or preferred name?

---

## 11. Immediate Next Steps (If Approved)

### Week 1 — Fork Execution (1-2 days)

- Create tag `esp-core-origin-v0.1` on `esp-v2/main`
- Capture origin baseline files (`docs/origin/*` on esp-v2)
- Clone `esp-v2` → `esp-core` preserving git history
- Rename remotes; push to new GitHub repo
- Remove JEA-brand identity from esp-core (README, composer.json name, package.json name)
- Enforce branch protection + prohibited-workflow rules (§7)

### Weeks 2-3 — P0 (Preparation)

- Publish ADR: adopt spec v0.1 + constitutional rules
- Publish gap audit (every MUST/SHOULD in spec → current state → owner)
- Publish per-table data-ownership classification
- Confirm role coverage for P1

### Week 4+ — P1 begins (per implementation plan)

Manifest schema authoring, loader design, validator implementation.

---

## 12. References

- **Full implementation plan:** `docs/architecture/08-pluggable-platform-implementation-plan.md`
- **Original spec:** `Pluggable_Institutional_Service_Platform/doc/ESP_Core_Pluggable_Service_Platform_Implementation_Specification_v0.1.docx`
- **Previous refactor context:** `docs/architecture/01-refactoring-plan.md` + `session_handoff_2026-07-22.md`
- **Current architecture:** `docs/architecture/04-modules.md` + `05-plugins-and-integrations.md`

---

## 13. Summary for Quick Reading

| Question | Answer |
|----------|--------|
| **What are we doing?** | Cloning ESP v2 to a new repo to build a business-neutral platform without exposing JEA production to platform-experiment runtime risk |
| **Why fork?** | JEA has 90k members in production. Runtime-risk isolation is required. |
| **How does JEA get the benefits later?** | JEA production runs as `ESP Core + JEA Organization Pack + JEA Service Packages + Integrations + Configuration` — convergence via packages, not git merge |
| **How long?** | 46 weeks for full platform program (parallel to JEA delivery) |
| **How much?** | Standard engineer time (multi-role coverage) + ~USD 150-250/month infrastructure |
| **What's the payoff?** | ESP becomes reusable across institutions (JMA, other NGOs, ministries). Not JEA-locked. |
| **What if we don't?** | Every new JEA feature deepens the coupling. Future transformation cost grows. Locked to one customer. |
| **What's the risk of the fork approach?** | Divergence between the two repos. Mitigated by §7 (one-way source of truth per fix category) + weekly reconciliation. |
| **When do we prove the convergence model?** | End of P3 (Month 4) — first JEA-on-Core rehearsal. This is a hard gate: if it fails, we pause and reassess. |
| **What happens to esp-v2 at the end?** | Archived (or read-only). JEA production runs on esp-core as a package installation. |
| **What decision is needed today?** | Conditional approval per §10 — 10 mandatory conditions + 6 stakeholder confirmations |

---

*This handoff, version 2, has been amended after engineering review. It converts a strategic direction (yes, we should fork) into an actionable engineering contract with governance guarantees, environment ladder, data ownership discipline, and per-phase exit criteria. Detailed technical plans remain in the referenced documents.*
