# ESP Core — P0-2 Gap Audit (Spec v0.1 §7 – §20)

- **Status:** DRAFT (spec-text-limited)
- **Baseline:** origin tag `esp-core-origin-v0.1` on `esp-v2/main`
- **Started:** 2026-07-28
- **Deliverable path (per plan):** every MUST/SHOULD in spec §7-§20 → current state → owner
- **Related:** [ADR-001](../adr/001-adopt-esp-core-spec-and-fork-approach.md), [implementation plan](08-pluggable-platform-implementation-plan.md), [fork handoff](../handoffs/2026-07-25_pluggable-platform-fork-approach.md)

## How this document is filled in

The spec (`ESP_Core_Pluggable_Service_Platform_Implementation_Specification_v0.1.docx`) is an **external artefact** — not in this repo. Rows in the tables below fall into one of three states:

- ✅ **VERIFIED** — requirement text confirmed, gap analysis stable
- 🟡 **INFERRED** — requirement stated from secondary sources (plan doc, handoff, prior ADR conversations); needs verbatim confirmation against the spec
- 🔴 **[SPEC-VERBATIM-NEEDED]** — I know the section exists but cannot cite the specific MUST/SHOULD text; paste the spec section here and it gets filled in

Owner mapping uses the workstream identifiers from [`08-pluggable-platform-implementation-plan.md`](08-pluggable-platform-implementation-plan.md) (P0-1 through P10-3). Status is one of `OPEN / IN-PROGRESS / DONE`.

## Cross-cutting principle (spec §3.2, VERIFIED)

> *"Preserve working behavior first, introduce contracts second, extract packages third, and remove temporary compatibility paths only after the equivalent package path is proven."*

Every row below is graded against this rule: no gap-closure counts as "DONE" until the corresponding old path is proven safe to retire (not merely retired).

---

## §4.2 — Constitutional Rules C-01 through C-12 (INFERRED)

Only **C-02** is spelled out in-repo (plan doc line 22): *"Zero-package validity — Core still assumes JEA modules present."*

The other 11 rules are referenced by number in the plan doc + handoff but never quoted. They must be enumerated verbatim before this section can be closed.

| # | Rule text | Current | Gap | Owner | Status |
|---|-----------|---------|-----|-------|--------|
| C-01 | 🔴 [SPEC-VERBATIM-NEEDED] | — | — | ADR-002 | OPEN |
| C-02 | *"Core boots with zero business packages installed."* (INFERRED from plan doc line 22) | Core still assumes JEA modules present at boot | Extract JEA modules → packages; add zero-package boot test | P2-* + P6-* | OPEN |
| C-03 | 🔴 [SPEC-VERBATIM-NEEDED] | — | — | ADR-002 | OPEN |
| C-04 | 🔴 [SPEC-VERBATIM-NEEDED] | — | — | ADR-002 | OPEN |
| C-05 | Cross-package direct table reads are prohibited (INFERRED — handoff §5) | Not enforced; JEA modules query each other's tables freely | Boundary tests + package data-access API | P4-* + P6-* | OPEN |
| C-06 | 🔴 [SPEC-VERBATIM-NEEDED] | — | — | ADR-002 | OPEN |
| C-07 | 🔴 [SPEC-VERBATIM-NEEDED] | — | — | ADR-002 | OPEN |
| C-08 | 🔴 [SPEC-VERBATIM-NEEDED] | — | — | ADR-002 | OPEN |
| C-09 | 🔴 [SPEC-VERBATIM-NEEDED] | — | — | ADR-002 | OPEN |
| C-10 | 🔴 [SPEC-VERBATIM-NEEDED] | — | — | ADR-002 | OPEN |
| C-11 | 🔴 [SPEC-VERBATIM-NEEDED] | — | — | ADR-002 | OPEN |
| C-12 | 🔴 [SPEC-VERBATIM-NEEDED] | — | — | ADR-002 | OPEN |

## §7 — Package Manifest (INFERRED from plan-doc P1-1)

Plan doc line 60: *"P1-1 | Design manifest JSON Schema | `packages/schema/package-manifest-v1.json` with all fields from spec §7.1"* — confirms §7.1 lists manifest fields.

