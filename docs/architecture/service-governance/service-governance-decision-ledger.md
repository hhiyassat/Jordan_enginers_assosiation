# Service Governance Decision Ledger

Running ledger of every material judgment across phases SG-00..SG-06. Each row cites the Judgment Record that carries the full reasoning chain.

| Judgment ID | Phase | Title | Decision | Status |
|---|---|---|---|---|
| JDG-SG00-01 | SG-00 | Service configuration source-of-truth model | Adopt distributed model (schema + engine + guards + extensions + external state) | ISSUED |
| JDG-SG00-02 | SG-00 | SRV-001 classification and terminology | Use four-classification set (PILOT_COMPLETE / PARTIAL / PARTIAL_OR_UNCONFIRMED / INCOMPLETE) | ISSUED |
| JDG-SG00-03 | SG-00 | Count reconciliation | Use REAL_APPROVED / REAL_UNAPPROVED / PLACEHOLDER / ABSENT / UNKNOWN vocabularies | ISSUED |
| JDG-SG00-04 | SG-00 | Service Package Contract persistence correction | Policies return typed decisions; use cases persist | ISSUED |
| JDG-SG01-01 | SG-01 | Service lifecycle state model — column layout | Add 15 governance columns; derive lifecycle from them | ISSUED |
| JDG-SG01-02 | SG-01 | Publication صحة conditions and موانع | 8 blocker codes + maker-checker in `ServicePublicationPolicy` | ISSUED |
| JDG-SG02-01 | SG-02 | Availability preference order (LENIENT vs STRICT) | LENIENT default; legacy status='active' fallback with warning | ISSUED |
| JDG-SG02-02 | SG-02 | Integration surface scope | Catalog wired; RES-SG02-02 tracks the remaining four endpoints | ISSUED |
| JDG-SG03-01 | SG-03 | Version snapshot scope | Schema-only; extension-declaration snapshotting deferred | ISSUED (closes RES-SG00-01) |
| JDG-SG03-02 | SG-03 | Application-version binding timing | Bind at submit; drafts unbound | ISSUED |
| JDG-SG03-03 | SG-03 | Legacy unversioned migration policy | No back-fill; explicit LEGACY_UNVERSIONED classification | ISSUED |
| JDG-SG04-01 | SG-04 | Rule model granularity | Per-calculator; three RuleDefinition rows for SRV-001 | ISSUED |
| JDG-SG04-02 | SG-04 | Recalculation policy | DRAFT overwrites; SUBMIT immutable; MANUAL_RECALC supersedes | ISSUED |
| JDG-SG05-01 | SG-05 | Extension contract scope | Implement ServiceSubmissionPolicy + ServiceCalculationPolicy; defer 4 others | ISSUED |
| JDG-SG06-01 | SG-06 | Legacy boundary approach | Parallel implementation; leave Srv001Guard runtime-wired | ISSUED |
