# ESP Core Pluggable Platform — Implementation Plan

**Source spec:** `Pluggable_Institutional_Service_Platform/doc/ESP_Core_Pluggable_Service_Platform_Implementation_Specification_v0.1.docx`  
**Written:** 2026-07-25  
**Target:** transform ESP v2 from JEA-specific application into a business-neutral installable platform (Moodle-style)

---

## 1. Where we stand vs the target

The 16-workstream refactor (2026-07-22 → 07-25) delivered ~35% of the foundation the spec requires. The rest is a 6-12 month journey.

| Spec requirement | Current state | Gap |
|------------------|---------------|-----|
| Platform / modules / plugins / integrations physical separation | ✅ Done | Vocabulary aligned |
| Config registries (per subsystem) | ✅ Done | No `package.json` manifest yet |
| ServiceProvider pattern per extension | ✅ Done | No lifecycle beyond boot |
| Workflow Engine + rule_id + AuditLog | ✅ Done | Currently in `jea-services` — must generalize to Core |
| Manual References | ✅ Done | Currently in `jea-services` — must generalize to Core |
| Boundary tests (backend + frontend) | ✅ Done | Need to extend to package conformance |
| Multi-org (Organization model) | ✅ Partial | No per-org activation yet — global only |
| Zero-package validity (C-02) | ❌ | Core still assumes JEA modules present |
| Package manifest + JSON schema | ❌ | Need spec + validator |
| Lifecycle manager (install/enable/upgrade/uninstall) | ❌ | Need artisan commands + state machine |
| Per-org activation | ❌ | Need `installations` + `organization_packages` tables |
| Frontend runtime registry | ❌ | `routes.tsx` + `navItems.tsx` are godlists |
| SDK / package scaffold | ❌ | Need CLI + templates |
| Admission controls (signing, review) | ❌ | Need SAST/dependency scan + signature |
| Package administration console | ❌ | Need UI for status/health/lifecycle |

---

## 2. Guiding principles (from spec §3.2)

> **Preserve working behavior first, introduce contracts second, extract packages third, and remove temporary compatibility paths only after the equivalent package path is proven.**

This means:
- **No big bang.** JEA production keeps running throughout.
- **Every phase is deployable.** Feature flags gate new paths.
- **Old paths retire ONLY after new paths are proven** — no cliff.

---

## 3. Ten-phase roadmap (30 workstreams total)

### PHASE 0 — Preparation (2 weeks)

| # | Workstream | Deliverable |
|---|-----------|-------------|
| P0-1 | ADR: adopt spec v0.1 | `docs/adr/001-pluggable-platform.md` — decision record + tradeoffs |
| P0-2 | Full gap-audit vs spec §7-§20 | Table: every MUST/SHOULD in spec → current state → owner |
| P0-3 | Package taxonomy naming | Confirm reverse-domain scheme (`eqratech.jea.membership-renewal`) |

**Exit criteria:** Written approval to proceed; gap audit reviewed.

### PHASE 1 — Foundation: Package Manifest + Loader (4 weeks)

| # | Workstream | Deliverable |
|---|-----------|-------------|
| P1-1 | Design manifest JSON Schema | `packages/schema/package-manifest-v1.json` with all fields from spec §7.1 |
| P1-2 | Build ManifestValidator | PHPUnit + fixtures; reject malformed manifests fast |
| P1-3 | Rewrite ModulesServiceProvider → PackageLoader | Reads manifest, boots via `entrypoints.backend`, replaces current config-driven boot |
| P1-4 | Compatibility check (Core version ↔ package requirement) | Semver-based dependency resolver |

**Exit criteria:** Loader can boot a hand-written `example-package/package.json`. Existing modules still work via a compat shim.

### PHASE 2 — Core Runtimes: Extract Generic Services (6 weeks)

