JUDGMENT_ID=JDG-SG03-01
TITLE=Service-definition version snapshot — scope
SCOPE=architecture/service-governance/SG-03
OWNER=service-governance-remediation

الوضع=
No `service_definition_versions` table exists (verified by data audit — RES-SG00-01). The complete runtime judgment of a service depends on (a) schema JSON, (b) generic engine code version, (c) cross-cutting guards, (d) optional service-specific extensions, (e) external state (per JDG-SG00-01). A version snapshot could theoretically capture all five dimensions.

تحرير_محل_النزاع=
Should the immutable version entity snapshot capture ONLY the schema JSON of a service_definition row, or ALSO the extension declarations (which submission guard / calculators / stage actions were bound to that version at publish time)?

السبب=
RES-SG00-01 explicitly asks this question. SG-03 must produce a decision.

الشرط=
The chosen scope must (i) prevent silent schema drift for in-flight applications (the primary R-01 risk), (ii) be operationally implementable given the current binding model (`ServiceSubmissionGuardRegistry` singleton-map registered in `JeaServicesServiceProvider`), (iii) support historical reproducibility of fee calculation and workflow routing, (iv) not require refactoring the guard-registry.

المانع=
Snapshotting extension declarations would require the guard-registry to become per-version (each version binds to a specific implementation class or capability declaration). That refactor is SG-05 / SG-06 work, not SG-03.

العلة=
Separation of concerns + phased delivery. The R-01 primary risk is schema drift (fee amount / workflow stages / required fields changing under an in-flight application). Extension-code drift (a guard's behaviour changing between deploys) is the R-02 risk, addressed by SG-04's rule-versioning mechanism (`CalculationSnapshot`). Coupling them into a single snapshot would either force SG-04 to be complete before SG-03 lands, or would create a partial snapshot that is misleading (looks reproducible but the code side drifts silently).

القادح=
Attempting to snapshot extension declarations in SG-03 without SG-05's registry refactor would produce a snapshot column that documents intent but is not enforced at runtime — a fasid state.

الصحة=
Valid scope for SG-03: capture ONLY the schema JSON + a schema hash + service_definition_id + version_identifier + status + effective_from/to + created/approved actors + supersedes chain. Extension-declaration snapshotting is deferred to a joint SG-05/SG-06 follow-up once the registry supports per-version binding.

الفساد=
Snapshotting extension declarations today without registry enforcement would be a fasid state.

البطلان=
Snapshotting nothing (letting `service_definitions.schema` remain the only source under an application's foot) is batil against the R-01 mandate.

الأثر=
(1) `service_definition_versions` stores `schema_snapshot` (JSON), `schema_hash` (SHA-256 of canonical JSON), plus governance fields. (2) Extension-declaration snapshotting recorded as a joint SG-05/SG-06 follow-up. (3) SG-04's `CalculationSnapshot` covers the code-side reproducibility gap for guard-computed derived values.

البقايا=
RES-SG03-01: Extend the version to also capture the set of registered service-specific extensions for the version, once SG-05/SG-06 make the registry per-version. Owner: post-SG-06. Blocks: full reproducibility guarantee.

التعارض=
Full reproducibility (all five dimensions) vs deliverable scope for SG-03.

الجمع=
Reconcile via layered snapshotting: SG-03 covers schema; SG-04 covers rule versions + calculation snapshots; a future phase covers extension bindings.

الترجيح=
Tier-4 (target architecture) demands full reproducibility eventually. Tier-5 (runtime safety) is preserved by the phased approach — no drift is silent because CalculationSnapshot (SG-04) will pin the exact rule-version used for each derived value.

التوقف=
Not stopped. The extension-declaration question is deferred, not left ambiguous.

EVIDENCE=
- docs/architecture/service-governance/judgments/JDG-SG00-01-source-of-truth-model.md
- backend/modules/JeaServices/Providers/JeaServicesServiceProvider.php:91 (ServiceSubmissionGuardRegistry singleton binding)
- backend/modules/JeaServices/Engine/ServiceSubmissionGuardRegistry.php (registry API — no version parameter)

DECISION=
Snapshot schema only in SG-03. Full extension-declaration snapshotting is a documented residual (RES-SG03-01) closed by a post-SG-06 follow-up.

IMPLEMENTATION_EFFECT=
`service_definition_versions` table has `schema_snapshot` JSON + `schema_hash` string + governance fields, no extension column.

MIGRATION_EFFECT=
Additive table. No existing data touched.

TEST_EVIDENCE=
Tests assert immutability (attempts to update a published version's `schema_snapshot` throw); assertion that `schema_hash` matches SHA-256 of canonical JSON.

OPEN_RESIDUALS=
- RES-SG03-01 (extension-declaration snapshotting — post-SG-06).