| Req | Section | Current | Gap | Owner | Status |
|-----|---------|---------|-----|-------|--------|
| Package identifier (reverse-domain, e.g. `eqratech.jea.membership-renewal`) | §7.1 (INFERRED) | No manifest exists; module names are ad-hoc | Adopt reverse-domain scheme via manifest `name` field | P1-1 | OPEN (P0-3 DENIED, taxonomy TBD) |
| Version (semver) | §7.1 (INFERRED) | Composer packages carry versions; ESP modules don't | Semver on package manifest + Core compat range | P1-1 + P1-4 | OPEN |
| Core version range required | §7.1 (INFERRED) | — | Semver constraint field + resolver | P1-4 | OPEN |
| Entrypoints (backend, frontend, migrations) | §7.1 (INFERRED) | ServiceProvider auto-discovery via `config/modules.php` | Manifest `entrypoints.backend/frontend/db` replaces config discovery | P1-3 | OPEN |
| Package dependencies (declared other packages) | §7.1 (INFERRED) | No declaration; cross-module coupling is compile-time | Declared deps + resolution order | P1-4 | OPEN |
| Data ownership declaration | §7.1 (INFERRED, per handoff §5) | No declaration | Manifest field naming which tables the package owns | P1-1 + ADR-003 | OPEN |
| Uninstall policy (preserve / archive / purge) | §7.1 (INFERRED, per handoff §5) | No uninstall path | Manifest field + lifecycle manager | P1-1 + P4-* | OPEN |
| Signature / provenance | §7.1 (INFERRED, per handoff P8) | None | Signing pipeline + verify at load | P8-* | OPEN |
| All other §7.1 fields | 🔴 [SPEC-VERBATIM-NEEDED] | — | — | P1-1 | OPEN |
| Full §7.2..§7.N (subsections) | 🔴 [SPEC-VERBATIM-NEEDED] | — | — | — | OPEN |

## §8 — 🔴 [SPEC-SECTION-UNKNOWN]

No inference available. Please paste spec §8 text.

## §9 — 🔴 [SPEC-SECTION-UNKNOWN]

No inference available. Please paste spec §9 text.

## §10 — 🔴 [SPEC-SECTION-UNKNOWN]

No inference available. Please paste spec §10 text.

## §11 – §20 — 🔴 [SPEC-SECTIONS-UNKNOWN]

No inference available for §11 through §20. Please paste each section (or drop the .docx text as a whole) and I fill in the rows.

---

## Best-effort gap map derived from plan-doc summary + handoff (VERIFIED)

While §7-§20 wait for spec text, these 14 rows are directly liftable from the plan-doc's "Where we stand vs the target" table (line 13-30) and the handoff §5 ownership model. They are the highest-confidence portion of the audit and cover most of the code-visible surface.

| Category | Requirement | Current state | Gap | Owner | Status |
|----------|-------------|---------------|-----|-------|--------|
| Package physical layout | platform / modules / plugins / integrations separated | ✅ Done via 16-workstream refactor | Vocabulary aligned to spec — none | already met | DONE |
| Config registries | Per-subsystem registries | ✅ Done | No `package.json` manifest yet — see §7 above | P1-1 | IN-PROGRESS |
| ServiceProvider pattern | Per-extension SP boot | ✅ Done | No lifecycle beyond boot (install/enable/upgrade/uninstall) | P4-* | OPEN |
| Workflow Engine | Rule-ID + AuditLog | ✅ Done in `Modules\JeaServices` | Currently JEA-owned; must generalise to Core | P2-1 | OPEN |
| Manual References | Reference-linking runtime | ✅ Done in `Modules\JeaServices` | Currently JEA-owned; must move to Core | P2-3 | OPEN |
| Boundary tests | Backend + frontend architecture tests | ✅ Done (dep-cruiser + PHPUnit) | Must extend to package-conformance tests | P1-2 + P1-3 | OPEN |
| Multi-org (Organization model) | Model + isolation trait | ✅ Partial (`BelongsToOrganization` global scope) | No per-org package activation matrix yet | P4-* | OPEN |
| Zero-package validity (C-02) | Core boots with zero business packages | ❌ Core assumes JEA modules present | Extract runtimes; add clean-core boot test | P2-* | OPEN |
| Package manifest + JSON schema | Manifest spec + validator | ❌ | Design + validator + fixtures | P1-1 + P1-2 | OPEN |
| Lifecycle manager | install / enable / upgrade / uninstall | ❌ | Artisan commands + state machine | P4-* | OPEN |
| Per-org activation | `installations` + `organization_packages` tables | ❌ | Migrations + activation UI | P4-* | OPEN |
| Frontend runtime registry | Route + nav plug-in points | ❌ (`routes.tsx` + `navItems.tsx` are godlists) | Registry with per-package contribution | P5-* | OPEN |
| SDK / package scaffold | CLI + templates | ❌ | New CLI project | P7-* | OPEN |
| Admission controls | Signing + SAST + dependency scan | ❌ | Signing pipeline + scan tooling | P8-* | OPEN |
| Package administration console | Status / health / lifecycle UI | ❌ | New admin UI | P9-* | OPEN |