| # | Workstream | Deliverable |
|---|-----------|-------------|
| P2-1 | Extract Workflow Runtime | Move `Modules\JeaServices\Engine\WorkflowEngine` → `App\Runtimes\Workflow\` with generic API |
| P2-2 | Extract Forms & Schema Runtime | Move `DynamicForm` + `SchemaValidator` → `App\Runtimes\Forms\` |
| P2-3 | Extract Manual References Runtime | Move from jea-services → `App\Runtimes\ManualReferences\` |
| P2-4 | Extract Notification Runtime | Generic channel abstraction; JEA notifications become package-provided |
| P2-5 | Extract Files & Documents Runtime | Storage-driver-agnostic upload/download/retention |
| P2-6 | Extract Payment Abstractions | `PaymentGateway` interface + intent/callback model (already scaffolded in W11) |

**Exit criteria:** Each runtime tested standalone with zero JEA imports. JEA modules use the runtimes via new interfaces.

### PHASE 3 — Pilot Package: Convert `jea-dues` (3 weeks)

| # | Workstream | Deliverable |
|---|-----------|-------------|
| P3-1 | Convert `jea-dues` to full package layout | `packages/services/eqratech-jea-dues/` with `package.json`, backend/, frontend/ folders |
| P3-2 | Migrate `jea-dues` to PackageLoader | Boot via manifest, prove old + new coexist |
| P3-3 | Package-level tests | Isolated test suite; can install/uninstall cleanly |

**Exit criteria:** `jea-dues` runs entirely as a package. All existing tests still green. Documentation updated.

### PHASE 4 — Per-Organization Activation (5 weeks)

| # | Workstream | Deliverable |
|---|-----------|-------------|
| P4-1 | Data model | Tables: `installed_packages`, `organization_packages` (activation state per org) |
| P4-2 | Activation guard middleware | Blocks routes/queues/events for orgs where package isn't enabled |
| P4-3 | Tenant context enforcement | Every DB query on tenant-owned tables scoped via global scope; audit bypasses |
| P4-4 | Config precedence engine (spec §14.4) | Core default → Package default → Org Pack → Org override → Env |

**Exit criteria:** `jea-dues` can be enabled for Org A and disabled for Org B in the same deployment.

### PHASE 5 — Frontend Runtime Registry (4 weeks)

| # | Workstream | Deliverable |
|---|-----------|-------------|
| P5-1 | Design RouteRegistry + NavRegistry contracts | Each package's `frontend/index.ts` calls `registerRoute()` / `registerNav()` |
| P5-2 | Rewrite `routes.tsx` + `navItems.tsx` as composers | Read from registries; delete hardcoded lists |
| P5-3 | Package build integration | Vite glob-import from `packages/*/frontend/` at compile time |
| P5-4 | Feature-flag toggle | Old god-list path + new registry path coexist; flip when proven |

**Exit criteria:** Adding a new package requires ZERO edits to `routes.tsx` or `navItems.tsx`.

### PHASE 6 — Convert Remaining Modules (6 weeks)

| # | Workstream | Deliverable |
|---|-----------|-------------|
| P6-1 | Convert `jea-projects` to package | Same pattern as P3 |
| P6-2 | Convert `jea-discipline` to package | Same pattern |
| P6-3 | Convert `jea-services` to package | The big one — most Core-coupling here |
| P6-4 | Convert plugins (`ai-schema`, `captcha`) to packages | Different type = capability |
| P6-5 | Convert integrations (`gsb`, `nashmi`) to packages | Different type = integration adapter |
| P6-6 | Retire the old `modules/`, `plugins/`, `integrations/` directories | Only after all replaced |

**Exit criteria:** ESP Core boots with **zero business packages**. C-02 (constitutional zero-package validity) achieved.

### PHASE 7 — Developer SDK (4 weeks)

| # | Workstream | Deliverable |
|---|-----------|-------------|
| P7-1 | CLI generators | `php artisan esp:make-package service my-service`, etc. |
| P7-2 | Package scaffold templates | Reference implementations per package type |
| P7-3 | Manifest validator as CLI | `esp:package:validate`, `esp:package:test`, `esp:package:build` |
| P7-4 | Developer documentation | Golden path + extension point catalogue + examples |

**Exit criteria:** A new developer with the SDK can build + install a "hello world" package end-to-end in under 1 hour.

### PHASE 8 — Admission Controls + Security (5 weeks)

| # | Workstream | Deliverable |
|---|-----------|-------------|
| P8-1 | Artifact signing | Deterministic build + SBOM + digital signature |
| P8-2 | Dependency & vulnerability scanning | SAST, npm/composer audit integration |
| P8-3 | Permission review UI | Admin sees what a package requests before approving |
| P8-4 | External-endpoint declaration + allowlist | Package declares hosts; runtime enforces |
| P8-5 | Package review workflow | Governance process for admission to production repo |

**Exit criteria:** Cannot install a package without passing admission gates.

### PHASE 9 — Operations & Observability (4 weeks)

| # | Workstream | Deliverable |
|---|-----------|-------------|
| P9-1 | Package Administration Console | Views: installed, compatibility, org activation, health, lifecycle, security, ops |
| P9-2 | Logging correlation | Every log has `package_id`, `package_version`, `organization_id`, `correlation_id`, `rule_id` |
| P9-3 | Metrics + dashboards per package | Prometheus/Grafana specific to package behavior |
| P9-4 | Alerting rules | Per package + per tenant + integration health |

**Exit criteria:** Operator can diagnose any package issue from the admin console + dashboards.

### PHASE 10 — Reference Distribution (3 weeks)

| # | Workstream | Deliverable |
|---|-----------|-------------|
| P10-1 | Publish Core as installable distribution | Docker image + composer package |
| P10-2 | Publish JEA package set as reference | `eqratech.jea.*` bundle |
| P10-3 | Build second-org proof | Deploy Core + minimal alternate package set for a hypothetical second tenant (e.g., JMA vessel registration mock) |

**Exit criteria:** A new institution can adopt ESP Core + a curated package set with **zero code changes to Core**.

---

## 4. Total effort estimate

| Phase | Weeks | Cumulative |
|-------|-------|------------|
| P0 Preparation | 2 | 2 |
| P1 Foundation | 4 | 6 |
| P2 Core Runtimes | 6 | 12 |
| P3 Pilot Package | 3 | 15 |
| P4 Per-Org Activation | 5 | 20 |
| P5 Frontend Registry | 4 | 24 |
| P6 Convert Remaining Modules | 6 | 30 |
| P7 SDK | 4 | 34 |
| P8 Admission + Security | 5 | 39 |
| P9 Operations | 4 | 43 |
| P10 Reference Distribution | 3 | 46 |

**Total: ~46 weeks (11 months)** for a dedicated engineer. With a team of 2-3, this can compress to 5-7 months.

---

## 5. What can happen in parallel

Sequential dependencies (must be in order):
- **P0 → P1 → P2** (foundation before runtimes)
- **P2 → P3** (runtimes exist before converting first package)
- **P3 → P4** (pilot proves pattern before scaling)
- **P6 must finish before P10**

Parallel opportunities:
- **P5 (frontend registry)** can start after P1 completes — independent of P2/P3
- **P7 (SDK)** can start during P3 — pilot informs SDK design
- **P8 (security)** can start during P6 — needs stable packages to test against
- **P9 (observability)** can start during P4 — activation events feed dashboards

---

## 6. Risks and mitigations

| Risk | Mitigation |
|------|-----------|
| Long timeline erodes team focus | Ship a working milestone every 4 weeks minimum. Every phase leaves system MORE deployable, never less. |
| Core neutralization breaks JEA production | Feature flags on every change. Old + new paths coexist until proven. Automated regression tests as gate. |
| Package format v1 needs breaking changes later | Version the manifest schema itself. Migration tools required for spec version bumps. |
| Multi-tenancy performance | Benchmark early (P4). Denormalize tenant_id into indexes. Consider database schema-per-tenant if needed. |
| SDK adoption fails (developers still edit Core) | Enforce via architecture tests (C-03). Every PR touching Core requires ADR justification. |
| Signing infrastructure complexity | Start with checksum-only in P7; add PGP/x509 signing in P8 when review process is defined. |
| Zero-package validity harder than expected | Track constitutional violations as backlog items throughout P2-P6. Never merge if C-02 regresses. |

---

## 7. Success metrics per phase

| Phase | Metric | Target |
|-------|--------|--------|
| P1 | Manifest validator false-positive rate | < 1% |
| P2 | Core runtimes with JEA imports | 0 |
| P3 | `jea-dues` tests still green after conversion | 100% |
| P4 | Time to enable/disable a package per org | < 2 seconds |
| P5 | Files edited when adding a new package | 0 (in Core), all changes contained in package dir |
| P6 | ESP Core boots with 0 business packages | Yes (C-02) |
| P7 | Time for new dev to ship "hello world" package | < 1 hour |
| P8 | Packages installed without passing admission | 0 |
| P9 | Operator can identify failing package | < 30 seconds via console |
| P10 | Second-org distribution boots successfully | Yes |

---

## 8. Recommended immediate actions (this month)

1. **Approve ADR** (P0-1) — decision to adopt spec is the gate for everything else
2. **Complete gap audit** (P0-2) — clarity on distance to target
3. **Start P1-1 (manifest schema)** — the foundational contract everything else depends on
4. **Reserve capacity** — decide whether this is a full-team focus, side-project, or paused work

---

## 9. Alternatives if the full spec is too ambitious

If 46 weeks is not feasible, three reduced-scope options:

### Option A — Contracts-only (16 weeks)
Do P0-P4 only. Get formal manifest + lifecycle + per-org activation. Skip SDK, admission, ops console. Existing modules stay as-is with a compat shim.

**Outcome:** JEA works multi-org. Third-party developers still edit Core. Not truly plug-and-play.

### Option B — JEA-locked pluggability (24 weeks)
Do P0-P6. Full package model but only for the internal team. Skip SDK/admission/ops (P7-P9). Skip second-org distribution (P10).

**Outcome:** ESP Core neutral. JEA modules are packages. Ready for internal reuse. Third-party ecosystem deferred.

### Option C — Full spec (46 weeks — recommended)
All 10 phases. Ready for external distribution + third-party developers.

**Outcome:** Genuine Moodle-style platform. Business-neutral. Marketable.

---

## 10. Decision needed

Before P0-1 can start, the following need approval:

- [ ] Adopt spec v0.1 as target (yes / needs revision)
- [ ] Choose scope option: A (contracts) / B (internal) / C (full)
- [ ] Assign owner + team size
- [ ] Reserve budget for infrastructure (P8 signing, P9 observability)
- [ ] Confirm production JEA delivery continues in parallel (yes — spec §3.2 mandates it)

---

*This plan converts the specification document into an executable sequence of workstreams that build on the 16-workstream refactor already merged. Every phase is independently valuable, every phase leaves the system deployable, and the constitutional rules (C-01..C-12) become achievable one phase at a time.*
