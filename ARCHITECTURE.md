# ESP v2 — Architecture Document

**Document ID:** ESP-ARCH-001
**Version:** 0.1 (STUB — pending authorship)
**Status:** PLACEHOLDER — cited by `BUILD_CONTRACT.md` §2 row 3
**Methodology:** Eqratech IEEE-Aligned Decision Assurance Methodology v1.1

---

## Purpose

This file exists because `BUILD_CONTRACT.md` §2 (Pre-Code Governance Artifacts) requires it as artifact #3, "Architecture Document — 7 viewpoints per §7.1". The reference existed for months without the file, which the 2026-07-30 architecture review flagged as a documentation-vs-code drift. Creating this stub means the citation resolves; authoring the full 7-viewpoint content is a follow-up task tracked as `P4-2` in `docs/remediation/architecture-review-remediation-ledger.md`.

## Current authoritative sources

Until this file is fleshed out, the architectural picture lives in:

- **`docs/architecture/`** — 15+ documents covering the modular-monolith layout, refactor plans, service catalog, cross-cutting submission pipeline, JEA production architecture, and the SRV-001 workstream plan.
- **`docs/adr/`** — accepted ADRs (`001-adopt-esp-core-spec-and-fork-approach.md`).
- **`docs/handoffs/`** — most recent full architectural narrative: `2026-07-30_esp-v2-platform-and-services-handoff.md`.
- **`docs/remediation/architecture-review-remediation-ledger.md`** — every architecture-review finding + its remediation status.

## 7 viewpoints (to be authored)

1. **Business** — JEA services, applicants, engineers, offices, workflow lifecycle.
2. **Functional** — modules (JeaServices / JeaProjects / JeaDues / JeaDiscipline), platform, plugins, integrations.
3. **Information** — data model, tenancy, JSON payloads, denormalized cadastral columns.
4. **Concurrency** — WorkflowEngine transactions, atomic counters (Application + Certificate), cross-cutting pipeline TOCTOU fix.
5. **Development** — module boundaries, BoundariesTest allowlist, PlatformMigrationsOnlyTest.
6. **Deployment** — Dockerfile, docker-compose.yml, deployment/env.production.template, supervisor config, PostgreSQL + Redis + object storage topology.
7. **Operational** — health/readiness (/up, /api/ready), CorrelationId + LogApiAccess, SecurityEvents, ProductionSafety validator, scheduled tasks.

Each viewpoint should be a distinct section (or a distinct file linked from here) — the current handoff already covers most of the ground and can be split apart into the seven sections when time is available.
