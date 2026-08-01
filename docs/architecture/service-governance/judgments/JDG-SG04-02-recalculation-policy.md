JUDGMENT_ID=JDG-SG04-02
TITLE=Recalculation policy and snapshot immutability
SCOPE=architecture/service-governance/SG-04
OWNER=service-governance-remediation

الوضع=
Program §Phase SG-04 mandates definitions for: draft recalculation, resubmission recalculation, submitted-application immutability, manual-approved recalculation, historical reproduction. The current `Srv001Guard::validate` re-computes derived values on every submit — draft editors see up-to-date computations because the guard is re-invoked. No snapshots exist yet.

تحرير_محل_النزاع=
When are new `CalculationSnapshot` rows created, when are existing ones overwritten, and when is history preserved even during recomputation?

السبب=
Program §Phase SG-04 §Recalculation policy explicitly asks. Snapshots without a clear write policy would either accumulate garbage (many drafts per applicant) or hide history (last-write-wins).

الشرط=
Policy must (i) let a draft re-computation update the "current" snapshot without preserving the intermediate drafts (drafts are exploratory), (ii) preserve every submitted snapshot indefinitely (audit trail), (iii) allow a manual approved recalculation on a submitted application to write a NEW snapshot without overwriting the historical one, (iv) let history be reproduced by joining application → rule_version → snapshot.

المانع=
Any policy that mutates the snapshot of a submitted application in place would defeat historical reproduction. Any policy that keeps every draft revision snapshot inflates storage without benefit.

العلة=
Historical integrity + operational cost.

القادح=
None.

الصحة=
Valid recalculation policy:
  - DRAFT recalculation: writes a snapshot; if a snapshot for (application, rule_version, purpose=DRAFT) already exists, OVERWRITE it. This is the "current draft state" record.
  - SUBMIT recalculation: writes a NEW snapshot with purpose=SUBMIT. Never overwrites.
  - MANUAL_RECALC on a submitted application: writes a NEW snapshot with purpose=MANUAL_RECALC linked to the SUBMIT snapshot as `superseded_snapshot_id`. Preserves the SUBMIT snapshot.
  - HISTORICAL_REPRODUCTION query: join application → rule_version → snapshots WHERE purpose IN (SUBMIT, MANUAL_RECALC) ORDER BY calculated_at.

الفساد=
Overwriting a SUBMIT snapshot on any code path is fasid — repairable only by never doing it.

البطلان=
A snapshot writer that lets any caller update a SUBMIT snapshot is batil.

الأثر=
(1) `CalculationSnapshot` has a `purpose` enum. (2) `CalculationSnapshotWriter` service enforces the write policy. (3) Model observer refuses UPDATE on rows with purpose IN (SUBMIT, MANUAL_RECALC).

البقايا=
RES-SG04-02: Manual recalculation UX + audit event definition. Owner: ops follow-up.

التعارض=
None.

الجمع=
Not needed.

الترجيح=
Tier-4 + Tier-5.

التوقف=
Not stopped.

EVIDENCE=
- backend/modules/JeaServices/Engine/Srv001Guard.php (current recomputation on submit)

DECISION=
Adopt the four-way policy above. Add `purpose` enum (DRAFT, SUBMIT, MANUAL_RECALC) to `calculation_snapshots`. Enforce SUBMIT/MANUAL_RECALC immutability in the model.

IMPLEMENTATION_EFFECT=
`CalculationSnapshotWriter::writeForDraft` / `writeForSubmit` / `writeForManualRecalc`. Model observer.

MIGRATION_EFFECT=
None (no existing snapshots).

TEST_EVIDENCE=
Tests: DRAFT overwrites; SUBMIT never overwrites; SUBMIT immutability observer throws; historical reproduction query returns rows in correct order.

OPEN_RESIDUALS=
- RES-SG04-02 (manual recalc UX).
