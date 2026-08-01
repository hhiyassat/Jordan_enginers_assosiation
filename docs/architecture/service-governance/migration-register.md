# Service Governance Migration Register

Every database or code migration considered or executed by this program.

| Migration ID | Phase | Description | Reversible | Historical Impact | Status |
|---|---|---|---|---|---|
| (none yet) | SG-00 | Documentation-only phase — no migrations. | N/A | N/A | N/A |
| MIG-SG01-01 | SG-01 | `2026_08_01_000010_add_lifecycle_governance_to_service_definitions` — additive columns (uat_*, publication_*, effective_from, suspended_*, retired_*, publication_reason). All defaults preserve current behaviour. | Yes (down() drops the new columns) | Existing rows default to `uat_status='NOT_SUBMITTED'` / `publication_status='NOT_PUBLISHED'`. Historical applications continue to reference their service_definitions row unchanged. | APPLIED (locally) |
| MIG-SG03-01 | SG-03 | `2026_08_01_000020_create_service_definition_versions_table` — new immutable version table. | Yes (down() drops the table) | No existing data touched. | APPLIED (locally) |
| MIG-SG03-02 | SG-03 | `2026_08_01_000021_add_service_definition_version_id_to_applications` — nullable FK. | Yes (down() drops the FK) | Existing applications remain FK=null (LEGACY_UNVERSIONED per JDG-SG03-03). Explicit no-back-fill decision. | APPLIED (locally) |
| MIG-SG04-01 | SG-04 | `2026_08_01_000030_create_rule_provenance_tables` — three tables: rule_definitions, rule_versions, calculation_snapshots. | Yes (down() drops all three) | No existing data touched. Srv001RulesSeeder populates rule rows on next `db:seed`. | APPLIED (locally) |
