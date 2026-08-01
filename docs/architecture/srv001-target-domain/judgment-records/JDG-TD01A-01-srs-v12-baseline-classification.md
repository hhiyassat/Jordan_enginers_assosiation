JUDGMENT_ID=JDG-TD01A-01
TITLE=SRS v1.2 baseline classification + preservation of v1.1 as historical evidence
OWNER=TD-01A
PHASE=TD-01A

الوضع=
User confirms SRS v1.2 as the current-draft target-requirements baseline. Document header + §الخلاصة both declare it non-contractual until baseline 2.0 is signed. TD-00R (commit `cd73651`) already absorbed v1.2 deltas into the requirement-delta-matrix, open-decision-register, source-register, and residual-register, and produced JDG-TD00-03. TD-01A now finalises the authority classification with the user-supplied vocabulary and preserves v1.1 as historical evidence.

تحرير_محل_النزاع=
Given SRS v1.2 is now formally the source-of-truth-draft, does its presence promote any TD-00/TD-01 classification to a higher authority tier (BUSINESS_APPROVED, IMPLEMENTATION_AUTHORIZED-for-target-rules, PUBLICATION_AUTHORIZED), or does it preserve the READY_WITH_NON_BLOCKING_RESIDUALS verdict with all publication authority still absent?

السبب=
User directive lists six authority dimensions and asserts every one is `NO` for SRS v1.2. TD-01A must record this explicitly so no downstream phase treats v1.2 as authorising anything more than draft-target-baseline classification.

الشرط=
Authority classification must be recorded per the user-supplied enum:
- `SOURCE_BASELINE_STATUS = CURRENT_DRAFT_TARGET_BASELINE`
- `DOCUMENT_STATUS = DRAFT_REVIEW`
- `CONTRACTUAL_AUTHORITY = NO`
- `BUSINESS_APPROVAL_AUTHORITY = NO`
- `FINAL_IMPLEMENTATION_AUTHORITY = NO`
- `PUBLICATION_AUTHORITY = NO`
- `PRODUCTION_AUTHORITY = NO`
- `REQUIRES_SIGNED_BASELINE_2_0 = YES`

Every rule/decision/OD status remains unchanged by this record.

المانع=
Inferring authorisation from any of: SOURCE_CONFIRMED status, presence of a formula, presence of a requirement, the word "current", numeric parity with legacy code, or use of the word "Reviewed" — user directive explicitly forbids all six.

العلة=
Business-authority integrity + the SRS v1.2 document's own self-declaration ("لا تصبح مرجعًا تعاقديًا إلا بعد ... توقيع مالك المتطلبات على خط الأساس 2.0").

القادح=
Any future TD-* phase promoting a rule to `IMPLEMENTATION_AUTHORIZED` for target execution or `PUBLICATION_AUTHORIZED` based solely on v1.2 presence would fire this قادح.

الصحة=
SRS v1.2 = `CURRENT_DRAFT_TARGET_BASELINE`. Every dimension above set to `NO`. v1.1 preserved as historical evidence (superseded per SRS §version log). Numeric parity in TD-01 remains characterisation evidence only.

الفساد=
Any classification promotion silently based on v1.2 = fasid (repairable by re-classification).

البطلان=
Publishing any target-canonical rule solely because v1.2 lists it = batil.

الأثر=
(1) Source register updated to explicitly stamp the seven authority fields. (2) Every TD-01 Target* class carries the same TARGET_DOMAIN_PROVISIONAL classification (already true; reaffirmed). (3) No OD closed by TD-01A. (4) No RuleVersion promoted. (5) v1.1 remains referenced as historical.

البقايا=
RES-TD01A-01 (informational): every rule marked `SOURCE_CONFIRMED` per SRS v1.2 remains `BUSINESS_APPROVAL_STATUS=UNVERIFIED` — same effect as pre-existing RES-TD00-02 / RES-TD00-01b. TD-01A creates no new blocking residual from this classification alone.

التعارض=
None. User directive + SRS §self-declaration + all prior TD registers converge on the same status.

الجمع=
Not needed — all sources concur.

الترجيح=
Tier-1 evidence (signed OD-Closures) still absent. Tier-2 SRS body available at v1.2 but self-declares non-contractual. Tier-4 (approved architecture SG-*/RC-*) unchanged. Verdict unchanged: `READY_WITH_NON_BLOCKING_RESIDUALS`.

التوقف=
STOPPED for any rule promotion beyond SOURCE_CONFIRMED / IMPLEMENTATION_AUTHORIZED-for-structural-skeleton. Continues for TD-* structural phases within the JDG-TD00-02 authorised scope.

READINESS_CLASSIFICATION=unchanged (`READY_WITH_NON_BLOCKING_RESIDUALS`).

IMPLEMENTATION_ACTION=Update `td-00/source-register.md` with the seven authority fields for SRS v1.2. Add explicit table to TD-01A report. No code changes required from this judgment alone (see JDG-TD01A-02 for the architectural residual).

CLOSURE_EVIDENCE=
- SRS v1.2 §header ضابط الاعتماد
- SRS v1.2 §الخلاصة
- SRS v1.2 §version log (v1.2 supersedes v1.1/v1.0/v0.1; v2.0 = future signed baseline)
- User directive TD-01A instruction