## Data ownership map — starter enumeration (per handoff §5)

The handoff's §5 lists example tables per ownership category. This starter table takes every table currently in the `backend/database/database.sqlite` schema and classifies it. Rows where classification is ambiguous are marked `LEGACY_JEA — TO-CLASSIFY`.

**Note:** this is P0-2 scope only for the FIRST CUT. ADR-003 will formalise the classification.

| Table | Suggested owner | Classification | Confidence |
|-------|-----------------|----------------|------------|
| `users` | ESP Core | CORE_OWNED | high — matches handoff example |
| `organizations` | ESP Core | CORE_OWNED | high |
| `sessions` | ESP Core | CORE_OWNED | high |
| `personal_access_tokens` | ESP Core | CORE_OWNED | high |
| `audit_logs` | ESP Core | CORE_OWNED | high |
| `notifications` | ESP Core | CORE_OWNED | high |
| `organization_packages` (not yet exists) | ESP Core | ORG_OWNED | high — new in P4 |
| `service_definitions` | JEA package | PACKAGE_OWNED | high |
| `applications` | JEA package | PACKAGE_OWNED | high |
| `application_documents` | JEA package | PACKAGE_OWNED | high |
| `certificates` | JEA package | PACKAGE_OWNED | high |
| `engineers` | JEA package | PACKAGE_OWNED | high |
| `office_registration_requests` | JEA package | PACKAGE_OWNED | high (new 2026-07-27) |
| `projects` | JEA package | PACKAGE_OWNED | high |
| `service_fees` / `fee_*` | JEA package | PACKAGE_OWNED | high |
| `manual_references` / `manual_reference_links` | ESP Core (per handoff §5.2) | CORE_OWNED (at P2 when runtime extracted) | high |
| Everything else in current DB | — | LEGACY_JEA — TO-CLASSIFY | — |

To complete this table: `sqlite3 backend/database/database.sqlite ".tables"` and grade each row. Deferred to a follow-up commit or ADR-003 draft.

## Exit criteria for P0-2

Per plan doc: *"Written approval to proceed; gap audit reviewed."*

This gap audit is complete when:
1. Every C-XX rule row has verbatim rule text
2. Every §7-§20 section has at least placeholder rows for its MUST/SHOULD items
3. The data-ownership table has every current table classified (not just examples)
4. The document has been reviewed and marked "approved" (or amended) by Hussein

Current completion estimate: **~30%** (best-effort inference from plan doc + handoff; ~70% of spec text still needs to be pulled in).

## What I need from you to close the remaining 70%

Priority order:

1. **The verbatim C-01..C-12 constitutional rules** (spec §4.2). Blocks ADR-002.
2. **§7 (Package Manifest) verbatim MUST/SHOULD list.** Blocks P1-1.
3. **§8-§20 verbatim MUST/SHOULD lists** (or a whole-spec text dump). Fills the tables above one section at a time.
4. **Confirmation of the data-ownership classification** for tables in the "TO-CLASSIFY" bucket. Feeds ADR-003.

Any subset unblocks the corresponding section. This document is versioned in place — every new section text added → I fill in rows → commit as an incremental gap-audit update.
