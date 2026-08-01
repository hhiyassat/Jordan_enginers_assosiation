JUDGMENT_ID=JDG-SG03-02
TITLE=Application → service-definition-version binding timing
SCOPE=architecture/service-governance/SG-03
OWNER=service-governance-remediation

الوضع=
Applications currently reference `service_definition_id`. After SG-03 they also carry `service_definition_version_id` (nullable). The mandate requires binding "no later than submission" and forbids silent switching after submit.

تحرير_محل_النزاع=
Should the binding occur at (A) application creation (draft), (B) first save/update, or (C) submission?

السبب=
Program §Phase SG-03 explicitly asks for a judgment on this timing.

الشرط=
The chosen timing must (i) never silently switch a submitted application to a newer version, (ii) never break an applicant's in-progress draft when the admin updates the schema before submit (this is the R-01 primary risk), (iii) integrate with SG-02's LENIENT fallback for legacy `status='active'` services (many services have no published version yet), (iv) not force draft applications to carry a version binding that they may never use (application abandoned before submit).

المانع=
Option (A) — bind at creation — makes every abandoned draft (a common case) hold a version reference that becomes irrelevant. It also silently prevents an applicant from benefiting from a schema improvement made before they submit. Neither is a bug per se, but it wastes storage and creates false reproducibility signal on abandoned rows.

Option (B) — bind at first save — has the same drawbacks plus subtly different semantics depending on which field is edited when.

Option (C) — bind at submit — matches the mandate ("no later than submission") precisely and produces the cleanest historical trail.

العلة=
Applicant protection. The applicant sees the CURRENT schema while drafting; on submit the currently-published version is captured as the immutable reference; from that moment forward the application resolves against the captured version, not the mutable service_definitions.schema.

القادح=
None. Option (C) satisfies all conditions.

الصحة=
Valid timing: bind at submit. Draft applications carry NULL `service_definition_version_id`. On submit, if a published version exists for the service the FK is populated; if none exists (transition window with LENIENT fallback), the FK remains null and the application is classified `LEGACY_UNVERSIONED`.

الفساد=
Binding at creation is not fasid per se — it just wastes storage.

البطلان=
Binding after submit (any post-submission auto-switching) is batil.

الأثر=
(1) `applications.service_definition_version_id` FK nullable. (2) `WorkflowEngine::submit` calls a small `ApplicationVersionBinder` that assigns the FK inside the same transaction as the STATUS_SUBMITTED transition. (3) Draft applications have null binding by design.

البقايا=
RES-SG03-02: For services with a published version, `ApplicationController::show` on a DRAFT could optionally display "the schema you see is the current schema — will be bound to version X at submit" as a governance hint. Not required for correctness. Owner: UX follow-up.

التعارض=
None between the mandate and the chosen approach.

الجمع=
Not needed — a single option (C) satisfies all conditions.

الترجيح=
Tier-4 (target architecture) + Tier-5 (runtime safety) + explicit program mandate ("no later than submission").

التوقف=
Not stopped.

EVIDENCE=
- backend/modules/JeaServices/Engine/WorkflowEngine.php:88-131 (submit — natural insertion point at line 124 after transitionTo)
- backend/modules/JeaServices/Models/Application.php (currently has no version FK)

DECISION=
Bind at submit. Draft applications carry `service_definition_version_id=NULL`. On submit, `ApplicationVersionBinder::bindOrClassifyLegacy($app)` assigns the FK if a published version exists, otherwise leaves it null and marks the application `LEGACY_UNVERSIONED` (via a computed column or a documented interpretation of "FK is null after submit").

IMPLEMENTATION_EFFECT=
One migration adds the FK. `ApplicationVersionBinder` class + WorkflowEngine::submit integration.

MIGRATION_EFFECT=
Additive. Every existing application remains `service_definition_version_id=NULL` — classified LEGACY_UNVERSIONED per JDG-SG03-03.

TEST_EVIDENCE=
Tests: draft application has null FK; submit with published version populates FK; submit with no published version leaves FK null; re-submit of the same application does not switch the FK to a newer version.

OPEN_RESIDUALS=
- RES-SG03-02 (UX hint on draft view — follow-up).
