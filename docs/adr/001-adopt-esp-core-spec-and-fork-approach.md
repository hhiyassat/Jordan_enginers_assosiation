# ADR-001: Adopt ESP Core Pluggable Platform Spec v0.1 + Fork Approach

- **Status:** Accepted
- **Ratified:** 2026-07-27
- **Deciders:** Hussein Hiyassat (sole signatory under R2 emergency single-human amendment, tag `p0-staffing-amendment-emergency-r2-v1`, sunset 2026-08-31)
- **Executor (drafting):** Claude Code
- **Reviewer:** ChatGPT (per R2 amendment role definition)
- **Related:**
  - `docs/architecture/08-pluggable-platform-implementation-plan.md` — the 10-phase, 30-workstream implementation plan this ADR unblocks
  - `docs/handoffs/2026-07-25_pluggable-platform-fork-approach.md` — v2 management handoff (Option A/B/C analysis; recommends B)
  - `docs/origin/README.md`, `docs/origin/api-baseline.json`, `docs/origin/schema-baseline.sql` — origin-baseline artefacts already pinned
  - Git tag `esp-core-origin-v0.1` — origin marker on `esp-v2/main`

## Context

The 16-workstream refactor completed 2026-07-22 → 07-25 delivered ~35% of the foundation required by the ESP Core Pluggable Platform specification. Two questions were then outstanding:

1. **Do we adopt the ESP Core Pluggable Platform spec v0.1 as the strategic target for the codebase?** — a 46-week program that transforms the current JEA-specific application into a business-neutral, installable, multi-organization service platform (Moodle-style).
2. **How do we execute it without endangering JEA production (~90k members)?**

The management handoff dated 2026-07-25 evaluated three options:
- **A.** In-place transformation of `esp-v2`
- **B.** Fork to new `esp-core` repo; converge through packages
- **C.** Wait / defer

Origin baseline artefacts (git tag + `docs/origin/*`) were captured proactively, on the assumption Option B would be selected. This ADR now formalises that assumption.

## Decision

### D1 — Adopt spec v0.1

The `ESP_Core_Pluggable_Service_Platform_Implementation_Specification_v0.1.docx` (hereafter "spec v0.1") — located at `~/Pluggable_Institutional_Service_Platform/doc/` and treated as the external source of truth — is the strategic target for the codebase's platform architecture.

The 10-phase, 30-workstream implementation plan at `docs/architecture/08-pluggable-platform-implementation-plan.md` is the executable interpretation of spec v0.1 and is adopted alongside it. Amendments to that plan require a new ADR.

### D2 — Execute via Option B (fork + converge through packages)

A new repository `esp-core` will be cloned from `esp-v2` at the origin tag `esp-core-origin-v0.1`. The two repositories then evolve independently for the duration of the platform program (46 weeks nominal). Convergence at the end of the program happens through **package installation**, not through a git merge:

```
JEA Production Distribution
  = ESP Core
  + JEA Organization Pack
  + JEA Service Packages
  + JEA Integrations
  + JEA Configuration
```

Options A and C are rejected — A because it exposes JEA production to runtime experiments; C because it forecloses on the platform-strategy optionality without a corresponding cost saving.

### D3 — Governance authority for this ADR

This ADR is ratified under the R2 emergency single-human amendment (tag `p0-staffing-amendment-emergency-r2-v1`, ratified 2026-07-26, sunset 2026-08-31). Under R2:
- Hussein Hiyassat is the sole signatory.
- Claude Code is authorised as executor per-workstream.
- ChatGPT is the review role.
- After the R2 sunset (2026-08-31), any amendment to this ADR reverts to the pre-R2 multi-signatory process. The decisions in D1 and D2 do not lapse at sunset — only the emergency authority to make further P0 amendments unilaterally does.

## Consequences

### Immediate (this ADR unblocks)

- Fork execution proceeds per §11 of the handoff: clone `esp-v2` → `esp-core` preserving history, rename remotes, strip JEA-brand identity from Core, enforce branch-protection rules.
- P0-2 (gap audit vs spec §7-§20) can start.
- P0-3 (package taxonomy naming) is out-of-scope for this ADR and separately marked DENIED per the current governance log.

### Follow-up ADRs required before P1 starts

This ADR is deliberately focused. The following decisions are recorded separately so each has its own reviewable audit trail:

- **ADR-002** — Ratify constitutional rules C-01..C-12 (spec §4.2). This is condition 2 of §10 of the handoff.
- **ADR-003** — Adopt per-table data-ownership classification (`CORE_OWNED` / `ORG_OWNED` / `PACKAGE_OWNED` / `INTEGRATION_OWNED` / `LEGACY_JEA`). This is condition 3 of §10 of the handoff.
- **ADR-004** — Adopt one-way source-of-truth backport policy (§7 of the handoff). This is condition 4.

The 10 mandatory conditions in §10 of the handoff must all be satisfied before P0 can be declared complete. This ADR satisfies conditions 1 (origin baseline pinning — already executed via tag + `docs/origin/*`) and the D1/D2 decision layer.

### Ongoing obligations

- Every commit in `esp-v2` tagged `security` or `platform-relevant` is reviewed in the weekly reconciliation defined in ADR-004 (when adopted).
- No commit may touch both `esp-v2` and `esp-core` in the same PR.
- No automated cross-repo cherry-picking.
- Divergence between the two repos is monitored via the divergence report (auto-generated `git diff` summary between the origin tag and current heads of both repos).

### Risks accepted

Enumerated in §9 of the handoff. The two highest-impact:
- **Convergence rehearsal at P3 fails or reveals blockers** (HIGH) — mitigation: explicit exit criteria at P3; program pauses if P3 rehearsal fails.
- **Platform team gets pulled into JEA firefighting** (HIGH) — mitigation: clear team separation with management-enforced escalation.

## Alternatives Considered

### Option A — In-place transformation of `esp-v2`

Rejected. Runtime risk to JEA production is unacceptable. Every architectural experiment during a 46-week program would risk 90k members' service continuity. The physical separation completed in the 16-workstream refactor makes the fork cheap, so the isolation benefit is available at low cost.

### Option C — Wait / defer

Rejected. LOW technical risk, HIGH strategic risk:
- Every additional JEA feature deepens JEA-specific coupling in the shared codebase.
- The cost of a future transformation grows monotonically.
- Forecloses on the platform-strategy optionality (reusable / marketable platform, second-customer viability).

### Option B'  — Fork but plan to git-merge back at end of program

Considered and rejected. Merging a 46-week-diverged codebase would violate the constitutional "clean core" rule (C-02) at merge time and reintroduce all the JEA-specific coupling that the fork was meant to remove. Convergence via package installation is architecturally correct AND operationally safer.

## References

- Spec v0.1: `~/Pluggable_Institutional_Service_Platform/doc/ESP_Core_Pluggable_Service_Platform_Implementation_Specification_v0.1.docx` (external artefact — not in this repo)
- Implementation plan: `docs/architecture/08-pluggable-platform-implementation-plan.md`
- Fork handoff (management review v2): `docs/handoffs/2026-07-25_pluggable-platform-fork-approach.md`
- Origin baseline: git tag `esp-core-origin-v0.1`; artefacts under `docs/origin/`
- Governance basis (R2 amendment): tag `p0-staffing-amendment-emergency-r2-v1`
