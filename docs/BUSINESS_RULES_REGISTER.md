# ESP v2 — Business Rules Register

**Document ID:** ESP-BR-001
**Version:** 0.1 (STUB — pending authorship)
**Status:** PLACEHOLDER — cited by `BUILD_CONTRACT.md` §2 row 6

---

## Purpose

Cited in `BUILD_CONTRACT.md` as artifact #6, "Business Rules Register — Fixed vs Derivable per §4.3". Stub created to resolve the phantom-file reference from the 2026-07-30 architecture review; authoring the full register is `P4-2` in the remediation ledger.

## Current authoritative sources

Business rules today are encoded in one of:

- **Fixed by rule** — `WorkflowEngine::ALLOWED_TRANSITIONS`, `Application` STATUS constants, `Certificate` status enum, `CrossCuttingSubmissionPipeline` guard order.
- **Derivable** — `config/esp.php` (session/password/captcha/notification retention), per-service `schema.workflow[*].sla_hours`, per-schema upload rules, `esp.password_history_size`.
- **Domain-specific engines** — `WellsCountCalculator`, `NetDepthTable`, `ExplorationRequirementMatrix`, `FeeCalculator`, `Srv001Guard`, `CadastralConflictGuard`, `OwnerMatchClearanceGuard`, `QuotaLedger`, `CapacityGuard`, `RecurringDuesService`.
- **Cross-cutting guards** — see `docs/architecture/cross-cutting-submission-pipeline.md`.

## To be authored

A single tabulation with the columns: `ID` · `Category (Fixed/Derivable)` · `Rule` · `Enforced in file` · `Test coverage` · `Change gate (rule/config/migration)`. The current handoff already enumerates most rules narratively; this file will lift them into a machine-readable table.
