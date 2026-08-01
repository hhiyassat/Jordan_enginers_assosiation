RESIDUAL_ID=RES-SG00-02
TITLE=SRV-001 calculators (WellsCount, NetDepth) require JEA-signed source
OWNER=Product owner / JEA
ORIGINATING_PHASE=SG-00

الوضع=`WellsCountCalculator` + `NetDepthTable` cite meeting minutes 2026-07-26 §X/§XI as source, marked PROVISIONAL in file headers. `ExplorationRequirementMatrix` cites the JEA-signed 2025 manual (APPROVED). SG-04 rule-version rows classify these accordingly.

تحرير_محل_النزاع=Does the missing JEA signature for the two provisional calculators block target-domain start, or does it only block target-canonical publication?

السبب=Closure mandate §10 asks. Target domain implementation would build classes; publication needs JEA sign-off.

الشرط=Building target-canonical calculators does not require unsigned inputs — target classes can be built empty or with placeholder values, awaiting the JEA-signed table before promotion to APPROVED.

المانع=Manufacturing the sign-off in code (rewriting the classifier as APPROVED without evidence) would be batil.

العلة=Business-authority separation. Only JEA can approve the numeric tables.

القادح=No repository artefact carries the signed source.

الصحة=`BLOCKS_TARGET_DOMAIN_PUBLICATION_ONLY` + `BUSINESS_DECISION_STOPPED`. Coding may begin; promotion to APPROVED requires JEA signature.

الفساد=Silently converting classifications to APPROVED would be fasid.

البطلان=Fake sign-off is batil.

الأثر=Rule promotion path is well-defined: `RuleVersion` row transitions PROVISIONAL → APPROVED with a signed `source_reference` update.

البقايا=RES-SG00-02 remains OPEN pending JEA signature.

التعارض=None.

الجمع=Not needed.

الترجيح=Business authority (Tier-1) unavailable → stop that specific decision, continue safe work.

التوقف=STOPPED on rule promotion. Coding continues.

READINESS_CLASSIFICATION=BUSINESS_DECISION_STOPPED + BLOCKS_TARGET_DOMAIN_PUBLICATION_ONLY

IMPLEMENTATION_ACTION=None. Await JEA decision.

CLOSURE_EVIDENCE=SRV-001 rule promotion audit trail when signed.
