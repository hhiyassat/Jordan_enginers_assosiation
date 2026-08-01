# Service Governance Migration Register

Every database or code migration considered or executed by this program.

| Migration ID | Phase | Description | Reversible | Historical Impact | Status |
|---|---|---|---|---|---|
| (none yet) | SG-00 | Documentation-only phase — no migrations. | N/A | N/A | N/A |
| MIG-SG01-01 | SG-01 | `2026_08_01_000010_add_lifecycle_governance_to_service_definitions` — additive columns (uat_*, publication_*, effective_from, suspended_*, retired_*, publication_reason). All defaults preserve current behaviour. | Yes (down() drops the new columns) | Existing rows default to `uat_status='NOT_SUBMITTED'` / `publication_status='NOT_PUBLISHED'`. Historical applications continue to reference their service_definitions row unchanged. | APPLIED (locally) |
