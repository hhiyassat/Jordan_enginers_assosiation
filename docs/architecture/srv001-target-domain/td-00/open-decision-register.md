# TD-00 · Open Decision Register (SRV-001 scope)

Every Open Decision (OD) referenced by Ground Truth §7 (Active Blockers) + §4 (Conflicts) + §5 (Gaps proposing new ODs). Owner-and-blocking-scope classification per user directive.

Legend:

* **BLOCKS_CALC**: blocks numeric calculation implementation
* **BLOCKS_ROUTE**: blocks path/workflow structure implementation
* **BLOCKS_INTEGRATION**: blocks external integration wiring
* **BLOCKS_PUB**: blocks target-canonical PUBLICATION (never coding)
* **BLOCKS_ACTIVATION**: blocks public activation of the affected service version
* **NON_BLOCKING**: unblocks TD-01+ structural work even if unresolved

## ODs — from Ground Truth §7 (existing SRS §18 blockers)

| OD_ID | Question | Ground Truth reference | Owner | Blocking scope | Blocks target-start? | Blocks publication? |
|---|---|---|---|---|---|---|
| **OD-01** | Fee formula base — engineering-services value + income tax + contract value + social-responsibility + survey fee | §7 (BLOCKS_CALC); CONF-02, CONF-03 | JEA product | BLOCKS_CALC | NO (structure) | YES |
| **OD-02** | (Numeric fee-related, unspecified in Ground Truth) | §7 | JEA product | BLOCKS_CALC | NO | YES |
| **OD-07** | Well count for area band 801-1000: 4 or 5 | §4 CONF-01 | JEA product | BLOCKS_CALC | NO (structure) | YES |
| **OD-11** | Minimum floors for soil test: 3 or 4 | §4 CONF-04 (BR-006) | JEA product | BLOCKS_CALC | NO (legacy value preserved) | YES |
| **OD-12** | (Numeric calculation OD, unspecified in Ground Truth) | §7 | JEA product | BLOCKS_CALC | NO | YES |
| **OD-18** | Post-second-auditor path — after Approved_Technically what precisely happens | §7 (BLOCKS_ROUTE) | JEA product | BLOCKS_ROUTE | PARTIAL (state-machine addition postponed) | YES |
| **OD-19** | (Calculation OD, unspecified) | §7 | JEA product | BLOCKS_CALC | NO | YES |
| **OD-20** | Tower/mega threshold — 15 floors edge (CONF-05) | §7 + §4 CONF-05 (BR-001) | JEA product | BLOCKS_CALC + BLOCKS_ROUTE | NO (workaround: SPECIAL_STUDY at floors 9+ preserved) | YES |
| **OD-21** | Roof 50m² edge — > vs ≥ | §7 + §4 CONF-06 (BR-004) | JEA product | BLOCKS_CALC | NO | YES |
| **OD-22** | (Calculation OD, unspecified) | §7 | JEA product | BLOCKS_CALC | NO | YES |
| **OD-23** | (Calculation OD, unspecified) | §7 | JEA product | BLOCKS_CALC | NO | YES |
| **OD-24** | Attachment file-size limits (500MB / 4MB per Ground Truth §7 hint) | §7 (BLOCKS_OPS) | JEA ops + JEA IT | BLOCKS_PUB (activation-blocking) | NO | YES |
| **OD-25** | RTO / RPO for disaster recovery | §7 (BLOCKS_OPS) | JEA IT | BLOCKS_PUB | NO | YES |
| **OD-26** | Sizing + concurrency assumptions | §7 (BLOCKS_OPS) | JEA IT | BLOCKS_PUB | NO | YES |
| **OD-27** | Retention windows | §7 (BLOCKS_OPS) | JEA legal + IT | BLOCKS_PUB | NO | YES |
| **OD-29** | Terminology canon — إجازة فنية vs قبول, states-vs-actions | §7 (BLOCKS_ROUTE) + §5 GAP-04 | JEA product | BLOCKS_ROUTE (subtle — affects state model) | NO (structural work continues under existing 7-state model) | YES |
| **OD-30** | External integration contracts (Oracle / BURA256 / BURA235 / BURA514 / DLS) + System of Record per field | §7 (BLOCKS_INTEGRATION) | JEA IT + external | BLOCKS_INTEGRATION | NO (mechanism-only implementable) | YES |

## Proposed ODs — from Ground Truth §5 (Gaps needing new ODs)

| OD_ID | Question | Ground Truth ref | Owner | Blocking scope | Blocks target-start? | Blocks publication? |
|---|---|---|---|---|---|---|
| **OD-31** (proposed) | Committees replacing first auditor for energy projects — decision model (single/quorum) + inclusion in permission matrix and transitions | §5 GAP-01 | JEA product | BLOCKS_ROUTE | NO | YES |
| **OD-32** (proposed) | Second-auditor substitution for first — conditions, same-person-both-stages permissibility, mandatory logging | §5 GAP-02 | JEA product | BLOCKS_ROUTE | NO | YES |
| **OD-33** (proposed) | "Excavation done" gate event before sensory inspection — who reports, how verified | §5 GAP-03 | JEA product + ops | BLOCKS_ROUTE | NO | YES |

## Depth-table OD (from §7 explicit note)

| OD_ID | Question | Ground Truth ref | Owner | Blocking scope | Blocks target-start? | Blocks publication? |
|---|---|---|---|---|---|---|
| **OD-DEPTH** (implicit, §7 explicit) | جداول العمق §8.4 — متى الثلث/الثلثان وكيفية الجمع (غير قابلة للبرمجة حاليًا) | §7 | JEA product | BLOCKS_CALC | NO (legacy PROVISIONAL preserved) | YES |

## Inherited residuals from SG-* / RC-* still relevant

| RESIDUAL_ID | Nature | Blocks target-start? | Blocks publication? |
|---|---|---|---|
| RES-SG00-02 | SRV-001 provisional-calculator JEA sign-off | NO | YES (until UAT reference attached) |
| RES-SG00-03 | Per-service `_UNAPPROVED` classification pending JEA sign-off | NO | YES (per service) |
| RES-SG03-01 | Extension-declaration snapshotting (post-canonical) | NO | Post-canonical only |
| RES-SG05-01 | Four deferred extension contracts (extract when 2nd consumer appears) | NO | NO |
| RES-SG06-01 | Runtime path swap (legacy guard → typed decision consumer) | NO | Blocks legacy removal, not target-domain build |
| RES-TD00-01 | Actual SRS v1.1 body file not in repo | NO (Ground Truth substitute) | Partial: blocks SRS §-level citation for any promotion |

## Summary — target-start blocking count

**Zero ODs block target-domain start.** All ODs listed above are either:
- Structural-implementation blockers (blocks calculation numeric changes) — target classes can be built with SIMULATION_ONLY outputs and PROVISIONAL classifications
- Route/state-machine blockers — target work continues within the existing 7-state model until OD-18/29/31/32/33 close
- Integration-contract blockers — target work builds the ports; adapters remain simulated until OD-30 closes
- Publication blockers — do not affect coding

Target-domain-start blockers would be:
- Loss of platform/architecture invariants (none exist)
- Data-model corruption risk (none exist)
- Legacy behaviour change (forbidden by user directive)

## OD-Closure workflow (out of TD-00 scope, referenced only)

Ground Truth §8: an OD closes when its items move from §4/§5/§7 to §3 with an OD-Closure reference and date. TD-00 does NOT close any ODs — it only classifies them.
