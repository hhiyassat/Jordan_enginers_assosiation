# ESP v2 — Requirements Traceability Matrix (RTM)

**Document ID:** ESP-RTM-001
**Version:** 0.1 (STUB — pending authorship)
**Status:** PLACEHOLDER — cited by `BUILD_CONTRACT.md` §3 B-11 and §5 rule 1

---

## Purpose

Cited in `BUILD_CONTRACT.md` §3 row B-11 ("Decision Traceable — RTM.md traces requirement → code → test") and §5 rule 1 ("Implements a traced requirement (RTM.md updated)"). Stub created to resolve the phantom-file reference the 2026-07-30 architecture review flagged.

## Current authoritative sources

Until this file is populated:

- `REQUIREMENTS.md` — authoritative source for BR / FR / NFR / SEC identifiers.
- `git log --grep='REQ-\|BR-\|FR-\|NFR-\|SEC-'` — traces which commits implement which requirement.
- Per-test docblocks and inline comments (e.g. `NFR-002: BelongsToOrganization trait enforces tenant isolation.`) — provide the reverse trace.
- `docs/remediation/architecture-review-remediation-ledger.md` — traces each architecture-review finding to its commit + tests.

## To be authored

A single table (or generated report) with columns:

| Requirement ID | Description | Implementing files | Test file(s) | Status |
|---|---|---|---|---|

Generation candidate: an artisan command that walks `REQUIREMENTS.md`, greps the codebase for each requirement ID, and emits this table on demand. Tracked as follow-up under `P4-2` in the remediation ledger.
