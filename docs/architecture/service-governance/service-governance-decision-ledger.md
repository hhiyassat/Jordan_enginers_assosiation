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
