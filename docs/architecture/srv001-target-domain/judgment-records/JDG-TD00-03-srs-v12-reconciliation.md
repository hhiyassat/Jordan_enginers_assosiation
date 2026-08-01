JUDGMENT_ID=JDG-TD00-03
TITLE=SRS v1.2 reconciliation — RES-TD00-01 closure + delta absorption
OWNER=this reconciliation commit
PHASE=TD-00 (addendum)

الوضع=
User supplied `srs/JEA-ESP2-SRS-SITE-SURVEY-001_v1.2.md` (350 lines / 40 KB, verified authentic SRS body). Original TD-00 was executed against Ground Truth as the reconciliation substitute because the file previously supplied at path `srs/JEA_Site_Survey_SRS_ESP_v2_AR_v1.1_Reviewed.txt` contained an unrelated bash test log (documented in JDG-TD00-01, escalated as RES-TD00-01). Direct read of v1.2 reveals: (a) v1.2 explicitly supersedes v1.0/v1.1/v0.1 per §version log, (b) v1.2 §الخلاصة confirms the document is "غير معتمدة للتنفيذ النهائي حتى إغلاق الحاجبات وتوقيع خط الأساس 2.0", (c) v1.2 introduces 10 new requirements (FR-SS-081..090) and 2 new open decisions (OD-34, OD-35) beyond what Ground Truth documented, (d) v1.2 §4.3 provides the full net-depth table (floors 3-14 explicit + 15-34 aggregated ranges), (e) v1.2 §4.1 provides the well-count band for >3000m² and ≥15 floors as BLOCKED items.

تحرير_محل_النزاع=
Does supplying the actual SRS v1.2 change TD-00's four-dimensional classifications, the readiness verdict, or the AUTHORIZED-scope for TD-01, in ways that require re-doing TD-00 rather than an addendum?

السبب=
User directive: "Do not restart TD-00. Continue from the next incomplete phase in the approved program." Reconciliation must therefore be an ADDENDUM commit preserving the TD-00 baseline, not a re-execution.

الشرط=
Reconciliation may proceed as an addendum only if (i) SRS v1.2 CONFIRMS rather than CONTRADICTS the Ground-Truth-based TD-00 classifications, (ii) every new v1.2 item can be added without invalidating existing classifications, (iii) the verdict from JDG-TD00-02 (READY_WITH_NON_BLOCKING_RESIDUALS + 10-item AUTHORIZED scope) remains valid after absorption.

المانع=
If SRS v1.2 flipped any BUSINESS_APPROVAL classification from UNVERIFIED to APPROVED, an addendum would understate the change. If SRS v1.2 introduced a target-start blocker, the verdict would need to flip.

العلة=
Preservation of the TD-00 commit + audit trail; user directive to continue rather than restart.

القادح=
Would fire only if SRS v1.2 contained an OD-Closure signature list, a formal APPROVED-baseline stamp, or a new rule impossible to build without immediate implementation. None of these are present in v1.2.

الصحة=
Valid reconciliation (Option ADDENDUM):
- **RES-TD00-01 CLOSED** — file now supplied.
- **RES-TD00-01b OPEN** — new sub-residual: SRS v1.2 own §الخلاصة declares itself NOT APPROVED. This is the exact same status as before (per JDG-TD00-02) — user directive: "Do not infer formal approval from the word 'Reviewed'" is CONFIRMED by the SRS text itself.
- **10 new TD-REQ rows** added to `requirement-delta-matrix.csv` for FR-SS-081..090 with their SRS-native classifications.
- **6 new TD-REQ rows** added for SRS-v1.2-specific items not covered by GT (depth tables §4.3, wells count >3000 §4.1, category B ambiguity §2.2, tax paths §6.2, reinforcement path §7.4, exemption invariants §5, form-step model §3, entities §10, RBAC §13, reports §15).
- **OD-34, OD-35 added** to `open-decision-register.md`.
- **OD-31, OD-32, OD-33 reclassified** from "proposed" to "formally adopted in SRS §18" (SRS v1.2 ratifies them from the Ground-Truth-proposed status).
- **OD-05 marked CLOSED-BY-MERGE** — SRS §18 explicitly merges OD-05 into OD-35.
- **Source register updated**: SRS v1.2 = rank 2, AVAILABLE, NOT_APPROVED_BY_OWN_DECLARATION.
- **10-item AUTHORIZED TD-01 scope from JDG-TD00-02 is preserved unchanged** — none of the SRS v1.2 additions unlock a new AUTHORIZED item; none block an existing one.

الفساد=
If any SRS v1.2 item silently flipped a rule to APPROVED without a per-rule OD-Closure, the addendum would be fasid. Repairable by explicit UNVERIFIED classification on every added row.

البطلان=
Any claim that SRS v1.2 supplies OD-Closure evidence for the pre-existing OD-01/07/11/12/17/19/20/21/22/23/24/25/26/27/29/30 blockers would be batil. SRS v1.2 §18 explicitly LISTS them as still-OPEN and adds OD-34/35 to the open list.

الأثر=
(1) Reconciliation commit updates 3 register files in place + adds 1 new reconciliation report + this judgment record. (2) TD-00-report.md marker updated (via reconciliation report referring to it). (3) Verdict from JDG-TD00-02 stands: READY_WITH_NON_BLOCKING_RESIDUALS. (4) TD-01 scope unchanged.

البقايا=
- RES-TD00-01b (SRS v1.2 own non-approval declaration) — informational; identical semantics to pre-existing RES-TD00-02.
- No new residuals from reconciliation itself.
- Existing residuals RES-TD00-02..07 unchanged.

التعارض=
Ground Truth §4 CONF-01 ("801-1000 wells: 4 or 5") remains — SRS v1.2 §4.1 confirms the same conflict (row-labelled CONFLICTED — OD-07, with note "5 معتمد من صورة الجدول الرسمي؛ ورد 4 في توصيف الخدمة PDF"). SRS v1.2 CONFIRMS the ground truth's classification rather than resolving it.

Ground Truth §7.2 step 4 (post-second-auditor path unclear) is now SPECIFIED as OD-34 in SRS v1.2 §7.2 + §18 — the ambiguity is formalised, not resolved.

الجمع=
No reconciliation-time joining needed. Every Ground Truth classification survives; SRS v1.2 additions layer on top.

الترجيح=
Tier-2 (governing SRS) is now AVAILABLE via v1.2 — but v1.2 explicitly disclaims approved status, so tier-1 remains needed for any promotion to BUSINESS_APPROVED. This is exactly the state JDG-TD00-02 anticipated.

التوقف=
Not stopped. RES-TD00-01 closed. TD-01 continues per JDG-TD00-02 authorized scope.

READINESS_CLASSIFICATION=Unchanged: READY_WITH_NON_BLOCKING_RESIDUALS for TD-01+. RES-TD00-01 CLOSED.

IMPLEMENTATION_ACTION=Addendum commit updating the three register files in place + adding TD-00-reconciliation-srs-v12.md + this judgment record. Then proceed to TD-01.

CLOSURE_EVIDENCE=
- SRS v1.2 file at `srs/JEA-ESP2-SRS-SITE-SURVEY-001_v1.2.md` — direct read (350 lines, all 20 sections)
- SRS §version log confirms v1.2 supersedes prior versions
- SRS §header ضابط الاعتماد + §الخلاصة confirm NOT APPROVED status
- SRS §18 explicit OD-31..35 list matches TD-00 open-decision register (after this update)
- SRS §8.2 FR-SS-081..090 additions absorbed into `requirement-delta-matrix.csv`
