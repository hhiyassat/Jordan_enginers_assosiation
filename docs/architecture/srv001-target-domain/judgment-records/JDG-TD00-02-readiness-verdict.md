JUDGMENT_ID=JDG-TD00-02
TITLE=TD-00 readiness verdict — what is authorized for TD-01+
OWNER=this closure phase
PHASE=TD-00

الوضع=
TD-00 has produced six deliverables (source register, terminology register, requirement delta matrix, business-rule register, open-decision register, residual register) and one judgment record on the missing SRS body (JDG-TD00-01). 39 rules classified across four dimensions; 20+ ODs classified per blocking scope; 7 TD-00-owned residuals raised.

تحرير_محل_النزاع=
What set of implementation work is IMPLEMENTATION_AUTHORIZED in TD-01+ given the 4-dimensional classification, without violating any of: (a) legacy behaviour preservation, (b) numeric-behaviour preservation, (c) do-not-invent-values, (d) publication-authority absence?

السبب=
User directive requires TD-00 to conclude with a clear "next-phase" authorization statement so TD-01 does not need to re-derive it.

الشرط=
Authorization criteria per rule/component:
- SOURCE_CONFIRMED (Ground Truth §3 anchor) — required
- BUSINESS_APPROVAL_STATUS ≠ REJECTED — required (UNVERIFIED acceptable for structural work with SIMULATION_ONLY output)
- IMPLEMENTATION_AUTHORIZATION = AUTHORIZED or STRUCTURE_ONLY_AUTHORIZED — required
- No numeric/workflow/fee behaviour change to legacy code path — required
- PUBLICATION_AUTHORIZATION = BLOCKED — acceptable (publication happens later, not in TD-01+)

المانع=
Any TD-01+ step that would:
- Modify existing `Srv001Guard`, `WellsCountCalculator`, `NetDepthTable`, `ExplorationRequirementMatrix` output on any input → FORBIDDEN
- Change fee for any existing seeded service → FORBIDDEN
- Change 7-state application state machine transitions → FORBIDDEN (extend only via new opt-in states behind version binding)
- Publish target-canonical rules without OD-Closure evidence → FORBIDDEN
- Cite SRS §N line for any promotion without RES-TD00-01 closure → FORBIDDEN
- Use forbidden aliases (Disposal / إتلاف as workflow / etc.) → FORBIDDEN

العلة=
User directive + evidence integrity + governance foundation (SG-* / RC-*) invariants.

القادح=
Any deliverable that would violate the FORBIDDEN list above.

الصحة=
Valid TD-01+ scope (**IMPLEMENTATION_AUTHORIZED_NOW**):

1. **Target-domain skeleton classes** (parallel to Legacy* classes, not wired to runtime):
   - `Modules\JeaServices\Domain\Srv001\TargetSrv001SubmissionPolicy` implementing `ServiceSubmissionPolicy`
   - `Modules\JeaServices\Domain\Srv001\Calculators\*` implementing `ServiceCalculationPolicy` — each initially delegating to the Legacy* calculator (preserves behaviour) with a `PROVISIONAL_TARGET_DOMAIN` marker
   - Value objects: `Srv001SubmissionInputs`, `Srv001DerivedValues`, `Srv001CalculationEvidence`, `Srv001ValidationErrors`
   - Ports (interfaces only): `EngineerRegistryPort` (DLS lookup — simulated), `DlsLookupPort` (parcel lookup — simulated), `PaymentGatewayPort` (already exists), `NotificationPort` (already exists via JeaNotificationService)

2. **Rule-version + snapshot writer wiring** (currently SG-04-ready but not connected):
   - Wire `LegacySrv001SubmissionPolicy` or the new `TargetSrv001SubmissionPolicy` end-to-end via a new submission use case that consumes `ServiceSubmissionDecision` and calls `CalculationSnapshotWriter::writeForSubmit`
   - This closes RES-SG06-01 by giving snapshot writes a runtime path (still without changing legacy numeric outputs — decision object carries the same values Legacy* produced)

3. **Duplicate-identity extension** (BR-DUP-01):
   - Add `application_cadastral_history` index (composite: basin+parcel+basin_name+owner+type+created_at)
   - Extend `OwnerMatchClearanceGuard` (or add new guard) to enforce the 5-year window
   - Configuration key: `esp.srv001.duplicate_identity_window_years` default 5

