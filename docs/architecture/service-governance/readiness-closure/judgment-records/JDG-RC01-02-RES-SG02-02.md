RESIDUAL_ID=RES-SG02-02
TITLE=Wire ServiceAvailabilityPolicy into application-creation, submission, payment, certificate entry points
OWNER=this closure program (RC-02)
ORIGINATING_PHASE=SG-02

الوضع=
Foundation SG-02 wired `ServiceAvailabilityPolicy` into `ServiceCatalogController::{index, show}`. Four other controllers still rely exclusively on the legacy `where('status','active')` filter or don't guard at all: `ApplicationController::store` (creation), `ApplicationController::submit` (submission), `PaymentsController::initiate` (payment demand), `CertificatesController` (PDF generation). Because every one of the 57 seeded services carries `status='active'` + `publication_status='NOT_PUBLISHED'`, LENIENT default mode keeps them visible AND lets applicants create/submit/pay/receive certificates against them.

تحرير_محل_النزاع=
Do the four unguarded controllers block target-domain start (target SRV-001 must depend on a trustworthy availability verdict), or can target-domain begin with the verdict wired only at the catalog surface?

السبب=
Closure mandate §5 explicitly asks. Target-domain SRV-001 will replace `LegacySrv001SubmissionPolicy` with a canonical version. If the calling use case still consults only `where('status','active')` at submission, the whole activation lifecycle SG-01/SG-02 constructed becomes advisory — applicants can submit against RETIRED/SUSPENDED services, or against a newly-created unapproved service the admin drafts.

الشرط=
The wiring must (i) preserve historical viewing on retired/suspended services (mandate: "Historical viewing must remain available"), (ii) preserve payment callbacks + historical certificate issuance where a valid payment demand already exists (mandate: "Do not block payment callbacks merely because the service was later suspended when a valid payment demand already exists"), (iii) not block existing draft/submitted applications from progressing, (iv) reject NEW creation/submission when the verdict denies, (v) route the failure through a typed 4xx response, not a 500.

المانع=
Strict-mode enforcement of `publication_status='PUBLISHED'` on ALL five entry points would break every existing draft (draft.serviceDefinition.publication_status = NOT_PUBLISHED for all 57 services). Payment callback blocking on suspension would break existing payment obligations. The LENIENT-mode preference order from JDG-SG02-01 handles the first case; the second requires a distinction between "the operation depends on the service being currently active" versus "the operation completes a prior commitment".

العلة=
Governance completeness (four unguarded surfaces expose the whole SG-02 investment as advisory) + applicant protection (unapproved services must not accept new commitments) + historical integrity (already-submitted applications and already-authorised payments must complete).

القادح=
The current 4 unguarded controllers, verified by grep — see EVIDENCE below.

الصحة=
Valid closure: (a) `ApplicationController::store` — check `applicationCreationAllowed`. (b) `ApplicationController::submit` — check `submissionAllowed`. (c) `PaymentsController::initiate` — check `paymentAllowed`. (d) `CertificatesController::downloadPdf*` — check `certificateAllowed`. (e) Admin bypass: when the caller is admin AND the operation is inspection (view existing app details, not create), allow. (f) Payment callback (`PaymentCallbackController::handle`) is a SYSTEM callback, not a NEW commitment — it completes a payment demand created by `PaymentsController::initiate` (which was already gated); it must NOT block on service suspension.

الفساد=
Wiring only three of the four would be fasid — the ungated one leaks the invariant.

البطلان=
Adding a naive strict check to submission that blocks in-flight drafts is batil — breaks existing tests + applicant obligations.

الأثر=
(1) 4 controllers modified with policy consultation. (2) `PaymentCallbackController` explicitly EXCLUDED (documented — it processes a prior obligation). (3) `CertificatesController` allows historical certificate access via `certificateAllowed=true` on RETIRED (already true per SG-02 policy) — but blocks certificate generation when service is unavailable AND the application does not already have an issued certificate. (4) New feature tests per controller.

البقايا=
None new — the closure fully wires the four entry points. If target-domain needs a different verdict for the target-canonical SRV-001, RES-SG03-01 (extension-declaration snapshotting) opens a distinct question.

التعارض=
Foundation SG-02 mandate ("wire five integration points") vs foundation actual delivery (one wired). Reconciled by this closure — the mandate is now fully satisfied.

الجمع=
Not needed — every entry point in the mandate list is addressed.

الترجيح=
Tier-4 (target architecture) + Tier-5 (runtime safety). No lower-tier evidence to weigh against.

التوقف=
Not stopped.

READINESS_CLASSIFICATION=BLOCKS_TARGET_DOMAIN_START (before closure) → CLOSED (after RC-02 implementation).

IMPLEMENTATION_ACTION=Wire ServiceAvailabilityPolicy into ApplicationController::{store,submit}, PaymentsController::initiate, CertificatesController::downloadPdf and downloadPdfAuthenticated (leave PaymentCallbackController explicitly unguarded — documented). Add focused tests per controller.

CLOSURE_EVIDENCE=
- Controller diffs in RC-02 commit.
- New feature tests: RC-02 file coverage.
- Regression sweep pass (docs in RC-02 report).

EVIDENCE=
- backend/modules/JeaServices/Http/Controllers/ApplicationController.php — no policy consultation at `store`, `submit`
- backend/modules/JeaServices/Http/Controllers/PaymentsController.php — no policy consultation at `initiate`
- backend/modules/JeaServices/Http/Controllers/CertificatesController.php — no policy consultation at PDF paths
- backend/modules/JeaServices/Http/Controllers/PaymentCallbackController.php — intentionally excluded (system callback)
