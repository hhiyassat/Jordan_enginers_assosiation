RESIDUAL_ID=RES-SG00-03
TITLE=Each `_UNAPPROVED` service classification pending JEA sign-off
OWNER=Product / JEA
ORIGINATING_PHASE=SG-00

الوضع=Corrected maturity CSV (SG-00) marks all 22 real-fee services as `REAL_UNAPPROVED` (technically specified from manuals — not JEA-signed). All 7 real-workflow services as `SOURCE_DERIVED_UNAPPROVED`. All seeded document services (10 DRW-P + SRV-001) as `FAMILY_INHERITED` or `SERVICE_SPECIFIC_UNAPPROVED`.

تحرير_محل_النزاع=Does the missing JEA sign-off block target-domain start (only SRV-001 in scope), or only publication of any given service?

السبب=Closure mandate §10 asks. Target domain scope is SRV-001 only.

الشرط=Target-domain SRV-001 classes can be built against the existing schema without a fresh JEA sign-off. Publication of the target-canonical SRV-001 requires signed UAT.

المانع=Same as RES-SG00-02.

العلة=Business-authority separation.

القادح=None absent in repository.

الصحة=`BLOCKS_TARGET_DOMAIN_PUBLICATION_ONLY` for the SRV-001 target-canonical version. Doesn't block target-domain start.

الفساد=Same as RES-SG00-02.

البطلان=Same.

الأثر=Each service `uat_status` transitions NOT_SUBMITTED → PENDING → APPROVED via `ServicePublicationPolicy` maker-checker flow.

البقايا=Per-service sign-off audit.

التعارض=None.

الجمع=Not needed.

الترجيح=Tier-1 unavailable → stop publication, continue coding.

التوقف=STOPPED per service. Coding continues.

READINESS_CLASSIFICATION=BUSINESS_DECISION_STOPPED + BLOCKS_TARGET_DOMAIN_PUBLICATION_ONLY

IMPLEMENTATION_ACTION=None. Per-service JEA decision.

CLOSURE_EVIDENCE=`uat_signed_at` populated per service.