4. **Attachment schema extensions** (BR-DOC-01):
   - Add per-well photo field type to schema (schema-level; renderer already schema-driven)
   - Add topographic attachment (PDF/DWG/image)
   - Do NOT change existing `site_investigation_report` behaviour

5. **PartialEditGrant mechanism** (BR-ELIG-03):
   - Add `application_edit_grants` table + policy class
   - Scope is UNSPECIFIED (blocked by OD-29) → default deny (backward compatible with current `isEditable` behaviour)

6. **Super-admin return with reason** (BR-ELIG-04):
   - Add pre-payment-only admin-return endpoint + audit
   - Feature flag OFF by default

7. **Rejection note mandatory** (BR-STATE-03):
   - Add field validator; behind feature flag until BR-STATE-01 (state chain) is ready

8. **Cross-office reassignment prevention** (BR-ROUTE-05):
   - Add unique constraint or query-guard preventing re-routing to a different office after return

9. **Certificate validity as dynamic config** (BR-DUP-04):
   - Add config key `esp.srv001.certificate_validity_years` default 5
   - Wire into certificate `expiry_date` calculation

10. **Terminology enforcement** (RES-TD00-03):
    - Architecture test that greps for forbidden aliases across source + docs + seeders + i18n JSON

**FORBIDDEN for TD-01+**:

- Changing any Legacy* calculator output
- Changing fee for any seeded service
- Publishing (activating) any target-canonical class without OD-Closure evidence
- Modifying the 7-state application machine in a way that changes existing transitions
- Implementing OD-31/32/33 (proposed) without a signed decision
- Implementing OD-01/07/11/12/19/20/21/22/23 numeric outputs without JEA signature

**BLOCKED for TD-01+** (structural pending):

- BR-STATE-01 target 11-state chain (needs OD-18 for post-second-auditor)
- BR-CALC-05 tower/mega classification (needs OD-20)
- Committees model (needs OD-31)
- Second-auditor substitution model (needs OD-32)
- Excavation-completed gate (needs OD-33)
- All fee formulas (need OD-01, OD-19, and CONF-02/03 resolution)

الفساد=
Any structural class built under §11 above that accidentally reads or writes state outside its port boundary → fasid (repairable via review).

البطلان=
Any promotion of a target class to PUBLISHED without an OD-Closure ID linked in its version's `approval_reference` → batil.

الأثر=
(1) TD-01+ builds against a clear, bounded scope. (2) Every commit in TD-01+ can name which rule/component it advances + which classification improves. (3) The `LegacySrv001SubmissionPolicy` path continues to be the runtime path throughout TD-01..TD-N until the swap decision (which itself requires the target-canonical version to be BUSINESS_APPROVED + PUBLICATION_AUTHORIZED).

البقايا=
No new residuals beyond those already in the residual register. The 10-item TD-01+ scope IS the residual → work mapping.

التعارض=
None — the scope reconciles user directive + foundation invariants + Ground Truth authority.

الجمع=
Not needed — the FORBIDDEN and AUTHORIZED sets are mutually exclusive.

الترجيح=
Tier-1 (signed decisions) — absent. Tier-2 (governing SRS) — file missing per RES-TD00-01. Tier-4 (approved architecture — SG-*) + tier-5 (runtime safety) + tier-6 (current implementation) all point to the parallel-target-domain-classes-under-simulation approach.

التوقف=
STOPPED for: any single rule where OD-Closure or SRS body citation is needed for its business meaning to be executed as canonical. NOT STOPPED for: the 10-item scope above.

READINESS_CLASSIFICATION=READY_WITH_NON_BLOCKING_RESIDUALS (for TD-01+ IMPLEMENTATION_AUTHORIZED scope); BLOCKS_TARGET_PUBLICATION for the target-canonical rollout (unchanged from preceding readiness closure).

IMPLEMENTATION_ACTION=Commit TD-00. Begin TD-01 targeting the 10-item scope above.

CLOSURE_EVIDENCE=
- Six TD-00 register files under `docs/architecture/srv001-target-domain/td-00/`
- Two judgment records under `docs/architecture/srv001-target-domain/judgment-records/`
- Zero legacy-behaviour changes
- Zero code changes (this is a read-only phase)
