JUDGMENT_BATCH=non-blocking legacy residuals (RES-SG01-01, RES-SG02-01, RES-SG03-02, RES-SG03-03, RES-SG04-01, RES-SG04-02, RES-SG05-01, RES-SG03-01)
SCOPE=readiness-closure/RC-01
OWNER=this closure program

Batched judgment for eight residuals sharing the same reasoning pattern: they represent legacy debt, observability polish, deferred technical judgments, or per-service-onboarding scaling — none reach into target-domain start.

Each residual below is written per the mandatory chain, but abbreviated because they share a shape.

---

## RES-SG01-01 — Legacy `status` column cleanup

الوضع=`ServiceDefinition.status` (active|inactive|draft) preserved alongside new `publication_status`. Both consulted.
تحرير_محل_النزاع=Does keeping two columns block anything? النو answer.
السبب=SG-01 preserved backward compatibility.
الشرط=Nothing depends on their eventual convergence.
المانع=Removing legacy column would break existing seeders + controllers not yet migrated.
العلة=Backward compatibility.
القادح=None.
الصحة=Non-blocking.
الفساد=Not fasid — coexistence is by design.
البطلان=Not batil.
الأثر=Kept as-is.
البقايا=Future cleanup ticket when consumer migration completes.
التعارض=None.
الجمع=Not needed.
الترجيح=Tier-6 preservation.
التوقف=Not stopped.
READINESS_CLASSIFICATION=NON_BLOCKING_LEGACY_RESIDUAL
IMPLEMENTATION_ACTION=None.
CLOSURE_EVIDENCE=Both columns queried; both queries pass tests.

---

## RES-SG02-01 — Ops dashboard for AVAIL_LEGACY_STATUS_FALLBACK

الوضع=Verdicts already carry the code; no dashboard renders it.
تحرير_محل_النزاع=Does missing dashboard block anything?
السبب=Observability polish only.
الشرط=None.
المانع=None.
العلة=Situational awareness.
القادح=None.
الصحة=Non-blocking.
الفساد=Not fasid.
البطلان=Not batil.
الأثر=Add when ops ready.
البقايا=Ops ticket.
التعارض=None.
الجمع=Not needed.
الترجيح=N/A.
التوقف=Not stopped.
READINESS_CLASSIFICATION=NON_BLOCKING_LEGACY_RESIDUAL
IMPLEMENTATION_ACTION=None.
CLOSURE_EVIDENCE=Code emits verdict codes.

---

## RES-SG03-02 — Draft-view UX hint

UX-only. Same reasoning pattern.
READINESS_CLASSIFICATION=NON_BLOCKING_LEGACY_RESIDUAL

---

## RES-SG03-03 — Manual per-application attachment procedure

Edge case for legacy application manual binding. Explicit LEGACY_UNVERSIONED classification exists. No default risk.
READINESS_CLASSIFICATION=NON_BLOCKING_LEGACY_RESIDUAL

---

## RES-SG04-01 — Per-service onboarding pattern

The `Srv001RulesSeeder` IS the pattern. Any new service copies it. Target-domain SRV-001 doesn't need a new pattern — it upgrades the existing SRV001_* rule versions.
READINESS_CLASSIFICATION=NON_BLOCKING_LEGACY_RESIDUAL (target start), BLOCKS_TARGET_DOMAIN_PUBLICATION_ONLY (for services other than SRV-001).

---

## RES-SG04-02 — Manual recalc UX + audit event

`CalculationSnapshotWriter::writeForManualRecalc` exists and is tested. No admin UX for it. Target domain doesn't need it.
READINESS_CLASSIFICATION=NON_BLOCKING_LEGACY_RESIDUAL

---

## RES-SG05-01 — Four deferred extension contracts

Target-domain SRV-001 uses `ServiceSubmissionPolicy` + `ServiceCalculationPolicy` (already implemented). The four deferred contracts (Eligibility/StageAction/FeeStrategy/IntegrationContributor) become live when a second consumer appears. Not needed for target-domain start.
READINESS_CLASSIFICATION=NON_BLOCKING_LEGACY_RESIDUAL

---

## RES-SG03-01 — Extension-declaration snapshotting

Schema snapshots exist (SG-03 delivered). Extension-declaration snapshotting waits for the registry to become per-version, which itself waits for target-canonical SRV-001 to prove the pattern.
READINESS_CLASSIFICATION=BLOCKS_TARGET_DOMAIN_PUBLICATION_ONLY (post-canonical)

---

**Combined effect**: none of the eight residuals in this batch blocks target-domain **start**. Each has a documented expiry / trigger condition. All continue as OPEN in the register with unchanged owners.
